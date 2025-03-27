<?php
// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create SportsPress Event for Paired Teams
 */
function mkps_create_sportspress_event( $stake_id, $creator_id, $accepting_user_id ) {
    $stake_points = get_post_meta( $stake_id, '_mkps_stake_points', true );
    $connection_code = get_post_meta( $stake_id, '_mkps_connection_code', true );

    // Fetch team details for creator
    $creator_team_id = get_user_meta( $creator_id, '_sp_team_id', true );
    $creator_team_name = '';
    if ( $creator_team_id ) {
        $team_meta = get_post_meta( $creator_team_id );
        $creator_team_name = $team_meta['sp_team_short_name'][0] ?? ''; // Prioritize short name
        if ( empty( $creator_team_name ) ) {
            $creator_team_name = $team_meta['sp_team'][0] ?? ''; // Then abbreviation
        }
        if ( empty( $creator_team_name ) ) {
            $creator_team_name = get_the_title( $creator_team_id ); // Then team title
        }
    }
    if ( empty( $creator_team_name ) ) {
        $creator_team_name = get_userdata( $creator_id )->display_name; // Last resort
    }

    // Fetch team details for acceptor
    $accepting_team_id = get_user_meta( $accepting_user_id, '_sp_team_id', true );
    $accepting_team_name = '';
    if ( $accepting_team_id ) {
        $team_meta = get_post_meta( $accepting_team_id );
        $accepting_team_name = $team_meta['sp_team_short_name'][0] ?? ''; // Prioritize short name
        if ( empty( $accepting_team_name ) ) {
            $accepting_team_name = $team_meta['sp_team'][0] ?? ''; // Then abbreviation
        }
        if ( empty( $accepting_team_name ) ) {
            $accepting_team_name = get_the_title( $accepting_team_id ); // Then team title
        }
    }
    if ( empty( $accepting_team_name ) ) {
        $accepting_team_name = get_userdata( $accepting_user_id )->display_name; // Last resort
    }

    $event_data = array(
        'post_title'   => sprintf( __( '%s vs %s - Stake', 'mk-point-staker' ), $creator_team_name, $accepting_team_name ),
        'post_content' => sprintf( __( 'A stake match for %d points. Connection Code: %s', 'mk-point-staker' ), $stake_points, $connection_code ),
        'post_type'    => 'sp_event',
        'post_status'  => 'publish',
        'post_author'  => $creator_id,
    );

    $event_id = wp_insert_post( $event_data );
    if ( $event_id ) {
        if ( $creator_team_id && $accepting_team_id ) {
            update_post_meta( $event_id, 'sp_team', array( $creator_team_id, $accepting_team_id ) );
            update_post_meta( $event_id, 'sp_format', 'Friendly' );
            update_post_meta( $event_id, 'sp_minutes', 90 );
            update_post_meta( $event_id, 'sp_mode', 'team' );
        }

        update_post_meta( $stake_id, '_mkps_event_id', $event_id );
        update_post_meta( $event_id, '_mkps_stake_id', $stake_id );

        mkps_notify_event_creation( $event_id, $creator_id, $accepting_user_id );
    }
}
add_action( 'mkps_stake_accepted', 'mkps_create_sportspress_event', 10, 3 );

/**
 * Notify Users of Event Creation
 */
function mkps_notify_event_creation( $event_id, $creator_id, $accepting_user_id ) {
    $event_link = get_permalink( $event_id );
    $stake_id = get_post_meta( $event_id, '_mkps_stake_id', true );
    $stake_link = get_permalink( $stake_id );
    $message = sprintf( __( 'A new stake match has been scheduled. View the event details here: %s | Stake details: %s', 'mk-point-staker' ), $event_link, $stake_link );
    wp_mail( get_userdata( $creator_id )->user_email, __( 'New Stake Match Event', 'mk-point-staker' ), $message );
    wp_mail( get_userdata( $accepting_user_id )->user_email, __( 'New Stake Match Event', 'mk-point-staker' ), $message );
}

/**
 * Record Match Results
 */
function mkps_record_match_results( $event_id, $winner_id, $is_draw = false ) {
    $stake_id = get_post_meta( $event_id, '_mkps_stake_id', true );
    $stake_points = get_post_meta( $stake_id, '_mkps_stake_points', true );
    $creator_id = get_post_field( 'post_author', $stake_id );
    $accepting_user_id = get_post_meta( $stake_id, '_mkps_stake_accepted_by', true );

    if ( $is_draw ) {
        mycred_add( 'stake_draw_refund', $creator_id, $stake_points, __( 'Points refunded for a draw in stake match', 'mk-point-staker' ) );
        mycred_add( 'stake_draw_refund', $accepting_user_id, $stake_points, __( 'Points refunded for a draw in stake match', 'mk-point-staker' ) );
        update_post_meta( $stake_id, '_mkps_stake_completed', true );
        mkps_notify_match_result( $stake_id, null, true );
    } else {
        $loser_id = $winner_id === $creator_id ? $accepting_user_id : $creator_id;
        if ( $winner_id && mycred_add( 'stake_victory', $winner_id, $stake_points * 2, __( 'Points awarded for winning a stake match', 'mk-point-staker' ) ) ) {
            update_post_meta( $stake_id, '_mkps_stake_winner', $winner_id );
            update_post_meta( $stake_id, '_mkps_stake_completed', true );
            $winner_wins = get_user_meta( $winner_id, '_mkps_wins', true ) ?: 0;
            $loser_losses = get_user_meta( $loser_id, '_mkps_losses', true ) ?: 0;
            update_user_meta( $winner_id, '_mkps_wins', $winner_wins + 1 );
            update_user_meta( $loser_id, '_mkps_losses', $loser_losses + 1 );
            mkps_notify_match_result( $stake_id, $winner_id );
        }
    }
}

/**
 * Notify Users of Match Result
 */
function mkps_notify_match_result( $stake_id, $winner_id, $is_draw = false ) {
    $creator_id = get_post_field( 'post_author', $stake_id );
    $accepting_user_id = get_post_meta( $stake_id, '_mkps_stake_accepted_by', true );

    if ( $is_draw ) {
        $message = __( 'The match has concluded in a draw. Your points have been refunded.', 'mk-point-staker' );
    } else {
        $winner_name = get_userdata( $winner_id )->display_name;
        $message = sprintf( __( 'The match has concluded, and %s is the winner!', 'mk-point-staker' ), $winner_name );
    }

    wp_mail( get_userdata( $creator_id )->user_email, __( 'Match Result', 'mk-point-staker' ), $message );
    wp_mail( get_userdata( $accepting_user_id )->user_email, __( 'Match Result', 'mk-point-staker' ), $message );
}

/**
 * Handle Match Results from SportsPress
 */
function mkps_handle_sportspress_result_update( $event_id ) {
    $stake_id = get_post_meta( $event_id, '_mkps_stake_id', true );
    if ( ! $stake_id ) {
        return;
    }

    $results = get_post_meta( $event_id, 'sp_results', true );
    $teams = get_post_meta( $event_id, 'sp_team', true );
    if ( ! $results || ! $teams ) {
        return;
    }

    $team_scores = array();
    foreach ( $teams as $team_id ) {
        $team_scores[$team_id] = isset( $results[$team_id]['points'] ) ? intval( $results[$team_id]['points'] ) : 0;
    }

    $creator_id = get_post_field( 'post_author', $stake_id );
    $accepting_user_id = get_post_meta( $stake_id, '_mkps_stake_accepted_by', true );
    $creator_team_id = get_user_meta( $creator_id, '_sp_team_id', true );
    $accepting_team_id = get_user_meta( $accepting_user_id, '_sp_team_id', true );

    if ( $team_scores[$creator_team_id] === $team_scores[$accepting_team_id] ) {
        mkps_record_match_results( $event_id, null, true );
    } elseif ( $team_scores[$creator_team_id] > $team_scores[$accepting_team_id] ) {
        mkps_record_match_results( $event_id, $creator_id );
    } else {
        mkps_record_match_results( $event_id, $accepting_user_id );
    }
}
add_action( 'sportspress_event_updated', 'mkps_handle_sportspress_result_update' );