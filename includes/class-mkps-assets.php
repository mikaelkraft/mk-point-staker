<?php
// Prevent direct access
if (!defined('ABSPATH')) {
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
    public static function enqueue_admin_assets($hook) {
        global $post_type;
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'stake') {
            wp_enqueue_style(
                'mkps-admin-style',
                plugins_url('assets/css/admin-style.css', dirname(__FILE__)),
                [],
                '1.0.0'
            );
            wp_enqueue_script(
                'mkps-admin-script',
                plugins_url('assets/js/admin-script.js', dirname(__FILE__)),
                ['jquery'],
                '1.0.0',
                true
            );

            $team_data = mkps_prefetch_teams();
            wp_localize_script('mkps-admin-script', 'mkpsData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('mkps_nonce'),
                'teams'   => $team_data,
            ]);
        }
    }

    /**
     * Enqueue front-end scripts and styles (fallback if not in mk-point-staker.php).
     */
    public static function enqueue_frontend_assets() {
        // Note: This is primarily handled in mk-point-staker.php, but kept as a fallback
        wp_enqueue_style(
            'mkps-frontend-style',
            plugins_url('assets/css/frontend-style.css', dirname(__FILE__)),
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'mkps-frontend-script',
            plugins_url('assets/js/frontend-script.js', dirname(__FILE__)),
            ['jquery'],
            '1.0.0',
            true
        );

        $team_data = mkps_prefetch_teams();
        wp_localize_script('mkps-frontend-script', 'mkpsFrontendData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mkps_nonce'), // Unified nonce
            'teams'   => $team_data,
        ]);
    }

    /**
     * Hooks into WordPress actions.
     */
    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        // Frontend enqueue is handled in mk-point-staker.php, but this can be uncommented if needed
        // add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
    }
}

MKPS_Assets::init();