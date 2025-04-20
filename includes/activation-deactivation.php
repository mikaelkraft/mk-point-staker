<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Activation and Deactivation Hooks
 */
class MKPS_Activation_Deactivation {

    /**
     * On plugin activation
     */
    public static function on_activation() {
        // Check for required plugins
        $errors = array();

        // Check myCRED
        if ( ! is_plugin_active( 'mycred/mycred.php' ) ) {
            $errors[] = __( 'myCRED is required for MK Point Staker to function.', 'mk-point-staker' );
        }

        // Check Ultimate Member
        if ( ! class_exists( 'UM' ) ) {
            $errors[] = __( 'Ultimate Member is required for MK Point Staker to function.', 'mk-point-staker' );
        }

        // Check SportsPress (free or pro)
        $sportspress_active = ( is_plugin_active( 'sportspress/sportspress.php' ) || is_plugin_active( 'sportspress-pro/sportspress-pro.php' ) );
        if ( ! $sportspress_active ) {
            $errors[] = __( 'SportsPress (Free or Pro) is required for MK Point Staker to function.', 'mk-point-staker' );
        }

        // If errors exist, prevent activation
        if ( ! empty( $errors ) ) {
            wp_die(
                '<p>' . implode( '</p><p>', $errors ) . '</p>',
                __( 'Plugin Activation Error', 'mk-point-staker' ),
                array( 'back_link' => true )
            );
        }

        // Call activator
        MKPS_Activator::activate();
    }

    /**
     * On plugin deactivation
     */
    public static function on_deactivation() {
        // Clean up if needed
    }
}

// Register hooks
register_activation_hook( MKPS_PLUGIN_DIR . 'mk-point-staker.php', array( 'MKPS_Activation_Deactivation', 'on_activation' ) );
register_deactivation_hook( MKPS_PLUGIN_DIR . 'mk-point-staker.php', array( 'MKPS_Activation_Deactivation', 'on_deactivation' ) );