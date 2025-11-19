<?php
/**
 * Plugin Name: Mix & Match Bundle for WooCommerce
 * Plugin URI: https://demo.betatech.co/mix-match-bundle
 * Description: Create customizable bundle promotions with tiered discounts based on quantity
 * Version: 1.0.0
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
define( 'MMB_VERSION', '1.0.0' );
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
        
        wp_enqueue_style( 'mix-match-frontend', MMB_PLUGIN_URL . 'assets/css/frontend.css', [], MMB_VERSION );
        wp_enqueue_script( 'mix-match-frontend', MMB_PLUGIN_URL . 'assets/js/frontend.js', [], MMB_VERSION, true );
        
        wp_localize_script( 'mix-match-frontend', 'mmb_frontend', [
            'nonce' => wp_create_nonce( 'mmb_frontend_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'cart_url' => wc_get_cart_url(),
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
        } catch ( Exception $e ) {
            // Silent fail to avoid breaking activation.
        }
    }
    
    public function activate_plugin() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( mmb_get_table_schema_sql() );
        
        // Run upgrade check
        $this->maybe_upgrade_database();
        
        // Set initial database version
        update_option( 'mmb_db_version', MMB_DB_VERSION );

        flush_rewrite_rules();
    }
}

// Plugin initialization moved to mmb_init_plugin() function
// which runs on 'plugins_loaded' hook to ensure WooCommerce is available

// AJAX Handlers
add_action( 'wp_ajax_mmb_save_bundle', 'mmb_save_bundle' );
add_action( 'wp_ajax_mmb_get_bundles', 'mmb_get_bundles' );
add_action( 'wp_ajax_mmb_delete_bundle', 'mmb_delete_bundle' );
add_action( 'wp_ajax_mmb_search_products', 'mmb_search_products' );
add_action( 'wp_ajax_nopriv_mmb_update_bundle_items', 'mmb_update_bundle_items' );
add_action( 'wp_ajax_mmb_update_bundle_items', 'mmb_update_bundle_items' );
add_action( 'wp_ajax_nopriv_mmb_add_bundle_to_cart', 'mmb_add_bundle_to_cart' );
add_action( 'wp_ajax_mmb_add_bundle_to_cart', 'mmb_add_bundle_to_cart' );
add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', 'mmb_wc_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'mmb_wc_ajax_add_to_cart' );

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

function mmb_add_bundle_to_cart() {
    check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( wp_unslash( $_POST['bundle_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $bundle_items_json = isset( $_POST['bundle_items'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bundle_items'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $discount_amount = isset( $_POST['discount_amount'] ) ? floatval( wp_unslash( $_POST['discount_amount'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    
    // Debug logging
    
    // Parse bundle items
    $bundle_items = json_decode( $bundle_items_json, true );
    
    if ( ! $bundle_id || empty( $bundle_items ) ) {
        wp_send_json_error( __( 'Invalid data', 'mix-match-bundle' ) );
    }
    
    // Extract product IDs for discount tracking
    $product_ids = [];
    $variation_ids = [];
    foreach ( $bundle_items as $item ) {
        $product_id = isset( $item['product_id'] ) ? intval( $item['product_id'] ) : ( isset( $item['id'] ) ? intval( $item['id'] ) : 0 );
        $variation_id = isset( $item['variation_id'] ) ? intval( $item['variation_id'] ) : 0;
        
        if ( $product_id ) {
            $product_ids[] = $product_id;
            if ( $variation_id ) {
                $variation_ids[] = $variation_id;
            }
        }
    }
    
    // Store bundle info in session
    WC()->session->set( 'mmb_bundle_discount', [
        'bundle_id' => $bundle_id,
        'product_ids' => $product_ids,
        'variation_ids' => $variation_ids,
        'discount_amount' => $discount_amount,
    ]);
    
    wp_send_json_success( __( 'Bundle added to session', 'mix-match-bundle' ) );
}

function mmb_wc_ajax_add_to_cart() {
    // Ensure WooCommerce is loaded
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        wp_send_json_error( __( 'WooCommerce not available', 'mix-match-bundle' ) );
    }
    
    $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    $quantity = isset( $_POST['quantity'] ) ? absint( wp_unslash( $_POST['quantity'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    
    if ( $product_id < 1 ) {
        wp_send_json_error( __( 'Invalid product ID', 'mix-match-bundle' ) );
    }
    
    // Determine which ID to use for the product check
    $check_id = $variation_id ? $variation_id : $product_id;
    
    // Check if product exists and is purchasable
    $product = wc_get_product( $check_id );
    if ( ! $product || ! $product->is_purchasable() ) {
        /* translators: %s: product or variation ID */
        wp_send_json_error( sprintf( __( 'Product cannot be purchased: %s', 'mix-match-bundle' ), ( $variation_id ? "Variation $variation_id" : "Product $product_id" ) ) );
    }
    
    // Add product to cart (with variation support)
    if ( $variation_id ) {
        // For variations, we need to pass the parent product ID and variation ID separately
        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
    } else {
        // Simple product
        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );
    }
    
    if ( $cart_item_key ) {
        // Get cart count
        $cart_count = WC()->cart->get_cart_contents_count();
        
        wp_send_json_success( [
            'cart_item_key' => $cart_item_key,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'cart_count' => $cart_count,
        ] );
    } else {
        wp_send_json_error( __( 'Failed to add product to cart', 'mix-match-bundle' ) );
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

