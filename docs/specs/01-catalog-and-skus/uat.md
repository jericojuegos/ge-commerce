# 🧪 Feature UAT & Verification: Catalog, Variable Attributes & SKUs (`01-catalog-and-skus`)

> **Feature Slug:** `01-catalog-and-skus` · **Spec Links:** [`requirements.md`](./requirements.md) · [`design.md`](./design.md) · [`tasks.md`](./tasks.md) · [`design-reference/`](./design-reference/)  
> **Overall Status:** `[x]` Passed · **Verified Date:** `2026-11-15`

---

## Level 1 — Automated Terminal, Unit & Build Checks

- [x] **PHP Syntax Check:** `php -l` passed with zero errors on all 23 PHP files in `src/` and `tests/`.
- [x] **Stack Hard-Failure Audit:** Fresh-context `Catalog Blind Diff Auditor` (`research` subagent) reported `✅ 0 P0/P1 Findings` (pure Domain/Application, `$wpdb->prepare()`, `current_user_can()`, zero-float integer cents).
- [x] **Unit Tests (Tier 1):** `vendor/bin/phpunit --testsuite Unit` passed (`12 tests, 45 assertions`).
- [x] **Frontend Build:** `npx tsc --noEmit` and `npm run build` compiled `build/index.tsx.js` (`45.7 KiB`) with zero errors.

---

## Level 2 — Functional & EARS Requirement Verification

| Check ID | Requirement Ref | Scenario (WHEN / IF) | Expected Outcome (THE System SHALL) | Evidence / Proof | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **UAT-1.1** | `Req 1.1` | Store manager creates/updates Product + SKUs | Persists `price_cents >= 0` & `available_qty >= 0` in `wp_gec_*` | `ProductAndSkuTest.php:L113` PASS + `wp eval-file` DB round-trip (`Saved:1 Price:1999`, `Deleted:true`) | `[x]` |
| **UAT-1.2** | `Req 1.2` | SKU saved with attributes (`size: L`) | Stores normalized JSON in `attributes_json` & enforces unique `sku_code` | `ProductAndSkuTest.php:L150-L154` PASS (`{"color":"Black","size":"L"}`) | `[x]` |
| **UAT-1.3** | `Req 1.3` | Negative price/stock or duplicate `sku_code` | Rejects with `InvalidArgumentException` / HTTP `422`, zero DB writes | `ProductAndSkuTest.php:L169-L246` PASS (`saveCalls === 0` / unchanged) | `[x]` |
| **UAT-2.2** | `Req 2.2` | Unauthenticated/unauthorized REST call | Denies with HTTP `401`/`403` before handler execution | `Invoke-WebRequest http://wp-ecommerce.test/?rest_route=/ge-commerce/v1/products` → `HTTP 403` | `[x]` |
| **UAT-3.1** | `Req 3.1, 3.2` | Admin opens `Catalog & SKUs` & saves product | Lists products with `Intl.NumberFormat` prices; modal saves & refreshes | `CatalogPage.tsx` + `ProductModal.tsx` compiled cleanly via `npx tsc --noEmit && npm run build` | `[x]` |

---

## Level 3 — Visual Fidelity & Human UX Sign-Off

- [x] **Design System Match:** Uses `docs/foundation/design-system.md` tokens (`#0F172A`, `#2563EB`, `#F8FAFC`, `#E2E8F0`, status badges `#16A34A` / `#D97706` / `#DC2626`).
- [x] **Double-Submit & Empty State:** Save button disables (`isSaving`) while in flight; empty state renders cleanly with `Create your first product` CTA.
- [x] **Sign-Off:** Approved to mark `[x]` in `docs/planning/roadmap.md` via `/ship`.

---

## 🧭 Manual Testing Walkthrough (Step-by-Step Human QA)

### 1. Prerequisites & Local Setup
- **Local Site URL:** `http://wp-ecommerce.test/wp-admin/admin.php?page=ge-commerce`
- **Test Account:** Logged-in WordPress Administrator (`manage_options` / `manage_ge_commerce` capability).

### 2. Happy-Path Walkthrough (`Req 1.1–1.2, 3.1–3.2`)
1. **Open:** Navigate to **WP Admin → GE Commerce** (`Catalog & SKUs` tab).
2. **Action:** Click **`+ Add Product`**, fill **Title** (`Ninja Hoodie`), **Status** (`Published`), and SKU row (`NH-BLK-L`, `size=L, color=Black`, `4999` cents, `25` avail qty), then click **`Save Product`**.
3. **Expected UI Result:** Modal closes, product appears in the table with `PUBLISHED` green badge, `1` SKU, `$49.99`, and `25` stock.
4. **Persistence Check:** Refresh the page (`F5`) and confirm `Ninja Hoodie` persists from `wp_gec_products` / `wp_gec_skus`.

### 3. Edge Cases & Error Validation (`Req 1.3, 2.2`)
1. **Duplicate SKU:** Create another product using `NH-BLK-L` → **Expected:** Inline error Notice inside modal (`HTTP 422`), inputs preserved.
2. **Unauthorized Access:** Open `http://wp-ecommerce.test/?rest_route=/ge-commerce/v1/products` in an incognito window → **Expected:** `HTTP 403` (`rest_forbidden`).
