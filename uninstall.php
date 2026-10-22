<?php
/**
 * Uninstall: remove all plugin data.
 *
 * Runs only when the plugin is deleted from the Plugins screen (not on deactivation).
 * Define AEO_KEEP_DATA as true in wp-config.php to keep data.
 *
 * @package AEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( defined( 'AEO_KEEP_DATA' ) && AEO_KEEP_DATA ) {
	return;
}

/**
 * Remove data for the current site.
 *
 * @return void
 */
function aeo_uninstall_site() {
	global $wpdb;

	foreach ( array( 'aeo_optimizer_settings', 'aeo_optimizer_schema_global', 'aeo_optimizer_audit_results', 'aeo_local_locations', 'aeo_last_citation_scan', 'aeo_db_version', 'aeo_settings' ) as $option ) {
		delete_option( $option );
	}

	delete_transient( 'aeo_llms_txt' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aeo\_%' OR option_name LIKE '\_transient\_timeout\_aeo\_%'" );

	foreach ( array( '_aeo_score', '_aeo_schema', '_aeo_faq_items', '_aeo_direct_answer', '_aeo_target_question', '_aeo_ai_rewrite_original', '_aeo_index_status', '_aeo_canonical' ) as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	foreach ( array( 'aeo_schema_cache', 'aeo_audit_log', 'aeo_citations' ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	// phpcs:enable

	wp_clear_scheduled_hook( 'aeo_weekly_maintenance' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $aeo_site_id ) {
		switch_to_blog( $aeo_site_id );
		aeo_uninstall_site();
		restore_current_blog();
	}
} else {
	aeo_uninstall_site();
}
