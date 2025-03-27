<?php
// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Stake Form Shortcode
 */
function mkps_stake_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( 'You must be logged in to create a stake.', 'mk-point-staker' ) . '</p>';
    }

    ob_start();
    ?>
    <div id="mkps-stake-feedback"></div>
    <form id="mkps-stake-form" class="mkps-stake-form">
        <label for="mkps_stake_points"><?php _e( 'Stake Points', 'mk-point-staker' ); ?></label>
        <input type="number" name="mkps_stake_points" id="mkps_stake_points" min="1" required>
        <button type="submit"><?php _e( 'Create Stake', 'mk-point-staker' ); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_stake_form', 'mkps_stake_form_shortcode' );

/**
 * Handle Stake Form Submission via AJAX
 */
function mkps_handle_stake_form_submission() {
    check_ajax_referer( 'mkps_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to create a stake.', 'mk-point-staker' ) ) );
    }

    $stake_points = intval( $_POST['mkps_stake_points'] );
    $user_id = get_current_user_id();

    if ( $stake_points <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Stake points must be greater than zero.', 'mk-point-staker' ) ) );
    }

    if ( mkps_has_sufficient_points( $user_id, $stake_points ) ) {
        mkps_deduct_points( $user_id, $stake_points );

        $connection_code = wp_generate_password( 8, false ); // Auto-generate unique code
        $stake_id = wp_insert_post( array(
            'post_title'  => sprintf( __( 'Stake by %s for %d points', 'mk-point-staker' ), get_userdata( $user_id )->display_name, $stake_points ),
            'post_type'   => 'stake',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ) );

        if ( $stake_id ) {
            update_post_meta( $stake_id, '_mkps_stake_points', $stake_points );
            update_post_meta( $stake_id, '_mkps_connection_code', $connection_code );
            wp_send_json_success( array( 'message' => sprintf( __( 'Stake created successfully! Connection Code: <strong>%s</strong>', 'mk-point-staker' ), $connection_code ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to create stake.', 'mk-point-staker' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'Insufficient points to create stake.', 'mk-point-staker' ) ) );
    }
}
add_action( 'wp_ajax_mkps_submit_stake', 'mkps_handle_stake_form_submission' );