<?php
/**
 * Mix & Match Bundle Analytics Dashboard
 * Modern, responsive analytics interface
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if user has required permissions
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mix-match-bundle' ) );
}

// Note: Analytics assets are enqueued in main plugin file via enqueue_admin_scripts hook
// This ensures proper loading order and prevents conflicts with other admin pages

// Ensure analytics variables are defined
$date_range   = isset( $date_range ) ? $date_range : '30days';
$start_date   = isset( $start_date ) ? $start_date : '';
$end_date     = isset( $end_date ) ? $end_date : '';
$analytics_data = isset( $analytics_data ) ? $analytics_data : mmb_get_analytics_data( $date_range, $start_date, $end_date );

// Prepare bundle creation timeline data
$bundle_timeline = array();
$bundle_counts = array();
if ( ! empty( $analytics_data['bundle_analytics']['bundles'] ) ) {
    $bundles_by_date = array();
    foreach ( $analytics_data['bundle_analytics']['bundles'] as $bundle ) {
        $date = gmdate( 'M j', strtotime( $bundle->created_at ) );
        if ( ! isset( $bundles_by_date[ $date ] ) ) {
            $bundles_by_date[ $date ] = 0;
        }
        $bundles_by_date[ $date ]++;
    }
    $bundle_timeline = array_keys( $bundles_by_date );
    $bundle_counts = array_values( $bundles_by_date );
}

// Prepare revenue timeline data (group orders by week)
$revenue_timeline = array();
$revenue_amounts = array();
if ( ! empty( $analytics_data['purchase_analytics']['orders'] ) ) {
    $orders_by_week = array();
    foreach ( $analytics_data['purchase_analytics']['orders'] as $order ) {
        $week = 'Week ' . ceil( ( strtotime( $order->post_date ) - strtotime( $analytics_data['date_range']['start'] ) ) / ( 7 * 24 * 60 * 60 ) );
        if ( ! isset( $orders_by_week[ $week ] ) ) {
            $orders_by_week[ $week ] = 0;
        }
        $orders_by_week[ $week ] += floatval( $order->order_total );
    }
    $revenue_timeline = array_keys( $orders_by_week );
    $revenue_amounts = array_values( $orders_by_week );
}

// If no revenue data, show empty chart
if ( empty( $revenue_timeline ) ) {
    $revenue_timeline = array( 'Week 1', 'Week 2', 'Week 3', 'Week 4' );
    $revenue_amounts = array( 0, 0, 0, 0 );
}

// Prepare conversion data (daily conversion rates for last 7 days)
$conversion_timeline = array();
$conversion_rates = array();
for ( $i = 6; $i >= 0; $i-- ) {
    $date = gmdate( 'M j', strtotime( "-{$i} days" ) );
    $conversion_timeline[] = $date;
    // Calculate conversion rate for this day (simplified - would need actual daily tracking)
    $conversion_rates[] = $analytics_data['conversion_analytics']['conversion_rate'];
}

// Prepare data for JavaScript
$chart_data = array(
    'couponLabels' => array( __( 'Used', 'mix-match-bundle' ), __( 'Unused', 'mix-match-bundle' ) ),
    'couponData' => array(
        intval( $analytics_data['coupon_analytics']['total_used'] ),
        intval( $analytics_data['coupon_analytics']['total_unused'] )
    ),
    'bundleLabels' => ! empty( $bundle_timeline ) ? $bundle_timeline : array( __( 'No data', 'mix-match-bundle' ) ),
    'bundleData' => ! empty( $bundle_counts ) ? $bundle_counts : array( 0 ),
    'revenueLabels' => $revenue_timeline,
    'revenueData' => $revenue_amounts,
    'cartLabels' => array( __( 'Total Carts', 'mix-match-bundle' ), __( 'With Bundles', 'mix-match-bundle' ) ),
    'cartData' => array(
        intval( $analytics_data['cart_analytics']['total_carts'] ),
        intval( $analytics_data['cart_analytics']['carts_with_bundles'] )
    ),
    'conversionLabels' => $conversion_timeline,
    'conversionData' => $conversion_rates,
    'bundlePerformanceLabels' => ! empty( $analytics_data['bundle_performance']['popular_bundles'] ) 
        ? array_column( array_slice( $analytics_data['bundle_performance']['popular_bundles'], 0, 5 ), 'name' )
        : array( __( 'No bundles', 'mix-match-bundle' ) ),
    'bundlePerformanceData' => ! empty( $analytics_data['bundle_performance']['popular_bundles'] )
        ? array_column( array_slice( $analytics_data['bundle_performance']['popular_bundles'], 0, 5 ), 'usage_count' )
        : array( 0 )
);

wp_localize_script( 'mmb-analytics-dashboard', 'mmbAnalyticsData', $chart_data );

// Log analytics data for debugging (only if logging is enabled)
mmb_debug_log( 'Analytics Data - Cart: ' . json_encode( $analytics_data['cart_analytics'] ), 'info' );
mmb_debug_log( 'Analytics Data - Bundle Performance: ' . json_encode( $analytics_data['bundle_performance'] ), 'info' );
?>

<div class="wrap mmb-analytics-dashboard">
    
    <!-- Header -->
    <div class="mmb-analytics-header">
        <h1 class="mmb-analytics-title">
            <?php echo esc_html__( 'Mix & Match Bundle Analytics', 'mix-match-bundle' ); ?>
        </h1>
    </div>

    <!-- Date Filters -->
    <div class="mmb-date-filters">
        <input type="hidden" id="mmb-analytics-nonce" value="<?php echo esc_attr( wp_create_nonce( 'mmb_analytics_filter' ) ); ?>">
        <div class="mmb-filter-group">
            <label for="mmb-date-range"><?php echo esc_html__( 'Date Range', 'mix-match-bundle' ); ?></label>
            <select id="mmb-date-range" name="date_range">
                <option value="7days" <?php selected( $date_range, '7days' ); ?>><?php echo esc_html__( 'Last 7 Days', 'mix-match-bundle' ); ?></option>
                <option value="30days" <?php selected( $date_range, '30days' ); ?>><?php echo esc_html__( 'Last 30 Days', 'mix-match-bundle' ); ?></option>
                <option value="90days" <?php selected( $date_range, '90days' ); ?>><?php echo esc_html__( 'Last 90 Days', 'mix-match-bundle' ); ?></option>
                <option value="this_month" <?php selected( $date_range, 'this_month' ); ?>><?php echo esc_html__( 'This Month', 'mix-match-bundle' ); ?></option>
                <option value="last_month" <?php selected( $date_range, 'last_month' ); ?>><?php echo esc_html__( 'Last Month', 'mix-match-bundle' ); ?></option>
                <option value="this_quarter" <?php selected( $date_range, 'this_quarter' ); ?>><?php echo esc_html__( 'This Quarter', 'mix-match-bundle' ); ?></option>
                <option value="this_year" <?php selected( $date_range, 'this_year' ); ?>><?php echo esc_html__( 'This Year', 'mix-match-bundle' ); ?></option>
                <option value="custom" <?php selected( $date_range, 'custom' ); ?>><?php echo esc_html__( 'Custom Range', 'mix-match-bundle' ); ?></option>
            </select>
        </div>

        <div id="mmb-custom-dates" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>; gap: 16px;">
            <div class="mmb-filter-group">
                <label for="mmb-start-date"><?php echo esc_html__( 'Start Date', 'mix-match-bundle' ); ?></label>
                <input type="date" id="mmb-start-date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>">
            </div>
            <div class="mmb-filter-group">
                <label for="mmb-end-date"><?php echo esc_html__( 'End Date', 'mix-match-bundle' ); ?></label>
                <input type="date" id="mmb-end-date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>">
            </div>
        </div>

        <div class="mmb-filter-actions">
            <button type="button" id="mmb-apply-filter" class="mmb-btn mmb-btn-primary">
                <span class="dashicons dashicons-filter"></span>
                <?php echo esc_html__( 'Apply Filter', 'mix-match-bundle' ); ?>
            </button>
            <button type="button" class="mmb-btn mmb-btn-secondary mmb-refresh-data">
                <span class="dashicons dashicons-update"></span>
                <?php echo esc_html__( 'Refresh', 'mix-match-bundle' ); ?>
            </button>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="mmb-stats-overview">
        
        <!-- Total Coupons Created -->
        <div class="mmb-stat-card">
            <div class="mmb-stat-header">
                <div class="mmb-stat-icon blue">
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
            </div>
            <div class="mmb-stat-label"><?php echo esc_html__( 'Total Coupons', 'mix-match-bundle' ); ?></div>
            <div class="mmb-stat-value"><?php echo esc_html( number_format( $analytics_data['coupon_analytics']['total_created'] ) ); ?></div>
            <div class="mmb-stat-change <?php echo $analytics_data['coupon_analytics']['usage_rate'] > 50 ? 'positive' : 'neutral'; ?>">
                <span class="dashicons dashicons-arrow-<?php echo $analytics_data['coupon_analytics']['usage_rate'] > 50 ? 'up' : 'right'; ?>-alt"></span>
                <?php echo esc_html( $analytics_data['coupon_analytics']['usage_rate'] ); ?>% <?php echo esc_html__( 'usage rate', 'mix-match-bundle' ); ?>
            </div>
        </div>

        <!-- Total Bundles -->
        <div class="mmb-stat-card">
            <div class="mmb-stat-header">
                <div class="mmb-stat-icon green">
                    <span class="dashicons dashicons-products"></span>
                </div>
            </div>
            <div class="mmb-stat-label"><?php echo esc_html__( 'Total Bundles', 'mix-match-bundle' ); ?></div>
            <div class="mmb-stat-value"><?php echo esc_html( number_format( $analytics_data['bundle_analytics']['total_bundles'] ) ); ?></div>
            <div class="mmb-stat-change positive">
                <span class="dashicons dashicons-arrow-up-alt"></span>
                <?php echo esc_html( $analytics_data['bundle_analytics']['enabled_bundles'] ); ?> <?php echo esc_html__( 'active', 'mix-match-bundle' ); ?>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="mmb-stat-card">
            <div class="mmb-stat-header">
                <div class="mmb-stat-icon orange">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
            </div>
            <div class="mmb-stat-label"><?php echo esc_html__( 'Total Revenue', 'mix-match-bundle' ); ?></div>
            <div class="mmb-stat-value">
                <?php 
                $revenue = $analytics_data['purchase_analytics']['total_revenue'];
                echo esc_html( get_woocommerce_currency_symbol() . number_format( $revenue, 2 ) ); 
                ?>
            </div>
            <div class="mmb-stat-change <?php echo $analytics_data['purchase_analytics']['total_orders'] > 0 ? 'positive' : 'neutral'; ?>">
                <span class="dashicons dashicons-arrow-<?php echo $analytics_data['purchase_analytics']['total_orders'] > 0 ? 'up' : 'right'; ?>-alt"></span>
                <?php echo esc_html( $analytics_data['purchase_analytics']['total_orders'] ); ?> <?php echo esc_html__( 'orders', 'mix-match-bundle' ); ?>
            </div>
        </div>

        <!-- Bundle Usage Rate -->
        <div class="mmb-stat-card">
            <div class="mmb-stat-header">
                <div class="mmb-stat-icon purple">
                    <span class="dashicons dashicons-performance"></span>
                </div>
            </div>
            <div class="mmb-stat-label"><?php echo esc_html__( 'Bundle Order Rate', 'mix-match-bundle' ); ?></div>
            <div class="mmb-stat-value">
                <?php 
                // Calculate: (Orders with bundles / Total coupons created) × 100
                $total_coupons = $analytics_data['coupon_analytics']['total_created'];
                $orders_with_bundles = $analytics_data['purchase_analytics']['total_orders'];
                
                $bundle_order_rate = $total_coupons > 0 
                    ? round( ( $orders_with_bundles / $total_coupons ) * 100, 1 ) 
                    : 0;
                echo esc_html( $bundle_order_rate . '%' );
                ?>
            </div>
            <div class="mmb-stat-change <?php echo $bundle_order_rate > 30 ? 'positive' : ( $bundle_order_rate > 0 ? 'neutral' : 'neutral' ); ?>">
                <span class="dashicons dashicons-arrow-<?php echo $bundle_order_rate > 30 ? 'up' : 'right'; ?>-alt"></span>
                <?php echo esc_html( $orders_with_bundles ); ?> <?php echo esc_html__( 'orders', 'mix-match-bundle' ); ?>
            </div>
        </div>

    </div>

    <!-- Charts Grid -->
    <div class="mmb-charts-grid">
        
        <!-- Coupon Usage Chart -->
        <div class="mmb-chart-card">
            <div class="mmb-chart-header">
                <div>
                    <h3 class="mmb-chart-title"><?php echo esc_html__( 'Coupon Usage', 'mix-match-bundle' ); ?></h3>
                    <p class="mmb-chart-subtitle"><?php echo esc_html__( 'Used vs Unused Coupons', 'mix-match-bundle' ); ?></p>
                </div>
            </div>
            <div class="mmb-chart-container">
                <canvas id="mmb-coupon-chart"></canvas>
            </div>
        </div>

        <!-- Bundle Creation Chart -->
        <div class="mmb-chart-card">
            <div class="mmb-chart-header">
                <div>
                    <h3 class="mmb-chart-title"><?php echo esc_html__( 'Bundle Creation', 'mix-match-bundle' ); ?></h3>
                    <p class="mmb-chart-subtitle"><?php echo esc_html__( 'Bundles created over time', 'mix-match-bundle' ); ?></p>
                </div>
            </div>
            <div class="mmb-chart-container">
                <canvas id="mmb-bundle-chart"></canvas>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="mmb-chart-card full-width">
            <div class="mmb-chart-header">
                <div>
                    <h3 class="mmb-chart-title"><?php echo esc_html__( 'Revenue Over Time', 'mix-match-bundle' ); ?></h3>
                    <p class="mmb-chart-subtitle"><?php echo esc_html__( 'Weekly revenue breakdown', 'mix-match-bundle' ); ?></p>
                </div>
            </div>
            <div class="mmb-chart-container large">
                <canvas id="mmb-revenue-chart"></canvas>
            </div>
        </div>

        <!-- Cart Analytics Chart -->
        <div class="mmb-chart-card">
            <div class="mmb-chart-header">
                <div>
                    <h3 class="mmb-chart-title"><?php echo esc_html__( 'Cart Analytics', 'mix-match-bundle' ); ?></h3>
                    <p class="mmb-chart-subtitle"><?php echo esc_html__( 'Carts with bundle items', 'mix-match-bundle' ); ?></p>
                </div>
            </div>
            <div class="mmb-chart-container">
                <canvas id="mmb-cart-chart"></canvas>
            </div>
        </div>

        <!-- Bundle Performance Chart -->
        <div class="mmb-chart-card">
            <div class="mmb-chart-header">
                <div>
                    <h3 class="mmb-chart-title"><?php echo esc_html__( 'Top Bundles', 'mix-match-bundle' ); ?></h3>
                    <p class="mmb-chart-subtitle"><?php echo esc_html__( 'Most popular bundles', 'mix-match-bundle' ); ?></p>
                </div>
            </div>
            <div class="mmb-chart-container">
                <canvas id="mmb-bundle-performance-chart"></canvas>
            </div>
        </div>

    </div>

    <!-- Data Tables -->
    <div class="mmb-data-table-wrapper">
        <div class="mmb-chart-header">
            <div>
                <h3 class="mmb-chart-title"><?php echo esc_html__( 'Recent Activity', 'mix-match-bundle' ); ?></h3>
                <p class="mmb-chart-subtitle"><?php echo esc_html__( 'Latest coupon usage and orders', 'mix-match-bundle' ); ?></p>
            </div>
        </div>
        
        <table class="mmb-data-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Coupon Code', 'mix-match-bundle' ); ?></th>
                    <th><?php echo esc_html__( 'Created', 'mix-match-bundle' ); ?></th>
                    <th><?php echo esc_html__( 'Discount', 'mix-match-bundle' ); ?></th>
                    <th><?php echo esc_html__( 'Usage', 'mix-match-bundle' ); ?></th>
                    <th><?php echo esc_html__( 'Status', 'mix-match-bundle' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $recent_coupons = array_slice( $analytics_data['coupon_analytics']['coupons'], 0, 10 );
                if ( ! empty( $recent_coupons ) ) :
                    foreach ( $recent_coupons as $coupon ) :
                        $usage_count = isset( $coupon->usage_count ) ? intval( $coupon->usage_count ) : 0;
                        $status_class = $usage_count > 0 ? 'success' : 'warning';
                        $status_text = $usage_count > 0 ? __( 'Used', 'mix-match-bundle' ) : __( 'Unused', 'mix-match-bundle' );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $coupon->coupon_code ); ?></strong></td>
                            <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $coupon->post_date ) ) ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $coupon->discount_amount ) ); ?></td>
                            <td><?php echo esc_html( $usage_count ); ?></td>
                            <td><span class="mmb-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
                        </tr>
                    <?php endforeach;
                else : ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <div class="mmb-empty-state">
                                <div class="mmb-empty-state-icon">📊</div>
                                <div class="mmb-empty-state-title"><?php echo esc_html__( 'No Data Available', 'mix-match-bundle' ); ?></div>
                                <div class="mmb-empty-state-text"><?php echo esc_html__( 'Create your first bundle to see analytics here', 'mix-match-bundle' ); ?></div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Diagnostics Link -->
    <div style="margin-top: 24px; padding: 16px; background: #fff; border-radius: 12px; text-align: center;">
        <p style="margin: 0; color: #64748b;">
            <?php echo esc_html__( 'Having issues with analytics?', 'mix-match-bundle' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mmb-diagnostics' ) ); ?>" style="color: #3b82f6; text-decoration: none; font-weight: 500;">
                <?php echo esc_html__( 'Run Diagnostics', 'mix-match-bundle' ); ?>
            </a>
        </p>
    </div>

</div>
