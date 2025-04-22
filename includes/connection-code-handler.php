<?php
// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Connection Code Form Shortcode
 */
function mkps_connection_code_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( 'You must be logged in to accept a stake by code.', 'mk-point-staker' ) . '</p>';
    }

    ob_start();
    ?>
    <div id="mkps-connection-code-feedback"></div>
    <form id="mkps-connection-code-form" class="mkps-connection-code-form">
        <label for="mkps_connection_code"><?php _e( 'Enter Connection Code', 'mk-point-staker' ); ?></label>
        <input type="text" name="mkps_connection_code" id="mkps_connection_code" required>
        <button type="submit"><?php _e( 'Accept Stake', 'mk-point-staker' ); ?></button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_connection_code_form', 'mkps_connection_code_form_shortcode' );

/**
 * Handle Connection Code Submission via AJAX
 */
function mkps_handle_connection_code_submission() {
    check_ajax_referer( 'mkps_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => __( 'You must be logged in to accept a stake.', 'mk-point-staker' ) ) );
    }

    $connection_code = sanitize_text_field( $_POST['mkps_connection_code'] );
    $user_id = get_current_user_id();

    $args = array(
        'post_type'  => 'stake',
        'meta_query' => array(
            array(
                'key'   => '_mkps_connection_code',
                'value' => $connection_code,
            ),
            array(
                'key'   => '_mkps_stake_accepted',
                'value' => true,
                'compare' => '!=',
            ),
        ),
        'posts_per_page' => 1,
    );

    $stakes = new WP_Query( $args );
    if ( $stakes->have_posts() ) {
        $stakes->the_post();
        $stake_id = get_the_ID();
        $stake_points = get_post_meta( $stake_id, '_mkps_stake_points', true );
        $stake_author_id = get_post_field( 'post_author', $stake_id );

        if ( $user_id === $stake_author_id ) {
            wp_send_json_error( array( 'message' => __( 'You cannot accept your own stake.', 'mk-point-staker' ) ) );
        }

        if ( mkps_has_sufficient_points( $user_id, $stake_points ) && mkps_has_sufficient_points( $stake_author_id, $stake_points ) ) {
            mkps_deduct_points( $user_id, $stake_points );
            update_post_meta( $stake_id, '_mkps_stake_accepted', true );
            update_post_meta( $stake_id, '_mkps_stake_accepted_by', $user_id );

            mkps_notify_stake_acceptance( $stake_id, $user_id );
            do_action( 'mkps_stake_accepted', $stake_id, $stake_author_id, $user_id );

            wp_send_json_success( array( 'message' => sprintf( __( 'Stake accepted! Connection Code: %s', 'mk-point-staker' ), $connection_code ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Insufficient points to accept stake.', 'mk-point-staker' ) ) );
        }
    } else {
        wp_send_json_error( array( 'message' => __( 'Invalid or expired connection code.', 'mk-point-staker' ) ) );
    }
    wp_reset_postdata();
}
add_action( 'wp_ajax_mkps_submit_connection_code', 'mkps_handle_connection_code_submission' );