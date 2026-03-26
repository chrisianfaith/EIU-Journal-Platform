<?php
/**
 * Uninstall Script – runs when plugin is deleted from WP admin.
 *
 * CAUTION: This permanently removes all plugin data.
 *
 * @package EIU_Research_Publication
 */

// Security check.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Remove all plugin data for a single site.
 */
function eiu_rp_uninstall_site() {
    global $wpdb;

    // Drop custom tables.
    $tables = array(
        $wpdb->prefix . 'eiu_articles',
        $wpdb->prefix . 'eiu_reviewers',
        $wpdb->prefix . 'eiu_reviews',
        $wpdb->prefix . 'eiu_activity_log',
        $wpdb->prefix . 'eiu_notifications',
        $wpdb->prefix . 'eiu_download_leads', // v1.2
    );
    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
    }

    // Remove plugin options.
    $options = array(
        'eiu_rp_version',
        'eiu_rp_activated_at',
        'eiu_rp_flush_rewrites',
        'eiu_rp_from_name',
        'eiu_rp_from_email',
        'eiu_rp_submission_notification_email',
        'eiu_rp_max_file_size_mb',
        'eiu_rp_allowed_file_types',
        'eiu_rp_review_days_due',
        'eiu_rp_welcome_accepted',
        'eiu_rp_subjects',
        'eiu_rp_submission_page_id',
        'eiu_rp_reviewer_page_id',
        'eiu_rp_listing_page_id',
        'eiu_rp_schema_version',         // v1.2
        'eiu_rp_reviewer_access_page_id', // v1.3
        'eiu_rp_default_reviewers',          // v1.5
        'eiu_rp_auto_assign_mode',           // v1.5
    );
    foreach ( $options as $opt ) {
        delete_option( $opt );
    }

    // Remove custom roles.
    remove_role( 'eiu_reviewer' );

    // Remove caps from editor and admin.
    $caps_to_remove = array(
        'eiu_manage_articles',
        'eiu_manage_reviewers',
        'eiu_manage_reviews',
        'eiu_view_activity_log',
        'eiu_manage_settings',
    );
    foreach ( array( 'editor', 'administrator' ) as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            foreach ( $caps_to_remove as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }

    // Remove user meta.
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'eiu_rp_%'" ); // phpcs:ignore

    // Delete uploaded articles directory.
    $upload_dir  = wp_upload_dir();
    $article_dir = $upload_dir['basedir'] . '/eiu-articles';
    if ( is_dir( $article_dir ) ) {
        eiu_rp_rmdir( $article_dir );
    }

    // Delete CPT posts.
    $posts = get_posts( array(
        'post_type'      => 'eiu_article',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ) );
    foreach ( $posts as $post_id ) {
        wp_delete_post( $post_id, true );
    }

    // Clean up taxonomy terms.
    $terms = get_terms( array( 'taxonomy' => 'eiu_subject', 'hide_empty' => false ) );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            wp_delete_term( $term->term_id, 'eiu_subject' );
        }
    }

    // Flush rewrite rules.
    flush_rewrite_rules();
}

/**
 * Recursively remove a directory.
 *
 * @param string $dir Directory path.
 */
function eiu_rp_rmdir( string $dir ): void {
    if ( ! is_dir( $dir ) ) { return; }
    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $items as $item ) {
        $item->isDir() ? rmdir( $item->getRealPath() ) : unlink( $item->getRealPath() );
    }
    rmdir( $dir );
}

// Run for network or single site.
if ( is_multisite() ) {
    $sites = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        eiu_rp_uninstall_site();
        restore_current_blog();
    }
} else {
    eiu_rp_uninstall_site();
}
