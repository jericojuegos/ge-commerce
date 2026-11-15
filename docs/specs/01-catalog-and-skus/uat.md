# 🧪 Feature UAT & Verification: Catalog, Variable Attributes & SKUs (`01-catalog-and-skus`)

> **Feature Slug:** `01-catalog-and-skus` · **Spec Links:** [`requirements.md`](./requirements.md) · [`design.md`](./design.md) · [`tasks.md`](./tasks.md) · [`design-reference/`](./design-reference/)  
> **Overall Status:** `[ ]` Pending · **Verified Date:** `2026-11-15`

---

## Level 1 — Automated Terminal, Unit & Build Checks

- [ ] **PHP Syntax Check:** `php -l` passes with zero errors on all touched PHP files.
- [ ] **Stack Hard-Failure Audit:** Pure Domain/Application (`zero WP`), `$wpdb->prepare()`, `current_user_can()`, and zero-float integer cents verified.
- [ ] **Unit Tests (Tier 1):** `vendor/bin/phpunit --testsuite Unit` passes for `ProductAndSkuTest.php` and `MoneyTest.php`.
- [ ] **Frontend Build:** `npm run build` succeeds with zero TypeScript/webpack errors.

---

## Level 2 — Functional & EARS Requirement Verification

| Check ID | Requirement Ref | Scenario (WHEN / IF) | Expected Outcome (THE System SHALL) | Evidence / Proof | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-1.1** | `Req 1.1` | Store manager creates/updates Product + SKUs | Persists `price_cents >= 0` & `available_qty >= 0` in `wp_gec_*` | Pending | `[ ]` |
| **UAT-1.2** | `Req 1.2` | SKU saved with attributes (`size: L`) | Stores normalized JSON in `attributes_json` & enforces unique `sku_code` | Pending | `[ ]` |
| **UAT-1.3** | `Req 1.3` | Negative price/stock or duplicate `sku_code` | Rejects with `InvalidArgumentException` / HTTP `400`/`422`, zero DB writes | Pending | `[ ]` |
| **UAT-2.2** | `Req 2.2` | Unauthenticated/unauthorized REST call | Denies with HTTP `401`/`403` before handler execution | Pending | `[ ]` |
| **UAT-3.1** | `Req 3.1, 3.2` | Admin opens `Catalog & SKUs` & saves product | Lists products with `Intl.NumberFormat` prices; modal saves & refreshes | Pending | `[ ]` |

---

## Level 3 — Visual Fidelity & Human UX Sign-Off

- [ ] **Design System Match:** Uses `docs/foundation/design-system.md` tokens (`#0F172A`, `#2563EB`, `#F8FAFC`, `#E2E8F0`, status badges).
- [ ] **Double-Submit & Empty State:** Save button disables while in flight; empty state renders cleanly.
- [ ] **Sign-Off:** Approved to mark `[x]` in `docs/planning/roadmap.md` via `/ship`.
