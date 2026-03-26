<?php
/**
 * Admin: Email Template Editor.
 *
 * Allows admins to edit the subject lines and body content
 * for all EIU Research Publication email notifications.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Security\Security;

class Email_Template_Editor {

    /** Option key prefix for custom templates. */
    const OPT_PREFIX = 'eiu_rp_email_tpl_';

    public function __construct() {
        add_action( 'admin_init', array( $this, 'save' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'eiu_manage_settings' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/email-template-editor.php' );
    }

    /**
     * Save template overrides.
     */
    public function save(): void {
        if ( ! isset( $_POST['eiu_rp_email_tpl_save'] ) ) { return; }
        Security::verify_admin_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'eiu_rp_email_tpl' );
        if ( ! current_user_can( 'eiu_manage_settings' ) ) { return; }

        $types = self::get_template_types();
        foreach ( $types as $key => $label ) {
            $subject = isset( $_POST[ "subject_{$key}" ] )
                ? sanitize_text_field( wp_unslash( $_POST[ "subject_{$key}" ] ) )
                : '';
            $body = isset( $_POST[ "body_{$key}" ] )
                ? wp_kses_post( wp_unslash( $_POST[ "body_{$key}" ] ) )
                : '';
            update_option( self::OPT_PREFIX . "subject_{$key}", $subject );
            update_option( self::OPT_PREFIX . "body_{$key}",    $body );
        }

        \EIU_RP\Models\Activity_Log::log( 'email_templates_saved', 'admin', 0, 'Email templates updated.' );
        wp_safe_redirect( add_query_arg( 'tpl-saved', '1', wp_get_referer() ) );
        exit;
    }

    /**
     * Get a custom template body. Returns empty string if not customized.
     *
     * @param string $type
     * @return string
     */
    public static function get_custom_body( string $type ): string {
        return (string) get_option( self::OPT_PREFIX . "body_{$type}", '' );
    }

    /**
     * Get a custom subject. Returns empty string if not customized.
     */
    public static function get_custom_subject( string $type ): string {
        return (string) get_option( self::OPT_PREFIX . "subject_{$type}", '' );
    }

    /**
     * All editable template types.
     */
    public static function get_template_types(): array {
        return array(
            // ── Author-facing ─────────────────────────────────────────
            'article_received'        => __( '1. Article Received (Author Confirmation)', 'eiu-rp' ),
            'status_changed'          => __( '2. Article Status Changed (Author)', 'eiu-rp' ),
            'article_accepted'        => __( '3. Article Accepted', 'eiu-rp' ),
            'revision_required'       => __( '4. Revision Required (Author)', 'eiu-rp' ),
            'article_rejected'        => __( '5. Article Rejected (Author)', 'eiu-rp' ),
            // ── Reviewer-facing ───────────────────────────────────────
            'reviewer_otp'            => __( '6. Reviewer OTP Login Code', 'eiu-rp' ),
            'reviewer_assigned'       => __( '7. Reviewer Assigned to Article', 'eiu-rp' ),
            'reviewer_notice'         => __( '8. New Submission Notice (All Reviewers)', 'eiu-rp' ),
            // ── Admin-facing ──────────────────────────────────────────
            'article_submitted'       => __( '9. New Article Submitted (Admin Alert)', 'eiu-rp' ),
            'review_submitted'        => __( '10. Review Submitted (Admin Alert)', 'eiu-rp' ),
            // ── Collaboration ──────────────────────────────────────────
            'co_reviewer_assigned'    => __( '11. Co-Reviewer Assigned Notification', 'eiu-rp' ),
            'reviewer_notes_shared'   => __( '12. Private Notes Shared with Co-Reviewers', 'eiu-rp' ),
            // ── Application-facing ─────────────────────────────────────
            'application_received'    => __( '13. Application Received (Applicant Confirmation)', 'eiu-rp' ),
            'application_approved'    => __( '14. Application Approved — Author Credentials', 'eiu-rp' ),
            'application_rejected'    => __( '15. Application Rejected', 'eiu-rp' ),
            'application_more_info'   => __( '16. Application — More Information Required', 'eiu-rp' ),
            // ── Admin alerts ──────────────────────────────────────────
            'application_submitted'        => __( '17. New Application Submitted (Admin Alert)', 'eiu-rp' ),
            'application_reviewer_assigned'=> __( '18. Application Assigned to Reviewer (Reviewer Notification)', 'eiu-rp' ),
        );
    }

    /**
     * Get default placeholder text for the body field.
     */
    public static function get_default_hint( string $type ): string {
        $hints = array(
            'article_received'   => __( 'Variables: {article_title}, {author_name}, {article_id}, {submission_date}, {site_name}', 'eiu-rp' ),
            'status_changed'     => __( 'Variables: {article_title}, {author_name}, {status}, {site_name}', 'eiu-rp' ),
            'article_accepted'   => __( 'Variables: {article_title}, {author_name}, {article_id}, {site_name}', 'eiu-rp' ),
            'revision_required'  => __( 'Variables: {article_title}, {author_name}, {revision_notes}, {login_url}, {site_name}', 'eiu-rp' ),
            'article_rejected'   => __( 'Variables: {article_title}, {author_name}, {site_name}', 'eiu-rp' ),
            'reviewer_otp'       => __( 'Variables: {reviewer_name}, {otp_code}, {site_name}', 'eiu-rp' ),
            'reviewer_assigned'  => __( 'Variables: {article_title}, {reviewer_name}, {login_url}, {site_name}', 'eiu-rp' ),
            'reviewer_notice'    => __( 'Variables: {article_title}, {author_name}, {subject}, {reviewer_name}, {login_url}', 'eiu-rp' ),
            'article_submitted'  => __( 'Variables: {article_title}, {author_name}, {author_email}, {subject}, {article_id}, {admin_url}, {site_name}', 'eiu-rp' ),
            'review_submitted'        => __( 'Variables: {article_title}, {reviewer_name}, {recommendation}, {admin_url}, {site_name}', 'eiu-rp' ),
            'co_reviewer_assigned'    => __( 'Variables: {article_title}, {co_reviewer_name}, {lead_reviewer_name}, {login_url}, {site_name}', 'eiu-rp' ),
            'reviewer_notes_shared'   => __( 'Variables: {article_title}, {co_reviewer_name}, {notes_content}, {login_url}, {site_name}', 'eiu-rp' ),
            'application_received'    => __( 'Variables: {full_name}, {app_id}, {site_name}', 'eiu-rp' ),
            'application_approved'    => __( 'Variables: {full_name}, {username}, {password}, {login_url}, {site_name}', 'eiu-rp' ),
            'application_rejected'    => __( 'Variables: {full_name}, {admin_notes}, {site_name}', 'eiu-rp' ),
            'application_more_info'   => __( 'Variables: {full_name}, {admin_notes}, {apply_url}, {site_name}', 'eiu-rp' ),
            'application_submitted'        => __( 'Variables: {full_name}, {email}, {app_id}, {admin_url}, {site_name}', 'eiu-rp' ),
            'application_reviewer_assigned'=> __( 'Variables: {full_name}, {applicant_name}, {app_id}, {expertise}, {review_url}, {site_name}', 'eiu-rp' ),
        );
        return $hints[ $type ] ?? '';
    }

    /**
     * Get the default subject line for a template type.
     * Used when the admin has not saved a custom subject.
     */
    public static function get_default_subject( string $type ): string {
        $site = get_bloginfo( 'name' );
        $subjects = array(
            'article_received'   => sprintf( __( '[%s] Your Article Has Been Received', 'eiu-rp' ), $site ),
            'status_changed'     => sprintf( __( '[%s] Article Status Update', 'eiu-rp' ), $site ),
            'article_accepted'   => sprintf( __( '[%s] Congratulations — Your Article Has Been Accepted', 'eiu-rp' ), $site ),
            'revision_required'  => sprintf( __( '[%s] Revision Required for Your Article', 'eiu-rp' ), $site ),
            'article_rejected'   => sprintf( __( '[%s] Update on Your Article Submission', 'eiu-rp' ), $site ),
            'reviewer_otp'       => sprintf( __( '[%s] Your Reviewer Login Code', 'eiu-rp' ), $site ),
            'reviewer_assigned'  => sprintf( __( '[%s] New Article Assigned for Review', 'eiu-rp' ), $site ),
            'reviewer_notice'    => sprintf( __( '[%s] New Submission Available for Review', 'eiu-rp' ), $site ),
            'article_submitted'  => sprintf( __( '[%s] New Article Submission Received', 'eiu-rp' ), $site ),
            'review_submitted'        => sprintf( __( '[%s] Review Submitted', 'eiu-rp' ), $site ),
            'co_reviewer_assigned'    => sprintf( __( '[%s] You Have Been Assigned as Co-Reviewer', 'eiu-rp' ), $site ),
            'reviewer_notes_shared'   => sprintf( __( '[%s] New Private Notes on Article Review', 'eiu-rp' ), $site ),
            'application_received'    => sprintf( __( '[%s] We Received Your Application', 'eiu-rp' ), $site ),
            'application_approved'    => sprintf( __( '[%s] Congratulations — Your Application Has Been Approved', 'eiu-rp' ), $site ),
            'application_rejected'    => sprintf( __( '[%s] Update on Your Author Application', 'eiu-rp' ), $site ),
            'application_more_info'   => sprintf( __( '[%s] More Information Needed for Your Application', 'eiu-rp' ), $site ),
            'application_submitted'   => sprintf( __( '[%s] New Author Application Received', 'eiu-rp' ), $site ),
            'application_reviewer_assigned'=> sprintf( __( '[%s] Application Assigned for Your Review', 'eiu-rp' ), $site ),
        );
        return $subjects[ $type ] ?? sprintf( '[%s] Notification', $site );
    }
}
