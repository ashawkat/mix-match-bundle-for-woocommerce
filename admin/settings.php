<?php
/**
 * Mix & Match Bundle Settings Page
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if user has required permissions
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bt-bundle-builder-for-wc' ) );
}

// Get current settings
$enable_logging = get_option( 'mmb_enable_logging', 'no' );
?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Mix & Match Bundle Settings', 'bt-bundle-builder-for-wc' ); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'mmb_settings_action', 'mmb_settings_nonce' ); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="mmb_enable_logging">
                            <?php echo esc_html__( 'Enable Logging', 'bt-bundle-builder-for-wc' ); ?>
                        </label>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php echo esc_html__( 'Enable Logging', 'bt-bundle-builder-for-wc' ); ?></span>
                            </legend>
                            <label for="mmb_enable_logging">
                                <input 
                                    type="checkbox" 
                                    name="mmb_enable_logging" 
                                    id="mmb_enable_logging" 
                                    value="yes" 
                                    <?php checked( $enable_logging, 'yes' ); ?>
                                />
                                <?php echo esc_html__( 'Enable debug logging to WooCommerce logs', 'bt-bundle-builder-for-wc' ); ?>
                            </label>
                            <p class="description">
                                <?php 
                                echo esc_html__( 'When enabled, the plugin will log important events and errors to WooCommerce → Status → Logs.', 'bt-bundle-builder-for-wc' );
                                echo ' ';
                                if ( function_exists( 'wc_get_logger' ) ) {
                                    echo '<span style="color: green;">✓ ' . esc_html__( 'WooCommerce logger is available', 'bt-bundle-builder-for-wc' ) . '</span>';
                                } else {
                                    echo '<span style="color: orange;">⚠ ' . esc_html__( 'WooCommerce logger not available', 'bt-bundle-builder-for-wc' ) . '</span>';
                                }
                                ?>
                            </p>
                            <p class="description">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ); ?>" target="_blank">
                                    <?php echo esc_html__( 'View WooCommerce Logs', 'bt-bundle-builder-for-wc' ); ?>
                                </a>
                                <?php echo esc_html__( '(Look for "bt-bundle-builder-for-wc" log file)', 'bt-bundle-builder-for-wc' ); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input 
                type="submit" 
                name="submit" 
                id="submit" 
                class="button button-primary" 
                value="<?php echo esc_attr__( 'Save Settings', 'bt-bundle-builder-for-wc' ); ?>"
            />
        </p>
    </form>
    
    <hr>
    
    <h2><?php echo esc_html__( 'About Logging', 'bt-bundle-builder-for-wc' ); ?></h2>
    <p><?php echo esc_html__( 'The Mix & Match Bundle plugin logs the following events when logging is enabled:', 'bt-bundle-builder-for-wc' ); ?></p>
    <ul style="list-style: disc; margin-left: 20px;">
        <li><?php echo esc_html__( 'Bundle creation and updates', 'bt-bundle-builder-for-wc' ); ?></li>
        <li><?php echo esc_html__( 'Coupon generation and application', 'bt-bundle-builder-for-wc' ); ?></li>
        <li><?php echo esc_html__( 'Cart operations (add to cart, apply discount)', 'bt-bundle-builder-for-wc' ); ?></li>
        <li><?php echo esc_html__( 'Analytics data queries', 'bt-bundle-builder-for-wc' ); ?></li>
        <li><?php echo esc_html__( 'Error conditions and warnings', 'bt-bundle-builder-for-wc' ); ?></li>
    </ul>
    
    <div class="notice notice-info inline">
        <p>
            <strong><?php echo esc_html__( 'Note:', 'bt-bundle-builder-for-wc' ); ?></strong>
            <?php echo esc_html__( 'Logging can impact performance. Only enable when troubleshooting issues.', 'bt-bundle-builder-for-wc' ); ?>
        </p>
    </div>
</div>

