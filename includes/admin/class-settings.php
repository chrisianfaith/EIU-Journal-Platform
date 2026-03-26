<?php
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Security\Security;

class Settings {
    public function __construct() {
        add_action( 'admin_init', array( $this, 'save' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'eiu_manage_settings' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/settings.php' );
    }

    public function save(): void {
        if ( ! isset( $_POST['eiu_rp_settings_save'] ) ) { return; }
        Security::verify_admin_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'eiu_rp_settings' );
        if ( ! current_user_can( 'eiu_manage_settings' ) ) { return; }

        $fields = array(
            'eiu_rp_from_name'                     => 'text',
            'eiu_rp_from_email'                    => 'email',
            'eiu_rp_submission_notification_email' => 'email',
            'eiu_rp_max_file_size_mb'              => 'int',
            'eiu_rp_review_days_due'               => 'int',
            // v1.9 Terminology labels
            'eiu_rp_term_system_name'              => 'text',
            'eiu_rp_term_submit_manuscript'        => 'text',
            'eiu_rp_term_join_as_author'           => 'text',
            'eiu_rp_term_author_portal'            => 'text',
            'eiu_rp_term_explore_link'             => 'text',
            'eiu_rp_term_submission_form_title'    => 'text',
            'eiu_rp_term_my_submissions'           => 'text',
            'eiu_rp_term_my_review'                => 'text',
            // All Terminology keys — auto-save any eiu_rp_term_* POSTed
            'eiu_rp_term_system_name'              => 'text',
            'eiu_rp_term_reviewer_portal'          => 'text',
            'eiu_rp_term_overview'                 => 'text',
            'eiu_rp_term_profile'                  => 'text',
            'eiu_rp_term_sign_out'                 => 'text',
            'eiu_rp_term_assigned_articles'        => 'text',
            'eiu_rp_term_reviewer_directory'       => 'text',
            'eiu_rp_term_all_submissions'          => 'text',
            'eiu_rp_term_in_review'                => 'text',
            'eiu_rp_term_total'                    => 'text',
            'eiu_rp_term_total_assigned'           => 'text',
            'eiu_rp_term_done'                     => 'text',
            'eiu_rp_term_submitted_kpi'            => 'text',
            'eiu_rp_term_completed_kpi'            => 'text',
            'eiu_rp_term_ready_to_share'           => 'text',
            'eiu_rp_term_have_research_to_submit'  => 'text',
            'eiu_rp_term_submit_directly_desc'     => 'text',
            'eiu_rp_term_submit_directly_rv_desc'  => 'text',
            'eiu_rp_term_submission_form_subtitle' => 'text',
            'eiu_rp_term_article_title_label'      => 'text',
            'eiu_rp_term_article_thumbnail_label'  => 'text',
            'eiu_rp_term_abstract_label'           => 'text',
            'eiu_rp_term_references_label'         => 'text',
            'eiu_rp_term_keywords_label'           => 'text',
            'eiu_rp_term_subject_category_label'   => 'text',
            'eiu_rp_term_author_details_label'     => 'text',
            'eiu_rp_term_recent_submissions'       => 'text',
            'eiu_rp_term_no_submissions_yet'       => 'text',
            'eiu_rp_term_no_submissions_found'     => 'text',
            'eiu_rp_term_submit_review'            => 'text',
            'eiu_rp_term_review_decision'          => 'text',
            'eiu_rp_term_accept'                   => 'text',
            'eiu_rp_term_minor_revision'           => 'text',
            'eiu_rp_term_major_revision'           => 'text',
            'eiu_rp_term_reject'                   => 'text',
            'eiu_rp_term_back_to_articles'         => 'text',
            'eiu_rp_term_back_to_submissions'      => 'text',
            'eiu_rp_term_view_and_review'          => 'text',
            'eiu_rp_term_awaiting_your_review'     => 'text',
            'eiu_rp_term_no_articles_assigned'     => 'text',
            'eiu_rp_term_your_review_submitted'    => 'text',
            'eiu_rp_term_comments_for_author'      => 'text',
            'eiu_rp_term_revision_notes_label'     => 'text',
            'eiu_rp_term_update_article_status'    => 'text',
            'eiu_rp_term_set_article_status'       => 'text',
            'eiu_rp_term_update_status_btn'        => 'text',
            'eiu_rp_term_co_reviewer_assignment'   => 'text',
            'eiu_rp_term_assign_co_reviewers_btn'  => 'text',
            'eiu_rp_term_private_notes'            => 'text',
            'eiu_rp_term_save_notify_btn'          => 'text',
            'eiu_rp_term_select_all'               => 'text',
            'eiu_rp_term_clear_all'                => 'text',
            'eiu_rp_term_revise_resubmit'          => 'text',
            'eiu_rp_term_resubmit_article_btn'     => 'text',
            'eiu_rp_term_reviewer_feedback'        => 'text',
            'eiu_rp_term_status_pending'           => 'text',
            'eiu_rp_term_status_under_review'      => 'text',
            'eiu_rp_term_status_approved'          => 'text',
            'eiu_rp_term_status_rejected'          => 'text',
            'eiu_rp_term_status_published'         => 'text',
            'eiu_rp_term_status_revision_required' => 'text',
            'eiu_rp_term_view_all'                 => 'text',
            'eiu_rp_term_cancel'                   => 'text',
            'eiu_rp_term_download_file'            => 'text',
            'eiu_rp_term_upload_photo'             => 'text',
            'eiu_rp_term_verified'                 => 'text',
            'eiu_rp_term_edit_tab'                 => 'text',
            'eiu_rp_term_collaborate_tab'          => 'text',
            'eiu_rp_term_status_tab'               => 'text',
            'eiu_rp_term_replace_article_file'     => 'text',
            'eiu_rp_term_upload_new_thumbnail'     => 'text',
            'eiu_rp_term_current_file'             => 'text',
            'eiu_rp_term_submitted_article_file'   => 'text',
        );

        foreach ( $fields as $key => $type ) {
            $raw = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';
            switch ( $type ) {
                case 'email': update_option( $key, sanitize_email( wp_unslash( $raw ) ) ); break;
                case 'int':   update_option( $key, absint( $raw ) ); break;
                default:      update_option( $key, sanitize_text_field( wp_unslash( $raw ) ) );
            }
        }

        // Subjects textarea.
        if ( isset( $_POST['eiu_rp_subjects'] ) ) {
            $subjects = array_filter( array_map( 'sanitize_text_field', explode( "\n", wp_unslash( $_POST['eiu_rp_subjects'] ) ) ) );
            update_option( 'eiu_rp_subjects', array_values( $subjects ) );
        }

        \EIU_RP\Models\Activity_Log::log( 'settings_updated', 'admin', 0, 'Plugin settings updated.' );

        wp_safe_redirect( add_query_arg( 'settings-updated', '1', wp_get_referer() ) );
        exit;
    }
}
