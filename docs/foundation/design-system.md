# 🎨 Design System — GE Commerce (`wp-hexagonal`)

- **Palette ("Grey Series" Industrial Slate):**
  - Primary Accent: `#0F172A` (Slate 900) / `#2563EB` (Action Blue)
  - Surface: `#FFFFFF` / `#F8FAFC`
  - Border: `#E2E8F0`
  - Status Badges: Paid (`#16A34A`), Pending (`#D97706`), Cancelled/Error (`#DC2626`)
- **Components:** Native `@wordpress/components` (`Card`, `Button`, `Modal`, `TextControl`, `SelectControl`, `Notice`) wrapped in `.gec-admin-shell`.
- **Currency Formatting:** Integer `price_cents` formatted at the presentation boundary via `Intl.NumberFormat`.