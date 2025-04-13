<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to Display User Points
 */
function mkps_display_user_points_shortcode($atts) {
    $atts = shortcode_atts(array(
        'user_id' => 0,
    ), $atts, 'mkps_user_points');

    $current_user_id = get_current_user_id();
    $viewed_user_id = $atts['user_id'] ? intval($atts['user_id']) : (bp_displayed_user_id() ?: (get_query_var('author') ?: $current_user_id));
    $user_id = ($current_user_id && $current_user_id === $viewed_user_id) || !$atts['user_id'] ? $current_user_id : $viewed_user_id;
    
    if (!$user_id) {
        return '<p>' . __('No user specified or logged in.', 'mk-point-staker') . '</p>';
    }

    $user_points = mycred_get_users_balance($user_id);
    return '<div class="mkps-user-points">
        <h3>' . __('Points Balance', 'mk-point-staker') . '</h3>
        <div class="mkps-points-display">
            <span class="mkps-points-value">' . $user_points . '</span>
            <span class="mkps-points-label">' . __('points', 'mk-point-staker') . '</span>
        </div>
    </div>';
}
add_shortcode('mkps_user_points', 'mkps_display_user_points_shortcode');

/**
 * Shortcode to Display User Stakes with Wins/Losses/Draws
 */
function mkps_display_user_stakes_shortcode($atts) {
    $atts = shortcode_atts(array(
        'user_id' => 0,
    ), $atts, 'mkps_user_stakes');

    $current_user_id = get_current_user_id();
    $viewed_user_id = $atts['user_id'] ? intval($atts['user_id']) : (bp_displayed_user_id() ?: (get_query_var('author') ?: $current_user_id));
    $user_id = ($current_user_id && $current_user_id === $viewed_user_id) || !$atts['user_id'] ? $current_user_id : $viewed_user_id;

    if (!$user_id) {
        return '<p>' . __('No user specified or logged in.', 'mk-point-staker') . '</p>';
    }

    $user_stakes = new WP_Query(array(
        'post_type'      => 'stake',
        'author'         => $user_id,
        'posts_per_page' => 10,
        'meta_query'     => array(
            array(
                'key'   => '_mkps_stake_completed',
                'value' => '1',
            ),
        ),
    ));

    $wins = get_user_meta($user_id, '_mkps_wins', true) ?: 0;
    $losses = get_user_meta($user_id, '_mkps_losses', true) ?: 0;
    $draws = get_user_meta($user_id, '_mkps_draws', true) ?: 0;

    ob_start();
    echo '<div class="mkps-user-stats">';
    echo '<h3>' . __('Staking Stats', 'mk-point-staker') . '</h3>';
    echo '<div class="mkps-stats-grid">';
    echo '<div class="mkps-stat-box wins"><span class="stat-value">' . $wins . '</span><span class="stat-label">' . __('Wins', 'mk-point-staker') . '</span></div>';
    echo '<div class="mkps-stat-box losses"><span class="stat-value">' . $losses . '</span><span class="stat-label">' . __('Losses', 'mk-point-staker') . '</span></div>';
    echo '<div class="mkps-stat-box draws"><span class="stat-value">' . $draws . '</span><span class="stat-label">' . __('Draws', 'mk-point-staker') . '</span></div>';
    echo '</div>';
    
    if ($user_stakes->have_posts()) {
        echo '<h4>' . __('Recent Stakes', 'mk-point-staker') . '</h4>';
        echo '<ul class="mkps-stakes-list">';
        while ($user_stakes->have_posts()) {
            $user_stakes->the_post();
            $stake_points = get_post_meta(get_the_ID(), '_mkps_stake_points', true);
            $result = get_post_meta(get_the_ID(), '_mkps_stake_result', true);
            $opponent_id = get_post_meta(get_the_ID(), '_mkps_stake_accepted_by', true);
            
            $result_class = '';
            $result_text = '';
            switch ($result) {
                case 'win':
                    $result_class = 'mkps-win';
                    $result_text = __('Won', 'mk-point-staker');
                    break;
                case 'loss':
                    $result_class = 'mkps-loss';
                    $result_text = __('Lost', 'mk-point-staker');
                    break;
                case 'draw':
                    $result_class = 'mkps-draw';
                    $result_text = __('Draw', 'mk-point-staker');
                    break;
            }
            
            echo '<li class="' . $result_class . '">';
            echo '<span class="stake-points">' . $stake_points . ' ' . __('points', 'mk-point-staker') . '</span>';
            echo '<span class="stake-result">' . $result_text . '</span>';
            if ($opponent_id) {
                echo '<span class="stake-opponent">' . __('vs', 'mk-point-staker') . ' ' . get_userdata($opponent_id)->display_name . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>' . __('No completed stakes found.', 'mk-point-staker') . '</p>';
    }
    echo '</div>';
    
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('mkps_user_stakes', 'mkps_display_user_stakes_shortcode');

/**
 * Shortcode to Display Available Stakes Button with Count
 */
function mkps_available_stakes_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to view available stakes.', 'mk-point-staker') . '</p>';
    }

    $user_id = get_current_user_id();
    $notifications = get_user_meta($user_id, '_mkps_notifications', true);
    $available_stakes = 0;

    if (!empty($notifications) && is_array($notifications)) {
        $available_stakes = count(array_filter($notifications, function ($n) {
            return !empty($n['post_id']) && get_post_type($n['post_id']) === 'stake' && !get_post_meta($n['post_id'], '_mkps_stake_accepted', true);
        }));
    }

    ob_start();
    ?>
    <button class="mkps-available-stakes-button" data-nonce="<?php echo wp_create_nonce('mkps_nonce'); ?>">
        <?php _e('Available Stakes', 'mk-point-staker'); ?>
        <?php if ($available_stakes > 0): ?>
            <span class="mkps-notification-bubble"><?php echo $available_stakes; ?></span>
        <?php endif; ?>
    </button>
    <div id="mkps-stakes-panel" style="display:none;">
        <?php echo do_shortcode('[mkps_user_notifications]'); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('mkps_available_stakes', 'mkps_available_stakes_shortcode');

/**
 * Add to WordPress User Profile Page (Admin)
 */
function mkps_add_to_wp_user_profile($user) {
    if (!is_user_logged_in()) {
        return;
    }
    echo '<h2>' . __('MK Point Staker Info', 'mk-point-staker') . '</h2>';
    echo do_shortcode('[mkps_user_points user_id="' . $user->ID . '"]');
    echo do_shortcode('[mkps_user_stakes user_id="' . $user->ID . '"]');
}
add_action('show_user_profile', 'mkps_add_to_wp_user_profile');
add_action('edit_user_profile', 'mkps_add_to_wp_user_profile');

/**
 * Add to BuddyPress Profile (if active)
 */
function mkps_add_to_buddypress_profile() {
    if (!function_exists('bp_is_active') || !bp_is_user()) {
        return;
    }
    echo '<div class="mkps-profile-section">';
    echo do_shortcode('[mkps_user_points]');
    echo do_shortcode('[mkps_user_stakes]');
    echo '</div>';
}
add_action('bp_before_member_header', 'mkps_add_to_buddypress_profile');

/**
 * Filter to Allow Custom Profile Manager Integration
 */
function mkps_profile_integration_output() {
    $output = '';
    $output .= do_shortcode('[mkps_user_points]');
    $output .= do_shortcode('[mkps_user_stakes]');
    return apply_filters('mkps_profile_integration_output', $output);
}

/**
 * Add Available Stake Count to UM Profile Menu
 */
function mkps_add_stake_count_to_um_menu($menu_items) {
    if (!function_exists('UM') || !is_user_logged_in()) {
        return $menu_items;
    }

    $user_id = get_current_user_id();
    $notifications = get_user_meta($user_id, '_mkps_notifications', true);
    $unread_count = 0;
    $available_stakes = 0;

    if (!empty($notifications) && is_array($notifications)) {
        $unread_count = count(array_filter($notifications, function ($n) {
            return !$n['read'];
        }));
        $available_stakes = count(array_filter($notifications, function ($n) {
            return !empty($n['post_id']) && get_post_type($n['post_id']) === 'stake' && !get_post_meta($n['post_id'], '_mkps_stake_accepted', true);
        }));
    }

    if (isset($menu_items['notifications'])) {
        $menu_items['notifications']['title'] .= ' <span class="um-notification-live-count mkps-stake-count">' . $unread_count . '</span>';
        if ($available_stakes > 0) {
            $menu_items['notifications']['title'] .= ' <span class="mkps-available-count">(' . $available_stakes . ' available)</span>';
        }
    }

    return $menu_items;
}
add_filter('um_profile_menu', 'mkps_add_stake_count_to_um_menu', 10, 1);

/**
 * Shortcode to display sitewide notifications
 */
function mkps_display_sitewide_notifications() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to view notifications.', 'mk-point-staker') . '</p>';
    }

    $user_id = get_current_user_id();
    $notifications = get_option('mkps_sitewide_notifications', array());
    $user_notifications = array_filter($notifications, function($n) use ($user_id) {
        return $n['user_id'] == $user_id;
    });

    if (empty($user_notifications)) {
        return '<p>' . __('No notifications.', 'mk-point-staker') . '</p>';
    }

    ob_start();
    echo '<div class="mkps-sitewide-notifications">';
    echo '<h3>' . __('Recent Activity', 'mk-point-staker') . '</h3>';
    echo '<ul>';
    
    foreach ($user_notifications as $index => $notification) {
        $class = $notification['read'] ? 'read' : 'unread';
        echo '<li class="' . $class . '" data-index="' . $index . '">';
        
        switch ($notification['type']) {
            case 'event_created':
                echo '<div class="notification-icon">🎯</div>';
                echo '<div class="notification-content">';
                echo '<p>' . esc_html($notification['data']['message']) . '</p>';
                echo '<small>' . human_time_diff($notification['time'], time()) . ' ago</small>';
                echo '</div>';
                break;
                
            case 'match_result':
                $icon = '';
                $result = $notification['data']['result'];
                if ($result == 'win') {
                    $icon = '🏆';
                } elseif ($result == 'loss') {
                    $icon = '😞';
                } else {
                    $icon = '🤝';
                }
                echo '<div class="notification-icon">' . $icon . '</div>';
                echo '<div class="notification-content">';
                echo '<p>' . esc_html($notification['data']['message']) . '</p>';
                echo '<small>' . human_time_diff($notification['time'], time()) . ' ago</small>';
                echo '</div>';
                break;
        }
        
        echo '</li>';
    }
    
    echo '</ul>';
    echo '</div>';
    
    return ob_get_clean();
}
add_shortcode('mkps_sitewide_notifications', 'mkps_display_sitewide_notifications');