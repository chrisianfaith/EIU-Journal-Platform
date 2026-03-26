<?php
/**
 * Custom Post Types.
 *
 * @package EIU_Research_Publication
 * @subpackage Core
 */

namespace EIU_RP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Post_Types
 */
class Post_Types {

    public function __construct() {
        add_action( 'init',                  array( $this, 'register' ) );
        add_action( 'init',                  array( $this, 'maybe_flush_rewrites' ) );
        add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
    }

    /**
     * Register all custom post types.
     */
    public function register(): void {
        $this->register_article_cpt();
        // Register a hard-cropped 150×150 thumbnail size for all article thumbnails.
        add_image_size( 'eiu-thumb-150', 150, 150, true );
    }

    /**
     * Register eiu_article CPT.
     */
    private function register_article_cpt(): void {
        $labels = array(
            'name'                  => _x( 'Articles', 'post type general name', 'eiu-rp' ),
            'singular_name'         => _x( 'Article', 'post type singular name', 'eiu-rp' ),
            'menu_name'             => _x( 'Articles', 'admin menu', 'eiu-rp' ),
            'name_admin_bar'        => _x( 'Article', 'add new on toolbar', 'eiu-rp' ),
            'add_new'               => _x( 'Add New', 'article', 'eiu-rp' ),
            'add_new_item'          => __( 'Add New Article', 'eiu-rp' ),
            'new_item'              => __( 'New Article', 'eiu-rp' ),
            'edit_item'             => __( 'Edit Article', 'eiu-rp' ),
            'view_item'             => __( 'View Article', 'eiu-rp' ),
            'all_items'             => __( 'All Articles', 'eiu-rp' ),
            'search_items'          => __( 'Search Articles', 'eiu-rp' ),
            'not_found'             => __( 'No articles found.', 'eiu-rp' ),
            'not_found_in_trash'    => __( 'No articles found in trash.', 'eiu-rp' ),
            'featured_image'        => __( 'Article Cover', 'eiu-rp' ),
            'set_featured_image'    => __( 'Set article cover', 'eiu-rp' ),
            'remove_featured_image' => __( 'Remove article cover', 'eiu-rp' ),
            'use_featured_image'    => __( 'Use as article cover', 'eiu-rp' ),
            'archives'              => __( 'Article Archives', 'eiu-rp' ),
            'insert_into_item'      => __( 'Insert into article', 'eiu-rp' ),
            'uploaded_to_this_item' => __( 'Uploaded to this article', 'eiu-rp' ),
            'items_list'            => __( 'Articles list', 'eiu-rp' ),
            'items_list_navigation' => __( 'Articles list navigation', 'eiu-rp' ),
            'filter_items_list'     => __( 'Filter articles list', 'eiu-rp' ),
        );

        register_post_type( 'eiu_article', array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // We manage in custom menu.
            'show_in_nav_menus'   => true,
            'show_in_admin_bar'   => true,
            'query_var'           => true,
            'rewrite'             => array( 'slug' => 'research-article', 'with_front' => false ),
            'capability_type'     => array( 'eiu_article', 'eiu_articles' ),
            'map_meta_cap'        => true,
            'has_archive'         => 'research-articles',
            'hierarchical'        => false,
            'menu_position'       => null,
            'menu_icon'           => 'dashicons-media-document',
            'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'comments' ),
            'show_in_rest'        => true,
            'rest_base'           => 'eiu-articles',
            'taxonomies'          => array( 'eiu_subject' ),
        ) );
    }

    /**
     * Flush rewrite rules only once after activation.
     */
    public function maybe_flush_rewrites(): void {
        if ( get_option( 'eiu_rp_flush_rewrites' ) ) {
            flush_rewrite_rules();
            delete_option( 'eiu_rp_flush_rewrites' );
        }
    }

    /**
     * Customise CPT updated messages.
     *
     * @param array $messages Existing messages.
     * @return array
     */
    public function updated_messages( array $messages ): array {
        global $post;
        $messages['eiu_article'] = array(
            0  => '',
            1  => __( 'Article updated.', 'eiu-rp' ),
            2  => __( 'Custom field updated.', 'eiu-rp' ),
            3  => __( 'Custom field deleted.', 'eiu-rp' ),
            4  => __( 'Article updated.', 'eiu-rp' ),
            5  => isset( $_GET['revision'] ) ? sprintf( __( 'Article restored to revision from %s.', 'eiu-rp' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6  => __( 'Article published.', 'eiu-rp' ),
            7  => __( 'Article saved.', 'eiu-rp' ),
            8  => __( 'Article submitted.', 'eiu-rp' ),
            9  => sprintf( __( 'Article scheduled for: <strong>%1$s</strong>.', 'eiu-rp' ), date_i18n( __( 'M j, Y @ G:i', 'eiu-rp' ), strtotime( $post->post_date ) ) ),
            10 => __( 'Article draft updated.', 'eiu-rp' ),
        );
        return $messages;
    }
}
