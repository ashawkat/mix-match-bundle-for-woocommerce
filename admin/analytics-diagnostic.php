<?php
/**
 * Mix & Match Bundle Analytics Diagnostic
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if user has required permissions
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mix-match-bundle' ) );
}

// Get diagnostic information
$diagnostic = [
    'wp_version' => get_bloginfo( 'version' ),
    'php_version' => phpversion(),
    'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not installed',
    'db_table_exists' => false,
    'db_table_name' => mmb_get_table_name(),
    'db_table_schema' => '',
    'required_functions' => [],
    'memory_limit' => ini_get( 'memory_limit' ),
    'max_execution_time' => ini_get( 'max_execution_time' ),
    'file_permissions' => [],
];

// Check if database table exists
global $wpdb, $wp_filesystem;
$table_name   = mmb_get_table_name();
$cache_group  = 'mmb_analytics_diagnostic';
$table_exists = wp_cache_get( 'analytics_table_exists', $cache_group );
if ( false === $table_exists ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table check cached above.
    $table_exists = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
    ) === $table_name;
    wp_cache_set( 'analytics_table_exists', $table_exists, $cache_group, MINUTE_IN_SECONDS );
}

if ( ! $wp_filesystem ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    $wp_filesystem = $GLOBALS['wp_filesystem'] ?? null;
}

if ( $table_exists ) {
    $diagnostic['db_table_exists'] = true;
    
    // Get table structure
    $table_structure = wp_cache_get( 'analytics_table_schema', $cache_group );
    if ( false === $table_structure ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- schema query cached above.
        $table_structure = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME = %s",
                DB_NAME,
                $table_name
            )
        );
        wp_cache_set( 'analytics_table_schema', $table_structure, $cache_group, MINUTE_IN_SECONDS );
    }
    $diagnostic['db_table_schema'] = $table_structure;
    
    // Check if required functions exist
    $required_functions = [
        'mmb_get_table_name',
        'mmb_get_table_schema_sql',
        'mmb_dbDelta',
        'mmb_get_analytics_data',
        'mmb_get_coupon_analytics',
        'mmb_get_bundle_analytics',
        'mmb_get_purchase_analytics',
        'mmb_get_cart_analytics',
        'mmb_get_checkout_analytics',
        'mmb_get_conversion_analytics',
        'mmb_get_bundle_performance_analytics',
    ];
    
    foreach ( $required_functions as $function ) {
        if ( function_exists( $function ) ) {
            $diagnostic['required_functions'][] = $function;
        } else {
            $diagnostic['missing_functions'][] = $function;
        }
    }
    
    // Check file permissions
    $analytics_file = MMB_PLUGIN_DIR . 'admin/analytics-dashboard.php';
    if ( $wp_filesystem && $wp_filesystem->exists( $analytics_file ) ) {
        $diagnostic['file_permissions'][] = 'Readable';
        $diagnostic['file_permissions'][] = $wp_filesystem->is_writable( $analytics_file ) ? 'Writable' : 'Not writable';
    } else {
        $diagnostic['file_permissions'][] = $wp_filesystem ? 'File not found' : 'Filesystem API unavailable';
    }
    
    // Check Chart.js availability
    $chart_js = MMB_PLUGIN_DIR . 'assets/js/vendor/chart.umd.min.js';
    if ( $wp_filesystem && $wp_filesystem->exists( $chart_js ) ) {
        $diagnostic['chart_js_available'] = true;
    } else {
        $diagnostic['chart_js_available'] = false;
        $diagnostic['missing_files'][] = 'Chart.js not found';
    }
} else {
    $diagnostic['db_table_exists'] = false;
    $diagnostic['missing_functions'][] = 'mmb_get_table_name function not found';
}

// Output diagnostic as JSON
wp_send_json( $diagnostic );
?>
