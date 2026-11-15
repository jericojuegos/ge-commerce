# 🧪 UAT & Verification Checklist: `00-scaffold`

## Level 1 — Automated Build, Syntax & Unit Tests
| Check | Command | Status | Evidence / Proof |
|-------|---------|--------|------------------|
| PHP Syntax Lint (`php -l`) | `php -l ge-commerce.php src/**/*.php tests/**/*.php` | `[x]` | 9/9 PHP files (`No syntax errors detected`) |
| Standalone Unit Suite | `vendor/bin/phpunit --testsuite Unit` | `[x]` | `MoneyTest.php`: 7 tests, 25 assertions PASS in `0.017s` |
| WP-CLI Activation & DB Migration | `wp plugin activate ge-commerce && wp option get ge_commerce_db_version` | `[x]` | `ge_commerce_db_version` = `1.0.0`; all 7 `wp_gec_*` tables verified in MySQL via `$wpdb->get_col('SHOW TABLES')` |

## Level 2 — Core Architecture & Schema Verification
| Requirement / Invariant | Status | Evidence / Proof |
|-------------------------|--------|------------------|
| PSR-4 Autoloading (`GeCommerce\` -> `src/`, `GeCommerce\Tests\` -> `tests/`) | `[x]` | `composer.json` + `MoneyTest::test_container_and_module_registry_lifecycle` PASS |
| Zero-Float Money Value Object (`src/Shared/ValueObjects/Money.php`) | `[x]` | `MoneyTest.php:L21-L60` PASS (integer cents, basis-points half-up `intdiv`, clamped discount, negative/currency guards) |
| Idempotent Custom Tables (`wp_gec_products`, `skus`, `sessions`, `coupons`, `orders`, `order_items`, `stock_reservations`) | `[x]` | `Migrator.php:L65-L169` + `MoneyTest::test_migrator_defines_all_seven_custom_gec_tables` PASS + live WP DB verified |
| Admin Menu & React Shell (`toplevel_page_ge-commerce`) | `[x]` | `Plugin.php:L52-L115` + `resources/js/admin/index.tsx` + `build/index.js` |

## 🧭 Manual Testing Walkthrough
1. **Prerequisites & Local Setup:**
   - Ensure Laragon is running (`D:\laragon\www\wp-ecommerce`, `http://wp-ecommerce.test`).
   - Run `wp --path="D:\laragon\www\wp-ecommerce" plugin activate ge-commerce`.
2. **Happy-Path Walkthrough:**
   - Log into `http://wp-ecommerce.test/wp-admin/` as an administrator.
   - Click **GE Commerce** in the left admin sidebar (`admin.php?page=ge-commerce`).
   - Confirm the **GE Commerce** React Admin shell loads with the Industrial Slate palette (`#0F172A`, `#F8FAFC`), displays the minor-unit currency formatting notice (`$29.99 (2999 cents)`), and switches cleanly between the **Catalog & SKUs**, **Orders**, and **Gateways & AI Copilot** tabs.
3. **Edge Cases & Invariant Validation:**
   - Run `vendor/bin/phpunit --testsuite Unit` to verify negative cent amounts and mismatched currencies throw `InvalidArgumentException`.
