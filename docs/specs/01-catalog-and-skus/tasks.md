# ✅ Feature Tasks: Catalog, Variable Attributes & SKUs (`01-catalog-and-skus`)

> **Phase / Wave:** `Phase 1 (Wave 1)` · **Branch / Owner:** `feat/01-catalog-and-skus` · **Status:** `[>]` Active  
> **Depends On:** `00-scaffold` (`[x]`) · **Touches:** `src/Modules/Catalog/, resources/js/admin/catalog/, tests/Unit/Modules/Catalog/`  
> **Spec Links:** [`requirements.md`](./requirements.md) · [`design.md`](./design.md) · [`uat.md`](./uat.md) · [`design-reference/`](./design-reference/)

---

## 🔄 Implementation Tasks

### 1. Domain & Application Layer + Unit Tests (`⚡ BATCH`: Pure PHP Core)
- [x] **1.1** `[BE]` Implement pure Domain ValueObjects (`ProductStatus`, `SkuAttributes`), Entities (`Sku`, `Product`), and `ProductRepositoryInterface` in `src/Modules/Catalog/Domain/`
  - Zero WordPress imports; enforce integer `price_cents >= 0`, `available_qty >= 0`, and unique `sku_code` per product.
  - _Requirements: 1.1, 1.2, 1.3_
- [x] **1.2** `[BE]` Implement Application DTOs (`SkuInputDTO`, `SaveProductDTO`) and Handlers (`SaveProductHandler`, `GetProductHandler`, `ListProductsHandler`, `DeleteProductHandler`) in `src/Modules/Catalog/Application/`
  - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.3_
- [x] **1.3** `[BE]` Write & run headless unit tests in `tests/Unit/Modules/Catalog/ProductAndSkuTest.php`
  - Cover Property 1 (valid creation/update/round-trip) and Property 2 (negative price/stock, duplicate SKU rejection).
  - _Validates: Property 1, Property 2 (Requirements 1.1, 1.2, 1.3, 2.1, 2.3)_
- [x] **1.4** `[REVIEW]` **Domain & Application Checkpoint:** Run `php -l` on all new PHP files and `vendor/bin/phpunit --testsuite Unit` — must pass with zero errors.

### 2. Infrastructure & Presentation REST Layer (`⚡ BATCH`: WP Adapter & DI)
- [x] **2.1** `[BE]` Implement `WpdbProductRepository` in `src/Modules/Catalog/Infrastructure/Repositories/WpdbProductRepository.php` using `$wpdb->prepare()` and SQL transactions on `wp_gec_products` and `wp_gec_skus`
  - _Requirements: 1.1, 1.2, 2.1, 2.3_
- [x] **2.2** `[BE]` Implement `CatalogRestController` and `CatalogServiceProvider` in `src/Modules/Catalog/Presentation/` and register in `src/Core/Plugin.php`
  - Enforce `current_user_can('manage_options') || current_user_can('manage_ge_commerce')` on all routes.
  - _Requirements: 1.3, 2.1, 2.2, 2.3_
- [x] **2.3** `[REVIEW]` **Backend Integration Checkpoint:** Run `php -l` on all touched PHP files and `vendor/bin/phpunit --testsuite Unit`.

### 3. React Admin Catalog & SKU UI (`⚡ BATCH`: Admin SPA)
- [x] **3.1** `[FE]` Create `resources/js/admin/catalog/types.ts` and `resources/js/admin/catalog/api.ts` using `@wordpress/api-fetch`
  - _Requirements: 3.1, 3.2_
- [x] **3.2** `[FE]` Build `resources/js/admin/catalog/ProductModal.tsx` and `resources/js/admin/catalog/CatalogPage.tsx` using `@wordpress/components`, `@tanstack/react-query`, and `docs/foundation/design-system.md` tokens
  - _Requirements: 3.1, 3.2_
- [x] **3.3** `[FE]` Wire `QueryClientProvider` and `<CatalogPage />` into `resources/js/admin/index.tsx`
  - _Requirements: 3.1_
- [x] **3.4** `[REVIEW]` **Frontend Build Checkpoint:** Run `npm run build` — must compile with zero TypeScript/webpack errors.

### 4. Final Verification & UAT
- [ ] **4.1** `[REVIEW]` **Final Feature Checkpoint:** Run `/verify` (`vendor/bin/phpunit --testsuite Unit`, `npm run build`, `php -l`, WP-CLI & live Browser QA if reachable) → triggers `/ship`.

---

## 🔀 Task Dependency Graph (Intra-Feature Waves)

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["1.4"] },
    { "id": 2, "tasks": ["2.1", "2.2"] },
    { "id": 3, "tasks": ["2.3"] },
    { "id": 4, "tasks": ["3.1", "3.2", "3.3"] },
    { "id": 5, "tasks": ["3.4", "4.1"] }
  ]
}
```

---

## 📝 Feature Activity Log

| Date | Task | Action & Files Touched |
| :--- | :--- | :--- |
| 2026-11-15 | Init | Expanded `/spec 01-catalog-and-skus` (`requirements.md`, `design.md`, `tasks.md`, `uat.md`, `stitch-prompt.md`). |
| 2026-11-15 | 1.1-1.4 | Implemented Catalog Domain & Application layer + `ProductAndSkuTest.php` (12/12 PHPUnit tests pass). |
| 2026-11-15 | 2.1-2.3 | Implemented `WpdbProductRepository`, `CatalogRestController`, `CatalogServiceProvider`, and registered in `Plugin.php`. |
| 2026-11-15 | 3.1-3.4 | Built React Admin Catalog UI (`CatalogPage`, `ProductModal`, `api.ts`, `types.ts`); `tsc --noEmit` & `npm run build` pass. |
