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
use AEO\Frontend\Frontend_Manager;
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
	public $version = '1.0.1';

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
		register_activation_hook( $this->file, array( Activator::class, 'activate' ) );
		register_deactivation_hook( $this->file, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 2 );
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

		if ( ! in_array( $post->post_type, array( 'post', 'page', 'product' ), true ) ) {
			return;
		}

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
	public function deactivate() {
		flush_rewrite_rules();
	}
}
