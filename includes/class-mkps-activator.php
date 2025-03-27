<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MKPS_Activator class
 * Handles plugin activation tasks
 */
class MKPS_Activator {

    /**
     * Activation hook
     */
    public static function activate() {
        // Check for myCRED dependency
        if ( ! class_exists( 'myCRED_Core' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( __( 'MK Point Staker requires myCRED to be installed and active.', 'mk-point-staker' ) );
        }

        // Register Stake post type
        $post_type = new MKPS_Post_Type();
        $post_type->register();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set default options
        $default_options = array(
            'default_stake_points' => 10,
            'enable_notifications' => true,
        );
        if ( ! get_option( 'mkps_options' ) ) {
            update_option( 'mkps_options', $default_options );
        }
    }
}