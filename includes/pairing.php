<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render Accept Button for Stake
 */
function mkps_render_accept_button($stake_id) {
    if (is_user_logged_in()) {
        $is_accepted = get_post_meta($stake_id, '_mkps_stake_accepted', true);
        $is_cancelled = get_post_meta($stake_id, '_mkps_stake_cancelled', true);
        $is_expired = get_post_meta($stake_id, '_mkps_stake_expired', true);

        if ($is_cancelled) {
            echo '<div class="mkps-notice mkps-error">' . __('This stake has been cancelled by the creator.', 'mk-point-staker') . '</div>';
        } elseif ($is_expired) {
            echo '<div class="mkps-notice mkps-error">' . __('This stake has expired.', 'mk-point-staker') . '</div>';
        } elseif (!$is_accepted) {
            echo '<form method="post" class="mkps-accept-stake-form">';
            echo '<input type="hidden" name="stake_id" value="' . esc_attr($stake_id) . '">';
            echo '<input type="submit" name="mkps_accept_stake" value="' . __('Accept Stake', 'mk-point-staker') . '" class="button button-primary">';
            echo wp_nonce_field('mkps_accept_stake_nonce', 'mkps_accept_stake_nonce_field');
            echo '</form>';
        } else {
            echo '<div class="mkps-notice">' . __('This stake has already been accepted.', 'mk-point-staker') . '</div>';
        }
    }
}

/**
 * Handle Stake Acceptance
 */
function mkps_handle_stake_acceptance() {
    if (isset($_POST['mkps_accept_stake'], $_POST['mkps_accept_stake_nonce_field']) &&
         wp_verify_nonce($_POST['mkps_accept_stake_nonce_field'], 'mkps_accept_stake_nonce')) {

        $stake_id = intval($_POST['stake_id']);
        $user_id = get_current_user_id();
        $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
        $stake_author_id = get_post_field('post_author', $stake_id);

        // Check if stake is already cancelled or expired
        $cancelled = get_post_meta($stake_id, '_mkps_stake_cancelled', true);
        $expired = get_post_meta($stake_id, '_mkps_stake_expired', true);
        if ($cancelled || $expired) {
            add_filter('the_content', function ($content) {
                return $content . '<div class="mkps-error">' . __('This stake is no longer available.', 'mk-point-staker') . '</div>';
            });
            return;
        }

        if ($user_id === $stake_author_id) {
            add_filter('the_content', function ($content) {
                return $content . '<div class="mkps-error">' . __('You cannot accept your own stake.', 'mk-point-staker') . '</div>';
            });
            return;
        }

        if (mkps_has_sufficient_points($user_id, $stake_points)) {
            if (!mkps_has_sufficient_points($stake_author_id, $stake_points)) {
                add_filter('the_content', function ($content) {
                    return $content . '<div class="mkps-error">' . __('The stake creator no longer has sufficient points.', 'mk-point-staker') . '</div>';
                });
                return;
            }

            mkps_deduct_points($user_id, $stake_points);

            update_post_meta($stake_id, '_mkps_stake_accepted', true);
            update_post_meta($stake_id, '_mkps_stake_accepted_by', $user_id);

            $connection_code = get_post_meta($stake_id, '_mkps_connection_code', true);
            mkps_notify_stake_acceptance($stake_id, $user_id);
            do_action('mkps_stake_accepted', $stake_id, $stake_author_id, $user_id);

            add_filter('the_content', function ($content) use ($connection_code, $user_id, $stake_author_id) {
                if ($user_id === get_current_user_id()) {
                    $content .= '<div class="mkps-success">' . sprintf(__('You accepted the stake! Connection Code: %s', 'mk-point-staker'), esc_html($connection_code)) . '</div>';
                } else {
                    $content .= '<div class="mkps-notice">' . __('Stake has been accepted.', 'mk-point-staker') . '</div>';
                }
                return $content;
            });
        } else {
            add_filter('the_content', function ($content) {
                return $content . '<div class="mkps-error">' . __('You don\'t have enough points to accept this stake.', 'mk-point-staker') . '</div>';
            });
        }
    }
}
add_action('template_redirect', 'mkps_handle_stake_acceptance');

/**
 * Check If User Has Sufficient Points
 */
function mkps_has_sufficient_points($user_id, $points) {
    $current_points = mycred_get_users_balance($user_id);
    return ($current_points >= $points);
}

/**
 * Deduct Points from User
 */
function mkps_deduct_points($user_id, $points) {
    mycred_subtract('stake_deduction', $user_id, $points, __('stake participation', 'mk-point-staker'));
}

/**
 * Notify Users of Stake Acceptance
 */
function mkps_notify_stake_acceptance($stake_id, $acceptor_id) {
    $stake_author_id = get_post_field('post_author', $stake_id);
    $acceptor_name = get_userdata($acceptor_id)->display_name;
    $message = sprintf(__('%s has accepted your stake!', 'mk-point-staker'), $acceptor_name);
    
    // Email notification
    wp_mail(get_userdata($stake_author_id)->user_email, __('Stake Accepted', 'mk-point-staker'), $message);
    
    // Add to notifications
    mkps_send_notification($stake_author_id, __('Stake Accepted', 'mk-point-staker'), $message, $stake_id);
    
    // Sitewide notification
    mkps_add_sitewide_notification($stake_author_id, 'stake_accepted', array(
        'stake_id' => $stake_id,
        'acceptor_id' => $acceptor_id,
        'message' => $message
    ));
}

/**
 * Shortcode to display stake status
 */
function mkps_stake_status_shortcode($atts) {
    if (!is_singular('stake')) {
        return '';
    }

    $stake_id = get_the_ID();
    $is_accepted = get_post_meta($stake_id, '_mkps_stake_accepted', true);
    $is_cancelled = get_post_meta($stake_id, '_mkps_stake_cancelled', true);
    $is_expired = get_post_meta($stake_id, '_mkps_stake_expired', true);
    $connection_code = get_post_meta($stake_id, '_mkps_connection_code', true);
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);

    ob_start();
    echo '<div class="mkps-stake-status">';
    echo '<h3>' . __('Stake Details', 'mk-point-staker') . '</h3>';
    echo '<p><strong>' . __('Points:', 'mk-point-staker') . '</strong> ' . $stake_points . '</p>';
    
    if ($is_cancelled) {
        echo '<div class="mkps-notice mkps-error">' . __('This stake has been cancelled.', 'mk-point-staker') . '</div>';
    } elseif ($is_expired) {
        echo '<div class="mkps-notice mkps-error">' . __('This stake has expired.', 'mk-point-staker') . '</div>';
    } elseif ($is_accepted) {
        $acceptor_id = get_post_meta($stake_id, '_mkps_stake_accepted_by', true);
        $acceptor_name = get_userdata($acceptor_id)->display_name;
        echo '<div class="mkps-notice mkps-success">';
        echo sprintf(__('Accepted by: %s', 'mk-point-staker'), $acceptor_name) . '<br>';
        echo sprintf(__('Connection Code: %s', 'mk-point-staker'), $connection_code);
        echo '</div>';
    } else {
        echo '<div class="mkps-notice">' . __('Waiting for acceptance...', 'mk-point-staker') . '</div>';
    }
    
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('mkps_stake_status', 'mkps_stake_status_shortcode');