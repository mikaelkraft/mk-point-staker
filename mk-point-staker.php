<?php
/*
Plugin Name: MK Point Staker
Plugin URI: https://ivytag.live
Description: A plugin that integrates myCRED, SportsPress, and Profile managers allow users stake points in online matches against each other. A stake is created by a user, and then a sportspress event is automatically generated between the author and any other user who accepts the stake first. The stake amount decided by the author is deducted as points from the involved users. The winner is decided when the match result is update. Results ending in a draw causes a refund to both parties.. 
Version: 1.0.1
Author: Mikael Kraft
Author URI: https://x.com/mikael_kraft
License: GPL2
Text Domain: mk-point-staker
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct access
}

// Define plugin constants
define( 'MKPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MKPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Autoload necessary files
require_once MKPS_PLUGIN_DIR . 'includes/activation-deactivation.php';
require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-activator.php';
require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-deactivator.php';

// Register activation and deactivation hooks
register_activation_hook( __FILE__, array( 'MKPS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MKPS_Deactivator', 'deactivate' ) );

// Include additional files for functionality
require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-post-type.php';
require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-meta-boxes.php';
require_once MKPS_PLUGIN_DIR . 'includes/notifications.php';
require_once MKPS_PLUGIN_DIR . 'includes/pairing.php';
require_once MKPS_PLUGIN_DIR . 'includes/sportspress-integration.php';
require_once MKPS_PLUGIN_DIR . 'includes/stake-form-handler.php';
require_once MKPS_PLUGIN_DIR . 'includes/profile-integration.php';
require_once MKPS_PLUGIN_DIR . 'includes/class-mkps-assets.php';

// Initialize custom post types and meta boxes
function mkps_initialize() {
    MKPS_Post_Type::register();
    MKPS_Meta_Boxes::init();
}
add_action( 'init', 'mkps_initialize' );

// Enqueue frontend and admin assets
add_action( 'wp_enqueue_scripts', 'mkps_enqueue_frontend_assets' );
add_action( 'admin_enqueue_scripts', array( 'MKPS_Assets', 'enqueue_admin_assets' ) );

function mkps_enqueue_frontend_assets() {
    wp_enqueue_script(
        'mkps-frontend-script',
        plugins_url( 'assets/js/frontend-script.js', __FILE__ ),
        array( 'jquery' ),
        '1.0',
        true
    );

    wp_localize_script( 'mkps-frontend-script', 'mkps_ajax_object', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'mkps_nonce' ),
    ));

    wp_enqueue_style( 'mkps-frontend-style', plugins_url( 'assets/css/frontend-style.css', __FILE__ ), array(), '1.0.0' );
}

// Check for required plugins
function mkps_check_required_plugins() {
    if ( ! is_plugin_active( 'mycred/mycred.php' ) || ! is_plugin_active( 'sportspress-pro/sportspress-pro.php' ) || ! class_exists( 'UM' ) ) {
        add_action( 'admin_notices', 'mkps_required_plugins_notice' );
    }
}
add_action( 'admin_init', 'mkps_check_required_plugins' );

// Display admin notice if required plugins are missing
function mkps_required_plugins_notice() {
    echo '<div class="notice notice-warning is-dismissible">
             <p><strong>Warning:</strong> MK Point Staker requires myCRED, SportsPress, and Ultimate Member plugins to be installed and active for full functionality.</p>
         </div>';
}