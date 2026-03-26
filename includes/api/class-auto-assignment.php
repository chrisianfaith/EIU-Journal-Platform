<?php
/**
 * Auto Reviewer Assignment.
 *
 * Hooks into eiu_rp_article_created and assigns the article to
 * all reviewers listed in the eiu_rp_default_reviewers option.
 *
 * Options:
 *   eiu_rp_default_reviewers  — array of reviewer row IDs (int[])
 *   eiu_rp_auto_assign_mode   — 'all' | 'none'  (default: 'all')
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */

namespace EIU_RP\API;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Review;
use EIU_RP\Models\Activity_Log;

/**
 * Class Auto_Assignment
 */
class Auto_Assignment {

    public function __construct() {
        // Priority 20 — runs after the Mailer (priority 10) so article exists fully.
        add_action( 'eiu_rp_article_created', array( $this, 'on_article_created' ), 20, 3 );

        // Admin AJAX handlers for managing default reviewers list.
        add_action( 'wp_ajax_eiu_rp_add_default_reviewer',    array( $this, 'ajax_add' ) );
        add_action( 'wp_ajax_eiu_rp_remove_default_reviewer', array( $this, 'ajax_remove' ) );
        add_action( 'wp_ajax_eiu_rp_save_assign_mode',        array( $this, 'ajax_save_mode' ) );
    }

    /**
     * Fired when a new article is created.
     * Assigns it to all default reviewers based on the configured mode.
     *
     * @param int   $article_id EIU article row ID.
     * @param int   $post_id    WP post ID.
     * @param array $data       Submitted article data.
     */
    public function on_article_created( int $article_id, int $post_id, array $data ): void {
        $mode = get_option( 'eiu_rp_auto_assign_mode', 'all' );

        if ( $mode === 'none' ) {
            Activity_Log::log( 'auto_assign_skipped', 'article', $article_id,
                'Auto-assignment skipped: mode is set to none.' );
            return;
        }

        // Mode 'subject': assign reviewers whose specialization matches the article subject.
        if ( $mode === 'subject' ) {
            $this->assign_by_subject( $article_id, $data );
            return;
        }

        // Mode 'all': assign every reviewer in the default list.
        $default_reviewer_ids = array_filter(
            array_map( 'absint', (array) get_option( 'eiu_rp_default_reviewers', array() ) )
        );

        if ( empty( $default_reviewer_ids ) ) {
            // Fallback: try subject matching if no default reviewers configured
            Activity_Log::log( 'auto_assign_fallback', 'article', $article_id,
                'No default reviewers set — falling back to subject-based assignment.' );
            $this->assign_by_subject( $article_id, $data );
            return;
        }

        $assigned = 0;
        $errors   = array();
        foreach ( $default_reviewer_ids as $reviewer_id ) {
            $result = Review::assign( $article_id, $reviewer_id );
            if ( is_wp_error( $result ) ) {
                $errors[] = "Reviewer #{$reviewer_id}: " . $result->get_error_message();
            } else {
                $assigned++;
            }
        }

        Activity_Log::log(
            'auto_assigned',
            'article',
            $article_id,
            sprintf(
                'Auto-assignment (all) complete for Article #%d: %d assigned, %d errors. %s',
                $article_id,
                $assigned,
                count( $errors ),
                implode( '; ', $errors )
            )
        );
    }

    /**
     * Assign reviewers whose specialisation keywords match the article subject.
     * Falls back to assigning ALL verified reviewers if no match found.
     */
    private function assign_by_subject( int $article_id, array $data ): void {
        global $wpdb;

        $subject = strtolower( trim( $data['subject'] ?? '' ) );

        // Get all verified, non-deleted reviewers.
        $all_reviewers = $wpdb->get_results(
            "SELECT id, full_name, specialization FROM {$wpdb->prefix}eiu_reviewers
             WHERE verified = 1 AND is_deleted = 0",
            ARRAY_A
        );

        if ( empty( $all_reviewers ) ) {
            Activity_Log::log( 'auto_assign_skipped', 'article', $article_id,
                'No verified reviewers found.' );
            return;
        }

        // Match on subject word overlap with reviewer specialization.
        $matched = array();
        if ( $subject ) {
            $subject_words = preg_split( '/[\s,\/]+/', $subject, -1, PREG_SPLIT_NO_EMPTY );
            foreach ( $all_reviewers as $rv ) {
                $spec = strtolower( $rv['specialization'] ?? '' );
                foreach ( $subject_words as $word ) {
                    if ( strlen( $word ) > 3 && strpos( $spec, $word ) !== false ) {
                        $matched[] = $rv;
                        break;
                    }
                }
            }
        }

        // If no subject matches, assign to all verified reviewers.
        $to_assign = ! empty( $matched ) ? $matched : $all_reviewers;
        $assigned  = 0;
        $errors    = array();

        foreach ( $to_assign as $rv ) {
            $result = Review::assign( $article_id, (int) $rv['id'] );
            if ( is_wp_error( $result ) ) {
                $errors[] = "Reviewer #{$rv['id']} ({$rv['full_name']}): " . $result->get_error_message();
            } else {
                $assigned++;
            }
        }

        $method = ! empty( $matched ) ? 'subject-match' : 'all-fallback';
        Activity_Log::log(
            'auto_assigned',
            'article',
            $article_id,
            sprintf(
                'Auto-assignment (%s) for Article #%d: %d assigned, %d errors. %s',
                $method,
                $article_id,
                $assigned,
                count( $errors ),
                implode( '; ', $errors )
            )
        );
    }

    /**
     * AJAX: add a reviewer to the default list.
     */
    public function ajax_add(): void {
        // Verify nonce first.
        if ( ! check_ajax_referer( 'eiu_rp_admin', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'eiu-rp' ) ), 403 );
        }

        if ( ! current_user_can( 'eiu_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
            // Log so the admin can debug permission issues.
            error_log( 'EIU RP: eiu_rp_add_default_reviewer denied for user ' . get_current_user_id() );
            wp_send_json_error( array( 'message' => __( 'Permission denied. Please deactivate and reactivate the plugin to refresh capabilities.', 'eiu-rp' ) ), 403 );
        }

        $reviewer_id = absint( $_POST['reviewer_id'] ?? 0 );
        if ( ! $reviewer_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid reviewer ID.', 'eiu-rp' ) ) );
        }

        $reviewer = \EIU_RP\Models\Reviewer::get( $reviewer_id );
        if ( ! $reviewer ) {
            wp_send_json_error( array( 'message' => __( 'Reviewer not found.', 'eiu-rp' ) ) );
        }

        $current = array_filter( array_map( 'absint', (array) get_option( 'eiu_rp_default_reviewers', array() ) ) );
        if ( in_array( $reviewer_id, $current, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Already in the default list.', 'eiu-rp' ) ) );
        }

        $current[] = $reviewer_id;
        update_option( 'eiu_rp_default_reviewers', array_values( $current ) );

        Activity_Log::log( 'default_reviewer_added', 'reviewer', $reviewer_id,
            "Reviewer #{$reviewer_id} ({$reviewer->full_name}) added to auto-assignment list." );

        wp_send_json_success( array(
            'message'      => __( 'Reviewer added to default list.', 'eiu-rp' ),
            'reviewer_id'  => $reviewer_id,
            'full_name'    => $reviewer->full_name,
            'email'        => $reviewer->email,
        ) );
    }

    /**
     * AJAX: remove a reviewer from the default list.
     */
    public function ajax_remove(): void {
        if ( ! check_ajax_referer( 'eiu_rp_admin', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'eiu-rp' ) ), 403 );
        }
        if ( ! current_user_can( 'eiu_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
            error_log( 'EIU RP: eiu_rp_remove_default_reviewer denied for user ' . get_current_user_id() );
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $reviewer_id = absint( $_POST['reviewer_id'] ?? 0 );
        $current = array_filter( array_map( 'absint', (array) get_option( 'eiu_rp_default_reviewers', array() ) ) );
        $updated = array_values( array_diff( $current, array( $reviewer_id ) ) );
        update_option( 'eiu_rp_default_reviewers', $updated );

        Activity_Log::log( 'default_reviewer_removed', 'reviewer', $reviewer_id,
            "Reviewer #{$reviewer_id} removed from auto-assignment list." );

        wp_send_json_success( array( 'message' => __( 'Reviewer removed from default list.', 'eiu-rp' ) ) );
    }

    /**
     * AJAX: save the assignment mode ('all' or 'none').
     */
    public function ajax_save_mode(): void {
        if ( ! check_ajax_referer( 'eiu_rp_admin', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'eiu-rp' ) ), 403 );
        }
        if ( ! current_user_can( 'eiu_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
            error_log( 'EIU RP: eiu_rp_save_assign_mode denied for user ' . get_current_user_id() );
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $mode = in_array( $_POST['mode'] ?? '', array( 'all', 'none', 'subject' ), true )
            ? sanitize_key( $_POST['mode'] )
            : 'all';

        update_option( 'eiu_rp_auto_assign_mode', $mode );

        wp_send_json_success( array( 'message' => __( 'Assignment mode saved.', 'eiu-rp' ) ) );
    }

    /**
     * Get the current default reviewers as full objects.
     *
     * @return array
     */
    public static function get_default_reviewers(): array {
        $ids = array_filter( array_map( 'absint', (array) get_option( 'eiu_rp_default_reviewers', array() ) ) );
        $reviewers = array();
        foreach ( $ids as $id ) {
            $r = \EIU_RP\Models\Reviewer::get( $id );
            if ( $r ) {
                $reviewers[] = $r;
            }
        }
        return $reviewers;
    }
}
