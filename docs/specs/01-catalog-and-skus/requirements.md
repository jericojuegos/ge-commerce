# 📋 Requirements: Catalog, Variable Attributes & SKUs (`01-catalog-and-skus`)

> **Feature Slug:** `01-catalog-and-skus` · **Wave:** `Wave 1` · **Depends On:** `00-scaffold`  
> **Specs:** [`design.md`](./design.md) · [`tasks.md`](./tasks.md) · [`uat.md`](./uat.md)

---

## 1. Glossary & Assumptions

- **Product (`wp_gec_products`):** Top-level catalog item with `slug`, `title`, `description`, and `status` (`draft`, `published`, `archived`). Stored strictly in custom tables, never `wp_posts`.
- **SKU (`wp_gec_skus`):** Sellable variant belonging to a Product, identified by a unique `sku_code`, variable attribute map (`attributes_json`), integer `price_cents` (>= 0), `available_qty` (>= 0), and `reserved_qty` (>= 0).
- **A1 (Currency):** Phase 1 uses single-store currency `USD` with integer minor units (`price_cents`).
- **A2 (Admin Capability):** Catalog management REST routes require `manage_options` or `manage_ge_commerce` capability plus valid WordPress REST nonce (`X-WP-Nonce`).

---

## 2. EARS Acceptance Criteria

### Story 1 — Product & SKU Persistence (Zero-Float & Custom Tables)
- **Req 1.1:** WHEN a store manager creates or updates a Product with one or more SKUs, THE SYSTEM SHALL persist the Product in `wp_gec_products` and its SKUs in `wp_gec_skus` with `price_cents` as an integer `>= 0` and `available_qty` as an integer `>= 0` without touching `wp_posts` or `wp_postmeta`.
- **Req 1.2:** WHEN a SKU is saved with variable attributes (e.g. `{"size":"L","color":"Black"}`), THE SYSTEM SHALL normalize and store them as JSON in `attributes_json` and enforce global uniqueness on `sku_code`.
- **Req 1.3:** IF a Product title/slug is empty, `price_cents < 0`, `available_qty < 0`, or a `sku_code` already exists on another SKU, THEN THE SYSTEM SHALL reject the request with HTTP `400`/`422` before any database mutation occurs.

### Story 2 — Catalog Query, Deletion & Security Gate
- **Req 2.1:** WHEN an authorized store manager requests the product list or a single product by ID, THE SYSTEM SHALL return the Product along with its associated SKUs and total count.
- **Req 2.2:** IF an unauthenticated user or a user lacking `manage_options` / `manage_ge_commerce` calls any catalog REST endpoint, THEN THE SYSTEM SHALL deny the request with HTTP `401`/`403` before any handler or database query executes.
- **Req 2.3:** WHEN an authorized store manager deletes a Product by ID, THE SYSTEM SHALL atomically delete the Product and its child SKUs from `wp_gec_products` and `wp_gec_skus`.

### Story 3 — React Admin Catalog & SKU Manager
- **Req 3.1:** WHEN the store manager opens the GE Commerce admin page (`Catalog & SKUs` tab), THE SYSTEM SHALL fetch and display products with SKU count, price range formatted via `Intl.NumberFormat`, total available stock, and status badge.
- **Req 3.2:** WHEN the store manager clicks `Add Product` or `Edit`, THE SYSTEM SHALL open a modal allowing them to edit product fields and add/remove/update variable attribute SKUs, disabling submit while in flight and refreshing the list upon save.
