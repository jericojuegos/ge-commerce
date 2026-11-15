# 🎨 Stitch UI Prompt: Catalog & SKUs Admin (`01-catalog-and-skus`)

Build a clean WordPress Admin SPA screen inside `.gec-admin-shell` using the "Grey Series" Industrial Slate design system:
- **Background:** `#F8FAFC`, **Card Surface:** `#FFFFFF`, **Border:** `1px solid #E2E8F0`
- **Primary Text:** `#0F172A`, **Secondary Text:** `#475569`, **Primary Action Button:** `#2563EB`
- **Status Badges:** Published (`#16A34A`), Draft (`#D97706`), Archived (`#DC2626`)

## Screens
1. **Catalog List View:**
   - Top toolbar with Status filter (`All`, `Published`, `Draft`, `Archived`) and primary `+ Add Product` button.
   - Clean data table columns: `Product` (title + slug), `Status` (badge), `SKUs` (count), `Price Range` (formatted `$XX.XX`), `Available Stock` (sum), `Actions` (`Edit`, `Delete`).
   - Empty state card when no products exist.
2. **Product & SKU Editor Modal:**
   - Product fields: `Title`, `Slug`, `Status` (`draft` / `published` / `archived`), `Description`.
   - Dynamic SKU rows: `SKU Code`, `Price (cents)`, `Available Qty`, `Attributes (JSON or key/value pairs like size=L, color=Black)`, `+ Add SKU`, `Remove`.
   - Footer: `Cancel` and `Save Product` (disabled while submitting).
