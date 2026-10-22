<?php
/**
 * Versioned upgrades and scheduled events.
 *
 * @package AEO
 */

namespace AEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs one-off migrations when the plugin version changes.
 */
class Upgrader {

	const DB_VERSION     = '1.1.0';
	const VERSION_OPTION = 'aeo_db_version';
	const CRON_HOOK      = 'aeo_weekly_maintenance';

	/**
	 * Run pending migrations (hooked to admin_init and WP-CLI).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::VERSION_OPTION, '1.0.0' );
		if ( version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}

		Activator::create_tables();

		if ( version_compare( $installed, '1.1.0', '<' ) ) {
			self::purge_simulated_citations();
			delete_transient( 'aeo_llms_txt' );
			// The llms.txt rule was never flushed on activation in 1.0.x.
			\AEO\Plugin::register_rewrites();
			flush_rewrite_rules( false );
			$settings = Settings::get_all();
			if ( ! isset( $settings['defer_to_seo_plugin'] ) ) {
				Settings::update( array( 'defer_to_seo_plugin' => 1 ) );
			}
		}

		self::schedule_events();
		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * 1.0.x stored made-up "citations" generated from a hash. Remove them.
	 *
	 * They are recognisable by their citation_url, which was always home_url('/?s=...').
	 *
	 * @return void
	 */
	public static function purge_simulated_citations() {
		global $wpdb;
		$table = $wpdb->prefix . 'aeo_citations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}
		$like = $wpdb->esc_like( home_url( '/?s=' ) ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE citation_url LIKE %s", $like ) );
	}

	/**
	 * Schedule the weekly maintenance event.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Remove scheduled events.
	 *
	 * @return void
	 */
	public static function unschedule_events() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
