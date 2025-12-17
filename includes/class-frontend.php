<?php
/**
 * Mix & Match Frontend Display
 */

class MMB_Frontend {
    
    public function __construct() {
        add_filter( 'woocommerce_product_get_price', [ $this, 'adjust_bundle_price' ], 10, 2 );
        add_filter( 'woocommerce_product_get_sale_price', [ $this, 'adjust_bundle_price' ], 10, 2 );
    }
    
    /**
     * Render bundle HTML
     */
    public static function render_bundle( $bundle ) {
        $bundle_manager = new MMB_Bundle_Manager();
        $products = [];
        
        foreach ( $bundle['product_ids'] as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( $product ) {
                $products[] = $product;
            }
        }
        
        ob_start();
        include MMB_PLUGIN_DIR . 'templates/bundle-display.php';
        return ob_get_clean();
    }
    
    /**
     * Get bundle discount display HTML
     */
    public static function render_discount_tiers( $tiers ) {
        ob_start();
        ?>
        <div class="mmb-discount-tiers">
            <div class="mmb-tier-header">
                <span class="mmb-tier-qty"><?php echo esc_html__( 'Quantity', 'bt-bundle-builder-for-wc' ); ?></span>
                <span class="mmb-tier-discount"><?php echo esc_html__( 'Discount', 'bt-bundle-builder-for-wc' ); ?></span>
            </div>
            <?php foreach ( $tiers as $tier ) : ?>
                <div class="mmb-tier-row">
                    <span class="mmb-tier-qty"><?php 
                        /* translators: %d: number of items */
                        echo esc_html( sprintf( __( '%d+ items', 'bt-bundle-builder-for-wc' ), intval( $tier['quantity'] ) ) ); 
                    ?></span>
                    <span class="mmb-tier-discount"><?php 
                        /* translators: %s: discount percentage */
                        echo esc_html( sprintf( __( '%s%% off', 'bt-bundle-builder-for-wc' ), floatval( $tier['discount'] ) ) ); 
                    ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function adjust_bundle_price( $price, $product ) {
        return $price;
    }
}

new MMB_Frontend();
