<?php
/**
 * Mix & Match Cart Handling
 */

class MMB_Cart {
    
    public function __construct() {
        add_filter( 'woocommerce_cart_calculate_fees', [ $this, 'add_bundle_discount' ] );
        // Disabled: Modifying prices can cause "product cannot be purchased" errors
        // add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_bundle_item_prices' ], 10, 1 );
    }
    
    /**
     * Add bundle discount as fee
     */
    public function add_bundle_discount( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        
        if ( is_null( $cart ) ) {
            $cart = WC()->cart;
        }
        
        $bundle_manager = new MMB_Bundle_Manager();
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        
        if ( ! $session_data || empty( $session_data['product_ids'] ) ) {
            return;
        }
        
        $bundle = $bundle_manager->get_bundle( $session_data['bundle_id'] );
        
        if ( ! $bundle ) {
            return;
        }
        
        // Check which bundle products are actually in the cart and count total quantities
        $bundle_items_count = 0;
        $bundle_subtotal = 0;
        $variation_ids = isset( $session_data['variation_ids'] ) ? $session_data['variation_ids'] : [];
        
        foreach ( $cart->get_cart() as $cart_item ) {
            $is_bundle_item = false;
            
            // Check if it's a variation in the bundle
            if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] && in_array( $cart_item['variation_id'], $variation_ids ) ) {
                $is_bundle_item = true;
            }
            // Check if it's a simple product in the bundle
            elseif ( in_array( $cart_item['product_id'], $session_data['product_ids'] ) ) {
                $is_bundle_item = true;
            }
            
            if ( $is_bundle_item ) {
                // Count the actual quantity, not just unique products
                $bundle_items_count += $cart_item['quantity'];
                $bundle_subtotal += $cart_item['line_total'];
            }
        }
        
        // Only apply discount if we have bundle products in cart
        if ( $bundle_items_count === 0 ) {
            return;
        }
        
        // Get discount tier based on TOTAL quantity of items in cart (not unique products)
        $tier = $bundle_manager->get_applicable_tier( $bundle, $bundle_items_count );
        $discount_amount = ( $bundle_subtotal * $tier['discount'] ) / 100;
        
        if ( $discount_amount > 0 ) {
            /* translators: %s: discount percentage */
            $fee_label = sprintf( __( 'Bundle Discount (%s%% off)', 'mix-match-bundle' ), $tier['discount'] );
            $cart->add_fee( 
                $fee_label,
                -$discount_amount 
            );
        }
    }
    
    /**
     * Set item prices for bundle
     */
    public function set_bundle_item_prices( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        
        if ( is_null( $cart ) ) {
            $cart = WC()->cart;
        }
        
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        
        if ( ! $session_data || ! isset( $session_data['product_ids'] ) ) {
            return;
        }
        
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( in_array( $cart_item['product_id'], $session_data['product_ids'] ) ) {
                $cart_item['data']->set_price( $session_data['per_item_price'] );
            }
        }
    }
}

new MMB_Cart();
