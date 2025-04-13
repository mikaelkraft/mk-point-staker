<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create SportsPress Event for Paired Teams
 */
function mkps_create_sportspress_event($stake_id, $creator_id, $accepting_user_id) {
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $connection_code = get_post_meta($stake_id, '_mkps_connection_code', true);

    // Get teams for both users
    $creator_team_id = get_user_meta($creator_id, '_sp_team_id', true);
    $accepting_team_id = get_user_meta($accepting_user_id, '_sp_team_id', true);

    // If users don't have teams assigned, create temporary ones
    if (!$creator_team_id) {
        $creator_team_id = mkps_create_temp_team($creator_id);
    }
    if (!$accepting_team_id) {
        $accepting_team_id = mkps_create_temp_team($accepting_user_id);
    }

    // Get team names
    $creator_team_name = sp_get_team_name($creator_team_id, $creator_id);
    $accepting_team_name = sp_get_team_name($accepting_team_id, $accepting_user_id);

    $event_data = array(
        'post_title'   => sprintf(__('%s vs %s - Stake', 'mk-point-staker'), $creator_team_name, $accepting_team_name),
        'post_content' => sprintf(__('A stake match for %d points. Connection Code: %s', 'mk-point-staker'), $stake_points, $connection_code),
        'post_type'    => 'sp_event',
        'post_status'  => 'publish',
        'post_author'  => $creator_id,
    );

    $event_id = wp_insert_post($event_data);
    if ($event_id) {
        // Set teams and other event meta
        update_post_meta($event_id, 'sp_team', array($creator_team_id, $accepting_team_id));
        update_post_meta($event_id, 'sp_home_team', $creator_team_id);
        update_post_meta($event_id, 'sp_away_team', $accepting_team_id);
        update_post_meta($event_id, 'sp_format', 'Friendly');
        update_post_meta($event_id, 'sp_minutes', 90);
        update_post_meta($event_id, 'sp_mode', 'team');

        // Link event to stake
        update_post_meta($stake_id, '_mkps_event_id', $event_id);
        update_post_meta($event_id, '_mkps_stake_id', $stake_id);
        update_post_meta($event_id, '_mkps_stake_points', $stake_points);

        mkps_notify_event_creation($event_id, $creator_id, $accepting_user_id);
    }
    
    return $event_id;
}

/**
 * Helper function to get team name with fallbacks
 */
function sp_get_team_name($team_id, $user_id) {
    $name = '';
    if ($team_id) {
        $team_meta = get_post_meta($team_id);
        $name = $team_meta['sp_team_short_name'][0] ?? '';
        if (empty($name)) {
            $name = $team_meta['sp_team'][0] ?? '';
        }
        if (empty($name)) {
            $name = get_the_title($team_id);
        }
    }
    if (empty($name)) {
        $name = get_userdata($user_id)->display_name;
    }
    return $name;
}

/**
 * Create temporary team for user if none exists
 */
function mkps_create_temp_team($user_id) {
    $user = get_userdata($user_id);
    $team_data = array(
        'post_title'  => $user->display_name . ' ' . __('Team', 'mk-point-staker'),
        'post_type'   => 'sp_team',
        'post_status' => 'publish',
        'post_author' => $user_id,
    );
    
    $team_id = wp_insert_post($team_data);
    if ($team_id) {
        update_user_meta($user_id, '_sp_team_id', $team_id);
        update_post_meta($team_id, 'sp_short_name', $user->display_name);
    }
    return $team_id;
}

/**
 * Notify Users of Event Creation
 */
function mkps_notify_event_creation($event_id, $creator_id, $accepting_user_id) {
    $event_link = get_permalink($event_id);
    $stake_id = get_post_meta($event_id, '_mkps_stake_id', true);
    $stake_link = get_permalink($stake_id);
    $message = sprintf(__('A new stake match has been scheduled. View the event details here: %s | Stake details: %s', 'mk-point-staker'), $event_link, $stake_link);
    
    // Notify both users
    wp_mail(get_userdata($creator_id)->user_email, __('New Stake Match Event', 'mk-point-staker'), $message);
    wp_mail(get_userdata($accepting_user_id)->user_email, __('New Stake Match Event', 'mk-point-staker'), $message);
    
    // Add sitewide notifications
    mkps_add_sitewide_notification($creator_id, 'event_created', array(
        'event_id' => $event_id,
        'opponent_id' => $accepting_user_id,
        'message' => sprintf(__('Your stake match against %s has been scheduled', 'mk-point-staker'), get_userdata($accepting_user_id)->display_name)
    ));
    
    mkps_add_sitewide_notification($accepting_user_id, 'event_created', array(
        'event_id' => $event_id,
        'opponent_id' => $creator_id,
        'message' => sprintf(__('Your stake match against %s has been scheduled', 'mk-point-staker'), get_userdata($creator_id)->display_name)
    ));
}

/**
 * Record Match Results with admin commission
 */
function mkps_record_match_results($event_id, $winner_id, $is_draw = false) {
    $stake_id = get_post_meta($event_id, '_mkps_stake_id', true);
    if (!$stake_id) return;

    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $creator_id = get_post_field('post_author', $stake_id);
    $accepting_user_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);

    // Get admin commission percentage (default 5%)
    $admin_commission = apply_filters('mkps_admin_commission', 5);
    $commission_factor = $admin_commission / 100;

    if ($is_draw) {
        // Refund both players
        mycred_add('stake_draw_refund', $creator_id, $stake_points, __('Points refunded for a draw in stake match', 'mk-point-staker'));
        mycred_add('stake_draw_refund', $accepting_user_id, $stake_points, __('Points refunded for a draw in stake match', 'mk-point-staker'));
        
        update_post_meta($stake_id, '_mkps_stake_completed', true);
        update_post_meta($stake_id, '_mkps_stake_result', 'draw');
        
        // Update user stats
        $creator_draws = get_user_meta($creator_id, '_mkps_draws', true) ?: 0;
        $acceptor_draws = get_user_meta($accepting_user_id, '_mkps_draws', true) ?: 0;
        update_user_meta($creator_id, '_mkps_draws', $creator_draws + 1);
        update_user_meta($accepting_user_id, '_mkps_draws', $acceptor_draws + 1);
        
        mkps_notify_match_result($stake_id, null, true);
    } else {
        $loser_id = $winner_id === $creator_id ? $accepting_user_id : $creator_id;
        
        // Calculate payout with commission
        $total_pot = $stake_points * 2;
        $admin_share = round($total_pot * $commission_factor);
        $winner_share = $total_pot - $admin_share;
        
        // Award points
        if ($winner_share > 0) {
            mycred_add('stake_victory', $winner_id, $winner_share, __('Points awarded for winning a stake match', 'mk-point-staker'));
        }
        
        // Take admin share if commission is set
        if ($admin_share > 0) {
            $admin_id = get_option('mkps_admin_user', 1); // Default to user ID 1 if not set
            mycred_add('stake_commission', $admin_id, $admin_share, __('Commission from stake match', 'mk-point-staker'));
        }
        
        update_post_meta($stake_id, '_mkps_stake_winner', $winner_id);
        update_post_meta($stake_id, '_mkps_stake_completed', true);
        update_post_meta($stake_id, '_mkps_stake_result', 'win');
        
        // Update user stats
        $winner_wins = get_user_meta($winner_id, '_mkps_wins', true) ?: 0;
        $loser_losses = get_user_meta($loser_id, '_mkps_losses', true) ?: 0;
        update_user_meta($winner_id, '_mkps_wins', $winner_wins + 1);
        update_user_meta($loser_id, '_mkps_losses', $loser_losses + 1);
        
        mkps_notify_match_result($stake_id, $winner_id);
    }
}

/**
 * Notify Users of Match Result
 */
function mkps_notify_match_result($stake_id, $winner_id, $is_draw = false) {
    $creator_id = get_post_field('post_author', $stake_id);
    $accepting_user_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);

    if ($is_draw) {
        $message = __('The match has concluded in a draw. Your points have been refunded.', 'mk-point-staker');
        $notification_type = 'draw';
    } else {
        $winner_name = get_userdata($winner_id)->display_name;
        $message = sprintf(__('The match has concluded, and %s is the winner!', 'mk-point-staker'), $winner_name);
        $notification_type = $winner_id == $creator_id ? 'win' : 'loss';
    }

    // Email notifications
    wp_mail(get_userdata($creator_id)->user_email, __('Match Result', 'mk-point-staker'), $message);
    wp_mail(get_userdata($accepting_user_id)->user_email, __('Match Result', 'mk-point-staker'), $message);
    
    // Sitewide notifications
    mkps_add_sitewide_notification($creator_id, 'match_result', array(
        'stake_id' => $stake_id,
        'result' => $notification_type,
        'message' => $message
    ));
    
    mkps_add_sitewide_notification($accepting_user_id, 'match_result', array(
        'stake_id' => $stake_id,
        'result' => $winner_id == $accepting_user_id ? 'win' : 'loss',
        'message' => $message
    ));
}

/**
 * Handle Match Results from SportsPress
 */
function mkps_handle_sportspress_result_update($event_id) {
    $stake_id = get_post_meta($event_id, '_mkps_stake_id', true);
    if (!$stake_id) {
        return;
    }

    $results = get_post_meta($event_id, 'sp_results', true);
    $teams = get_post_meta($event_id, 'sp_team', true);
    if (!$results || !$teams) {
        return;
    }

    $team_scores = array();
    foreach ($teams as $team_id) {
        $team_scores[$team_id] = isset($results[$team_id]['points']) ? intval($results[$team_id]['points']) : 0;
    }

    $creator_id = get_post_field('post_author', $stake_id);
    $accepting_user_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);
    $creator_team_id = get_user_meta($creator_id, '_sp_team_id', true);
    $accepting_team_id = get_user_meta($accepting_user_id, '_sp_team_id', true);

    if ($team_scores[$creator_team_id] === $team_scores[$accepting_team_id]) {
        mkps_record_match_results($event_id, null, true);
    } elseif ($team_scores[$creator_team_id] > $team_scores[$accepting_team_id]) {
        mkps_record_match_results($event_id, $creator_id);
    } else {
        mkps_record_match_results($event_id, $accepting_user_id);
    }
}
add_action('sportspress_event_updated', 'mkps_handle_sportspress_result_update');