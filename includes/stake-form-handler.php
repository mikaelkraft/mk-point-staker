<?php
// Prevent direct access to the file.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if user can create more stakes
 */
function mkps_can_create_stake($user_id) {
    $max_stakes = apply_filters('mkps_max_stakes_per_user', 3); // Allow filtering the max stakes
    
    $active_stakes = get_posts(array(
        'post_type' => 'stake',
        'author' => $user_id,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_mkps_stake_accepted',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_mkps_stake_cancelled',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_mkps_stake_expired',
                'compare' => 'NOT EXISTS'
            )
        ),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    return count($active_stakes) < $max_stakes;
}

/**
 * Get user's active stakes
 */
function mkps_get_user_active_stakes($user_id) {
    return get_posts(array(
        'post_type' => 'stake',
        'author' => $user_id,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_mkps_stake_accepted',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_mkps_stake_cancelled',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_mkps_stake_expired',
                'compare' => 'NOT EXISTS'
            )
        ),
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'DESC'
    ));
}

/**
 * Render Stake Form Shortcode with multi-stake support
 */
function mkps_stake_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to create a stake.', 'mk-point-staker') . '</p>';
    }

    $user_id = get_current_user_id();
    $active_stakes = mkps_get_user_active_stakes($user_id);
    
    ob_start();
    ?>
    <div id="mkps-stake-feedback"></div>
    
    <?php if (mkps_can_create_stake($user_id)) : ?>
        <form id="mkps-stake-form" class="mkps-stake-form">
            <label for="mkps_stake_points"><?php _e('Stake Points', 'mk-point-staker'); ?></label>
            <input type="number" name="mkps_stake_points" id="mkps_stake_points" min="1" required>
            
            <div class="mkps-form-actions">
                <button type="submit" name="mkps_submit_stake" class="button button-primary">
                    <?php _e('Create Stake', 'mk-point-staker'); ?>
                </button>
            </div>
        </form>
    <?php else : ?>
        <div class="mkps-stake-limit-reached">
            <p><?php _e('You have reached your maximum number of active stakes (3). Please cancel one or wait for acceptance before creating new stakes.', 'mk-point-staker'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($active_stakes)) : ?>
        <div class="mkps-active-stakes">
            <h4><?php _e('Your Active Stakes:', 'mk-point-staker'); ?></h4>
            <ul class="mkps-active-stakes-list">
                <?php foreach ($active_stakes as $stake) : 
                    $stake_points = get_post_meta($stake->ID, '_mkps_stake_points', true);
                    $expiration = get_post_meta($stake->ID, '_mkps_expiration', true);
                    $expires_in = $expiration ? human_time_diff(time(), $expiration) : '';
                    ?>
                    <li class="mkps-stake-item" data-stake-id="<?php echo $stake->ID; ?>">
                        <div class="mkps-stake-info">
                            <span class="mkps-stake-id">#<?php echo $stake->ID; ?></span>
                            <span class="mkps-stake-points"><?php echo $stake_points; ?> <?php _e('points', 'mk-point-staker'); ?></span>
                            <?php if ($expires_in) : ?>
                                <span class="mkps-stake-expiry"><?php printf(__('Expires in %s', 'mk-point-staker'), $expires_in); ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="mkps-cancel-stake button" data-stake-id="<?php echo $stake->ID; ?>">
                            <?php _e('Cancel', 'mk-point-staker'); ?>
                            <span class="mkps-spinner" style="display:none;"></span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
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
        wp_send_json_error(array('message' => __('You must be logged in to create a stake.', 'mk-point-staker')));
    }

    $user_id = get_current_user_id();
    $stake_points = intval($_POST['mkps_stake_points']);

    // Check stake limit
    if (!mkps_can_create_stake($user_id)) {
        wp_send_json_error(array('message' => __('You have reached your maximum number of active stakes (3).', 'mk-point-staker')));
    }

    if ($stake_points <= 0) {
        wp_send_json_error(array('message' => __('Stake points must be greater than zero.', 'mk-point-staker')));
    }

    if (mkps_has_sufficient_points($user_id, $stake_points)) {
        mkps_deduct_points($user_id, $stake_points);

        $connection_code = wp_generate_password(8, false);
        $stake_id = wp_insert_post(array(
            'post_title'  => sprintf(__('Stake by %s for %d points', 'mk-point-staker'), get_userdata($user_id)->display_name, $stake_points),
            'post_type'   => 'stake',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));

        if ($stake_id) {
            update_post_meta($stake_id, '_mkps_stake_points', $stake_points);
            update_post_meta($stake_id, '_mkps_connection_code', $connection_code);
            
            // Set default expiration (24 hours)
            $expiration = time() + (24 * 60 * 60);
            update_post_meta($stake_id, '_mkps_expiration', $expiration);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Stake created successfully! Connection Code: <strong>%s</strong>', 'mk-point-staker'), $connection_code),
                'refresh' => true
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create stake.', 'mk-point-staker')));
        }
    } else {
        wp_send_json_error(array('message' => __('Insufficient points to create stake.', 'mk-point-staker')));
    }
}
add_action('wp_ajax_mkps_submit_stake', 'mkps_handle_stake_form_submission');

/**
 * Handle Stake Cancellation via AJAX
 */
function mkps_handle_stake_cancellation() {
    check_ajax_referer('mkps_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('You must be logged in to cancel a stake.', 'mk-point-staker')));
    }

    $stake_id = intval($_POST['stake_id']);
    $user_id = get_current_user_id();
    
    // Verify user owns the stake
    $stake_author = get_post_field('post_author', $stake_id);
    if ($user_id != $stake_author) {
        wp_send_json_error(array('message' => __('You can only cancel your own stakes.', 'mk-point-staker')));
    }
    
    // Verify stake isn't already accepted
    $accepted = get_post_meta($stake_id, '_mkps_stake_accepted', true);
    if ($accepted) {
        wp_send_json_error(array('message' => __('This stake has already been accepted and cannot be cancelled.', 'mk-point-staker')));
    }
    
    // Refund points
    $stake_points = get_post_meta($stake_id, '_mkps_stake_points', true);
    mycred_add('stake_cancellation_refund', $user_id, $stake_points, __('Points refunded from cancelled stake', 'mk-point-staker'));
    
    // Update stake status
    update_post_meta($stake_id, '_mkps_stake_cancelled', true);
    wp_update_post(array(
        'ID' => $stake_id,
        'post_status' => 'trash'
    ));
    
    // Remove any related notifications
    $users = get_users(array('exclude' => array($user_id)));
    foreach ($users as $user) {
        $notifications = get_user_meta($user->ID, '_mkps_notifications', true);
        if (is_array($notifications)) {
            $notifications = array_filter($notifications, function($n) use ($stake_id) {
                return $n['post_id'] != $stake_id;
            });
            update_user_meta($user->ID, '_mkps_notifications', $notifications);
            
            // Update unread count
            $unread_count = count(array_filter($notifications, function($n) {
                return !$n['read'];
            }));
            update_user_meta($user->ID, '_mkps_unread_count', $unread_count);
        }
    }
    
    wp_send_json_success(array( 
        'message' => __('Stake cancelled successfully. Your points have been refunded.', 'mk-point-staker'),
        'refresh' => true
    ));
}
add_action('wp_ajax_mkps_cancel_stake', 'mkps_handle_stake_cancellation');

/**
 * Check for expired stakes and process them
 */
function mkps_check_expired_stakes() {
    $expired_stakes = get_posts(array(
        'post_type' => 'stake',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_mkps_expiration',
                'value' => time(),
                'compare' => '<=',
                'type' => 'NUMERIC'
            ),
            array(
                'key' => '_mkps_stake_accepted',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_mkps_stake_cancelled',
                'compare' => 'NOT EXISTS'
            )
        ),
        'posts_per_page' => -1
    ));

    foreach ($expired_stakes as $stake) {
        $stake_points = get_post_meta($stake->ID, '_mkps_stake_points', true);
        $user_id = $stake->post_author;
        
        // Refund points
        mycred_add('stake_expiration_refund', $user_id, $stake_points, __('Points refunded from expired stake', 'mk-point-staker'));
        
        // Mark as expired
        update_post_meta($stake->ID, '_mkps_stake_expired', true);
        wp_update_post(array(
            'ID' => $stake->ID,
            'post_status' => 'draft'
        ));
        
        // Notify user
        $message = sprintf(__('Your stake for %d points has expired and has been refunded.', 'mk-point-staker'), $stake_points);
        mkps_send_notification($user_id, __('Stake Expired', 'mk-point-staker'), $message);
        mkps_send_email_notification(get_userdata($user_id)->user_email, __('Stake Expired', 'mk-point-staker'), $message);
    }
}
add_action('init', 'mkps_check_expired_stakes');