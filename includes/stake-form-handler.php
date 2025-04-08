<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display Stake Creation Form
 */
function mkps_stake_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to create a stake.', 'mk-point-staker') . '</p>';
    }
    ob_start();
    ?>
    <form id="mkps-stake-form" method="post">
        <label for="mkps_stake_points"><?php _e('Stake Points:', 'mk-point-staker'); ?></label>
        <input type="number" id="mkps_stake_points" name="mkps_stake_points" min="1" required>
        <button type="submit"><?php _e('Create Stake', 'mk-point-staker'); ?></button>
        <div id="mkps-stake-feedback"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('mkps_stake_form', 'mkps_stake_form_shortcode');

/**
 * Handle Stake Form Submission via AJAX
 */
function mkps_handle_stake_form_submission() {
    if (!check_ajax_referer('mkps_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'mk-point-staker')]);
    }

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to create a stake.', 'mk-point-staker')]);
    }

    $user_id = get_current_user_id();
    $stake_points = intval($_POST['mkps_stake_points']);

    if ($stake_points <= 0) {
        wp_send_json_error(['message' => __('Stake points must be greater than zero.', 'mk-point-staker')]);
    }

    $team_name = mkps_get_team_name($user_id, true);
    if (!$team_name) {
        wp_send_json_error(['message' => __('You must have a team assigned to create a stake.', 'mk-point-staker')]);
    }

    if (!function_exists('mycred_get_users_balance') || !mkps_has_sufficient_points($user_id, $stake_points)) {
        wp_send_json_error(['message' => __('Insufficient points: ') . (function_exists('mycred_get_users_balance') ? mycred_get_users_balance($user_id) : 'myCRED not active')]);
    }

    mkps_deduct_points($user_id, $stake_points);

    $connection_code = wp_generate_password(8, false);
    $stake_data = [
        'post_title'  => sprintf(__('Stake by %s for %d points', 'mk-point-staker'), $team_name, $stake_points),
        'post_type'   => 'stake',
        'post_status' => 'publish',
        'post_author' => $user_id,
    ];
    $stake_id = wp_insert_post($stake_data, true);

    if (is_wp_error($stake_id)) {
        mycred_add('stake_refund', $user_id, $stake_points, __('Refunded due to stake creation failure', 'mk-point-staker'));
        wp_send_json_error(['message' => __('Failed to create stake: ') . $stake_id->get_error_message()]);
    }

    update_post_meta($stake_id, '_mkps_stake_points', $stake_points);
    update_post_meta($stake_id, '_mkps_connection_code', $connection_code);

    wp_send_json_success(['message' => sprintf(__('Stake created successfully! Connection Code: <strong>%s</strong>', 'mk-point-staker'), $connection_code)]);
}
add_action('wp_ajax_mkps_submit_stake', 'mkps_handle_stake_form_submission');