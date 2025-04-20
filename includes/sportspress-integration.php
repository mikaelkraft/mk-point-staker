<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create SportsPress Event for Accepted Stake
 */
function mkps_create_sportspress_event( $stake_id, $author_team_id, $opponent_team_id ) {
    $author_team_name = $author_team_id ? get_the_title( $author_team_id ) : 'Team 1';
    $opponent_team_name = $opponent_team_id ? get_the_title( $opponent_team_id ) : 'Team 2';

    $event_args = array(
        'post_type'   => 'sp_event',
        'post_title'  => sprintf( '%s vs %s - Stake', $author_team_name, $opponent_team_name ),
        'post_status' => 'publish',
        'post_author' => get_post_field( 'post_author', $stake_id ),
    );

    $event_id = wp_insert_post( $event_args );

    if ( $event_id ) {
        update_post_meta( $event_id, 'sp_team', array( $author_team_id, $opponent_team_id ) );
        update_post_meta( $event_id, '_sp_teams', array( $author_team_id, $opponent_team_id ) );
        update_post_meta( $event_id, '_mkps_stake_id', $stake_id );

        // Initialize box score
        update_post_meta( $event_id, 'sp_results', array(
            $author_team_id   => array( 'score' => '' ),
            $opponent_team_id => array( 'score' => '' ),
        ) );
    }

    return $event_id;
}
add_action( 'mkps_stake_accepted', 'mkps_create_sportspress_event', 10, 3 );

/**
 * Update Points Based on SportsPress Event Results
 */
function mkps_update_points_on_result( $post_id ) {
    if ( get_post_type( $post_id ) !== 'sp_event' ) {
        return;
    }

    $stake_id = get_post_meta( $post_id, '_mkps_stake_id', true );
    if ( ! $stake_id ) {
        return;
    }

    $results = get_post_meta( $post_id, 'sp_results', true );
    $teams = get_post_meta( $post_id, 'sp_team', true );
    if ( empty( $results ) || empty( $teams ) ) {
        return;
    }

    $author_team_id = $teams[0];
    $opponent_team_id = $teams[1];
    $author_score = isset( $results[ $author_team_id ]['score'] ) ? intval( $results[ $author_team_id ]['score'] ) : 0;
    $opponent_score = isset( $results[ $opponent_team_id ]['score'] ) ? intval( $results[ $opponent_team_id ]['score'] ) : 0;

    $points = get_post_meta( $stake_id, '_mkps_stake_points', true );
    $commission_rate = get_post_meta( $stake_id, '_mkps_commission_rate', true );
    $commission_rate = $commission_rate ?: get_option( 'mkps_options', array( 'commission_rate' => 0.05 ) )['commission_rate'];
    $commission = $points * $commission_rate * 2; // Commission from both users
    $payout = ( $points * 2 ) - $commission;

    $mycred = mycred();
    $author_id = get_post_field( 'post_author', $stake_id );
    $opponent_id = get_post_meta( $stake_id, '_mkps_opponent_id', true );
    $admin_id = get_option( 'admin_user_id', 1 );

    if ( $author_score > $opponent_score ) {
        // Author wins
        $mycred->add_creds( 'mkps_stake_won', $author_id, $payout, 'Won stake #%d', $stake_id );
        update_user_meta( $author_id, '_mkps_wins', get_user_meta( $author_id, '_mkps_wins', true ) + 1 );
        update_user_meta( $opponent_id, '_mkps_losses', get_user_meta( $opponent_id, '_mkps_losses', true ) + 1 );
    } elseif ( $opponent_score > $author_score ) {
        // Opponent wins
        $mycred->add_creds( 'mkps_stake_won', $opponent_id, $payout, 'Won stake #%d', $stake_id );
        update_user_meta( $opponent_id, '_mkps_wins', get_user_meta( $opponent_id, '_mkps_wins', true ) + 1 );
        update_user_meta( $author_id, '_mkps_losses', get_user_meta( $author_id, '_mkps_losses', true ) + 1 );
    } else {
        // Draw
        $mycred->add_creds( 'mkps_stake_draw', $author_id, $points, 'Draw for stake #%d', $stake_id );
        $mycred->add_creds( 'mkps_stake_draw', $opponent_id, $points, 'Draw for stake #%d', $stake_id );
        update_user_meta( $author_id, '_mkps_draws', get_user_meta( $author_id, '_mkps_draws', true ) + 1 );
        update_user_meta( $opponent_id, '_mkps_draws', get_user_meta( $opponent_id, '_mkps_draws', true ) + 1 );
    }

    // Award commission to admin
    $mycred->add_creds( 'mkps_commission', $admin_id, $commission, 'Commission for stake #%d', $stake_id );

    update_post_meta( $stake_id, '_mkps_status', 'completed' );
}
add_action( 'save_post_sp_event', 'mkps_update_points_on_result' );