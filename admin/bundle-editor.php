<?php
/**
 * Admin Bundle Editor Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check database status
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$table_name   = function_exists( 'mmb_get_table_name' ) ? mmb_get_table_name() : $wpdb->prefix . 'mmb_bundles';
$previous_err = $wpdb->last_error;
dbDelta( mmb_get_table_schema_sql() );
$table_exists = empty( $wpdb->last_error ) || $wpdb->last_error === $previous_err;

// Handle manual setup request
if ( isset( $_GET['mmb_setup_db'] ) && check_admin_referer( 'mmb_setup_db' ) ) {
    $plugin_instance = Mix_Match_Bundle::get_instance();
    $previous_err    = $wpdb->last_error;
    $plugin_instance->activate_plugin();
    
    $had_error   = ! empty( $wpdb->last_error ) && $wpdb->last_error !== $previous_err;
    $table_exists = ! $had_error;
    
    if ( ! $had_error ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Database table created successfully!', 'bt-bundle-builder-for-wc' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to create database table. Please check your database permissions.', 'bt-bundle-builder-for-wc' ) . '</p></div>';
    }
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'Mix & Match Bundles', 'bt-bundle-builder-for-wc' ); ?></h1>
    
    <?php if ( ! $table_exists ) : ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php echo esc_html__( 'Database Setup Required', 'bt-bundle-builder-for-wc' ); ?></strong><br>
            <?php echo esc_html__( 'The plugin database table does not exist. This usually happens on fresh installations.', 'bt-bundle-builder-for-wc' ); ?>
        </p>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mmb_setup_db', '1' ), 'mmb_setup_db' ) ); ?>" class="button button-primary">
                <?php echo esc_html__( 'Setup Database Now', 'bt-bundle-builder-for-wc' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="mmb-admin-container">
        <div class="mmb-bundles-list">
            <h2><?php echo esc_html__( 'Your Bundles', 'bt-bundle-builder-for-wc' ); ?></h2>
            <div id="mmb-bundles-container" class="mmb-bundles-table">
                <p><?php echo esc_html__( 'Loading bundles...', 'bt-bundle-builder-for-wc' ); ?></p>
            </div>
        </div>
        
        <div class="mmb-bundle-editor">
            <h2><?php echo esc_html__( 'Create New Bundle', 'bt-bundle-builder-for-wc' ); ?></h2>
            <form id="mmb-bundle-form">
                <input type="hidden" name="bundle_id" id="bundle_id" value="0">
                
                <div class="mmb-form-group">
                    <label for="bundle_name"><?php echo esc_html__( 'Bundle Name', 'bt-bundle-builder-for-wc' ); ?></label>
                    <input type="text" name="name" id="bundle_name" placeholder="<?php echo esc_attr__( 'e.g., Mix & Match Your Favorites', 'bt-bundle-builder-for-wc' ); ?>" required>
                </div>
                
                <div class="mmb-form-group">
                    <label for="bundle_description"><?php echo esc_html__( 'Description', 'bt-bundle-builder-for-wc' ); ?></label>
                    <textarea name="description" id="bundle_description" placeholder="<?php echo esc_attr__( 'Bundle description...', 'bt-bundle-builder-for-wc' ); ?>" rows="3"></textarea>
                </div>
                
                <div class="mmb-form-group">
                    <label for="heading_text"><?php echo esc_html__( 'Products Section Heading', 'bt-bundle-builder-for-wc' ); ?></label>
                    <input type="text" name="heading_text" id="heading_text" placeholder="<?php echo esc_attr__( 'e.g., Select Your Products Below', 'bt-bundle-builder-for-wc' ); ?>">
                    <small><?php echo esc_html__( 'This heading appears above the products grid', 'bt-bundle-builder-for-wc' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label for="hint_text"><?php echo esc_html__( 'Hint Text', 'bt-bundle-builder-for-wc' ); ?></label>
                    <input type="text" name="hint_text" id="hint_text" placeholder="<?php echo esc_attr__( 'e.g., Bundle 2, 3, 4 or 5 items and watch the savings grow.', 'bt-bundle-builder-for-wc' ); ?>">
                    <small><?php echo esc_html__( 'This text appears below the heading as a hint', 'bt-bundle-builder-for-wc' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label><?php echo esc_html__( 'Select Products', 'bt-bundle-builder-for-wc' ); ?></label>
                    <div id="mmb-product-selector">
                        <input type="text" id="mmb-product-search" placeholder="<?php echo esc_attr__( 'Search products...', 'bt-bundle-builder-for-wc' ); ?>">
                        <div id="mmb-products-list" class="mmb-products-list"></div>
                    </div>
                </div>
                
                <div class="mmb-form-group" id="mmb-selected-products-group" style="display: none;">
                    <label><?php echo esc_html__( 'Selected Products (Drag to Reorder)', 'bt-bundle-builder-for-wc' ); ?></label>
                    <p class="description"><?php echo esc_html__( 'Products will appear on the frontend in this order. Drag to rearrange.', 'bt-bundle-builder-for-wc' ); ?></p>
                    <div id="mmb-selected-products-list" class="mmb-selected-products-list"></div>
                </div>
                
                <div class="mmb-form-group">
                    <label for="bundle_use_quantity">
                        <input type="checkbox" name="use_quantity" id="bundle_use_quantity" value="1">
                        <?php echo esc_html__( 'Allow Quantity Selection (instead of single select per product)', 'bt-bundle-builder-for-wc' ); ?>
                    </label>
                    <small><?php echo esc_html__( 'If enabled, customers can add multiple of the same product', 'bt-bundle-builder-for-wc' ); ?></small>
                </div>
                
                <div class="mmb-form-group" id="max_quantity_group" style="display: none;">
                    <label for="max_quantity"><?php echo esc_html__( 'Maximum Quantity Per Product', 'bt-bundle-builder-for-wc' ); ?></label>
                    <input type="number" name="max_quantity" id="max_quantity" min="1" max="999" value="10" step="1">
                    <small><?php echo esc_html__( 'Set the maximum quantity customers can select per product (default: 10)', 'bt-bundle-builder-for-wc' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label><?php echo esc_html__( 'Discount Tiers', 'bt-bundle-builder-for-wc' ); ?></label>
                    <div id="mmb-tiers-container">
                        <div class="mmb-tier-input">
                            <input type="number" name="discount_tiers[0][quantity]" placeholder="<?php echo esc_attr__( 'Quantity', 'bt-bundle-builder-for-wc' ); ?>" min="1" value="2" required>
                            <input type="number" name="discount_tiers[0][discount]" placeholder="<?php echo esc_attr__( 'Discount %', 'bt-bundle-builder-for-wc' ); ?>" min="0" max="100" value="10" step="0.01" required>
                            <button type="button" class="mmb-remove-tier"><?php echo esc_html__( 'Remove', 'bt-bundle-builder-for-wc' ); ?></button>
                        </div>
                    </div>
                    <button type="button" id="mmb-add-tier" class="button"><?php echo esc_html__( '+ Add Tier', 'bt-bundle-builder-for-wc' ); ?></button>
                </div>
                
                <!-- Customization Options -->
                <div class="mmb-form-section">
                    <h3><?php echo esc_html__( 'Design Customization', 'bt-bundle-builder-for-wc' ); ?></h3>
                    
                    <div class="mmb-color-picker-group">
                        <div class="mmb-form-group">
                            <label for="primary_color"><?php echo esc_html__( 'Primary Color', 'bt-bundle-builder-for-wc' ); ?></label>
                            <input type="color" name="primary_color" id="primary_color" value="#4caf50">
                            <small><?php echo esc_html__( 'Main brand color (buttons, prices, checkmarks)', 'bt-bundle-builder-for-wc' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="button_text_color"><?php echo esc_html__( 'Button Text Color', 'bt-bundle-builder-for-wc' ); ?></label>
                            <input type="color" name="button_text_color" id="button_text_color" value="#ffffff">
                            <small><?php echo esc_html__( 'Text color for buttons', 'bt-bundle-builder-for-wc' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="accent_color"><?php echo esc_html__( 'Accent Color', 'bt-bundle-builder-for-wc' ); ?></label>
                            <input type="color" name="accent_color" id="accent_color" value="#45a049">
                            <small><?php echo esc_html__( 'Secondary accent color', 'bt-bundle-builder-for-wc' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="hover_bg_color"><?php echo esc_html__( 'Hover Background Color', 'bt-bundle-builder-for-wc' ); ?></label>
                            <input type="color" name="hover_bg_color" id="hover_bg_color" value="#388e3c">
                            <small><?php echo esc_html__( 'Button color on hover', 'bt-bundle-builder-for-wc' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="hover_accent_color"><?php echo esc_html__( 'Hover Accent Color', 'bt-bundle-builder-for-wc' ); ?></label>
                            <input type="color" name="hover_accent_color" id="hover_accent_color" value="#2e7d32">
                            <small><?php echo esc_html__( 'Accent color on hover', 'bt-bundle-builder-for-wc' ); ?></small>
                        </div>
                    </div>
                    
                    <div class="mmb-form-group">
                        <label for="button_text"><?php echo esc_html__( 'Add to Cart Button Text', 'bt-bundle-builder-for-wc' ); ?></label>
                        <input type="text" name="button_text" id="button_text" placeholder="<?php echo esc_attr__( 'Add Bundle to Cart', 'bt-bundle-builder-for-wc' ); ?>">
                        <small><?php echo esc_html__( 'Customize the add to cart button text', 'bt-bundle-builder-for-wc' ); ?></small>
                    </div>
                    
                    <div class="mmb-form-group">
                        <label for="progress_text"><?php echo esc_html__( 'Progress Bar Title', 'bt-bundle-builder-for-wc' ); ?></label>
                        <input type="text" name="progress_text" id="progress_text" placeholder="<?php echo esc_attr__( 'Your Savings Progress', 'bt-bundle-builder-for-wc' ); ?>">
                        <small><?php echo esc_html__( 'Title shown above the discount progress bar', 'bt-bundle-builder-for-wc' ); ?></small>
                    </div>
                    
                    <div class="mmb-form-group">
                        <label for="cart_behavior"><?php echo esc_html__( 'Add to Cart Behavior', 'bt-bundle-builder-for-wc' ); ?></label>
                        <select name="cart_behavior" id="cart_behavior">
                            <option value="sidecart"><?php echo esc_html__( 'Open Sidecart (Recommended)', 'bt-bundle-builder-for-wc' ); ?></option>
                            <option value="redirect"><?php echo esc_html__( 'Redirect to Cart Page', 'bt-bundle-builder-for-wc' ); ?></option>
                        </select>
                        <small><?php echo esc_html__( 'Choose whether to open a sidecart or redirect to cart page when bundle is added', 'bt-bundle-builder-for-wc' ); ?></small>
                    </div>
                </div>
                
                <!-- Visibility Options -->
                <div class="mmb-form-section">
                    <h3><?php echo esc_html__( 'Visibility Settings', 'bt-bundle-builder-for-wc' ); ?></h3>
                    
                    <div class="mmb-visibility-options">
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_bundle_title" id="show_bundle_title" value="1" checked>
                            <?php echo esc_html__( 'Show Bundle Title', 'bt-bundle-builder-for-wc' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_bundle_description" id="show_bundle_description" value="1" checked>
                            <?php echo esc_html__( 'Show Bundle Description', 'bt-bundle-builder-for-wc' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_heading_text" id="show_heading_text" value="1" checked>
                            <?php echo esc_html__( 'Show Products Section Heading', 'bt-bundle-builder-for-wc' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_hint_text" id="show_hint_text" value="1" checked>
                            <?php echo esc_html__( 'Show Hint Text', 'bt-bundle-builder-for-wc' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_progress_text" id="show_progress_text" value="1" checked>
                            <?php echo esc_html__( 'Show Progress Bar Title', 'bt-bundle-builder-for-wc' ); ?>
                        </label>
                    </div>
                    <small><?php echo esc_html__( 'Control which text elements are displayed on the frontend', 'bt-bundle-builder-for-wc' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label>
                        <input type="checkbox" name="enabled" id="bundle_enabled" value="1" checked>
                        <?php echo esc_html__( 'Enabled', 'bt-bundle-builder-for-wc' ); ?>
                    </label>
                </div>
                
                <div class="mmb-form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Bundle', 'bt-bundle-builder-for-wc' ); ?></button>
                    <button type="button" id="mmb-reset-form" class="button"><?php echo esc_html__( 'Reset', 'bt-bundle-builder-for-wc' ); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/html" id="mmb-bundle-row-template">
    <div class="mmb-bundle-row">
        <div class="mmb-bundle-col mmb-bundle-name">{name}</div>
        <div class="mmb-bundle-col mmb-bundle-products">{product_count} <?php echo esc_html__( 'products', 'bt-bundle-builder-for-wc' ); ?></div>
        <div class="mmb-bundle-col mmb-bundle-tiers">{tier_count} <?php echo esc_html__( 'tiers', 'bt-bundle-builder-for-wc' ); ?></div>
        <div class="mmb-bundle-col mmb-bundle-status {status_class}">{status}</div>
        <div class="mmb-bundle-col mmb-bundle-actions">
            <button class="button mmb-edit-bundle" data-bundle-id="{bundle_id}"><?php echo esc_html__( 'Edit', 'bt-bundle-builder-for-wc' ); ?></button>
            <button class="button button-link-delete mmb-delete-bundle" data-bundle-id="{bundle_id}"><?php echo esc_html__( 'Delete', 'bt-bundle-builder-for-wc' ); ?></button>
        </div>
    </div>
</script>
