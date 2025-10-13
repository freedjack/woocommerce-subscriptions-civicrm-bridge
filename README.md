# WooCommerce Subscriptions CiviCRM Bridge

A WordPress plugin that bridges WooCommerce Subscriptions with CiviCRM for renewal orders.

## Description

This plugin addresses the gap between WooCommerce Subscriptions and the WPCV WooCommerce CiviCRM Integration plugin. While the WPCV plugin handles regular WooCommerce orders well, it doesn't have built-in support for subscription renewal orders.

## Features

- **Renewal Order Processing**: Hooks into renewal order creation events
- **CiviCRM Contribution Creation**: Automatically creates/updates CiviCRM contributions for renewal orders
- **Dependency Checking**: Ensures required plugins are active before initialization
- **Comprehensive Logging**: Detailed logging for debugging and monitoring

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- WooCommerce Subscriptions
- WPCV WooCommerce CiviCRM Integration
- CiviCRM with CiviMember component enabled

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-subscriptions-civicrm-bridge/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure all required dependencies are installed and active

## How It Works

### 1. Plugin Initialization

The plugin initializes using the `plugins_loaded` action to ensure all dependencies are available:

- Checks for required plugins: WooCommerce, WooCommerce Subscriptions, and WPCV WooCommerce CiviCRM Integration
- Loads text domain for internationalization
- Sets up hooks for renewal order processing

### 2. Renewal Order Processing

The plugin hooks into the `wcs_renewal_order_created` filter:

- Triggers when WooCommerce Subscriptions creates a renewal order
- Automatically calls the WPCV plugin's contribution creation function
- Logs the process for debugging and monitoring

### 3. CiviCRM Integration

For each renewal order:

- Uses `WPCV_WCI()->contribution->create_from_order()` to create/update the CiviCRM contribution
- Leverages the existing WPCV plugin functionality for contribution management
- Maintains consistency with regular order processing

## Configuration

### Product Setup

Configure your subscription products using the WPCV WooCommerce CiviCRM Integration plugin:

1. Edit a WooCommerce product
2. Go to the "CiviCRM Settings" tab (added by WPCV plugin)
3. Configure the appropriate CiviCRM entity type and settings
4. Save the product

The bridge plugin will automatically handle renewal orders for products configured through WPCV.

## Debugging

The plugin provides comprehensive logging. Check your debug log at:

```
wp-content/debug.log
```

Look for entries prefixed with "WCS CiviCRM Bridge:"

## Admin Interface

The plugin provides admin notices for dependency checking:

- Displays error messages if required plugins are missing
- Shows which specific plugins need to be installed/activated
- Prevents plugin initialization if dependencies are not met

## Hooks and Filters

### Filters

- `wcs_renewal_order_created` - Hooks into renewal order creation to process CiviCRM contributions

## Troubleshooting

### Common Issues

1. **Plugin not initializing**: Check that all required plugins are active (WooCommerce, WooCommerce Subscriptions, WPCV WooCommerce CiviCRM Integration)
2. **No contribution created**: Verify the WPCV plugin is properly configured and CiviCRM is accessible
3. **Missing admin notices**: Check that the plugin is properly activated and dependencies are met

### Debug Steps

1. Enable WordPress debug logging
2. Process a test renewal order
3. Check the debug log for entries prefixed with "WCS CiviCRM Bridge:"
4. Verify that the WPCV plugin is creating contributions for regular orders

## Support

For issues and feature requests, please create an issue in the plugin repository.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Basic renewal order processing with CiviCRM contribution creation
- Dependency checking and admin notices
- Comprehensive logging
- Integration with WPCV WooCommerce CiviCRM Integration plugin
