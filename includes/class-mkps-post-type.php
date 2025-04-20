<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom Post Type Handler
 */
class MKPS_Post_Type {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    /**
     * Register stake post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __( 'Stakes', 'mk-point-staker' ),
            'singular_name'      => __( 'Stake', 'mk-point-staker' ),
            'add_new'            => __( 'Add New', 'mk-point-staker' ),
            'add_new_item'       => __( 'Add New Stake', 'mk-point-staker' ),
            'edit_item'          => __( 'Edit Stake', 'mk-point-staker' ),
            'new_item'           => __( 'New Stake', 'mk-point-staker' ),
            'view_item'          => __( 'View Stake', 'mk-point-staker' ),
            'search_items'       => __( 'Search Stakes', 'mk-point-staker' ),
            'not_found'          => __( 'No stakes found', 'mk-point-staker' ),
            'not_found_in_trash' => __( 'No stakes found in Trash', 'mk-point-staker' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'stake' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'supports'           => array( 'title', 'author' ),
        );

        register_post_type( 'mkps_stake', $args );
    }
}

// Initialize
new MKPS_Post_Type();