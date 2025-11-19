<?php
/**
 * Uninstall Mix & Match Bundle
 *
 * Removes all plugin data when uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// The table name is static and known ahead of time.
$table_name = $wpdb->prefix . 'mmb_bundles';

// Ensure the prefix is safe (WordPress guarantees this).
$prefix = preg_replace( '/[^A-Za-z0-9_]/', '', $wpdb->prefix );
$table  = $prefix . 'mmb_bundles';

/*
 * DROP TABLE cannot use prepare(), and Plugin Check complains about variables.
 * The table name is sanitized and built from known safe values.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

// Delete plugin options.
delete_option( 'mmb_settings' );
delete_option( 'mmb_version' );

// Delete transients.
delete_transient( 'mmb_bundles_cache' );

// Clear cache.
wp_cache_flush();