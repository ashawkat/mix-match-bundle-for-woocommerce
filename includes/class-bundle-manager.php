<?php
/**
 * Mix & Match Bundle Manager
 * Handles database operations and bundle logic
 */

class MMB_Bundle_Manager {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mmb_bundles';
    }
    
    /**
     * Save or update a bundle
     */
    public function save_bundle( $data ) {
        global $wpdb;
        
        $bundle_id = isset( $data['bundle_id'] ) ? intval( $data['bundle_id'] ) : 0;
        $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        
        // Handle product_ids - can be JSON string or array
        $product_ids = [];
        if ( isset( $data['product_ids'] ) ) {
            if ( is_string( $data['product_ids'] ) ) {
                $decoded = json_decode( $data['product_ids'], true );
                $product_ids = is_array( $decoded ) ? array_map( 'intval', $decoded ) : [];
            } else {
                $product_ids = array_map( 'intval', (array) $data['product_ids'] );
            }
        }
        
        $enabled = isset( $data['enabled'] ) ? intval( $data['enabled'] ) : 1;
        $use_quantity = isset( $data['use_quantity'] ) ? intval( $data['use_quantity'] ) : 0;
        $heading_text = isset( $data['heading_text'] ) ? sanitize_text_field( $data['heading_text'] ) : 'Select Your Products Below';
        $hint_text = isset( $data['hint_text'] ) ? sanitize_text_field( $data['hint_text'] ) : 'Bundle 2, 3, 4 or 5 items and watch the savings grow.';
        $primary_color = isset( $data['primary_color'] ) ? sanitize_hex_color( $data['primary_color'] ) : '#4caf50';
        $accent_color = isset( $data['accent_color'] ) ? sanitize_hex_color( $data['accent_color'] ) : '#45a049';
        $hover_bg_color = isset( $data['hover_bg_color'] ) ? sanitize_hex_color( $data['hover_bg_color'] ) : '#388e3c';
        $hover_accent_color = isset( $data['hover_accent_color'] ) ? sanitize_hex_color( $data['hover_accent_color'] ) : '#2e7d32';
        $button_text_color = isset( $data['button_text_color'] ) ? sanitize_hex_color( $data['button_text_color'] ) : '#ffffff';
        $button_text = isset( $data['button_text'] ) ? sanitize_text_field( $data['button_text'] ) : 'Add Bundle to Cart';
        $progress_text = isset( $data['progress_text'] ) ? sanitize_text_field( $data['progress_text'] ) : 'Your Savings Progress';
        $cart_behavior = isset( $data['cart_behavior'] ) ? sanitize_text_field( $data['cart_behavior'] ) : 'sidecart';
        $show_bundle_title = isset( $data['show_bundle_title'] ) ? intval( $data['show_bundle_title'] ) : 1;
        $show_bundle_description = isset( $data['show_bundle_description'] ) ? intval( $data['show_bundle_description'] ) : 1;
        $show_heading_text = isset( $data['show_heading_text'] ) ? intval( $data['show_heading_text'] ) : 1;
        $show_hint_text = isset( $data['show_hint_text'] ) ? intval( $data['show_hint_text'] ) : 1;
        $show_progress_text = isset( $data['show_progress_text'] ) ? intval( $data['show_progress_text'] ) : 1;
        
        // Handle discount_tiers - can be JSON string or array
        $discount_tiers = [];
        if ( isset( $data['discount_tiers'] ) ) {
            $tiers_data = $data['discount_tiers'];
            if ( is_string( $tiers_data ) ) {
                $tiers_data = json_decode( $tiers_data, true );
            }
            if ( is_array( $tiers_data ) ) {
                foreach ( $tiers_data as $tier ) {
                    if ( is_array( $tier ) && isset( $tier['quantity'] ) && isset( $tier['discount'] ) ) {
                        $discount_tiers[] = [
                            'quantity' => intval( $tier['quantity'] ),
                            'discount' => floatval( $tier['discount'] ),
                        ];
                    }
                }
            }
        }
        
        if ( ! $name ) {
            return false;
        }
        
        // Sort tiers by quantity
        if ( ! empty( $discount_tiers ) ) {
            usort( $discount_tiers, function( $a, $b ) {
                return $a['quantity'] - $b['quantity'];
            });
        }
        
        // Debug logging
        error_log( 'Saving bundle - Product IDs: ' . print_r( $product_ids, true ) );
        error_log( 'Saving bundle - Discount Tiers: ' . print_r( $discount_tiers, true ) );
        
        $bundle_data = [
            'name' => $name,
            'description' => $description,
            'enabled' => $enabled,
            'use_quantity' => $use_quantity,
            'product_ids' => wp_json_encode( $product_ids ),
            'discount_tiers' => wp_json_encode( $discount_tiers ),
            'heading_text' => $heading_text,
            'hint_text' => $hint_text,
            'primary_color' => $primary_color,
            'accent_color' => $accent_color,
            'hover_bg_color' => $hover_bg_color,
            'hover_accent_color' => $hover_accent_color,
            'button_text_color' => $button_text_color,
            'button_text' => $button_text,
            'progress_text' => $progress_text,
            'cart_behavior' => $cart_behavior,
            'show_bundle_title' => $show_bundle_title,
            'show_bundle_description' => $show_bundle_description,
            'show_heading_text' => $show_heading_text,
            'show_hint_text' => $show_hint_text,
            'show_progress_text' => $show_progress_text,
        ];
        
        if ( $bundle_id > 0 ) {
            // Update
            error_log( 'Updating bundle ID: ' . $bundle_id );
            error_log( 'Bundle data being saved: ' . print_r( $bundle_data, true ) );
            
            $result = $wpdb->update(
                $this->table,
                $bundle_data,
                [ 'id' => $bundle_id ],
                [ '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ],
                [ '%d' ]
            );
            
            error_log( 'Update result: ' . print_r( $result, true ) );
            error_log( 'Last DB error: ' . $wpdb->last_error );
            
            // Verify what was saved
            $saved = $wpdb->get_row( $wpdb->prepare( "SELECT discount_tiers FROM {$this->table} WHERE id = %d", $bundle_id ) );
            error_log( 'Verified saved discount_tiers: ' . $saved->discount_tiers );
            
            return $bundle_id;
        } else {
            // Insert
            error_log( 'Inserting new bundle' );
            error_log( 'Bundle data being saved: ' . print_r( $bundle_data, true ) );
            
            $result = $wpdb->insert(
                $this->table,
                $bundle_data,
                [ '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ]
            );
            
            error_log( 'Insert result: ' . print_r( $result, true ) );
            error_log( 'Last DB error: ' . $wpdb->last_error );
            error_log( 'Insert ID: ' . $wpdb->insert_id );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Get all bundles
     */
    public function get_all_bundles() {
        global $wpdb;
        
        $bundles = $wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY id DESC"
        );
        
        $formatted = [];
        foreach ( $bundles as $bundle ) {
            $formatted[] = $this->format_bundle( $bundle );
        }
        
        return $formatted;
    }
    
    /**
     * Get single bundle
     */
    public function get_bundle( $bundle_id ) {
        global $wpdb;
        
        $bundle = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $bundle_id )
        );
        
        if ( ! $bundle ) {
            return null;
        }
        
        return $this->format_bundle( $bundle );
    }
    
    /**
     * Format bundle data
     */
    private function format_bundle( $bundle ) {
        // Debug logging
        error_log( 'Formatting bundle ' . $bundle->id );
        error_log( 'Raw product_ids from DB: ' . $bundle->product_ids );
        error_log( 'Raw discount_tiers from DB: ' . $bundle->discount_tiers );
        
        $decoded_tiers = json_decode( $bundle->discount_tiers, true );
        error_log( 'Decoded discount_tiers: ' . print_r( $decoded_tiers, true ) );
        
        return [
            'id' => intval( $bundle->id ),
            'name' => $bundle->name,
            'description' => $bundle->description,
            'enabled' => intval( $bundle->enabled ),
            'use_quantity' => isset( $bundle->use_quantity ) ? intval( $bundle->use_quantity ) : 0,
            'product_ids' => json_decode( $bundle->product_ids, true ) ?: [],
            'discount_tiers' => $decoded_tiers ?: [],
            'heading_text' => isset( $bundle->heading_text ) ? $bundle->heading_text : 'Select Your Products Below',
            'hint_text' => isset( $bundle->hint_text ) ? $bundle->hint_text : 'Bundle 2, 3, 4 or 5 items and watch the savings grow.',
            'primary_color' => isset( $bundle->primary_color ) ? $bundle->primary_color : '#4caf50',
            'accent_color' => isset( $bundle->accent_color ) ? $bundle->accent_color : '#45a049',
            'hover_bg_color' => isset( $bundle->hover_bg_color ) ? $bundle->hover_bg_color : '#388e3c',
            'hover_accent_color' => isset( $bundle->hover_accent_color ) ? $bundle->hover_accent_color : '#2e7d32',
            'button_text_color' => isset( $bundle->button_text_color ) ? $bundle->button_text_color : '#ffffff',
            'button_text' => isset( $bundle->button_text ) ? $bundle->button_text : 'Add Bundle to Cart',
            'progress_text' => isset( $bundle->progress_text ) ? $bundle->progress_text : 'Your Savings Progress',
            'cart_behavior' => isset( $bundle->cart_behavior ) ? $bundle->cart_behavior : 'sidecart',
            'show_bundle_title' => isset( $bundle->show_bundle_title ) ? intval( $bundle->show_bundle_title ) : 1,
            'show_bundle_description' => isset( $bundle->show_bundle_description ) ? intval( $bundle->show_bundle_description ) : 1,
            'show_heading_text' => isset( $bundle->show_heading_text ) ? intval( $bundle->show_heading_text ) : 1,
            'show_hint_text' => isset( $bundle->show_hint_text ) ? intval( $bundle->show_hint_text ) : 1,
            'show_progress_text' => isset( $bundle->show_progress_text ) ? intval( $bundle->show_progress_text ) : 1,
            'created_at' => $bundle->created_at,
            'updated_at' => $bundle->updated_at,
        ];
    }
    
    /**
     * Delete a bundle
     */
    public function delete_bundle( $bundle_id ) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table,
            [ 'id' => intval( $bundle_id ) ],
            [ '%d' ]
        );
    }
    
    /**
     * Get applicable discount tier
     */
    public function get_applicable_tier( $bundle, $item_count ) {
        $tiers = $bundle['discount_tiers'];
        
        if ( empty( $tiers ) ) {
            return [ 'quantity' => 1, 'discount' => 0 ];
        }
        
        // Sort tiers in reverse to get the highest applicable tier
        usort( $tiers, function( $a, $b ) {
            return $b['quantity'] - $a['quantity'];
        });
        
        foreach ( $tiers as $tier ) {
            if ( $item_count >= $tier['quantity'] ) {
                return $tier;
            }
        }
        
        return [ 'quantity' => 1, 'discount' => 0 ];
    }
    
    /**
     * Get bundles for frontend
     */
    public function get_enabled_bundles() {
        global $wpdb;
        
        $bundles = $wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE enabled = 1 ORDER BY id DESC"
        );
        
        $formatted = [];
        foreach ( $bundles as $bundle ) {
            $formatted[] = $this->format_bundle( $bundle );
        }
        
        return $formatted;
    }
}
