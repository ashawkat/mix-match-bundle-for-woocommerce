<?php
/**
 * Plugin Name: Mix & Match Bundle for WooCommerce
 * Plugin URI: https://demo.betatech.co/mix-match-bundle
 * Description: Create customizable bundle promotions with tiered discounts based on quantity. Now with advanced analytics, reporting, and coupon tracking.
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Betatech
 * Author URI: https://betatech.co
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: mix-match-bundle
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MMB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MMB_VERSION', '1.0.2' );
define( 'MMB_DB_VERSION', '2.1' ); // Database version for schema updates

/**
 * Get the fully-qualified bundles table name.
 *
 * @since 2.1
 *
 * @return string
 */
function mmb_get_table_name() {
    global $wpdb;

    $raw_name = $wpdb->prefix . 'mmb_bundles';
    $sanitized = preg_replace( '/[^A-Za-z0-9_]/', '', $raw_name );

    return esc_sql( $sanitized ?: $raw_name );
}

/**
 * Apply database schema changes using dbDelta
 *
 * @since 2.1
 *
 * @param string $schema_sql The SQL schema to apply
 */
function mmb_dbDelta( $schema_sql ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $schema_sql );
}

/**
 * Get the SQL statement used for dbDelta
 *
 * @since 2.1
 *
 * @return string
 */
function mmb_get_table_schema_sql() {
    global $wpdb;

    $table_name = mmb_get_table_name();

    return "CREATE TABLE {$table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description longtext,
        enabled tinyint(1) DEFAULT 1,
        use_quantity tinyint(1) DEFAULT 0,
        max_quantity int DEFAULT 10,
        discount_tiers longtext,
        product_ids longtext,
        heading_text varchar(255) DEFAULT 'Select Your Products Below',
        hint_text varchar(255) DEFAULT 'Bundle 2, 3, 4 or 5 items and watch the savings grow.',
        primary_color varchar(7) DEFAULT '#4caf50',
        accent_color varchar(7) DEFAULT '#45a049',
        hover_bg_color varchar(7) DEFAULT '#388e3c',
        hover_accent_color varchar(7) DEFAULT '#2e7d32',
        button_text_color varchar(7) DEFAULT '#ffffff',
        button_text varchar(255) DEFAULT 'Add Bundle to Cart',
        progress_text varchar(255) DEFAULT 'Your Savings Progress',
        cart_behavior varchar(20) DEFAULT 'sidecart',
        show_bundle_title tinyint(1) DEFAULT 1,
        show_bundle_description tinyint(1) DEFAULT 1,
        show_heading_text tinyint(1) DEFAULT 1,
        show_hint_text tinyint(1) DEFAULT 1,
        show_progress_text tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        primary key (id)
    )";
}

/**
 * Check and upgrade database if needed
 * Runs on every admin page load to ensure database is up to date
 *
 * @since 2.1
 */
function mmb_check_database_upgrade() {
    if ( ! is_admin() ) {
        return;
    }

    global $wpdb;

    $current_db_version = get_option( 'mmb_db_version', '1.0' );

    if ( version_compare( $current_db_version, MMB_DB_VERSION, '<' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $previous_error = $wpdb->last_error;
        $schema_sql     = mmb_get_table_schema_sql();
        dbDelta( $schema_sql );
        $had_error      = ! empty( $wpdb->last_error ) && $wpdb->last_error !== $previous_error;

        update_option( 'mmb_db_version', MMB_DB_VERSION );

        if ( current_user_can( 'manage_options' ) ) {
            add_action(
                'admin_notices',
                function () use ( $had_error ) {
                    ?>
                    <div class="notice notice-<?php echo $had_error ? 'error' : 'success'; ?> <?php echo $had_error ? '' : 'is-dismissible'; ?>">
                        <p>
                            <strong><?php echo esc_html__( 'Mix & Match Bundle:', 'mix-match-bundle' ); ?></strong>
                            <?php
                            if ( $had_error ) {
                                echo esc_html__( 'Database update encountered an issue. Please review the debug log for details.', 'mix-match-bundle' );
                            } else {
                                echo esc_html__( 'Database schema verified successfully.', 'mix-match-bundle' );
                            }
                            ?>
                        </p>
                    </div>
                    <?php
                }
            );
        }
    }
}
add_action( 'admin_init', 'mmb_check_database_upgrade' );

/**
 * Helper function for debug logging
 * Logs to WooCommerce logger if logging is enabled in settings
 * 
 * @param string $message Log message
 * @param string $level Log level (debug, info, notice, warning, error, critical, alert, emergency)
 */
function mmb_debug_log( $message, $level = 'info' ) {
    // Check if logging is enabled in settings
    $logging_enabled = get_option( 'mmb_enable_logging', 'no' );
    
    if ( $logging_enabled !== 'yes' ) {
        return;
    }
    
    // Use WooCommerce logger if available
    if ( function_exists( 'wc_get_logger' ) ) {
        $logger = wc_get_logger();
        $logger->log( $level, $message, array( 'source' => 'mix-match-bundle' ) );
    }
}

/**
 * Check if WooCommerce is active
 *
 * @since 1.0.0
 * @return bool
 */
function mmb_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Admin notice if WooCommerce is not active
 *
 * @since 1.0.0
 */
function mmb_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php echo esc_html__( 'Mix & Match Bundle for WooCommerce', 'mix-match-bundle' ); ?></strong> 
            <?php echo esc_html__( 'requires WooCommerce to be installed and active.', 'mix-match-bundle' ); ?>
            <a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ); ?>">
                <?php echo esc_html__( 'Install WooCommerce', 'mix-match-bundle' ); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Deactivate plugin if WooCommerce is not active
 *
 * @since 1.0.0
 */
function mmb_check_woocommerce_dependency() {
    if ( ! mmb_is_woocommerce_active() ) {
        // Deactivate the plugin
        deactivate_plugins( plugin_basename( __FILE__ ) );
        
        // Show admin notice
        add_action( 'admin_notices', 'mmb_woocommerce_missing_notice' );
        
        // Prevent plugin from loading
        return false;
    }
    return true;
}

// Main plugin class
class Mix_Match_Bundle {
    
    private static $instance = null;
    private $frontend_assets_forced = false;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        $this->includes();
        $this->hooks();
        
        // Run database upgrade check only once per version
        $db_version = get_option( 'mmb_db_version', '1.0' );
        if ( version_compare( $db_version, MMB_DB_VERSION, '<' ) ) {
            $this->maybe_upgrade_database();
            update_option( 'mmb_db_version', MMB_DB_VERSION );
        }
    }
    
    private function includes() {
        require_once MMB_PLUGIN_DIR . 'includes/class-settings.php';
        require_once MMB_PLUGIN_DIR . 'includes/class-bundle-manager.php';
        require_once MMB_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once MMB_PLUGIN_DIR . 'includes/class-cart.php';
        require_once MMB_PLUGIN_DIR . 'includes/class-shortcode.php';
    }
    
    private function hooks() {
        // Admin hooks
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'admin_head', [ $this, 'add_menu_icon_styles' ] ); // Inline styles for menu icon
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_action_links' ] );
        
        // Frontend hooks
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        
        // Database setup
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
        
        // HPOS Compatibility
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
    }
    
    /**
     * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage)
     * 
     * @since 1.0.0
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
                'custom_order_tables', 
                __FILE__, 
                true 
            );
        }
    }
    
    /**
     * Add settings link on plugin page
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=mix-match-bundles' ) . '">' . __( 'Settings', 'mix-match-bundle' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
    
    public function register_admin_menu() {
        add_menu_page(
            __( 'Mix & Match Bundles', 'mix-match-bundle' ),
            __( 'Mix & Match', 'mix-match-bundle' ),
            'manage_options',
            'mix-match-bundles',
            [ $this, 'admin_page' ],
            MMB_PLUGIN_URL . 'assets/img/mix-match-icon.svg',
            30
        );
        
        // Add analytics submenu page
        add_submenu_page(
            'mix-match-bundles',
            __( 'Bundle Analytics', 'mix-match-bundle' ),
            __( 'Analytics', 'mix-match-bundle' ),
            'manage_options',
            'mmb-analytics',
            [ $this, 'analytics_page' ]
        );
        
        // Add diagnostics submenu page
        add_submenu_page(
            'mix-match-bundles',
            __( 'Diagnostics', 'mix-match-bundle' ),
            __( 'Diagnostics', 'mix-match-bundle' ),
            'manage_options',
            'mmb-diagnostics',
            [ $this, 'diagnostics_page' ]
        );
        
        // Add settings submenu page
        add_submenu_page(
            'mix-match-bundles',
            __( 'Settings', 'mix-match-bundle' ),
            __( 'Settings', 'mix-match-bundle' ),
            'manage_options',
            'mmb-settings',
            [ $this, 'settings_page' ]
        );
    }
    
    /**
     * Add inline styles for menu icon on all admin pages
     */
    public function add_menu_icon_styles() {
        ?>
        <style type="text/css">
            #adminmenu #toplevel_page_mix-match-bundles .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                padding: 6px 0 !important;
                opacity: 0.6;
            }
            #adminmenu #toplevel_page_mix-match-bundles:hover .wp-menu-image img,
            #adminmenu #toplevel_page_mix-match-bundles.wp-has-current-submenu .wp-menu-image img,
            #adminmenu #toplevel_page_mix-match-bundles.current .wp-menu-image img {
                opacity: 1;
            }
        </style>
        <?php
    }
    
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include MMB_PLUGIN_DIR . 'admin/bundle-editor.php';
    }
    
    public function enqueue_admin_scripts( $hook ) {
        // Always ensure dashicons are loaded on all admin pages
        wp_enqueue_style( 'dashicons' );
        
        // Only load plugin-specific assets on our plugin pages
        if ( strpos( $hook, 'mix-match' ) === false && strpos( $hook, 'mmb-' ) === false ) {
            return;
        }
        
        // Load main admin styles only on Mix & Match pages
        // Using filemtime for cache busting during development
        $css_version = MMB_VERSION;
        $js_version = MMB_VERSION;
        
        if ( file_exists( MMB_PLUGIN_DIR . 'assets/css/admin.css' ) ) {
            $css_version .= '-' . filemtime( MMB_PLUGIN_DIR . 'assets/css/admin.css' );
        }
        if ( file_exists( MMB_PLUGIN_DIR . 'assets/js/admin.js' ) ) {
            $js_version .= '-' . filemtime( MMB_PLUGIN_DIR . 'assets/js/admin.js' );
        }
        
        wp_enqueue_style( 'mix-match-admin', MMB_PLUGIN_URL . 'assets/css/admin.css', array( 'dashicons' ), $css_version );
        wp_enqueue_script( 'mix-match-admin', MMB_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-api' ), $js_version, true );
        
        wp_localize_script( 'mix-match-admin', 'mmb_admin', array(
            'nonce' => wp_create_nonce( 'mmb_admin_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ));
        
        // Analytics-specific assets (only on analytics page)
        if ( strpos( $hook, 'mmb-analytics' ) !== false ) {
            $analytics_css_version = MMB_VERSION;
            $analytics_js_version = MMB_VERSION;
            
            if ( file_exists( MMB_PLUGIN_DIR . 'assets/css/analytics-dashboard.css' ) ) {
                $analytics_css_version .= '-' . filemtime( MMB_PLUGIN_DIR . 'assets/css/analytics-dashboard.css' );
            }
            if ( file_exists( MMB_PLUGIN_DIR . 'assets/js/analytics-dashboard.js' ) ) {
                $analytics_js_version .= '-' . filemtime( MMB_PLUGIN_DIR . 'assets/js/analytics-dashboard.js' );
            }
            
            wp_enqueue_style( 'mmb-analytics-dashboard', MMB_PLUGIN_URL . 'assets/css/analytics-dashboard.css', array( 'dashicons' ), $analytics_css_version );
            
            $chart_js_version = '4.4.0';
            $chart_js_path    = MMB_PLUGIN_DIR . 'assets/js/vendor/chart.umd.min.js';
            if ( file_exists( $chart_js_path ) ) {
                $chart_js_version .= '-' . filemtime( $chart_js_path );
            }
            
            wp_enqueue_script( 'mmb-chart-js', MMB_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js', array(), $chart_js_version, true );
            wp_enqueue_script( 'mmb-analytics-dashboard', MMB_PLUGIN_URL . 'assets/js/analytics-dashboard.js', array( 'jquery', 'mmb-chart-js' ), $analytics_js_version, true );
        }
    }
    
    public function enqueue_frontend_scripts() {
        if ( ! $this->should_enqueue_frontend_assets() ) {
            return;
        }
        
        $css_path = MMB_PLUGIN_DIR . 'assets/css/frontend.css';
        $js_path  = MMB_PLUGIN_DIR . 'assets/js/frontend.js';

        $css_version = MMB_VERSION;
        $js_version  = MMB_VERSION;

        if ( file_exists( $css_path ) ) {
            $css_version .= '-' . filemtime( $css_path );
        }

        if ( file_exists( $js_path ) ) {
            $js_version .= '-' . filemtime( $js_path );
        }

        wp_enqueue_style( 'mix-match-frontend', MMB_PLUGIN_URL . 'assets/css/frontend.css', [], $css_version );
        wp_enqueue_script( 'mix-match-frontend', MMB_PLUGIN_URL . 'assets/js/frontend.js', [], $js_version, true );
        
        // Get bundle discount info for JavaScript
        $bundle_discount_data = [];
        if ( WC()->session ) {
            $session_data = WC()->session->get( 'mmb_bundle_discount' );
            if ( $session_data && ! empty( $session_data['product_ids'] ) ) {
                $bundle_discount_data = $session_data;
            }
        }
        
        wp_localize_script( 'mix-match-frontend', 'mmb_frontend', [
            'nonce' => wp_create_nonce( 'mmb_frontend_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'cart_url' => wc_get_cart_url(),
            'bundle_discount' => $bundle_discount_data,
            'discount_label' => __( 'Bundle Discount', 'mix-match-bundle' ),
        ]);
    }
    
    /**
     * Allow developers to explicitly request frontend assets.
     * Useful when the shortcode is rendered outside standard content areas.
     */
    public function force_frontend_assets() {
        $this->frontend_assets_forced = true;
    }
    
    /**
     * Determine whether frontend assets should be loaded on the current request.
     */
    private function should_enqueue_frontend_assets() {
        if ( is_admin() ) {
            return false;
        }
        
        $should_load = false;
        
        if ( $this->frontend_assets_forced ) {
            $should_load = true;
        } elseif ( $this->current_view_has_shortcode() ) {
            $should_load = true;
        }
        
        /**
         * Filter whether Mix & Match should enqueue frontend assets.
         *
         * @param bool $should_load Whether assets should be loaded.
         */
        return apply_filters( 'mmb_should_enqueue_frontend_assets', $should_load );
    }
    
    /**
     * Check if the current page content contains the Mix & Match shortcode.
     */
    private function current_view_has_shortcode() {
        if ( is_singular() ) {
            global $post;
            if ( $this->post_contains_bundle_shortcode( $post ) ) {
                return true;
            }
        }
        
        if ( is_home() || is_front_page() || is_archive() ) {
            global $posts;
            if ( ! empty( $posts ) ) {
                foreach ( $posts as $post ) {
                    if ( $this->post_contains_bundle_shortcode( $post ) ) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Helper to check if a post has the [mmb_bundle] shortcode.
     *
     * @param WP_Post|null $post
     */
    private function post_contains_bundle_shortcode( $post ) {
        return ( $post instanceof WP_Post ) && has_shortcode( $post->post_content, 'mmb_bundle' );
    }
    
    private function maybe_upgrade_database() {
        try {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( mmb_get_table_schema_sql() );
            
            // Log success for debugging
            mmb_debug_log( 'MMB: Database upgrade successful' );
        } catch ( Exception $e ) {
            // Log error for debugging
            mmb_debug_log( 'MMB Database upgrade error: ' . $e->getMessage(), 'error' );
            
            // Store error for admin notice
            update_option( 'mmb_db_upgrade_error', $e->getMessage() );
        }
    }
    
    public function activate_plugin() {
        try {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( mmb_get_table_schema_sql() );
            
            // Run upgrade check
            $this->maybe_upgrade_database();
            
            // Set initial database version
            update_option( 'mmb_db_version', MMB_DB_VERSION );

            flush_rewrite_rules();
            
            // Log success for debugging
            mmb_debug_log( 'MMB: Plugin activation successful' );
        } catch ( Exception $e ) {
            // Log error for debugging
            mmb_debug_log( 'MMB Plugin activation error: ' . $e->getMessage(), 'error' );
            
            // Store error for admin notice
            update_option( 'mmb_activation_error', $e->getMessage() );
            
            // Re-throw to let WordPress handle the error
            throw $e;
        }
    }

    public function analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Handle form submissions for date filtering
        $post_nonce = isset( $_POST['mmb_analytics_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mmb_analytics_nonce'] ) ) : '';
        if ( $post_nonce && wp_verify_nonce( $post_nonce, 'mmb_analytics_action' ) ) {
            $this->handle_analytics_form_submission();
        }
        
        $date_range = '30days';
        $start_date = '';
        $end_date   = '';
        
        // Prefer GET parameters with nonce verification
        $get_nonce = isset( $_GET['mmb_analytics_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['mmb_analytics_nonce'] ) ) : '';
        if ( $get_nonce && wp_verify_nonce( $get_nonce, 'mmb_analytics_filter' ) ) {
            $date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : $date_range;
            $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
            $end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
        } else {
            // Fallback to POST (legacy) if nonce missing
            $date_range = isset( $_POST['date_range'] ) ? sanitize_text_field( wp_unslash( $_POST['date_range'] ) ) : $date_range;
            $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : $start_date;
            $end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : $end_date;
        }
        
        // Get analytics data using global function
        $analytics_data = mmb_get_analytics_data( $date_range, $start_date, $end_date );
        
        include MMB_PLUGIN_DIR . 'admin/analytics-dashboard.php';
    }
    
    public function diagnostics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        include MMB_PLUGIN_DIR . 'admin/diagnostics.php';
    }
    
    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Handle settings form submission
        $settings_nonce = isset( $_POST['mmb_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['mmb_settings_nonce'] ) ) : '';
        if ( $settings_nonce && wp_verify_nonce( $settings_nonce, 'mmb_settings_action' ) ) {
            $enable_logging = isset( $_POST['mmb_enable_logging'] ) ? 'yes' : 'no';
            update_option( 'mmb_enable_logging', $enable_logging );
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'mix-match-bundle' ) . '</p></div>';
        }
        
        include MMB_PLUGIN_DIR . 'admin/settings.php';
    }
    
    /**
     * Handle analytics form submission
     */
    private function handle_analytics_form_submission() {
        // Form data will be processed in get_analytics_data method
        // Redirect to prevent form resubmission
        wp_safe_redirect( add_query_arg( 'settings-updated', 'true', menu_page_url( 'mmb-analytics' ) ) );
        exit;
    }
}

// Plugin initialization moved to mmb_init_plugin() function
// which runs on 'plugins_loaded' hook to ensure WooCommerce is available

// AJAX Handlers
add_action( 'wp_ajax_mmb_save_bundle', 'mmb_save_bundle' );
add_action( 'wp_ajax_mmb_get_bundles', 'mmb_get_bundles' );
add_action( 'wp_ajax_mmb_delete_bundle', 'mmb_delete_bundle' );
add_action( 'wp_ajax_mmb_search_products', 'mmb_search_products' );
add_action( 'wp_ajax_mmb_get_products_by_ids', 'mmb_get_products_by_ids' );
add_action( 'wp_ajax_nopriv_mmb_update_bundle_items', 'mmb_update_bundle_items' );
add_action( 'wp_ajax_mmb_update_bundle_items', 'mmb_update_bundle_items' );
add_action( 'wp_ajax_nopriv_mmb_add_bundle_to_cart', 'mmb_add_bundle_to_cart' );
add_action( 'wp_ajax_mmb_add_bundle_to_cart', 'mmb_add_bundle_to_cart' );
add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', 'mmb_wc_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'mmb_wc_ajax_add_to_cart' );
add_action( 'wp_ajax_mmb_wc_ajax_add_to_cart', 'mmb_wc_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_mmb_wc_ajax_add_to_cart', 'mmb_wc_ajax_add_to_cart' );

// AJAX handler for debug function
add_action( 'wp_ajax_mmb_debug_coupon_state', 'mmb_debug_coupon_state' );

function mmb_save_bundle() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }
    
    // Debug: Log what PHP receives
    
    $bundle_manager = new MMB_Bundle_Manager();
    $result = $bundle_manager->save_bundle( wp_unslash( $_POST ) );
    
    if ( $result ) {
        wp_send_json_success( $result );
    } else {
        // Return detailed error message
        global $wpdb;
        $error_message = $wpdb->last_error ? $wpdb->last_error : __( 'Failed to save bundle', 'mix-match-bundle' );
        wp_send_json_error( $error_message );
    }
}

function mmb_get_bundles() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }
    
    $bundle_manager = new MMB_Bundle_Manager();
    $bundles = $bundle_manager->get_all_bundles();
    
    // Debug: Log what we're sending
    foreach ( $bundles as $bundle ) {
    }
    
    wp_send_json_success( $bundles );
}

function mmb_delete_bundle() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( wp_unslash( $_POST['bundle_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    
    if ( ! $bundle_id ) {
        wp_send_json_error( __( 'Invalid bundle ID', 'mix-match-bundle' ) );
    }
    
    $bundle_manager = new MMB_Bundle_Manager();
    if ( $bundle_manager->delete_bundle( $bundle_id ) ) {
        wp_send_json_success( __( 'Bundle deleted', 'mix-match-bundle' ) );
    } else {
        wp_send_json_error( __( 'Failed to delete bundle', 'mix-match-bundle' ) );
    }
}

function mmb_update_bundle_items() {
    check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );
    
    // Debug logging
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( wp_unslash( $_POST['bundle_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $product_ids_raw = isset( $_POST['product_ids'] ) ? sanitize_textarea_field( wp_unslash( $_POST['product_ids'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    
    // Parse product_ids if it's JSON
    if ( is_string( $product_ids_raw ) ) {
        // Remove escaping that URLSearchParams adds
        $product_ids_clean = wp_unslash( $product_ids_raw );
        $product_ids = json_decode( $product_ids_clean, true );
    } else {
        $product_ids = (array) $product_ids_raw;
    }
    
    
    if ( json_last_error() !== JSON_ERROR_NONE ) {
    }
    
    if ( ! $bundle_id || empty( $product_ids ) ) {
        wp_send_json_error( __( 'Invalid data', 'mix-match-bundle' ) );
    }
    
    $bundle_manager = new MMB_Bundle_Manager();
    $bundle = $bundle_manager->get_bundle( $bundle_id );
    
    if ( ! $bundle ) {
        wp_send_json_error( __( 'Bundle not found', 'mix-match-bundle' ) );
    }
    
    // Calculate discount
    $tier = $bundle_manager->get_applicable_tier( $bundle, count( $product_ids ) );
    
    // Calculate prices
    $products_data = [];
    $total_price = 0;
    
    foreach ( $product_ids as $item ) {
        // Handle both old format (just IDs) and new format (objects with product_id and variation_id)
        if ( is_array( $item ) || is_object( $item ) ) {
            $item = (array) $item;
            $product_id = isset( $item['product_id'] ) ? intval( $item['product_id'] ) : 0;
            $variation_id = isset( $item['variation_id'] ) ? intval( $item['variation_id'] ) : 0;
        } else {
            $product_id = intval( $item );
            $variation_id = 0;
        }
        
        // Get the actual product (variation or simple)
        $actual_product_id = $variation_id ? $variation_id : $product_id;
        $product = wc_get_product( $actual_product_id );
        
        if ( $product ) {
            $price = (float) $product->get_price();
            $total_price += $price;
            $products_data[] = [
                'id' => $product_id,
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'name' => $product->get_name(),
                'price' => $price,
            ];
        }
    }
    
    $discount_amount = ( $total_price * $tier['discount'] ) / 100;
    $final_price = $total_price - $discount_amount;
    
    $response_data = [
        'products' => $products_data,
        'subtotal' => $total_price,
        'total_price' => $final_price,
        'discount_percentage' => $tier['discount'],
        'discount_amount' => $discount_amount,
        'item_count' => count( $product_ids ),
    ];
    
    // Debug logging
    
    wp_send_json_success( $response_data );
}

function mmb_search_products() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }
    
    $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 100,
        'orderby' => 'title',
        'order' => 'ASC',
    ];
    
    if ( ! empty( $search ) ) {
        $args['s'] = $search;
    }
    
    $products = get_posts( $args );
    $formatted = [];
    
    foreach ( $products as $product_post ) {
        $product = wc_get_product( $product_post->ID );
        if ( $product ) {
            $formatted[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => wc_price( $product->get_price() ),
            ];
        }
    }
    
    wp_send_json_success( $formatted );
}

/**
 * Ajax handler to fetch specific products by ID.
 *
 * This is used by the admin UI to ensure previously selected products are
 * always available in the local cache, even if they do not match the current
 * search query.
 *
 * @since 1.0.0
 * @return void
 */
function mmb_get_products_by_ids() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }

    $product_ids = [];
    $raw_ids_array = filter_input( INPUT_POST, 'product_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

    if ( null !== $raw_ids_array && false !== $raw_ids_array ) {
        $raw_ids_array = wp_unslash( $raw_ids_array );
        $raw_ids_array = array_map( 'sanitize_text_field', $raw_ids_array );
        $product_ids = array_map( 'absint', $raw_ids_array );
    } else {
        $raw_ids_string = filter_input( INPUT_POST, 'product_ids', FILTER_UNSAFE_RAW ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( null !== $raw_ids_string && '' !== $raw_ids_string ) {
            $raw_ids_string = sanitize_textarea_field( wp_unslash( $raw_ids_string ) );
            $decoded = json_decode( $raw_ids_string, true );
            if ( is_array( $decoded ) ) {
                $product_ids = array_map( 'absint', $decoded );
            } else {
                $parts = array_filter( array_map( 'trim', explode( ',', $raw_ids_string ) ) );
                $product_ids = array_map( 'absint', $parts );
            }
        }
    }

    $product_ids = array_values( array_unique( array_filter( $product_ids ) ) );

    if ( empty( $product_ids ) ) {
        wp_send_json_success( [] );
    }

    $args = [
        'post_type' => [ 'product', 'product_variation' ],
        'post__in' => $product_ids,
        'posts_per_page' => count( $product_ids ),
        'orderby' => 'post__in',
    ];

    $products = get_posts( $args );
    $formatted = [];

    foreach ( $products as $product_post ) {
        $product = wc_get_product( $product_post->ID );
        if ( ! $product ) {
            continue;
        }

        $formatted[] = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => wc_price( $product->get_price() ),
        ];
    }

    wp_send_json_success( $formatted );
}

function mmb_add_bundle_to_cart() {
    // Verify nonce for security
    check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );
    
    // Ensure session is available for all users (including logged-out)
    if ( ! WC()->session ) {
        if ( ! class_exists( 'WC_Session_Handler' ) ) {
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        }
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        
        // Set session cookie for guest users
        if ( ! headers_sent() && ! is_user_logged_in() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( wp_unslash( $_POST['bundle_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $bundle_items_json = isset( $_POST['bundle_items'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bundle_items'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $discount_amount = isset( $_POST['discount_amount'] ) ? floatval( wp_unslash( $_POST['discount_amount'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    
    // Parse bundle items
    $bundle_items = json_decode( $bundle_items_json, true );
    
    if ( ! $bundle_id || empty( $bundle_items ) ) {
        wp_send_json_error( __( 'Invalid data', 'mix-match-bundle' ) );
    }
    
    // Clear any previous bundle session to prevent mixing old and new bundles
    if ( WC()->session ) {
        $old_session = WC()->session->get( 'mmb_bundle_discount' );
        if ( $old_session ) {
            // Remove old coupon if it exists
            if ( isset( $old_session['coupon_code'] ) && ! empty( $old_session['coupon_code'] ) && WC()->cart ) {
                $old_coupon_code = $old_session['coupon_code'];
                if ( WC()->cart->has_discount( $old_coupon_code ) ) {
                    WC()->cart->remove_coupon( $old_coupon_code );
                }
            }
            WC()->session->set( 'mmb_bundle_discount', null );
        }
    }
    
    // Extract product IDs for discount tracking
    $product_ids = [];
    $variation_ids = [];
    foreach ( $bundle_items as $item ) {
        $product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : ( isset( $item['id'] ) ? absint( $item['id'] ) : 0 );
        $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
        
        if ( $product_id ) {
            $product_ids[] = $product_id;
            if ( $variation_id ) {
                $variation_ids[] = $variation_id;
            }
        }
    }
    
    // Store NEW bundle info in session
    if ( WC()->session ) {
        WC()->session->set( 'mmb_bundle_discount', [
            'bundle_id' => $bundle_id,
            'discount_amount' => $discount_amount,
            'product_ids' => $product_ids,
            'coupon_code' => null, // Will be set after coupon creation
        ] );
        
        // Send success response immediately - don't wait for coupon creation
        wp_send_json_success( [
            'success' => true,
            'message' => 'Bundle items stored in session',
            'data' => [
                'bundle_id' => $bundle_id,
                'product_ids' => $product_ids,
                'discount_amount' => $discount_amount,
            ]
        ] );
    }
    
    if ( empty( $product_ids ) ) {
        // Send proper response structure for empty product list
        wp_send_json_success( [
            'success' => false,
            'message' => 'No products available to add to bundle',
            'data' => [
                'bundle_id' => 0,
                'product_ids' => [],
                'product_options' => [],
                'total_price' => 0,
                'subtotal' => 0,
                'discount_amount' => 0
            ]
        ] );
        
        // Debug logging
        mmb_debug_log( 'MMB: Empty product list handled properly' );
        mmb_debug_log( 'MMB: Response structure: ' . json_encode( [
                'success' => false,
                'message' => 'No products available to add to bundle',
                'data' => [
                    'bundle_id' => 0,
                    'product_ids' => [],
                    'product_options' => [],
                    'total_price' => 0,
                    'subtotal' => 0,
                    'discount_amount' => 0,
                ]
            ] ) );
    }
    
    wp_send_json_success( __( 'Bundle added to session', 'mix-match-bundle' ) );
}

function mmb_wc_ajax_add_to_cart() {
    try {
        // Ensure session is available for all users (including logged-out)
        if ( ! WC()->session ) {
            if ( ! class_exists( 'WC_Session_Handler' ) ) {
                include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
            }
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
            
            // Set session cookie for guest users
            if ( ! headers_sent() && ! is_user_logged_in() ) {
                WC()->session->set_customer_session_cookie( true );
            }
        }
        
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( __( 'WooCommerce not available', 'mix-match-bundle' ) );
            return;
        }
        
        // Check if we're adding multiple products (bundle) or single product
        $products_json = isset( $_POST['products'] ) ? sanitize_textarea_field( wp_unslash( $_POST['products'] ) ) : '';
    
        if ( ! empty( $products_json ) ) {
            // Handle multiple products
            $products = json_decode( $products_json, true );
            
            if ( empty( $products ) || ! is_array( $products ) ) {
                wp_send_json_error( __( 'Invalid products data', 'mix-match-bundle' ) );
            }
            
            // Verify nonce for security - only for bundle requests
            check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );
            
            // Get discount amount from POST parameter first (most reliable), then fallback to session
            $discount_amount = isset( $_POST['discount_amount'] ) ? floatval( wp_unslash( $_POST['discount_amount'] ) ) : 0;
            
            $session_data = WC()->session ? WC()->session->get( 'mmb_bundle_discount' ) : null;
            $bundle_id    = $session_data && isset( $session_data['bundle_id'] ) ? $session_data['bundle_id'] : 0;
            
            // Use session discount if POST parameter is 0
            if ( $discount_amount <= 0 && $session_data && isset( $session_data['discount_amount'] ) ) {
                $discount_amount = floatval( $session_data['discount_amount'] );
            }

            $added_items = [];
            $failed_items = [];

            foreach ( $products as $item ) {
                $product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : ( isset( $item['id'] ) ? absint( $item['id'] ) : 0 );
                $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
                $quantity     = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

                if ( $product_id < 1 ) {
                    $failed_items[] = __( 'Invalid product ID', 'mix-match-bundle' );
                    continue;
                }

                $check_id = $variation_id ? $variation_id : $product_id;
                $product  = wc_get_product( $check_id );

                if ( ! $product || ! $product->is_purchasable() ) {
                    $failed_items[] = sprintf(
                        /* translators: %s: Product or variation identifier (e.g., "Product 123" or "Variation 456") */
                        __( 'Product cannot be purchased: %s', 'mix-match-bundle' ),
                        ( $variation_id ? "Variation $variation_id" : "Product $product_id" )
                    );
                    continue;
                }

                // Mark item as bundle item - coupon will handle the discount
                $cart_item_data = [
                    'mmb_bundle_item' => true,
                    'mmb_bundle_id'   => $bundle_id,
                ];

                if ( $variation_id ) {
                    $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, [], $cart_item_data );
                } else {
                    $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );
                }

                if ( $cart_item_key ) {
                    $added_items[] = $cart_item_key;
                } else {
                    $failed_items[] = sprintf(
                        /* translators: %d: Product ID */
                        __( 'Failed to add product %d', 'mix-match-bundle' ),
                        $product_id
                    );
                }
            }

            if ( empty( $added_items ) ) {
                wp_send_json_error( [
                    'message' => __( 'Failed to add any products to cart', 'mix-match-bundle' ),
                    'errors' => $failed_items,
                ] );
            }

            // Apply bundle coupon if we have bundle data
            try {
                if ( $session_data && isset( $session_data['bundle_id'] ) && isset( $session_data['discount_amount'] ) ) {
                    $bundle_id = intval( $session_data['bundle_id'] );
                    $discount_amount = floatval( $session_data['discount_amount'] );
                    
                    if ( $bundle_id > 0 && $discount_amount > 0 ) {
                        // Calculate bundle subtotal
                        $bundle_subtotal = 0;
                        foreach ( WC()->cart->get_cart() as $item ) {
                            if ( ! empty( $item['mmb_bundle_item'] ) ) {
                                $bundle_subtotal += $item['data']->get_price() * $item['quantity'];
                            }
                        }
                        
                        if ( $bundle_subtotal > 0 ) {
                            // Get product IDs from session for coupon restriction
                            $bundle_product_ids = isset( $session_data['product_ids'] ) ? $session_data['product_ids'] : [];
                            
                            // Get cart instance to create and apply coupon
                            if ( ! class_exists( 'MMB_Cart' ) ) {
                                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                    mmb_debug_log( 'MMB: MMB_Cart class not found' );
                                }
                            } else {
                                $cart_handler = MMB_Cart::get_instance();
                                
                                if ( method_exists( $cart_handler, 'create_bundle_coupon' ) ) {
                                    $coupon_code = $cart_handler->create_bundle_coupon( $bundle_id, $discount_amount, $bundle_subtotal, $bundle_product_ids );
                                    
                                    // Only proceed if coupon was created successfully
                                    if ( ! empty( $coupon_code ) ) {
                                        // Verify coupon exists before applying
                                        $coupon_id = wc_get_coupon_id_by_code( $coupon_code );
                                        
                                        if ( $coupon_id ) {
                                            // Apply coupon if not already applied
                                            if ( ! WC()->cart->has_discount( $coupon_code ) ) {
                                                WC()->cart->apply_coupon( $coupon_code );
                                                
                                                // Remove any error notices about our bundle coupon
                                                if ( method_exists( $cart_handler, 'remove_bundle_coupon_notices' ) ) {
                                                    $cart_handler->remove_bundle_coupon_notices();
                                                }
                                                
                                                // Update session with coupon code
                                                $session_data = WC()->session ? WC()->session->get( 'mmb_bundle_discount' ) : null;
                                                if ( $session_data ) {
                                                    $session_data['coupon_code'] = $coupon_code;
                                                    WC()->session->set( 'mmb_bundle_discount', $session_data );
                                                }
                                                
                                                // Recalculate totals immediately after coupon application
                                                WC()->cart->calculate_totals();
                                                
                                                // Debug logging
                                                mmb_debug_log( 'MMB: Coupon Applied - Code: ' . $coupon_code );
                                                mmb_debug_log( 'MMB: Coupon Amount: ' . $discount_amount );
                                                mmb_debug_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } catch ( Exception $e ) {
                // Log error for debugging
                mmb_debug_log( 'MMB Coupon Error: ' . $e->getMessage(), 'error' );
            }

            // Final calculation to ensure all totals are correct (including coupon discount)
            WC()->cart->calculate_totals();

            // Use WooCommerce's native fragment refresh to ensure compatibility
            // Fragments will now use the discounted prices we just set
            $fragments = WC_AJAX::get_refreshed_fragments();

            do_action( 'woocommerce_ajax_added_to_cart', $product_id );

            // Return fragments in the format WooCommerce expects
            wp_send_json( [
                'fragments' => $fragments,
                'cart_hash' => WC()->cart->get_cart_hash(),
            ] );
        }
        
        // Handle single product (legacy support) - only when no products parameter
        if ( empty( $_POST['products'] ) ) {
            // Verify nonce for security
            check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );
            
            $product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $quantity     = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            
            if ( $product_id < 1 ) {
                wp_send_json_error( __( 'Invalid product ID', 'mix-match-bundle' ) );
            }
            
            $check_id = $variation_id ? $variation_id : $product_id;
            $product  = wc_get_product( $check_id );
            
            if ( ! $product || ! $product->is_purchasable() ) {
                wp_send_json_error(
                    sprintf(
                        /* translators: %s: product or variation ID */
                        __( 'Product cannot be purchased: %s', 'mix-match-bundle' ),
                        ( $variation_id ? "Variation $variation_id" : "Product $product_id" )
                    )
                );
            }

            $session_data = WC()->session ? WC()->session->get( 'mmb_bundle_discount' ) : null;
            $bundle_id    = $session_data && isset( $session_data['bundle_id'] ) ? $session_data['bundle_id'] : 0;
            
            $cart_item_data = [
                'mmb_bundle_item' => true,
                'mmb_bundle_id'   => $bundle_id,
            ];
            
            if ( $variation_id ) {
                $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, [], $cart_item_data );
            } else {
                $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );
            }
            
            if ( $cart_item_key ) {
                WC()->cart->calculate_fees();
                WC()->cart->calculate_totals();
                do_action( 'woocommerce_ajax_added_to_cart', $product_id );

                if ( class_exists( 'WC_AJAX' ) ) {
                    WC_AJAX::get_refreshed_fragments();
                } else {
                    wp_send_json_success(
                        [
                            'fragments' => [],
                            'cart_hash' => WC()->cart->get_cart_hash(),
                        ]
                    );
                }
            } else {
                wp_send_json_error( __( 'Failed to add product to cart', 'mix-match-bundle' ) );
            }
        }
    } catch ( Exception $e ) {
        // Log full error for debugging
        mmb_debug_log( 'MMB AJAX Error: ' . $e->getMessage(), 'error' );
        mmb_debug_log( 'MMB AJAX Stack Trace: ' . $e->getTraceAsString(), 'error' );
        
        // Send error response to frontend
        wp_send_json_error( [
            'message' => __( 'An error occurred while adding products to cart', 'mix-match-bundle' ),
            'error' => defined( 'WP_DEBUG' ) && WP_DEBUG ? $e->getMessage() : '',
        ] );
    } catch ( Error $e ) {
        // Catch fatal errors (PHP 7+)
        mmb_debug_log( 'MMB AJAX Fatal Error: ' . $e->getMessage(), 'error' );
        mmb_debug_log( 'MMB AJAX Stack Trace: ' . $e->getTraceAsString(), 'error' );
        
        // Send error response to frontend
        wp_send_json_error( [
            'message' => __( 'An error occurred while adding products to cart', 'mix-match-bundle' ),
            'error' => defined( 'WP_DEBUG' ) && WP_DEBUG ? $e->getMessage() : '',
        ] );
    }
}

function mmb_debug_coupon_state() {
    // Verify nonce for security
    check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );
    
    // Call debug function
    if ( class_exists( 'MMB_Cart' ) ) {
        $cart_handler = MMB_Cart::get_instance();
        if ( method_exists( $cart_handler, 'debug_coupon_state' ) ) {
            wp_send_json_success( $cart_handler->debug_coupon_state() );
        } else {
            wp_send_json_error( [ 'error' => 'Debug function not available' ] );
        }
    } else {
        wp_send_json_error( [ 'error' => 'MMB_Cart class not available' ] );
    }
}

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 */
function mmb_init_plugin() {
    // Check if WooCommerce is active
    if ( mmb_check_woocommerce_dependency() ) {
        // Initialize plugin
        Mix_Match_Bundle::get_instance();
    }
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action( 'plugins_loaded', 'mmb_init_plugin', 20 );

// Schedule daily cleanup of unused bundle coupons
add_action( 'wp', 'mmb_schedule_coupon_cleanup' );

function mmb_schedule_coupon_cleanup() {
    if ( ! wp_next_scheduled( 'mmb_daily_coupon_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'mmb_daily_coupon_cleanup' );
    }
}

// Hook the cleanup function to the scheduled event
add_action( 'mmb_daily_coupon_cleanup', 'mmb_cleanup_unused_coupons' );

/**
 * Clean up unused bundle coupons older than 24 hours
 * 
 * This function removes bundle coupons that:
 * - Were created more than 24 hours ago
 * - Have not been used (usage_count = 0)
 * - Were created by this plugin (code starts with 'mmb_bundle_')
 */
function mmb_cleanup_unused_coupons() {
    /** @var wpdb $wpdb WordPress database abstraction object. */
    global $wpdb;
    
    // Check if we have cached results
    $cache_key = 'mmb_bundle_coupons_cleanup';
    $cache_group = 'mix_match_bundle';
    $cached = wp_cache_get( $cache_key, $cache_group );
    
    if ( false !== $cached ) {
        return $cached;
    }
    
    // Use direct SQL query for better performance instead of meta_query
    // 
    // WordPress APIs like get_posts() could be used here, but a direct query is preferred because:
    // 1. We need to join posts and postmeta tables which is more efficient with direct SQL
    // 2. We need to filter by meta_value with LIKE which is slow with meta_query
    // 3. We're processing potentially many coupons, so direct query with proper caching is more performant
    // 4. The query is properly secured with $wpdb->prepare() to prevent SQL injection
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- performance optimization, caching handled externally.
    $coupon_posts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.ID 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'shop_coupon' 
            AND p.post_status = 'publish' 
            AND pm.meta_key = 'coupon_code' 
            AND pm.meta_value LIKE %s",
            'mmb_bundle_%'
        )
    );
    
    $deleted_count = 0;
    $kept_count = 0;
    
    foreach ( $coupon_posts as $coupon_post ) {
        $coupon = new WC_Coupon( $coupon_post->ID );
        
        // Check if coupon code starts with 'mmb_bundle_'
        if ( strpos( $coupon->get_code(), 'mmb_bundle_' ) !== 0 ) {
            continue;
        }
        
        // Get coupon creation date
        $created_date = get_post_time( 'U', true, $coupon_post->ID );
        $current_time = current_time( 'timestamp' );
        $age_in_hours = ( $current_time - $created_date ) / HOUR_IN_SECONDS;
        
        // Check if coupon is older than 24 hours
        if ( $age_in_hours < 24 ) {
            continue;
        }
        
        // Check if coupon has been used
        $usage_count = $coupon->get_usage_count();
        
        if ( $usage_count > 0 ) {
            // Coupon has been used, keep it
            $kept_count++;
            
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                mmb_debug_log( sprintf(
                    'MMB Cleanup: Keeping used coupon %s (used %d times)',
                    $coupon->get_code(),
                    $usage_count
                ) );
            }
        } else {
            // Coupon has not been used, delete it
            wp_delete_post( $coupon_post->ID, true );
            $deleted_count++;
            
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                mmb_debug_log( sprintf(
                    'MMB Cleanup: Deleted unused coupon %s (age: %.1f hours)',
                    $coupon->get_code(),
                    $age_in_hours
                ) );
            }
        }
    }
    
    // Cache the result for 1 hour
    $result = [
        'deleted' => $deleted_count,
        'kept' => $kept_count,
    ];
    wp_cache_set( $cache_key, $result, $cache_group, HOUR_IN_SECONDS );
    
    // Log summary
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        mmb_debug_log( sprintf(
            'MMB Cleanup: Completed. Deleted: %d, Kept: %d',
            $deleted_count,
            $kept_count
        ) );
    }
    
    return $result;
}

// Add manual cleanup action for testing/debugging
add_action( 'admin_init', 'mmb_manual_coupon_cleanup' );

function mmb_manual_coupon_cleanup() {
    // Check if manual cleanup is requested
    if ( isset( $_GET['mmb_cleanup_coupons'] ) && current_user_can( 'manage_woocommerce' ) ) {
        check_admin_referer( 'mmb_cleanup_coupons' );
        
        $result = mmb_cleanup_unused_coupons();
        
        // Add admin notice
        add_action( 'admin_notices', function() use ( $result ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: 1: Number of deleted coupons, 2: Number of kept coupons */
                    esc_html__( 'Bundle coupon cleanup completed. Deleted: %1$d, Kept: %2$d', 'mix-match-bundle' ),
                    absint( $result['deleted'] ),
                    absint( $result['kept'] )
                )
            );
        } );
    }
}

// Clear scheduled event on plugin deactivation
register_deactivation_hook( __FILE__, 'mmb_deactivate_cleanup_schedule' );

function mmb_deactivate_cleanup_schedule() {
    $timestamp = wp_next_scheduled( 'mmb_daily_coupon_cleanup' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'mmb_daily_coupon_cleanup' );
    }
}

// Hook into checkout page to ensure coupon is applied
add_action( 'woocommerce_before_checkout_process', 'mmb_ensure_coupon_applied', 10 );

function mmb_ensure_coupon_applied() {
    // Ensure session is available for all users (including logged-out)
    if ( ! WC()->session ) {
        if ( ! class_exists( 'WC_Session_Handler' ) ) {
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        }
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        
        // Set session cookie for guest users
        if ( ! headers_sent() && ! is_user_logged_in() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
    
    if ( ! WC()->cart ) {
        return;
    }
    
    // Get bundle data from session
    $session_data = WC()->session->get( 'mmb_bundle_discount' );
    if ( ! $session_data ) {
        return;
    }
    
    $bundle_id = isset( $session_data['bundle_id'] ) ? $session_data['bundle_id'] : 0;
    $discount_amount = isset( $session_data['discount_amount'] ) ? $session_data['discount_amount'] : 0;
    $coupon_code = isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null;
    
    // Check if we have bundle data and coupon code but coupon is not applied
    if ( $bundle_id > 0 && $discount_amount > 0 && ! empty( $coupon_code ) && ! WC()->cart->has_discount( $coupon_code ) ) {
        // Apply coupon if not already applied
        WC()->cart->apply_coupon( $coupon_code );
        
        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            mmb_debug_log( 'MMB: Coupon Applied on Checkout - Code: ' . $coupon_code );
            mmb_debug_log( 'MMB: Coupon Amount: ' . $discount_amount );
            mmb_debug_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

// Hook into cart page to ensure coupon is applied
add_action( 'woocommerce_before_cart', 'mmb_ensure_coupon_applied_on_cart', 10 );

function mmb_ensure_coupon_applied_on_cart() {
    // Ensure session is available for all users (including logged-out)
    if ( ! WC()->session ) {
        if ( ! class_exists( 'WC_Session_Handler' ) ) {
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        }
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        
        // Set session cookie for guest users
        if ( ! headers_sent() && ! is_user_logged_in() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
    
    if ( ! WC()->cart ) {
        return;
    }
    
    // Get bundle data from session
    $session_data = WC()->session->get( 'mmb_bundle_discount' );
    if ( ! $session_data ) {
        return;
    }
    
    $bundle_id = isset( $session_data['bundle_id'] ) ? $session_data['bundle_id'] : 0;
    $discount_amount = isset( $session_data['discount_amount'] ) ? $session_data['discount_amount'] : 0;
    $coupon_code = isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null;
    
    // Check if we have bundle data and coupon code but coupon is not applied
    if ( $bundle_id > 0 && $discount_amount > 0 && ! empty( $coupon_code ) && ! WC()->cart->has_discount( $coupon_code ) ) {
        // Apply coupon if not already applied
        WC()->cart->apply_coupon( $coupon_code );
        
        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            mmb_debug_log( 'MMB: Coupon Applied on Cart - Code: ' . $coupon_code );
            mmb_debug_log( 'MMB: Coupon Amount: ' . $discount_amount );
            mmb_debug_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

// Hook into mini-cart to ensure coupon is applied
add_action( 'woocommerce_before_mini_cart_contents', 'mmb_ensure_coupon_applied_on_mini_cart', 10 );

function mmb_ensure_coupon_applied_on_mini_cart() {
    // Ensure session is available for all users (including logged-out)
    if ( ! WC()->session ) {
        if ( ! class_exists( 'WC_Session_Handler' ) ) {
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        }
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        
        // Set session cookie for guest users
        if ( ! headers_sent() && ! is_user_logged_in() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
    
    if ( ! WC()->cart ) {
        return;
    }
    
    // Get bundle data from session
    $session_data = WC()->session->get( 'mmb_bundle_discount' );
    if ( ! $session_data ) {
        return;
    }
    
    $bundle_id = isset( $session_data['bundle_id'] ) ? $session_data['bundle_id'] : 0;
    $discount_amount = isset( $session_data['discount_amount'] ) ? $session_data['discount_amount'] : 0;
    $coupon_code = isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null;
    
    // Check if we have bundle data and coupon code but coupon is not applied
    if ( $bundle_id > 0 && $discount_amount > 0 && ! empty( $coupon_code ) && ! WC()->cart->has_discount( $coupon_code ) ) {
        // Apply coupon if not already applied
        WC()->cart->apply_coupon( $coupon_code );
        
        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            mmb_debug_log( 'MMB: Coupon Applied on Mini-Cart - Code: ' . $coupon_code );
            mmb_debug_log( 'MMB: Coupon Amount: ' . $discount_amount );
            mmb_debug_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

// Hook into cart refresh to ensure coupon is applied
add_action( 'woocommerce_cart_loaded_from_session', 'mmb_ensure_coupon_applied_on_cart_refresh', 10 );

function mmb_ensure_coupon_applied_on_cart_refresh() {
    // Ensure session is available for all users (including logged-out)
    if ( ! WC()->session ) {
        if ( ! class_exists( 'WC_Session_Handler' ) ) {
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        }
        WC()->session = new WC_Session_Handler();
        WC()->session->init();
        
        // Set session cookie for guest users
        if ( ! headers_sent() && ! is_user_logged_in() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
    
    if ( ! WC()->cart ) {
        return;
    }
    
    // Get bundle data from session
    $session_data = WC()->session->get( 'mmb_bundle_discount' );
    if ( ! $session_data ) {
        return;
    }
    
    $bundle_id = isset( $session_data['bundle_id'] ) ? $session_data['bundle_id'] : 0;
    $discount_amount = isset( $session_data['discount_amount'] ) ? $session_data['discount_amount'] : 0;
    $coupon_code = isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null;
    
    // Check if we have bundle data and coupon code but coupon is not applied
    if ( $bundle_id > 0 && $discount_amount > 0 && ! empty( $coupon_code ) && ! WC()->cart->has_discount( $coupon_code ) ) {
        // Apply coupon if not already applied
        WC()->cart->apply_coupon( $coupon_code );
        
        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            mmb_debug_log( 'MMB: Coupon Applied on Cart Refresh - Code: ' . $coupon_code );
            mmb_debug_log( 'MMB: Coupon Amount: ' . $discount_amount );
            mmb_debug_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

/**
 * Get analytics data for the dashboard
 *
 * @param string $date_range Date range preset
 * @param string $start_date Custom start date
 * @param string $end_date Custom end date
 * @return array Analytics data
 */
function mmb_get_analytics_data( $date_range = '30days', $start_date = '', $end_date = '' ) {
    global $wpdb;
    
    $current_time = current_time( 'timestamp', true );
    
    // Calculate date range based on preset or custom dates
    if ( $start_date && $end_date ) {
        // Custom date range
        $start_timestamp = strtotime( $start_date . ' 00:00:00' );
        $end_timestamp = strtotime( $end_date . ' 23:59:59' );
    } else {
        // Preset date ranges
        switch ( $date_range ) {
            case '7days':
                $end_timestamp = strtotime( 'today 23:59:59', $current_time );
                $start_timestamp = strtotime( '-7 days', $end_timestamp );
                break;
                
            case '30days':
                $end_timestamp = strtotime( 'today 23:59:59', $current_time );
                $start_timestamp = strtotime( '-30 days', $end_timestamp );
                break;
                
            case '90days':
                $end_timestamp = strtotime( 'today 23:59:59', $current_time );
                $start_timestamp = strtotime( '-90 days', $end_timestamp );
                break;
                
            case 'this_month':
                $start_timestamp = strtotime( 'first day of this month 00:00:00', $current_time );
                $end_timestamp = strtotime( 'last day of this month 23:59:59', $current_time );
                break;
                
            case 'last_month':
                $start_timestamp = strtotime( 'first day of last month 00:00:00', $current_time );
                $end_timestamp = strtotime( 'last day of last month 23:59:59', $current_time );
                break;
                
            case 'this_quarter':
                $current_month = gmdate( 'n', $current_time );
                $current_year  = gmdate( 'Y', $current_time );
                $quarter_start_month = floor( ( $current_month - 1 ) / 3 ) * 3 + 1;
                $start_timestamp = strtotime( "$current_year-$quarter_start_month-01 00:00:00" );
                $end_timestamp = strtotime( 'today 23:59:59', $current_time );
                break;
                
            case 'this_year':
                $start_timestamp = strtotime( 'first day of January ' . gmdate( 'Y', $current_time ) . ' 00:00:00' );
                $end_timestamp = strtotime( 'today 23:59:59', $current_time );
                break;
                
            default:
                // Default to last 30 days
                $end_timestamp = strtotime( 'today 23:59:59', $current_time );
                $start_timestamp = strtotime( '-30 days', $end_timestamp );
                break;
        }
    }
    
    // Format dates for SQL query
    $start_date_sql = gmdate( 'Y-m-d H:i:s', $start_timestamp );
    $end_date_sql   = gmdate( 'Y-m-d H:i:s', $end_timestamp );
    
    // Get all analytics data
    $coupon_analytics = mmb_get_coupon_analytics( $start_date_sql, $end_date_sql );
    $bundle_analytics = mmb_get_bundle_analytics( $start_date_sql, $end_date_sql );
    $purchase_analytics = mmb_get_purchase_analytics( $start_date_sql, $end_date_sql );
    $cart_analytics = mmb_get_cart_analytics( $start_date_sql, $end_date_sql );
    $checkout_analytics = mmb_get_checkout_analytics( $start_date_sql, $end_date_sql );
    $conversion_analytics = mmb_get_conversion_analytics( $start_date_sql, $end_date_sql );
    $bundle_performance = mmb_get_bundle_performance_analytics( $start_date_sql, $end_date_sql );
    
    return [
        'coupon_analytics' => $coupon_analytics,
        'bundle_analytics' => $bundle_analytics,
        'purchase_analytics' => $purchase_analytics,
        'cart_analytics' => $cart_analytics,
        'checkout_analytics' => $checkout_analytics,
        'conversion_analytics' => $conversion_analytics,
        'bundle_performance' => $bundle_performance,
        'date_range' => [
            'start' => $start_date ? $start_date : gmdate( 'Y-m-d', $start_timestamp ),
            'end'   => $end_date ? $end_date : gmdate( 'Y-m-d', $end_timestamp ),
            'label' => mmb_get_date_range_label( $date_range, $start_timestamp, $end_timestamp )
        ]
    ];
}

/**
 * Get coupon analytics data
 */
function mmb_get_coupon_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // Get coupon data - WooCommerce stores coupon code as post_title
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- complex join query for performance, no caching needed for analytics.
    $coupon_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                p.ID,
                p.post_title as coupon_code,
                p.post_date,
                pm_amount.meta_value as discount_amount,
                pm_usage.meta_value as usage_count,
                pm_limit.meta_value as usage_limit
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'coupon_amount'
            LEFT JOIN {$wpdb->postmeta} pm_usage ON p.ID = pm_usage.post_id AND pm_usage.meta_key = 'usage_count'
            LEFT JOIN {$wpdb->postmeta} pm_limit ON p.ID = pm_limit.post_id AND pm_limit.meta_key = 'usage_limit'
            WHERE p.post_type = 'shop_coupon'
            AND p.post_status = 'publish'
            AND p.post_title LIKE %s
            AND p.post_date BETWEEN %s AND %s
            ORDER BY p.post_date DESC",
            'mmb_bundle_%',
            $start_date,
            $end_date
        )
    );
    
    // Ensure we have an array
    if ( ! is_array( $coupon_data ) ) {
        $coupon_data = array();
    }
    
    $total_created = count( $coupon_data );
    $total_used = 0;
    $total_unused = 0;
    $total_discount = 0;
    
    foreach ( $coupon_data as $coupon ) {
        if ( ! is_object( $coupon ) ) {
            continue;
        }
        
        $usage_count = isset( $coupon->usage_count ) ? intval( $coupon->usage_count ) : 0;
        $discount_amount = isset( $coupon->discount_amount ) ? floatval( $coupon->discount_amount ) : 0;
        
        if ( $usage_count > 0 ) {
            $total_used++;
            $total_discount += $discount_amount * $usage_count;
        } else {
            $total_unused++;
        }
    }
    
    return array(
        'total_created' => $total_created,
        'total_used' => $total_used,
        'total_unused' => $total_unused,
        'total_discount' => $total_discount,
        'usage_rate' => $total_created > 0 ? round( ( $total_used / $total_created ) * 100, 2 ) : 0,
        'coupons' => $coupon_data
    );
}

/**
 * Get bundle analytics data
 */
function mmb_get_bundle_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // Get ALL bundles (not filtered by date - we want to show total bundles available)
    $table_name = mmb_get_table_name();
    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check, minimal impact.
    $table_exists = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
    );
    
    if ( ! $table_exists ) {
        return array(
            'total_bundles' => 0,
            'enabled_bundles' => 0,
            'bundles' => array()
        );
    }
    
    // Get all bundles
    $bundle_table = esc_sql( $table_name );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table query, caching handled by calling function.
    $bundle_data  = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name sanitized above.
        "SELECT * FROM {$bundle_table} ORDER BY created_at DESC"
    );
    
    // Ensure we have an array
    if ( ! is_array( $bundle_data ) ) {
        $bundle_data = array();
    }
    
    $total_bundles = count( $bundle_data );
    $enabled_bundles = 0;
    
    foreach ( $bundle_data as $bundle ) {
        if ( isset( $bundle->enabled ) && $bundle->enabled ) {
            $enabled_bundles++;
        }
    }
    
    return array(
        'total_bundles' => $total_bundles,
        'enabled_bundles' => $enabled_bundles,
        'bundles' => $bundle_data
    );
}

/**
 * Get purchase analytics data
 */
function mmb_get_purchase_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // First, get all orders in the date range
    // Include both shop_order and shop_order_placehold (for orders stuck in placeholder state)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- querying WooCommerce orders, no caching needed for analytics.
    $all_orders = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                o.ID,
                o.post_date,
                o.post_date_gmt,
                o.post_status,
                o.post_type
            FROM {$wpdb->posts} o
            WHERE o.post_type IN ( 'shop_order', 'shop_order_placehold' )
            AND o.post_status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'draft' )
            AND (o.post_date BETWEEN %s AND %s OR o.post_date_gmt BETWEEN %s AND %s)
            ORDER BY o.post_date DESC",
            $start_date,
            $end_date,
            $start_date,
            $end_date
        )
    );
    
    // Debug: Log the query
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        mmb_debug_log( sprintf( 
            'Purchase Analytics Query - Date Range: %s to %s, Found %d orders', 
            $start_date, 
            $end_date,
            count( $all_orders )
        ) );
    }
    
    $order_data = array();
    $debug_info = array();
    
    // Check each order for bundle coupons
    foreach ( $all_orders as $order ) {
        $order_id = $order->ID;
        
        // Get order object
        $wc_order = wc_get_order( $order_id );
        if ( ! $wc_order ) {
            continue;
        }
        
        // Check if order has bundle coupon
        $has_bundle_coupon = false;
        $used_coupons = $wc_order->get_coupon_codes();
        
        // Debug: Log all coupons for this order
        if ( ! empty( $used_coupons ) ) {
            $debug_info[] = sprintf( 
                'Order #%d has coupons: %s', 
                $order_id, 
                implode( ', ', $used_coupons ) 
            );
        }
        
        foreach ( $used_coupons as $coupon_code ) {
            if ( strpos( $coupon_code, 'mmb_bundle_' ) === 0 ) {
                $has_bundle_coupon = true;
                $debug_info[] = sprintf( 'Order #%d matched bundle coupon: %s', $order_id, $coupon_code );
                break;
            }
        }
        
        // If order has bundle coupon, add to results
        if ( $has_bundle_coupon ) {
            $order_data[] = (object) array(
                'ID' => $order_id,
                'post_date' => $order->post_date,
                'post_status' => $order->post_status,
                'coupon_code' => implode( ', ', $used_coupons ),
                'order_total' => $wc_order->get_total(),
                'cart_discount' => $wc_order->get_discount_total()
            );
        }
    }
    
    // Debug logging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $debug_info ) ) {
        foreach ( $debug_info as $debug_msg ) {
            mmb_debug_log( $debug_msg );
        }
    }
    
    $total_orders = count( $order_data );
    $total_revenue = 0;
    $total_discount = 0;
    
    foreach ( $order_data as $order ) {
        if ( ! is_object( $order ) ) {
            continue;
        }
        
        // Add order total to revenue
        $order_total = isset( $order->order_total ) ? floatval( $order->order_total ) : 0;
        $cart_discount = isset( $order->cart_discount ) ? floatval( $order->cart_discount ) : 0;
        
        $total_revenue += $order_total;
        $total_discount += $cart_discount;
    }
    
    // Debug logging
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        mmb_debug_log( sprintf( 
            'Purchase Analytics: Found %d orders with bundle coupons. Revenue: %s', 
            $total_orders, 
            $total_revenue 
        ) );
    }
    
    return array(
        'total_orders' => $total_orders,
        'total_revenue' => $total_revenue,
        'total_discount' => $total_discount,
        'orders' => $order_data
    );
}

/**
 * Get date range label for display
 */
function mmb_get_date_range_label( $date_range, $start_timestamp, $end_timestamp ) {
    switch ( $date_range ) {
        case '7days':
            return __( 'Last 7 Days', 'mix-match-bundle' );
        case '30days':
            return __( 'Last 30 Days', 'mix-match-bundle' );
        case '90days':
            return __( 'Last 90 Days', 'mix-match-bundle' );
        case 'this_month':
            return __( 'This Month', 'mix-match-bundle' );
        case 'last_month':
            return __( 'Last Month', 'mix-match-bundle' );
        case 'this_quarter':
            return __( 'This Quarter', 'mix-match-bundle' );
        case 'last_quarter':
            return __( 'Last Quarter', 'mix-match-bundle' );
        case 'this_year':
            return __( 'This Year', 'mix-match-bundle' );
        case 'last_year':
            return __( 'Last Year', 'mix-match-bundle' );
        case 'custom':
            return sprintf(
                    /* translators: 1: start date, 2: end date */
                    __( 'Custom: %1$s to %2$s', 'mix-match-bundle' ),
                    gmdate( 'M j, Y', $start_timestamp ),
                    gmdate( 'M j, Y', $end_timestamp )
                );
        default:
            return __( 'Custom Range', 'mix-match-bundle' );
    }
}

/**
 * Get cart analytics data
 */
function mmb_get_cart_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // Since WooCommerce sessions only contain active carts, we'll track cart analytics 
    // based on orders (completed carts) and coupon usage as a proxy for cart activity
    
    // Get total orders in date range
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics query, no caching needed.
    $total_orders = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT ID) 
            FROM {$wpdb->posts} 
            WHERE post_type IN ('shop_order', 'shop_order_placehold')
            AND post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'draft')
            AND post_date BETWEEN %s AND %s",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        )
    );
    
    // Get orders with bundle coupons (orders that used bundles)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics query, no caching needed.
    $orders_with_bundles = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT o.ID, o.post_date
            FROM {$wpdb->posts} o
            WHERE o.post_type IN ('shop_order', 'shop_order_placehold')
            AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'draft')
            AND o.post_date BETWEEN %s AND %s
            ORDER BY o.post_date DESC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        )
    );
    
    // Initialize counters with safe defaults
    $total_carts = intval( $total_orders );
    $carts_with_bundles = 0;
    $total_cart_value = 0;
    $bundle_cart_value = 0;
    
    // Check each order for bundle coupons
    if ( is_array( $orders_with_bundles ) ) {
        foreach ( $orders_with_bundles as $order_data ) {
            $order = wc_get_order( $order_data->ID );
            if ( ! $order ) {
                continue;
            }
            
            $coupons = $order->get_coupon_codes();
            $has_bundle = false;
            
            foreach ( $coupons as $coupon_code ) {
                if ( strpos( $coupon_code, 'mmb_bundle_' ) === 0 ) {
                    $has_bundle = true;
                    break;
                }
            }
            
            $order_total = floatval( $order->get_total() );
            $total_cart_value += $order_total;
            
            if ( $has_bundle ) {
                $carts_with_bundles++;
                $bundle_cart_value += $order_total;
            }
        }
    }
    
    // Also check current active sessions for more accurate current cart data
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- active session check, no preparation needed for simple query.
    $active_sessions = $wpdb->get_results(
        "SELECT session_key, session_value, session_expiry
        FROM {$wpdb->prefix}woocommerce_sessions
        WHERE session_expiry > UNIX_TIMESTAMP()
        ORDER BY session_expiry DESC
        LIMIT 100"
    );
    
    $active_carts = 0;
    $active_bundle_carts = 0;
    
    if ( is_array( $active_sessions ) ) {
        foreach ( $active_sessions as $session ) {
            if ( ! is_object( $session ) || empty( $session->session_value ) ) {
                continue;
            }
            
            $session_value = maybe_unserialize( $session->session_value );
            
            if ( ! is_array( $session_value ) || ! isset( $session_value['cart'] ) ) {
                continue;
            }
            
            $cart_contents = maybe_unserialize( $session_value['cart'] );
            
            if ( ! empty( $cart_contents ) && is_array( $cart_contents ) ) {
                $active_carts++;
                
                // Check if any cart item has bundle metadata
                foreach ( $cart_contents as $cart_item ) {
                    if ( is_array( $cart_item ) && isset( $cart_item['mmb_bundle_id'] ) ) {
                        $active_bundle_carts++;
                        break;
                    }
                }
            }
        }
    }
    
    // Combine historical orders and active carts
    $total_carts = max( $total_carts, $active_carts );
    $carts_with_bundles = max( $carts_with_bundles, $active_bundle_carts );
    
    return array(
        'total_carts' => $total_carts,
        'carts_with_bundles' => $carts_with_bundles,
        'total_cart_value' => $total_cart_value,
        'bundle_cart_value' => $bundle_cart_value,
        'carts' => array() // Placeholder for detailed cart data if needed
    );
}

/**
 * Get checkout analytics data
 */
function mmb_get_checkout_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // Get completed orders with bundle coupons
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- complex join for checkout analytics, no caching needed.
    $order_data = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                    o.ID,
                    o.post_date,
                    o.post_status,
                    pm2.meta_value as order_total,
                    pm.meta_value as coupon_code,
                    pm3.meta_value as discount_amount,
                    pm4.meta_value as customer_email
                FROM {$wpdb->posts} o
                LEFT JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = '_used_coupons'
                LEFT JOIN {$wpdb->postmeta} pm2 ON o.ID = pm2.post_id AND pm2.meta_key = '_order_total'
                LEFT JOIN {$wpdb->postmeta} pm3 ON o.ID = pm3.post_id AND pm3.meta_key = '_cart_discount'
                LEFT JOIN {$wpdb->postmeta} pm4 ON o.ID = pm4.post_id AND pm4.meta_key = '_billing_email'
                WHERE o.post_type = 'shop_order'
                AND o.post_status IN ( 'wc-completed', 'wc-processing' )
                AND pm.meta_value LIKE %s
                AND o.post_date BETWEEN %s AND %s
                ORDER BY o.post_date DESC",
            '%mmb_bundle_%',
            $start_date,
            $end_date
        )
    );
    
    // Ensure $order_data is an array before counting
    if ( ! is_array( $order_data ) ) {
        $order_data = array();
    }
    
    $total_orders = count( $order_data );
    $completed_orders = 0;
    $total_revenue = 0;
    $total_discount = 0;
    $new_customers = 0;
    $returning_customers = 0;
    
    foreach ( $order_data as $order ) {
        // Skip invalid order data
        if ( ! is_object( $order ) ) {
            continue;
        }
        
        $order_total = isset( $order->order_total ) ? floatval( $order->order_total ) : 0;
        $discount_amount = isset( $order->discount_amount ) ? floatval( $order->discount_amount ) : 0;
        
        if ( isset( $order->post_status ) && $order->post_status === 'wc-completed' ) {
            $completed_orders++;
            $total_revenue += $order_total;
            $total_discount += $discount_amount;
        }
        
        // Check if this is customer's first order (if we have customer email)
        if ( isset( $order->customer_email ) && ! empty( $order->customer_email ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- customer order count check, not cached.
            $customer_orders = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) 
                    FROM {$wpdb->posts} o
                    LEFT JOIN {$wpdb->postmeta} pm ON o.ID = pm.post_id AND pm.meta_key = '_billing_email'
                    WHERE pm.meta_value = %s 
                    AND o.post_type = 'shop_order' 
                    AND o.post_status IN ( 'wc-completed', 'wc-processing' )",
                    $order->customer_email
                )
            );
            
            if ( $customer_orders == 1 ) {
                $new_customers++;
            } else {
                $returning_customers++;
            }
        }
    }
    
    return array(
        'total_orders' => $total_orders,
        'completed_orders' => $completed_orders,
        'total_revenue' => $total_revenue,
        'total_discount' => $total_discount,
        'new_customers' => $new_customers,
        'returning_customers' => $returning_customers,
        'orders' => $order_data
    );
}

/**
 * Get conversion rate metrics
 */
function mmb_get_conversion_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // Initialize default values
    $total_views = 0;
    $unique_visitors = 0;
    $page_views = array();
    
    // Check if statistics table exists (requires WP Statistics plugin or similar)
    $statistics_table = esc_sql( $wpdb->prefix . 'statistics_pages' );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check, minimal performance impact.
    $table_exists     = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $statistics_table
        )
    );
    
    if ( $table_exists ) {
        // Get page views for bundle pages
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- statistics table name sanitized via esc_sql() above.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics query, table name sanitized.
        $page_views = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as views,
                    DATE(date) as view_date
                FROM {$statistics_table}
                WHERE uri LIKE %s
                AND date BETWEEN %s AND %s
                GROUP BY DATE(date)
                ORDER BY view_date DESC",
                '%mmb_bundle%',
                $start_date,
                $end_date
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        // Ensure $page_views is an array
        if ( ! is_array( $page_views ) ) {
            $page_views = array();
        }
        
        // Get unique visitors
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- statistics table name sanitized via esc_sql() above.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- analytics query, table name sanitized.
        $unique_visitors = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT ip) 
                FROM {$statistics_table}
                WHERE uri LIKE %s
                AND date BETWEEN %s AND %s",
                '%mmb_bundle%',
                $start_date,
                $end_date
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        
        // Calculate total views
        foreach ( $page_views as $view ) {
            if ( is_object( $view ) && isset( $view->views ) ) {
                $total_views += intval( $view->views );
            }
        }
    }
    
    // Calculate conversion rate
    $conversion_rate = $total_views > 0 && $unique_visitors > 0 ? round( ( $unique_visitors / $total_views ) * 100, 2 ) : 0;
    
    return array(
        'total_views' => $total_views,
        'unique_visitors' => intval( $unique_visitors ),
        'conversion_rate' => $conversion_rate,
        'page_views' => $page_views,
        'note' => ! $table_exists ? __( 'Install WP Statistics plugin for detailed analytics', 'mix-match-bundle' ) : ''
    );
}

/**
 * Get bundle performance analytics
 */
function mmb_get_bundle_performance_analytics( $start_date, $end_date ) {
    global $wpdb;
    
    // Get bundle data from our custom table
    $table_name = mmb_get_table_name();
    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- table existence check, minimal performance impact.
    $table_exists = $wpdb->get_var(
        $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
    );
    
    if ( ! $table_exists ) {
        return array(
            'total_bundles' => 0,
            'used_bundles' => 0,
            'unused_bundles' => 0,
            'total_usage' => 0,
            'average_usage' => 0,
            'popular_bundles' => array()
        );
    }
    
    // Get ALL bundles (not filtered by creation date - show usage in selected date range)
    $bundle_table = esc_sql( $table_name );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table query, caching handled by calling function.
    $bundle_data  = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name sanitized above.
        "SELECT * FROM {$bundle_table} ORDER BY created_at DESC"
    );
    
    // Ensure $bundle_data is an array
    if ( ! is_array( $bundle_data ) ) {
        $bundle_data = array();
    }
    
    $total_bundles = count( $bundle_data );
    $used_bundles = 0;
    $total_usage = 0;
    $bundle_usage = array();
    
    // For each bundle, count how many times it was used in orders WITHIN THE DATE RANGE
    foreach ( $bundle_data as $bundle ) {
        if ( ! is_object( $bundle ) || ! isset( $bundle->id ) ) {
            continue;
        }
        
        // Count orders that used this bundle's coupon in the selected date range
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bundle usage count, analytics query.
        $usage_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT o.ID) 
                FROM {$wpdb->posts} o
                WHERE o.post_type IN ('shop_order', 'shop_order_placehold')
                AND o.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'draft')
                AND o.post_date BETWEEN %s AND %s
                AND o.ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_used_coupons'
                    AND meta_value LIKE %s
                )",
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59',
                '%mmb_bundle_' . $bundle->id . '%'
            )
        );
        
        // Alternative method using WC order objects for more accuracy
        if ( $usage_count === null || $usage_count === 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fallback query for bundle usage, analytics only.
            $orders_in_range = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                    WHERE post_type IN ('shop_order', 'shop_order_placehold')
                    AND post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending', 'draft')
                    AND post_date BETWEEN %s AND %s",
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                )
            );
            
            $usage_count = 0;
            if ( is_array( $orders_in_range ) ) {
                foreach ( $orders_in_range as $order_data ) {
                    $order = wc_get_order( $order_data->ID );
                    if ( ! $order ) {
                        continue;
                    }
                    
                    $coupons = $order->get_coupon_codes();
                    foreach ( $coupons as $coupon_code ) {
                        if ( strpos( $coupon_code, 'mmb_bundle_' . $bundle->id ) !== false ) {
                            $usage_count++;
                            break;
                        }
                    }
                }
            }
        }
        
        $usage_count = intval( $usage_count );
        $total_usage += $usage_count;
        
        if ( $usage_count > 0 ) {
            $used_bundles++;
        }
        
        // Store bundle usage data
        $bundle_usage[] = array(
            'id' => $bundle->id,
            'name' => isset( $bundle->name ) ? $bundle->name : __( 'Unnamed Bundle', 'mix-match-bundle' ),
            'usage_count' => $usage_count,
            'created_at' => isset( $bundle->created_at ) ? $bundle->created_at : ''
        );
    }
    
    // Sort bundles by usage count (descending)
    usort( $bundle_usage, function( $a, $b ) {
        return $b['usage_count'] - $a['usage_count'];
    });
    
    // Get top 10 popular bundles (increased from 5)
    $popular_bundles = array_slice( $bundle_usage, 0, 10 );
    
    return array(
        'total_bundles' => $total_bundles,
        'used_bundles' => $used_bundles,
        'unused_bundles' => $total_bundles - $used_bundles,
        'total_usage' => $total_usage,
        'average_usage' => $total_bundles > 0 ? round( $total_usage / $total_bundles, 2 ) : 0,
        'popular_bundles' => $popular_bundles
    );
}

