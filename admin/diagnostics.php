<?php
/**
 * Bundle Builder Diagnostics Page
 * System diagnostics and troubleshooting
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if user has required permissions
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bt-bundle-builder-for-wc' ) );
}

// Run diagnostics
global $wpdb;

// Check database tables
$cache_group = 'mmb_diagnostics';
$table_cache = wp_cache_get( 'table_exists', $cache_group );
if ( false === $table_cache ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table check, caching implemented above.
    $table_cache = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
    ) === $table_name;
    wp_cache_set( 'table_exists', $table_cache, $cache_group, MINUTE_IN_SECONDS );
}
$table_exists = $table_cache;

// Check required functions
$required_functions = array(
    'mmb_get_table_name',
    'mmb_get_analytics_data',
    'mmb_get_coupon_analytics',
    'mmb_get_bundle_analytics',
    'mmb_get_purchase_analytics',
    'mmb_get_cart_analytics',
    'mmb_get_checkout_analytics',
    'mmb_get_conversion_analytics',
    'mmb_get_bundle_performance_analytics'
);

$missing_functions = array();
foreach ( $required_functions as $function ) {
    if ( ! function_exists( $function ) ) {
        $missing_functions[] = $function;
    }
}

// Get sample analytics data
$analytics_data = mmb_get_analytics_data( '30days', '', '' );

$debug_lines = array(
    'Plugin Version'        => MMB_VERSION,
    'WordPress Version'     => get_bloginfo( 'version' ),
    'PHP Version'           => phpversion(),
    'WooCommerce Version'   => $wc_active ? $wc_version : 'N/A',
    'Bundle Table Exists'   => $table_exists ? 'Yes' : 'No',
    'Sessions Table Exists' => $sessions_table_exists ? 'Yes' : 'No',
    'Total Coupons'         => $analytics_data['coupon_analytics']['total_created'],
    'Total Bundles'         => $analytics_data['bundle_analytics']['total_bundles'],
    'Total Orders'          => $analytics_data['purchase_analytics']['total_orders'],
    'Missing Functions'     => empty( $missing_functions ) ? 'None' : implode( ', ', $missing_functions ),
);

$debug_output = '';
foreach ( $debug_lines as $label => $value ) {
    $debug_output .= $label . ': ' . $value . "\n";
}

// Check WooCommerce
$wc_active = class_exists( 'WooCommerce' );
$wc_version = $wc_active ? WC()->version : 'N/A';

// Check for sessions table
$sessions_table        = esc_sql( $wpdb->prefix . 'woocommerce_sessions' );
$sessions_cache = wp_cache_get( 'sessions_table_exists', $cache_group );
if ( false === $sessions_cache ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table check, caching implemented above.
    $sessions_cache = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table )
    ) === $sessions_table;
    wp_cache_set( 'sessions_table_exists', $sessions_cache, $cache_group, MINUTE_IN_SECONDS );
}
$sessions_table_exists = $sessions_cache;

?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Bundle Builder Diagnostics', 'bt-bundle-builder-for-wc' ); ?></h1>
    
    <div class="notice notice-info">
        <p><?php echo esc_html__( 'This page helps diagnose issues with the Bundle Builder plugin and analytics system.', 'bt-bundle-builder-for-wc' ); ?></p>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2><?php echo esc_html__( 'System Information', 'bt-bundle-builder-for-wc' ); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html__( 'Plugin Version', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( MMB_VERSION ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'WordPress Version', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'PHP Version', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( phpversion() ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'WooCommerce Status', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td>
                        <?php if ( $wc_active ) : ?>
                            <span style="color: green;">✓ <?php echo esc_html__( 'Active', 'bt-bundle-builder-for-wc' ); ?> (<?php echo esc_html( $wc_version ); ?>)</span>
                        <?php else : ?>
                            <span style="color: red;">✗ <?php echo esc_html__( 'Not Active', 'bt-bundle-builder-for-wc' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2><?php echo esc_html__( 'Database Status', 'bt-bundle-builder-for-wc' ); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html__( 'Bundle Table', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td>
                        <?php if ( $table_exists ) : ?>
                            <span style="color: green;">✓ <?php echo esc_html( $table_name ); ?></span>
                        <?php else : ?>
                            <span style="color: red;">✗ <?php echo esc_html__( 'Table not found', 'bt-bundle-builder-for-wc' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'WooCommerce Sessions Table', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td>
                        <?php if ( $sessions_table_exists ) : ?>
                            <span style="color: green;">✓ <?php echo esc_html( $wpdb->prefix . 'woocommerce_sessions' ); ?></span>
                        <?php else : ?>
                            <span style="color: orange;">⚠ <?php echo esc_html__( 'Table not found (cart analytics may be limited)', 'bt-bundle-builder-for-wc' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ( $table_exists ) :
                    $bundle_table = esc_sql( $table_name );
                    $bundle_count = wp_cache_get( 'bundle_count', $cache_group );
                    if ( false === $bundle_count ) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table name sanitized above, caching implemented.
                        $bundle_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$bundle_table} WHERE 1 = %d", 1 ) );
                        wp_cache_set( 'bundle_count', $bundle_count, $cache_group, MINUTE_IN_SECONDS );
                    }
                ?>
                <tr>
                    <td><strong><?php echo esc_html__( 'Total Bundles', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $bundle_count ); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2><?php echo esc_html__( 'Required Functions', 'bt-bundle-builder-for-wc' ); ?></h2>
        <?php if ( empty( $missing_functions ) ) : ?>
            <p style="color: green;">✓ <?php echo esc_html__( 'All required functions are available', 'bt-bundle-builder-for-wc' ); ?></p>
        <?php else : ?>
            <p style="color: red;">✗ <?php echo esc_html__( 'Missing functions:', 'bt-bundle-builder-for-wc' ); ?></p>
            <ul>
                <?php foreach ( $missing_functions as $function ) : ?>
                    <li><code><?php echo esc_html( $function ); ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2><?php echo esc_html__( 'Analytics Data Sample', 'bt-bundle-builder-for-wc' ); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html__( 'Total Coupons Created', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['coupon_analytics']['total_created'] ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Coupons Used', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['coupon_analytics']['total_used'] ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Coupons Unused', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['coupon_analytics']['total_unused'] ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Total Bundles', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['bundle_analytics']['total_bundles'] ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Total Orders', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['purchase_analytics']['total_orders'] ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Total Revenue', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo wp_kses_post( wc_price( $analytics_data['purchase_analytics']['total_revenue'] ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Total Carts', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['cart_analytics']['total_carts'] ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php echo esc_html__( 'Carts with Bundles', 'bt-bundle-builder-for-wc' ); ?></strong></td>
                    <td><?php echo esc_html( $analytics_data['cart_analytics']['carts_with_bundles'] ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="max-width: 100%; margin-top: 20px;">
        <h2><?php echo esc_html__( 'Debug Information', 'bt-bundle-builder-for-wc' ); ?></h2>
        <p><?php echo esc_html__( 'Copy this information when reporting issues:', 'bt-bundle-builder-for-wc' ); ?></p>
        <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $debug_output ); ?></textarea>
    </div>

    <p style="margin-top: 20px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=mmb-analytics' ) ); ?>" class="button button-primary">
            <?php echo esc_html__( '← Back to Analytics', 'bt-bundle-builder-for-wc' ); ?>
        </a>
    </p>
</div>
