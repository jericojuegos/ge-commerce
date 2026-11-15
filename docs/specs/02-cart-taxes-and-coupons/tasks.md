# ✅ Feature Tasks: Session Cart, Coupons, Tax & Shipping Calculators (`02-cart-taxes-and-coupons`)

> **Phase / Wave:** `Phase 1 (Wave 2)` · **Branch / Owner:** `feat/02-cart-taxes-and-coupons` · **Status:** `[>]` In Progress  
> **Depends On:** `01-catalog-and-skus` (`[x]`) · **Touches:** `src/Modules/Cart/, src/Modules/Coupon/, src/Shared/Contracts/, resources/js/admin/coupons/, tests/Unit/Modules/`  
> **Spec Links:** [`requirements.md`](./requirements.md) · [`design.md`](./design.md) · [`uat.md`](./uat.md) · [`design-reference/`](./design-reference/)

---

## 🔄 Implementation Tasks

### 1. Shared Contracts & Coupon Pure Domain/App + Unit Tests (`⚡ BATCH`: Pure PHP Coupon Core)
- [x] **1.1** `[BE]` Create cross-module ports (`SkuSnapshot`, `CatalogLookupInterface`, `CouponEvaluationResult`, `CouponEvaluatorInterface`) in `src/Shared/Contracts/`
  - Zero WordPress imports; preserves module isolation between `Catalog`, `Coupon`, and `Cart`.
  - _Requirements: 1.2, 2.1_
- [x] **1.2** `[BE]` Implement pure Coupon Domain (`DiscountType`, `Coupon`, `CouponRepositoryInterface`) and Application (`SaveCouponDTO`, `SaveCouponHandler`, `ListCouponsHandler`, `GetCouponHandler`, `DeleteCouponHandler`, `CouponEvaluator`) in `src/Modules/Coupon/`
  - Enforce uppercase code, integer `amount_value`, clamped discount `<= subtotal_cents`, `min_order_cents`, `usage_limit`, and UTC expiry.
  - _Requirements: 1.1, 1.2, 1.3_
- [x] **1.3** `[BE]` Write & run headless unit tests in `tests/Unit/Modules/Coupon/CouponTest.php`
  - Cover Property 1 (fixed & bps discount + clamping at subtotal) and Property 2 (expired, usage limit, min order, invalid code/negative/over-10000 bps rejection).
  - _Validates: Property 1, Property 2 (Requirements 1.1, 1.2, 1.3)_
- [x] **1.4** `[REVIEW]` **Coupon Core Checkpoint:** Run `php -l` on all new PHP files and `vendor/bin/phpunit --testsuite Unit`.

### 2. Cart, Tax & Shipping Pure Domain/App + Unit Tests (`⚡ BATCH`: Pure PHP Cart & Calculators)
- [x] **2.1** `[BE]` Implement pure Cart Domain ValueObjects (`TaxZone`, `ShippingZone`, `CartTotals`), Entities (`CartItem`, `Cart`), Services (`TaxCalculator`, `ShippingCalculator`), and Ports (`CartSessionRepositoryInterface`, `ZoneConfigRepositoryInterface`) in `src/Modules/Cart/Domain/`
  - Zero WordPress imports; pure integer basis-point tax & shipping math.
  - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2_
- [x] **2.2** `[BE]` Implement Cart Application DTOs (`CartItemInputDTO`, `QuotePreviewDTO`) and Handlers (`GetCartHandler`, `UpdateCartHandler`, `MergeGuestCartHandler`, `CalculateCartTotalsHandler`, `SaveZoneConfigHandler`) in `src/Modules/Cart/Application/`
  - _Requirements: 1.2, 1.3, 2.1, 2.2, 2.3, 3.1, 3.2_
- [x] **2.3** `[BE]` Write & run headless unit tests in `tests/Unit/Modules/Cart/CartAndTaxTest.php`
  - Cover cart item stock checks, guest-to-user merge (item sum capped at `available_qty`, coupon adoption, guest session cleanup), expired session handling, tax specificity (`country+region > country+* > *+*`), and shipping free-over threshold.
  - _Validates: Property 1, Property 2 (Requirements 1.2, 1.3, 2.1, 2.2, 2.3, 3.1, 3.2)_
- [x] **2.4** `[REVIEW]` **Cart Core Checkpoint:** Run `php -l` and `vendor/bin/phpunit --testsuite Unit`.

### 3. Infrastructure Adapters, REST Controllers & ServiceProviders (`⚡ BATCH`: WP Adapters & DI)
- [x] **3.1** `[BE]` Implement `CatalogLookupAdapter` in `src/Modules/Catalog/Infrastructure/Adapters/CatalogLookupAdapter.php` and bind in `CatalogServiceProvider.php`
  - _Requirements: 2.1_
- [x] **3.2** `[BE]` Implement `WpdbCouponRepository`, `CouponRestController`, and `CouponServiceProvider` in `src/Modules/Coupon/`
  - Enforce `current_user_can('manage_options') || current_user_can('manage_ge_commerce')` on `/ge-commerce/v1/coupons*`.
  - _Requirements: 1.1, 4.1_
- [x] **3.3** `[BE]` Implement `WpdbCartSessionRepository`, `WpZoneConfigRepository`, `CartRestController`, and `CartServiceProvider` in `src/Modules/Cart/`, and register both providers in `src/Core/Plugin.php`
  - Wire `wp_login` guest-to-user merge hook; enforce capability guard on `/tax-shipping-zones` & `/cart/quote-preview`, and `is_user_logged_in()` on `/cart/merge`.
  - _Requirements: 2.1, 2.2, 4.1, 4.2_
- [x] **3.4** `[REVIEW]` **Backend Integration Checkpoint:** Run `php -l` on all touched PHP files and `vendor/bin/phpunit --testsuite Unit`.

### 4. React Admin Coupons, Tax Zones & Quote Simulator UI (`⚡ BATCH`: Admin SPA)
- [ ] **4.1** `[FE]` Create `resources/js/admin/coupons/types.ts` and `resources/js/admin/coupons/api.ts` using `@wordpress/api-fetch`
  - _Requirements: 4.3_
- [ ] **4.2** `[FE]` Build `resources/js/admin/coupons/CouponModal.tsx` and `resources/js/admin/coupons/CouponsAndTaxPage.tsx` using `@wordpress/components`, `@tanstack/react-query`, and `docs/foundation/design-system.md` tokens
  - Include Coupons CRUD table, Tax & Shipping Zones editor, and Live Quote Simulator card.
  - _Requirements: 1.1, 1.3, 3.1, 3.2, 4.3_
- [ ] **4.3** `[FE]` Wire `<CouponsAndTaxPage />` tab into `resources/js/admin/index.tsx`
  - _Requirements: 4.3_
- [ ] **4.4** `[REVIEW]` **Frontend Build Checkpoint:** Run `npx tsc --noEmit && npm run build` — must compile with zero TypeScript/webpack errors.

### 5. Final Verification & UAT
- [ ] **5.1** `[REVIEW]` **Final Feature Checkpoint:** Run `/verify` (`vendor/bin/phpunit --testsuite Unit`, `npx tsc --noEmit`, `npm run build`, `php -l`, WP-CLI & live Browser QA if reachable) → triggers `/ship`.

---

## 🔀 Task Dependency Graph (Intra-Feature Waves)

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["1.4"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3"] },
    { "id": 3, "tasks": ["2.4"] },
    { "id": 4, "tasks": ["3.1", "3.2", "3.3"] },
    { "id": 5, "tasks": ["3.4"] },
    { "id": 6, "tasks": ["4.1", "4.2", "4.3"] },
    { "id": 7, "tasks": ["4.4", "5.1"] }
  ]
}
```

---

## 🐛 Ad-Hoc / Small Enhancements
*(None)*

---

## 🧪 Feature Verification & UAT Gate
- [ ] **Level 1 (Automated Build & Unit Tests):** PHP syntax check, PHPUnit unit tests, TypeScript check, and `npm run build` pass with zero errors
- [ ] **Level 2 & 3 (UAT & Visual Match):** `/verify` completed in [`uat.md`](./uat.md)

---

## 🛑 Blocked / Waiting
*(None)*

---

## 📝 Feature Activity Log

| Date | Task | Action & Files Touched |
| :--- | :--- | :--- |
| 2026-11-15 | Init | Expanded `/spec 02-cart-taxes-and-coupons` (`requirements.md`, `design.md`, `tasks.md`, `uat.md`, `stitch-prompt.md`). |
| 2026-11-15 | 1.1-1.4 | Implemented Shared cross-module ports (`src/Shared/Contracts/`) & pure Coupon Domain/App + `CouponTest.php` (15/15 PHPUnit pass). |
| 2026-11-15 | 2.1-2.4 | Implemented pure Cart, Tax & Shipping Domain/App + `CartAndTaxTest.php` (19/19 PHPUnit pass). |
| 2026-11-15 | 3.1-3.4 | Implemented `CatalogLookupAdapter`, `WpdbCouponRepository`, `CouponRestController`, `WpdbCartSessionRepository`, `WpZoneConfigRepository`, `CartRestController`, providers, and `Plugin.php` wiring (`php -l` & 19/19 PHPUnit pass). |
