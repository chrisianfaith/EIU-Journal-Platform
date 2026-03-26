<?php
/**
 * Review Model.
 *
 * @package EIU_Research_Publication
 * @subpackage Models
 */

namespace EIU_RP\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Review
 */
class Review {

    const STATUS_ASSIGNED   = 'assigned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTED  = 'submitted';
    const STATUS_APPROVED   = 'approved';
    const STATUS_REJECTED   = 'rejected';

    const REC_ACCEPT       = 'accept';
    const REC_MINOR        = 'minor_revision';
    const REC_MAJOR        = 'major_revision';
    const REC_REJECT       = 'reject';

    /**
     * Assign a reviewer to an article.
     *
     * @param int    $article_id  Article row ID.
     * @param int    $reviewer_id Reviewer row ID.
     * @param string $due_date    Due date (Y-m-d). Optional.
     * @return int|WP_Error Review row ID or error.
     */
    public static function assign( int $article_id, int $reviewer_id, string $due_date = '' ) {
        global $wpdb;

        // Prevent duplicate assignment.
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eiu_reviews WHERE article_id = %d AND reviewer_id = %d AND is_deleted = 0",
            $article_id, $reviewer_id
        ) );

        if ( $exists ) {
            return new \WP_Error( 'duplicate_assignment', __( 'This reviewer is already assigned to this article.', 'eiu-rp' ) );
        }

        if ( empty( $due_date ) ) {
            $days     = (int) get_option( 'eiu_rp_review_days_due', 14 );
            $due_date = date( 'Y-m-d', strtotime( "+{$days} days" ) );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'eiu_reviews',
            array(
                'article_id'    => $article_id,
                'reviewer_id'   => $reviewer_id,
                'assigned_at'   => current_time( 'mysql' ),
                'due_date'      => $due_date,
                'status'        => self::STATUS_ASSIGNED,
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            return new \WP_Error( 'db_insert_failed', __( 'Could not create review assignment.', 'eiu-rp' ) );
        }

        $review_id = $wpdb->insert_id;

        // Update article status.
        Article::update_status( $article_id, Article::STATUS_UNDER_REVIEW );

        do_action( 'eiu_rp_reviewer_assigned', $review_id, $article_id, $reviewer_id );

        return $review_id;
    }

    /**
     * Submit a review.
     *
     * @param int    $review_id      Review row ID.
     * @param string $recommendation Recommendation slug.
     * @param string $comments       Review comments.
     * @return bool
     */
    public static function submit( int $review_id, string $recommendation, string $comments ): bool {
        global $wpdb;

        $valid_recs = array( self::REC_ACCEPT, self::REC_MINOR, self::REC_MAJOR, self::REC_REJECT );
        if ( ! in_array( $recommendation, $valid_recs, true ) ) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'eiu_reviews',
            array(
                'recommendation' => $recommendation,
                'comments'       => $comments,
                'status'         => self::STATUS_SUBMITTED,
                'submitted_at'   => current_time( 'mysql' ),
            ),
            array( 'id' => $review_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            do_action( 'eiu_rp_review_submitted', $review_id );
        }

        return $result !== false;
    }

    /**
     * Approve or reject a review (admin moderation).
     *
     * @param int    $review_id  Review row ID.
     * @param string $status     approved|rejected.
     * @param string $admin_notes Admin notes.
     * @return bool
     */
    public static function moderate( int $review_id, string $status, string $admin_notes = '' ): bool {
        global $wpdb;

        $valid = array( self::STATUS_APPROVED, self::STATUS_REJECTED );
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'eiu_reviews',
            array(
                'status'      => $status,
                'admin_notes' => $admin_notes,
            ),
            array( 'id' => $review_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( $result !== false ) {
            do_action( 'eiu_rp_review_moderated', $review_id, $status );
        }

        return $result !== false;
    }

    /**
     * Soft-delete a review (admin).
     *
     * @param int $review_id Review row ID.
     * @return bool
     */
    public static function delete( int $review_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'eiu_reviews',
            array( 'is_deleted' => 1 ),
            array( 'id' => $review_id ),
            array( '%d' ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Get a single review.
     *
     * @param int $id Review row ID.
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, rv.full_name as reviewer_name, rv.email as reviewer_email,
                    a.post_id, p.post_title as article_title
             FROM {$wpdb->prefix}eiu_reviews r
             LEFT JOIN {$wpdb->prefix}eiu_reviewers rv ON r.reviewer_id = rv.id
             LEFT JOIN {$wpdb->prefix}eiu_articles a ON r.article_id = a.id
             LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
             WHERE r.id = %d AND r.is_deleted = 0",
            $id
        ) );
    }

    /**
     * Get all reviews for a given article.
     *
     * @param int $article_id Article row ID.
     * @return array
     */
    public static function get_by_article( int $article_id ): array {
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, rv.full_name as reviewer_name, rv.email as reviewer_email
             FROM {$wpdb->prefix}eiu_reviews r
             LEFT JOIN {$wpdb->prefix}eiu_reviewers rv ON r.reviewer_id = rv.id
             WHERE r.article_id = %d AND r.is_deleted = 0
             ORDER BY r.assigned_at ASC",
            $article_id
        ), ARRAY_A );

        return $results ?: array();
    }

    /**
     * Get all reviews assigned to a reviewer.
     *
     * @param int $reviewer_id Reviewer row ID.
     * @return array
     */
    public static function get_by_reviewer( int $reviewer_id ): array {
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*,
                    p.post_title  AS article_title,
                    a.id          AS article_row_id,
                    a.post_id,
                    a.author_name,
                    a.author_email,
                    a.coauthor_name,
                    a.coauthor_email,
                    a.keywords,
                    a.disclosures,
                    a.advisers,
                    a.issn,
                    a.file_name,
                    a.file_path,
                    a.file_type,
                    a.submitted_at,
                    a.country
             FROM {$wpdb->prefix}eiu_reviews r
             LEFT JOIN {$wpdb->prefix}eiu_articles a ON r.article_id = a.id
             LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
             WHERE r.reviewer_id = %d AND r.is_deleted = 0
             ORDER BY r.assigned_at DESC",
            $reviewer_id
        ), ARRAY_A );

        return $results ?: array();
    }

    /**
     * Return human-readable recommendation label.
     *
     * @param string $rec Recommendation slug.
     * @return string
     */
    public static function recommendation_label( string $rec ): string {
        $labels = array(
            self::REC_ACCEPT => __( 'Accept', 'eiu-rp' ),
            self::REC_MINOR  => __( 'Minor Revision', 'eiu-rp' ),
            self::REC_MAJOR  => __( 'Major Revision', 'eiu-rp' ),
            self::REC_REJECT => __( 'Reject', 'eiu-rp' ),
        );
        return $labels[ $rec ] ?? ucwords( str_replace( '_', ' ', $rec ) );
    }
}
