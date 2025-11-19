<?php
/**
 * Mix & Match Settings
 */

class MMB_Settings {
    
    private $options_key = 'mmb_settings';
    
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }
    
    public function register_settings() {
        register_setting(
            'mmb_settings',
            $this->options_key,
            [
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'          => $this->get_defaults(),
            ]
        );
    }
    
    public function get_settings() {
        return get_option( $this->options_key, $this->get_defaults() );
    }
    
    public function update_settings( $settings ) {
        return update_option( $this->options_key, $settings );
    }
    
    /**
     * Sanitize settings before saving.
     *
     * @param array $settings Raw settings.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $settings ) {
        $defaults = $this->get_defaults();
        $clean    = $defaults;
        
        if ( isset( $settings['show_coupon_warning'] ) ) {
            $clean['show_coupon_warning'] = (bool) $settings['show_coupon_warning'];
        }
        
        if ( isset( $settings['exclude_categories'] ) ) {
            $categories = $settings['exclude_categories'];
            if ( ! is_array( $categories ) ) {
                $categories = explode( ',', (string) $categories );
            }
            $clean['exclude_categories'] = array_filter( array_map( 'absint', $categories ) );
        }
        
        /**
         * Filter sanitized settings before saving.
         *
         * @param array $clean Sanitized settings.
         * @param array $settings Raw settings.
         */
        return apply_filters( 'mmb_sanitize_settings', $clean, $settings );
    }
    
    private function get_defaults() {
        return [
            'show_coupon_warning' => true,
            'exclude_categories' => [],
        ];
    }
}

new MMB_Settings();
