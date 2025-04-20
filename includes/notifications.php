<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Send Notification to User
 */
function mkps_send_notification( $user_id, $title, $message, $stake_id = null ) {
    $stake_link = $stake_id ? get_permalink( $stake_id ) : '';
    $formatted_message = $stake_link ? sprintf( '%s <a href="%s">%s</a>', $message, esc_url( $stake_link ), __( 'View Stake', 'mk-point-staker' ) ) : $message;

    // UM Real-Time Notifications (primary)
    if ( function_exists( 'um_notifications_add_notification' ) ) {
        um_notifications_add_notification( array(
            'user_id'    => $user_id,
            'type'       => 'mkps_notification',
            'title'      => $title,
            'content'    => $formatted_message,
            'link'       => $stake_link,
        ) );
    }

    // myCRED Notification (fallback)
    if ( function_exists( 'mycred_add_notification' ) ) {
        mycred_add_notification( array(
            'user_id' => $user_id,
            'message' => $formatted_message,
            'type'    => 'mkps_notification',
        ) );
    }

    // Email Notification
    wp_mail( get_userdata( $user_id )->user_email, $title, wp_kses_post( $formatted_message ) );
}

/**
 * Notify All Users of New Stake
 */
function mkps_notify_new_stake( $stake_id ) {
    $stake_points = get_post_meta( $stake_id, '_mkps_stake_points', true );
    $author_id = get_post_field( 'post_author', $stake_id );
    $author_team_id = get_user_meta( $author_id, '_sp_team_id', true );
    $author_team_name = $author_team_id ? get_the_title( $author_team_id ) : get_userdata( $author_id )->display_name;
    $author_team_name = $author_team_name ?: 'Stake Author';
    $stake_link = get_permalink( $stake_id );

    $message = sprintf( __( '%s created a new stake for %d points. View available stakes to join.', 'mk-point-staker' ), esc_html( $author_team_name ), $stake_points );
    $title = __( 'New Stake Available', 'mk-point-staker' );

    $users = get_users( array( 'fields' => 'ID' ) );
    foreach ( $users as $user_id ) {
        if ( $user_id != $author_id ) {
            mkps_send_notification( $user_id, $title, $message, $stake_id );
        }
    }
}
add_action( 'mkps_stake_created', 'mkps_notify_new_stake' );