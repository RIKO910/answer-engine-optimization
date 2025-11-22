<?php
defined( 'ABSPATH' ) || exit;

/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
class Answer_Engine_Optimization {

    /**
     * Single instance of the class
     * @var Answer_Engine_Optimization
     *
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * Frontend handler
     * @var AEO_Frontend
     *
     * @since 1.0.0
     */
    public $frontend;

    /**
     * Questions handler
     * @var AEO_Questions
     *
     * @since 1.0.0
     */
    public $question;

    /**
     * Schema handler
     * @var AEO_Schema
     *
     * @since 1.0.0
     */
    public $schema;

    /**
     * Admin handler
     * @var AEO_Admin
     *
     * @since 1.0.0
     */
    public $admin;

    /**
     * Meta handler
     * @var AEO_Meta_Box
     *
     * @since 1.0.0
     */
    public $meta_box;

    /**
     * File.
     *
     * @var string $file File
     *
     * @since 1.0.0
     */
    public string $file;

    /**
     * Version.
     *
     * @var mixed|string $version Version
     *
     * @since 1.0.0
     */
    public string $version = '1.0.0';

    /**
     * Get instance
     *
     * @since 1.0.0
     */
    public static function get_instance($file = '') {
        if (null === self::$instance) {
            self::$instance = new self($file);
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct($file = '') {
        $this->file = $file;
        $this->activation();
        $this->deactivation();
        $this->define_constant();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Activation.
     *
     * @return void
     * @since 1.0.0
     */
    private function activation() {
        register_activation_hook( $this->file, array( $this, 'activation_hook' ) );
    }

    /**
     * Activation hook.
     *
     * @return void
     * @since 1.0.0
     */
    private function activation_hook() {
        $default_options = array(
            'aeo_enable_schema' => 1,
            'aeo_auto_questions' => 1,
            'aeo_target_questions' => '',
            'aeo_voice_optimization' => 1
        );

        if (!get_option('aeo_settings')) {
            update_option('aeo_settings', $default_options);
        }

        flush_rewrite_rules();
    }

    /**
     * Deactivation.
     *
     * @return void
     * @since 1.0.0
     */
    private function deactivation() {
        register_deactivation_hook( $this->file, array( $this, 'deactivation_hook' ) );
    }

    /**
     * Deactivation hook
     *
     * @return void
     * @since 1.0.0
     */
    private function deactivation_hook() {
        flush_rewrite_rules();
    }

    /**
     * Define Constant.
     *
     * @return void
     * @since 1.0.0
     */
    private function define_constant() {
        define('AEO_VERSION', $this->version);
        define('AEO_PLUGIN_DIR', plugin_dir_path($this->file));
        define('AEO_PLUGIN_URL', plugin_dir_url($this->file));
        define('AEO_PLUGIN_BASENAME', plugin_basename($this->file));
    }

    /**
     * Include required files
     *
     * @since 1.0.0
     */
    private function includes() {
        require_once AEO_PLUGIN_DIR . 'includes/admin/class-aeo-admin.php';
        require_once AEO_PLUGIN_DIR . 'includes/admin/class-aeo-questions.php';
        require_once AEO_PLUGIN_DIR . 'includes/admin/class-aeo-meta-box.php';
        require_once AEO_PLUGIN_DIR . 'includes/frontend/class-aeo-frontend.php';
        require_once AEO_PLUGIN_DIR . 'includes/frontend/class-aeo-schema.php';
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     * @return void
     */
    public function init() {
        if ( is_admin() ) {
            $this->admin    = new AEO_Admin();
            $this->meta_box = new AEO_Meta_Box();
            $this->question = new AEO_Questions();
        } else {
            $this->frontend = new AEO_Frontend();
            $this->schema   = new AEO_Schema();
        }
    }
}