<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Stake Form Shortcode
 */
function mkps_stake_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to create a stake.', 'mk-point-staker') . '</p>';
    }

    ob_start();
    ?>
    <div id="mkps-stake-feedback"></div>
    <form id="mkps-stake-form" class="mkps-stake-form">
        <label for="mkps_stake_points"><?php _e('Stake Points', 'mk-point-staker'); ?></label>
        <input type="number" name="mkps_stake_points" id="mkps_stake_points" min="1" required>
        <button type="submit"><?php _e('Create Stake', 'mk-point-staker'); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('mkps_stake_form', 'mkps_stake_form_shortcode');

/**
 * Handle Stake Form Submission via AJAX
 */
function mkps_handle_stake_form_submission() {
    check_ajax_referer('mkps_nonce', 'nonce');

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

    if (!mkps_has_sufficient_points($user_id, $stake_points)) {
        wp_send_json_error(['message' => __('Insufficient points to create stake. Current balance: ' . mycred_get_users_balance($user_id), 'mk-point-staker')]);
    }

    mkps_deduct_points($user_id, $stake_points);

    $connection_code = wp_generate_password(8, false);
    $stake_data = [
        'post_title'  => sprintf(__('Stake by %s for %d points', 'mk-point-staker'), $team_name, $stake_points),
        'post_type'   => 'stake',
        'post_status' => 'publish',
        'post_author' => $user_id,
    ];
    $stake_id = wp_insert_post($stake_data, true); // Return WP_Error on failure

    if (is_wp_error($stake_id)) {
        wp_send_json_error(['message' => __('Failed to create stake: ') . $stake_id->get_error_message()]);
    }

    if ($stake_id) {
        update_post_meta($stake_id, '_mkps_stake_points', $stake_points);
        update_post_meta($stake_id, '_mkps_connection_code', $connection_code);
        wp_send_json_success(['message' => sprintf(__('Stake created successfully! Connection Code: <strong>%s</strong>', 'mk-point-staker'), $connection_code)]);
    } else {
        wp_send_json_error(['message' => __('Failed to create stake (unknown error).', 'mk-point-staker')]);
    }
}
add_action('wp_ajax_mkps_submit_stake', 'mkps_handle_stake_form_submission');

/**
 * Handle Stake Cancellation via AJAX
 */
function mkps_handle_stake_cancellation() {
    check_ajax_referer('mkps_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to cancel a stake.', 'mk-point-staker')]);
    }

    $stake_id = intval($_POST['stake_id']);
    $user_id = get_current_user_id();
    $stake_author_id = get_post_field('post_author', $stake_id);

    if ($user_id !== $stake_author_id) {
        wp_send_json_error(['message' => __('You can only cancel your own stakes.', 'mk-point-staker')]);
    }

    if (get_post_meta($stake_id, '_mkps_stake_accepted', true)) {
        wp_send_json_error(['message' => __('Cannot cancel an accepted stake.', 'mk-point-staker')]);
    }

    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $refund_points = floor($stake_points * 0.9); // 90% refund

    wp_delete_post($stake_id, true);
    mycred_add('stake_cancel_refund', $user_id, $refund_points, __('Points refunded (90%) for canceled stake', 'mk-point-staker'));

    wp_send_json_success(['message' => sprintf(__('Stake canceled. %d points refunded.', 'mk-point-staker'), $refund_points)]);
}
add_action('wp_ajax_mkps_cancel_stake', 'mkps_handle_stake_cancellation');