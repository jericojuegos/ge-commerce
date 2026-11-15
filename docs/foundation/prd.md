# 📋 Product Requirements Document (PRD) — GE Commerce (`wp-hexagonal`)

## 1. Product Vision
**GE Commerce** is a clean-room, modern WordPress E-Commerce Core plugin designed to replace legacy `wp_postmeta` store bloat with dedicated custom tables (`wp_gec_*`), pure-PHP Hexagonal Domain logic, integer minor-unit money math, atomic inventory locks, and a fast React/TypeScript store admin.

## 2. User Personas & Roles
- **Store Owner / Manager (`manage_ge_commerce`):** Manages catalog products, variable attributes, SKUs, inventory, coupons, tax/shipping zones, orders, and payment gateways inside `wp-admin`.
- **Shopper (Guest or Logged-in `wp_users`):** Browses products, adds SKUs to a session cart (merging guest cart on login), applies coupons, completes atomic checkout, and tracks order status.
- **Extension Developer:** Hooks into `do_action('ge_commerce_*')` and `apply_filters('ge_commerce_*')` or implements `PaymentGatewayInterface` to extend store functionality without touching core files.

## 3. Phase 1 Core Feature Scope (Spec Track A)
1. **`00-scaffold`:** PSR-4 bootstrap (`GeCommerce\`), DI `Container`, `ModuleRegistry`, custom table migrator (`wp_gec_*`), React Admin shell, and PHPUnit suite.
2. **`01-catalog-and-skus`:** Products, Variable Attributes (e.g., Size/Color), SKUs (`price_cents`, `available_qty`, `reserved_qty`), REST API, and React Admin CRUD.
3. **`02-cart-taxes-and-coupons`:** Guest + WP User Session Cart (`wp_gec_sessions`), guest-to-user cart merge, single-store Coupon rules (fixed cents & basis-points percentage), Tax & Shipping Zone calculators.
4. **`03-checkout-orders-and-hooks`:** Atomic Stock Reservation (`15m` TTL), Order & Order Item persistence, Order State Machine (`PENDING_PAYMENT -> PAID -> FULFILLING -> SHIPPED -> COMPLETED / CANCELLED / REFUNDED`), WP Admin Order Manager, and extensible hook lifecycle.
5. **`04-payment-gateways-and-ai`:** Pluggable `PaymentGatewayInterface` (Sandbox Gateway + Webhook Idempotency key guard) + **Gemini AI Product Copilot** (auto-generates SEO descriptions and attribute tags from product titles).

## 4. Clean-Room & Non-Goals
- **Clean-Room:** Zero code or CSS/trademark assets copied from WooCommerce/Automattic.
- **Non-Goals:** Multi-vendor marketplace splitting (covered in `ge-shop`), legacy shortcode templates.