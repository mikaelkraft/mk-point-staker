<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Stake Form Shortcode
 */
function mkps_stake_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( 'Please log in to create or accept a stake.', 'mk-point-staker' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="mkps-stake-container">
        <h3><?php _e( 'Create a Stake', 'mk-point-staker' ); ?></h3>
        <form id="mkps-stake-form" method="post">
            <?php wp_nonce_field( 'mkps_stake_form', 'mkps_stake_nonce' ); ?>
            <p>
                <label for="mkps_stake_points"><?php _e( 'Points to Stake', 'mk-point-staker' ); ?></label>
                <input type="number" id="mkps_stake_points" name="mkps_stake_points" min="1" required>
            </p>
            <p>
                <input type="submit" name="mkps_submit_stake" value="<?php _e( 'Create Stake', 'mk-point-staker' ) ?>">
            </p>
        </form>

        <h3><?php _e( 'Accept a Stake by Code', 'mk-point-staker' ); ?></h3>
        <form id="mkps-accept-code-form" method="post">
            <?php wp_nonce_field( 'mkps_accept_code', 'mkps_accept_code_nonce' ); ?>
            <p>
                <label for="mkps_connection_code"><?php _e( 'Connection Code', 'mk-point-staker' ); ?></label>
                <input type="text" id="mkps_connection_code" name="mkps_connection_code" placeholder="e.g., STK123" required>
            </p>
            <p>
                <input type="submit" name="mkps_accept_code" value="<?php _e( 'Accept Stake', 'mk-point-staker' ) ?>">
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_stake_form', 'mkps_stake_form_shortcode' );

/**
 * Handle Stake Form Submission
 */
function mkps_handle_stake_form() {
    // Create Stake
    if ( isset( $_POST['mkps_submit_stake'] ) && isset( $_POST['mkps_stake_nonce'] ) && wp_verify_nonce( $_POST['mkps_stake_nonce'], 'mkps_stake_form' ) && is_user_logged_in() ) {
        $points = isset( $_POST['mkps_stake_points'] ) ? absint( $_POST['mkps_stake_points'] ) : 0;
        $user_id = get_current_user_id();

        if ( $points < 1 ) {
            add_action( 'wp_footer', function() {
                echo '<p class="mkps-error">' . __( 'Please enter a valid number of points.', 'mk-point-staker' ) . '</p>';
            } );
            return;
        }

        $mycred = mycred();
        $balance = $mycred->get_users_balance( $user_id );

        if ( $balance < $points ) {
            add_action( 'wp_footer', function() {
                echo '<p class="mkps-error">' . __( 'Insufficient points to create stake.', 'mk-point-staker' ) . '</p>';
            } );
            return;
        }

        $mycred->add_creds(
            'mkps_stake_created',
            $user_id,
            -$points,
            'Points deducted for creating stake'
        );

        $stake_args = array(
            'post_type'   => 'mkps_stake',
            'post_title'  => sprintf( __( 'Stake by %s', 'mk-point-staker' ), get_userdata( $user_id )->display_name ),
            'post_status' => 'publish',
            'post_author' => $user_id,
        );

        $stake_id = wp_insert_post( $stake_args );

        if ( $stake_id ) {
            update_post_meta( $stake_id, '_mkps_stake_points', $points );
            update_post_meta( $stake_id, '_mkps_status', 'open' );

            // Generate connection code
            $connection_code = 'STK' . strtoupper( wp_generate_password( 3, false, false ) );
            update_post_meta( $stake_id, '_mkps_connection_code', $connection_code );

            do_action( 'mkps_stake_created', $stake_id );

            // Notify author with connection code
            $team_id = get_user_meta( $user_id, '_sp_team_id', true );
            $team_name = $team_id ? get_the_title( $team_id ) : get_userdata( $user_id )->display_name;
            $message = sprintf( __( 'Your stake for %d points has been created. Connection Code: %s. Use this code in Dream League Soccer to play the match.', 'mk-point-staker' ), $points, $connection_code );
            mkps_send_notification( $user_id, __( 'Stake Created', 'mk-point-staker' ), $message, $stake_id );

            add_action( 'wp_footer', function() use ( $connection_code ) {
                echo '<p class="mkps-success">' . sprintf( __( 'Stake created successfully. Connection Code: %s. Use this code in Dream League Soccer.', 'mk-point-staker' ), esc_html( $connection_code ) ) . '</p>';
            } );
        }
    }

    // Accept Stake by Code
    if ( isset( $_POST['mkps_accept_code'] ) && isset( $_POST['mkps_accept_code_nonce'] ) && wp_verify_nonce( $_POST['mkps_accept_code_nonce'], 'mkps_accept_code' ) && is_user_logged_in() ) {
        $code = isset( $_POST['mkps_connection_code'] ) ? sanitize_text_field( $_POST['mkps_connection_code'] ) : '';
        $user_id = get_current_user_id();

        if ( empty( $code ) ) {
            add_action( 'wp_footer', function() {
                echo '<p class="mkps-error">' . __( 'Please enter a valid connection code.', 'mk-point-staker' ) . '</p>';
            } );
            return;
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
            add_action( 'wp_footer', function() {
                echo '<p class="mkps-error">' . __( 'Invalid or unavailable connection code.', 'mk-point-staker' ) . '</p>';
            } );
            return;
        }

        $stake_id = $stakes[0]->ID;
        $author_id = get_post_field( 'post_author', $stake_id );

        if ( $user_id == $author_id ) {
            add_action( 'wp_footer', function() {
                echo '<p class="mkps-error">' . __( 'You cannot accept your own stake.', 'mk-point-staker' ) . '</p>';
            } );
            return;
        }

        $points = get_post_meta( $stake_id, '_mkps_stake_points', true );
        $mycred = mycred();
        $balance = $mycred->get_users_balance( $user_id );

        if ( $balance < $points ) {
            add_action( 'wp_footer', function() {
                echo '<p class="mkps-error">' . __( 'Insufficient points.', 'mk-point-staker' ) . '</p>';
            } );
            return;
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

        // Notify author
        $message = sprintf( __( '%s accepted your stake for %d points.', 'mk-point-staker' ), esc_html( $opponent_team_name ), $points );
        mkps_send_notification( $author_id, __( 'Stake Accepted', 'mk-point-staker' ), $message, $stake_id );

        // Notify acceptor with connection code
        $connection_code = get_post_meta( $stake_id, '_mkps_connection_code', true );
        $acceptor_message = sprintf( __( 'You accepted a stake for %d points. Connection Code: %s. Use this code in Dream League Soccer to play the match.', 'mk-point-staker' ), $points, $connection_code );
        mkps_send_notification( $user_id, __( 'Stake Accepted', 'mk-point-staker' ), $acceptor_message, $stake_id );

        add_action( 'wp_footer', function() {
            echo '<p class="mkps-success">' . __( 'Stake accepted successfully. Check your profile for the connection code.', 'mk-point-staker' ) . '</p>';
        } );
    }
}
add_action( 'wp', 'mkps_handle_stake_form' );