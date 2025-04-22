<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Accept Button for Stake
 */
function mkps_render_accept_button( $stake_id ) {
    if ( is_user_logged_in() ) {
        $is_accepted = get_post_meta( $stake_id, '_mkps_stake_accepted', true );

        if ( ! $is_accepted ) {
            echo '<form method="post" class="mkps-accept-stake-form">';
            echo '<input type="hidden" name="stake_id" value="' . esc_attr( $stake_id ) . '">';
            echo '<input type="submit" name="mkps_accept_stake" value="' . __( 'Accept Stake', 'mk-point-staker' ) . '" class="button button-primary">';
            echo wp_nonce_field( 'mkps_accept_stake_nonce', 'mkps_accept_stake_nonce_field' );
            echo '</form>';
        } else {
            echo '<p>' . __( 'This stake has already been accepted.', 'mk-point-staker' ) . '</p>';
        }
    }
}

/**
 * Handle Stake Acceptance
 */
function mkps_handle_stake_acceptance() {
    if ( isset( $_POST['mkps_accept_stake'], $_POST['mkps_accept_stake_nonce_field'] ) &&
         wp_verify_nonce( $_POST['mkps_accept_stake_nonce_field'], 'mkps_accept_stake_nonce' ) ) {

        $stake_id = intval( $_POST['stake_id'] );
        $user_id = get_current_user_id();
        $stake_points = get_post_meta( $stake_id, '_mkps_stake_points', true );
        $stake_author_id = get_post_field( 'post_author', $stake_id );

        if ( $user_id === $stake_author_id ) {
            add_filter( 'the_content', function ( $content ) {
                return $content . '<p>' . __( 'You cannot accept your own stake.', 'mk-point-staker' ) . '</p>';
            });
            return;
        }

        if ( mkps_has_sufficient_points( $user_id, $stake_points ) && mkps_has_sufficient_points( $stake_author_id, $stake_points ) ) {
            mkps_deduct_points( $user_id, $stake_points );

            update_post_meta( $stake_id, '_mkps_stake_accepted', true );
            update_post_meta( $stake_id, '_mkps_stake_accepted_by', $user_id );

            $connection_code = get_post_meta( $stake_id, '_mkps_connection_code', true ); // Already set on creation
            mkps_notify_stake_acceptance( $stake_id, $user_id );
            do_action( 'mkps_stake_accepted', $stake_id, $stake_author_id, $user_id );

            add_filter( 'the_content', function ( $content ) use ( $connection_code, $user_id, $stake_author_id ) {
                if ( $user_id === $stake_author_id ) {
                    $content .= '<p>' . sprintf( __( 'Stake accepted! Connection Code: <strong>%s</strong>', 'mk-point-staker' ), esc_html( $connection_code ) ) . '</p>';
                } elseif ( $user_id === get_current_user_id() ) {
                    $content .= '<p>' . sprintf( __( 'You accepted the stake! Connection Code: %s', 'mk-point-staker' ), esc_html( $connection_code ) ) . '</p>';
                } else {
                    $content .= '<p>' . __( 'Stake has been accepted.', 'mk-point-staker' ) . '</p>';
                }
                return $content;
            });
        } else {
            add_filter( 'the_content', function ( $content ) {
                $content .= '<p>' . __( 'Failed to accept stake due to insufficient points.', 'mk-point-staker' ) . '</p>';
                return $content;
            });
        }
    }
}
add_action( 'template_redirect', 'mkps_handle_stake_acceptance' );

/**
 * Check If User Has Sufficient Points
 */
function mkps_has_sufficient_points( $user_id, $points ) {
    $current_points = mycred_get_users_balance( $user_id );
    return ( $current_points >= $points );
}

/**
 * Deduct Points from User
 */
function mkps_deduct_points( $user_id, $points ) {
    mycred_subtract( 'stake_deduction', $user_id, $points, __( 'Points deducted for stake participation', 'mk-point-staker' ) );
}

/**
 * Notify Users of Stake Acceptance
 */
function mkps_notify_stake_acceptance( $stake_id, $acceptor_id ) {
    $stake_author_id = get_post_field( 'post_author', $stake_id );
    $acceptor_name = get_userdata( $acceptor_id )->display_name;
    $message = sprintf( __( '%s has accepted your stake!', 'mk-point-staker' ), $acceptor_name );
    wp_mail( get_userdata( $stake_author_id )->user_email, __( 'Stake Accepted', 'mk-point-staker' ), $message );
}