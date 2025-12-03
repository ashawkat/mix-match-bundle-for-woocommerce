<?php
/**
 * Mix & Match Cart Handling
 * 
 * Uses dynamic WooCommerce coupons to apply bundle discounts.
 * This ensures discounts display correctly in all cart views (sidecart, checkout, etc.)
 */

class MMB_Cart {
    
    private static $instance = null;
    private $coupon_code_prefix = 'mmb_bundle_';
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Apply coupon when bundle items are added
        add_action( 'woocommerce_add_to_cart', [ $this, 'maybe_apply_bundle_coupon' ], 10, 6 );
        
        // Clean up coupon when bundle items are removed
        add_action( 'woocommerce_cart_item_removed', [ $this, 'maybe_remove_bundle_coupon' ], 10, 2 );
        
        // Clean up coupon when cart is emptied
        add_action( 'woocommerce_cart_emptied', [ $this, 'remove_bundle_coupon' ], 10 );
        
        // Ensure session is available for AJAX requests
        add_action( 'woocommerce_init', [ $this, 'ensure_session' ], 5 );
        
        // Allow our dynamic coupon to be applied - hook into multiple validation points
        add_filter( 'woocommerce_coupon_is_valid', [ $this, 'validate_bundle_coupon' ], 10, 3 );
        add_filter( 'woocommerce_coupon_error', [ $this, 'suppress_bundle_coupon_error' ], 10, 3 );
        add_filter( 'woocommerce_coupon_is_valid_for_cart', [ $this, 'validate_bundle_coupon_for_cart' ], 10, 2 );
        
        // Remove error notices for bundle coupons
        add_action( 'woocommerce_before_checkout_process', [ $this, 'remove_bundle_coupon_notices' ], 5 );
        add_action( 'woocommerce_after_calculate_totals', [ $this, 'remove_bundle_coupon_notices' ], 5 );
        add_action( 'woocommerce_applied_coupon', [ $this, 'remove_bundle_coupon_notices' ], 5 );
        
        // Ensure block-based mini cart gets updated totals with coupon discount
        add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'update_block_mini_cart_fragments' ], 20, 1 );
        
        // Ensure cart hash changes when coupon is applied (for block cart refresh)
        add_action( 'woocommerce_applied_coupon', [ $this, 'update_cart_hash_for_blocks' ], 10, 1 );
        
        // Ensure cart totals are recalculated after coupon application for block cart
        add_action( 'woocommerce_applied_coupon', [ $this, 'recalculate_totals_for_blocks' ], 20, 1 );
        
        // Verify coupon is applied after cart calculation
        add_action( 'woocommerce_after_calculate_totals', [ $this, 'verify_coupon_applied' ], 10, 1 );
    }
    
    /**
     * Ensure WooCommerce session is available for AJAX requests
     */
    public function ensure_session() {
        // Don't initialize if session already exists
        if ( WC()->session ) {
            return;
        }
        
        // Initialize session for all users (including logged-out)
        if ( ! class_exists( 'WC_Session_Handler' ) ) {
            include_once WC_ABSPATH . 'includes/class-wc-session-handler.php';
        }
        
        // Create new session handler
        WC()->session = new WC_Session_Handler();
        
        // Initialize session with customer ID
        $customer_id = WC()->session->get_customer_id();
        if ( ! $customer_id ) {
            // Generate a unique ID for guest customers
            $customer_id = $this->generate_guest_id();
            WC()->session->set_customer_id( $customer_id );
        }
        
        // Initialize session data
        WC()->session->init();
        
        // Ensure session cookie is set
        if ( ! headers_sent() ) {
            WC()->session->set_customer_session_cookie( true );
        }
    }
    
    /**
     * Generate a unique guest ID for session tracking
     */
    private function generate_guest_id() {
        // Try to get existing guest ID from cookie
        if ( isset( $_COOKIE['woocommerce_mmb_guest_id'] ) ) {
            return sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_mmb_guest_id'] ) );
        }
        
        // Generate new unique ID
        $guest_id = 'mmb_guest_' . wp_generate_uuid4();
        
        // Set cookie for 30 days
        if ( ! headers_sent() ) {
            setcookie( 'woocommerce_mmb_guest_id', $guest_id, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        
        return $guest_id;
    }
    
    /**
     * Get or create unique bundle coupon code for this session
     */
    private function get_bundle_coupon_code( $bundle_id ) {
        if ( ! WC()->session ) {
            return $this->generate_unique_coupon_code( $bundle_id );
        }
        
        // Check if we already have a coupon code for this bundle in this session
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        if ( $session_data && isset( $session_data['coupon_code'] ) && ! empty( $session_data['coupon_code'] ) ) {
            return $session_data['coupon_code'];
        }
        
        // Generate new unique coupon code
        $coupon_code = $this->generate_unique_coupon_code( $bundle_id );
        
        // Store in session (create session data if it doesn't exist)
        if ( ! $session_data ) {
            $session_data = [];
        }
        $session_data['coupon_code'] = $coupon_code;
        WC()->session->set( 'mmb_bundle_discount', $session_data );
        
        return $coupon_code;
    }
    
    /**
     * Generate a unique coupon code
     */
    private function generate_unique_coupon_code( $bundle_id ) {
        // Generate unique code: mmb_bundle_{bundle_id}_{timestamp}_{random}
        $timestamp = time();
        
        // Generate random string - use wp_generate_password if available, otherwise use mt_rand
        if ( function_exists( 'wp_generate_password' ) ) {
            $random = wp_generate_password( 6, false ); // 6 character random string
        } else {
            // Fallback: generate random alphanumeric string
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $random = '';
            for ( $i = 0; $i < 6; $i++ ) {
                $random .= $characters[ wp_rand( 0, strlen( $characters ) - 1 ) ];
            }
        }
        
        return $this->coupon_code_prefix . $bundle_id . '_' . $timestamp . '_' . $random;
    }
    
    /**
     * Create a dynamic coupon for bundle discount
     */
    public function create_bundle_coupon( $bundle_id, $discount_amount, $subtotal, $product_ids = [] ) {
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
            
            $coupon_code = $this->get_bundle_coupon_code( $bundle_id );
            
            // Check if coupon already exists
            $coupon_id = wc_get_coupon_id_by_code( $coupon_code );
            
            if ( $coupon_id ) {
                // Update existing coupon
                $coupon = new WC_Coupon( $coupon_id );
            } else {
                // Create new coupon
                $coupon = new WC_Coupon();
                $coupon->set_code( $coupon_code );
            }
            
            // Use fixed cart discount - applies to entire cart
            // Note: This applies to entire cart, but since we only apply it when bundle items are added
            // and remove it when bundle items are removed, it should work correctly
            $coupon->set_discount_type( 'fixed_cart' );
            $coupon->set_amount( $discount_amount );
            $coupon->set_individual_use( false );
            $coupon->set_usage_limit( 1 ); // Limit to 1 use per customer
            $coupon->set_usage_limit_per_user( 1 );
            $coupon->set_limit_usage_to_x_items( null );
            $coupon->set_free_shipping( false );
            $coupon->set_exclude_sale_items( false );
            
            // Set minimum amount to 0 (no minimum required)
            $coupon->set_minimum_amount( 0 );
            $coupon->set_maximum_amount( '' );
            
            // Set expiration (24 hours from now)
            $coupon->set_date_expires( time() + DAY_IN_SECONDS );
            
            // Restrict coupon to bundle products only
            if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
                $coupon->set_product_ids( $product_ids );
            }
            
            // Ensure coupon is published/active
            $coupon->set_status( 'publish' );
            
            // Save coupon
            $saved = $coupon->save();
            
            if ( ! $saved ) {
                throw new Exception( 'Failed to save coupon' );
            }
            
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $logger = wc_get_logger();
                $logger->debug( 'MMB: Coupon created successfully - Code: ' . $coupon_code, array( 'source' => 'mix-match-bundle' ) );
                $logger->debug( 'MMB: Coupon details - Type: ' . $coupon->get_discount_type(), array( 'source' => 'mix-match-bundle' ) );
                $logger->debug( 'MMB: Coupon amount: ' . $coupon->get_amount(), array( 'source' => 'mix-match-bundle' ) );
                $logger->debug( 'MMB: Coupon usage limit: ' . $coupon->get_usage_limit(), array( 'source' => 'mix-match-bundle' ) );
                $logger->debug( 'MMB: Coupon product restriction: ' . json_encode( $coupon->get_product_ids() ), array( 'source' => 'mix-match-bundle' ) );
            }
            
            // Clear coupon cache to ensure it's immediately available
            if ( function_exists( 'wc_delete_shop_order_transients' ) ) {
                wc_delete_shop_order_transients();
            }
            if ( function_exists( 'wp_cache_delete' ) ) {
                wp_cache_delete( 'wc_coupon_' . $coupon_code );
            }
            
            return $coupon_code;
        } catch ( Exception $e ) {
            // Log error for debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $logger = wc_get_logger();
                $logger->error( 'MMB Coupon Creation Error: ' . $e->getMessage(), array( 'source' => 'mix-match-bundle' ) );
            }
            
            // Return empty string to indicate failure
            return '';
        }
    }
    
    /**
     * Apply bundle coupon to cart
     */
    public function maybe_apply_bundle_coupon( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        // Only apply if this is a bundle item
        if ( empty( $cart_item_data['mmb_bundle_item'] ) ) {
            return;
        }
        
        if ( ! WC()->cart || ! WC()->session ) {
            return;
        }
        
        // Use a small delay to ensure all items are added before applying coupon
        // This prevents race conditions when adding multiple items
        add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'apply_bundle_coupon_delayed' ], 20 );
        add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'apply_bundle_coupon_delayed' ], 20 );
    }
    
    /**
     * Apply bundle coupon (delayed to ensure all items are added)
     */
    public function apply_bundle_coupon_delayed() {
        if ( ! WC()->cart || ! WC()->session ) {
            return;
        }
        
        // Get bundle data from session
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        if ( ! $session_data ) {
            return;
        }
        
        $bundle_id = isset( $session_data['bundle_id'] ) ? intval( $session_data['bundle_id'] ) : 0;
        $discount_amount = isset( $session_data['discount_amount'] ) ? floatval( $session_data['discount_amount'] ) : 0;
        
        if ( $bundle_id <= 0 || $discount_amount <= 0 ) {
            return;
        }
        
        $coupon_code = $this->get_bundle_coupon_code( $bundle_id );
        
        // Only proceed if coupon was created successfully
        if ( ! empty( $coupon_code ) ) {
            // Verify coupon exists before applying
            $coupon_id = wc_get_coupon_id_by_code( $coupon_code );
            
            if ( $coupon_id ) {
                // Apply coupon to cart
                $coupon_applied = WC()->cart->apply_coupon( $coupon_code );
                
                // Verify coupon was applied
                if ( $coupon_applied ) {
                    // Remove any error notices about our bundle coupon
                    if ( method_exists( $cart_handler, 'remove_bundle_coupon_notices' ) ) {
                        $cart_handler->remove_bundle_coupon_notices();
                    }
                    
                    // Recalculate totals immediately after coupon application
                    WC()->cart->calculate_totals();
                }
            }
        }
    }
    
    /**
     * Remove bundle coupon when bundle items are removed
     */
    public function maybe_remove_bundle_coupon( $cart_item_key, $cart ) {
        if ( ! WC()->session || ! WC()->cart ) {
            return;
        }
        
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        if ( ! $session_data ) {
            return;
        }
        
        $bundle_id = isset( $session_data['bundle_id'] ) ? intval( $session_data['bundle_id'] ) : 0;
        if ( $bundle_id <= 0 ) {
            return;
        }
        
        // Check if any bundle items remain
        $has_bundle_items = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( ! empty( $item['mmb_bundle_item'] ) ) {
                $has_bundle_items = true;
                break;
            }
        }
        
        // Remove coupon if no bundle items remain
        if ( ! $has_bundle_items ) {
            // Get coupon code from session
            $coupon_code = isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null;
            if ( ! empty( $coupon_code ) && WC()->cart->has_discount( $coupon_code ) ) {
                WC()->cart->remove_coupon( $coupon_code );
            }
        }
    }
    
    /**
     * Remove bundle coupon when cart is emptied
     */
    public function remove_bundle_coupon() {
        if ( ! WC()->session ) {
            return;
        }
        
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        if ( ! $session_data ) {
            return;
        }
        
        // Get coupon code from session
        $coupon_code = isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null;
        if ( ! empty( $coupon_code ) && WC()->cart && WC()->cart->has_discount( $coupon_code ) ) {
            WC()->cart->remove_coupon( $coupon_code );
        }
    }
    
    /**
     * Validate bundle coupon (allow it even if it doesn't exist in database yet)
     */
    public function validate_bundle_coupon( $is_valid, $coupon, $discount_obj ) {
        // Allow our dynamic bundle coupons
        if ( is_string( $coupon ) ) {
            $coupon_code = $coupon;
        } else {
            $coupon_code = $coupon->get_code();
        }
        
        if ( strpos( $coupon_code, $this->coupon_code_prefix ) === 0 ) {
            return true;
        }
        
        return $is_valid;
    }
    
    /**
     * Suppress error messages for bundle coupons
     */
    public function suppress_bundle_coupon_error( $error, $error_code, $coupon ) {
        // If this is our bundle coupon, suppress the error
        $coupon_code = '';
        
        if ( is_object( $coupon ) && method_exists( $coupon, 'get_code' ) ) {
            $coupon_code = $coupon->get_code();
        } elseif ( is_string( $coupon ) ) {
            $coupon_code = $coupon;
        } elseif ( is_numeric( $coupon ) ) {
            // Might be a coupon ID
            $coupon_obj = new WC_Coupon( $coupon );
            $coupon_code = $coupon_obj->get_code();
        }
        
        if ( ! empty( $coupon_code ) && strpos( $coupon_code, $this->coupon_code_prefix ) === 0 ) {
            // Return false to suppress error (WooCommerce checks for false)
            return false;
        }
        
        return $error;
    }
    
    /**
     * Validate bundle coupon for cart usage
     */
    public function validate_bundle_coupon_for_cart( $is_valid, $coupon ) {
        if ( is_object( $coupon ) ) {
            $coupon_code = $coupon->get_code();
        } elseif ( is_string( $coupon ) ) {
            $coupon_code = $coupon;
        } else {
            return $is_valid;
        }
        
        if ( strpos( $coupon_code, $this->coupon_code_prefix ) === 0 ) {
            return true;
        }
        
        return $is_valid;
    }
    
    /**
     * Update block-based mini cart fragments to include coupon discount
     */
    public function update_block_mini_cart_fragments( $fragments ) {
        // Ensure cart totals are calculated with coupon discount
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            // Recalculate totals to ensure coupon discount is included
            // This is critical for block-based mini cart to show correct totals
            WC()->cart->calculate_totals();
            
            // Add cart hash to fragments to ensure block cart knows cart has changed
            // The block cart uses cart_hash to determine if it needs to refresh
            $fragments['cart_hash'] = WC()->cart->get_cart_hash();
            
            // Add cart totals as a fragment for block cart
            // Some block implementations check for this
            ob_start();
            ?>
            <span class="wc-block-mini-cart__amount"><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></span>
            <?php
            $fragments['.wc-block-mini-cart__amount'] = ob_get_clean();
        }
        
        return $fragments;
    }
    
    /**
     * Update cart hash when coupon is applied to trigger block cart refresh
     */
    public function update_cart_hash_for_blocks( $coupon_code ) {
        // Check if this is our bundle coupon
        if ( strpos( $coupon_code, $this->coupon_code_prefix ) === 0 ) {
            // Force cart hash update by recalculating totals
            if ( WC()->cart ) {
                WC()->cart->calculate_totals();
                // The cart hash will automatically update when totals are recalculated
            }
        }
    }
    
    /**
     * Recalculate totals after coupon application for block cart
     */
    public function recalculate_totals_for_blocks( $coupon_code ) {
        // Check if this is our bundle coupon
        if ( strpos( $coupon_code, $this->coupon_code_prefix ) === 0 ) {
            // Ensure totals are recalculated with coupon discount
            // This ensures the block cart sees the updated totals when it fetches cart data
            if ( WC()->cart ) {
                WC()->cart->calculate_totals();
            }
        }
    }
    
    /**
     * Verify coupon is applied after cart calculation
     */
    public function verify_coupon_applied( $cart ) {
        if ( ! WC()->session || ! $cart ) {
            return;
        }
        
        // Get bundle session data
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        if ( ! $session_data || ! isset( $session_data['coupon_code'] ) ) {
            return;
        }
        
        $coupon_code = $session_data['coupon_code'];
        
        // Verify coupon is in applied coupons list
        $applied_coupons = $cart->get_applied_coupons();
        if ( ! in_array( $coupon_code, $applied_coupons, true ) ) {
            // Coupon not applied, try to apply it
            if ( ! $cart->has_discount( $coupon_code ) ) {
                $coupon_id = wc_get_coupon_id_by_code( $coupon_code );
                if ( $coupon_id ) {
                    $cart->apply_coupon( $coupon_code );
                    $cart->calculate_totals();
                }
            }
        }
    }
    
    /**
     * Remove bundle coupon error notices
     */
    public function remove_bundle_coupon_notices() {
        try {
            if ( ! WC()->session ) {
                return;
            }
            
            // Get all error notices from session
            $notices = WC()->session->get( 'wc_notices', [] );
            if ( empty( $notices ) || ! isset( $notices['error'] ) || ! is_array( $notices['error'] ) ) {
                return;
            }
            
            // Filter out notices that mention our bundle coupon prefix
            $filtered_notices = [];
            foreach ( $notices['error'] as $notice ) {
                $notice_text = '';
                if ( is_array( $notice ) && isset( $notice['notice'] ) ) {
                    $notice_text = $notice['notice'];
                } elseif ( is_string( $notice ) ) {
                    $notice_text = $notice;
                }
                
                // Keep notices that don't mention our bundle coupon prefix
                if ( empty( $notice_text ) || strpos( $notice_text, $this->coupon_code_prefix ) === false ) {
                    $filtered_notices[] = $notice;
                }
            }
            
            // Update session with filtered notices
            $notices['error'] = $filtered_notices;
            WC()->session->set( 'wc_notices', $notices );
        } catch ( Exception $e ) {
            // Silently fail - don't break cart functionality
            mmb_debug_log( 'MMB Notice Removal Error: ' . $e->getMessage(), 'error' );
        }
    }

    /**
     * Debug function to log current coupon state
     * Call this via AJAX to see what's happening with coupons
     */
    public function debug_coupon_state() {
        if ( ! WC()->cart ) {
            return [ 'error' => 'Cart not available' ];
        }
        
        $debug_info = [
            'cart_contents' => [],
            'applied_coupons' => [],
            'coupon_data' => [],
        ];
        
        // Get cart contents
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $debug_info['cart_contents'][] = [
                'key' => $cart_item_key,
                'product_id' => $cart_item['product_id'],
                'name' => $cart_item['data']->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $cart_item['data']->get_price(),
                'subtotal' => $cart_item['data']->get_price() * $cart_item['quantity'],
                'bundle_item' => ! empty( $cart_item['mmb_bundle_item'] ),
                'bundle_id' => ! empty( $cart_item['mmb_bundle_id'] ) ? $cart_item['mmb_bundle_id'] : null,
            ];
        }
        
        // Get applied coupons
        $applied_coupons = WC()->cart->get_applied_coupons();
        foreach ( $applied_coupons as $coupon ) {
            $debug_info['applied_coupons'][] = [
                'code' => $coupon->get_code(),
                'type' => $coupon->get_discount_type(),
                'amount' => $coupon->get_amount(),
                'product_ids' => $coupon->get_product_ids(),
                'usage_limit' => $coupon->get_usage_limit(),
                'usage_count' => $coupon->get_usage_count(),
                'date_expires' => $coupon->get_date_expires(),
            ];
        }
        
        // Get session data
        $session_data = WC()->session ? WC()->session->get( 'mmb_bundle_discount' ) : null;
        if ( $session_data ) {
            $debug_info['coupon_data'] = [
                'bundle_id' => $session_data['bundle_id'],
                'discount_amount' => $session_data['discount_amount'],
                'coupon_code' => isset( $session_data['coupon_code'] ) ? $session_data['coupon_code'] : null,
            ];
        }
        
        return $debug_info;
    }
}
