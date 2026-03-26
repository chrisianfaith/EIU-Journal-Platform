<?php
/**
 * Application AJAX Handler.
 *
 * Handles the "Apply as Researcher" OTP + form submission workflow.
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */
namespace EIU_RP\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Security\Security;
use EIU_RP\Models\Activity_Log;
use EIU_RP\Models\Application;

class Application_Handler {

    public function __construct() {
        // OTP for pre-application email verification
        add_action( 'wp_ajax_eiu_rp_apply_send_otp',        array( $this, 'send_otp' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_apply_send_otp', array( $this, 'send_otp' ) );
        add_action( 'wp_ajax_eiu_rp_apply_verify_otp',        array( $this, 'verify_otp' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_apply_verify_otp', array( $this, 'verify_otp' ) );
        // Form submission
        add_action( 'wp_ajax_eiu_rp_submit_application',        array( $this, 'submit' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_submit_application', array( $this, 'submit' ) );
        // Admin actions
        add_action( 'wp_ajax_eiu_rp_application_assign_reviewer',     array( $this, 'assign_reviewer' ) );
        add_action( 'wp_ajax_eiu_rp_application_set_status',          array( $this, 'set_status' ) );
        // v2.0.1: Reviewer-side application status update (from reviewer dashboard).
        add_action( 'wp_ajax_eiu_rp_reviewer_application_set_status', array( $this, 'reviewer_set_status' ) );
        // v2.0.1: Admin delete application + WP user.
        add_action( 'wp_ajax_eiu_rp_delete_application',              array( $this, 'delete_application' ) );
    }

    /* ── OTP: send ─────────────────────────────────────────────────── */
    public function send_otp(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_apply_otp' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Rate-limit OTP sends per EMAIL address, not per IP.
        // Using IP blocks all users on shared networks (university, corporate NAT).
        // Keying on email: each address can request at most 5 OTPs per 10 minutes.
        $rate_email = strtolower( trim( Security::sanitize_email( $_POST['email'] ?? '' ) ) );
        if ( $rate_email && ! Security::check_rate_limit( 'apply_otp_email_' . md5( $rate_email ), 5, 600 ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Too many verification codes sent to this address. Please wait a few minutes before requesting another.', 'eiu-rp' ) ), 429 );
        }

        $email = Security::sanitize_email( $_POST['email'] ?? '' );
        if ( ! $email || ! is_email( $email ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'eiu-rp' ) ), 422 );
        }

        // Block emails that already have a researcher account
        if ( get_user_by( 'email', $email ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please use a different email or log in.', 'eiu-rp' ) ), 409 );
        }

        $otp           = str_pad( (string) random_int( 100000, 999999 ), 6, '0', STR_PAD_LEFT );
        $transient_key = 'eiu_apply_otp_' . md5( strtolower( trim( $email ) ) );
        set_transient( $transient_key, wp_hash( $otp ), 10 * MINUTE_IN_SECONDS );

        $site_name = get_bloginfo( 'name' );
        $subject   = sprintf( __( '[%s] Your Application Verification Code', 'eiu-rp' ), $site_name );
        $body = '<h2 style="color:#1a4988;margin-bottom:8px;">' . esc_html__( 'Author Application — Email Verification', 'eiu-rp' ) . '</h2>'
            . '<p>' . esc_html__( 'To continue your registration, please enter the verification code below.', 'eiu-rp' ) . '</p>'
            . '<div style="font-size:38px;font-weight:900;letter-spacing:.18em;color:#1a4988;background:#eef4ff;border-radius:10px;padding:18px 24px;text-align:center;margin:16px 0;font-family:monospace;">'
            . esc_html( $otp ) . '</div>'
            . '<p style="font-size:13px;color:#6b7280;">' . esc_html__( 'This code expires in 10 minutes. If you did not request this, please ignore this email.', 'eiu-rp' ) . '</p>';

        add_filter( 'wp_mail_content_type', static function() { return 'text/html'; }, 999 );
        $sent = wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        remove_all_filters( 'wp_mail_content_type' );

        Activity_Log::log( 'apply_otp_' . ( $sent ? 'sent' : 'failed' ), 'application', 0, "Application OTP for {$email}." );

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'A verification code has been sent to your email. Please check your inbox and spam folder.', 'eiu-rp' ),
        ) );
    }

    /* ── OTP: verify ────────────────────────────────────────────────── */
    public function verify_otp(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_apply_otp' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Rate-limit OTP verification per EMAIL, not per IP (shared networks fix).
        $rate_v_email = strtolower( trim( Security::sanitize_email( $_POST['email'] ?? '' ) ) );
        if ( $rate_v_email && ! Security::check_rate_limit( 'apply_otp_v_email_' . md5( $rate_v_email ), 10, 600 ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Too many verification attempts for this address. Please request a new code.', 'eiu-rp' ) ), 429 );
        }

        $email = Security::sanitize_email( $_POST['email'] ?? '' );
        $code  = preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['otp'] ?? '' ) );

        if ( ! $email || strlen( $code ) !== 6 ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Please enter the 6-digit code.', 'eiu-rp' ) ), 422 );
        }

        $transient_key = 'eiu_apply_otp_' . md5( strtolower( trim( $email ) ) );
        $stored        = get_transient( $transient_key );
        if ( ! $stored || ! hash_equals( (string) $stored, wp_hash( $code ) ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid or expired code. Please request a new one.', 'eiu-rp' ) ) );
        }

        delete_transient( $transient_key );
        $token     = wp_generate_password( 32, false );
        $tok_key   = 'eiu_apply_tok_' . md5( strtolower( trim( $email ) ) );
        set_transient( $tok_key, wp_hash( $token ), 30 * MINUTE_IN_SECONDS );

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'Email verified. Please complete the application form.', 'eiu-rp' ),
            'token'   => $token,
        ) );
    }

    /* ── Form submission ─────────────────────────────────────────────── */
    public function submit(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_apply_form' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Validate OTP token
        $email = Security::sanitize_email( $_POST['email'] ?? '' );
        $token = sanitize_text_field( wp_unslash( $_POST['apply_token'] ?? '' ) );
        $tok_key = 'eiu_apply_tok_' . md5( strtolower( trim( $email ) ) );
        $stored  = get_transient( $tok_key );
        if ( ! $email || ! $token || ! $stored || ! hash_equals( (string) $stored, wp_hash( $token ) ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Email verification expired. Please restart the application process.', 'eiu-rp' ) ), 403 );
        }

        if ( ! Security::check_rate_limit( 'apply_submit_' . Security::get_ip(), 2, HOUR_IN_SECONDS ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Too many submissions. Please try again later.', 'eiu-rp' ) ), 429 );
        }

        // Sanitize fields
        $data = array(
            'full_name'      => Security::sanitize_text( $_POST['full_name']      ?? '' ),
            'title'          => Security::sanitize_text( $_POST['title']          ?? '' ),
            'designation'    => Security::sanitize_text( $_POST['designation']    ?? '' ),
            'country'        => Security::sanitize_text( $_POST['country']        ?? '' ),
            'academic_bg'    => Security::sanitize_textarea( $_POST['academic_bg']    ?? '' ),
            'gender'         => in_array( $_POST['gender'] ?? '', array('male','female','other'), true ) ? sanitize_key( $_POST['gender'] ) : '',
            'date_of_birth'  => sanitize_text_field( wp_unslash( $_POST['date_of_birth'] ?? '' ) ),
            'student_number' => Security::sanitize_text( $_POST['student_number'] ?? '' ),
            'email'          => $email,
            'expertise'      => Security::sanitize_text( $_POST['expertise']      ?? '' ),
            'about'          => Security::sanitize_textarea( $_POST['about']      ?? '' ),
        );

        $required = array( 'full_name', 'email', 'expertise', 'about', 'academic_bg', 'country' );
        $missing  = Security::validate_required( $data, $required );
        if ( ! empty( $missing ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Please complete all required fields.', 'eiu-rp' ), 'fields' => $missing ), 422 );
        }

        // Declaration checkbox
        if ( empty( $_POST['declaration'] ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Please accept the declaration to submit your application.', 'eiu-rp' ) ), 422 );
        }

        // File uploads
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $allowed_doc_mimes = array(
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );

        $upload_doc = static function( string $field ) use ( $allowed_doc_mimes ): array {
            if ( empty( $_FILES[ $field ]['tmp_name'] ) || $_FILES[ $field ]['error'] !== UPLOAD_ERR_OK ) {
                return array();
            }
            if ( $_FILES[ $field ]['size'] > 10 * MB_IN_BYTES ) {
                return array( 'error' => __( 'File must be under 10 MB.', 'eiu-rp' ) );
            }
            $upload = wp_handle_upload( $_FILES[ $field ], array( 'test_form' => false, 'mimes' => $allowed_doc_mimes ) );
            if ( isset( $upload['error'] ) ) {
                return array( 'error' => $upload['error'] );
            }
            return array( 'path' => $upload['file'], 'name' => basename( $upload['file'] ) );
        };

        $cv_upload       = $upload_doc( 'cv_file' );
        $research_upload = $upload_doc( 'research_file' );

        if ( isset( $cv_upload['error'] ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => 'CV: ' . $cv_upload['error'] ) );
        }
        if ( isset( $research_upload['error'] ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => 'Research Work: ' . $research_upload['error'] ) );
        }

        $data['cv_file_path']        = $cv_upload['path'] ?? '';
        $data['cv_file_name']        = $cv_upload['name'] ?? '';
        $data['research_file_path']  = $research_upload['path'] ?? '';
        $data['research_file_name']  = $research_upload['name'] ?? '';

        $application_id = Application::create( $data );
        if ( is_wp_error( $application_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $application_id->get_error_message() ) );
        }

        // Consume the verification token
        delete_transient( $tok_key );

        do_action( 'eiu_rp_application_submitted', $application_id, $data );
        Activity_Log::log( 'application_submitted', 'application', $application_id, "New researcher application from {$email}." );

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'Your application has been submitted successfully! Our team will review it and contact you at the email address provided.', 'eiu-rp' ),
        ) );
    }

    /* ── Admin: assign reviewer to application ───────────────────────── */
    public function assign_reviewer(): void {
        Security::verify_admin_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'eiu_rp_admin' );
        if ( ! current_user_can( 'eiu_manage_reviewers' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $app_id      = Security::sanitize_int( $_POST['application_id'] ?? 0 );
        $reviewer_id = Security::sanitize_int( $_POST['reviewer_id']    ?? 0 );

        if ( ! $app_id || ! $reviewer_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid application or reviewer.', 'eiu-rp' ) ) );
        }

        $updated = Application::update_status( $app_id, Application::STATUS_REVIEWING, '', $reviewer_id );
        if ( ! $updated ) {
            wp_send_json_error( array( 'message' => __( 'Could not assign reviewer.', 'eiu-rp' ) ) );
        }

        do_action( 'eiu_rp_application_reviewer_assigned', $app_id, $reviewer_id );
        Activity_Log::log( 'application_reviewer_assigned', 'application', $app_id, "Reviewer #{$reviewer_id} assigned to application #{$app_id}." );
        wp_send_json_success( array( 'message' => __( 'Reviewer assigned. Status set to Under Review.', 'eiu-rp' ) ) );
    }

    /* ── Admin: set application status ────────────────────────────────── */
    public function set_status(): void {
        Security::verify_admin_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'eiu_rp_admin' );
        if ( ! current_user_can( 'eiu_manage_articles' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $valid_statuses = array(
            Application::STATUS_PENDING,
            Application::STATUS_REVIEWING,
            Application::STATUS_APPROVED,
            Application::STATUS_REJECTED,
            Application::STATUS_MORE_INFO,
        );

        $app_id      = Security::sanitize_int( $_POST['application_id'] ?? 0 );
        $status      = sanitize_key( $_POST['status'] ?? '' );
        $admin_notes = Security::sanitize_textarea( $_POST['admin_notes'] ?? '' );

        if ( ! $app_id || ! in_array( $status, $valid_statuses, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'eiu-rp' ) ) );
        }

        $app = Application::get( $app_id );
        if ( ! $app ) {
            wp_send_json_error( array( 'message' => __( 'Application not found.', 'eiu-rp' ) ) );
        }

        Application::update_status( $app_id, $status, $admin_notes );

        // If approved: create WP user + send credentials
        if ( $status === Application::STATUS_APPROVED ) {
            $result = $this->provision_researcher( $app, $admin_notes );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
        }

        do_action( 'eiu_rp_application_status_changed', $app_id, $status, $admin_notes );
        Activity_Log::log( 'application_status_changed', 'application', $app_id, "Application #{$app_id} status → {$status}." );

        wp_send_json_success( array(
            'message'      => __( 'Status updated.', 'eiu-rp' ),
            'status_label' => Application::status_label( $status ),
        ) );
    }

    /* ── Provision: create researcher WP account ─────────────────────── */
    private function provision_researcher( object $app, string $admin_notes ): bool|\WP_Error {
        // If a WP account already exists for this email, treat it as already provisioned.
        // Do not return an error — simply update the role if needed and continue.
        $existing_user = get_user_by( 'email', $app->email );
        if ( $existing_user ) {
            // Ensure the user has the researcher role.
            if ( ! in_array( 'eiu_researcher', (array) $existing_user->roles, true ) ) {
                $existing_user->set_role( 'eiu_researcher' );
            }
            // Fire the approved hook so the credentials email is sent (uses existing account).
            do_action( 'eiu_rp_researcher_approved', $app, $existing_user->user_login, '', $existing_user->ID );
            Activity_Log::log( 'researcher_account_linked', 'user', $existing_user->ID,
                "Existing WP account linked to approved application #{$app->id} ({$app->email})." );
            return true;
        }

        $username = sanitize_user( strtolower( explode( '@', $app->email )[0] ) . '_' . wp_generate_password( 4, false ) );
        $password = wp_generate_password( 12, true );
        $user_id  = wp_create_user( $username, $password, $app->email );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $app->full_name,
            'first_name'   => $app->full_name,
            'role'         => 'eiu_researcher',
        ) );

        // Store extra meta
        update_user_meta( $user_id, 'eiu_expertise',     $app->expertise );
        update_user_meta( $user_id, 'eiu_country',       $app->country );
        update_user_meta( $user_id, 'eiu_application_id', $app->id );

        // Send credentials email
        do_action( 'eiu_rp_researcher_approved', $app, $username, $password, $user_id );

        Activity_Log::log( 'researcher_account_created', 'user', $user_id, "Researcher account created for application #{$app->id} ({$app->email})." );
        return true;
    }
    /* ── Reviewer: set application status (from reviewer dashboard) ──── */
    /**
     * v2.0.1: Reviewer may set status to approved, rejected, or more_info_required
     * only for applications assigned to them.
     * Uses frontend nonce (not admin nonce) so it works in the frontend dashboard.
     */
    public function reviewer_set_status(): void {
        /* ── Validate request (before any output buffer is opened) ── */
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_frontend'
        ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        if ( ! current_user_can( 'eiu_review_articles' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $app_id      = Security::sanitize_int( $_POST['application_id'] ?? 0 );
        $status      = sanitize_key( $_POST['status'] ?? '' );
        $admin_notes = Security::sanitize_textarea( $_POST['admin_notes'] ?? '' );

        $allowed_statuses = array(
            Application::STATUS_APPROVED,
            Application::STATUS_REJECTED,
            Application::STATUS_MORE_INFO,
        );

        if ( ! $app_id || ! in_array( $status, $allowed_statuses, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'eiu-rp' ) ) );
        }

        global $wpdb;
        $rv_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eiu_reviewers
             WHERE user_id = %d AND is_deleted = 0",
            get_current_user_id()
        ) );

        if ( empty( $rv_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Reviewer profile not found.', 'eiu-rp' ) ), 403 );
        }

        $rv_ids = array_map( 'intval', $rv_ids );
        $app    = Application::get( $app_id );
        if ( ! $app || ! in_array( (int) $app->assigned_reviewer_id, $rv_ids, true ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not assigned to this application.', 'eiu-rp' ) ), 403 );
        }

        $reviewer_row_id = $rv_ids[0];

        /* ── All validation passed — now do the work ── */

        Application::update_status( $app_id, $status, $admin_notes );

        $provision_error = null;
        if ( $status === Application::STATUS_APPROVED ) {
            // Suppress WP core new-user notification emails — we send our own.
            // wp_create_user fires user_register → wp_send_new_user_notifications.
            add_filter( 'wp_send_new_user_notification_to_admin', '__return_false', 999 );
            add_filter( 'wp_send_new_user_notification_to_user',  '__return_false', 999 );

            // Capture ALL output from provisioning (wp_mail debug, SMTP notices, etc.)
            ob_start();
            $result = $this->provision_researcher( $app, $admin_notes );
            ob_end_clean(); // discard any stray output — JSON must be pristine

            // Restore filters
            remove_filter( 'wp_send_new_user_notification_to_admin', '__return_false', 999 );
            remove_filter( 'wp_send_new_user_notification_to_user',  '__return_false', 999 );

            if ( is_wp_error( $result ) ) {
                $provision_error = $result->get_error_message();
                wp_send_json_error( array( 'message' => $provision_error ) );
            }
        }

        // Fire status-change hook (sends rejection/more-info emails via Mailer).
        // Wrap in its own isolated buffer so any SMTP debug output is discarded.
        ob_start();
        do_action( 'eiu_rp_application_status_changed', $app_id, $status, $admin_notes );
        ob_end_clean();

        Activity_Log::log(
            'application_status_changed_by_reviewer',
            'application',
            $app_id,
            "Reviewer #{$reviewer_row_id} set application #{$app_id} status → {$status}."
        );

        wp_send_json_success( array(
            'message'      => __( 'Status updated.', 'eiu-rp' ),
            'status_label' => Application::status_label( $status ),
        ) );
    }

    /* ── Admin: delete application + optional WP user ───────────────── */
    /**
     * v2.0.1: Permanently delete an application row and its uploaded files.
     * Optionally also deletes the provisioned WP user account.
     * Admin-only: requires eiu_manage_articles or manage_options.
     */
    public function delete_application(): void {
        ob_start();
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_articles' ) && ! current_user_can( 'manage_options' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $app_id      = Security::sanitize_int( $_POST['application_id'] ?? 0 );
        $delete_user = ! empty( $_POST['delete_user'] ) && $_POST['delete_user'] === '1';

        if ( ! $app_id ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid application ID.', 'eiu-rp' ) ) );
        }

        $app = Application::get( $app_id );
        if ( ! $app ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Application not found.', 'eiu-rp' ) ) );
        }

        // Optionally delete the provisioned WP user.
        $user_deleted = false;
        if ( $delete_user ) {
            $wp_user = get_user_by( 'email', $app->email );
            if ( $wp_user ) {
                // Prevent deleting administrators.
                if ( ! user_can( $wp_user->ID, 'manage_options' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    wp_delete_user( $wp_user->ID );
                    $user_deleted = true;
                }
            }
        }

        // Delete uploaded files.
        if ( ! empty( $app->cv_file_path ) && file_exists( $app->cv_file_path ) ) {
            wp_delete_file( $app->cv_file_path );
        }
        if ( ! empty( $app->research_file_path ) && file_exists( $app->research_file_path ) ) {
            wp_delete_file( $app->research_file_path );
        }

        // Delete the application row.
        global $wpdb;
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'eiu_researcher_applications',
            array( 'id' => $app_id ),
            array( '%d' )
        );

        if ( $deleted === false ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Could not delete application.', 'eiu-rp' ) ) );
        }

        Activity_Log::log(
            'application_deleted',
            'application',
            $app_id,
            sprintf(
                'Application #%d (%s) deleted by admin. User deleted: %s.',
                $app_id,
                $app->email,
                $user_deleted ? 'yes' : 'no'
            )
        );

        ob_end_clean();
        wp_send_json_success( array(
            'message'      => __( 'Application deleted successfully.', 'eiu-rp' ),
            'user_deleted' => $user_deleted,
        ) );
    }

}
