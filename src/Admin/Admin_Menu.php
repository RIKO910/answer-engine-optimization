<?php
/**
 * Admin menu registration.
 *
 * @package AEO
 */

namespace AEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the main AEO admin menu.
 */
class Admin_Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register admin menu pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		$brand      = \AEO\Core\Settings::branding();
		$brand_name = $brand['name'];

		add_menu_page(
			$brand_name ? $brand_name : __( 'Answer Engine Optimization', 'answer-engine-optimization' ),
			$brand_name ? $brand_name : __( 'AEO', 'answer-engine-optimization' ),
			'aeo_view_reports',
			'aeo-dashboard',
			array( $this, 'render_app' ),
			'dashicons-chart-area',
			30
		);

		$pages = array(
			'aeo-dashboard'     => __( 'Dashboard', 'answer-engine-optimization' ),
			'aeo-content'       => __( 'Content Optimizer', 'answer-engine-optimization' ),
			'aeo-schema'        => __( 'Schema Builder', 'answer-engine-optimization' ),
			'aeo-faqs'          => __( 'FAQ Manager', 'answer-engine-optimization' ),
			'aeo-local'         => __( 'Local Business', 'answer-engine-optimization' ),
			'aeo-woocommerce'   => __( 'WooCommerce AEO', 'answer-engine-optimization' ),
			'aeo-analytics'     => __( 'AI Visibility', 'answer-engine-optimization' ),
			'aeo-traffic'       => __( 'Traffic', 'answer-engine-optimization' ),
			'aeo-content-tools' => __( 'Content Tools', 'answer-engine-optimization' ),
			'aeo-templates'     => __( 'Schema Templates', 'answer-engine-optimization' ),
			'aeo-hotel'         => __( 'Hotel', 'answer-engine-optimization' ),
			'aeo-ai-access'     => __( 'AI Access', 'answer-engine-optimization' ),
			'aeo-tools'         => __( 'Tools', 'answer-engine-optimization' ),
			'aeo-license'       => __( 'License', 'answer-engine-optimization' ),
			'aeo-audit'         => __( 'Site Audit', 'answer-engine-optimization' ),
			'aeo-briefs'        => __( 'Content Briefs', 'answer-engine-optimization' ),
			'aeo-settings'      => __( 'Settings', 'answer-engine-optimization' ),
			'aeo-agency'        => __( 'Agency Tools', 'answer-engine-optimization' ),
		);

		$caps = array(
			'aeo-settings' => 'aeo_manage_settings',
			'aeo-agency'   => 'aeo_manage_settings',
			'aeo-tools'    => 'aeo_manage_settings',
			'aeo-license'  => 'manage_options',
			'aeo-ai-access' => 'aeo_manage_settings',
			'aeo-templates' => 'aeo_edit_schema',
		);

		foreach ( $pages as $slug => $title ) {
			if ( 'aeo-dashboard' === $slug ) {
				continue;
			}
			add_submenu_page(
				'aeo-dashboard',
				$title,
				$title,
				$caps[ $slug ] ?? 'aeo_view_reports',
				$slug,
				array( $this, 'render_app' )
			);
		}
	}

	/**
	 * Render React app root.
	 *
	 * @return void
	 */
	public function render_app() {
		echo '<div id="aeo-genius-root"><noscript>' . esc_html__( 'This page needs JavaScript enabled.', 'answer-engine-optimization' ) . '</noscript></div>';
	}
}
