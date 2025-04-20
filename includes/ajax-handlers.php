<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX Handler for Accepting Stake by Code
 */
function mkps_accept_stake_by_code() {
    check_ajax_referer( 'mkps_accept_code', 'nonce' );

    $code = isset( $_POST['connection_code'] ) ? sanitize_text_field( $_POST['connection_code'] ) : '';
    $user_id = get_current_user_id();

    if ( empty( $code ) || ! $user_id ) {
        wp_send_json_error( __( 'Invalid request.', 'mk-point-staker' ) );
    }

    $args = array(
        'post_type'  => 'mkps_stake',
        'meta_query' => array(
            array(
                'key'   => '_mkps_connection_code',
                'value' => $code,
            ),
            array(
                'key'   => '_mkps_status',
                'value' => 'open',
            ),
        ),
        'posts_per_page' => 1,
    );

    $stakes = get_posts( $args );

    if ( empty( $stakes ) ) {
        wp_send_json_error( __( 'Invalid or unavailable connection code.', 'mk-point-staker' ) );
    }

    $stake_id = $stakes[0]->ID;
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

    $connection_code = get_post_meta( $stake_id, '_mkps_connection_code', true );
    $acceptor_message = sprintf( __( 'You accepted a stake for %d points. Connection Code: %s. Use this code in Dream League Soccer to play the match.', 'mk-point-staker' ), $points, $connection_code );
    mkps_send_notification( $user_id, __( 'Stake Accepted', 'mk-point-staker' ), $acceptor_message, $stake_id );

    wp_send_json_success( __( 'Stake accepted successfully.', 'mk-point-staker' ) );
}
add_action( 'wp_ajax_mkps_accept_stake_by_code', 'mkps_accept_stake_by_code' );