<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MKPS_Assets
 * Responsible for enqueueing scripts and styles for the MK Point Staker plugin.
 */
class MKPS_Assets {

    /**
     * Enqueue admin scripts and styles.
     */
    public static function enqueue_admin_assets( $hook ) {
        global $post_type;
        if ( $post_type === 'stake' ) {
            wp_enqueue_style( 'mkps-admin-style', plugin_dir_url( __FILE__ ) . '../assets/css/admin-style.css', array(), '1.0.0' );
            wp_enqueue_script( 'mkps-admin-script', plugin_dir_url( __FILE__ ) . '../assets/js/admin-script.js', array( 'jquery' ), '1.0.0', true );

            wp_localize_script( 'mkps-admin-script', 'mkpsData', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mkps_nonce' ),
            ));
        }
    }

    /**
     * Enqueue front-end scripts and styles.
     */
    public static function enqueue_frontend_assets() {
        wp_enqueue_style( 'mkps-frontend-style', plugin_dir_url( __FILE__ ) . '../assets/css/frontend-style.css', array(), '1.0.0' );
        wp_enqueue_script( 'mkps-frontend-script', plugin_dir_url( __FILE__ ) . '../assets/js/frontend-script.js', array( 'jquery' ), '1.0.0', true );

        wp_localize_script( 'mkps-frontend-script', 'mkpsFrontendData', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mkps_frontend_nonce' ),
        ));
    }

    /**
     * Hooks into WordPress actions.
     */
    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
    }
}

MKPS_Assets::init();