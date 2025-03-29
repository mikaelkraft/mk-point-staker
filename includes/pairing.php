<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assign a SportsPress team to a user (for admin or user profile use)
 */
function mkps_assign_team_to_user($user_id, $team_id) {
    if (!get_post($team_id) || get_post_type($team_id) !== 'sp_team') {
        return false; // Invalid team ID
    }
    update_user_meta($user_id, '_sp_team_id', $team_id);
    return true;
}

/**
 * Get paired users for a stake
 */
function mkps_get_paired_users($stake_id) {
    $creator_id = get_post_field('post_author', $stake_id);
    $acceptor_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);

    if (!$creator_id || !$acceptor_id) {
        return false; // Stake not fully paired yet
    }

    $creator_team_id = get_user_meta($creator_id, '_sp_team_id', true);
    $acceptor_team_id = get_user_meta($acceptor_id, '_sp_team_id', true);

    if (!$creator_team_id || !$acceptor_team_id) {
        return false; // Missing team assignments
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
        // Notify creator if pairing fails due to missing teams
        $message = __('Stake pairing failed: Both users must have assigned teams.', 'mk-point-staker');
        wp_mail(get_userdata($creator_id)->user_email, __('Stake Pairing Failed', 'mk-point-staker'), $message);
        wp_mail(get_userdata($accepting_user_id)->user_email, __('Stake Pairing Failed', 'mk-point-staker'), $message);
        return false;
    }

    return true;
}
add_action('mkps_stake_accepted', function($stake_id, $creator_id, $accepting_user_id) {
    if (!mkps_validate_pairing_before_event($stake_id, $creator_id, $accepting_user_id)) {
        update_post_meta($stake_id, '_mkps_stake_accepted', false); // Revert acceptance if pairing fails
        update_post_meta($stake_id, '_mkps_stake_accepted_by', '');
        mycred_add('stake_refund', $accepting_user_id, get_post_meta($stake_id, '_mkps_stake_points', true), __('Points refunded due to pairing failure', 'mk-point-staker'));
    }
}, 5, 3); // Run before event creation (priority 5)

/**
 * Shortcode to display pairing form (e.g., for users to select their team)
 */
function mkps_team_pairing_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to assign a team.', 'mk-point-staker') . '</p>';
    }

    $user_id = get_current_user_id();
    $current_team_id = get_user_meta($user_id, '_sp_team_id', true);
    $teams = mkps_prefetch_teams();

    if (isset($_POST['mkps_team_submit']) && wp_verify_nonce($_POST['mkps_team_nonce'], 'mkps_team_assign')) {
        $selected_team_id = intval($_POST['mkps_team_id']);
        if (mkps_assign_team_to_user($user_id, $selected_team_id)) {
            return '<p>' . __('Team assigned successfully!', 'mk-point-staker') . '</p>';
        } else {
            return '<p>' . __('Failed to assign team.', 'mk-point-staker') . '</p>';
        }
    }

    ob_start();
    ?>
    <form method="post" class="mkps-team-form">
        <label for="mkps_team_id"><?php _e('Select Your Team', 'mk-point-staker'); ?></label>
        <select name="mkps_team_id" id="mkps_team_id">
            <option value=""><?php _e('Choose a team', 'mk-point-staker'); ?></option>
            <?php foreach ($teams as $id => $data) : ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($current_team_id, $id); ?>>
                    <?php echo esc_html($data['long_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php wp_nonce_field('mkps_team_assign', 'mkps_team_nonce'); ?>
        <button type="submit" name="mkps_team_submit"><?php _e('Assign Team', 'mk-point-staker'); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('mkps_team_pairing', 'mkps_team_pairing_shortcode');