<?php
/**
 * Main plugin bootstrap.
 *
 * @package AEO
 */

namespace AEO;

use AEO\Admin\Admin_Menu;
use AEO\Admin\Assets;
use AEO\Api\Rest_Api;
use AEO\Core\Activator;
use AEO\Core\Settings;
use AEO\Core\Upgrader;
use AEO\Frontend\Frontend_Manager;
use AEO\Services\Notification_Service;
use AEO\Services\Score_Engine;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton.
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.3.0';

	/**
	 * Get singleton instance.
	 *
	 * @param string $file Plugin file.
	 * @return Plugin
	 */
	public static function get_instance( $file = '' ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param string $file Plugin file.
	 */
	private function __construct( $file ) {
		$this->file = $file;
		$this->define_constants();
		$this->register_hooks();
		$this->load_legacy();
	}

	/**
	 * Define plugin constants.
	 *
	 * @return void
	 */
	private function define_constants() {
		if ( ! defined( 'AEO_VERSION' ) ) {
			define( 'AEO_VERSION', $this->version );
		}
		if ( ! defined( 'AEO_PLUGIN_DIR' ) ) {
			define( 'AEO_PLUGIN_DIR', plugin_dir_path( $this->file ) );
		}
		if ( ! defined( 'AEO_PLUGIN_URL' ) ) {
			define( 'AEO_PLUGIN_URL', plugin_dir_url( $this->file ) );
		}
		if ( ! defined( 'AEO_PLUGIN_BASENAME' ) ) {
			define( 'AEO_PLUGIN_BASENAME', plugin_basename( $this->file ) );
		}
		if ( ! defined( 'AEO_NEW_SYSTEM' ) ) {
			define( 'AEO_NEW_SYSTEM', true );
		}
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( Upgrader::class, 'maybe_upgrade' ), 20 );
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
		add_action( 'deleted_post', array( $this, 'flush_llms_cache' ) );
		add_filter( 'robots_txt', array( \AEO\Frontend\Ai_Crawlers::class, 'filter_robots_txt' ), 20, 2 );
		\AEO\Api\Public_Api::register();
		\AEO\Api\Rest_Extra::register();
		\AEO\Api\Rest_Content::register();
		\AEO\Content\Blocks::register();
		\AEO\Content\Editor_Panel::register();
		\AEO\Content\Author_Profile::register();
		\AEO\Integrations\Page_Builders::register();
		\AEO\Integrations\Abilities::register();
		\AEO\Api\Rest_Tools::register();
		\AEO\Integrations\Graphql::register();
		\AEO\Core\Capabilities::register();
		\AEO\Core\License::register();
		\AEO\Services\IndexNow::register();
		add_action( Upgrader::CRON_HOOK, array( $this, 'run_weekly_maintenance' ) );
		add_action( 'wp_initialize_site', array( Activator::class, 'on_new_site' ), 20 );
	}

	/**
	 * Register the /llms.txt rewrite rule on every request (front end and admin),
	 * so flush_rewrite_rules() during activation actually persists it.
	 *
	 * @return void
	 */
	public static function register_rewrites() {
		\AEO\Frontend\Llms_Txt::rewrites();
		\AEO\Services\IndexNow::rewrites();
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'aeo_llms_txt';
		$vars[] = 'aeo_md';
		$vars[] = 'aeo_indexnow_key';
		return $vars;
	}

	/**
	 * Weekly cron callback.
	 *
	 * @return void
	 */
	public function run_weekly_maintenance() {
		\AEO\Services\Traffic_Log::prune( (int) apply_filters( 'aeo_traffic_retention_days', 90 ) );
		( new Notification_Service() )->run_weekly();
	}

	/**
	 * Clear the cached llms.txt.
	 *
	 * @return void
	 */
	public function flush_llms_cache() {
		\AEO\Frontend\Llms_Txt::flush();
		\AEO\Api\Public_Api::flush_schemamap();
	}

	/**
	 * Load legacy class files for backward compatibility.
	 *
	 * @return void
	 */
	private function load_legacy() {
		require_once AEO_PLUGIN_DIR . 'includes/admin/class-aeo-admin.php';
		require_once AEO_PLUGIN_DIR . 'includes/admin/class-aeo-questions.php';
		require_once AEO_PLUGIN_DIR . 'includes/admin/class-aeo-meta-box.php';
		require_once AEO_PLUGIN_DIR . 'includes/frontend/class-aeo-frontend.php';
		require_once AEO_PLUGIN_DIR . 'includes/frontend/class-aeo-schema.php';
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	public function init() {
		( new Rest_Api() )->register();

		if ( is_admin() ) {
			new Admin_Menu();
			new Assets();
			new \AEO_Meta_Box();
			new \AEO_Questions();
		} else {
			new Frontend_Manager();
			new \AEO_Frontend();
			\AEO\Frontend\Llms_Txt::register();
			\AEO\Frontend\Ai_Crawlers::register();
			\AEO\Frontend\Referral_Tracker::register();
			\AEO\Content\Meta_Service::register();
			if ( ! defined( 'AEO_NEW_SYSTEM' ) || ! AEO_NEW_SYSTEM ) {
				new \AEO_Schema();
			}
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once AEO_PLUGIN_DIR . 'src/CLI/Commands.php';
			\WP_CLI::add_command( 'aeo', 'AEO\\CLI\\Commands' );
		}

		do_action( 'aeo_optimizer/loaded' );
	}

	/**
	 * Recalculate AEO score on post save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, Settings::get_post_types(), true ) ) {
			return;
		}

		$this->flush_llms_cache();
		\AEO\Content\Content_Extractor::forget( $post_id );

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		( new Score_Engine() )->calculate( $post_id );
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Upgrader::unschedule_events();
		wp_clear_scheduled_hook( \AEO\Services\IndexNow::HOOK );
		wp_clear_scheduled_hook( \AEO\Core\License::CRON );
		\AEO\Frontend\Llms_Txt::flush();
		flush_rewrite_rules();
	}
}
