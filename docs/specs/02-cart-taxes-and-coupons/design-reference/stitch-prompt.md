# 🎨 UI / Google Stitch Prompt: Coupons, Tax & Shipping Zones (`02-cart-taxes-and-coupons`)

> **How to Use:**
> 1. **Personal / New UI (Google Stitch):** Copy the prompt block below into [Google Stitch](https://stitch.withgoogle.com/). Export the screenshot (`01-coupons-tax.png`) into `docs/specs/02-cart-taxes-and-coupons/design-reference/`.
> 2. **Client Already Has Designs:** Drop screenshots (`*.png` / `*.jpg`) into `docs/specs/02-cart-taxes-and-coupons/design-reference/`.

---

## Screen 1 Prompt — `Coupons, Tax & Shipping Zones Admin Tab`

```text
Design a WordPress Admin SPA tab screen for "GE Commerce" — a clean, zero-bloat industrial e-commerce core plugin.

SCREEN PURPOSE:
Enable a store manager to manage discount coupons (fixed-dollar or percentage), configure regional tax & shipping zones, and simulate live cart totals in integer cents.

VISUAL DIRECTION & DESIGN TOKENS (from docs/foundation/design-system.md):
- Visual Tone: Clean Industrial Slate ("Grey Series")
- Mode: Light
- Background Primary: #F8FAFC | Surface / Card: #FFFFFF | Border: #E2E8F0
- Text Primary: #0F172A | Text Muted: #475569
- Primary Brand Accent: #2563EB | Status Badges: Active (#16A34A), Limit Reached (#D97706), Expired (#DC2626)
- Components: Native @wordpress/components (Card, Button, Modal, TextControl, SelectControl, Notice) inside .gec-admin-shell

LAYOUT & KEY COMPONENTS (Mapped to EARS Requirements):
1. Top Tab Bar: Active tab "Coupons & Tax" alongside "Catalog & SKUs", "Orders", "Gateways & AI Copilot".
2. Primary Card — Coupons Manager (Req 1.1, 4.3): Header with "+ Add Coupon" primary button. Table columns: Code (monospace uppercase), Type (Fixed $ / Percentage %), Value, Min Order ($), Usage (used / limit), Expires, Status badge (Active/Expired/Limit Reached), Actions (Edit, Delete).
3. Modal — Create/Edit Coupon (Req 1.1, 1.3): Code, Discount Type select, Amount Value (cents or basis points with helper hint), Min Order (cents), Usage Limit, Expiry Datetime, inline error Notice.
4. Two-Column Lower Section (Req 3.1, 3.2, 4.3):
   - Left Card: Tax & Shipping Zones Configurator (editable rows for Country, Region, Tax Rate bps, Shipping Method, Flat Rate cents, Free Over cents + Save Zones button).
   - Right Card: Live Quote Simulator (pick SKU/qty or enter subtotal cents, coupon code, country/region, shipping method -> displays integer breakdown: Subtotal, Discount, Taxable, Tax, Shipping, Total).

CONSTRAINTS:
- Do NOT include multi-vendor or subscription fields.
- Keep layout clean, high-contrast, and consistent with existing CatalogPage.tsx.
```

---

## Export Checklist (Before Running `/build` `[FE]`)

- [ ] Saved approved screenshot(s) as `docs/specs/02-cart-taxes-and-coupons/design-reference/01-coupons-tax.png`
- [ ] *(Optional)* Saved Stitch HTML/Tailwind export as `docs/specs/02-cart-taxes-and-coupons/design-reference/01-coupons-tax.html`
