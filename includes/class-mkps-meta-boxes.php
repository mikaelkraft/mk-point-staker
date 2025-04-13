<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MKPS_Meta_Boxes
 * Handles adding and saving custom meta boxes for the Stake post type.
 */
class MKPS_Meta_Boxes {

    /**
     * Registers the meta boxes.
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'mkps_stake_meta_box',
            __( 'Stake Details', 'mk-point-staker' ),
            array( __CLASS__, 'render_stake_meta_box' ),
            'stake',
            'normal',
            'high'
        );
    }

    /**
     * Renders the Stake Details meta box content.
     *
     * @param WP_Post $post The post object.
     */
    public static function render_stake_meta_box( $post ) {
        // Retrieve existing data
        $stake_points = get_post_meta( $post->ID, '_mkps_stake_points', true );

        // Nonce for security
        wp_nonce_field( 'mkps_save_stake_meta_box_data', 'mkps_stake_meta_box_nonce' );

        // Display form fields
        ?>
        <p>
            <label for="mkps_stake_points"><?php _e( 'Stake Points:', 'mk-point-staker' ); ?></label><br>
            <input type="number" id="mkps_stake_points" name="mkps_stake_points" value="<?php echo esc_attr( $stake_points ); ?>" min="1" required>
        </p>
        <?php
    }

    /**
     * Saves the meta box data when the stake post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public static function save_meta_box_data( $post_id ) {
        // Check nonce for security
        if ( ! isset( $_POST['mkps_stake_meta_box_nonce'] ) || 
             ! wp_verify_nonce( $_POST['mkps_stake_meta_box_nonce'], 'mkps_save_stake_meta_box_data' ) ) {
            return;
        }

        // Ensure user has permission to edit the post
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save Stake Points
        if ( isset( $_POST['mkps_stake_points'] ) ) {
            $stake_points = intval( $_POST['mkps_stake_points'] );
            update_post_meta( $post_id, '_mkps_stake_points', $stake_points );
        }
    }

    /**
     * Hooks into WordPress actions.
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post', array( __CLASS__, 'save_meta_box_data' ) );
    }
}

MKPS_Meta_Boxes::init();