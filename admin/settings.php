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
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'mix-match-bundle' ) );
}

// Get current settings
$enable_logging = get_option( 'mmb_enable_logging', 'no' );
?>

<div class="wrap">
    <h1><?php echo esc_html__( 'Mix & Match Bundle Settings', 'mix-match-bundle' ); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'mmb_settings_action', 'mmb_settings_nonce' ); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="mmb_enable_logging">
                            <?php echo esc_html__( 'Enable Logging', 'mix-match-bundle' ); ?>
                        </label>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php echo esc_html__( 'Enable Logging', 'mix-match-bundle' ); ?></span>
                            </legend>
                            <label for="mmb_enable_logging">
                                <input 
                                    type="checkbox" 
                                    name="mmb_enable_logging" 
                                    id="mmb_enable_logging" 
                                    value="yes" 
                                    <?php checked( $enable_logging, 'yes' ); ?>
                                />
                                <?php echo esc_html__( 'Enable debug logging to WooCommerce logs', 'mix-match-bundle' ); ?>
                            </label>
                            <p class="description">
                                <?php 
                                echo esc_html__( 'When enabled, the plugin will log important events and errors to WooCommerce → Status → Logs.', 'mix-match-bundle' );
                                echo ' ';
                                if ( function_exists( 'wc_get_logger' ) ) {
                                    echo '<span style="color: green;">✓ ' . esc_html__( 'WooCommerce logger is available', 'mix-match-bundle' ) . '</span>';
                                } else {
                                    echo '<span style="color: orange;">⚠ ' . esc_html__( 'WooCommerce logger not available', 'mix-match-bundle' ) . '</span>';
                                }
                                ?>
                            </p>
                            <p class="description">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ); ?>" target="_blank">
                                    <?php echo esc_html__( 'View WooCommerce Logs', 'mix-match-bundle' ); ?>
                                </a>
                                <?php echo esc_html__( '(Look for "mix-match-bundle" log file)', 'mix-match-bundle' ); ?>
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
                value="<?php echo esc_attr__( 'Save Settings', 'mix-match-bundle' ); ?>"
            />
        </p>
    </form>
    
    <hr>
    
    <h2><?php echo esc_html__( 'About Logging', 'mix-match-bundle' ); ?></h2>
    <p><?php echo esc_html__( 'The Mix & Match Bundle plugin logs the following events when logging is enabled:', 'mix-match-bundle' ); ?></p>
    <ul style="list-style: disc; margin-left: 20px;">
        <li><?php echo esc_html__( 'Bundle creation and updates', 'mix-match-bundle' ); ?></li>
        <li><?php echo esc_html__( 'Coupon generation and application', 'mix-match-bundle' ); ?></li>
        <li><?php echo esc_html__( 'Cart operations (add to cart, apply discount)', 'mix-match-bundle' ); ?></li>
        <li><?php echo esc_html__( 'Analytics data queries', 'mix-match-bundle' ); ?></li>
        <li><?php echo esc_html__( 'Error conditions and warnings', 'mix-match-bundle' ); ?></li>
    </ul>
    
    <div class="notice notice-info inline">
        <p>
            <strong><?php echo esc_html__( 'Note:', 'mix-match-bundle' ); ?></strong>
            <?php echo esc_html__( 'Logging can impact performance. Only enable when troubleshooting issues.', 'mix-match-bundle' ); ?>
        </p>
    </div>
</div>

