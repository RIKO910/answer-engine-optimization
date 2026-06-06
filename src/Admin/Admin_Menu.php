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
		add_menu_page(
			__( 'Answer Engine Optimization', 'answer-engine-optimization' ),
			__( 'AEO', 'answer-engine-optimization' ),
			'manage_options',
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
			'aeo-analytics'     => __( 'Analytics', 'answer-engine-optimization' ),
			'aeo-audit'         => __( 'Site Audit', 'answer-engine-optimization' ),
			'aeo-briefs'        => __( 'Content Briefs', 'answer-engine-optimization' ),
			'aeo-settings'      => __( 'Settings', 'answer-engine-optimization' ),
			'aeo-agency'        => __( 'Agency Tools', 'answer-engine-optimization' ),
		);

		foreach ( $pages as $slug => $title ) {
			if ( 'aeo-dashboard' === $slug ) {
				continue;
			}
			add_submenu_page(
				'aeo-dashboard',
				$title,
				$title,
				'manage_options',
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
		echo '<div id="aeo-genius-root"></div>';
	}
}
