<?php
/**
 * Pro detection and upsell helpers.
 *
 * Pro features are only available when BOTH are true:
 *   1. the "Answer Engine Optimization Pro" add-on is loaded (it defines AEO_Pro_Plugin
 *      only after its own checks pass), and
 *   2. the license is active (License::is_active()).
 *
 * The Pro feature code lives in the add-on. The free plugin only exposes hooks,
 * the License page and "Pro" labels in the admin.
 *
 * @package AEO
 */

namespace AEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Pro helpers.
 */
class Pro {

	/**
	 * Is the Pro add-on loaded?
	 *
	 * @return bool
	 */
	public static function plugin_active() {
		return class_exists( 'AEO_Pro_Plugin', false );
	}

	/**
	 * Pro add-on loaded AND license active.
	 *
	 * @return bool
	 */
	public static function licensed() {
		return self::plugin_active() && License::is_active();
	}

	/**
	 * Where to buy Pro.
	 *
	 * @return string
	 */
	public static function sales_url() {
		return (string) apply_filters( 'aeo_pro_sales_url', 'https://tarikul.top/plugins/answer-engine-optimization/' );
	}

	/**
	 * Admin page for the license.
	 *
	 * @return string
	 */
	public static function license_url() {
		return admin_url( 'admin.php?page=aeo-license' );
	}

	/**
	 * Standard error for a Pro-only request (HTTP 402).
	 *
	 * @param string $message Optional message.
	 * @return \WP_Error
	 */
	public static function required_error( $message = '' ) {
		return new \WP_Error(
			'aeo_pro_required',
			'' !== $message ? $message : __( 'This feature needs Answer Engine Optimization Pro with an active license.', 'answer-engine-optimization' ),
			array(
				'status'       => 402,
				'pro_required' => true,
				'upgrade_url'  => self::license_url(),
			)
		);
	}

	/**
	 * What Pro adds, for the License page and upsell cards.
	 *
	 * @return array<int, array{id: string, label: string, description: string}>
	 */
	public static function features() {
		return array(
			array( 'id' => 'engines', 'label' => __( 'ChatGPT, Gemini and Claude visibility', 'answer-engine-optimization' ), 'description' => __( 'Track citations on four AI engines instead of Perplexity only, with up to 25 prompts.', 'answer-engine-optimization' ) ),
			array( 'id' => 'insights', 'label' => __( 'Share of voice and trends', 'answer-engine-optimization' ), 'description' => __( 'See which competitors the engines cite and how your visibility changes scan by scan.', 'answer-engine-optimization' ) ),
			array( 'id' => 'scheduled', 'label' => __( 'Scheduled weekly scans', 'answer-engine-optimization' ), 'description' => __( 'Scans run automatically and the results are emailed to you.', 'answer-engine-optimization' ) ),
			array( 'id' => 'google', 'label' => __( 'Google Search Console and GA4', 'answer-engine-optimization' ), 'description' => __( 'Question-style searches and sessions from AI assistants, in your dashboard.', 'answer-engine-optimization' ) ),
			array( 'id' => 'bing', 'label' => __( 'Bing AI Performance import', 'answer-engine-optimization' ), 'description' => __( 'Copilot citations per page and per day.', 'answer-engine-optimization' ) ),
			array( 'id' => 'reports', 'label' => __( 'Client reports', 'answer-engine-optimization' ), 'description' => __( 'A printable report of scores, AI visibility and traffic.', 'answer-engine-optimization' ) ),
			array( 'id' => 'white_label', 'label' => __( 'White-label', 'answer-engine-optimization' ), 'description' => __( 'Your agency name and logo in the admin.', 'answer-engine-optimization' ) ),
			array( 'id' => 'bulk_ai', 'label' => __( 'Bulk AI tools', 'answer-engine-optimization' ), 'description' => __( 'Draft FAQs for many pages at once, and write SEO titles, descriptions and alt text with AI.', 'answer-engine-optimization' ) ),
			array( 'id' => 'templates', 'label' => __( 'Schema templates', 'answer-engine-optimization' ), 'description' => __( 'Reusable schema with display rules, filled from custom fields, ACF and Meta Box.', 'answer-engine-optimization' ) ),
			array( 'id' => 'mcp', 'label' => __( 'MCP server for AI agents', 'answer-engine-optimization' ), 'description' => __( 'Let Claude, ChatGPT or Cursor read and update your AEO data.', 'answer-engine-optimization' ) ),
			array( 'id' => 'roles', 'label' => __( 'Role permissions', 'answer-engine-optimization' ), 'description' => __( 'Choose which roles can view reports, edit schema or use AI.', 'answer-engine-optimization' ) ),
			array( 'id' => 'woo', 'label' => __( 'Advanced WooCommerce schema', 'answer-engine-optimization' ), 'description' => __( 'Return policy, shipping details and product variants (ProductGroup).', 'answer-engine-optimization' ) ),
		);
	}

	/**
	 * State passed to the admin app.
	 *
	 * @return array<string, mixed>
	 */
	public static function data() {
		return array(
			'plugin_active' => self::plugin_active(),
			'licensed'      => self::licensed(),
			'license'       => License::is_active(),
			'version'       => defined( 'AEO_PRO_VERSION' ) ? AEO_PRO_VERSION : '',
			'sales_url'     => self::sales_url(),
			'license_url'   => self::license_url(),
			'features'      => self::features(),
		);
	}
}
