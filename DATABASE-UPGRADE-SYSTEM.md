# Automatic Database Upgrade System

## Overview
The plugin now includes an **automatic database upgrade system** that checks for schema changes on every admin page load and updates the database accordingly. No manual intervention required!

## How It Works

### 1. Version Tracking
```php
define( 'MMB_DB_VERSION', '2.1' );
```
- Plugin tracks database schema version separately from plugin version
- Stored in WordPress options as `mmb_db_version`
- Current version: **2.1** (includes `max_quantity` field)

### 2. Automatic Check (admin_init)
```php
add_action( 'admin_init', 'mmb_check_database_upgrade' );
```
- Runs on **every admin page load**
- Compares current DB version with required version
- Only runs in admin area (no frontend performance impact)
- Fast check: exits immediately if versions match

### 3. Smart Upgrade Process
When an upgrade is needed:
1. ✅ Verifies table exists
2. ✅ Checks if column already exists (prevents duplicates)
3. ✅ Adds missing columns via ALTER TABLE
4. ✅ Updates version in options table
5. ✅ Shows success/error admin notice
6. ✅ Logs all actions for debugging

### 4. Dual Protection
The system has **two layers** of database upgrade:

#### Layer 1: On Plugin Activation
```php
public function activate_plugin() {
    // Creates table with current schema
    dbDelta( $sql );
    
    // Runs upgrade check for existing installations
    $this->maybe_upgrade_database();
    
    // Sets database version
    update_option( 'mmb_db_version', MMB_DB_VERSION );
}
```

#### Layer 2: On Admin Page Load
```php
function mmb_check_database_upgrade() {
    // Automatic check every admin page
    if ( version_compare( $current_db_version, MMB_DB_VERSION, '<' ) ) {
        // Add missing columns
        // Update version
        // Show notice
    }
}
```

## Version History

| DB Version | Changes | Date |
|------------|---------|------|
| 1.0 | Initial schema | Original |
| 2.1 | Added `max_quantity` column | Current |

## Upgrade Scenarios

### Scenario 1: Fresh Installation
```
User installs plugin
    ↓
Plugin activation runs
    ↓
Table created with full schema (including max_quantity)
    ↓
Database version set to 2.1
    ↓
✅ Everything works immediately
```

### Scenario 2: Existing Installation (Plugin Already Active)
```
User updates plugin files
    ↓
User visits any admin page
    ↓
admin_init hook runs
    ↓
Detects DB version 1.0 < 2.1
    ↓
Adds max_quantity column
    ↓
Updates version to 2.1
    ↓
Shows success notice
    ↓
✅ Database updated automatically
```

### Scenario 3: Existing Installation (Plugin Deactivated)
```
User updates plugin files
    ↓
User activates plugin
    ↓
activate_plugin() runs
    ↓
maybe_upgrade_database() adds missing columns
    ↓
Database version set to 2.1
    ↓
✅ Database updated on activation
```

## Admin Notices

### Success Notice
```
┌─────────────────────────────────────────────────┐
│ ✓ Mix & Match Bundle: Database updated          │
│   successfully! New "Maximum Quantity" feature  │
│   is now available.                             │
│                                            [×]  │
└─────────────────────────────────────────────────┘
```

### Error Notice (Rare)
```
┌─────────────────────────────────────────────────┐
│ ✗ Mix & Match Bundle: Database update failed.   │
│   Please check error logs or contact support.   │
│                                                  │
│   Error: [specific database error message]      │
│                                            [×]  │
└─────────────────────────────────────────────────┘
```

## Logging

All upgrade actions are logged for debugging:

```
MMB: Database upgrade needed from 1.0 to 2.1
MMB: Adding max_quantity column to database
MMB: Successfully added max_quantity column
MMB: Database version updated to 2.1
```

View logs:
- **WordPress Debug Log**: `wp-content/debug.log` (if WP_DEBUG is enabled)
- **Server Error Log**: Check your hosting control panel
- **Browser Console**: For admin notices

## Performance

### Impact: Minimal
- Check runs once per admin page load
- Takes ~2-5ms when no upgrade needed (just version comparison)
- Takes ~50-200ms when upgrade is needed (one-time only)
- Zero frontend impact (only runs in admin)

### Optimization
```php
// Fast exit if versions match
if ( version_compare( $current_db_version, MMB_DB_VERSION, '<' ) ) {
    // Only runs upgrade code if needed
}
```

## Database Migration Safety

### Safeguards:
1. ✅ **Idempotent**: Safe to run multiple times (won't duplicate columns)
2. ✅ **Non-destructive**: Only adds columns, never removes data
3. ✅ **Error handling**: Catches exceptions, logs errors
4. ✅ **Graceful degradation**: Site continues working even if upgrade fails
5. ✅ **Default values**: New columns have sensible defaults (10 for max_quantity)

### SQL Executed:
```sql
-- Only runs if column doesn't exist
ALTER TABLE wp_mmb_bundles 
ADD COLUMN max_quantity int DEFAULT 10 
AFTER use_quantity;
```

## Adding Future Upgrades

To add a new database change in the future:

### Step 1: Update DB Version
```php
define( 'MMB_DB_VERSION', '2.2' ); // Increment version
```

### Step 2: Add Upgrade Logic
```php
function mmb_check_database_upgrade() {
    // ... existing code ...
    
    // Version 2.2 upgrade: Add new_field column
    if ( version_compare( $current_db_version, '2.2', '<' ) ) {
        $column_exists = $wpdb->get_results( 
            "SHOW COLUMNS FROM {$table_name} LIKE 'new_field'" 
        );
        
        if ( empty( $column_exists ) ) {
            $wpdb->query( 
                "ALTER TABLE {$table_name} 
                 ADD COLUMN new_field varchar(255) DEFAULT 'default_value' 
                 AFTER some_column" 
            );
        }
    }
    
    // Update version (this stays at the end)
    update_option( 'mmb_db_version', MMB_DB_VERSION );
}
```

### Step 3: Update CREATE TABLE Statement
```php
$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
    ...
    new_field varchar(255) DEFAULT 'default_value',
    ...
)";
```

### Step 4: Update maybe_upgrade_database() Method
```php
// Add same check to the class method
$new_field_exists = $wpdb->get_results( 
    $wpdb->prepare(
        "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_schema = %s 
        AND table_name = %s 
        AND column_name = 'new_field'",
        DB_NAME,
        $table_name
    )
);

if ( empty( $new_field_exists ) ) {
    $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN new_field varchar(255) DEFAULT 'default_value' AFTER some_column" );
}
```

## Troubleshooting

### Issue: "Unknown column 'max_quantity'"
**Solution:** Database hasn't been updated yet
```
1. Visit any WordPress admin page
2. Check for success notice
3. If no notice, check error logs
4. Try deactivating and reactivating plugin
```

### Issue: Upgrade notice keeps showing
**Solution:** Clear WordPress object cache
```
1. If using Redis/Memcached, flush cache
2. Delete transients: wp transient delete --all
3. Check if option is being set: SELECT * FROM wp_options WHERE option_name = 'mmb_db_version'
```

### Issue: Duplicate column error
**Solution:** Column already exists but version wasn't updated
```
1. Manually set version: update_option('mmb_db_version', '2.1')
2. Or run: UPDATE wp_options SET option_value = '2.1' WHERE option_name = 'mmb_db_version'
```

### Issue: Permission denied error
**Solution:** Database user lacks ALTER permissions
```
1. Contact hosting provider
2. Grant ALTER permission to database user
3. Or manually run SQL via phpMyAdmin
```

## Manual Check (For Developers)

Check current database version:
```php
$db_version = get_option( 'mmb_db_version', '1.0' );
echo "Current DB Version: " . $db_version;
```

Force database upgrade:
```php
// Temporarily set old version
update_option( 'mmb_db_version', '1.0' );

// Reload admin page to trigger upgrade
```

Verify max_quantity column exists:
```sql
SHOW COLUMNS FROM wp_mmb_bundles LIKE 'max_quantity';
```

## Best Practices

### ✅ DO:
- Increment DB version for any schema change
- Use default values for new columns
- Check if column exists before adding
- Log all actions for debugging
- Show user-friendly notices
- Test upgrades on staging first

### ❌ DON'T:
- Remove or rename columns (breaks backward compatibility)
- Forget to update CREATE TABLE statement
- Skip version increment
- Modify data during upgrade (just structure)
- Run destructive migrations without backups

## Testing Upgrades

### Local Testing:
```bash
# 1. Install old version
wp plugin install mix-match-bundle --version=1.0

# 2. Create test bundle
wp mmb create-bundle "Test Bundle"

# 3. Set old DB version
wp option update mmb_db_version "1.0"

# 4. Update to new version
wp plugin update mix-match-bundle

# 5. Visit admin page - should auto-upgrade
wp admin

# 6. Verify column exists
wp db query "SHOW COLUMNS FROM wp_mmb_bundles LIKE 'max_quantity'"
```

### Staging Testing:
1. Clone production database
2. Deploy new plugin version
3. Visit admin page
4. Verify upgrade notice
5. Test bundle save/edit
6. Check for errors in logs

## Summary

The automatic database upgrade system ensures:

- 🚀 **Zero downtime** - Upgrades happen automatically
- 🔒 **Safe migrations** - Non-destructive, idempotent
- 👥 **User-friendly** - Clear notices, no manual steps
- 🐛 **Easy debugging** - Comprehensive logging
- ⚡ **High performance** - Minimal overhead
- 🔄 **Always current** - Database stays in sync with code

**Result:** Users update the plugin, visit admin, and everything "just works"! ✨

