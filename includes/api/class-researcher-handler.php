<?php
/**
 * Researcher AJAX Handler.
 *
 * Handles:
 *  - Researcher profile update (AJAX)
 *  - Researcher registration (AJAX)
 *  - Password change (AJAX)
 *  - Read count increment (AJAX + inline init)
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */
namespace EIU_RP\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Security\Security;
use EIU_RP\Models\Activity_Log;

class Researcher_Handler {

    public function __construct() {
        add_action( 'wp_ajax_eiu_rp_update_researcher_profile', array( $this, 'update_profile' ) );
        add_action( 'wp_ajax_eiu_rp_researcher_register',       array( $this, 'register' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_researcher_register', array( $this, 'register' ) );
        add_action( 'wp_ajax_eiu_rp_researcher_login',           array( $this, 'login' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_researcher_login',    array( $this, 'login' ) );
        // v1.9: Unified login — handles both researcher + reviewer.
        add_action( 'wp_ajax_eiu_rp_unified_login',              array( $this, 'unified_login' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_unified_login',       array( $this, 'unified_login' ) );
        // v2.0: Real-time status polling endpoint.
        add_action( 'wp_ajax_eiu_rp_get_article_statuses',          array( $this, 'get_article_statuses' ) );
        // v2.0: Reviewer OTP — send and verify.
        add_action( 'wp_ajax_eiu_rp_send_reviewer_otp',          array( $this, 'send_reviewer_otp' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_send_reviewer_otp',   array( $this, 'send_reviewer_otp' ) );
        add_action( 'wp_ajax_eiu_rp_verify_reviewer_otp',        array( $this, 'verify_reviewer_otp' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_verify_reviewer_otp', array( $this, 'verify_reviewer_otp' ) );
        add_action( 'wp_ajax_eiu_rp_upload_profile_photo', array( $this, 'upload_profile_photo' ) );
        add_action( 'wp_ajax_eiu_rp_track_read',                 array( $this, 'track_read' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_track_read',          array( $this, 'track_read' ) );
        // v1.9: Fresh nonce endpoint — bypasses page caching so nonces are always valid.
        add_action( 'wp_ajax_eiu_rp_get_nonce',        array( $this, 'get_nonce' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_get_nonce', array( $this, 'get_nonce' ) );
        add_action( 'wp_ajax_eiu_rp_save_draft_submission',      array( $this, 'save_draft' ) );
        add_action( 'wp_ajax_eiu_rp_load_draft_submission',      array( $this, 'load_draft' ) );
    }

    /**
     * Update logged-in researcher's WP profile + meta.
     */
    public function update_profile(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'eiu-rp' ) ), 401 );
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_researcher_profile' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        $user_id      = get_current_user_id();
        $display_name = Security::sanitize_text( $_POST['display_name'] ?? '' );
        $first_name   = Security::sanitize_text( $_POST['first_name']   ?? '' );
        $last_name    = Security::sanitize_text( $_POST['last_name']    ?? '' );
        $phone        = Security::sanitize_text( $_POST['phone']        ?? '' );
        $country      = Security::sanitize_text( $_POST['country']      ?? '' );
        $nationality  = Security::sanitize_text( $_POST['nationality']  ?? '' );
        $expertise    = Security::sanitize_text( $_POST['expertise']    ?? '' );
        $new_pass     = wp_unslash( $_POST['new_password'] ?? '' );
        $confirm_pass = wp_unslash( $_POST['confirm_password'] ?? '' );

        // Basic validation
        if ( ! $display_name ) {
            wp_send_json_error( array( 'message' => __( 'Full name is required.', 'eiu-rp' ) ) );
        }

        // Update core WP user fields
        $user_data = array(
            'ID'           => $user_id,
            'display_name' => $display_name,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
        );

        // Password update (optional)
        if ( $new_pass !== '' ) {
            if ( strlen( $new_pass ) < 8 ) {
                wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'eiu-rp' ) ) );
            }
            if ( $new_pass !== $confirm_pass ) {
                wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'eiu-rp' ) ) );
            }
            $user_data['user_pass'] = $new_pass;
        }

        $result = wp_update_user( $user_data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Store extra meta
        update_user_meta( $user_id, 'eiu_phone',       $phone );
        update_user_meta( $user_id, 'eiu_country',     $country );
        update_user_meta( $user_id, 'eiu_nationality', $nationality );
        update_user_meta( $user_id, 'eiu_expertise',   $expertise );

        Activity_Log::log( 'researcher_profile_updated', 'user', $user_id, "Researcher #{$user_id} updated profile." );

        wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'eiu-rp' ) ) );
    }

    /**
     * Frontend researcher self-registration.
     * Disabled by default — only admins can create accounts (v2.0).
     * Set option 'eiu_rp_allow_self_registration' to '1' to re-enable.
     */
    public function register(): void {
        // Self-registration is disabled. Only admins create accounts.
        if ( ! get_option( 'eiu_rp_allow_self_registration', '0' ) ) {
            wp_send_json_error( array( 'message' => __( 'Account registration is currently closed. Please contact the administrator.', 'eiu-rp' ) ), 403 );
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_researcher_register' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Rate limit: 3 registrations per IP per hour
        if ( ! Security::check_rate_limit( 'reg_' . Security::get_ip(), 3 ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many attempts. Please try again later.', 'eiu-rp' ) ), 429 );
        }

        $email      = Security::sanitize_email( $_POST['email']    ?? '' );
        $first_name = Security::sanitize_text(  $_POST['first_name'] ?? '' );
        $last_name  = Security::sanitize_text(  $_POST['last_name']  ?? '' );
        $password   = wp_unslash( $_POST['password'] ?? '' );

        if ( ! $email || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Valid email address required.', 'eiu-rp' ) ) );
        }
        if ( ! $first_name ) {
            wp_send_json_error( array( 'message' => __( 'First name required.', 'eiu-rp' ) ) );
        }
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'eiu-rp' ) ) );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'An account with this email already exists.', 'eiu-rp' ) ) );
        }

        $username = sanitize_user( strtolower( $first_name . '.' . $last_name . '.' . wp_generate_password( 4, false ) ), true );

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim( $first_name . ' ' . $last_name ),
            'role'         => 'eiu_researcher',
        ) );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
        }

        Activity_Log::log( 'researcher_registered', 'user', $user_id, "New researcher registered: {$email}" );

        wp_send_json_success( array( 'message' => __( 'Account created! You can now log in.', 'eiu-rp' ) ) );
    }

    /**
     * Frontend researcher login.
     */
    public function login(): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_researcher_login' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        if ( ! Security::check_rate_limit( 'login_' . Security::get_ip(), 10 ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many login attempts.', 'eiu-rp' ) ), 429 );
        }

        $email    = Security::sanitize_email( $_POST['email'] ?? '' );
        $password = wp_unslash( $_POST['password'] ?? '' );
        $remember = ! empty( $_POST['remember'] );

        $user = wp_signon( array(
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => $remember,
        ), is_ssl() );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( array( 'message' => __( 'Incorrect email or password.', 'eiu-rp' ) ) );
        }

        // Get the researcher dashboard URL
        $dashboard_url = get_option( 'eiu_rp_researcher_dashboard_page_id' )
            ? get_permalink( get_option( 'eiu_rp_researcher_dashboard_page_id' ) )
            : home_url( '/researcher-dashboard/' );

        wp_send_json_success( array(
            'message'  => __( 'Logged in! Redirecting…', 'eiu-rp' ),
            'redirect' => $dashboard_url,
        ) );
    }

    /**
     * Track article read count (fires when article-detail template loads).
     */
    public function track_read(): void {
        $post_id = absint( $_POST['post_id'] ?? 0 );
        $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! $post_id || ! wp_verify_nonce( $nonce, 'eiu_rp_track_read_' . $post_id ) ) {
            wp_send_json_error( array(), 400 );
        }
        // Increment post meta read count
        $current = (int) get_post_meta( $post_id, '_eiu_read_count', true );
        update_post_meta( $post_id, '_eiu_read_count', $current + 1 );
        wp_send_json_success( array( 'count' => $current + 1 ) );
    }

    /**
     * Save submission draft to user meta (Save & Continue Later).
     */
    public function save_draft(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in to save a draft.', 'eiu-rp' ) ) );
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }
        $draft = wp_unslash( $_POST['draft_data'] ?? '' );
        $draft = wp_kses_post( $draft ); // Sanitize JSON string
        update_user_meta( get_current_user_id(), '_eiu_submission_draft', $draft );
        wp_send_json_success( array( 'message' => __( 'Draft saved.', 'eiu-rp' ) ) );
    }

    /**
     * Load submission draft from user meta.
     */
    public function load_draft(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Please log in to load a draft.', 'eiu-rp' ) ) );
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }
        $draft = get_user_meta( get_current_user_id(), '_eiu_submission_draft', true );
        if ( ! $draft ) {
            wp_send_json_error( array( 'message' => __( 'No draft found.', 'eiu-rp' ) ) );
        }
        wp_send_json_success( array( 'draft_data' => $draft ) );
    }
    /**
     * v1.9: Unified login handler — authenticates any plugin role.
     *
     * Accepts a role_hint ('researcher' | 'reviewer') from the UI tab,
     * but after wp_signon succeeds it reads the ACTUAL WP role from the
     * user object — the hint is only used to personalise the error message.
     * This way a user cannot bypass role checks by manipulating the field.
     *
     * Redirects:
     *   eiu_reviewer   → eiu_rp_reviewer_access_page_id  (or /reviewer-dashboard/)
     *   eiu_researcher → eiu_rp_researcher_dashboard_page_id (or /researcher-dashboard/)
     *   administrator  → wp-admin
     *
     * Security:
     *   - nonce: eiu_unified_login (per-session, per-action)
     *   - rate limit: 10 attempts per IP per hour
     *   - wp_signon uses WordPress core auth
     *   - role verified AFTER authentication, not before
     */
    public function unified_login(): void {
        ob_start();

        // Nonce check
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_unified_login' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Rate limiting — 10 attempts per IP per hour
        if ( ! Security::check_rate_limit( 'unified_login_' . Security::get_ip(), 10 ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Too many login attempts. Please wait before trying again.', 'eiu-rp' ),
            ), 429 );
        }

        $email     = Security::sanitize_email( $_POST['email'] ?? '' );
        $password  = wp_unslash( $_POST['password'] ?? '' );
        $remember  = ! empty( $_POST['remember'] );
        $role_hint = sanitize_key( $_POST['role_hint'] ?? 'researcher' );

        if ( ! $email || ! $password ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Email and password are required.', 'eiu-rp' ) ), 422 );
        }

        // ── OTP gate for reviewer tab ─────────────────────────────────
        // When the reviewer tab is active, the client must pass a valid
        // short-lived verification token proving OTP was completed.
        if ( $role_hint === 'reviewer' ) {
            $otp_token = sanitize_text_field( wp_unslash( $_POST['otp_token'] ?? '' ) );
            if ( empty( $otp_token ) ) {
                ob_end_clean();
                wp_send_json_error( array(
                    'message' => __( 'Email verification is required for reviewer login.', 'eiu-rp' ),
                ), 403 );
            }
            $tok_key      = 'eiu_otp_tok_' . md5( strtolower( trim( $email ) ) );
            $stored_hash  = get_transient( $tok_key );
            if ( ! $stored_hash || ! hash_equals( (string) $stored_hash, wp_hash( $otp_token ) ) ) {
                ob_end_clean();
                wp_send_json_error( array(
                    'message' => __( 'Verification session expired. Please complete email verification again.', 'eiu-rp' ),
                ), 403 );
            }
            // Consume the token — single use.
            delete_transient( $tok_key );
        }
        // ── End OTP gate ───────────────────────────────────────────────

        // Authenticate
        $user = wp_signon( array(
            'user_login'    => $email,
            'user_password' => $password,
            'remember'      => $remember,
        ), is_ssl() );

        if ( is_wp_error( $user ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Incorrect email or password.', 'eiu-rp' ),
            ) );
        }

        // ── Role-based redirect (determined from REAL roles, not the hint) ──
        $roles     = (array) $user->roles;
        $redirect  = '';

        // Check for optional same-origin redirect_to param first
        $redirect_to = sanitize_url( wp_unslash( $_POST['redirect_to'] ?? '' ) );
        if ( $redirect_to && strpos( $redirect_to, home_url() ) === 0 ) {
            // Only honour it if the user has a plugin role (not a generic subscriber)
            $is_plugin_role = in_array( 'eiu_reviewer', $roles, true )
                           || in_array( 'eiu_researcher', $roles, true )
                           || in_array( 'administrator', $roles, true );
            if ( $is_plugin_role ) {
                $redirect = $redirect_to;
            }
        }

        if ( ! $redirect ) {
            if ( in_array( 'eiu_reviewer', $roles, true ) ) {
                $page_id  = get_option( 'eiu_rp_reviewer_access_page_id' );
                $redirect = $page_id
                    ? get_permalink( $page_id )
                    : home_url( '/reviewer-dashboard/' );
            } elseif ( in_array( 'eiu_researcher', $roles, true ) ) {
                $page_id  = get_option( 'eiu_rp_researcher_dashboard_page_id' );
                $redirect = $page_id
                    ? get_permalink( $page_id )
                    : home_url( '/researcher-dashboard/' );
            } elseif ( user_can( $user, 'manage_options' ) ) {
                $redirect = admin_url();
            } else {
                // Authenticated but no recognised plugin role
                ob_end_clean();
                wp_send_json_error( array(
                    'message' => __( 'Your account does not have access to this portal. Please contact the administrator.', 'eiu-rp' ),
                ) );
            }
        }

        Activity_Log::log(
            'user_logged_in',
            'user',
            $user->ID,
            sprintf( 'User %s logged in via unified login. Role: %s', $user->user_email, implode( ', ', $roles ) )
        );

        ob_end_clean();
        wp_send_json_success( array(
            'message'  => __( 'Logged in! Redirecting\u2026', 'eiu-rp' ),
            'redirect' => esc_url_raw( $redirect ),
        ) );
    }

    /**
     * v2.0: Send a 6-digit OTP to a reviewer's email.
     *
     * Only sends if:
     *  - The email belongs to a verified reviewer in eiu_reviewers.
     *  - Rate limit not exceeded (3 OTP sends per IP per 10 minutes).
     *
     * The OTP is stored as a WP transient keyed by a hash of the email.
     * TTL = 5 minutes. The OTP is single-use (deleted on verify).
     *
     * We deliberately return the same success message whether or not the
     * email matches a reviewer, to avoid user enumeration.
     */
    public function send_reviewer_otp(): void {
        // Capture any stray PHP output (notices, warnings, debug output) so it
        // cannot corrupt the JSON response body and trigger a client "Network error".
        ob_start();

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_reviewer_otp' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Rate-limit: 3 sends per IP per 10 minutes.
        if ( ! Security::check_rate_limit( 'otp_send_' . Security::get_ip(), 3, 600 ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Too many OTP requests. Please wait a few minutes and try again.', 'eiu-rp' ),
            ), 429 );
        }

        $email = Security::sanitize_email( $_POST['email'] ?? '' );
        if ( ! $email || ! is_email( $email ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'eiu-rp' ) ), 422 );
        }

        // Check reviewer exists (but respond the same either way for security).
        // Note: we do NOT require verified=1 here — a reviewer who has not yet
        // clicked their verification link can still log in via OTP. The OTP itself
        // proves identity. The verified flag controls dashboard access after login.
        global $wpdb;
        $reviewer = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, full_name FROM {$wpdb->prefix}eiu_reviewers WHERE email = %s AND is_deleted = 0 LIMIT 1",
            $email
        ) );

        if ( $reviewer ) {
            // Generate a cryptographically random 6-digit OTP.
            $otp = str_pad( (string) random_int( 100000, 999999 ), 6, '0', STR_PAD_LEFT );

            // Store OTP in a transient (5-minute TTL). Key is hashed to avoid
            // leaking the email in option names visible in the DB.
            $transient_key = 'eiu_otp_rv_' . md5( strtolower( trim( $email ) ) );
            set_transient( $transient_key, wp_hash( $otp ), 5 * MINUTE_IN_SECONDS );

            // Build subject + body via Email_Templates so admin can customise in Email Template Editor.
            $subject = \EIU_RP\Email\Email_Templates::subject( 'reviewer_otp', array(
                'reviewer_name' => $reviewer->full_name,
                'site_name'     => get_bloginfo( 'name' ),
            ) );
            $body = \EIU_RP\Email\Email_Templates::reviewer_otp_body( $reviewer, $otp );

            // Send OTP via direct wp_mail — NOT through Mailer::send() wrapper.
            // Mailer::send() adds a full HTML layout wrapper which can cause some
            // SMTP plugins to misidentify the content-type. For OTP we keep it
            // simple: force HTML content-type, call wp_mail directly, then restore.
            add_filter( 'wp_mail_content_type', static function() { return 'text/html'; }, 999 );
            $mail_result = wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
            remove_all_filters( 'wp_mail_content_type' );

            if ( $mail_result ) {
                Activity_Log::log( 'reviewer_otp_sent', 'reviewer', (int) $reviewer->id,
                    sprintf( 'OTP email sent successfully to reviewer %s', $email ) );
            } else {
                Activity_Log::log( 'reviewer_otp_failed', 'reviewer', (int) $reviewer->id,
                    sprintf( 'OTP email FAILED for %s — check wp_mail configuration and SMTP settings.', $email ) );
            }
        }

        // Always return the same success message to prevent email enumeration.
        // Log the real outcome to the Activity Log for admin diagnosis.
        if ( ! $reviewer ) {
            Activity_Log::log( 'reviewer_otp_not_found', 'reviewer', 0,
                sprintf( 'OTP requested for %s — no matching reviewer record found (check email address and registration).', $email )
            );
        }

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'If that email belongs to a registered reviewer, a one-time code has been sent. Please check your inbox and spam/junk folder.', 'eiu-rp' ),
        ) );
    }

    /**
     * v2.0: Verify the OTP entered by the reviewer.
     *
     * Checks the submitted code against the stored hash.
     * On success: deletes the transient (single-use) and returns a short-lived
     * verification token that the login form must submit alongside credentials.
     */
    public function verify_reviewer_otp(): void {
        ob_start();

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_reviewer_otp' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        // Rate-limit: 5 verify attempts per IP per 10 minutes.
        if ( ! Security::check_rate_limit( 'otp_verify_' . Security::get_ip(), 5, 600 ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Too many verification attempts. Please request a new code.', 'eiu-rp' ),
            ), 429 );
        }

        $email = Security::sanitize_email( $_POST['email'] ?? '' );
        $code  = preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['otp'] ?? '' ) );

        if ( ! $email || strlen( $code ) !== 6 ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Please enter the 6-digit code sent to your email.', 'eiu-rp' ) ), 422 );
        }

        $transient_key = 'eiu_otp_rv_' . md5( strtolower( trim( $email ) ) );
        $stored_hash   = get_transient( $transient_key );

        if ( ! $stored_hash || ! hash_equals( (string) $stored_hash, wp_hash( $code ) ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid or expired code. Please request a new one.', 'eiu-rp' ) ) );
        }

        // OTP is correct — consume it immediately (single-use).
        delete_transient( $transient_key );

        // Issue a short-lived verification token so the login form knows OTP was passed.
        $token   = wp_generate_password( 32, false );
        $tok_key = 'eiu_otp_tok_' . md5( strtolower( trim( $email ) ) );
        set_transient( $tok_key, wp_hash( $token ), 3 * MINUTE_IN_SECONDS );

        Activity_Log::log( 'reviewer_otp_verified', 'reviewer', 0,
            sprintf( 'OTP verified for email %s', $email ) );

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'Email verified. Please enter your password to continue.', 'eiu-rp' ),
            'token'   => $token,
        ) );
    }

    /**
     * v2.0: Return current statuses for a set of article IDs belonging to
     * the logged-in researcher. Used by the real-time polling logic.
     */
    public function get_article_statuses(): void {
        ob_start();
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) || ! is_user_logged_in() ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }

        $current_user = wp_get_current_user();
        $user_email   = sanitize_email( $current_user->user_email );

        $raw_ids = sanitize_text_field( wp_unslash( $_POST['ids'] ?? '' ) );
        $ids     = array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) );
        if ( empty( $ids ) ) {
            ob_end_clean();
            wp_send_json_success( array() );
        }

        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $args         = array_merge( $ids, array( $user_email ) );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results(
            // call_user_func_array used for PHP 7.4 compat (no spread operator)
            call_user_func_array(
                array( $wpdb, 'prepare' ),
                array_merge(
                    array( "SELECT id, status FROM {$wpdb->prefix}eiu_articles WHERE id IN ($placeholders) AND author_email = %s" ),
                    $args
                )
            ),
            ARRAY_A
        );

        $map = array();
        foreach ( $rows as $row ) {
            // Map full status slug back to the CSS class fragment used client-side
            $map[ (string) $row['id'] ] = $row['status'];
        }

        ob_end_clean();
        wp_send_json_success( $map );
    }

    /**
     * v2.1: Upload a profile photo for the logged-in researcher OR reviewer.
     * Stores the attachment ID in user meta (eiu_profile_photo_id).
     * For reviewers, also updates eiu_reviewers.profile_photo_id.
     */
    public function upload_profile_photo(): void {
        ob_start();
        if ( ! is_user_logged_in() ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'eiu-rp' ) ), 401 );
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        if ( empty( $_FILES['photo']['tmp_name'] ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'No photo uploaded.', 'eiu-rp' ) ), 422 );
        }

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        if ( $_FILES['photo']['size'] > 3 * 1024 * 1024 ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Photo must be under 3 MB.', 'eiu-rp' ) ), 422 );
        }

        // Use finfo to detect real MIME type — do NOT trust browser-reported type,
        // which can be empty string or wrong on some mobile/OS combinations.
        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
        $real_mime = '';
        if ( function_exists( 'finfo_open' ) ) {
            $fi        = finfo_open( FILEINFO_MIME_TYPE );
            $real_mime = finfo_file( $fi, $_FILES['photo']['tmp_name'] );
            finfo_close( $fi );
        } elseif ( function_exists( 'mime_content_type' ) ) {
            $real_mime = mime_content_type( $_FILES['photo']['tmp_name'] );
        } else {
            // Fallback: trust browser type
            $real_mime = $_FILES['photo']['type'];
        }
        if ( ! in_array( $real_mime, $allowed_mimes, true ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Only JPG, PNG, and WebP images are allowed.', 'eiu-rp' ) ), 422 );
        }

        $upload = wp_handle_upload( $_FILES['photo'], array( 'test_form' => false, 'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        ) ) );

        if ( isset( $upload['error'] ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $upload['error'] ) );
        }

        $attachment_id = wp_insert_attachment( array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
            'post_status'    => 'inherit',
        ), $upload['file'] );

        if ( is_wp_error( $attachment_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'eiu_profile_photo_id', $attachment_id );

        // If this user is a reviewer, update the reviewers table too
        $reviewer = \EIU_RP\Models\Reviewer::get_by_user( $user_id );
        if ( $reviewer ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'eiu_reviewers',
                array( 'profile_photo_id' => $attachment_id ),
                array( 'id' => (int) $reviewer->id ),
                array( '%d' ), array( '%d' )
            );
        }

        $thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
        $full_url  = wp_get_attachment_image_url( $attachment_id, 'medium' );

        Activity_Log::log( 'profile_photo_uploaded', 'user', $user_id,
            "User #{$user_id} uploaded a new profile photo (attachment #{$attachment_id})." );

        ob_end_clean();
        wp_send_json_success( array(
            'attachment_id' => $attachment_id,
            'thumb_url'     => $thumb_url,
            'full_url'      => $full_url,
            'message'       => __( 'Profile photo updated.', 'eiu-rp' ),
        ) );
    }

    /**
     * v1.9: Return fresh nonces for use by forms that may be served from cache.
     *
     * Forms that embed nonces in the HTML (apply-as-researcher, unified-login)
     * fail when a page-caching plugin serves a stale HTML page with an expired
     * nonce. This endpoint is called by JS on page load to fetch fresh values
     * that are guaranteed to be valid for the current user/session.
     *
     * The endpoint itself must NEVER be cached — it uses no-store headers
     * and is a POST action so caching plugins skip it.
     */
    public function get_nonce(): void {
        ob_start();
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );

        $for = sanitize_key( $_POST['for'] ?? $_GET['for'] ?? '' );

        $nonces = array();
        switch ( $for ) {
            case 'apply':
                $nonces['otp_nonce']  = wp_create_nonce( 'eiu_rp_apply_otp' );
                $nonces['form_nonce'] = wp_create_nonce( 'eiu_rp_apply_form' );
                break;
            case 'login':
                $nonces['login_nonce'] = wp_create_nonce( 'eiu_unified_login' );
                $nonces['otp_nonce']   = wp_create_nonce( 'eiu_reviewer_otp' );
                break;
            default:
                ob_end_clean();
                wp_send_json_error( array( 'message' => 'Unknown nonce group.' ), 400 );
                return;
        }

        ob_end_clean();
        wp_send_json_success( $nonces );
    }

}
