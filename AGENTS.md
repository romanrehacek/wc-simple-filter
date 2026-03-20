# AGENTS.md - Simple Product Filter

## Project Overview

WordPress/WooCommerce plugin for displaying configurable product filters on the
shop page (and any archive). Filters are inserted via shortcode `[simple_product_filter]`
or the PHP helper `simple_product_filter()`. The plugin is **read-only** with respect
to WooCommerce products -- it never modifies or deletes them.

- **Language:** PHP (>= 8.0)
- **Platform:** WordPress >= 6.x, WooCommerce >= 7.x
- **Admin location:** WooCommerce > Nastavenia > Filtre (tab added to WC settings)
- **Plugin slug:** `simple-product-filter`
- **Text domain:** `simple-product-filter`
- **Status:** Early development. `PRD.md` has full requirements. `.opencode/plans/`
  contains the detailed implementation plan.

### Agent Capabilities
- This project uses **WordPress Agent Skills** (wp-plugin-development, wp-rest-api).
- Always refer to these skills when implementing WordPress-specific logic, security
  checks, or plugin architecture.

---

## Build / Lint / Test Commands

There is currently no build tooling, test framework, or CI/CD pipeline configured.
When these are set up, follow the conventions below.

### PHP Linting (planned)
```bash
# Install PHP_CodeSniffer with WordPress standards
composer require --dev wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
# Lint the entire plugin
vendor/bin/phpcs --standard=WordPress .
# Fix auto-fixable issues
vendor/bin/phpcbf --standard=WordPress .
# Lint a single file
vendor/bin/phpcs --standard=WordPress path/to/file.php
```

### Testing
```bash
# Run all tests
vendor/bin/phpunit
# Run a single test file
vendor/bin/phpunit tests/path/to/SomeTest.php
# Run a single test method
vendor/bin/phpunit --filter test_method_name
```

### Static Analysis (planned)
```bash
composer require --dev phpstan/phpstan szepeviktor/phpstan-wordpress
vendor/bin/phpstan analyse
```

### No JS/CSS Build Step
This plugin has no JavaScript or CSS build pipeline. If one is introduced later,
use `wp_enqueue_script()` / `wp_enqueue_style()` for asset loading.

---

## Code Style Guidelines

Follow the **WordPress Coding Standards (WPCS)** for all PHP code.
Reference: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/

### Indentation and Formatting
- Use **tabs** for indentation, not spaces.
- Opening braces on the **same line** as the statement (K&R style).
- **Spaces inside parentheses** of control structures: `if ( $condition )`, not `if ($condition)`.
- Spaces around operators: `$a = $b + $c;`
- Spaces on both sides of the concatenation operator: `'Hello ' . $name`
- No space between `require_once` and parenthesis: `require_once( 'file.php' );`
- Single blank line between logical sections. Use comment block separators sparingly.

### Naming Conventions
- **Files:** lowercase kebab-case: `class-filter-manager.php`, `admin-page.php`
- **Classes:** `Upper_Snake_Case` per WP convention: `class Simple_Product_Filter {}`
- **Methods/Functions:** `snake_case`: `function get_filter_config() {}`
- **Variables:** `snake_case`: `$filter_id`, `$filter_type` (never camelCase)
- **Constants:** `UPPER_SNAKE_CASE`: `define( 'SPF_VERSION', '1.0.0' );`
- **Hooks:** prefix with plugin slug: `spf_before_filters`, `spf_filter_output`
- **Database tables:**
  - `{$wpdb->prefix}spf_filters` — filter configuration (repeater rows)
  - `{$wpdb->prefix}spf_index`   — value index (term/meta → product count)
- **Nonces:** descriptive action names: `spf_save_filters`, `spf_save_settings`
- **Text domain:** `simple-product-filter`
- **Option names:** `spf_filters` (serialized array), `spf_settings`

### PHP Version and Type Usage
- Target **PHP 8.0+**. Use union types, named arguments, `match`, and `null-safe`
  operator where they improve clarity.
- Add **type declarations** on all function parameters and return types.
- Add **PHPDoc blocks** on every class, method, and function.
- Prefer strict comparisons (`===`, `!==`) over loose ones.

### Imports and Autoloading
- Use **Composer PSR-4 autoloading** when `composer.json` is present.
- Namespace: `Simple_Product_Filter\`
- If not using Composer, use `require_once` and guard with `defined( 'ABSPATH' )`.

### WordPress Security (mandatory)
- **Every file** must start with the ABSPATH guard:
  ```php
  if ( ! defined( 'ABSPATH' ) ) {
      exit;
  }
  ```
- **Sanitize all input:** `sanitize_text_field()`, `absint()`, `wp_unslash()`, etc.
- **Escape all output:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`.
- **Nonce verification** on every form submission and AJAX handler.
- **Capability checks:** `current_user_can( 'manage_options' )` before any action.
- **Prepared statements** for all database queries.
- Never use `die()` -- use `wp_die()` or `wp_send_json_error()`.

### Error Handling
- Use `WP_Error` objects for recoverable errors, not exceptions.
- Use `wp_send_json_error()` / `wp_send_json_success()` in AJAX handlers.
- Log errors with `error_log()` -- never echo errors to users.

### Internationalization (i18n)
- Wrap all user-facing strings:
  ```php
  __( 'Filter name', 'simple-product-filter' )
  esc_html__( 'No values', 'simple-product-filter' )
  ```
- Use `_n()` for plurals, `_x()` for context-disambiguated strings.

### Architecture
- Use **OOP with single-responsibility classes**.
- Main plugin file (`simple-product-filter.php`) should only bootstrap.
- Suggested directory structure:
  ```
  simple-product-filter/
  ├── simple-product-filter.php          # Plugin header + bootstrap
  ├── uninstall.php                 # Cleanup on uninstall
  ├── includes/
  │   ├── class-plugin.php          # Main orchestrator / hook loader
  │   ├── admin/
  │   │   ├── class-admin.php       # WC settings tab integration
  │   │   ├── class-filters-tab.php # Tab: filter repeater UI
  │   │   ├── class-settings-tab.php# Tab: general settings
  │   │   ├── class-help-tab.php    # Tab: help / shortcode docs
  │   │   └── class-filter-edit.php # Single filter edit page
  │   ├── class-filter-manager.php  # CRUD for filter config (DB)
  │   ├── class-index-manager.php   # Build/query the value index table
  │   ├── class-ajax-handler.php    # AJAX endpoints (save, reindex)
  │   └── class-shortcode.php       # [simple_product_filter] shortcode + PHP helper
  ├── assets/
  │   ├── css/
  │   │   └── admin.css
  │   └── js/
  │       └── admin.js              # Repeater, sortable, filter edit logic
  ├── templates/
  │   └── admin/                    # PHP partials for admin pages
  ├── languages/
  ├── tests/
  ├── .opencode/
  │   └── plans/
  ├── AGENTS.md
  ├── composer.json
  └── phpcs.xml.dist
  ```

### Database
- `{$wpdb->prefix}spf_filters` — one row per filter, stores type, style, config JSON.
- `{$wpdb->prefix}spf_index`   — maps (filter_type, value) → product_count for fast
  hide-empty logic. Rebuilt via AJAX or on WooCommerce product save.
- Tables created on activation via `dbDelta()`.
- Always use `$wpdb->prepare()` for parameterised queries.

### Asset Enqueuing
- Use `wp_enqueue_script()` and `wp_enqueue_style()`.
- Enqueue admin assets only on plugin pages via `$hook_suffix` check.
- Use `wp_localize_script()` to pass PHP data to JS.
