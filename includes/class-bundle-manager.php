<?php
/**
 * Mix & Match Bundle Manager
 * Handles database operations and bundle logic
 */

class MMB_Bundle_Manager {
    
    private $table;
    
    public function __construct() {
        if ( function_exists( 'mmb_get_table_name' ) ) {
            $this->table = mmb_get_table_name();
        } else {
            global $wpdb;
            $this->table = $wpdb->prefix . 'mmb_bundles';
        }
    }
    
    /**
     * Ensure the database table exists
     * 
     * @return bool True if table exists or was created, false otherwise
     */
    private function ensure_table_exists() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $previous_error = $wpdb->last_error;
        dbDelta( mmb_get_table_schema_sql() );

        return empty( $wpdb->last_error ) || $wpdb->last_error === $previous_error;
    }
    
    /**
     * Save or update a bundle
     */
    public function save_bundle( $data ) {
        global $wpdb;
        
        
        $bundle_id = isset( $data['bundle_id'] ) ? intval( $data['bundle_id'] ) : 0;
        $name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        
        // Validate name first
        if ( ! $name || empty( trim( $name ) ) ) {
            $wpdb->last_error = 'Bundle name is required';
            return false;
        }
        
        // Ensure table exists
        if ( ! $this->ensure_table_exists() ) {
            $wpdb->last_error = 'Database table does not exist and could not be created. Please check database permissions.';
            return false;
        }
        
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
        $max_quantity = isset( $data['max_quantity'] ) ? max( 1, intval( $data['max_quantity'] ) ) : 10;
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
        // Validate discount tiers
        if ( empty( $discount_tiers ) ) {
            $wpdb->last_error = 'At least one discount tier is required';
            return false;
        }
        
        // Sort tiers by quantity
        if ( ! empty( $discount_tiers ) ) {
            usort( $discount_tiers, function( $a, $b ) {
                return $a['quantity'] - $b['quantity'];
            });
        }
        
        $bundle_data = [
            'name' => $name,
            'description' => $description,
            'enabled' => $enabled,
            'use_quantity' => $use_quantity,
            'max_quantity' => $max_quantity,
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
            $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $this->table,
                $bundle_data,
                [ 'id' => $bundle_id ],
                [ '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ],
                [ '%d' ]
            );
            
            // Check for errors (false means error, 0 or positive number means success)
            if ( $result === false ) {
                if ( empty( $wpdb->last_error ) ) {
                    $wpdb->last_error = 'Database update failed';
                }
                return false;
            }
            
            wp_cache_delete( 'mmb_bundle_' . $bundle_id, 'mix_match_bundle' );
            wp_cache_delete( 'mmb_all_bundles', 'mix_match_bundle' );
            wp_cache_delete( 'mmb_enabled_bundles', 'mix_match_bundle' );
            return $bundle_id;
        } else {
            // Insert new bundle
            $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $this->table,
                $bundle_data,
                [ '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d' ]
            );
            
            // Check for errors (false means error)
            if ( $result === false ) {
                if ( empty( $wpdb->last_error ) ) {
                    $wpdb->last_error = 'Database insert failed';
                }
                return false;
            }
            
            $insert_id = $wpdb->insert_id;
            
            // Check if insert_id is valid
            if ( ! $insert_id || $insert_id <= 0 ) {
                $wpdb->last_error = 'Database insert succeeded but no ID was generated';
                return false;
            }
            
            wp_cache_delete( 'mmb_all_bundles', 'mix_match_bundle' );
            wp_cache_delete( 'mmb_enabled_bundles', 'mix_match_bundle' );
            return $insert_id;
        }
    }
    
    /**
     * Get all bundles
     */
    public function get_all_bundles() {
        global $wpdb;
        
        $cache_key = 'mmb_all_bundles';
        $cache_group = 'mix_match_bundle';
        
        $cached = wp_cache_get( $cache_key, $cache_group );
        if ( false !== $cached ) {
            return $cached;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $bundles = $wpdb->get_results(
            /* phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber */
            $wpdb->prepare(
                sprintf(
                    'SELECT * FROM %s WHERE 1 = %%d ORDER BY id DESC',
                    esc_sql( mmb_get_table_name() )
                ),
                1
            )
        );
        
        $formatted = [];
        foreach ( $bundles as $bundle ) {
            $formatted[] = $this->format_bundle( $bundle );
        }
        
        wp_cache_set( $cache_key, $formatted, $cache_group, MINUTE_IN_SECONDS * 5 );
        return $formatted;
    }
    
    /**
     * Get single bundle
     */
    public function get_bundle( $bundle_id ) {
        global $wpdb;
        
        $cache_key = 'mmb_bundle_' . $bundle_id;
        $cache_group = 'mix_match_bundle';
        
        $cached = wp_cache_get( $cache_key, $cache_group );
        if ( false !== $cached ) {
            return $cached;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $bundle = $wpdb->get_row(
            /* phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber */
            $wpdb->prepare(
                sprintf(
                    'SELECT * FROM %s WHERE id = %%d',
                    esc_sql( mmb_get_table_name() )
                ),
                $bundle_id
            )
        );
        
        if ( ! $bundle ) {
            return null;
        }
        
        $formatted = $this->format_bundle( $bundle );
        wp_cache_set( $cache_key, $formatted, $cache_group, MINUTE_IN_SECONDS * 5 );
        return $formatted;
    }
    
    /**
     * Format bundle data
     */
    private function format_bundle( $bundle ) {
        $decoded_tiers = json_decode( $bundle->discount_tiers, true );
        
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
        
        $table = esc_sql( $this->table );
        $result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $table,
            [ 'id' => intval( $bundle_id ) ],
            [ '%d' ]
        );
        
        if ( false !== $result ) {
            wp_cache_delete( 'mmb_bundle_' . $bundle_id, 'mix_match_bundle' );
            wp_cache_delete( 'mmb_all_bundles', 'mix_match_bundle' );
            wp_cache_delete( 'mmb_enabled_bundles', 'mix_match_bundle' );
        }
        
        return $result;
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
        
        $cache_key = 'mmb_enabled_bundles';
        $cache_group = 'mix_match_bundle';
        
        $cached = wp_cache_get( $cache_key, $cache_group );
        if ( false !== $cached ) {
            return $cached;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $bundles = $wpdb->get_results(
            /* phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber */
            $wpdb->prepare(
                sprintf(
                    'SELECT * FROM %s WHERE enabled = %%d ORDER BY id DESC',
                    esc_sql( mmb_get_table_name() )
                ),
                1
            )
        );
        
        $formatted = [];
        foreach ( $bundles as $bundle ) {
            $formatted[] = $this->format_bundle( $bundle );
        }
        
        wp_cache_set( $cache_key, $formatted, $cache_group, MINUTE_IN_SECONDS * 5 );
        return $formatted;
    }
}
