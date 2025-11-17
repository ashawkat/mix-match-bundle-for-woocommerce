<?php
/**
 * Admin Bundle Editor Page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check database status
global $wpdb;
$table_name = $wpdb->prefix . 'mmb_bundles';
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );

// Handle manual setup request
if ( isset( $_GET['mmb_setup_db'] ) && check_admin_referer( 'mmb_setup_db' ) ) {
    $plugin_instance = Mix_Match_Bundle::get_instance();
    $plugin_instance->activate_plugin();
    
    // Re-check if table was created
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
    
    if ( $table_exists ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Database table created successfully!', 'mix-match-bundle' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to create database table. Please check your database permissions.', 'mix-match-bundle' ) . '</p></div>';
    }
}
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'Mix & Match Bundles', 'mix-match-bundle' ); ?></h1>
    
    <?php if ( ! $table_exists ) : ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php echo esc_html__( 'Database Setup Required', 'mix-match-bundle' ); ?></strong><br>
            <?php echo esc_html__( 'The plugin database table does not exist. This usually happens on fresh installations.', 'mix-match-bundle' ); ?>
        </p>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mmb_setup_db', '1' ), 'mmb_setup_db' ) ); ?>" class="button button-primary">
                <?php echo esc_html__( 'Setup Database Now', 'mix-match-bundle' ); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="mmb-admin-container">
        <div class="mmb-bundles-list">
            <h2><?php echo esc_html__( 'Your Bundles', 'mix-match-bundle' ); ?></h2>
            <div id="mmb-bundles-container" class="mmb-bundles-table">
                <p><?php echo esc_html__( 'Loading bundles...', 'mix-match-bundle' ); ?></p>
            </div>
        </div>
        
        <div class="mmb-bundle-editor">
            <h2><?php echo esc_html__( 'Create New Bundle', 'mix-match-bundle' ); ?></h2>
            <form id="mmb-bundle-form">
                <input type="hidden" name="bundle_id" id="bundle_id" value="0">
                
                <div class="mmb-form-group">
                    <label for="bundle_name"><?php echo esc_html__( 'Bundle Name', 'mix-match-bundle' ); ?></label>
                    <input type="text" name="name" id="bundle_name" placeholder="<?php echo esc_attr__( 'e.g., Mix & Match Your Favorites', 'mix-match-bundle' ); ?>" required>
                </div>
                
                <div class="mmb-form-group">
                    <label for="bundle_description"><?php echo esc_html__( 'Description', 'mix-match-bundle' ); ?></label>
                    <textarea name="description" id="bundle_description" placeholder="<?php echo esc_attr__( 'Bundle description...', 'mix-match-bundle' ); ?>" rows="3"></textarea>
                </div>
                
                <div class="mmb-form-group">
                    <label for="heading_text"><?php echo esc_html__( 'Products Section Heading', 'mix-match-bundle' ); ?></label>
                    <input type="text" name="heading_text" id="heading_text" placeholder="<?php echo esc_attr__( 'e.g., Select Your Products Below', 'mix-match-bundle' ); ?>">
                    <small><?php echo esc_html__( 'This heading appears above the products grid', 'mix-match-bundle' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label for="hint_text"><?php echo esc_html__( 'Hint Text', 'mix-match-bundle' ); ?></label>
                    <input type="text" name="hint_text" id="hint_text" placeholder="<?php echo esc_attr__( 'e.g., Bundle 2, 3, 4 or 5 items and watch the savings grow.', 'mix-match-bundle' ); ?>">
                    <small><?php echo esc_html__( 'This text appears below the heading as a hint', 'mix-match-bundle' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label><?php echo esc_html__( 'Select Products', 'mix-match-bundle' ); ?></label>
                    <div id="mmb-product-selector">
                        <input type="text" id="mmb-product-search" placeholder="<?php echo esc_attr__( 'Search products...', 'mix-match-bundle' ); ?>">
                        <div id="mmb-products-list" class="mmb-products-list"></div>
                    </div>
                </div>
                
                <div class="mmb-form-group" id="mmb-selected-products-group" style="display: none;">
                    <label><?php echo esc_html__( 'Selected Products (Drag to Reorder)', 'mix-match-bundle' ); ?></label>
                    <p class="description"><?php echo esc_html__( 'Products will appear on the frontend in this order. Drag to rearrange.', 'mix-match-bundle' ); ?></p>
                    <div id="mmb-selected-products-list" class="mmb-selected-products-list"></div>
                </div>
                
                <div class="mmb-form-group">
                    <label for="bundle_use_quantity">
                        <input type="checkbox" name="use_quantity" id="bundle_use_quantity" value="1">
                        <?php echo esc_html__( 'Allow Quantity Selection (instead of single select per product)', 'mix-match-bundle' ); ?>
                    </label>
                    <small><?php echo esc_html__( 'If enabled, customers can add multiple of the same product', 'mix-match-bundle' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label><?php echo esc_html__( 'Discount Tiers', 'mix-match-bundle' ); ?></label>
                    <div id="mmb-tiers-container">
                        <div class="mmb-tier-input">
                            <input type="number" name="discount_tiers[0][quantity]" placeholder="<?php echo esc_attr__( 'Quantity', 'mix-match-bundle' ); ?>" min="1" value="2" required>
                            <input type="number" name="discount_tiers[0][discount]" placeholder="<?php echo esc_attr__( 'Discount %', 'mix-match-bundle' ); ?>" min="0" max="100" value="10" step="0.01" required>
                            <button type="button" class="mmb-remove-tier"><?php echo esc_html__( 'Remove', 'mix-match-bundle' ); ?></button>
                        </div>
                    </div>
                    <button type="button" id="mmb-add-tier" class="button"><?php echo esc_html__( '+ Add Tier', 'mix-match-bundle' ); ?></button>
                </div>
                
                <!-- Customization Options -->
                <div class="mmb-form-section">
                    <h3><?php echo esc_html__( 'Design Customization', 'mix-match-bundle' ); ?></h3>
                    
                    <div class="mmb-color-picker-group">
                        <div class="mmb-form-group">
                            <label for="primary_color"><?php echo esc_html__( 'Primary Color', 'mix-match-bundle' ); ?></label>
                            <input type="color" name="primary_color" id="primary_color" value="#4caf50">
                            <small><?php echo esc_html__( 'Main brand color (buttons, prices, checkmarks)', 'mix-match-bundle' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="button_text_color"><?php echo esc_html__( 'Button Text Color', 'mix-match-bundle' ); ?></label>
                            <input type="color" name="button_text_color" id="button_text_color" value="#ffffff">
                            <small><?php echo esc_html__( 'Text color for buttons', 'mix-match-bundle' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="accent_color"><?php echo esc_html__( 'Accent Color', 'mix-match-bundle' ); ?></label>
                            <input type="color" name="accent_color" id="accent_color" value="#45a049">
                            <small><?php echo esc_html__( 'Secondary accent color', 'mix-match-bundle' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="hover_bg_color"><?php echo esc_html__( 'Hover Background Color', 'mix-match-bundle' ); ?></label>
                            <input type="color" name="hover_bg_color" id="hover_bg_color" value="#388e3c">
                            <small><?php echo esc_html__( 'Button color on hover', 'mix-match-bundle' ); ?></small>
                        </div>
                        
                        <div class="mmb-form-group">
                            <label for="hover_accent_color"><?php echo esc_html__( 'Hover Accent Color', 'mix-match-bundle' ); ?></label>
                            <input type="color" name="hover_accent_color" id="hover_accent_color" value="#2e7d32">
                            <small><?php echo esc_html__( 'Accent color on hover', 'mix-match-bundle' ); ?></small>
                        </div>
                    </div>
                    
                    <div class="mmb-form-group">
                        <label for="button_text"><?php echo esc_html__( 'Add to Cart Button Text', 'mix-match-bundle' ); ?></label>
                        <input type="text" name="button_text" id="button_text" placeholder="<?php echo esc_attr__( 'Add Bundle to Cart', 'mix-match-bundle' ); ?>">
                        <small><?php echo esc_html__( 'Customize the add to cart button text', 'mix-match-bundle' ); ?></small>
                    </div>
                    
                    <div class="mmb-form-group">
                        <label for="progress_text"><?php echo esc_html__( 'Progress Bar Title', 'mix-match-bundle' ); ?></label>
                        <input type="text" name="progress_text" id="progress_text" placeholder="<?php echo esc_attr__( 'Your Savings Progress', 'mix-match-bundle' ); ?>">
                        <small><?php echo esc_html__( 'Title shown above the discount progress bar', 'mix-match-bundle' ); ?></small>
                    </div>
                    
                    <div class="mmb-form-group">
                        <label for="cart_behavior"><?php echo esc_html__( 'Add to Cart Behavior', 'mix-match-bundle' ); ?></label>
                        <select name="cart_behavior" id="cart_behavior">
                            <option value="sidecart"><?php echo esc_html__( 'Open Sidecart (Recommended)', 'mix-match-bundle' ); ?></option>
                            <option value="redirect"><?php echo esc_html__( 'Redirect to Cart Page', 'mix-match-bundle' ); ?></option>
                        </select>
                        <small><?php echo esc_html__( 'Choose whether to open a sidecart or redirect to cart page when bundle is added', 'mix-match-bundle' ); ?></small>
                    </div>
                </div>
                
                <!-- Visibility Options -->
                <div class="mmb-form-section">
                    <h3><?php echo esc_html__( 'Visibility Settings', 'mix-match-bundle' ); ?></h3>
                    
                    <div class="mmb-visibility-options">
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_bundle_title" id="show_bundle_title" value="1" checked>
                            <?php echo esc_html__( 'Show Bundle Title', 'mix-match-bundle' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_bundle_description" id="show_bundle_description" value="1" checked>
                            <?php echo esc_html__( 'Show Bundle Description', 'mix-match-bundle' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_heading_text" id="show_heading_text" value="1" checked>
                            <?php echo esc_html__( 'Show Products Section Heading', 'mix-match-bundle' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_hint_text" id="show_hint_text" value="1" checked>
                            <?php echo esc_html__( 'Show Hint Text', 'mix-match-bundle' ); ?>
                        </label>
                        
                        <label class="mmb-checkbox-label">
                            <input type="checkbox" name="show_progress_text" id="show_progress_text" value="1" checked>
                            <?php echo esc_html__( 'Show Progress Bar Title', 'mix-match-bundle' ); ?>
                        </label>
                    </div>
                    <small><?php echo esc_html__( 'Control which text elements are displayed on the frontend', 'mix-match-bundle' ); ?></small>
                </div>
                
                <div class="mmb-form-group">
                    <label>
                        <input type="checkbox" name="enabled" id="bundle_enabled" value="1" checked>
                        <?php echo esc_html__( 'Enabled', 'mix-match-bundle' ); ?>
                    </label>
                </div>
                
                <div class="mmb-form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Bundle', 'mix-match-bundle' ); ?></button>
                    <button type="button" id="mmb-reset-form" class="button"><?php echo esc_html__( 'Reset', 'mix-match-bundle' ); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/html" id="mmb-bundle-row-template">
    <div class="mmb-bundle-row">
        <div class="mmb-bundle-col mmb-bundle-name">{name}</div>
        <div class="mmb-bundle-col mmb-bundle-products">{product_count} <?php echo esc_html__( 'products', 'mix-match-bundle' ); ?></div>
        <div class="mmb-bundle-col mmb-bundle-tiers">{tier_count} <?php echo esc_html__( 'tiers', 'mix-match-bundle' ); ?></div>
        <div class="mmb-bundle-col mmb-bundle-status {status_class}">{status}</div>
        <div class="mmb-bundle-col mmb-bundle-actions">
            <button class="button mmb-edit-bundle" data-bundle-id="{bundle_id}"><?php echo esc_html__( 'Edit', 'mix-match-bundle' ); ?></button>
            <button class="button button-link-delete mmb-delete-bundle" data-bundle-id="{bundle_id}"><?php echo esc_html__( 'Delete', 'mix-match-bundle' ); ?></button>
        </div>
    </div>
</script>
