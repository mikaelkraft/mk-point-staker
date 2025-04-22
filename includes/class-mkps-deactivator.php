<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MKPS_Deactivator class
 * Handles plugin deactivation tasks
 */
class MKPS_Deactivator {

    /**
     * Deactivation hook
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}