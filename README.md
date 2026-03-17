# WC Simple Filter

A lightweight, configurable product filter plugin for WooCommerce shops and product archives. Create and manage custom filters without modifying products.

## Description

WC Simple Filter enables shop managers to add powerful, customizable product filters to their WooCommerce stores with minimal effort. Filters can be displayed via shortcode `[wc_simple_filter]` or the PHP helper function `wc_simple_filter()`.

### Key Features

- **Configurable Filters**: Create filters for product categories, tags, attributes, and custom taxonomies
- **Multiple Filter Types**: Support for checkbox, radio, dropdown, and range-based filters
- **Hide Empty Values**: Automatically hide filter values with no matching products
- **Performance Optimized**: Efficient database indexing for fast filter queries
- **Read-Only**: Never modifies or deletes WooCommerce products
- **Easy Integration**: Simple shortcode or PHP function integration
- **Admin Interface**: Intuitive settings within WooCommerce admin
- **Internationalization**: Full i18n support with translation-ready code

## Requirements

- **WordPress**: 6.0 or higher
- **WooCommerce**: 7.0 or higher
- **PHP**: 8.0 or higher

## Installation

1. Download the plugin and extract it to your `wp-content/plugins/` directory
2. Activate "WC Simple Filter" from the Plugins menu
3. Navigate to **WooCommerce > Settings > Filters** to configure your filters

## Usage

### Shortcode Method

Add the shortcode to any page, post, or template:

```
[wc_simple_filter]
```

### PHP Function Method

Add the function to your theme template or custom plugin:

```php
<?php
if ( function_exists( 'wc_simple_filter' ) ) {
    wc_simple_filter();
}
?>
```

### Configuration

1. Go to **WooCommerce > Settings > Filters**
2. Click **Add Filter** to create a new filter
3. Configure:
   - **Filter Name**: Display label
   - **Filter Type**: Choose from available types (categories, tags, attributes, etc.)
   - **Style**: Select visual style (checkbox, radio, dropdown, etc.)
   - **Options**: Set specific values or auto-detect from products
4. Save and activate

## Filters & Hooks

### Action Hooks

- `wc_sf_before_filters` — Fired before filters render on frontend
- `wc_sf_after_filters` — Fired after filters render on frontend
- `wc_sf_filter_registered` — Fired when a new filter is saved

### Filter Hooks

- `wc_sf_filter_output` — Modifies filter HTML output
- `wc_sf_filter_config` — Modifies filter configuration before rendering
- `wc_sf_index_count` — Customizes product count calculations

## Database Tables

The plugin creates two custom tables:

- `{prefix}wc_sf_filters` — Stores filter configurations
- `{prefix}wc_sf_index` — Stores product count index for performance

These tables are created automatically on plugin activation and cleaned up on uninstall.

## Development

### Code Standards

This plugin follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

- PSR-4 autoloading via Composer
- Namespace: `WC_Simple_Filter\`
- Type declarations on all functions (PHP 8.0+)
- Strict security practices (sanitization, escaping, nonce verification)

### File Structure

```
wc-simple-filter/
├── wc-simple-filter.php          # Plugin header & bootstrap
├── uninstall.php                 # Cleanup on uninstall
├── includes/
│   ├── class-plugin.php          # Main orchestrator
│   ├── class-filter-manager.php  # CRUD operations
│   ├── class-index-manager.php   # Index management
│   ├── class-ajax-handler.php    # AJAX endpoints
│   ├── class-shortcode.php       # Shortcode registration
│   └── admin/                    # Admin pages & UI
├── assets/                       # CSS & JavaScript
├── templates/                    # PHP templates
└── languages/                    # Translation files
```

### Running Lints & Tests

Currently, no build tooling is configured. To set up PHP CodeSniffer:

```bash
composer require --dev wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
vendor/bin/phpcs --standard=WordPress .
vendor/bin/phpcbf --standard=WordPress .
```

## Security

- **Input Sanitization**: All user inputs are sanitized
- **Output Escaping**: All output is properly escaped
- **Nonce Verification**: Nonces protect all form submissions
- **Capability Checks**: Only authenticated users with proper permissions
- **SQL Injections**: All database queries use prepared statements
- **WPCS Compliance**: Code follows WordPress security standards

## Compatibility

- **WordPress Multisite**: Full support
- **WooCommerce High-Performance Order Storage (HPOS)**: Declared compatible
- **Block-based Checkout**: Declared compatible
- **PHP 8.0+**: Full type declaration support

## Troubleshooting

### Filters Not Showing
- Ensure WooCommerce is activated
- Verify filters are configured in **WooCommerce > Settings > Filters**
- Check that the shortcode `[wc_simple_filter]` is placed on the correct page

### Empty Product Counts
- Run AJAX index rebuild from the admin settings page
- Check that "Hide empty values" setting is configured correctly

### Performance Issues
- The plugin uses database indexing for fast queries
- Clear WooCommerce caches after bulk product updates
- Rebuild the product index from admin panel

## Contributing

Contributions are welcome! Please follow the code standards outlined in `AGENTS.md` and submit pull requests on [GitHub](https://github.com/romanrehacek/wc-simple-filter).

## Support

For issues, feature requests, or support, visit the [GitHub repository](https://github.com/romanrehacek/wc-simple-filter/issues).

## License

This plugin is licensed under the GPL v2 or later. See LICENSE file for details.

## Credits

Developed by [Roman Rehacek](https://github.com/romanrehacek)

## Changelog

### 0.1.0
- Initial plugin release
- Core filter configuration system
- Database schema creation
- Admin settings interface (in development)
- Frontend rendering (in development)
