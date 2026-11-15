# Requirements: `01-catalog-and-skus` — Catalog, Variable Attributes & SKUs

## EARS Acceptance Criteria
- **REQ-01:** WHEN a store manager creates or updates a Product and SKU, THE SYSTEM SHALL persist `price_cents` as an integer >= 0 and `available_qty` >= 0 without touching `wp_postmeta`.