<?php
/**
 * Database Update Script for Mix & Match Bundle
 * 
 * This script adds the max_quantity column to existing installations.
 * Only run this if the automatic database update didn't work.
 * 
 * USAGE:
 * 1. Access this file via browser: yoursite.com/wp-content/plugins/mix-match-bundle/update-database.php
 * 2. Or run via WP-CLI: wp eval-file update-database.php
 * 3. Delete this file after successful update for security
 * 
 * @package MixMatchBundle
 * @version 2.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // If not in WordPress context, try to load it
    $wp_load = dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php';
    if ( file_exists( $wp_load ) ) {
        require_once $wp_load;
    } else {
        die( 'WordPress not found. Please run this script from WordPress context.' );
    }
}

// Check user permissions
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to run this script.' );
}

/**
 * Add max_quantity column to mmb_bundles table
 */
function mmb_update_database_add_max_quantity() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mmb_bundles';
    
    // Check if table exists
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
    if ( ! $table_exists ) {
        return [
            'success' => false,
            'message' => "Table {$table_name} does not exist. Please activate the plugin first."
        ];
    }
    
    // Check if column already exists
    $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'max_quantity'" );
    if ( ! empty( $column_exists ) ) {
        return [
            'success' => true,
            'message' => 'Column max_quantity already exists. No update needed.',
            'existing' => true
        ];
    }
    
    // Add the column
    $result = $wpdb->query( 
        "ALTER TABLE {$table_name} 
         ADD COLUMN max_quantity int DEFAULT 10 
         AFTER use_quantity" 
    );
    
    if ( $result === false ) {
        return [
            'success' => false,
            'message' => 'Failed to add max_quantity column: ' . $wpdb->last_error
        ];
    }
    
    // Verify column was added
    $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'max_quantity'" );
    if ( empty( $column_exists ) ) {
        return [
            'success' => false,
            'message' => 'Column addition reported success but verification failed.'
        ];
    }
    
    // Count how many bundles were affected
    $bundle_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    
    return [
        'success' => true,
        'message' => "Successfully added max_quantity column. {$bundle_count} existing bundle(s) will use default value of 10.",
        'bundles_affected' => $bundle_count
    ];
}

// Run the update
$result = mmb_update_database_add_max_quantity();

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mix & Match Bundle - Database Update</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .result {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        .success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        .info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            color: #0c5460;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .button:hover {
            background: #5568d3;
        }
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Update</h1>
        <p style="color: #666; margin-bottom: 20px;">Mix & Match Bundle Plugin</p>
        
        <?php if ( $result['success'] ) : ?>
            <div class="result success">
                <div class="icon">✅</div>
                <strong>Update Successful!</strong>
                <p><?php echo esc_html( $result['message'] ); ?></p>
                
                <?php if ( isset( $result['bundles_affected'] ) && $result['bundles_affected'] > 0 ) : ?>
                    <div class="details">
                        <strong>Bundles Affected:</strong> <?php echo intval( $result['bundles_affected'] ); ?><br>
                        <strong>Default Max Quantity:</strong> 10<br>
                        <strong>Next Step:</strong> Edit bundles to set custom max quantities if needed
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> Please delete this file (<code>update-database.php</code>) after successful update to prevent unauthorized access.
            </div>
            
        <?php else : ?>
            <div class="result error">
                <div class="icon">❌</div>
                <strong>Update Failed</strong>
                <p><?php echo esc_html( $result['message'] ); ?></p>
                
                <div class="details">
                    <strong>Troubleshooting:</strong><br>
                    1. Check database permissions<br>
                    2. Verify plugin is activated<br>
                    3. Check WordPress error logs<br>
                    4. Try deactivating and reactivating the plugin
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <a href="<?php echo admin_url( 'admin.php?page=mmb-bundles' ); ?>" class="button">
                Go to Bundle Manager
            </a>
            <a href="<?php echo admin_url(); ?>" class="button" style="background: #6c757d; margin-left: 10px;">
                Go to Dashboard
            </a>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 14px;">
            <strong>Manual SQL (if needed):</strong>
            <div class="details" style="margin-top: 10px;">
                ALTER TABLE <?php echo $wpdb->prefix; ?>mmb_bundles<br>
                ADD COLUMN max_quantity int DEFAULT 10 AFTER use_quantity;
            </div>
            <p style="margin-top: 10px; font-size: 12px;">Run this via phpMyAdmin or database tool if automatic update fails.</p>
        </div>
    </div>
</body>
</html>
<?php
// Log the update attempt
error_log( 'MMB Database Update: ' . ( $result['success'] ? 'SUCCESS' : 'FAILED' ) . ' - ' . $result['message'] );
?>

