<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin Activation Hook
 */
function mkps_activate_plugin() {
    // Register Stake post type to initialize rewrite rules
    mkps_register_stake_post_type();
    flush_rewrite_rules();

    // Set up default options or configurations if needed
    if ( ! get_option( 'mkps_plugin_options' ) ) {
        update_option( 'mkps_plugin_options', array(
            'version' => '1.0.0',
            'setup'   => true,
        ));
    }

    // Check required plugins (myCRED and SportsPress) and notify if any are missing
    if ( ! class_exists( 'myCRED_Core' ) || ! class_exists( 'SportsPress' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'MK Point Staker requires myCRED and SportsPress plugins to be installed and activated.', 'mk-point-staker' ) );
    }

    // Optional: Warn about Ultimate Member for enhanced features
    if ( ! class_exists( 'UM' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-info is-dismissible">
                    <p>' . __( 'MK Point Staker can enhance profile integration with Ultimate Member. Install and activate UM for additional features like notification counts in the profile menu.', 'mk-point-staker' ) . '</p>
                  </div>';
        });
    }
}

/**
 * Plugin Deactivation Hook
 */
function mkps_deactivate_plugin() {
    // Flush rewrite rules to clean up registered post types and endpoints
    flush_rewrite_rules();

    // Optional: Delete specific options or meta data created by the plugin
    delete_option( 'mkps_plugin_options' );
}

/**
 * Register Stake Post Type (called during activation)
 */
function mkps_register_stake_post_type() {
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