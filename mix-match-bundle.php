<?php
/**
 * Plugin Name: Mix & Match Bundle for WooCommerce
 * Plugin URI: https://demo.betatech.co/mix-match-bundle
 * Description: Create customizable bundle promotions with tiered discounts based on quantity
 * Version: 1.0.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Betatech
 * Author URI: https://betatech.co
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: mix-match-bundle
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MMB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MMB_VERSION', '1.0.1' );
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

    return $sanitized ?: $raw_name;
}

/**
 * Returns the SQL statement used for dbDelta to manage the bundles table.
 *
 * @since 2.1
 *
 * @return string
 */
function mmb_get_table_schema_sql() {
    global $wpdb;

    $table_name      = mmb_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

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
    ) {$charset_collate};";
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
    }
    
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include MMB_PLUGIN_DIR . 'admin/bundle-editor.php';
    }
    
    public function enqueue_admin_scripts( $hook ) {
        // Always load icon styles for the menu
        wp_enqueue_style( 'mix-match-admin-icon', MMB_PLUGIN_URL . 'assets/css/admin.css', [], MMB_VERSION );
        
        // Only load full admin scripts on plugin pages
        if ( strpos( $hook, 'mix-match' ) === false ) {
            return;
        }
        
        wp_enqueue_script( 'mix-match-admin', MMB_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'wp-api' ], MMB_VERSION, true );
        
        wp_localize_script( 'mix-match-admin', 'mmb_admin', [
            'nonce' => wp_create_nonce( 'mmb_admin_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ]);
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
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'MMB: Database upgrade successful' );
            }
        } catch ( Exception $e ) {
            // Log error for debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'MMB Database upgrade error: ' . $e->getMessage() );
            }
            
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
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'MMB: Plugin activation successful' );
            }
        } catch ( Exception $e ) {
            // Log error for debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'MMB Plugin activation error: ' . $e->getMessage() );
            }
            
            // Store error for admin notice
            update_option( 'mmb_activation_error', $e->getMessage() );
            
            // Re-throw to let WordPress handle the error
            throw $e;
        }
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
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'MMB: Empty product list handled properly' );
            error_log( 'MMB: Response structure: ' . json_encode( [
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
    }
    
    wp_send_json_success( __( 'Bundle added to session', 'mix-match-bundle' ) );
}

function mmb_wc_ajax_add_to_cart() {
    try {
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
                                    error_log( 'MMB: MMB_Cart class not found' );
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
                                                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                                    error_log( 'MMB: Coupon Applied - Code: ' . $coupon_code );
                                                    error_log( 'MMB: Coupon Amount: ' . $discount_amount );
                                                    error_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
                                                }
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
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'MMB Coupon Error: ' . $e->getMessage() );
                }
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
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'MMB AJAX Error: ' . $e->getMessage() );
            error_log( 'MMB AJAX Stack Trace: ' . $e->getTraceAsString() );
        }
        
        // Send error response to frontend
        wp_send_json_error( [
            'message' => __( 'An error occurred while adding products to cart', 'mix-match-bundle' ),
            'error' => defined( 'WP_DEBUG' ) && WP_DEBUG ? $e->getMessage() : '',
        ] );
    } catch ( Error $e ) {
        // Catch fatal errors (PHP 7+)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'MMB AJAX Fatal Error: ' . $e->getMessage() );
            error_log( 'MMB AJAX Stack Trace: ' . $e->getTraceAsString() );
        }
        
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
    
    // Get all coupons created by this plugin
    $coupon_posts = get_posts( [
        'post_type' => 'shop_coupon',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => 'coupon_code',
                'value' => 'mmb_bundle_',
                'compare' => 'LIKE',
            ],
        ],
    ] );
    
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
                error_log( sprintf(
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
                error_log( sprintf(
                    'MMB Cleanup: Deleted unused coupon %s (age: %.1f hours)',
                    $coupon->get_code(),
                    $age_in_hours
                ) );
            }
        }
    }
    
    // Log summary
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            'MMB Cleanup: Completed. Deleted: %d, Kept: %d',
            $deleted_count,
            $kept_count
        ) );
    }
    
    return [
        'deleted' => $deleted_count,
        'kept' => $kept_count,
    ];
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
                    esc_html__( 'Bundle coupon cleanup completed. Deleted: %d, Kept: %d', 'mix-match-bundle' ),
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
    if ( ! WC()->cart || ! WC()->session ) {
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
            error_log( 'MMB: Coupon Applied on Checkout - Code: ' . $coupon_code );
            error_log( 'MMB: Coupon Amount: ' . $discount_amount );
            error_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

// Hook into cart page to ensure coupon is applied
add_action( 'woocommerce_before_cart', 'mmb_ensure_coupon_applied_on_cart', 10 );

function mmb_ensure_coupon_applied_on_cart() {
    if ( ! WC()->cart || ! WC()->session ) {
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
            error_log( 'MMB: Coupon Applied on Cart - Code: ' . $coupon_code );
            error_log( 'MMB: Coupon Amount: ' . $discount_amount );
            error_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

// Hook into mini-cart to ensure coupon is applied
add_action( 'woocommerce_before_mini_cart_contents', 'mmb_ensure_coupon_applied_on_mini_cart', 10 );

function mmb_ensure_coupon_applied_on_mini_cart() {
    if ( ! WC()->cart || ! WC()->session ) {
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
            error_log( 'MMB: Coupon Applied on Mini-Cart - Code: ' . $coupon_code );
            error_log( 'MMB: Coupon Amount: ' . $discount_amount );
            error_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

// Hook into cart refresh to ensure coupon is applied
add_action( 'woocommerce_cart_loaded_from_session', 'mmb_ensure_coupon_applied_on_cart_refresh', 10 );

function mmb_ensure_coupon_applied_on_cart_refresh() {
    if ( ! WC()->cart || ! WC()->session ) {
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
            error_log( 'MMB: Coupon Applied on Cart Refresh - Code: ' . $coupon_code );
            error_log( 'MMB: Coupon Amount: ' . $discount_amount );
            error_log( 'MMB: Cart Totals After: ' . json_encode( WC()->cart->get_totals() ) );
        }
        
        // Recalculate totals immediately after coupon application
        WC()->cart->calculate_totals();
    }
}

