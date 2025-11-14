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
        register_setting( 'mmb_settings', $this->options_key );
    }
    
    public function get_settings() {
        return get_option( $this->options_key, $this->get_defaults() );
    }
    
    public function update_settings( $settings ) {
        return update_option( $this->options_key, $settings );
    }
    
    private function get_defaults() {
        return [
            'show_coupon_warning' => true,
            'exclude_categories' => [],
        ];
    }
}

new MMB_Settings();
