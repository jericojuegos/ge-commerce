# 🏛️ Architecture Decisions (ADR) — GE Commerce

1. **ADR-01: Custom Tables Over `wp_postmeta`:** All products, SKUs, sessions, and orders reside in indexed `wp_gec_*` tables to guarantee O(1) SKU lookups and atomic stock updates.
2. **ADR-02: Pure Hexagonal Domain:** `Domain/` and `Application/` have zero WordPress dependencies so all pricing, coupon, and state-machine invariants run in `<100ms` via standalone PHPUnit.
3. **ADR-03: Integer Minor Units (`price_cents`):** Eliminates IEEE 754 floating-point rounding bugs across cart totals, tax, and coupon calculations.
4. **ADR-04: Cross-Module Shared Contracts & Zone Options:** Inter-module calls (`Cart` -> `Catalog` / `Coupon`) communicate strictly through `src/Shared/Contracts/*Interface` ports wired in `Container`; regional Tax & Shipping Zones reside in `wp_options` (`WpZoneConfigRepository`) to preserve the 7 core `wp_gec_*` tables in `Migrator.php`.