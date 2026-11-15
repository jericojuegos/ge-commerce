# 📋 Feature Requirements: Session Cart, Coupons, Tax & Shipping Calculators (`02-cart-taxes-and-coupons`)

> **Feature Slug:** `02-cart-taxes-and-coupons` · **Phase / Wave:** `Phase 1 (Wave 2)`  
> **Depends On:** `01-catalog-and-skus` (`[x]`)  
> **Branch / Owner:** `feat/02-cart-taxes-and-coupons` · **Status:** `[>]` In Progress  
> **Specs:** [`design.md`](./design.md) · [`tasks.md`](./tasks.md) · [`uat.md`](./uat.md)

---

## 1. Introduction & Overview

`02-cart-taxes-and-coupons` implements the persistent Guest and WordPress-User Session Cart engine (`wp_gec_sessions`), deterministic single-store Coupon evaluation (`wp_gec_coupons`), and pure-PHP Tax & Shipping Zone calculators for **GE Commerce**. All monetary arithmetic uses integer minor units (`Money` in `price_cents` and basis points `bps`, where `10000 bps = 100.00%`) so cart subtotals, discounts, taxes, shipping, and grand totals are 100% immune to IEEE 754 floating-point drift.

In addition, this feature provides an admin-facing **Coupons & Tax Zones** management tab inside the GE Commerce React Admin SPA (`resources/js/admin/coupons/`) so store managers can create/edit/delete coupons, configure regional tax and shipping rates, and test live quote breakdowns.

### Assumptions (Flagged for Review)

- **A1 (Currency & Integer Basis Points):** Phase 1 uses single-store currency `USD` with integer `cents` (`int >= 0`) and basis points (`bps`, `int >= 0`, e.g., `825` = `8.25%`) via `Money::percentageBasisPoints()`. **Confirmed.**
- **A2 (Taxable Base):** Tax is calculated on the post-discount subtotal (`max(0, subtotal_cents - discount_cents)`), and shipping is added after tax unless future jurisdiction rules override. **Confirmed.**
- **A3 (Tax & Shipping Zone Storage):** Tax and shipping zone rules are stored in WordPress options (`ge_commerce_tax_zones`, `ge_commerce_shipping_zones`) via an infrastructure adapter implementing `ZoneConfigRepositoryInterface`, keeping the 7 core custom tables in `Migrator.php` unchanged. **Confirmed.**
- **A4 (Session Identity & Guest Merge):** Authenticated users are always resolved from `get_current_user_id()`; guest carts use a 48-char cryptographic `session_key` stored in an `HttpOnly`, `SameSite=Lax` cookie (`gec_session_key`) or `X-GEC-Session` header, and merge into the user's cart on `wp_login` or `POST /cart/merge`. **Confirmed.**

### Domain Glossary

- **Cart Session (`wp_gec_sessions`):** Persistent cart state identified by `session_key` (or `user_id` for logged-in shoppers), storing line items and destination metadata in `cart_json`, optional `coupon_code`, and UTC `expires_at`.
- **Coupon (`wp_gec_coupons`):** Discount rule with normalized uppercase `code`, `discount_type` (`fixed_cents` or `percent_bps`), `amount_value`, `min_order_cents`, `usage_limit`, `used_count`, and optional `expires_at`.
- **Basis Points (`bps`):** Integer percentage unit where `10000 bps = 100%` and `100 bps = 1%` (e.g., `825 bps = 8.25%`).
- **Tax Zone / Shipping Zone:** Geographic rule matched by specificity (`country + region` > `country + *` > `* + *`) to resolve tax rate (`rate_bps`) or shipping cost (`flat_rate_cents`, `free_over_cents`).

---

## 2. Requirements & EARS Acceptance Criteria

### Requirement 1: Pure Integer Coupon Validation & Discount Clamping
**User Story:** As a store owner, I want deterministic fixed-cent and percentage coupons with minimum-order, usage-limit, and expiration rules, so that discounts are always accurate and never produce negative order totals.

#### Acceptance Criteria
1. **1.1:** WHEN a valid coupon (`fixed_cents` or `percent_bps`) is created or updated, THE SYSTEM SHALL normalize `code` to uppercase trimmed alphanumeric/dash characters (`<= 64` chars), enforce `amount_value >= 0` (and `<= 10000` for `percent_bps`), `min_order_cents >= 0`, and persist to `wp_gec_coupons`.
2. **1.2:** WHEN a coupon is applied to a cart whose `subtotal_cents >= min_order_cents` and the coupon is neither expired nor at `usage_limit`, THE SYSTEM SHALL compute `discount_cents` in integer cents (`fixed_cents` or `Money::percentageBasisPoints()`), clamping `discount_cents` so it NEVER exceeds `subtotal_cents`.
3. **1.3:** IF a coupon code is empty, duplicate, negative in value, exceeds `10000 bps` for percentage discounts, fails `min_order_cents`, is expired, or has `used_count >= usage_limit`, THEN THE SYSTEM SHALL reject coupon creation/application without mutating state and return a clear validation error.

---

### Requirement 2: Session Cart Persistence, Stock Check & Guest-to-User Merge
**User Story:** As a shopper, I want my cart items and coupon to persist across page loads and merge automatically when I log in, so that I never lose my shopping bag.

#### Acceptance Criteria
1. **2.1:** WHEN a shopper adds, updates, or removes a SKU in the cart, THE SYSTEM SHALL look up current SKU pricing and `available_qty` through `CatalogLookupInterface` (without importing Catalog module internals), reject quantities `<= 0` or exceeding `available_qty`, and persist the cart in `wp_gec_sessions`.
2. **2.2:** WHEN a guest shopper with an active guest session logs into a WordPress user account (`wp_login` hook or `POST /cart/merge`), THE SYSTEM SHALL merge guest line items into the user's cart (summing matching `sku_id` quantities capped at `available_qty`), adopt the guest `coupon_code` if the user cart has none, persist the merged user cart, and delete the guest session.
3. **2.3:** IF a cart session has expired (`expires_at <= nowUtc`), THEN THE SYSTEM SHALL treat the expired session as empty and purge stale session records.

---

### Requirement 3: Regional Tax & Shipping Zone Calculators
**User Story:** As a store owner, I want regional tax rates and shipping zone rules calculated in integer cents, so that checkout totals match regional regulations without rounding bugs.

#### Acceptance Criteria
1. **3.1:** WHEN cart totals are computed for a destination `(country, region)` and `shipping_method`, THE SYSTEM SHALL resolve the most specific matching Tax Zone (`country+region` > `country+*` > `*+*`) and compute `tax_cents = taxable_subtotal->percentageBasisPoints(rate_bps)` where `taxable_subtotal = max(0, subtotal_cents - discount_cents)`.
2. **3.2:** WHEN cart totals are computed for a non-empty cart, THE SYSTEM SHALL resolve the matching Shipping Zone (`country+region+method` > `country+*+method` > `*+*+method`), returning `0` if `free_over_cents !== null && taxable_subtotal_cents >= free_over_cents` (or cart is empty), otherwise `flat_rate_cents`, and compute `total_cents = taxable_subtotal_cents + tax_cents + shipping_cents`.

---

### Requirement 4: REST API Security & React Admin Coupons/Tax Manager
**User Story:** As a store manager, I want a secure REST API and React Admin screen to manage coupons, configure tax/shipping zones, and simulate cart totals.

#### Acceptance Criteria
1. **4.1:** IF an unauthenticated user or a user lacking `manage_options` / `manage_ge_commerce` calls `/ge-commerce/v1/coupons*`, `/ge-commerce/v1/tax-shipping-zones`, or `/ge-commerce/v1/cart/quote-preview`, THEN THE SYSTEM SHALL deny the request with HTTP `401`/`403` before executing any handler or DB query.
2. **4.2:** IF an unauthenticated user calls `POST /ge-commerce/v1/cart/merge`, THEN THE SYSTEM SHALL deny the request with HTTP `401`/`403`.
3. **4.3:** WHEN the store manager opens **WP Admin → GE Commerce → Coupons & Tax**, THE SYSTEM SHALL render the Coupons table (with formatted `Intl.NumberFormat` or `%` values, usage stats, status badge, Add/Edit/Delete modal), Tax & Shipping Zones editor, and a live Quote Simulator card matching `docs/foundation/design-system.md`.

---

## 3. UI & Entrypoint Locations

- **Backend Modules:** `src/Modules/Coupon/`, `src/Modules/Cart/`, `src/Shared/Contracts/`
- **Admin UI Tab:** `resources/js/admin/coupons/` mounted inside `resources/js/admin/index.tsx` (`Coupons & Tax` tab)
- **Design Reference:** `docs/specs/02-cart-taxes-and-coupons/design-reference/stitch-prompt.md` & `docs/foundation/design-system.md`

---

## 4. Non-Goals (Out of Scope for This Feature)

- **NG1:** Do NOT reserve/decrement `wp_gec_skus.available_qty` or create `wp_gec_orders` rows (belongs to `03-checkout-orders-and-hooks`).
- **NG2:** Do NOT implement payment gateway charges or Stripe/PayPal webhooks (belongs to `04-payment-gateways-and-ai`).
- **NG3:** Do NOT add new tables to `Migrator.php` (must preserve the 7 core `wp_gec_*` tables verified in `MoneyTest.php`).

---

## 5. 📝 Revision History

| Rev | Date | Changes (Added / Changed / Removed) | Reason / User Feedback |
| :--- | :--- | :--- | :--- |
| `v1.0` | `2026-11-15` | Expanded full EARS requirements (`Req 1.1`–`4.3`), glossary, and assumptions from starter spec. | Pre-build `/spec` expansion for Wave 2. |
