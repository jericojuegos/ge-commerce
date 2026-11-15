# Tasks: `00-scaffold` (GE Commerce — `wp-hexagonal`)

- [x] **T01:** Create `composer.json` with PSR-4 (`GeCommerce\` -> `src/`, `GeCommerce\Tests\` -> `tests/`) and PHPUnit dev dependency, plus `phpunit.xml.dist`.
- [x] **T02:** Create `ge-commerce.php` plugin entrypoint, `src/Core/Container.php`, `src/Core/ModuleRegistry.php`, and `src/Core/Plugin.php`.
- [x] **T03:** Create `src/Shared/ValueObjects/Money.php` (pure PHP integer minor-unit value object with zero-float arithmetic) and unit test `tests/Unit/Shared/MoneyTest.php`.
- [x] **T04:** Create `src/WordPress/Database/Migrator.php` managing idempotent `dbDelta` schema creation for `wp_gec_*` tables (`products`, `skus`, `sessions`, `coupons`, `orders`, `order_items`, `stock_reservations`).
- [x] **T05:** Configure `package.json` (`@wordpress/scripts`) and admin menu shell (`resources/js/admin/index.tsx`), then verify `php -l` and `vendor/bin/phpunit --testsuite Unit`.

## 📝 Feature Activity Log
- `2026-11-15`: Completed `00-scaffold` (T01–T05). Verified `php -l` on all 9 PHP files, `vendor/bin/phpunit --testsuite Unit` (7 tests, 25 assertions PASS in 17ms), and WP-CLI plugin activation (`ge_commerce_db_version = 1.0.0`, all 7 `wp_gec_*` tables created).