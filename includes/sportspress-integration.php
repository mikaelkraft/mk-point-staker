<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prefetch all SportsPress teams
 */
function mkps_prefetch_teams() {
    $teams = get_posts([
        'post_type' => 'sp_team',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ]);
    $team_data = [];
    foreach ($teams as $team) {
        $abbreviation = get_post_meta($team->ID, 'sp_abbreviation', true);
        $team_data[$team->ID] = [
            'long_name' => $team->post_title,
            'short_name' => $abbreviation ?: $team->post_title,
        ];
    }
    return $team_data;
}

/**
 * Get the user's assigned team name
 */
function mkps_get_team_name($user_id, $use_short_name = true) {
    $team_id = get_user_meta($user_id, 'assigned_team_id', true);
    if (!$team_id || get_post_type($team_id) !== 'sp_team') {
        return false;
    }
    $abbreviation = get_post_meta($team_id, 'sp_abbreviation', true);
    $long_name = get_the_title($team_id);
    return $use_short_name && $abbreviation ? $abbreviation : $long_name;
}

/**
 * Notify users when a SportsPress event is created
 */
function mkps_notify_event_creation($event_id, $stake_id, $creator_id, $acceptor_id) {
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $connection_code = get_post_meta($stake_id, '_mkps_connection_code', true);
    $event_link = get_permalink($event_id);
    $creator_team = mkps_get_team_name($creator_id, true);
    $acceptor_team = mkps_get_team_name($acceptor_id, true);

    $message = sprintf(
        __('A new event has been created for your stake of %d points. Teams: %s vs %s. Connection Code: %s. View it here: %s', 'mk-point-staker'),
        $stake_points, $creator_team, $acceptor_team, $connection_code, $event_link
    );

    wp_mail(get_userdata($creator_id)->user_email, __('Stake Event Created', 'mk-point-staker'), $message);
    wp_mail(get_userdata($acceptor_id)->user_email, __('Stake Event Created', 'mk-point-staker'), $message);
}

/**
 * Create a SportsPress event when a stake is accepted
 */
function mkps_create_sp_event($stake_id, $creator_id, $acceptor_id) {
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $creator_team = get_user_meta($creator_id, 'assigned_team_id', true);
    $acceptor_team = get_user_meta($acceptor_id, 'assigned_team_id', true);

    if (!$creator_team || !$acceptor_team) {
        return;
    }

    $event_data = [
        'post_title'  => sprintf(__('Stake Event: %d Points', 'mk-point-staker'), $stake_points),
        'post_type'   => 'sp_event',
        'post_status' => 'publish',
    ];
    $event_id = wp_insert_post($event_data);

    if ($event_id) {
        update_post_meta($event_id, 'sp_team', [$creator_team, $acceptor_team]);
        update_post_meta($event_id, 'sp_main_result', '');
        update_post_meta($stake_id, '_mkps_event_id', $event_id);
        mkps_notify_event_creation($event_id, $stake_id, $creator_id, $acceptor_id);
    }
}
add_action('mkps_stake_accepted', 'mkps_create_sp_event', 10, 3);

/**
 * Handle event result updates and award points or refund
 */
function mkps_handle_event_result_update($post_id, $post_after, $post_before) {
    if (get_post_type($post_id) !== 'sp_event' || $post_after->post_status !== 'publish') {
        return;
    }

    $stake_id = get_post_meta($post_id, '_mkps_event_id', true);
    if (!$stake_id) {
        $stakes = get_posts([
            'post_type' => 'stake',
            'meta_key' => '_mkps_event_id',
            'meta_value' => $post_id,
            'posts_per_page' => 1,
        ]);
        if (empty($stakes)) {
            return;
        }
        $stake_id = $stakes[0]->ID;
    }

    if (get_post_meta($stake_id, '_mkps_stake_resolved', true)) {
        return; // Already processed
    }

    $old_result = get_post_meta($post_id, 'sp_main_result', true);
    $new_result = isset($_POST['sp_main_result']) ? sanitize_text_field($_POST['sp_main_result']) : $old_result;
    if ($old_result === $new_result || empty($new_result)) {
        return;
    }

    $creator_id = get_post_field('post_author', $stake_id);
    $acceptor_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $creator_team = get_user_meta($creator_id, 'assigned_team_id', true);
    $acceptor_team = get_user_meta($acceptor_id, 'assigned_team_id', true);
    $teams = get_post_meta($post_id, 'sp_team', true);

    if (!$creator_team || !$acceptor_team || !$stake_points || empty($teams)) {
        return;
    }

    $total_points = $stake_points * 2;
    $creator_team_index = array_search($creator_team, $teams);
    $acceptor_team_index = array_search($acceptor_team, $teams);

    if ($new_result === $creator_team) {
        mycred_add('stake_winner', $creator_id, $total_points, __('Won stake event', 'mk-point-staker'), $stake_id);
        update_user_meta($creator_id, '_mkps_wins', (int)get_user_meta($creator_id, '_mkps_wins', true) + 1);
        update_user_meta($acceptor_id, '_mkps_losses', (int)get_user_meta($acceptor_id, '_mkps_losses', true) + 1);
        $winner_message = sprintf(__('You won the stake event for %d points! Teams: %s vs %s', 'mk-point-staker'), $total_points, mkps_get_team_name($creator_id, true), mkps_get_team_name($acceptor_id, true));
        $loser_message = sprintf(__('You lost the stake event. Teams: %s vs %s', 'mk-point-staker'), mkps_get_team_name($creator_id, true), mkps_get_team_name($acceptor_id, true));
        wp_mail(get_userdata($creator_id)->user_email, __('Stake Event Won', 'mk-point-staker'), $winner_message);
        wp_mail(get_userdata($acceptor_id)->user_email, __('Stake Event Lost', 'mk-point-staker'), $loser_message);
    } elseif ($new_result === $acceptor_team) {
        mycred_add('stake_winner', $acceptor_id, $total_points, __('Won stake event', 'mk-point-staker'), $stake_id);
        update_user_meta($acceptor_id, '_mkps_wins', (int)get_user_meta($acceptor_id, '_mkps_wins', true) + 1);
        update_user_meta($creator_id, '_mkps_losses', (int)get_user_meta($creator_id, '_mkps_losses', true) + 1);
        $winner_message = sprintf(__('You won the stake event for %d points! Teams: %s vs %s', 'mk-point-staker'), $total_points, mkps_get_team_name($creator_id, true), mkps_get_team_name($acceptor_id, true));
        $loser_message = sprintf(__('You lost the stake event. Teams: %s vs %s', 'mk-point-staker'), mkps_get_team_name($creator_id, true), mkps_get_team_name($acceptor_id, true));
        wp_mail(get_userdata($acceptor_id)->user_email, __('Stake Event Won', 'mk-point-staker'), $winner_message);
        wp_mail(get_userdata($creator_id)->user_email, __('Stake Event Lost', 'mk-point-staker'), $loser_message);
    } elseif ($new_result === 'draw' || $new_result === '') {
        mycred_add('stake_refunded', $creator_id, $stake_points, __('Stake event ended in a draw - points refunded', 'mk-point-staker'), $stake_id);
        mycred_add('stake_refunded', $acceptor_id, $stake_points, __('Stake event ended in a draw - points refunded', 'mk-point-staker'), $stake_id);
        update_user_meta($creator_id, '_mkps_draws', (int)get_user_meta($creator_id, '_mkps_draws', true) + 1);
        update_user_meta($acceptor_id, '_mkps_draws', (int)get_user_meta($acceptor_id, '_mkps_draws', true) + 1);
        $draw_message = sprintf(__('The stake event ended in a draw. %d points refunded. Teams: %s vs %s', 'mk-point-staker'), $stake_points, mkps_get_team_name($creator_id, true), mkps_get_team_name($acceptor_id, true));
        wp_mail(get_userdata($creator_id)->user_email, __('Stake Event Draw', 'mk-point-staker'), $draw_message);
        wp_mail(get_userdata($acceptor_id)->user_email, __('Stake Event Draw', 'mk-point-staker'), $draw_message);
    }

    update_post_meta($stake_id, '_mkps_stake_resolved', true);
}
add_action('save_post_sp_event', 'mkps_handle_event_result_update', 20, 3);

/**
 * Display user's Wins, Draws, Losses
 */
function mkps_display_user_stats_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to view your stats.', 'mk-point-staker') . '</p>';
    }

    $user_id = get_current_user_id();
    $wins = (int)get_user_meta($user_id, '_mkps_wins', true);
    $draws = (int)get_user_meta($user_id, '_mkps_draws', true);
    $losses = (int)get_user_meta($user_id, '_mkps_losses', true);

    ob_start();
    ?>
    <div class="mkps-user-stats">
        <h3><?php _e('Your Stake Stats', 'mk-point-staker'); ?></h3>
        <ul>
            <li><strong><?php _e('Wins:', 'mk-point-staker'); ?></strong> <?php echo esc_html($wins); ?></li>
            <li><strong><?php _e('Draws:', 'mk-point-staker'); ?></strong> <?php echo esc_html($draws); ?></li>
            <li><strong><?php _e('Losses:', 'mk-point-staker'); ?></strong> <?php echo esc_html($losses); ?></li>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('mkps_user_stats', 'mkps_display_user_stats_shortcode');