<?php
/*
 * Plugin Name: MK Point Staker
 * Description: A plugin for managing point-based stakes with SportsPress integration.
 * Version: 1.0.0
 * Author: Mikael Kraft
 * Text Domain: mk-point-staker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Plugin Class
 */
class MK_Point_Staker {

    private static $instance;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define constants
     */
    private function define_constants() {
        define( 'MKPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'MKPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-post-type.php';
        require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-assets.php';
        require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-meta-boxes.php';
        require_once MKPS_PLUGIN_DIR . 'includes/notifications.php';
        require_once MKPS_PLUGIN_DIR . 'includes/pairing.php';
        require_once MKPS_PLUGIN_DIR . 'includes/sportspress-integration.php';
        require_once MKPS_PLUGIN_DIR . 'includes/stake-form-handler.php';
        require_once MKPS_PLUGIN_DIR . 'includes/profile-integration.php';
        require_once MKPS_PLUGIN_DIR . 'includes/activation-deactivation.php';
        require_once MKPS_PLUGIN_DIR . 'includes/ajax-handlers.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        register_activation_hook( __FILE__, array( 'MKPS_Activation_Deactivation', 'on_activation' ) );
        register_deactivation_hook( __FILE__, array( 'MKPS_Activation_Deactivation', 'on_deactivation' ) );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'mk-point-staker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_style( 'mkps-admin-style', MKPS_PLUGIN_URL . 'assets/css/admin-style.css', array(), '1.0.0' );
        wp_enqueue_script( 'mkps-admin-script', MKPS_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), '1.0.0', true );
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_front_scripts() {
        wp_enqueue_style( 'mkps-frontend-style', MKPS_PLUGIN_URL . 'assets/css/frontend-style.css', array(), '1.0.0' );
        wp_enqueue_script( 'mkps-frontend-script', MKPS_PLUGIN_URL . 'assets/js/frontend-script.js', array( 'jquery' ), '1.0.0', true );
        wp_localize_script( 'mkps-frontend-script', 'mkps_ajax', array(
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'stake_nonce'      => wp_create_nonce( 'mkps_stake_nonce' ),
            'accept_code_nonce' => wp_create_nonce( 'mkps_accept_code' )
        ) );
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            __( 'MK Point Staker Settings', 'mk-point-staker' ),
            __( 'Point Staker', 'mk-point-staker' ),
            'manage_options',
            'mkps-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'MK Point Staker Settings', 'mk-point-staker' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'mkps_settings_group' );
                do_settings_sections( 'mkps-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'mkps_settings_group', 'mkps_options', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'mkps_general_section',
            __( 'General Settings', 'mk-point-staker' ),
            null,
            'mkps-settings'
        );

        add_settings_field(
            'commission_rate',
            __( 'Commission Rate', 'mk-point-staker' ),
            array( $this, 'commission_rate_callback' ),
            'mkps-settings',
            'mkps_general_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $sanitized['commission_rate'] = isset( $input['commission_rate'] ) ? floatval( $input['commission_rate'] ) : 0.05;
        return $sanitized;
    }

    /**
     * Commission rate field callback
     */
    public function commission_rate_callback() {
        $options = get_option( 'mkps_options', array( 'commission_rate' => 0.05 ) );
        ?>
        <input type="number" step="0.01" min="0" max="1" name="mkps_options[commission_rate]" value="<?php echo esc_attr( $options['commission_rate'] ); ?>">
        <p class="description"><?php _e( 'Set the commission rate for stakes (e.g., 0.05 for 5%).', 'mk-point-staker' ); ?></p>
        <?php
    }
}

// Initialize plugin
MK_Point_Staker::get_instance();