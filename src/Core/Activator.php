<?php
/**
 * Plugin activation handler.
 *
 * @package AEO
 */

namespace AEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation tasks.
 */
class Activator {

	/**
	 * Run activation routines.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$schema_table    = $wpdb->prefix . 'aeo_schema_cache';
		$audit_table     = $wpdb->prefix . 'aeo_audit_log';
		$citations_table = $wpdb->prefix . 'aeo_citations';

		$sql = "CREATE TABLE {$schema_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			schema_type varchar(100) NOT NULL DEFAULT '',
			schema_data longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY schema_type (schema_type)
		) {$charset_collate};

		CREATE TABLE {$audit_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			issue_id varchar(64) NOT NULL DEFAULT '',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			severity varchar(20) NOT NULL DEFAULT 'warning',
			category varchar(50) NOT NULL DEFAULT '',
			message text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			fixed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY issue_id (issue_id),
			KEY post_id (post_id),
			KEY severity (severity)
		) {$charset_collate};

		CREATE TABLE {$citations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword varchar(255) NOT NULL DEFAULT '',
			page_url varchar(500) NOT NULL DEFAULT '',
			engine varchar(50) NOT NULL DEFAULT '',
			citation_url varchar(500) NOT NULL DEFAULT '',
			detected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY keyword (keyword),
			KEY engine (engine),
			KEY detected_at (detected_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		$defaults = array(
			'site_type'           => 'blog',
			'onboarding_complete' => false,
			'ai_provider'         => 'openai',
			'modules'             => array(
				'content_optimizer' => true,
				'schema_builder'    => true,
				'faq_automation'      => true,
				'analytics'           => true,
				'local_business'      => false,
				'woocommerce'         => false,
				'technical'           => true,
				'social_og'           => true,
				'content_briefs'      => true,
				'site_audit'          => true,
				'agency'              => false,
			),
			'aeo_enable_schema'      => 1,
			'aeo_auto_questions'     => 1,
			'aeo_voice_optimization' => 1,
			'aeo_target_questions'   => '',
			'notifications'          => array(
				'email_citations' => true,
				'email_audit'     => true,
			),
			'competitors'            => array(),
			'tracked_keywords'       => array(),
			'business_info'          => array(
				'name'        => get_bloginfo( 'name' ),
				'description' => get_bloginfo( 'description' ),
				'logo_url'    => '',
				'social'      => array(),
			),
			'agency'                 => array(
				'white_label_name' => '',
				'white_label_logo' => '',
			),
			'crawler_settings'       => array(
				'gptbot'         => 'allow',
				'perplexitybot'  => 'allow',
				'claudebot'      => 'allow',
				'googlebot_ai'   => 'allow',
			),
		);

		if ( ! get_option( 'aeo_optimizer_settings' ) ) {
			update_option( 'aeo_optimizer_settings', $defaults );
		}

		// Migrate legacy settings.
		$legacy = get_option( 'aeo_settings' );
		if ( $legacy && is_array( $legacy ) ) {
			$merged = array_merge( $defaults, $legacy );
			update_option( 'aeo_optimizer_settings', $merged );
		}

		if ( ! get_option( 'aeo_optimizer_schema_global' ) ) {
			update_option(
				'aeo_optimizer_schema_global',
				array(
					'website'      => array( '@type' => 'WebSite' ),
					'organization' => array( '@type' => 'Organization' ),
				)
			);
		}
	}
}
