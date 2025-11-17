# Mix & Match Bundle - Troubleshooting Guide

## "Failed to save bundle" Error - FIXED

### What was the problem?
On fresh installations, the plugin's database table might not be created automatically, causing bundle saves to fail with "Failed to save bundle" error.

### What has been fixed?

#### 1. **Automatic Database Table Creation**
- The plugin now automatically creates the database table when you try to save a bundle if it doesn't exist
- No manual intervention required in most cases

#### 2. **Enhanced Error Messages**
- Errors now show specific messages instead of generic "Failed to save bundle"
- Possible error messages you might see:
  - "Bundle name is required"
  - "At least one discount tier is required"
  - "Database table does not exist and could not be created"
  - Specific MySQL errors if there are database issues

#### 3. **Manual Database Setup Option**
- If automatic creation fails, a warning banner appears on the admin page
- Click the "Setup Database Now" button to manually create the table

#### 4. **Detailed Logging**
- Comprehensive error logging in `wp-content/debug.log` (if WP_DEBUG_LOG is enabled)
- Console logging in browser developer tools for frontend debugging

## How to Debug Issues

### Step 1: Enable WordPress Debug Mode
Add these lines to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 2: Check the Admin Page
1. Go to **Mix & Match** in WordPress admin
2. Look for any warning banners at the top
3. If you see "Database Setup Required", click "Setup Database Now"

### Step 3: Check Browser Console
1. Open your browser's Developer Tools (F12)
2. Go to the Console tab
3. Try to save a bundle
4. Look for detailed error messages starting with "=== SAVING BUNDLE ==="

### Step 4: Check WordPress Debug Log
1. Look at `wp-content/debug.log`
2. Search for lines containing "mmb_save_bundle" or "MMB:"
3. Error lines will show exactly what went wrong

### Step 5: Check Database Manually
Run this SQL query in phpMyAdmin or your database tool:
```sql
SHOW TABLES LIKE 'wp_mmb_bundles';
```
(Replace `wp_` with your WordPress table prefix if different)

If the table doesn't exist, the plugin should create it automatically, but you can also:
1. Go to WordPress admin → **Plugins**
2. Deactivate the plugin
3. Reactivate the plugin (this triggers the activation hook)

## Common Issues and Solutions

### Issue: "Bundle name is required"
**Solution:** Make sure you enter a bundle name before saving.

### Issue: "At least one discount tier is required"
**Solution:** Add at least one discount tier with quantity and discount percentage.

### Issue: "Database table does not exist and could not be created"
**Possible Causes:**
1. Database user doesn't have CREATE TABLE permission
2. Database is full or has quota limits
3. Hosting restrictions

**Solution:**
1. Contact your hosting provider about database permissions
2. Check if your database has space available
3. Try the manual setup button in the admin area

### Issue: Still getting "Failed to save bundle" after fixes
**Steps to resolve:**
1. Clear browser cache and try again
2. Check if you're logged in as an administrator
3. Verify that WooCommerce is installed and active
4. Check the debug log for specific MySQL errors

## Getting More Help

If you're still experiencing issues:

1. **Check the logs**: Enable debug mode and check both browser console and `wp-content/debug.log`
2. **Verify permissions**: Make sure you're logged in as an administrator
3. **Database check**: Verify your database user has CREATE, INSERT, UPDATE permissions
4. **Plugin conflicts**: Try disabling other plugins temporarily to rule out conflicts

## Technical Details

### Database Table Structure
The plugin creates a table named `{prefix}_mmb_bundles` with these key columns:
- `id`: Auto-increment primary key
- `name`: Bundle name (required)
- `description`: Bundle description
- `product_ids`: JSON array of product IDs
- `discount_tiers`: JSON array of quantity/discount pairs
- Plus many customization fields (colors, texts, etc.)

### Validation Rules
- Bundle name: Required, non-empty
- Discount tiers: At least one tier required
- Product IDs: Stored as JSON array
- Colors: Must be valid hex colors

### Error Handling Flow
1. Client validates required fields
2. Data sent via AJAX to server
3. Server validates and sanitizes all data
4. Database table existence checked (created if needed)
5. Data inserted/updated in database
6. Success/error response sent back to client
7. Client displays appropriate message

## Changes Made to Fix the Issue

### PHP Files Modified:
1. **`includes/class-bundle-manager.php`**
   - Added `ensure_table_exists()` method
   - Enhanced error logging throughout `save_bundle()`
   - Added validation for discount tiers
   - Improved error messages with specific details
   - Better handling of database operation return values

2. **`admin/bundle-editor.php`**
   - Added database status check on page load
   - Added warning banner if table doesn't exist
   - Added manual "Setup Database Now" button

### JavaScript Files Modified:
1. **`assets/js/admin.js`**
   - Enhanced console logging for debugging
   - Improved error message display
   - Better handling of server error responses

These changes ensure that:
- Database issues are automatically resolved when possible
- Clear error messages guide users to solutions
- Manual override options are available if needed
- Comprehensive logging helps with debugging

