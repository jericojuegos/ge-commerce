# 🧰 Tech Stack — GE Commerce (`wp-hexagonal`)

- **Language:** PHP 8.2+ (`declare(strict_types=1);`)
- **Autoloading:** Composer PSR-4 (`GeCommerce\` -> `src/`, `GeCommerce\Tests\` -> `tests/`)
- **Host Platform:** WordPress 6.5+ (Custom tables via `dbDelta`, REST API `/wp-json/ge-commerce/v1/*`)
- **Admin UI:** React 18+ & TypeScript via `@wordpress/scripts`, `@wordpress/components`, `@wordpress/api-fetch`
- **AI Integration:** Google Gemini API (`gemini-2.5-flash`) with deterministic offline fallback for unit/CI testing
- **Testing:** PHPUnit 10+ (`tests/Unit/` zero-WP fast suite + `tests/Integration/`), PHPStan Level 8