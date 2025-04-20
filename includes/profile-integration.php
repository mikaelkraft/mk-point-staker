<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * User Stats Shortcode
 */
function mkps_user_stats_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'user_id' => get_current_user_id(),
    ), $atts );

    $user_id = absint( $atts['user_id'] );
    if ( ! $user_id ) {
        return '';
    }

    $wins = get_user_meta( $user_id, '_mkps_wins', true ) ?: 0;
    $losses = get_user_meta( $user_id, '_mkps_losses', true ) ?: 0;
    $draws = get_user_meta( $user_id, '_mkps_draws', true ) ?: 0;
    $team_id = get_user_meta( $user_id, '_sp_team_id', true );
    $team_name = $team_id ? get_the_title( $team_id ) : get_userdata( $user_id )->display_name;

    ob_start();
    ?>
    <div class="mkps-user-stats">
        <h3><?php echo esc_html( $team_name ); ?> <?php _e( 'Stats', 'mk-point-staker' ); ?></h3>
        <div class="mkps-stats-box wins"><?php _e( 'Wins', 'mk-point-staker' ); ?>: <?php echo esc_html( $wins ); ?></div>
        <div class="mkps-stats-box losses"><?php _e( 'Losses', 'mk-point-staker' ); ?>: <?php echo esc_html( $losses ); ?></div>
        <div class="mkps-stats-box draws"><?php _e( 'Draws', 'mk-point-staker' ); ?>: <?php echo esc_html( $draws ); ?></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_user_stats', 'mkps_user_stats_shortcode' );

/**
 * Connection Code Shortcode
 */
function mkps_connection_code_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'user_id' => get_current_user_id(),
    ), $atts );

    $user_id = absint( $atts['user_id'] );
    if ( ! $user_id ) {
        return '';
    }

    $args = array(
        'post_type'      => 'mkps_stake',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'   => '_mkps_status',
                'value' => 'open',
            ),
            array(
                'key'   => '_mkps_status',
                'value' => 'accepted',
            ),
        ),
        'author__in'     => array( $user_id ),
    );

    $author_stakes = get_posts( $args );

    $args = array(
        'post_type'      => 'mkps_stake',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'   => '_mkps_status',
                'value' => 'accepted',
            ),
            array(
                'key'   => '_mkps_opponent_id',
                'value' => $user_id,
            ),
        ),
    );

    $opponent_stakes = get_posts( $args );

    $stakes = array_merge( $author_stakes, $opponent_stakes );

    if ( empty( $stakes ) ) {
        return '<p>' . __( 'No stakes with connection codes.', 'mk-point-staker' ) . '</p>';
    }

    ob_start();
    ?>
    <div class="mkps-connection-codes">
        <h3><?php _e( 'Your Stake Connection Codes', 'mk-point-staker' ); ?></h3>
        <ul>
            <?php foreach ( $stakes as $stake ) : ?>
                <?php
                $code = get_post_meta( $stake->ID, '_mkps_connection_code', true );
                $points = get_post_meta( $stake->ID, '_mkps_stake_points', true );
                $status = get_post_meta( $stake->ID, '_mkps_status', true );
                ?>
                <li>
                    <?php
                    echo sprintf(
                        __( 'Stake for %d points (%s): <strong>%s</strong>. Use this code in Dream League Soccer.', 'mk-point-staker' ),
                        esc_html( $points ),
                        esc_html( $status === 'open' ? 'Open' : 'Accepted' ),
                        esc_html( $code )
                    );
                    ?>
                    <a href="<?php echo esc_url( get_permalink( $stake->ID ) ); ?>"><?php _e( 'View Stake', 'mk-point-staker' ); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_connection_code', 'mkps_connection_code_shortcode' );

/**
 * Available Stakes Shortcode
 */
function mkps_available_stakes_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . __( 'Please log in to view available stakes.', 'mk-point-staker' ) . '</p>';
    }

    $args = array(
        'post_type'      => 'mkps_stake',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'   => '_mkps_status',
                'value' => 'open',
            ),
            array(
                'key'   => '_mkps_status',
                'value' => 'accepted',
            ),
        ),
    );

    $stakes = get_posts( $args );

    if ( empty( $stakes ) ) {
        return '<p>' . __( 'No stakes available.', 'mk-point-staker' ) . '</p>';
    }

    $current_user_id = get_current_user_id();

    ob_start();
    ?>
    <div class="mkps-available-stakes">
        <h3><?php _e( 'Available Stakes', 'mk-point-staker' ); ?></h3>
        <div class="mkps-view-toggle">
            <button class="mkps-toggle-view active" data-view="list"><?php _e( 'List View', 'mk-point-staker' ); ?></button>
            <button class="mkps-toggle-view" data-view="card"><?php _e( 'Card View', 'mk-point-staker' ); ?></button>
        </div>
        <div class="mkps-stakes-list list-view">
            <?php foreach ( $stakes as $stake ) : ?>
                <?php
                $points = get_post_meta( $stake->ID, '_mkps_stake_points', true );
                $status = get_post_meta( $stake->ID, '_mkps_status', true );
                $author_id = get_post_field( 'post_author', $stake );
                $author_team_id = get_user_meta( $author_id, '_sp_team_id', true );
                $author_team_name = $author_team_id ? get_the_title( $author_team_id ) : get_userdata( $author_id )->display_name;
                ?>
                <div class="mkps-stake-item">
                    <h4><?php echo sprintf( __( 'Stake by %s for %d points', 'mk-point-staker' ), esc_html( $author_team_name ), esc_html( $points ) ); ?></h4>
                    <p>
                        <?php if ( $status === 'open' && $author_id != $current_user_id ) : ?>
                            <button class="mkps-accept-stake" data-stake-id="<?php echo esc_attr( $stake->ID ); ?>"><?php _e( 'Accept Stake', 'mk-point-staker' ); ?></button>
                        <?php elseif ( $status === 'accepted' ) : ?>
                            <span class="mkps-status-accepted"><?php _e( 'Accepted', 'mk-point-staker' ); ?></span>
                        <?php else : ?>
                            <span class="mkps-status-own"><?php _e( 'Your Stake', 'mk-point-staker' ); ?></span>
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo esc_url( get_permalink( $stake->ID ) ); ?>"><?php _e( 'View Stake', 'mk-point-staker' ); ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'mkps_available_stakes', 'mkps_available_stakes_shortcode' );