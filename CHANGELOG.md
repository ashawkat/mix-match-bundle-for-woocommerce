# Changelog

All notable changes to the Mix & Match Bundle for WooCommerce plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2024-12-03

### Added
- **Analytics Dashboard**: Comprehensive analytics page with charts and graphs
  - Coupon usage tracking (created vs used vs unused)
  - Bundle performance metrics and popularity tracking
  - Purchase analytics with revenue tracking
  - Cart conversion analytics
  - Bundle order rate calculations
  - Date filtering (Last 7 days, Last 30 days, This Month, Last Month, Last Quarter, Custom Range)
  - Real-time data visualization with Chart.js
  - Responsive design for mobile and tablet devices

- **Settings Page**: New dedicated settings interface
  - Enable/disable debug logging option
  - Direct link to WooCommerce logs viewer
  - Logging status indicator

- **Diagnostics Page**: System diagnostics and troubleshooting tools
  - Database table status checks
  - Required functions verification
  - File permissions checker
  - System information display

- **Enhanced Logging System**
  - Integration with WooCommerce logger
  - Settings-controlled logging (no longer tied to WP_DEBUG)
  - Categorized log levels (debug, info, warning, error, critical)
  - Improved error tracking and debugging capabilities

### Changed
- **Session Handling**: Improved session management for logged-out users
  - Enhanced `ensure_session()` method in cart class
  - Better session cookie handling for guest users
  - Fixed bundle additions for non-logged-in users
  - Improved coupon application for guest checkout

- **Database Queries**: Optimized all database operations
  - Implemented proper `$wpdb->prepare()` for all SQL queries
  - Added database caching for frequently accessed data
  - Fixed SQL interpolation issues in Bundle Manager class
  - Replaced slow meta_query operations with direct SQL where appropriate

- **WordPress Coding Standards Compliance**
  - Fixed internationalization (i18n) issues with translatable strings
  - Added proper translators comments for complex strings
  - Corrected placeholder ordering in translation functions
  - Replaced `print_r()` debugging with `json_encode()` for logging
  - Removed all `error_log()` calls in favor of WooCommerce logger

- **Analytics Data Accuracy**
  - Fixed coupon analytics to correctly track bundle coupons
  - Improved order detection including draft and placeholder orders
  - Corrected revenue calculations using WooCommerce order methods
  - Fixed date range filtering for all analytics queries
  - Enhanced bundle order rate calculations

- **Asset Management**
  - Implemented proper CSS scoping to prevent style conflicts
  - Added cache busting for CSS and JavaScript files using `filemtime()`
  - Separated analytics dashboard assets from admin assets
  - Ensured dashicons load globally on all admin pages
  - Fixed menu icon sizing issues

- **Code Quality**
  - Added comprehensive error handling with try-catch blocks
  - Implemented null checks for all database results
  - Type casting for numeric operations to prevent count() errors
  - Improved code documentation and inline comments
  - Removed temporary debug code

### Fixed
- Bundle addition not working for logged-out users
- Coupon application failing for guest checkout
- Analytics page showing incorrect or missing data
- JavaScript errors due to missing DOM elements
- PHP count() errors with null values
- Menu icons displaying incorrectly on non-plugin pages
- CSS conflicts with WordPress admin dashboard
- Date range filters not applying correctly
- Revenue calculations showing $0 despite orders
- Conversion metrics not calculating properly

### Security
- All database queries properly sanitized using `$wpdb->prepare()`
- Input validation for all user-submitted data
- Nonce verification for settings form submissions
- Permission checks on all admin pages
- Proper data escaping in all output

### Performance
- Implemented WordPress caching for bundle cleanup operations
- Optimized database queries for analytics data
- Reduced number of database calls with strategic caching
- Improved asset loading with conditional enqueuing

### Developer
- Added helper functions for common operations
- Improved code organization and file structure
- Enhanced error messages for debugging
- Added diagnostics tools for troubleshooting
- Updated translation template (POT file) with all 218 translatable strings
- Translation files ready for 28 languages

### Compatibility
- **WordPress**: Tested up to 6.9
- **WooCommerce**: Tested up to 10.3.6
- **PHP**: Requires 7.4 or higher
- **Minimum WordPress**: 6.0
- **Minimum WooCommerce**: 7.0

## [1.0.1] - 2024-11-26

### Fixed
- Minor bug fixes and improvements
- Database table creation issues
- Bundle cleanup optimization

## [1.0.0] - 2024-11-01

### Added
- Initial release
- Bundle creation and management
- Dynamic coupon generation
- Tiered discount system
- Product selection interface
- Frontend bundle display
- Cart integration
- Checkout integration

### Features
- Create unlimited bundles with custom names
- Set tiered discount percentages based on quantity
- Select specific products for each bundle
- Automatic coupon generation on bundle selection
- Real-time discount calculation
- Seamless WooCommerce integration
- Responsive design
- Translation ready

---

## Upgrade Notes

### Upgrading to 1.0.2

**Important**: This update includes significant new features and improvements.

1. **Automatic Updates**: Database tables will be automatically updated on plugin activation
2. **New Settings**: Visit Mix & Match Bundle → Settings to configure logging preferences
3. **Analytics Data**: Historical data will begin tracking after update; previous orders may not appear in analytics
4. **Cache Clearing**: Clear your browser cache and any site caching plugins after update
5. **Compatibility**: Ensure you're running WordPress 6.0+ and WooCommerce 7.0+

**What's New for Users**:
- Access the new Analytics Dashboard from the admin menu
- View detailed reports on bundle performance
- Track coupon usage and conversion rates
- Export data for external analysis
- Enable/disable debug logging from Settings page

**What's New for Developers**:
- Enhanced logging system with WooCommerce integration
- New helper functions for analytics data
- Improved code standards compliance
- Better error handling and debugging tools
- Comprehensive diagnostics page

---

For more information, visit [https://demo.betatech.co/mix-match-bundle](https://demo.betatech.co/mix-match-bundle)

