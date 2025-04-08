<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get paired users for a stake
 */
function mkps_get_paired_users($stake_id) {
    $creator_id = get_post_field('post_author', $stake_id);
    $acceptor_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);

    if (!$creator_id || !$acceptor_id) {
        return false;
    }

    $creator_team_id = get_user_meta($creator_id, 'assigned_team_id', true);
    $acceptor_team_id = get_user_meta($acceptor_id, 'assigned_team_id', true);

    if (!$creator_team_id || !$acceptor_team_id) {
        return false;
    }

    return [
        'creator' => [
            'user_id' => $creator_id,
            'team_id' => $creator_team_id,
            'team_name' => mkps_get_team_name($creator_id, true),
        ],
        'acceptor' => [
            'user_id' => $acceptor_id,
            'team_id' => $acceptor_team_id,
            'team_name' => mkps_get_team_name($acceptor_id, true),
        ],
    ];
}

/**
 * Validate pairing before event creation
 */
function mkps_validate_pairing_before_event($stake_id, $creator_id, $accepting_user_id) {
    $creator_team = mkps_get_team_name($creator_id, true);
    $acceptor_team = mkps_get_team_name($accepting_user_id, true);

    if (!$creator_team || !$acceptor_team) {
        $message = __('Stake pairing failed: Both users must have assigned teams.', 'mk-point-staker');
        wp_mail(get_userdata($creator_id)->user_email, __('Stake Pairing Failed', 'mk-point-staker'), $message);
        wp_mail(get_userdata($accepting_user_id)->user_email, __('Stake Pairing Failed', 'mk-point-staker'), $message);
        return false;
    }
    return true;
}
add_action('mkps_stake_accepted', function($stake_id, $creator_id, $accepting_user_id) {
    if (!mkps_validate_pairing_before_event($stake_id, $creator_id, $accepting_user_id)) {
        update_post_meta($stake_id, '_mkps_stake_accepted', false);
        update_post_meta($stake_id, '_mkps_stake_accepted_by', '');
        mycred_add('stake_refund', $accepting_user_id, get_post_meta($stake_id, '_mkps_stake_points', true), __('Points refunded due to pairing failure', 'mk-point-staker'));
    }
}, 5, 3);