<?php
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Models\Review;

class Reviews_List {
    public function render(): void {
        global $wpdb;
        $action = sanitize_text_field( $_GET['action'] ?? 'list' );
        $id     = absint( $_GET['id'] ?? 0 );

        if ( $action === 'view' && $id ) {
            $review = Review::get( $id );
            \EIU_RP\Utils\Template_Loader::get_template( 'admin/review-view.php', compact( 'review' ) );
            return;
        }

        $status  = sanitize_text_field( $_GET['status'] ?? '' );
        $per_page = 20;
        $page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $where    = 'is_deleted = 0';
        if ( $status ) {
            $where .= $wpdb->prepare( ' AND r.status = %s', $status );
        }
        $offset = ( $page - 1 ) * $per_page;
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // $where is built only from trusted prepared fragments; $per_page/$offset are absint.
        $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_reviews r WHERE {$where}" ); // phpcs:ignore
        $items  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, rv.full_name as reviewer_name, p.post_title as article_title
                 FROM {$wpdb->prefix}eiu_reviews r
                 LEFT JOIN {$wpdb->prefix}eiu_reviewers rv ON r.reviewer_id = rv.id
                 LEFT JOIN {$wpdb->prefix}eiu_articles a ON r.article_id = a.id
                 LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
                 WHERE {$where} ORDER BY r.assigned_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        // phpcs:enable
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/reviews-list.php', compact( 'items','total','per_page','page' ) );
    }
}
