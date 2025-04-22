<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MKPS_Post_Type {

    /**
     * Registers the custom post type 'stake'
     */
    public static function register() {
        $labels = array(
            'name'                  => _x( 'Stakes', 'Post type general name', 'mk-point-staker' ),
            'singular_name'         => _x( 'Stake', 'Post type singular name', 'mk-point-staker' ),
            'menu_name'             => _x( 'Stakes', 'Admin Menu text', 'mk-point-staker' ),
            'name_admin_bar'        => _x( 'Stake', 'Add New on Toolbar', 'mk-point-staker' ),
            'add_new'               => __( 'Add New', 'mk-point-staker' ),
            'add_new_item'          => __( 'Add New Stake', 'mk-point-staker' ),
            'new_item'              => __( 'New Stake', 'mk-point-staker' ),
            'edit_item'             => __( 'Edit Stake', 'mk-point-staker' ),
            'view_item'             => __( 'View Stake', 'mk-point-staker' ),
            'all_items'             => __( 'All Stakes', 'mk-point-staker' ),
            'search_items'          => __( 'Search Stakes', 'mk-point-staker' ),
            'not_found'             => __( 'No stakes found.', 'mk-point-staker' ),
            'not_found_in_trash'    => __( 'No stakes found in Trash.', 'mk-point-staker' ),
            'filter_items_list'     => __( 'Filter stakes list', 'mk-point-staker' ),
            'items_list_navigation' => __( 'Stakes list navigation', 'mk-point-staker' ),
            'items_list'            => __( 'Stakes list', 'mk-point-staker' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'stake' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'supports'           => array( 'title' ),
            'map_meta_cap'       => true,
        );

        register_post_type( 'stake', $args );
    }

    /**
     * Adjusts visibility so users see their own stakes in the backend
     */
    public static function adjust_stake_visibility( $query ) {
        if ( is_admin() && $query->is_main_query() && 'stake' === $query->get( 'post_type' ) && ! current_user_can( 'edit_others_posts' ) ) {
            $query->set( 'author', get_current_user_id() );
        }
    }
}

// Hook into WordPress actions
add_action( 'init', array( 'MKPS_Post_Type', 'register' ) );
add_action( 'pre_get_posts', array( 'MKPS_Post_Type', 'adjust_stake_visibility' ) );