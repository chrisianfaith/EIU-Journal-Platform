<?php
/**
 * Custom Taxonomies.
 *
 * @package EIU_Research_Publication
 * @subpackage Core
 */

namespace EIU_RP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Taxonomies
 */
class Taxonomies {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
    }

    /**
     * Register taxonomies.
     */
    public function register(): void {
        $this->register_subject_taxonomy();
    }

    /**
     * Register eiu_subject taxonomy.
     */
    private function register_subject_taxonomy(): void {
        $labels = array(
            'name'              => _x( 'Subjects', 'taxonomy general name', 'eiu-rp' ),
            'singular_name'     => _x( 'Subject', 'taxonomy singular name', 'eiu-rp' ),
            'search_items'      => __( 'Search Subjects', 'eiu-rp' ),
            'all_items'         => __( 'All Subjects', 'eiu-rp' ),
            'parent_item'       => __( 'Parent Subject', 'eiu-rp' ),
            'parent_item_colon' => __( 'Parent Subject:', 'eiu-rp' ),
            'edit_item'         => __( 'Edit Subject', 'eiu-rp' ),
            'update_item'       => __( 'Update Subject', 'eiu-rp' ),
            'add_new_item'      => __( 'Add New Subject', 'eiu-rp' ),
            'new_item_name'     => __( 'New Subject Name', 'eiu-rp' ),
            'menu_name'         => __( 'Subjects', 'eiu-rp' ),
            'not_found'         => __( 'No subjects found.', 'eiu-rp' ),
        );

        register_taxonomy( 'eiu_subject', array( 'eiu_article' ), array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'research-subject' ),
            'show_in_rest'      => true,
            'rest_base'         => 'eiu-subjects',
        ) );
    }
}
