<?php
/**
 * Reviewer AJAX Handler.
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */

namespace EIU_RP\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use EIU_RP\Security\Security;
use EIU_RP\Models\Reviewer;
use EIU_RP\Models\Activity_Log;

/**
 * Class Reviewer_Handler
 */
class Reviewer_Handler {

    public function __construct() {
        add_action( 'wp_ajax_eiu_rp_register_reviewer',        array( $this, 'register' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_register_reviewer', array( $this, 'register' ) );
        add_action( 'wp_ajax_eiu_rp_admin_assign_reviewer',    array( $this, 'admin_assign' ) );
        add_action( 'wp_ajax_eiu_rp_admin_verify_reviewer',    array( $this, 'admin_verify' ) );
        add_action( 'wp_ajax_eiu_rp_admin_create_reviewer',    array( $this, 'admin_create' ) );
        add_action( 'wp_ajax_eiu_rp_admin_sync_reviewers',     array( $this, 'admin_sync' ) );
    }

    /**
     * Handle reviewer self-registration.
     */
    public function register(): void {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        Security::verify_nonce( $nonce, 'eiu_rp_frontend' );

        $data = Security::sanitize_post_fields( array(
            'full_name'      => 'text',
            'email'          => 'email',
            'organization'   => 'text',
            'specialization' => 'textarea',
        ) );

        $missing = Security::validate_required( $data, array( 'full_name', 'email' ) );
        if ( ! empty( $missing ) ) {
            wp_send_json_error( array(
                'message' => __( 'Full name and email are required.', 'eiu-rp' ),
                'fields'  => $missing,
            ), 422 );
        }

        if ( ! is_email( $data['email'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Please provide a valid email address.', 'eiu-rp' ) ), 422 );
        }

        $reviewer_id = Reviewer::register( $data );

        if ( is_wp_error( $reviewer_id ) ) {
            wp_send_json_error( array( 'message' => $reviewer_id->get_error_message() ), 400 );
        }

        Activity_Log::log( 'reviewer_registered', 'reviewer', $reviewer_id, "Reviewer {$data['full_name']} registered." );

        wp_send_json_success( array(
            'message' => __( 'Registration successful. Please check your email to verify your account.', 'eiu-rp' ),
        ) );
    }

    /**
     * Admin: manually assign reviewer to article.
     */
    public function admin_assign(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_reviewers' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id  = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        $reviewer_id = Security::sanitize_int( $_POST['reviewer_id'] ?? 0 );
        $due_date    = sanitize_text_field( wp_unslash( $_POST['due_date'] ?? '' ) );

        if ( ! $article_id || ! $reviewer_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid article or reviewer.', 'eiu-rp' ) ), 422 );
        }

        $result = \EIU_RP\Models\Review::assign( $article_id, $reviewer_id, $due_date );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        Activity_Log::log(
            'reviewer_assigned',
            'review',
            $result,
            sprintf( 'Reviewer #%d assigned to article #%d', $reviewer_id, $article_id )
        );

        wp_send_json_success( array(
            'message'   => __( 'Reviewer assigned successfully.', 'eiu-rp' ),
            'review_id' => $result,
        ) );
    }

    /**
     * Admin: manually verify a reviewer profile.
     */
    public function admin_verify(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_reviewers' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $reviewer_id = Security::sanitize_int( $_POST['reviewer_id'] ?? 0 );

        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'eiu_reviewers',
            array( 'verified' => 1 ),
            array( 'id' => $reviewer_id ),
            array( '%d' ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_send_json_error( array( 'message' => __( 'Could not verify reviewer.', 'eiu-rp' ) ) );
        }

        Activity_Log::log( 'reviewer_verified_by_admin', 'reviewer', $reviewer_id, "Reviewer #$reviewer_id manually verified by admin." );

        wp_send_json_success( array( 'message' => __( 'Reviewer verified.', 'eiu-rp' ) ) );
    }

    /**
     * Admin: create a new reviewer record directly (name + email).
     * Creates the WP user if they don't exist, inserts eiu_reviewers row,
     * and marks them verified immediately (admin-created = trusted).
     */
    public function admin_create(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_reviewers' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $full_name      = sanitize_text_field( wp_unslash( $_POST['full_name']      ?? '' ) );
        $email          = sanitize_email( wp_unslash( $_POST['email']          ?? '' ) );
        $organization   = sanitize_text_field( wp_unslash( $_POST['organization']   ?? '' ) );
        $specialization = sanitize_textarea_field( wp_unslash( $_POST['specialization'] ?? '' ) );

        if ( ! $full_name || ! $email || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Full name and a valid email are required.', 'eiu-rp' ) ), 422 );
        }

        global $wpdb;

        // Check if a reviewer record already exists for this email.
        $existing_rv = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eiu_reviewers WHERE email = %s LIMIT 1",
            $email
        ) );
        if ( $existing_rv ) {
            wp_send_json_error( array( 'message' => __( 'A reviewer with this email already exists.', 'eiu-rp' ) ) );
        }

        // Find or create WP user.
        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $username = sanitize_user( strtolower( explode( '@', $email )[0] ) . '_' . wp_generate_password( 4, false ) );
            $user_id  = wp_create_user( $username, wp_generate_password( 16 ), $email );
            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
            }
            wp_update_user( array( 'ID' => $user_id, 'display_name' => $full_name ) );
            $wp_user = new \WP_User( $user_id );
            $wp_user->set_role( 'eiu_reviewer' );
        } else {
            $user_id = $user->ID;
            $wp_user = new \WP_User( $user_id );
            $wp_user->add_role( 'eiu_reviewer' );
        }

        // Insert reviewer record — mark verified immediately (admin-created).
        $result = $wpdb->insert(
            $wpdb->prefix . 'eiu_reviewers',
            array(
                'user_id'          => $user_id,
                'full_name'        => $full_name,
                'email'            => $email,
                'organization'     => $organization,
                'specialization'   => $specialization,
                'verified'         => 1,
                'verification_key' => '',
                'registered_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not create reviewer record.', 'eiu-rp' ) ) );
        }

        $reviewer_id = (int) $wpdb->insert_id;
        Activity_Log::log( 'reviewer_created_by_admin', 'reviewer', $reviewer_id,
            "Reviewer #{$reviewer_id} ({$email}) created directly by admin." );

        wp_send_json_success( array(
            'message'     => __( 'Reviewer created and verified successfully.', 'eiu-rp' ),
            'reviewer_id' => $reviewer_id,
            'full_name'   => $full_name,
            'email'       => $email,
        ) );
    }

    /**
     * Admin: sync — create eiu_reviewers records for any WP user with
     * the eiu_reviewer role who does not yet have a reviewer record.
     * This fixes the case where reviewers were assigned the role manually
     * in WordPress but never completed the self-registration form.
     */
    public function admin_sync(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_admin'
        );

        if ( ! current_user_can( 'eiu_manage_reviewers' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $wp_users = get_users( array( 'role' => 'eiu_reviewer', 'number' => 500 ) );
        $created  = 0;

        global $wpdb;
        foreach ( $wp_users as $user ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}eiu_reviewers WHERE user_id = %d OR email = %s LIMIT 1",
                $user->ID, $user->user_email
            ) );
            if ( $exists ) {
                continue;
            }
            $insert = $wpdb->insert(
                $wpdb->prefix . 'eiu_reviewers',
                array(
                    'user_id'          => $user->ID,
                    'full_name'        => $user->display_name ?: $user->user_login,
                    'email'            => $user->user_email,
                    'organization'     => '',
                    'specialization'   => '',
                    'verified'         => 1,
                    'verification_key' => '',
                    'registered_at'    => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
            );
            if ( $insert ) {
                $created++;
                Activity_Log::log( 'reviewer_synced', 'reviewer', (int) $wpdb->insert_id,
                    "Reviewer record synced for WP user #{$user->ID} ({$user->user_email})." );
            }
        }

        wp_send_json_success( array(
            'message' => sprintf(
                _n( '%d reviewer record created.', '%d reviewer records created.', $created, 'eiu-rp' ),
                $created
            ),
            'created' => $created,
        ) );
    }
}
