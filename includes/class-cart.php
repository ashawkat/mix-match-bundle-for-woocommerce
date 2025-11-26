<?php
/**
 * Mix & Match Cart Handling
 */

class MMB_Cart {
    
    public function __construct() {
        add_filter( 'woocommerce_cart_calculate_fees', [ $this, 'add_bundle_discount' ] );
        // Disabled: Modifying prices can cause "product cannot be purchased" errors
        // add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_bundle_item_prices' ], 10, 1 );
        
        // Add filters to show original and discounted prices in sidecart
        // Use high priority to ensure cart totals are calculated first
        add_filter( 'woocommerce_cart_item_price', [ $this, 'display_bundle_item_price' ], 99, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'display_bundle_item_subtotal' ], 99, 3 );
        add_filter( 'woocommerce_cart_product_subtotal', [ $this, 'display_bundle_product_subtotal' ], 99, 4 );
        
        // Ensure cart totals are calculated before sidecart renders
        add_action( 'fkcart_get_cart_item', [ $this, 'ensure_cart_totals_calculated' ], 5 );
        
        // Since FunnelKit doesn't have a filter for get_items(), we need to intercept it
        // We'll hook into the template rendering to modify prices there
        add_action( 'fkcart_before_cart_items', [ $this, 'ensure_cart_totals_before_render' ], 5 );
        
        // Ensure cart totals are calculated before fragments are generated
        // Use high priority to run before FunnelKit processes fragments
        add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'ensure_session_for_fragments' ], 1 );
        add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'ensure_cart_totals_before_fragments' ], 5 );
        add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'inject_bundle_prices_into_fragments' ], 50 );
        add_filter( 'fkcart_fragments', [ $this, 'ensure_session_for_fragments' ], 1 );
        add_filter( 'fkcart_fragments', [ $this, 'ensure_cart_totals_before_fragments' ], 5 );
        add_filter( 'fkcart_fragments', [ $this, 'inject_bundle_prices_into_fragments' ], 50 );
        
        // Hook into when FunnelKit gets items to ensure our filters run
        // This runs before get_items() is called, so we can ensure totals are calculated
        add_action( 'fkcart_get_cart_item', [ $this, 'ensure_cart_totals_before_get_items' ], 1 );
        
        // Modify items array after it's built but before template renders
        // We'll hook into the template rendering to modify the items
        add_action( 'fkcart_before_cart_items', [ $this, 'modify_cart_items_for_template' ], 10, 1 );
        
        // Filter the template output directly
        add_filter( 'fkcart_cart_item_price_output', [ $this, 'filter_price_output_in_template' ], 20, 2 );

        // AJAX endpoint to sync prices in sidecart
        add_action( 'wp_ajax_mmb_get_bundle_prices', [ $this, 'ajax_get_bundle_prices' ] );
        add_action( 'wp_ajax_nopriv_mmb_get_bundle_prices', [ $this, 'ajax_get_bundle_prices' ] );
        
        // Ensure session is available for AJAX requests
        add_action( 'woocommerce_init', [ $this, 'ensure_session' ], 5 );
        
        // Allow del and ins tags in wp_kses_post for our discounted prices
        add_filter( 'wp_kses_allowed_html', [ $this, 'allow_price_tags_in_kses' ], 10, 2 );
    }
    
    /**
     * Allow del and ins tags in wp_kses_post for our discounted prices
     */
    public function allow_price_tags_in_kses( $allowed, $context ) {
        if ( 'post' === $context ) {
            $allowed['del'] = [
                'class' => true,
                'style' => true,
                'aria-hidden' => true,
            ];
            $allowed['ins'] = [
                'class' => true,
                'style' => true,
            ];
            if ( ! isset( $allowed['span'] ) ) {
                $allowed['span'] = [];
            }
            $allowed['span']['class'] = true;
            $allowed['span']['style'] = true;
        }
        return $allowed;
    }

    /**
     * Shared label for discount rows (PHP + JS).
     *
     * @return string
     */
    private function get_bundle_discount_label() {
        return apply_filters( 'mmb_bundle_discount_label', __( 'Bundle Discount', 'mix-match-bundle' ) );
    }
    
    /**
     * Ensure WooCommerce session is available for AJAX requests
     */
    public function ensure_session() {
        if ( ! WC()->session && ! headers_sent() ) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
    }
    
    /**
     * Ensure session is available during AJAX fragment generation
     * This is critical for bundle discount data to be available
     */
    public function ensure_session_for_fragments( $fragments ) {
        // Ensure session is initialized
        $this->ensure_session();
        
        // Ensure cart totals are calculated with our discounts
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
            
            // Verify session data is available
            $session_data = WC()->session ? WC()->session->get( 'mmb_bundle_discount' ) : null;
        }
        
        return $fragments;
    }
    
    /**
     * Modify FunnelKit cart item price data after it's built
     * This is called for each cart item
     * 
     * @param array $item_data Cart item data from FunnelKit
     * @param array $cart_item Original cart item
     * @return array Modified item data
     */
    public function modify_fkcart_item_price( $item_data, $cart_item ) {
        if ( ! isset( $item_data['cart_item'] ) || ! WC()->cart || WC()->cart->is_empty() ) {
            return $item_data;
        }
        
        $actual_cart_item = $item_data['cart_item'];
        $discount_info = $this->get_bundle_discount_info( $actual_cart_item );
        
        if ( ! $discount_info ) {
            return $item_data;
        }
        
        $product_id = isset( $actual_cart_item['product_id'] ) ? $actual_cart_item['product_id'] : 0;
        // Modify the 'price' field which is what FunnelKit uses in the template
        if ( isset( $item_data['price'] ) ) {
            $original_subtotal = $discount_info['item_line_total'];
            $discounted_subtotal = max( 0, $original_subtotal - $discount_info['item_discount_amount'] );
            
            $original_subtotal_formatted = wc_price( $original_subtotal );
            $discounted_subtotal_formatted = wc_price( $discounted_subtotal );
            
            $discounted_html = sprintf(
                '<del aria-hidden="true">%s</del> <ins>%s</ins>',
                $original_subtotal_formatted,
                $discounted_subtotal_formatted
            );
            
            $item_data['price'] = $discounted_html;
        }
        
        return $item_data;
    }
    
    /**
     * Modify FunnelKit cart items after they're rendered
     * This is a fallback in case the filter doesn't work
     */
    public function modify_fkcart_items_after_render( $front ) {
        // This is called after items are rendered, so we can't modify them here
        // But we can use JavaScript to update the prices if needed
    }
    
    /**
     * Modify FunnelKit cart items prices after they're built
     * This ensures our discounted prices are used in the sidecart
     * 
     * @param array $items Cart items array from FunnelKit
     * @return array Modified items array
     */
    public function modify_fkcart_items_prices( $items ) {
        if ( ! is_array( $items ) || empty( $items ) || ! WC()->cart || WC()->cart->is_empty() ) {
            return $items;
        }
        
        foreach ( $items as $cart_item_key => $item_data ) {
            if ( ! isset( $item_data['cart_item'] ) ) {
                continue;
            }
            
            $actual_cart_item = $item_data['cart_item'];
            
            // Check if this is a bundle item
            $discount_info = $this->get_bundle_discount_info( $actual_cart_item );
            
            if ( ! $discount_info ) {
                continue;
            }
            
            $product_id = isset( $actual_cart_item['product_id'] ) ? $actual_cart_item['product_id'] : 0;
            
            // Modify price if it exists (this is what shows in the sidecart)
            if ( isset( $item_data['price'] ) ) {
                $original_subtotal = $discount_info['item_line_total'];
                $discounted_subtotal = max( 0, $original_subtotal - $discount_info['item_discount_amount'] );
                
                $original_subtotal_formatted = wc_price( $original_subtotal );
                $discounted_subtotal_formatted = wc_price( $discounted_subtotal );
                
                $discounted_html = sprintf(
                    '<del aria-hidden="true">%s</del> <ins>%s</ins>',
                    $original_subtotal_formatted,
                    $discounted_subtotal_formatted
                );
                
                $items[ $cart_item_key ]['price'] = $discounted_html;
            }
            
            // Also modify product_subtotal and product_price for consistency
            if ( isset( $item_data['product_subtotal'] ) ) {
                $original_subtotal = $discount_info['item_line_total'];
                $discounted_subtotal = max( 0, $original_subtotal - $discount_info['item_discount_amount'] );
                
                $original_subtotal_formatted = wc_price( $original_subtotal );
                $discounted_subtotal_formatted = wc_price( $discounted_subtotal );
                
                $items[ $cart_item_key ]['product_subtotal'] = sprintf(
                    '<del aria-hidden="true">%s</del> <ins>%s</ins>',
                    $original_subtotal_formatted,
                    $discounted_subtotal_formatted
                );
            }
            
            if ( isset( $item_data['product_price'] ) ) {
                $_product = $actual_cart_item['data'];
                $quantity = $actual_cart_item['quantity'];
                
                if ( WC()->cart->display_prices_including_tax() ) {
                    $original_price = wc_get_price_including_tax( $_product );
                } else {
                    $original_price = wc_get_price_excluding_tax( $_product );
                }
                
                $discount_per_item = $discount_info['item_discount_amount'] / $quantity;
                $discounted_price_per_item = max( 0, $original_price - $discount_per_item );
                
                $original_price_formatted = wc_price( $original_price );
                $discounted_price_formatted = wc_price( $discounted_price_per_item );
                
                $items[ $cart_item_key ]['product_price'] = sprintf(
                    '<del aria-hidden="true">%s</del> <ins>%s</ins>',
                    $original_price_formatted,
                    $discounted_price_formatted
                );
            }
        }
        
        return $items;
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
    
    /**
     * Ensure cart totals are calculated before sidecart renders
     */
    public function ensure_cart_totals_calculated() {
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            // Ensure fees are calculated
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
        }
    }
    
    /**
     * Ensure cart totals are calculated before get_items() is called
     * This is critical - get_items() builds the cart item array that's used in fragments
     */
    public function ensure_cart_totals_before_get_items() {
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            // Ensure session is available
            $this->ensure_session();
            
            // Force recalculation to ensure our filters run when get_items() calls get_cart_items()
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
            
            // Verify session data
            $session_data = WC()->session ? WC()->session->get( 'mmb_bundle_discount' ) : null;
        }
    }
    
    /**
     * Modify cart items for template rendering
     * This runs right before the template renders, so we can modify the items array
     */
    public function modify_cart_items_for_template( $front ) {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return;
        }
        // Get the items array - we'll modify it via a filter on the template data
        // The items are already built by get_items(), but we can modify them here
        // by using a filter that runs when the template accesses the price
    }
    
    /**
     * Filter the price output in the template
     * This runs when the template outputs the price
     */
    public function filter_price_output_in_template( $price, $cart_item ) {
        if ( ! isset( $cart_item['cart_item'] ) ) {
            return $price;
        }
        
        $discount_info = $this->get_bundle_discount_info( $cart_item['cart_item'] );
        
        if ( $discount_info ) {
            // Check if price already has our discount HTML
            if ( strpos( $price, 'mmb-original-price' ) === false && strpos( $price, 'mmb-discounted-price' ) === false ) {
                // Price doesn't have our discount HTML, let's add it
                $original_subtotal = $discount_info['item_line_total'];
                $discounted_subtotal = max( 0, $original_subtotal - $discount_info['item_discount_amount'] );
                
                $original_subtotal_formatted = wc_price( $original_subtotal );
                $discounted_subtotal_formatted = wc_price( $discounted_subtotal );
                
                $price = sprintf(
                    '<del class="mmb-original-price" style="text-decoration: line-through; opacity: 0.6; margin-right: 5px;">%s</del> <ins class="mmb-discounted-price" style="text-decoration: none; color: #4caf50; font-weight: bold;">%s</ins>',
                    $original_subtotal_formatted,
                    $discounted_subtotal_formatted
                );
            }
        }
        
        return $price;
    }
    
    /**
     * Ensure cart totals are calculated before cart items are rendered
     */
    public function ensure_cart_totals_before_render( $front ) {
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            // Force recalculation to ensure our filters run
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
        }
    }
    
    /**
     * Ensure cart totals are calculated before fragments are generated
     * This is critical - fragments are generated via AJAX and need our discounts
     */
    public function ensure_cart_totals_before_fragments( $fragments ) {
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            // Force recalculation to ensure our filters run when fragments are built
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
        }
        return $fragments;
    }

    /**
     * Ensure sidecart fragments already contain discounted markup so JS fallbacks are minimal.
     *
     * @param array $fragments
     * @return array
     */
    public function inject_bundle_prices_into_fragments( $fragments ) {
        if ( ! is_array( $fragments ) || empty( $fragments ) ) {
            return $fragments;
        }

        $payload   = $this->get_bundle_markup_map();
        $markup_map = isset( $payload['items'] ) ? $payload['items'] : [];
        $totals_formatted = isset( $payload['totals_formatted'] ) ? $payload['totals_formatted'] : [];
        $totals_raw       = isset( $payload['totals'] ) ? $payload['totals'] : [];

        if ( empty( $markup_map ) && empty( $totals_formatted ) ) {
            return $fragments;
        }

        foreach ( $fragments as $key => $html ) {
            if ( ! $this->fragment_contains_sidecart_markup( $key, $html ) ) {
                continue;
            }

            $fragments[ $key ] = $this->inject_markup_into_fragment_html( $html, $markup_map, $totals_formatted, $totals_raw );
        }

        return $fragments;
    }
    
    
    /**
     * Check if a cart item is part of a bundle
     * 
     * @param array $cart_item Cart item data
     * @return array|false Bundle session data if item is part of bundle, false otherwise
     */
    private function is_bundle_item( $cart_item ) {
        if ( ! WC()->session ) {
            return false;
        }
        
        $session_data = WC()->session->get( 'mmb_bundle_discount' );
        
        if ( ! $session_data || empty( $session_data['product_ids'] ) ) {
            return false;
        }
        
        $variation_ids = isset( $session_data['variation_ids'] ) ? $session_data['variation_ids'] : [];
        $product_id = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
        $variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
        
        // Check if it's a variation in the bundle
        if ( $variation_id && ! empty( $variation_ids ) && in_array( $variation_id, $variation_ids ) ) {
            return $session_data;
        }
        
        // Check if it's a simple product in the bundle
        if ( $product_id && in_array( $product_id, $session_data['product_ids'] ) ) {
            return $session_data;
        }
        
        return false;
    }
    
    /**
     * Get bundle discount info for a cart item
     * 
     * @param array $cart_item Cart item data
     * @return array|false Discount info or false
     */
    private function get_bundle_discount_info( $cart_item ) {
        if ( ! WC()->cart || ! is_array( $cart_item ) ) {
            return false;
        }
        
        $session_data = $this->is_bundle_item( $cart_item );
        
        if ( ! $session_data ) {
            return false;
        }
        
        $bundle_manager = new MMB_Bundle_Manager();
        $bundle = $bundle_manager->get_bundle( $session_data['bundle_id'] );
        
        if ( ! $bundle ) {
            return false;
        }
        
        // Get all bundle items in cart to calculate total discount
        $cart = WC()->cart;
        $bundle_items_count = 0;
        $bundle_subtotal = 0;
        $variation_ids = isset( $session_data['variation_ids'] ) ? $session_data['variation_ids'] : [];
        
        foreach ( $cart->get_cart() as $item ) {
            $is_bundle_item = false;
            
            if ( isset( $item['variation_id'] ) && $item['variation_id'] && in_array( $item['variation_id'], $variation_ids ) ) {
                $is_bundle_item = true;
            } elseif ( in_array( $item['product_id'], $session_data['product_ids'] ) ) {
                $is_bundle_item = true;
            }
            
            if ( $is_bundle_item ) {
                $bundle_items_count += $item['quantity'];
                $bundle_subtotal += isset( $item['line_total'] ) ? $item['line_total'] : 0;
            }
        }
        
        if ( $bundle_items_count === 0 || $bundle_subtotal <= 0 ) {
            return false;
        }
        
        // Get discount tier
        $tier = $bundle_manager->get_applicable_tier( $bundle, $bundle_items_count );
        
        if ( ! $tier || $tier['discount'] <= 0 ) {
            return false;
        }
        
        // Calculate total discount amount
        $total_discount_amount = ( $bundle_subtotal * $tier['discount'] ) / 100;
        
        // Calculate per-item discount ratio based on line total
        $item_line_total = isset( $cart_item['line_total'] ) ? $cart_item['line_total'] : 0;
        if ( $item_line_total <= 0 ) {
            return false;
        }
        
        $item_discount_ratio = $bundle_subtotal > 0 ? ( $item_line_total / $bundle_subtotal ) : 0;
        $item_discount_amount = $total_discount_amount * $item_discount_ratio;
        
        return [
            'discount_percentage' => $tier['discount'],
            'item_discount_amount' => $item_discount_amount,
            'item_line_total' => $item_line_total,
        ];
    }
    
    /**
     * Display bundle item price with original and discounted prices
     * 
     * @param string $price Formatted price
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified price HTML
     */
    public function display_bundle_item_price( $price, $cart_item, $cart_item_key ) {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return $price;
        }
        
        // Validate cart_item structure
        if ( ! is_array( $cart_item ) ) {
            return $price;
        }
        
        // Ensure cart totals are calculated
        if ( ! did_action( 'woocommerce_cart_calculate_fees' ) ) {
            WC()->cart->calculate_fees();
        }
        
        $markup = $this->get_discount_markup_for_cart_item( $cart_item );
        
        if ( ! $markup ) {
            return $price;
        }
        
        return $markup['price_html'];
    }
    
    /**
     * Display bundle item subtotal with original and discounted prices
     * 
     * @param string $subtotal Formatted subtotal
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     * @return string Modified subtotal HTML
     */
    public function display_bundle_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return $subtotal;
        }
        
        // Validate cart_item structure
        if ( ! is_array( $cart_item ) ) {
            return $subtotal;
        }
        
        // Ensure cart totals are calculated
        if ( ! did_action( 'woocommerce_cart_calculate_fees' ) ) {
            WC()->cart->calculate_fees();
        }
        
        $markup = $this->get_discount_markup_for_cart_item( $cart_item );
        
        if ( ! $markup ) {
            return $subtotal;
        }
        
        return $markup['subtotal_html'];
    }

    /**
     * Display bundle subtotal when WooCommerce core calls get_product_subtotal()
     *
     * @param string      $product_subtotal Formatted subtotal string.
     * @param WC_Product  $product          Product object.
     * @param int         $quantity         Quantity being purchased.
     * @param WC_Cart     $cart             Cart instance.
     *
     * @return string
     */
    public function display_bundle_product_subtotal( $product_subtotal, $product, $quantity, $cart ) {
        if ( ! WC()->cart || WC()->cart->is_empty() || ! $product instanceof WC_Product ) {
            return $product_subtotal;
        }

        $cart_item = [
            'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
            'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
            'data'         => $product,
            'quantity'     => $quantity,
            'line_total'   => $this->get_product_line_total( $product, $quantity ),
        ];

        $markup = $this->get_discount_markup_for_cart_item( $cart_item );

        if ( ! $markup ) {
            return $product_subtotal;
        }

        return $markup['subtotal_html'];
    }

    /**
     * Build markup for discounted price/subtotal display.
     *
     * @param array $cart_item
     *
     * @return array|false
     */
    private function get_discount_markup_for_cart_item( $cart_item ) {
        if ( ! is_array( $cart_item ) ) {
            return false;
        }

        $discount_info = $this->get_bundle_discount_info( $cart_item );

        if ( ! $discount_info ) {
            return false;
        }

        $quantity = isset( $cart_item['quantity'] ) ? max( 1, (int) $cart_item['quantity'] ) : 1;
        $original_subtotal   = isset( $discount_info['item_line_total'] ) ? (float) $discount_info['item_line_total'] : 0.0;
        $discounted_subtotal = max( 0, $original_subtotal - (float) $discount_info['item_discount_amount'] );

        $original_price = $this->calculate_cart_item_price( $cart_item, $quantity, $original_subtotal );
        $discount_per_item = $quantity > 0 ? (float) $discount_info['item_discount_amount'] / $quantity : 0;
        $discounted_price = max( 0, $original_price - $discount_per_item );

        return [
            'price_html'          => $this->format_discount_html( $original_price, $discounted_price ),
            'subtotal_html'       => $this->format_discount_html( $original_subtotal, $discounted_subtotal ),
            'original_price'      => $original_price,
            'discounted_price'    => $discounted_price,
            'original_subtotal'   => $original_subtotal,
            'discounted_subtotal' => $discounted_subtotal,
            'quantity'            => $quantity,
        ];
    }

    /**
     * Build a lookup map of cart item keys to markup arrays.
     *
     * @return array
     */
    private function get_bundle_markup_map() {
        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            return [
                'items'            => [],
                'totals'           => [],
                'totals_formatted' => [],
            ];
        }

        $items            = [];
        $original_total   = 0.0;
        $discounted_total = 0.0;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $line_total      = $this->get_cart_item_line_total( $cart_item );
            $original_total += $line_total;

            $markup = $this->get_discount_markup_for_cart_item( $cart_item );

            if ( $markup ) {
                $items[ trim( $cart_item_key ) ] = $markup;
                $discounted_total += isset( $markup['discounted_subtotal'] ) ? (float) $markup['discounted_subtotal'] : $line_total;
            } else {
                $discounted_total += $line_total;
            }
        }

        $original_total   = (float) wc_format_decimal( $original_total, wc_get_price_decimals() );
        $discounted_total = (float) wc_format_decimal( $discounted_total, wc_get_price_decimals() );
        $discount_amount  = max( 0, $original_total - $discounted_total );

        $totals = [
            'original'        => $original_total,
            'discounted'      => $discounted_total,
            'discount_amount' => $discount_amount,
        ];

        $totals_formatted = [
            'original'   => wc_price( $original_total ),
            'discounted' => wc_price( $discounted_total ),
            'discount'   => $discount_amount > 0 ? wc_price( $discount_amount * -1 ) : '',
        ];

        return [
            'items'            => $items,
            'totals'           => $totals,
            'totals_formatted' => $totals_formatted,
        ];
    }

    /**
     * Determine whether a fragment should be processed for price injection.
     *
     * @param string $fragment_key
     * @param string $html
     * @return bool
     */
    private function fragment_contains_sidecart_markup( $fragment_key, $html ) {
        if ( empty( $html ) || ! is_string( $html ) ) {
            return false;
        }

        if ( is_string( $fragment_key ) ) {
            if ( false !== strpos( $fragment_key, 'fkcart-modal-container' ) ) {
                return true;
            }

            if ( false !== strpos( $fragment_key, 'widget_shopping_cart_content' ) ) {
                return true;
            }
        }

        return ( false !== strpos( $html, 'fkcart-item-price' ) );
    }

    /**
     * Inject formatted markup into fragment HTML string.
     *
     * @param string $html
     * @param array  $markup_map
     *
     * @return string
     */
    private function inject_markup_into_fragment_html( $html, $markup_map, $totals_formatted = [], $totals_raw = [] ) {
        if ( empty( $markup_map ) || empty( $html ) || ! is_string( $html ) ) {
            if ( empty( $totals_formatted ) ) {
                return $html;
            }
        }

        if ( ! class_exists( 'DOMDocument' ) ) {
            return $html;
        }

        $wrapped_html = '<div id="mmb-fragment-wrapper">' . $html . '</div>';

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $encoding = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $wrapped_html, 'HTML-ENTITIES', 'UTF-8' ) : $wrapped_html;
        $dom->loadHTML( $encoding );
        libxml_clear_errors();

        $xpath      = new DOMXPath( $dom );
        $item_nodes = $xpath->query( '//*[@data-key]' );

        if ( $item_nodes && 0 !== $item_nodes->length ) {
            /** @var DOMElement $item_node */
            foreach ( $item_nodes as $item_node ) {
                $cart_key = trim( $item_node->getAttribute( 'data-key' ) );

                if ( '' === $cart_key || ! isset( $markup_map[ $cart_key ] ) ) {
                    continue;
                }

                $item_markup = $markup_map[ $cart_key ];

                if ( empty( $item_markup['price_html'] ) ) {
                    continue;
                }

                $price_nodes = $xpath->query( './/*[contains(@class,"fkcart-item-price")]', $item_node );

                if ( ! $price_nodes || 0 === $price_nodes->length ) {
                    continue;
                }

                /** @var DOMElement $price_node */
                $price_node = $price_nodes->item( 0 );

                $this->replace_dom_inner_html( $dom, $price_node, $item_markup['price_html'] );
            }
        }

        if ( ! empty( $totals_formatted ) ) {
            $this->update_summary_totals_in_dom( $dom, $xpath, $totals_formatted, $totals_raw );
        }

        $wrapper = $dom->getElementById( 'mmb-fragment-wrapper' );

        if ( ! $wrapper ) {
            return $html;
        }

        $updated_html = '';

        foreach ( $wrapper->childNodes as $child_node ) {
            $updated_html .= $dom->saveHTML( $child_node );
        }

        return $updated_html;
    }

    /**
     * Replace inner HTML of a DOM element helper.
     *
     * @param DOMDocument $dom
     * @param DOMElement  $node
     * @param string      $html
     */
    private function replace_dom_inner_html( $dom, $node, $html ) {
        if ( ! $node instanceof DOMElement ) {
            return;
        }

        while ( $node->firstChild ) {
            $node->removeChild( $node->firstChild );
        }

        if ( '' === $html ) {
            return;
        }

        $fragment = $dom->createDocumentFragment();

        if ( @$fragment->appendXML( $html ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            $node->appendChild( $fragment );
        }
    }

    /**
     * Update subtotal/discount rows inside the sidecart summary.
     *
     * @param DOMDocument $dom
     * @param DOMXPath    $xpath
     * @param array       $totals_formatted
     * @param array       $totals_raw
     */
    private function update_summary_totals_in_dom( $dom, $xpath, $totals_formatted, $totals_raw ) {
        $subtotal_value = isset( $totals_formatted['discounted'] ) ? $totals_formatted['discounted'] : '';

        if ( $subtotal_value ) {
            $subtotal_nodes = $xpath->query( '//*[contains(@class,"fkcart-subtotal-wrap")]//*[contains(@class,"fkcart-summary-amount")]' );
            if ( $subtotal_nodes && $subtotal_nodes->length ) {
                $this->replace_dom_inner_html( $dom, $subtotal_nodes->item( 0 ), '<strong>' . $subtotal_value . '</strong>' );
            }
        }

        $discount_amount = isset( $totals_raw['discount_amount'] ) ? (float) $totals_raw['discount_amount'] : 0.0;
        $discount_value  = isset( $totals_formatted['discount'] ) ? $totals_formatted['discount'] : '';
        $discount_rows   = $xpath->query( '//*[contains(@class,"mmb-bundle-discount")]' );

        if ( $discount_amount > 0 && $discount_value ) {
            if ( $discount_rows && $discount_rows->length ) {
                $amount_nodes = $xpath->query( './/*[contains(@class,"fkcart-summary-amount")]', $discount_rows->item( 0 ) );
                if ( $amount_nodes && $amount_nodes->length ) {
                    $this->replace_dom_inner_html( $dom, $amount_nodes->item( 0 ), $discount_value );
                }
            } else {
                $summary_containers = $xpath->query( '//*[contains(@class,"fkcart-order-summary-container")]' );
                if ( $summary_containers && $summary_containers->length ) {
                    $row_html = sprintf(
                        '<div class="fkcart-summary-line-item mmb-bundle-discount"><div class="fkcart-summary-text">%s</div><div class="fkcart-summary-amount">%s</div></div>',
                        esc_html( $this->get_bundle_discount_label() ),
                        $discount_value
                    );
                    $fragment = $dom->createDocumentFragment();
                    if ( @$fragment->appendXML( $row_html ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                        $summary_container = $summary_containers->item( 0 );
                        $subtotal_row      = $xpath->query( './/*[contains(@class,"fkcart-subtotal-wrap")]', $summary_container );
                        if ( $subtotal_row && $subtotal_row->length ) {
                            $subtotal_node = $subtotal_row->item( 0 );
                            if ( $subtotal_node->nextSibling ) {
                                $summary_container->insertBefore( $fragment, $subtotal_node->nextSibling );
                            } else {
                                $summary_container->appendChild( $fragment );
                            }
                        } else {
                            $summary_container->appendChild( $fragment );
                        }
                    }
                }
            }
        } elseif ( $discount_rows && $discount_rows->length ) {
            $discount_row = $discount_rows->item( 0 );
            $discount_row->parentNode->removeChild( $discount_row );
        }
    }

    /**
     * Format a pair of prices into HTML.
     *
     * @param float $original
     * @param float $discounted
     *
     * @return string
     */
    private function format_discount_html( $original, $discounted ) {
        return sprintf(
            '<span class="mmb-original-price">%s</span><span class="mmb-discounted-price">%s</span>',
            wc_price( $original ),
            wc_price( $discounted )
        );
    }

    /**
     * Determine the original per-item price for a cart item.
     *
     * @param array $cart_item
     * @param int   $quantity
     * @param float $fallback_subtotal
     *
     * @return float
     */
    private function calculate_cart_item_price( $cart_item, $quantity, $fallback_subtotal ) {
        if ( isset( $cart_item['data'] ) && $cart_item['data'] instanceof WC_Product ) {
            if ( WC()->cart && WC()->cart->display_prices_including_tax() ) {
                return (float) wc_get_price_including_tax( $cart_item['data'] );
            }

            return (float) wc_get_price_excluding_tax( $cart_item['data'] );
        }

        return $quantity > 0 ? (float) $fallback_subtotal / $quantity : 0.0;
    }

    /**
     * Calculate line total for helper cart item.
     *
     * @param WC_Product $product
     * @param int        $quantity
     *
     * @return float
     */
    private function get_product_line_total( $product, $quantity ) {
        if ( ! $product instanceof WC_Product ) {
            return 0;
        }

        if ( WC()->cart && WC()->cart->display_prices_including_tax() ) {
            $line_total = wc_get_price_including_tax( $product, [ 'qty' => $quantity ] );
        } else {
            $line_total = wc_get_price_excluding_tax( $product, [ 'qty' => $quantity ] );
        }

        return (float) $line_total;
    }

    /**
     * Get the display line total for a cart item respecting tax settings.
     *
     * @param array $cart_item
     * @return float
     */
    private function get_cart_item_line_total( $cart_item ) {
        if ( ! is_array( $cart_item ) ) {
            return 0.0;
        }

        $line_total = isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] : 0.0;

        if ( WC()->cart && WC()->cart->display_prices_including_tax() ) {
            $line_total += isset( $cart_item['line_tax'] ) ? (float) $cart_item['line_tax'] : 0.0;
        }

        return $line_total;
    }

    /**
     * AJAX: Return discounted price markup for items currently in the cart.
     */
    public function ajax_get_bundle_prices() {
        check_ajax_referer( 'mmb_frontend_nonce', 'nonce' );

        if ( ! WC()->cart || WC()->cart->is_empty() ) {
            wp_send_json_error( __( 'Cart is empty', 'mix-match-bundle' ) );
        }

        $payload = $this->get_bundle_markup_map();

        if ( empty( $payload['items'] ) && empty( $payload['totals'] ) ) {
            wp_send_json_error( __( 'No bundle items in cart', 'mix-match-bundle' ) );
        }

        wp_send_json_success( $payload );
    }
    
}

new MMB_Cart();
