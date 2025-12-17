<?php
/**
 * Bundle Display Template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="mmb-bundle-wrapper" 
     data-bundle-id="<?php echo intval( $bundle['id'] ); ?>" 
     data-cart-behavior="<?php echo esc_attr( $bundle['cart_behavior'] ?? 'sidecart' ); ?>"
     data-primary-color="<?php echo esc_attr( $bundle['primary_color'] ?? '#4caf50' ); ?>"
     data-accent-color="<?php echo esc_attr( $bundle['accent_color'] ?? '#45a049' ); ?>"
     data-hover-bg-color="<?php echo esc_attr( $bundle['hover_bg_color'] ?? '#388e3c' ); ?>"
     data-hover-accent-color="<?php echo esc_attr( $bundle['hover_accent_color'] ?? '#2e7d32' ); ?>"
     data-button-text-color="<?php echo esc_attr( $bundle['button_text_color'] ?? '#ffffff' ); ?>">
    <?php if ( ! empty( $bundle['show_bundle_title'] ) || ! empty( $bundle['show_bundle_description'] ) ) : ?>
    <div class="mmb-bundle-header">
        <?php if ( ! empty( $bundle['show_bundle_title'] ) ) : ?>
            <h2><?php echo esc_html( $bundle['name'] ); ?></h2>
        <?php endif; ?>
        <?php if ( ! empty( $bundle['show_bundle_description'] ) && ! empty( $bundle['description'] ) ) : ?>
            <p class="mmb-subtitle"><?php echo esc_html( $bundle['description'] ); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="mmb-bundle-content">
        <div class="mmb-products-section">
            <?php if ( ! empty( $bundle['show_heading_text'] ) ) : ?>
                <h3><?php echo esc_html( $bundle['heading_text'] ?: __( 'Select Your Products Below', 'bt-bundle-builder-for-wc' ) ); ?></h3>
            <?php endif; ?>
            <?php if ( ! empty( $bundle['show_hint_text'] ) && ! empty( $bundle['hint_text'] ) ) : ?>
                <p class="mmb-bundle-hint"><?php echo esc_html( $bundle['hint_text'] ); ?></p>
            <?php endif; ?>
            
            <!-- Savings Progress Section - Mobile Only (before products) -->
            <div class="mmb-discount-tiers-simple mmb-mobile-only-tiers" data-primary-color="<?php echo esc_attr( $bundle['primary_color'] ); ?>">
                <?php if ( ! empty( $bundle['show_progress_text'] ) ) : ?>
                    <h3><?php echo esc_html( $bundle['progress_text'] ?: __( 'Your Savings Progress', 'bt-bundle-builder-for-wc' ) ); ?></h3>
                <?php endif; ?>
                <div class="mmb-tiers-list">
                    <?php foreach ( $bundle['discount_tiers'] as $index => $tier ) : ?>
                        <div class="mmb-tier-item" data-quantity="<?php echo intval( $tier['quantity'] ); ?>" data-discount="<?php echo floatval( $tier['discount'] ); ?>">
                            <div class="mmb-tier-check">
                                <span class="mmb-check-icon">✓</span>
                            </div>
                            <div class="mmb-tier-info">
                                <span class="mmb-tier-text">
                                    <?php 
                                    /* translators: 1: quantity, 2: discount percentage */
                                    echo esc_html( sprintf( __( 'Buy %1$d items', 'bt-bundle-builder-for-wc' ), intval( $tier['quantity'] ) ) ); 
                                    ?>
                                </span>
                                <span class="mmb-tier-discount"><?php echo floatval( $tier['discount'] ); ?>% <?php echo esc_html__( 'OFF', 'bt-bundle-builder-for-wc' ); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mmb-products-grid">
                <?php foreach ( $products as $product ) :
                    $is_variable = $product->is_type( 'variable' );
                    $variations = $is_variable ? $product->get_available_variations() : [];
                ?>
                    <div class="mmb-product-card" 
                         data-product-id="<?php echo intval( $product->get_id() ); ?>" 
                         data-is-variable="<?php echo esc_attr( $is_variable ? '1' : '0' ); ?>"
                         data-price="<?php echo esc_attr( $product->get_price() ); ?>">
                        <div class="mmb-product-image">
                            <?php echo wp_kses_post( $product->get_image( 'medium' ) ); ?>
                        </div>
                        <div class="mmb-product-info">
                            <h4><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" target="_blank" class="mmb-product-link"><?php echo esc_html( $product->get_name() ); ?></a></h4>
                            <p class="mmb-product-price" data-base-price="<?php echo esc_attr( $product->get_price() ); ?>">
                                <?php 
                                $price_html = '';
                                if ( $is_variable ) {
                                    $min_price = $product->get_variation_price( 'min' );
                                    $max_price = $product->get_variation_price( 'max' );
                                    if ( $min_price !== $max_price ) {
                                        $price_html = wc_price( $min_price ) . ' - ' . wc_price( $max_price );
                                    } else {
                                        $price_html = wc_price( $min_price );
                                    }
                                } else {
                                    $price_html = wc_price( $product->get_price() );
                                }
                                echo wp_kses_post( $price_html );
                                ?>
                            </p>
                            
                            <?php if ( $is_variable ) : ?>
                                <div class="mmb-variation-select">
                                    <label for="mmb-variation-<?php echo intval( $product->get_id() ); ?>" class="mmb-variation-label"><?php echo esc_html__( 'Select an option', 'bt-bundle-builder-for-wc' ); ?></label>
                                    <select class="mmb-variation-dropdown" id="mmb-variation-<?php echo intval( $product->get_id() ); ?>" data-product-id="<?php echo intval( $product->get_id() ); ?>">
                                        <option value=""><?php echo esc_html__( 'Select an option', 'bt-bundle-builder-for-wc' ); ?></option>
                                        <?php foreach ( $variations as $variation ) : 
                                            $variation_obj = wc_get_product( $variation['variation_id'] );
                                            if ( ! $variation_obj || ! $variation_obj->is_in_stock() ) continue;
                                            
                                            $attributes_text = [];
                                            foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
                                                $attr_label = wc_attribute_label( str_replace( 'attribute_', '', $attr_name ) );
                                                $attributes_text[] = $attr_label . ': ' . $attr_value;
                                            }
                                            $variation_name = implode( ', ', $attributes_text );
                                        ?>
                                            <option 
                                                value="<?php echo esc_attr( $variation['variation_id'] ); ?>" 
                                                data-price="<?php echo esc_attr( $variation_obj->get_price() ); ?>"
                                                data-image="<?php echo esc_url( isset( $variation['image']['url'] ) ? $variation['image']['url'] : '' ); ?>">
                                                <?php echo esc_html( $variation_name ); ?> - <?php echo wp_kses_post( wc_price( $variation_obj->get_price() ) ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ( $bundle['use_quantity'] ) : 
                                $max_quantity = isset( $bundle['max_quantity'] ) ? intval( $bundle['max_quantity'] ) : 10;
                            ?>
                                <div class="mmb-product-quantity">
                                    <div class="mmb-quantity-controls" role="group" aria-label="<?php echo esc_attr__( 'Product quantity controls', 'bt-bundle-builder-for-wc' ); ?>">
                                        <button type="button" class="mmb-qty-btn mmb-qty-minus" data-product-id="<?php echo intval( $product->get_id() ); ?>" <?php echo $is_variable ? 'disabled' : ''; ?> aria-label="<?php echo esc_attr__( 'Decrease quantity', 'bt-bundle-builder-for-wc' ); ?>" tabindex="0">
                                            <span>−</span>
                                        </button>
                                        <input type="number" min="0" max="<?php echo esc_attr( $max_quantity ); ?>" value="0" class="mmb-product-qty-input" 
                                               data-product-id="<?php echo intval( $product->get_id() ); ?>"
                                               <?php echo $is_variable ? 'disabled' : ''; ?> readonly aria-label="<?php echo esc_attr__( 'Quantity', 'bt-bundle-builder-for-wc' ); ?>" tabindex="0">
                                        <button type="button" class="mmb-qty-btn mmb-qty-plus" data-product-id="<?php echo intval( $product->get_id() ); ?>" <?php echo $is_variable ? 'disabled' : ''; ?> aria-label="<?php echo esc_attr__( 'Increase quantity', 'bt-bundle-builder-for-wc' ); ?>" tabindex="0">
                                            <span>+</span>
                                        </button>
                                    </div>
                                </div>
                            <?php else : ?>
                                <label class="mmb-product-checkbox">
                                    <input type="checkbox" value="<?php echo intval( $product->get_id() ); ?>" 
                                           class="mmb-product-select"
                                           <?php echo $is_variable ? 'disabled' : ''; ?>>
                                    <span><?php echo esc_html__( 'Select', 'bt-bundle-builder-for-wc' ); ?></span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mmb-bundle-sidebar">
            <!-- Simplified Discount Tiers -->
            <div class="mmb-discount-tiers-simple" data-primary-color="<?php echo esc_attr( $bundle['primary_color'] ); ?>">
                <?php if ( ! empty( $bundle['show_progress_text'] ) ) : ?>
                    <h3><?php echo esc_html( $bundle['progress_text'] ?: __( 'Your Savings Progress', 'bt-bundle-builder-for-wc' ) ); ?></h3>
                <?php endif; ?>
                <div class="mmb-tiers-list">
                    <?php foreach ( $bundle['discount_tiers'] as $index => $tier ) : ?>
                        <div class="mmb-tier-item" data-quantity="<?php echo intval( $tier['quantity'] ); ?>" data-discount="<?php echo floatval( $tier['discount'] ); ?>">
                            <div class="mmb-tier-check">
                                <span class="mmb-check-icon">✓</span>
                            </div>
                            <div class="mmb-tier-info">
                                <span class="mmb-tier-text">
                                    <?php 
                                    /* translators: 1: quantity, 2: discount percentage */
                                    echo esc_html( sprintf( __( 'Buy %1$d items', 'bt-bundle-builder-for-wc' ), intval( $tier['quantity'] ) ) ); 
                                    ?>
                                </span>
                                <span class="mmb-tier-discount"><?php echo floatval( $tier['discount'] ); ?>% <?php echo esc_html__( 'OFF', 'bt-bundle-builder-for-wc' ); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mmb-bundle-summary" role="region" aria-labelledby="bundle-summary-title">
                <h3 id="bundle-summary-title"><?php echo esc_html__( 'My Bundle', 'bt-bundle-builder-for-wc' ); ?> <span class="mmb-item-count">0 / 10</span></h3>
                
                <div id="mmb-bundle-items" class="mmb-selected-items">
                    <p class="mmb-empty-state"><?php echo esc_html__( 'Select products to get started', 'bt-bundle-builder-for-wc' ); ?></p>
                </div>
                
                <div class="mmb-price-breakdown">
                    <div class="mmb-price-row">
                        <span><?php echo esc_html__( 'Subtotal', 'bt-bundle-builder-for-wc' ); ?></span>
                        <span class="mmb-price" id="mmb-subtotal">$0.00</span>
                    </div>
                    <div class="mmb-price-row">
                        <span><?php echo esc_html__( 'Discount', 'bt-bundle-builder-for-wc' ); ?></span>
                        <span class="mmb-discount" id="mmb-discount">-$0.00</span>
                    </div>
                    <div class="mmb-price-row mmb-total">
                        <span><?php echo esc_html__( 'Total', 'bt-bundle-builder-for-wc' ); ?></span>
                        <span class="mmb-price" id="mmb-total"><?php echo wp_kses_post( wc_price( 0 ) ); ?></span>
                    </div>
                </div>
                
                <button id="mmb-add-to-cart" class="button button-primary mmb-add-to-cart" disabled data-primary-color="<?php echo esc_attr( $bundle['primary_color'] ); ?>" aria-describedby="mmb-total">
                    <?php echo esc_html( $bundle['button_text'] ?: __( 'Add Bundle to Cart', 'bt-bundle-builder-for-wc' ) ); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Sticky Cart Footer -->
    <div class="mmb-mobile-sticky-cart" data-primary-color="<?php echo esc_attr( $bundle['primary_color'] ); ?>">
        <!-- Mobile Discount Badge -->
        <div class="mmb-mobile-discount-badge" id="mmb-mobile-discount-badge">
            <span class="mmb-mobile-badge-icon">🎯</span>
            <span class="mmb-mobile-badge-text">
                <?php 
                // Show first tier message if available
                if ( ! empty( $bundle['discount_tiers'] ) ) {
                    $first_tier = $bundle['discount_tiers'][0];
                    $tier_qty = intval( $first_tier['quantity'] );
                    $tier_discount = floatval( $first_tier['discount'] );
                    /* translators: 1: quantity required, 2: pluralized label, 3: discount percentage */
                    $first_tier_text = __( 'Add %1$d %2$s to get %3$s%% OFF', 'bt-bundle-builder-for-wc' );
                    echo esc_html(
                        sprintf(
                            $first_tier_text,
                            $tier_qty,
                            _n( 'item', 'items', $tier_qty, 'bt-bundle-builder-for-wc' ),
                            $tier_discount
                        )
                    );
                } else {
                    echo esc_html__( 'Select items to see your discount', 'bt-bundle-builder-for-wc' );
                }
                ?>
            </span>
        </div>
        
        <div class="mmb-mobile-cart-content">
            <div class="mmb-mobile-cart-info">
                <div class="mmb-mobile-cart-details">
                    <span class="mmb-mobile-sticky-items">0 <?php echo esc_html__( 'items', 'bt-bundle-builder-for-wc' ); ?></span>
                    <span class="mmb-mobile-sticky-total">$0.00</span>
                </div>
                <div class="mmb-mobile-cart-discount">
                    <span class="mmb-mobile-discount-text"><?php echo esc_html__( 'Save', 'bt-bundle-builder-for-wc' ); ?>: </span>
                    <span class="mmb-mobile-discount-amount" id="mmb-mobile-discount">$0.00</span>
                </div>
            </div>
            <button class="mmb-mobile-add-to-cart" id="mmb-mobile-add-cart" disabled data-primary-color="<?php echo esc_attr( $bundle['primary_color'] ); ?>">
                <?php echo esc_html( $bundle['button_text'] ?: __( 'Add to Cart', 'bt-bundle-builder-for-wc' ) ); ?>
            </button>
        </div>
    </div>
</div>
