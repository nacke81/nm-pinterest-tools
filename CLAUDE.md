# NM Pinterest Tools

WordPress plugin that adds Pinterest image generation and sharing tools to the post editor. Depends on ACF (Advanced Custom Fields) and Uncanny Automator (Magic Link recipes).

## Architecture

- **Singleton pattern** via `NM_Pinterest_Tools::instance()`
- `class-nm-pinterest-tools.php` — Core singleton, settings retrieval, ACF field group registration, date/time utilities, truthy evaluation
- `class-nm-pinterest-tools-settings.php` — WP Settings API page (Settings → NM Pinterest Tools)
- `class-nm-pinterest-tools-admin.php` — Editor UI (Generate Pin / Share to Pinterest buttons), admin list column with filtering and sorting
- All post meta keys prefixed with `_nm_pinterest_` (defined in `get_meta_keys()`)
- Settings stored in `nm_pinterest_tools_settings` option

## Coding Standards

- Follow WordPress Coding Standards (WPCS)
- Tabs for indentation (not spaces)
- PHPDoc on every public method
- Guard clause at top of every PHP file: `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- Class files named `class-{slug}.php`
- Text domain: `nm-pinterest-tools`

## Commands

- Lint: `composer lint`
- Fix lint: `composer lint:fix`
- Test: `composer test`
- Test single: `composer test -- --filter=test_name`

## Deployment

- Merging to `main` triggers GitHub Actions deploy to Cloudways via SSH/rsync
- Never push directly to main; always use a PR
- CI must pass (lint + tests) before merge

## Key Files

- `nm-pinterest-tools.php` — Plugin bootstrap, constants, requires
- `includes/class-nm-pinterest-tools.php` — Core singleton, settings, ACF fields, utilities
- `includes/class-nm-pinterest-tools-settings.php` — WP Settings API page
- `includes/class-nm-pinterest-tools-admin.php` — Editor UI, list columns, filtering, sorting

## Post Meta Keys (writable by Uncanny Automator, Make, etc.)

- `_nm_pinterest_shared` — Share status
- `_nm_pinterest_shared_at` — Timestamp of last share
- `_nm_pinterest_pin_url` — URL to Pinterest pin
- `_nm_pinterest_pin_id` — Pinterest pin ID
- `_nm_pinterest_shared_via` — Share method identifier
- `_nm_pinterest_last_attempt_at` — Last attempt timestamp
- `_nm_pinterest_last_error` — Error message from last attempt
