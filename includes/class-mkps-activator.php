<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Activation Handler
 */
class MKPS_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        // Set default options
        $options = get_option( 'mkps_options', array() );
        if ( empty( $options ) ) {
            $options = array(
                'commission_rate' => 0.05, // Default 5% commission
            );
            update_option( 'mkps_options', $options );
        }

        // Set admin user ID for commission
        if ( ! get_option( 'admin_user_id' ) ) {
            update_option( 'admin_user_id', 1 );
        }
    }
}