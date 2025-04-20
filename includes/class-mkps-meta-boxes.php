<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Meta Boxes Handler
 */
class MKPS_Meta_Boxes {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'mkps_stake_details',
            __( 'Stake Details', 'mk-point-staker' ),
            array( $this, 'render_stake_details' ),
            'mkps_stake',
            'normal',
            'high'
        );
    }

    /**
     * Render stake details meta box
     */
    public function render_stake_details( $post ) {
        wp_nonce_field( 'mkps_save_stake_details', 'mkps_stake_nonce' );
        $points = get_post_meta( $post->ID, '_mkps_stake_points', true );
        $commission = get_post_meta( $post->ID, '_mkps_commission_rate', true );
        $commission = $commission ?: get_option( 'mkps_options', array( 'commission_rate' => 0.05 ) )['commission_rate'];
        ?>
        <p>
            <label for="mkps_stake_points"><?php _e( 'Stake Points', 'mk-point-staker' ); ?></label>
            <input type="number" id="mkps_stake_points" name="mkps_stake_points" value="<?php echo esc_attr( $points ); ?>" min="1">
        </p>
        <p>
            <label for="mkps_commission_rate"><?php _e( 'Commission Rate', 'mk-point-staker' ); ?></label>
            <input type="number" step="0.01" min="0" max="1" id="mkps_commission_rate" name="mkps_commission_rate" value="<?php echo esc_attr( $commission ); ?>">
            <span><?php _e( 'e.g., 0.05 for 5%', 'mk-point-staker' ); ?></span>
        </p>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['mkps_stake_nonce'] ) || ! wp_verify_nonce( $_POST['mkps_stake_nonce'], 'mkps_save_stake_details' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['mkps_stake_points'] ) ) {
            update_post_meta( $post_id, '_mkps_stake_points', absint( $_POST['mkps_stake_points'] ) );
        }

        if ( isset( $_POST['mkps_commission_rate'] ) ) {
            update_post_meta( $post_id, '_mkps_commission_rate', floatval( $_POST['mkps_commission_rate'] ) );
        }
    }
}

// Initialize
new MKPS_Meta_Boxes();