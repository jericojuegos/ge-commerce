# 🧠 GE Commerce — Agent Brain (`AGENTS.md`)

**Project:** GE Commerce — Standalone, zero-bloat WordPress E-Commerce Core engine built on custom tables and Hexagonal Architecture (Ports & Adapters).
**Context:** Personal / JutsuWorks Commercial Portfolio
**Stack:** PHP 8.2+ · Composer PSR-4 · WordPress REST API & `$wpdb` · React + TypeScript (`@wordpress/components`, TanStack Query/Table) · PHPUnit · PHPStan · WPCS · Playwright
**Architecture Pattern:** Feature-Based Modular Monolith + Hexagonal Architecture (Ports & Adapters) + Selective DDD
**Master Brain:** `wp-hexagonal`
**Last synced:** `wp-hexagonal-v1.0` — 2026-11-15

---

## 🚦 Project State

**Stage:** `active`
**Stage last updated:** `2026-11-15`

| Stage | Meaning | Next Action / Skill |
|-------|---------|---------------------|
| `initialized` | Brain & specs ready, Phase 0 not built yet | Run **`/build`** on `docs/specs/00-scaffold/tasks.md` |
| `scaffolded` | PSR-4 Core, Container, ModuleRegistry & DB migrations deployed | Run **`/spec`** or **`/build`** on Wave 1 features in `docs/planning/roadmap.md` |
| `active` | Feature wave development in progress | **`/spec`** → **`/build`** → **`/verify`** → **`/ship`** |

---

## ⚡ Deterministic Verification Commands & Local QA Target

Run these commands after every task before marking `[x]`:
- **PHP Syntax Check (Every touched PHP file):** `php -l <path/to/file.php>`
- **Unit & Domain Invariant Tests (No WP boot required for Domain/Application):** `vendor/bin/phpunit --testsuite Unit`
- **Static Analysis & Coding Standards (when configured):** `vendor/bin/phpstan analyse && vendor/bin/phpcs`
- **Frontend Build & Typecheck (when `resources/js/` changes):** `npm run build`
- **Local WordPress Root & URL (for WP-CLI & `flash` Browser QA):**
  - **WP Root Path:** `D:\laragon\www\wp-ecommerce`
  - **Local Site URL:** `http://wp-ecommerce.test`
  - **WP-CLI Smoke Check:** `wp --path="D:\laragon\www\wp-ecommerce" option get ge_commerce_db_version`
  - **Browser QA Auth Rule (Zero Plaintext Passwords in Git):** NEVER store admin passwords in `AGENTS.md` or tracked docs. Read credentials from `.env.testing.local` (`WP_TEST_USER` / `WP_TEST_PASS`) or provision a temporary local QA user via WP-CLI.

---

## 🗺️ Context Map (`docs/INDEX.md`) & Skills (`.agents/skills/`)

| Question / Action | File or Skill |
|-------------------|---------------|
| What is the phased roadmap & feature dependency wave matrix? | [`docs/planning/roadmap.md`](docs/planning/roadmap.md) |
| Create or expand a feature spec (`requirements`, `design`, `tasks`, `uat`) | **`/spec`** ([`.agents/skills/spec/SKILL.md`](.agents/skills/spec/SKILL.md)) |
| Build Phase 0 scaffold or execute tasks/waves for a feature | **`/build`** ([`.agents/skills/build/SKILL.md`](.agents/skills/build/SKILL.md)) |
| Verify syntax, Domain purity, EARS criteria, and WP security Hard Failures | **`/verify`** ([`.agents/skills/verify/SKILL.md`](.agents/skills/verify/SKILL.md)) |
| Ship completed feature, update `roadmap.md`, and generate PR summary | **`/ship`** ([`.agents/skills/ship/SKILL.md`](.agents/skills/ship/SKILL.md)) |
| What are we building overall & why? | [`docs/foundation/prd.md`](docs/foundation/prd.md) |
| Where do modules, ports, adapters & DB migrations live? | [`docs/foundation/architecture.md`](docs/foundation/architecture.md) |
| What PSR-4 namespace, packages, constants & hooks exist? | [`docs/foundation/tech-stack.md`](docs/foundation/tech-stack.md) |
| What are the UI tokens, `@wordpress/components` & TanStack patterns? | [`docs/foundation/design-system.md`](docs/foundation/design-system.md) |
| Why was X decided this way? | [`docs/foundation/decisions.md`](docs/foundation/decisions.md) |
| How do we execute tasks, waves & cross-model reviews? | [`.agents/rules/execution-protocol.md`](.agents/rules/execution-protocol.md) |
| What are the WP Hexagonal layer boundaries & security rules? | [`.agents/rules/stack-rules.md`](.agents/rules/stack-rules.md) |

---

## ⚠️ Critical Constraints — Never Violate

- **Pure Domain Rule (Hexagonal Core):** Classes in `src/Modules/<Feature>/Domain/` and `src/Modules/<Feature>/Application/` must NEVER call WordPress functions (`get_option`, `wp_insert_post`, `current_user_can`), `$wpdb`, or `WP_User`. Depend strictly on `Domain/Contracts/*Interface` ports so unit tests run in milliseconds without booting WordPress.
- **Zero-Float Money Math:** All monetary values (`price_cents`, `discount_cents`, `tax_cents`, `shipping_cents`, `total_cents`) MUST be integer minor units (`int`). Floating-point currency math is strictly forbidden.
- **Custom Tables Only (`wp_gec_*`):** Never store products, SKUs, carts, or orders in `wp_posts` / `wp_postmeta`. Use versioned idempotent `dbDelta()` migrations in `src/WordPress/Database/`.
- **Atomic Stock Reservation:** Inventory reservation during checkout MUST execute an atomic conditional update (`WHERE available_qty >= %d`) to prevent negative stock under concurrent requests.
- **Mandatory WP Security:** Every REST/AJAX endpoint must verify capability (`current_user_can()`) and nonce (`X-WP-Nonce`), sanitize inputs at `Presentation/`, escape outputs, and use `$wpdb->prepare()`.

---

## 🚫 Anti-Hallucination List (Out of Scope for Phase 1)

Do NOT build in Phase 1:
- Multi-vendor seller onboarding or split-shop orders (that belongs to `ge-shop`, not single-store `ge-commerce`)
- Legacy `wp_posts` / Custom Post Type product storage or WooCommerce plugin dependencies
- Recurring billing / subscriptions (deferred to separate extension plugin)