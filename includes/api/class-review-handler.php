<?php
/**
 * Review AJAX Handler.
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */

namespace EIU_RP\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use EIU_RP\Security\Security;
use EIU_RP\Models\Review;
use EIU_RP\Models\Reviewer;
use EIU_RP\Models\Activity_Log;

/**
 * Class Review_Handler
 */
class Review_Handler {

    public function __construct() {
        add_action( 'wp_ajax_eiu_rp_submit_review',         array( $this, 'submit' ) );
        add_action( 'wp_ajax_eiu_rp_admin_moderate_review', array( $this, 'moderate' ) );
        add_action( 'wp_ajax_eiu_rp_admin_delete_review',   array( $this, 'delete' ) );
        add_action( 'wp_ajax_eiu_rp_admin_update_article_status', array( $this, 'update_article_status' ) );
        // v2.1: Reviewer-facing status & notes AJAX
        add_action( 'wp_ajax_eiu_rp_reviewer_update_status',  array( $this, 'reviewer_update_status' ) );
        add_action( 'wp_ajax_eiu_rp_reviewer_save_notes',     array( $this, 'reviewer_save_notes' ) );
        add_action( 'wp_ajax_eiu_rp_assign_co_reviewer',      array( $this, 'assign_co_reviewer' ) );
    }

    /**
     * Reviewer submits their review.
     */
    public function submit(): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        Security::verify_nonce( $nonce, 'eiu_rp_frontend' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'eiu-rp' ) ), 403 );
        }

        $user_id  = get_current_user_id();
        $reviewer = Reviewer::get_by_user( $user_id );

        if ( ! $reviewer ) {
            wp_send_json_error( array( 'message' => __( 'Reviewer profile not found.', 'eiu-rp' ) ), 403 );
        }

        $review_id     = Security::sanitize_int( $_POST['review_id'] ?? 0 );
        $recommendation = Security::sanitize_text( $_POST['recommendation'] ?? '' );
        // v2.1: Comments now accept rich HTML from the TinyMCE WYSIWYG editor.
        // wp_kses_post() allows safe formatting tags (bold, lists, links, images)
        // while blocking scripts and other dangerous markup.
        $comments = wp_kses_post( wp_unslash( $_POST['comments'] ?? '' ) );

        if ( ! $review_id || ! $recommendation || ! $comments ) {
            wp_send_json_error( array( 'message' => __( 'All fields are required.', 'eiu-rp' ) ), 422 );
        }

        // Confirm the review belongs to this reviewer.
        $review = Review::get( $review_id );
        if ( ! $review || (int) $review->reviewer_id !== (int) $reviewer->id ) {
            wp_send_json_error( array( 'message' => __( 'Review not found or permission denied.', 'eiu-rp' ) ), 403 );
        }

        if ( $review->status === Review::STATUS_SUBMITTED ) {
            wp_send_json_error( array( 'message' => __( 'This review has already been submitted.', 'eiu-rp' ) ), 400 );
        }

        // Extra validation: revision recommendations require substantive comments.
        $is_revision_rec = in_array( $recommendation, array( 'minor_revision', 'major_revision' ), true );
        if ( $is_revision_rec && strlen( trim( $comments ) ) < 20 ) {
            wp_send_json_error( array(
                'message' => __( 'Please provide detailed revision notes (at least 20 characters) so the researcher knows what to improve.', 'eiu-rp' ),
            ), 422 );
        }

        $result = Review::submit( $review_id, $recommendation, $comments );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not submit review. Please try again.', 'eiu-rp' ) ), 500 );
        }

        // When recommendation is a revision type, set article status to revision_required
        // and store the reviewer comments so the researcher can see them.
        if ( $is_revision_rec ) {
            $article_id_for_rev = (int) ( $review->article_id ?? 0 );
            if ( $article_id_for_rev ) {
                \EIU_RP\Models\Article::update_status(
                    $article_id_for_rev,
                    \EIU_RP\Models\Article::STATUS_REVISION,
                    $comments
                );
                // eiu_rp_revision_required action fires inside update_status and
                // triggers the mailer notification to the researcher automatically.
            }
        }

        Activity_Log::log(
            'review_submitted',
            'review',
            $review_id,
            sprintf( 'Review #%d submitted by reviewer #%d (recommendation: %s)', $review_id, $reviewer->id, $recommendation ),
            $user_id
        );

        wp_send_json_success( array(
            'message' => $is_revision_rec
                ? __( 'Review submitted. The researcher has been notified and can now revise their article.', 'eiu-rp' )
                : __( 'Your review has been submitted successfully.', 'eiu-rp' ),
        ) );
    }

    /**
     * Admin: approve or reject a submitted review.
     */
    public function moderate(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_reviews' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $review_id   = Security::sanitize_int( $_POST['review_id'] ?? 0 );
        $status      = Security::sanitize_text( $_POST['status'] ?? '' );
        $admin_notes = Security::sanitize_textarea( $_POST['admin_notes'] ?? '' );

        $result = Review::moderate( $review_id, $status, $admin_notes );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not update review.', 'eiu-rp' ) ) );
        }

        Activity_Log::log(
            'review_moderated',
            'review',
            $review_id,
            "Review #$review_id moderated to status '$status'."
        );

        wp_send_json_success( array( 'message' => __( 'Review updated.', 'eiu-rp' ) ) );
    }

    /**
     * Admin: soft-delete a review.
     */
    public function delete(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_reviews' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $review_id = Security::sanitize_int( $_POST['review_id'] ?? 0 );
        $result    = Review::delete( $review_id );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not delete review.', 'eiu-rp' ) ) );
        }

        Activity_Log::log( 'review_deleted', 'review', $review_id, "Review #$review_id deleted by admin." );

        wp_send_json_success( array( 'message' => __( 'Review deleted.', 'eiu-rp' ) ) );
    }

    /**
     * Admin: update article status.
     */
    public function update_article_status(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_articles' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id     = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        $status         = Security::sanitize_text( $_POST['status'] ?? '' );
        $revision_notes = Security::sanitize_textarea( $_POST['revision_notes'] ?? '' );

        // When admin sets status to revision_required, revision notes are required.
        if ( $status === \EIU_RP\Models\Article::STATUS_REVISION && empty( trim( $revision_notes ) ) ) {
            wp_send_json_error( array(
                'message' => __( 'Revision notes are required when setting status to Revision Required.', 'eiu-rp' ),
            ), 422 );
        }

        $published_at = sanitize_text_field( wp_unslash( $_POST['published_at'] ?? '' ) );
        $result = \EIU_RP\Models\Article::update_status( $article_id, $status, $revision_notes, $published_at );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not update article status.', 'eiu-rp' ) ) );
        }

        Activity_Log::log( 'article_status_updated', 'article', $article_id, "Article #$article_id status updated to '$status'." );

        wp_send_json_success( array(
            'message' => __( 'Article status updated.', 'eiu-rp' ),
            'label'   => \EIU_RP\Models\Article::status_label( $status ),
        ) );
    }
    /**
     * v2.1: Reviewer updates the article/review status (all system statuses allowed).
     */
    public function reviewer_update_status(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        $reviewer = \EIU_RP\Models\Reviewer::get_by_user( get_current_user_id() );
        if ( ! $reviewer ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Reviewer not found.', 'eiu-rp' ) ), 403 );
        }

        $review_id  = \EIU_RP\Security\Security::sanitize_int( $_POST['review_id'] ?? 0 );
        $new_status = \EIU_RP\Security\Security::sanitize_text( $_POST['status'] ?? '' );
        $notes      = wp_kses_post( wp_unslash( $_POST['revision_notes'] ?? '' ) );

        // Validate status is in allowed list
        $allowed = array(
            \EIU_RP\Models\Article::STATUS_PENDING,
            \EIU_RP\Models\Article::STATUS_UNDER_REVIEW,
            \EIU_RP\Models\Article::STATUS_APPROVED,
            \EIU_RP\Models\Article::STATUS_REJECTED,
            \EIU_RP\Models\Article::STATUS_PUBLISHED,
            \EIU_RP\Models\Article::STATUS_REVISION,
        );
        if ( ! in_array( $new_status, $allowed, true ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid status.', 'eiu-rp' ) ), 422 );
        }

        if ( $new_status === \EIU_RP\Models\Article::STATUS_REVISION && empty( trim( $notes ) ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Revision notes are required when setting Revision Required.', 'eiu-rp' ) ), 422 );
        }

        global $wpdb;
        $review = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eiu_reviews WHERE id = %d AND is_deleted = 0",
            $review_id
        ) );

        if ( ! $review || (int) $review->reviewer_id !== (int) $reviewer->id ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Review not found or permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id   = (int) $review->article_id;
        $published_at = sanitize_text_field( wp_unslash( $_POST['published_at'] ?? '' ) );
        $updated      = \EIU_RP\Models\Article::update_status( $article_id, $new_status, $notes, $published_at );

        if ( ! $updated ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Could not update status.', 'eiu-rp' ) ) );
        }

        \EIU_RP\Models\Activity_Log::log(
            'reviewer_status_updated', 'article', $article_id,
            "Reviewer #{$reviewer->id} set article #{$article_id} status to '{$new_status}'."
        );

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'Status updated successfully.', 'eiu-rp' ),
            'label'   => \EIU_RP\Models\Article::status_label( $new_status ),
        ) );
    }

    /**
     * v2.1: Reviewer saves co-reviewer name and private notes for a review.
     */
    public function reviewer_save_notes(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        $reviewer = \EIU_RP\Models\Reviewer::get_by_user( get_current_user_id() );
        if ( ! $reviewer ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Reviewer not found.', 'eiu-rp' ) ), 403 );
        }

        $review_id   = \EIU_RP\Security\Security::sanitize_int( $_POST['review_id'] ?? 0 );
        // co_reviewer is now a JSON-encoded array of reviewer IDs (multi-select).
        // Accept either: JSON array of int IDs, or legacy plain text name.
        $co_reviewer_raw = wp_unslash( $_POST['co_reviewer'] ?? '' );
        $decoded = json_decode( $co_reviewer_raw, true );
        if ( is_array( $decoded ) ) {
            // Sanitize: only keep positive integers
            $co_reviewer = wp_json_encode( array_values( array_filter( array_map( 'absint', $decoded ) ) ) );
        } else {
            // Legacy fallback: plain text name
            $co_reviewer = sanitize_text_field( $co_reviewer_raw );
        }
        $rev_notes   = wp_kses_post( wp_unslash( $_POST['reviewer_notes'] ?? '' ) );

        global $wpdb;
        $review = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eiu_reviews WHERE id = %d AND is_deleted = 0",
            $review_id
        ) );

        if ( ! $review || (int) $review->reviewer_id !== (int) $reviewer->id ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Review not found or permission denied.', 'eiu-rp' ) ), 403 );
        }

        $wpdb->update(
            $wpdb->prefix . 'eiu_reviews',
            array( 'co_reviewer' => $co_reviewer, 'reviewer_notes' => $rev_notes ),
            array( 'id' => $review_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        \EIU_RP\Models\Activity_Log::log(
            'reviewer_notes_saved', 'review', $review_id,
            "Reviewer #{$reviewer->id} saved notes for review #{$review_id}."
        );

        // Fire action so Mailer can notify co-reviewers about updated notes.
        if ( ! empty( trim( $rev_notes ) ) ) {
            do_action( 'eiu_rp_reviewer_notes_saved', $review_id, $reviewer, $co_reviewer, $rev_notes );
        }

        ob_end_clean();
        wp_send_json_success( array( 'message' => __( 'Notes saved and co-reviewers notified.', 'eiu-rp' ) ) );
    }

    /**
     * v2.2: Assign one or more co-reviewers to a review.
     * Saves the co-reviewer list and sends notification emails to newly added ones.
     */
    public function assign_co_reviewer(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        $reviewer = \EIU_RP\Models\Reviewer::get_by_user( get_current_user_id() );
        if ( ! $reviewer ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Reviewer not found.', 'eiu-rp' ) ), 403 );
        }

        $review_id      = \EIU_RP\Security\Security::sanitize_int( $_POST['review_id'] ?? 0 );
        $new_ids_raw    = wp_unslash( $_POST['co_reviewer_ids'] ?? '' );
        $new_ids_arr    = json_decode( $new_ids_raw, true );
        if ( ! is_array( $new_ids_arr ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid co-reviewer selection.', 'eiu-rp' ) ) );
        }
        $new_ids = array_values( array_filter( array_map( 'absint', $new_ids_arr ) ) );

        global $wpdb;
        $review = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eiu_reviews WHERE id = %d AND is_deleted = 0",
            $review_id
        ) );
        if ( ! $review || (int) $review->reviewer_id !== (int) $reviewer->id ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Review not found or permission denied.', 'eiu-rp' ) ), 403 );
        }

        // Determine previously assigned co-reviewers to find newly added ones.
        $prev_ids = array();
        if ( ! empty( $review->co_reviewer ) ) {
            $decoded  = json_decode( $review->co_reviewer, true );
            $prev_ids = is_array( $decoded ) ? array_map( 'intval', $decoded ) : array();
        }
        $newly_added = array_diff( $new_ids, $prev_ids );

        // Save the new co-reviewer list.
        $wpdb->update(
            $wpdb->prefix . 'eiu_reviews',
            array( 'co_reviewer' => wp_json_encode( $new_ids ) ),
            array( 'id' => $review_id ),
            array( '%s' ), array( '%d' )
        );

        // Fire action for newly added co-reviewers — Mailer sends emails.
        if ( ! empty( $newly_added ) ) {
            $article = \EIU_RP\Models\Article::get( (int) $review->article_id );
            foreach ( $newly_added as $co_id ) {
                $co_rv = \EIU_RP\Models\Reviewer::get( $co_id );
                if ( $co_rv && $article ) {
                    do_action( 'eiu_rp_co_reviewer_assigned', $review_id, $co_rv, $reviewer, $article );
                }
            }
        }

        \EIU_RP\Models\Activity_Log::log(
            'co_reviewer_assigned', 'review', $review_id,
            sprintf( 'Reviewer #%d updated co-reviewers for review #%d. IDs: %s', $reviewer->id, $review_id, implode( ',', $new_ids ) )
        );

        ob_end_clean();
        wp_send_json_success( array(
            'message'      => sprintf(
                _n( '%d co-reviewer assigned.', '%d co-reviewers assigned and notified.', count( $new_ids ), 'eiu-rp' ),
                count( $new_ids )
            ),
            'assigned_ids' => $new_ids,
            'notified'     => array_values( $newly_added ),
        ) );
    }

}
