<?php
/**
 * Terminology Helper — v1.9
 *
 * Single source of truth for every user-visible label in the plugin.
 * Admins change labels via EIU Research → Settings → Terminology & Labels.
 * All strings default to their original shipped value when the option is empty.
 *
 * Usage:  Terminology::get('submit_manuscript')
 *
 * @package EIU_Research_Publication
 * @subpackage Utils
 */
namespace EIU_RP\Utils;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Terminology {

    /**
     * Full map: option_key → default value.
     * Option keys are stored in WP as eiu_rp_term_{key}.
     */
    private static array $defaults = array(

        /* ── Brand / System ──────────────────────────────────────── */
        'system_name'              => 'EIU JOURNAL SYSTEM',

        /* ── Navigation labels ───────────────────────────────────── */
        'submit_manuscript'        => 'Submit Manuscript',
        'join_as_author'           => 'Join as Author',
        'author_portal'            => 'Author Portal',
        'reviewer_portal'          => 'Reviewer Portal',
        'my_submissions'           => 'My Submissions',
        'my_review'                => 'My Review',
        'overview'                 => 'Overview',
        'profile'                  => 'Profile',
        'sign_out'                 => 'Sign Out',
        'assigned_articles'        => 'Assigned Articles',
        'reviewer_directory'       => 'Reviewer Directory',
        'profile_settings'         => 'Profile Settings',

        /* ── Dashboard stats / KPI labels ────────────────────────── */
        'all_submissions'          => 'All Submissions',
        'pending'                  => 'Pending',
        'in_review'                => 'In Review',
        'published'                => 'Published',
        'total'                    => 'Total',
        'total_assigned'           => 'Total Assigned',
        'done'                     => 'Done',
        'submitted_kpi'            => 'Submitted',
        'completed_kpi'            => 'Completed',

        /* ── CTA / Prompt strings ────────────────────────────────── */
        'ready_to_share'           => 'Ready to share your research?',
        'have_research_to_submit'  => 'Have research to submit?',
        'submit_directly_desc'     => 'Submit directly — no redirect, no extra steps.',
        'submit_directly_rv_desc'  => 'Submit directly from your dashboard — no redirect needed.',

        /* ── Submission form ─────────────────────────────────────── */
        'submission_form_title'    => 'Submit Your Manuscript',
        'submission_form_subtitle' => 'All submissions are reviewed by the EIU Editorial Board. Fields marked * are required.',
        'explore_link'             => 'Explore Research to See All Submitted Articles',

        /* ── Article / Manuscript labels ─────────────────────────── */
        'article_title_label'      => 'Article Title',
        'article_thumbnail_label'  => 'Article Thumbnail',
        'abstract_label'           => 'Abstract',
        'references_label'         => 'References',
        'keywords_label'           => 'Keywords',
        'subject_category_label'   => 'Subject / Category',
        'author_details_label'     => 'Author Details',
        'recent_submissions'       => 'Recent Submissions',
        'no_submissions_yet'       => 'No submissions yet.',
        'no_submissions_found'     => 'No submissions found.',

        /* ── Review actions ──────────────────────────────────────── */
        'submit_review'            => 'Submit Review',
        'review_decision'          => 'Review Decision',
        'accept'                   => 'Accept',
        'minor_revision'           => 'Minor Revision',
        'major_revision'           => 'Major Revision',
        'reject'                   => 'Reject',
        'back_to_articles'         => 'Back to Articles',
        'back_to_submissions'      => 'Back to My Submissions',
        'view_and_review'          => 'View & Review',
        'awaiting_your_review'     => 'Awaiting Your Review',
        'no_articles_assigned'     => 'No articles assigned yet.',
        'your_review_submitted'    => 'Your review has been submitted.',
        'comments_for_author'      => 'Comments for the Author',
        'revision_notes_label'     => 'Revision Notes',
        'update_article_status'    => 'Update Article Status',
        'set_article_status'       => 'Set Article Status',
        'update_status_btn'        => 'Update Status',

        /* ── Co-reviewer ─────────────────────────────────────────── */
        'co_reviewer_assignment'   => 'Co-Reviewer Assignment',
        'assign_co_reviewers_btn'  => 'Assign Selected as Co-Reviewers',
        'private_notes'            => 'Private Notes',
        'save_notify_btn'          => 'Save & Notify Co-Reviewers',
        'select_all'               => 'Select All',
        'clear_all'                => 'Clear All',

        /* ── Revise & Resubmit ───────────────────────────────────── */
        'revise_resubmit'          => 'Revise & Resubmit',
        'resubmit_article_btn'     => 'Resubmit Article',
        'reviewer_feedback'        => 'Reviewer Feedback',

        /* ── Status labels ───────────────────────────────────────── */
        'status_pending'           => 'Pending',
        'status_under_review'      => 'Under Review',
        'status_approved'          => 'Approved',
        'status_rejected'          => 'Rejected',
        'status_published'         => 'Published',
        'status_revision_required' => 'Revision Required',

        /* ── General UI ──────────────────────────────────────────── */
        'view_all'                 => 'View All',
        'save_changes'             => 'Save Changes',
        'cancel'                   => 'Cancel',
        'download_file'            => 'Download File',
        'upload_photo'             => 'Upload Photo',
        'verified'                 => 'Verified',

        /* ── Edit panel (reviewer) ───────────────────────────────── */
        'edit_tab'                 => 'Edit',
        'collaborate_tab'          => 'Collaborate',
        'status_tab'               => 'Status',
        'replace_article_file'     => 'Replace Article File',
        'upload_new_thumbnail'     => 'Upload New Thumbnail',
        'current_file'             => 'Current file',
        'submitted_article_file'   => 'Submitted Article File',
    );

    /**
     * Get a terminology string by key.
     * Returns the admin-customised value, or the default if not set.
     *
     * @param string $key  Key from $defaults.
     * @return string
     */
    public static function get( string $key ): string {
        $default = self::$defaults[ $key ] ?? '';
        return (string) get_option( 'eiu_rp_term_' . $key, $default ) ?: $default;
    }

    /**
     * Echo a terminology string (convenience wrapper).
     *
     * @param string $key
     */
    public static function e( string $key ): void {
        echo esc_html( self::get( $key ) );
    }

    /**
     * Return the full defaults map (used by the settings page to render fields).
     */
    public static function defaults(): array {
        return self::$defaults;
    }

    /**
     * Return grouped map for the settings UI.
     * Each group: [ 'label' => string, 'keys' => string[] ]
     */
    public static function groups(): array {
        return array(
            'brand'      => array(
                'label' => 'Brand & System Name',
                'keys'  => [ 'system_name' ],
            ),
            'navigation' => array(
                'label' => 'Navigation & Tab Labels',
                'keys'  => [
                    'submit_manuscript', 'join_as_author', 'author_portal',
                    'reviewer_portal', 'my_submissions', 'my_review', 'overview',
                    'profile', 'sign_out', 'assigned_articles', 'reviewer_directory',
                    'profile_settings',
                ],
            ),
            'dashboard'  => array(
                'label' => 'Dashboard Stats & KPI Labels',
                'keys'  => [
                    'all_submissions', 'pending', 'in_review', 'published', 'total',
                    'total_assigned', 'done', 'submitted_kpi', 'completed_kpi',
                ],
            ),
            'cta'        => array(
                'label' => 'Call-to-Action & Prompt Text',
                'keys'  => [
                    'ready_to_share', 'have_research_to_submit',
                    'submit_directly_desc', 'submit_directly_rv_desc',
                ],
            ),
            'submission' => array(
                'label' => 'Submission Form',
                'keys'  => [
                    'submission_form_title', 'submission_form_subtitle', 'explore_link',
                    'article_title_label', 'article_thumbnail_label', 'abstract_label',
                    'references_label', 'keywords_label', 'subject_category_label',
                    'author_details_label', 'recent_submissions',
                    'no_submissions_yet', 'no_submissions_found',
                ],
            ),
            'review'     => array(
                'label' => 'Review Actions & Labels',
                'keys'  => [
                    'submit_review', 'review_decision', 'accept', 'minor_revision',
                    'major_revision', 'reject', 'back_to_articles', 'back_to_submissions',
                    'view_and_review', 'awaiting_your_review', 'no_articles_assigned',
                    'your_review_submitted', 'comments_for_author', 'revision_notes_label',
                    'update_article_status', 'set_article_status', 'update_status_btn',
                ],
            ),
            'collab'     => array(
                'label' => 'Co-Reviewer & Collaboration',
                'keys'  => [
                    'co_reviewer_assignment', 'assign_co_reviewers_btn',
                    'private_notes', 'save_notify_btn', 'select_all', 'clear_all',
                ],
            ),
            'resubmit'   => array(
                'label' => 'Revise & Resubmit',
                'keys'  => [ 'revise_resubmit', 'resubmit_article_btn', 'reviewer_feedback' ],
            ),
            'statuses'   => array(
                'label' => 'Article Status Labels',
                'keys'  => [
                    'status_pending', 'status_under_review', 'status_approved',
                    'status_rejected', 'status_published', 'status_revision_required',
                ],
            ),
            'general'    => array(
                'label' => 'General UI',
                'keys'  => [
                    'view_all', 'save_changes', 'cancel', 'download_file',
                    'upload_photo', 'verified',
                ],
            ),
            'edit'       => array(
                'label' => 'Edit Panel (Reviewer)',
                'keys'  => [
                    'edit_tab', 'collaborate_tab', 'status_tab',
                    'replace_article_file', 'upload_new_thumbnail',
                    'current_file', 'submitted_article_file',
                ],
            ),
        );
    }
}
