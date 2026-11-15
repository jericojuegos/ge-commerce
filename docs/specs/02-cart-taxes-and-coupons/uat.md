# 🧪 Feature UAT & Verification: Session Cart, Coupons, Tax & Shipping Calculators (`02-cart-taxes-and-coupons`)

> **Feature Slug:** `02-cart-taxes-and-coupons` · **Spec Links:** [`requirements.md`](./requirements.md) · [`design.md`](./design.md) · [`tasks.md`](./tasks.md) · [`design-reference/`](./design-reference/)  
> **Overall Status:** `[ ]` Pending · **Verified Date:** `YYYY-MM-DD`

---

## Level 1 — Automated Terminal, Unit & Build Checks

- [ ] **PHP Syntax Check:** `php -l` passes with zero errors on all touched PHP files.
- [ ] **Stack Hard-Failure Audit:** Pure Domain/Application (no WP calls in `Domain/` or `Application/`), `$wpdb->prepare()`, `current_user_can()` + nonce, zero cross-module internal imports, zero-float integer cents.
- [ ] **Unit Tests (Tier 1 — Mandatory):** `vendor/bin/phpunit --testsuite Unit` passes for all `CouponTest` and `CartAndTaxTest` cases.
- [ ] **Frontend Build:** `npx tsc --noEmit && npm run build` compiles with zero errors.

---

## Level 2 — Functional & EARS Requirement Verification

| Check ID | Requirement Ref | Scenario (WHEN / IF) | Expected Outcome (THE System SHALL) | Evidence / Proof (`test:line`, CLI output, or `./evidence/*.png`) | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-1.1** | `Req 1.1, 1.2` | Valid `fixed_cents` & `percent_bps` coupons applied | Computes integer discount clamped at `subtotal_cents` | Pending | `[ ]` |
| **UAT-1.3** | `Req 1.3` | Invalid/expired/over-limit/below-min coupon submitted | Rejects without state mutation, returns error | Pending | `[ ]` |
| **UAT-2.1** | `Req 2.1, 2.3` | Cart add/update/remove & expired session check | Validates stock via `CatalogLookupInterface`, persists `wp_gec_sessions`, treats expired as empty | Pending | `[ ]` |
| **UAT-2.2** | `Req 2.2` | Guest cart merged into user cart on login | Sums matching SKU qty (capped at stock), adopts coupon, deletes guest session | Pending | `[ ]` |
| **UAT-3.1** | `Req 3.1, 3.2` | Tax & shipping calculated for `(country, region, method)` | Matches most specific zone, computes integer basis-point tax & shipping free-over threshold | Pending | `[ ]` |
| **UAT-4.1** | `Req 4.1, 4.2` | Unauthenticated / unauthorized call to `/coupons`, `/tax-shipping-zones`, `/cart/merge` | Denies with HTTP `401`/`403` before handler execution | Pending | `[ ]` |
| **UAT-4.3** | `Req 4.3` | Admin opens `Coupons & Tax` tab, creates coupon, updates zones, runs simulator | Persists coupon & zones across reload, renders integer-formatted quote breakdown | Pending | `[ ]` |

---

## Level 3 — Visual Fidelity (`design-reference/`), Evidence Pack & Human UX Sign-Off

- [ ] **Design System Match:** Uses `docs/foundation/design-system.md` tokens (`#0F172A`, `#2563EB`, `#F8FAFC`, `#E2E8F0`, status badges `#16A34A` / `#D97706` / `#DC2626`).
- [ ] **Double-Submit & Empty State:** Submit buttons disable while in flight; empty coupon/zone states render cleanly.
- [ ] **Visual Evidence Captured:** Screenshots saved to `docs/specs/02-cart-taxes-and-coupons/evidence/`.
- [ ] **Sign-Off:** Approved to mark `[x]` in `docs/planning/roadmap.md` via `/ship`.

---

## 🧭 Manual Testing Walkthrough (Step-by-Step Human QA)

*(Populated by `/verify` upon completion.)*
