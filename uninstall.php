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

	$options = array(
		'aeo_optimizer_settings',
		'aeo_optimizer_schema_global',
		'aeo_optimizer_audit_results',
		'aeo_local_locations',
		'aeo_last_citation_scan',
		'aeo_db_version',
		'aeo_settings',
		// 1.2.0.
		'aeo_schema_templates',
		'aeo_visibility_scan_state',
		'aeo_visibility_scan_lock',
		'aeo_faq_queue_state',
		'aeo_bing_ai_performance',
		'aeo_google_tokens',
		'aeo_indexnow_key',
		'aeo_indexnow_last',
		'aeo_indexnow_queue',
		// License (Pro).
		'aeo_license_key',
		'aeo_license_status',
		'aeo_license_data',
		'aeo_license_expiry',
		'aeo_license_last_check',
		'aeo_license_last_error',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_transient( 'aeo_llms_txt' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_aeo\_%' OR option_name LIKE '\_transient\_timeout\_aeo\_%'" );

	$post_meta = array(
		'_aeo_score',
		'_aeo_score_checks',
		'_aeo_schema',
		'_aeo_faq_items',
		'_aeo_faq_suggested',
		'_aeo_direct_answer',
		'_aeo_target_question',
		'_aeo_ai_rewrite_original',
		'_aeo_index_status',
		'_aeo_canonical',
		'_aeo_meta_title',
		'_aeo_meta_description',
		'_aeo_reviewed_at',
	);
	foreach ( $post_meta as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}
	// Author profile fields and room review ratings.
	foreach ( array( 'aeo_job_title', 'aeo_credentials', 'aeo_knows_about', 'aeo_same_as', 'aeo_works_for' ) as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}
	delete_metadata( 'comment', 0, 'aeo_rating', '', true );

	foreach ( array( 'aeo_schema_cache', 'aeo_audit_log', 'aeo_citations', 'aeo_ai_traffic', 'aeo_ai_answers' ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	// phpcs:enable

	wp_clear_scheduled_hook( 'aeo_weekly_maintenance' );
	wp_clear_scheduled_hook( 'aeo_indexnow_ping' );
	wp_clear_scheduled_hook( 'aeo_daily_license_check' );
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
