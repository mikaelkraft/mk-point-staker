<?php
// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode to Display User Points
 */
function mkps_display_user_points_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'user_id' => 0,
    ), $atts, 'mkps_user_points' );

    $current_user_id = get_current_user_id();
    $viewed_user_id = $atts['user_id'] ? intval( $atts['user_id'] ) : ( bp_displayed_user_id() ?: ( get_query_var( 'author' ) ?: $current_user_id ) );
    $user_id = ( $current_user_id && $current_user_id === $viewed_user_id ) || ! $atts['user_id'] ? $current_user_id : $viewed_user_id;
    
    if ( ! $user_id ) {
        return '<p>' . __( 'No user specified or logged in.', 'mk-point-staker' ) . '</p>';
    }

    $user_points = mycred_get_users_balance( $user_id );
    return '<h3>' . __( 'Points Balance', 'mk-point-staker' ) . '</h3>' .
           '<p>' . sprintf( __( '%s, you currently have <strong>%d</strong> points.', 'mk-point-staker' ), esc_html( get_userdata( $user_id )->display_name ), $user_points ) . '</p>';
}
add_shortcode( 'mkps_user_points', 'mkps_display_user_points_shortcode' );

/**
 * Shortcode to Display User Stakes
 */
function mkps_display_user_stakes_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'user_id' => 0,
    ), $atts, 'mkps_user_stakes' );

    $current_user_id = get_current_user_id();
    $viewed_user_id = $atts['user_id'] ? intval( $atts['user_id'] ) : ( bp_displayed_user_id() ?: ( get_query_var( 'author' ) ?: $current_user_id ) );
    $user_id = ( $current_user_id && $current_user_id === $viewed_user_id ) || ! $atts['user_id'] ? $current_user_id : $viewed_user_id;

    if ( ! $user_id ) {
        return '<p>' . __( 'No user specified or logged in.', 'mk-point-staker' ) . '</p>';
    }

    $user_stakes = new WP_Query( array(
        'post_type'      => 'stake',
        'author'         => $user_id,
        'posts_per_page' => 10,
    ));

    $wins = get_user_meta( $user_id, '_mkps_wins', true ) ?: 0;
    $losses = get_user_meta( $user_id, '_mkps_losses', true ) ?: 0;
    $draws = get_user_meta( $user_id, '_mkps_draws', true ) ?: 0;

    ob_start();
    echo '<h3>' . __( 'My Stakes', 'mk-point-staker' ) . '</h3>';
    echo '<div class="mkps-stats-box">';
    echo '<div><h4>' . __( 'Wins', 'mk-point-staker' ) . '</h4><span>' . $wins . '</span></div>';
    echo '<div><h4>' . __( 'Losses', 'mk-point-staker' ) . '</h4><span>' . $losses . '</span></div>';
    echo '<div><h4>' . __( 'Draws', 'mk-point-staker' ) . '</h4><span>' . $draws . '</span></div>';
    echo '</div>';
    if ( $user_stakes->have_posts() ) {
        echo '<ul>';
        while ( $user_stakes->have_posts() ) {
            $user_stakes->the_post();
            $stake_points = get_post_meta( get_the_ID(), '_mkps_stake_points', true );
            $accepted_by = get_post_meta( get_the_ID(), '_mkps_stake_accepted_by', true );
            echo '<li>' . sprintf( __( 'Stake for %d points - %s', 'mk-point-staker' ), $stake_points, $accepted_by ? __( 'Accepted', 'mk-point-staker' ) : __( 'Pending', 'mk-point-staker' ) ) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>' . __( 'No stakes found.', 'mk-point-staker' ) . '</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'mkps_user_stakes', 'mkps_display_user_stakes_shortcode' );

/**
 * Shortcode to Display Available Stakes Button with Count
 */
function mkps_available_stakes_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( 'You must be logged in to view available stakes.', 'mk-point-staker' ) . '</p>';
    }

    $user_id = get_current_user_id();
    $notifications = get_user_meta( $user_id, '_mkps_notifications', true );
    $available_stakes = 0;

    if ( ! empty( $notifications ) && is_array( $notifications ) ) {
        $available_stakes = count( array_filter( $notifications, function ( $n ) {
            return ! empty( $n['post_id'] ) && get_post_type( $n['post_id'] ) === 'stake' && ! get_post_meta( $n['post_id'], '_mkps_stake_accepted', true );
        } ) );
    }

    ob_start();
    ?>
    <button class="mkps-available-stakes-button" data-nonce="<?php echo wp_create_nonce( 'mkps_nonce' ); ?>">
        <?php _e( 'Available Stakes', 'mk-point-staker' ); ?>
        <?php if ( $available_stakes > 0 ): ?>
            <span class="mkps-notification-bubble"><?php echo $available_stakes; ?></span>
        <?php endif; ?>
    </button>
    <div id="mkps-stakes-panel" style="display:none;">
        <?php echo do_shortcode( '[mkps_user_notifications]' ); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_available_stakes', 'mkps_available_stakes_shortcode' );

/**
 * Optional: Add to WordPress User Profile Page (Admin)
 */
function mkps_add_to_wp_user_profile( $user ) {
    if ( ! is_user_logged_in() ) {
        return;
    }
    echo '<h2>' . __( 'MK Point Staker Info', 'mk-point-staker' ) . '</h2>';
    echo do_shortcode( '[mkps_user_points user_id="' . $user->ID . '"]' );
    echo do_shortcode( '[mkps_user_stakes user_id="' . $user->ID . '"]' );
}
add_action( 'show_user_profile', 'mkps_add_to_wp_user_profile' );
add_action( 'edit_user_profile', 'mkps_add_to_wp_user_profile' );

/**
 * Optional: Add to BuddyPress Profile (if active)
 */
function mkps_add_to_buddypress_profile() {
    if ( ! function_exists( 'bp_is_active' ) || ! bp_is_user() ) {
        return;
    }
    echo '<div class="mkps-profile-section">';
    echo do_shortcode( '[mkps_user_points]' );
    echo do_shortcode( '[mkps_user_stakes]' );
    echo '</div>';
}
add_action( 'bp_before_member_header', 'mkps_add_to_buddypress_profile' );

/**
 * Filter to Allow Custom Profile Manager Integration
 */
function mkps_profile_integration_output() {
    $output = '';
    $output .= do_shortcode( '[mkps_user_points]' );
    $output .= do_shortcode( '[mkps_user_stakes]' );
    return apply_filters( 'mkps_profile_integration_output', $output );
}

/**
 * Add Available Stake Count to UM Profile Menu
 */
function mkps_add_stake_count_to_um_menu( $menu_items ) {
    if ( ! function_exists( 'UM' ) || ! is_user_logged_in() ) {
        return $menu_items;
    }

    $user_id = get_current_user_id();
    $notifications = get_user_meta( $user_id, '_mkps_notifications', true );
    $available_stakes = 0;

    if ( ! empty( $notifications ) && is_array( $notifications ) ) {
        $available_stakes = count( array_filter( $notifications, function ( $n ) {
            return ! empty( $n['post_id'] ) && get_post_type( $n['post_id'] ) === 'stake' && ! get_post_meta( $n['post_id'], '_mkps_stake_accepted', true );
        } ) );
    }

    foreach ( $menu_items as &$item ) {
        if ( isset( $item['tab'] ) && $item['tab'] === 'available-stakes' ) {
            if ( $available_stakes > 0 ) {
                $item['title'] .= ' <span class="mkps-notification-bubble">' . $available_stakes . '</span>';
            }
        }
    }

    return $menu_items;
}
add_filter( 'um_profile_menu', 'mkps_add_stake_count_to_um_menu', 10, 1 );