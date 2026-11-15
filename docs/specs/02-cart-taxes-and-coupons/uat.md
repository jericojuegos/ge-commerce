# 🧪 Feature UAT & Verification: Session Cart, Coupons, Tax & Shipping Calculators (`02-cart-taxes-and-coupons`)

> **Feature Slug:** `02-cart-taxes-and-coupons` · **Spec Links:** [`requirements.md`](./requirements.md) · [`design.md`](./design.md) · [`tasks.md`](./tasks.md) · [`design-reference/`](./design-reference/)  
> **Overall Status:** `[x]` Passed · **Verified Date:** `2026-11-15`

---

## Level 1 — Automated Terminal, Unit & Build Checks

- [x] **PHP Syntax Check:** `php -l` passed with zero errors on all 62 PHP files in `src/` and `tests/`.
- [x] **Stack Hard-Failure Audit:** Fresh-context `Cart and Coupon Blind Diff Auditor` (`research` subagent) reported `✅ 0 P0/P1 Findings` (pure Domain/Application, zero cross-module internal imports, `$wpdb->prepare()`, `current_user_can()`, zero-float integer cents & basis points).
- [x] **Unit Tests (Tier 1 — Mandatory):** `vendor/bin/phpunit --testsuite Unit` passed (`19 tests, 87 assertions`).
- [x] **Frontend Build:** `tsc --noEmit` and `npm run build` compiled `build/index.tsx.js` (`61.9 KiB`) with zero errors.

---

## Level 2 — Functional & EARS Requirement Verification

| Check ID | Requirement Ref | Scenario (WHEN / IF) | Expected Outcome (THE System SHALL) | Evidence / Proof (`test:line`, CLI output, or `./evidence/*.png`) | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-1.1** | `Req 1.1, 1.2` | Valid `fixed_cents` & `percent_bps` coupons applied | Computes integer discount clamped at `subtotal_cents` | `CouponTest.php:L110-L157` PASS + `wp eval-file` DB round-trip (`Coupon:SMOKETEST20 ClampedDiscount:1500`, `CouponDeleted:true`) | `[x]` |
| **UAT-1.3** | `Req 1.3` | Invalid/expired/over-limit/below-min coupon submitted | Rejects without state mutation, returns error | `CouponTest.php:L159-L216` PASS (`saveCalls === 0/1`, expired/below-min/exhausted rejected) | `[x]` |
| **UAT-2.1** | `Req 2.1, 2.3` | Cart add/update/remove & expired session check | Validates stock via `CatalogLookupInterface`, persists `wp_gec_sessions`, treats expired as empty | `CartAndTaxTest.php:L196-L212` PASS + `wp eval-file` DB round-trip (`SessionItems:1 Subtotal:5000`, `SessionDeleted:true`) | `[x]` |
| **UAT-2.2** | `Req 2.2` | Guest cart merged into user cart on login | Sums matching SKU qty (capped at stock), adopts coupon, deletes guest session | `CartAndTaxTest.php:L166-L194` PASS (3+4 capped at 5 stock, coupon `TENOFF` adopted, guest session deleted) | `[x]` |
| **UAT-3.1** | `Req 3.1, 3.2` | Tax & shipping calculated for `(country, region, method)` | Matches most specific zone, computes integer basis-point tax & shipping free-over threshold | `CartAndTaxTest.php:L118-L164` PASS (US-CA `825` bps = `330`c, US-* `600` bps, free shipping `>= 7500`c) | `[x]` |
| **UAT-4.1** | `Req 4.1, 4.2` | Unauthenticated / unauthorized call to `/coupons`, `/tax-shipping-zones`, `/cart/quote-preview`, `/cart/merge` | Denies with HTTP `401`/`403` before handler execution | `Invoke-WebRequest http://wp-ecommerce.test/?rest_route=/ge-commerce/v1/coupons` (`403`), `/tax-shipping-zones` (`403`), `POST /cart/quote-preview` (`403`), `POST /cart/merge` (`401`) | `[x]` |
| **UAT-4.3** | `Req 4.3` | Admin opens `Coupons & Tax` tab, creates coupon, updates zones, runs simulator | Persists coupon & zones across reload, renders integer-formatted quote breakdown | Live Browser QA (`http://wp-ecommerce.test/wp-admin/admin.php?page=ge-commerce`): created `SAVE20` ($20.00 off, $10.00 min), ran simulator ($49.99 - $20.00 + $2.47 tax + $5.99 ship = $38.45), verified reload persistence & cleanup, 0 console/network errors, `NO_DEBUG_LOG`, [`./evidence/02-coupons-tax-qa-final.png`](./evidence/02-coupons-tax-qa-final.png) | `[x]` |

---

## Level 3 — Visual Fidelity (`design-reference/`), Evidence Pack & Human UX Sign-Off

- [x] **Design System Match:** Uses `docs/foundation/design-system.md` tokens (`#0F172A`, `#2563EB`, `#F8FAFC`, `#E2E8F0`, status badges `#16A34A` / `#D97706` / `#DC2626`).
- [x] **Double-Submit & Empty State:** Submit buttons disable while in flight (`isSaving` / `isPending`); empty coupon state renders cleanly with `Create your first coupon` CTA.
- [x] **Visual Evidence Captured:** Screenshot saved to [`./evidence/02-coupons-tax-qa-final.png`](./evidence/02-coupons-tax-qa-final.png).
- [x] **Sign-Off:** Approved to mark `[x]` in `docs/planning/roadmap.md` via `/ship`.

---

## 🧭 Manual Testing Walkthrough (Step-by-Step Human QA)

### 1. Prerequisites & Local Setup
- **Local Site URL:** `http://wp-ecommerce.test/wp-admin/admin.php?page=ge-commerce`
- **Test Account:** Logged-in WordPress Administrator (`manage_options` / `manage_ge_commerce` capability).

### 2. Happy-Path Walkthrough (`Req 1.1–1.2, 3.1–3.2, 4.3`)
1. **Open:** Navigate to **WP Admin → GE Commerce** and click the **`Coupons & Tax`** tab.
2. **Create Coupon:** Click **`+ Add Coupon`**, fill **Coupon Code** (`SAVE20`), **Discount Type** (`Fixed Amount (cents)`), **Discount Amount (cents)** (`2000`), **Minimum Order (cents)** (`1000`), then click **`Save Coupon`**.
3. **Expected Coupon Result:** Modal closes and `SAVE20` appears in the table with `$20.00`, `$10.00` min order, and green `ACTIVE` badge.
4. **Live Quote Simulator:** In **Live Quote Simulator**, enter **Subtotal (cents)** `4999`, **Coupon Code** `SAVE20`, **Country** `US`, **Region / State** `CA`, **Shipping Method** `Standard`, and click **`Calculate Quote`**.
5. **Expected Quote Result:** Breakdown shows Subtotal `$49.99`, Discount `-$20.00`, Tax `$2.47`, Shipping `$5.99`, Total `$38.45`.
6. **Persistence Check & Cleanup:** Refresh (`F5`), switch back to **`Coupons & Tax`**, confirm `SAVE20` persists from `wp_gec_coupons`, then click **`Delete`** to clean up.

### 3. Edge Cases & Error Validation (`Req 1.3, 4.1`)
1. **Invalid Percentage (`> 10000 bps`):** Open **`+ Add Coupon`**, select `Percentage (basis points)`, enter `12000`, submit → **Expected:** Inline error Notice inside modal (`HTTP 422`), inputs preserved.
2. **Unauthorized Access:** Request `http://wp-ecommerce.test/?rest_route=/ge-commerce/v1/coupons` unauthenticated → **Expected:** `HTTP 403` (`rest_forbidden`).
