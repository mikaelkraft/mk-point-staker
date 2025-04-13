<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send Notification on New Stake Creation
 */
function mkps_notify_new_stake_creation($post_id) {
    if (get_post_type($post_id) !== 'stake' || get_post_status($post_id) !== 'publish') {
        return;
    }

    $stake_author_id = get_post_field('post_author', $post_id);
    $stake_link = get_permalink($post_id);
    $message = sprintf(
        __('A new stake has been created by %s. View it here: %s', 'mk-point-staker'),
        get_the_author_meta('display_name', $stake_author_id),
        $stake_link
    );

    $users = get_users(array('exclude' => array($stake_author_id)));
    foreach ($users as $user) {
        mkps_send_notification($user->ID, __('New Stake Available', 'mk-point-staker'), $message, $post_id);
        mkps_send_email_notification($user->user_email, __('New Stake Available', 'mk-point-staker'), $message);
        
        // Add sitewide notification
        mkps_add_sitewide_notification($user->ID, 'new_stake', array(
            'stake_id' => $post_id,
            'author_id' => $stake_author_id,
            'message' => $message
        ));
    }
}
add_action('save_post_stake', 'mkps_notify_new_stake_creation');

/**
 * Send a Notification to a User
 */
function mkps_send_notification($user_id, $title, $message, $post_id = 0) {
    $notifications = get_user_meta($user_id, '_mkps_notifications', true);
    if (!is_array($notifications)) {
        $notifications = array();
    }

    $notifications[] = array(
        'title'   => $title,
        'message' => $message,
        'post_id' => $post_id,
        'date'    => current_time('mysql'),
        'read'    => false,
    );

    update_user_meta($user_id, '_mkps_notifications', $notifications);
    
    // Update unread count
    $unread_count = count(array_filter($notifications, function($n) {
        return !$n['read'];
    }));
    update_user_meta($user_id, '_mkps_unread_count', $unread_count);
}

/**
 * Display Notifications for the Logged-in User
 */
function mkps_display_user_notifications() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to view notifications.', 'mk-point-staker') . '</p>';
    }

    $user_id = get_current_user_id();
    $notifications = get_user_meta($user_id, '_mkps_notifications', true);
    $output = '<div class="mkps-frontend-container">';

    if (!empty($notifications) && is_array($notifications)) {
        usort($notifications, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        $unread_count = count(array_filter($notifications, function ($n) {
            return !$n['read'];
        }));
        $available_stakes = count(array_filter($notifications, function ($n) {
            return !empty($n['post_id']) && get_post_type($n['post_id']) === 'stake' && !get_post_meta($n['post_id'], '_mkps_stake_accepted', true);
        }));

        $output .= '<button id="mkps-notifications-toggle" class="button">' . __('Notifications', 'mk-point-staker') . ' <span class="mkps-unread-count">(' . $unread_count . ' unread)</span> <span class="mkps-available-count">(' . $available_stakes . ' available)</span></button>';
        $output .= '<div id="mkps-notifications-panel" style="display:none;">';

        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        $per_page = 5;
        $offset = ($paged - 1) * $per_page;
        $paginated_notifications = array_slice($notifications, $offset, $per_page);
        $total_pages = ceil(count($notifications) / $per_page);

        $output .= '<h3>' . __('Notifications', 'mk-point-staker') . '</h3>';
        $output .= '<ul class="mkps-notifications">';
        foreach ($paginated_notifications as $notification) {
            $class = $notification['read'] ? 'mkps-read' : 'mkps-unread';
            $output .= '<li class="' . esc_attr($class) . '">';
            $output .= '<strong>' . esc_html($notification['title']) . '</strong><br>';
            $output .= esc_html($notification['message']) . '<br>';
            $output .= '<em>' . esc_html(date('F j, Y, g:i a', strtotime($notification['date']))) . '</em>';

            if (!empty($notification['post_id']) && get_post_type($notification['post_id']) === 'stake') {
                $stake_id = $notification['post_id'];
                $is_accepted = get_post_meta($stake_id, '_mkps_stake_accepted', true);
                $stake_author_id = get_post_field('post_author', $stake_id);
                $accepted_by = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);
                $connection_code = get_post_meta($stake_id, '_mkps_connection_code', true);

                if (!$is_accepted) {
                    $output .= '<button class="mkps-accept-button button button-primary" data-stake-id="' . esc_attr($stake_id) . '">' . __('Accept Stake', 'mk-point-staker') . ' <span class="mkps-spinner" style="display:none;"></span></button>';
                } elseif ($user_id === $stake_author_id) {
                    $output .= '<span class="mkps-accepted-text">' . sprintf(__('Stake Accepted - Connection Code: <strong>%s</strong>', 'mk-point-staker'), esc_html($connection_code)) . '</span>';
                } elseif ($user_id === intval($accepted_by)) {
                    $output .= '<span class="mkps-accepted-text">' . sprintf(__('You Accepted - Connection Code: %s', 'mk-point-staker'), esc_html($connection_code)) . '</span>';
                } else {
                    $output .= '<span class="mkps-accepted-text">' . __('Stake has been accepted', 'mk-point-staker') . '</span>';
                }
            }
            $output .= '</li>';
        }
        $output .= '</ul>';

        $output .= '<div class="mkps-pagination">';
        $output .= paginate_links(array(
            'base'    => add_query_arg('paged', '%#%'),
            'format'  => '?paged=%#%',
            'current' => $paged,
            'total'   => $total_pages,
            'prev_text' => __('« Previous'),
            'next_text' => __('Next »'),
        ));
        $output .= '</div>';

        $output .= '</div>';
    } else {
        $output .= '<p>' . __('No notifications.', 'mk-point-staker') . '</p>';
    }

    $output .= '</div>';
    return $output;
}
add_shortcode('mkps_user_notifications', 'mkps_display_user_notifications');

/**
 * Send Email Notification
 */
function mkps_send_email_notification($email, $subject, $message) {
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($email, $subject, $message, $headers);
}

/**
 * Handle AJAX Stake Acceptance
 */
function mkps_handle_ajax_stake_acceptance() {
    check_ajax_referer('mkps_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in.', 'mk-point-staker')));
    }

    $stake_id = intval($_POST['stake_id']);
    $user_id = get_current_user_id();
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    $stake_author_id = get_post_field('post_author', $stake_id);

    if ($user_id === $stake_author_id) {
        wp_send_json_error(array('message' => __('You cannot accept your own stake.', 'mk-point-staker')));
    }

    if (mkps_has_sufficient_points($user_id, $stake_points) && mkps_has_sufficient_points($stake_author_id, $stake_points)) {
        mkps_deduct_points($user_id, $stake_points);
        update_post_meta($stake_id, '_mkps_stake_accepted', true);
        update_post_meta($stake_id, '_mkps_stake_accepted_by', $user_id);

        $connection_code = get_post_meta($stake_id, '_mkps_connection_code', true);
        mkps_notify_stake_acceptance($stake_id, $user_id);
        do_action('mkps_stake_accepted', $stake_id, $stake_author_id, $user_id);

        wp_send_json_success(array('message' => sprintf(__('Stake accepted! Connection Code: %s', 'mk-point-staker'), $connection_code)));
    } else {
        wp_send_json_error(array('message' => __('Insufficient points to accept stake.', 'mk-point-staker')));
    }
}
add_action('wp_ajax_mkps_accept_stake', 'mkps_handle_ajax_stake_acceptance');

/**
 * Mark Notification as Read
 */
function mkps_mark_notification_read($post_id) {
    if (is_user_logged_in() && get_post_type($post_id) === 'stake') {
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, '_mkps_notifications', true);
        if (is_array($notifications)) {
            foreach ($notifications as &$notification) {
                if ($notification['post_id'] == $post_id) {
                    $notification['read'] = true;
                }
            }
            update_user_meta($user_id, '_mkps_notifications', $notifications);
            
            // Update unread count
            $unread_count = count(array_filter($notifications, function($n) {
                return !$n['read'];
            }));
            update_user_meta($user_id, '_mkps_unread_count', $unread_count);
        }
    }
}
add_action('wp', function() {
    if (is_singular('stake')) {
        mkps_mark_notification_read(get_the_ID());
    }
});

/**
 * Add sitewide notification
 */
function mkps_add_sitewide_notification($user_id, $type, $data) {
    $notifications = get_option('mkps_sitewide_notifications', array());
    
    $notification = array(
        'user_id' => $user_id,
        'type'    => $type,
        'data'    => $data,
        'time'    => time(),
        'read'    => false
    );
    
    $notifications[] = $notification;
    update_option('mkps_sitewide_notifications', $notifications);
    
    // Update user's unread count
    $unread_count = get_user_meta($user_id, '_mkps_unread_count', true) ?: 0;
    update_user_meta($user_id, '_mkps_unread_count', $unread_count + 1);
}

/**
 * Mark sitewide notification as read via AJAX
 */
function mkps_mark_sitewide_notification_read() {
    check_ajax_referer('mkps_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
    if ($index < 0) {
        wp_send_json_error(array('message' => 'Invalid index'));
    }
    
    $notifications = get_option('mkps_sitewide_notifications', array());
    if (isset($notifications[$index])) {
        $notifications[$index]['read'] = true;
        update_option('mkps_sitewide_notifications', $notifications);
        
        // Update user's unread count
        $user_id = get_current_user_id();
        $unread_count = get_user_meta($user_id, '_mkps_unread_count', true) ?: 0;
        if ($unread_count > 0) {
            update_user_meta($user_id, '_mkps_unread_count', $unread_count - 1);
        }
        
        wp_send_json_success();
    }
    
    wp_send_json_error(array('message' => 'Notification not found'));
}
add_action('wp_ajax_mkps_mark_notification_read', 'mkps_mark_sitewide_notification_read');

/**
 * AJAX handler for getting notification counts
 */
function mkps_get_notification_counts() {
    check_ajax_referer('mkps_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Not logged in'));
    }
    
    $user_id = get_current_user_id();
    $notifications = get_user_meta($user_id, '_mkps_notifications', true);
    
    $unread = 0;
    $available = 0;
    
    if (is_array($notifications)) {
        $unread = count(array_filter($notifications, function($n) {
            return !$n['read'];
        }));
        
        $available = count(array_filter($notifications, function($n) {
            return !empty($n['post_id']) && get_post_type($n['post_id']) === 'stake' && !get_post_meta($n['post_id'], '_mkps_stake_accepted', true);
        }));
    }
    
    wp_send_json_success(array(
        'unread' => $unread,
        'available' => $available
    ));
}
add_action('wp_ajax_mkps_get_notification_counts', 'mkps_get_notification_counts');