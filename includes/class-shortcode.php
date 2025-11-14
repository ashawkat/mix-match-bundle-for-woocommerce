<?php
/**
 * Mix & Match Shortcode
 */

class MMB_Shortcode {
    
    public function __construct() {
        add_shortcode( 'mmb_bundle', [ $this, 'render_bundle_shortcode' ] );
    }
    
    public function render_bundle_shortcode( $atts ) {
        $atts = shortcode_atts( [
            'id' => 0,
        ], $atts, 'mmb_bundle' );
        
        $bundle_manager = new MMB_Bundle_Manager();
        $bundle = $bundle_manager->get_bundle( intval( $atts['id'] ) );
        
        if ( ! $bundle ) {
            return '<p>' . esc_html__( 'Bundle not found.', 'mix-match-bundle' ) . '</p>';
        }
        
        return MMB_Frontend::render_bundle( $bundle );
    }
}

new MMB_Shortcode();
