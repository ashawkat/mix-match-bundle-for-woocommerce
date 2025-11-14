<?php
/**
 * Uninstall Mix & Match Bundle
 * 
 * Removes all plugin data when uninstalled
 */

// Exit if accessed directly or not uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete custom database table
$table_name = $wpdb->prefix . 'mmb_bundles';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete plugin options
delete_option( 'mmb_settings' );
delete_option( 'mmb_version' );

// Delete transients
delete_transient( 'mmb_bundles_cache' );

// Clear any cached data
wp_cache_flush();

