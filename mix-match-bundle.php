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
        $db_version = get_option( 'mmb_db_version', '0' );
        if ( version_compare( $db_version, MMB_VERSION, '<' ) ) {
            $this->maybe_upgrade_database();
            update_option( 'mmb_db_version', MMB_VERSION );
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
        
        // Internationalization
        add_action( 'init', [ $this, 'load_textdomain' ] );
        
        // Database setup
        register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
        
        // HPOS Compatibility
        add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'mix-match-bundle', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
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
        wp_enqueue_style( 'mix-match-frontend', MMB_PLUGIN_URL . 'assets/css/frontend.css', [], MMB_VERSION );
        wp_enqueue_script( 'mix-match-frontend', MMB_PLUGIN_URL . 'assets/js/frontend.js', [], MMB_VERSION, true );
        
        wp_localize_script( 'mix-match-frontend', 'mmb_frontend', [
            'nonce' => wp_create_nonce( 'mmb_frontend_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'cart_url' => wc_get_cart_url(),
        ]);
    }
    
    private function maybe_upgrade_database() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'mmb_bundles';
            
            // Check if table exists first
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
            if ( ! $table_exists ) {
                return;
            }
        
        // Check and add use_quantity column
        $column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'use_quantity'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN use_quantity tinyint(1) DEFAULT 0 AFTER enabled" );
        }
        
        // Check and add heading_text column
        $heading_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'heading_text'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $heading_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN heading_text varchar(255) DEFAULT 'Select Your Products Below' AFTER product_ids" );
        }
        
        // Check and add hint_text column
        $hint_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'hint_text'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $hint_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN hint_text varchar(255) DEFAULT 'Bundle 2, 3, 4 or 5 items and watch the savings grow.' AFTER heading_text" );
        }
        
        // Check and add primary_color column
        $color_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'primary_color'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $color_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN primary_color varchar(7) DEFAULT '#4caf50' AFTER hint_text" );
        }
        
        // Check and add button_text column
        $button_text_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'button_text'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $button_text_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN button_text varchar(255) DEFAULT 'Add Bundle to Cart' AFTER primary_color" );
        }
        
        // Check and add progress_text column
        $progress_text_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'progress_text'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $progress_text_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN progress_text varchar(255) DEFAULT 'Your Savings Progress' AFTER button_text" );
        }
        
        // Check and add cart_behavior column
        $cart_behavior_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'cart_behavior'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $cart_behavior_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN cart_behavior varchar(20) DEFAULT 'sidecart' AFTER progress_text" );
        }
        
        // Check and add accent_color column
        $accent_color_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'accent_color'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $accent_color_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN accent_color varchar(7) DEFAULT '#45a049' AFTER primary_color" );
        }
        
        // Check and add hover_bg_color column
        $hover_bg_color_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'hover_bg_color'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $hover_bg_color_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN hover_bg_color varchar(7) DEFAULT '#388e3c' AFTER accent_color" );
        }
        
        // Check and add hover_accent_color column
        $hover_accent_color_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'hover_accent_color'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $hover_accent_color_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN hover_accent_color varchar(7) DEFAULT '#2e7d32' AFTER hover_bg_color" );
        }
        
        // Check and add button_text_color column
        $button_text_color_column_exists = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND column_name = 'button_text_color'",
                DB_NAME,
                $table_name
            )
        );
        
        if ( empty( $button_text_color_column_exists ) ) {
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN button_text_color varchar(7) DEFAULT '#ffffff' AFTER hover_accent_color" );
        }
        
        // Check and add show/hide columns
        $show_columns = [
            'show_bundle_title' => 1,
            'show_bundle_description' => 1,
            'show_heading_text' => 1,
            'show_hint_text' => 1,
            'show_progress_text' => 1
        ];
        
        foreach ( $show_columns as $column_name => $default_value ) {
            $column_exists = $wpdb->get_results( 
                $wpdb->prepare(
                    "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE table_schema = %s 
                    AND table_name = %s 
                    AND column_name = %s",
                    DB_NAME,
                    $table_name,
                    $column_name
                )
            );
            
            if ( empty( $column_exists ) ) {
                $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN {$column_name} tinyint(1) DEFAULT {$default_value} AFTER cart_behavior" );
            }
        }
        } catch ( Exception $e ) {
            // Log error but don't break the site
            error_log( 'MMB Database Upgrade Error: ' . $e->getMessage() );
        }
    }
    
    public function activate_plugin() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'mmb_bundles';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description longtext,
            enabled tinyint(1) DEFAULT 1,
            use_quantity tinyint(1) DEFAULT 0,
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
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        
        // Run upgrade check
        $this->maybe_upgrade_database();
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
    error_log( '=== mmb_save_bundle - RAW $_POST ===' );
    error_log( 'product_ids from POST: ' . ( isset( $_POST['product_ids'] ) ? $_POST['product_ids'] : 'NOT SET' ) );
    error_log( 'discount_tiers from POST: ' . ( isset( $_POST['discount_tiers'] ) ? $_POST['discount_tiers'] : 'NOT SET' ) );
    error_log( 'Full $_POST: ' . print_r( $_POST, true ) );
    
    $bundle_manager = new MMB_Bundle_Manager();
    $result = $bundle_manager->save_bundle( $_POST );
    
    if ( $result ) {
        wp_send_json_success( $result );
    } else {
        // Return detailed error message
        global $wpdb;
        $error_message = $wpdb->last_error ? $wpdb->last_error : __( 'Failed to save bundle', 'mix-match-bundle' );
        error_log( 'Save bundle failed with error: ' . $error_message );
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
    error_log( '=== mmb_get_bundles AJAX ===' );
    error_log( 'Sending ' . count( $bundles ) . ' bundles' );
    foreach ( $bundles as $bundle ) {
        error_log( 'Bundle ID ' . $bundle['id'] . ' - discount_tiers: ' . print_r( $bundle['discount_tiers'], true ) );
    }
    
    wp_send_json_success( $bundles );
}

function mmb_delete_bundle() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( $_POST['bundle_id'] ) : 0;
    
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
    error_log( '=== mmb_update_bundle_items Called ===' );
    error_log( 'POST data: ' . print_r( $_POST, true ) );
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( $_POST['bundle_id'] ) : 0;
    $product_ids_raw = isset( $_POST['product_ids'] ) ? $_POST['product_ids'] : '';
    
    // Parse product_ids if it's JSON
    if ( is_string( $product_ids_raw ) ) {
        // Remove escaping that URLSearchParams adds
        $product_ids_clean = stripslashes( $product_ids_raw );
        error_log( 'Product IDs (cleaned): ' . $product_ids_clean );
        $product_ids = json_decode( $product_ids_clean, true );
    } else {
        $product_ids = (array) $product_ids_raw;
    }
    
    error_log( 'Bundle ID: ' . $bundle_id );
    error_log( 'Product IDs (parsed): ' . print_r( $product_ids, true ) );
    
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        error_log( 'JSON Decode Error: ' . json_last_error_msg() );
    }
    
    if ( ! $bundle_id || empty( $product_ids ) ) {
        error_log( 'ERROR: Invalid data in mmb_update_bundle_items' );
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
    error_log( '=== mmb_update_bundle_items Response ===' );
    error_log( 'Products count: ' . count( $products_data ) );
    error_log( 'Products data: ' . print_r( $products_data, true ) );
    error_log( 'Full response: ' . print_r( $response_data, true ) );
    
    wp_send_json_success( $response_data );
}

function mmb_search_products() {
    check_ajax_referer( 'mmb_admin_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'mix-match-bundle' ) );
    }
    
    $search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
    
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
    
    $bundle_id = isset( $_POST['bundle_id'] ) ? intval( $_POST['bundle_id'] ) : 0;
    $bundle_items_json = isset( $_POST['bundle_items'] ) ? $_POST['bundle_items'] : '';
    $discount_amount = isset( $_POST['discount_amount'] ) ? floatval( $_POST['discount_amount'] ) : 0;
    
    // Debug logging
    error_log( '=== mmb_add_bundle_to_cart ===' );
    error_log( 'Bundle ID: ' . $bundle_id );
    error_log( 'Bundle Items JSON (raw): ' . $bundle_items_json );
    error_log( 'Discount Amount: ' . $discount_amount );
    
    // Parse bundle items
    $bundle_items = json_decode( stripslashes( $bundle_items_json ), true );
    error_log( 'Bundle Items (decoded): ' . print_r( $bundle_items, true ) );
    
    if ( ! $bundle_id || empty( $bundle_items ) ) {
        error_log( 'ERROR: Invalid data - bundle_id: ' . $bundle_id . ', items count: ' . count( (array) $bundle_items ) );
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
    
    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
    $quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
    
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
