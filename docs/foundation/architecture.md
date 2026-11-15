# 🏗️ Architecture — GE Commerce (`wp-hexagonal`)

## 1. Folder Structure (`src/` + `resources/` + `tests/`)
```text
ge-commerce/
├── ge-commerce.php
├── composer.json
├── package.json
├── src/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Container.php
│   │   └── ModuleRegistry.php
│   ├── Shared/
│   │   ├── Contracts/
│   │   ├── ValueObjects/Money.php
│   │   └── Events/
│   ├── WordPress/
│   │   ├── Database/Migrator.php
│   │   └── Hooks/HookDispatcher.php
│   └── Modules/
│       ├── Catalog/
│       │   ├── Domain/{Entities,ValueObjects,Contracts,Services}/
│       │   ├── Application/{Commands,Queries,Handlers,DTOs}/
│       │   ├── Infrastructure/Repositories/WpdbProductRepository.php
│       │   └── Presentation/{Rest,Providers}/
│       ├── Cart/
│       ├──Checkout/
│       ├── Order/
│       └── Payment/
├── resources/
│   ├── js/admin/
│   └── scss/
└── tests/
    ├── Unit/Modules/
    └── Integration/
```

## 2. Custom Database Tables (`wp_gec_*`)
- `wp_gec_products` (`id`, `slug`, `title`, `description`, `status`, `created_at`, `updated_at`)
- `wp_gec_skus` (`id`, `product_id`, `sku_code`, `attributes_json`, `price_cents`, `available_qty`, `reserved_qty`)
- `wp_gec_sessions` (`session_key`, `user_id`, `cart_json`, `coupon_code`, `expires_at`)
- `wp_gec_coupons` (`id`, `code`, `discount_type`, `amount_value`, `min_order_cents`, `usage_limit`, `used_count`, `expires_at`)
- `wp_gec_orders` (`id`, `order_number`, `user_id`, `status`, `subtotal_cents`, `discount_cents`, `tax_cents`, `shipping_cents`, `total_cents`, `payment_gateway`, `idempotency_key`, `created_at`)
- `wp_gec_order_items` (`id`, `order_id`, `sku_id`, `sku_code`, `title`, `qty`, `unit_price_cents`, `line_total_cents`)
- `wp_gec_stock_reservations` (`id`, `order_id`, `sku_id`, `qty`, `status`, `expires_at`)