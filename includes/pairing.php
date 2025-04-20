<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle Stake Pairing
 */
function mkps_accept_stake() {
    check_ajax_referer( 'mkps_stake_nonce', 'nonce' );

    $stake_id = isset( $_POST['stake_id'] ) ? absint( $_POST['stake_id'] ) : 0;
    $user_id = get_current_user_id();

    if ( ! $stake_id || ! $user_id ) {
        wp_send_json_error( __( 'Invalid request.', 'mk-point-staker' ) );
    }

    $status = get_post_meta( $stake_id, '_mkps_status', true );
    if ( $status !== 'open' ) {
        wp_send_json_error( __( 'Stake is not available.', 'mk-point-staker' ) );
    }

    $author_id = get_post_field( 'post_author', $stake_id );
    if ( $user_id == $author_id ) {
        wp_send_json_error( __( 'You cannot accept your own stake.', 'mk-point-staker' ) );
    }

    $points = get_post_meta( $stake_id, '_mkps_stake_points', true );
    $mycred = mycred();
    $balance = $mycred->get_users_balance( $user_id );

    if ( $balance < $points ) {
        wp_send_json_error( __( 'Insufficient points.', 'mk-point-staker' ) );
    }

    $mycred->add_creds(
        'mkps_stake_accepted',
        $user_id,
        -$points,
        'Points deducted for accepting stake #%d',
        $stake_id
    );

    update_post_meta( $stake_id, '_mkps_status', 'accepted' );
    update_post_meta( $stake_id, '_mkps_opponent_id', $user_id );

    $author_team_id = get_user_meta( $author_id, '_sp_team_id', true );
    $opponent_team_id = get_user_meta( $user_id, '_sp_team_id', true );
    $author_team_name = $author_team_id ? get_the_title( $author_team_id ) : get_userdata( $author_id )->display_name;
    $opponent_team_name = $opponent_team_id ? get_the_title( $opponent_team_id ) : get_userdata( $user_id )->display_name;

    do_action( 'mkps_stake_accepted', $stake_id, $author_team_id, $opponent_team_id );

    $message = sprintf( __( '%s accepted your stake for %d points.', 'mk-point-staker' ), esc_html( $opponent_team_name ), $points );
    mkps_send_notification( $author_id, __( 'Stake Accepted', 'mk-point-staker' ), $message, $stake_id );

    wp_send_json_success( __( 'Stake accepted successfully.', 'mk-point-staker' ) );
}
add_action( 'wp_ajax_mkps_accept_stake', 'mkps_accept_stake' );

/**
 * Cancel Stake
 */
function mkps_cancel_stake() {
    check_ajax_referer( 'mkps_stake_nonce', 'nonce' );

    $stake_id = isset( $_POST['stake_id'] ) ? absint( $_POST['stake_id'] ) : 0;
    $user_id = get_current_user_id();

    if ( ! $stake_id || ! $user_id ) {
        wp_send_json_error( __( 'Invalid request.', 'mk-point-staker' ) );
    }

    $author_id = get_post_field( 'post_author', $stake_id );
    if ( $user_id != $author_id ) {
        wp_send_json_error( __( 'You can only cancel your own stakes.', 'mk-point-staker' ) );
    }

    $status = get_post_meta( $stake_id, '_mkps_status', true );
    if ( $status !== 'open' ) {
        wp_send_json_error( __( 'Only open stakes can be cancelled.', 'mk-point-staker' ) );
    }

    $points = get_post_meta( $stake_id, '_mkps_stake_points', true );
    $mycred = mycred();
    $mycred->add_creds(
        'mkps_stake_cancelled',
        $user_id,
        $points,
        'Points refunded for cancelling stake #%d',
        $stake_id
    );

    wp_delete_post( $stake_id, true );

    $message = __( 'Your stake has been cancelled and points refunded.', 'mk-point-staker' );
    mkps_send_notification( $user_id, __( 'Stake Cancelled', 'mk-point-staker' ), $message );

    wp_send_json_success( __( 'Stake cancelled successfully.', 'mk-point-staker' ) );
}
add_action( 'wp_ajax_mkps_cancel_stake', 'mkps_cancel_stake' );