<?php
/**
 * Frontend AJAX: Download Request + Post Comment.
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */
namespace EIU_RP\API;
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Security\Security;
use EIU_RP\Models\Article;
use EIU_RP\Models\Activity_Log;

class Frontend_Handler {
    public function __construct() {
        add_action( 'wp_ajax_eiu_rp_download_request',        array( $this, 'download' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_download_request', array( $this, 'download' ) );
        add_action( 'wp_ajax_eiu_rp_post_comment',            array( $this, 'post_comment' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_post_comment',     array( $this, 'post_comment' ) );
    }

    /**
     * Gate the file download behind an email capture.
     */
    public function download(): void {
        $post_id = Security::sanitize_int( $_POST['post_id'] ?? 0 );
        $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        Security::verify_nonce( $nonce, 'eiu_rp_download_' . $post_id );

        $email = Security::sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'eiu-rp' ) ) );
        }

        $article = Article::get_by_post( $post_id );
        if ( ! $article || empty( $article->file_path ) || ! file_exists( $article->file_path ) ) {
            wp_send_json_error( array( 'message' => __( 'File not available for download.', 'eiu-rp' ) ) );
        }

        // Log download request.
        Activity_Log::log( 'file_download_requested', 'article', $article->id,
            "Download requested by {$email} for article #{$article->id}" );

        // Store email lead.
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'eiu_download_leads', array(
            'article_id'   => $article->id,
            'email'        => $email,
            'requested_at' => current_time( 'mysql' ),
            'ip'           => Security::get_ip(),
        ), array( '%d', '%s', '%s', '%s' ) );

        // Generate a short-lived signed download URL via a transient token.
        $token = wp_generate_password( 32, false );
        set_transient( 'eiu_rp_dl_' . $token, array(
            'article_id' => $article->id,
            'file_path'  => $article->file_path,
            'file_name'  => $article->file_name,
            'email'      => $email,
        ), 5 * MINUTE_IN_SECONDS );

        // Build the download URL — always anchored to home_url() to prevent open redirect.
        $download_url = add_query_arg( array(
            'eiu_rp_dl' => $token,
        ), home_url( '/' ) );

        wp_send_json_success( array(
            'message' => __( 'Your download is starting…', 'eiu-rp' ),
            'url'     => esc_url_raw( $download_url ),
        ) );
    }

    /**
     * Handle AJAX comment submission.
     */
    public function post_comment(): void {
        $post_id = Security::sanitize_int( $_POST['post_id'] ?? 0 );
        $nonce   = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        Security::verify_nonce( $nonce, 'eiu_rp_comment_' . $post_id );

        // Rate-limit: 3 comments per IP per hour.
        if ( ! Security::check_rate_limit( 'comment_' . Security::get_ip(), 3 ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many comments. Please wait before commenting again.', 'eiu-rp' ) ), 429 );
        }

        $author       = Security::sanitize_text( $_POST['comment_author'] ?? '' );
        $author_email = Security::sanitize_email( $_POST['comment_author_email'] ?? '' );
        $content      = Security::sanitize_textarea( $_POST['comment_content'] ?? '' );

        if ( ! $author || ! is_email( $author_email ) || ! $content ) {
            wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'eiu-rp' ) ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'eiu_article' ) {
            wp_send_json_error( array( 'message' => __( 'Invalid article.', 'eiu-rp' ) ) );
        }

        // Check comments are open.
        if ( ! comments_open( $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Comments are closed for this article.', 'eiu-rp' ) ) );
        }

        $comment_id = wp_insert_comment( array(
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author,
            'comment_author_email' => $author_email,
            'comment_content'      => $content,
            'comment_author_IP'    => Security::get_ip(),
            'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'comment_approved'     => get_option( 'comment_moderation' ) ? 0 : 1,
        ) );

        if ( ! $comment_id || is_wp_error( $comment_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Could not post comment.', 'eiu-rp' ) ) );
        }

        $approved = get_option( 'comment_moderation' );
        $message  = $approved
            ? __( 'Your comment has been submitted and is awaiting moderation.', 'eiu-rp' )
            : __( 'Your comment has been posted.', 'eiu-rp' );

        wp_send_json_success( array( 'message' => $message ) );
    }
}
// Standalone download token handler — hooked in Plugin bootstrap


// v1.2: PDF preview token handler — serves file inline for preview (headers prevent download)
add_action( 'init', function() {
    if ( ! isset( $_GET['eiu_pdf_preview'] ) ) return;
    $token = sanitize_text_field( wp_unslash( $_GET['eiu_pdf_preview'] ) );
    // Token must be exactly 32 hex/alnum chars to prevent injection.
    if ( ! preg_match( '/^[A-Za-z0-9]{20,64}$/', $token ) ) {
        wp_die( esc_html__( 'Invalid preview token.', 'eiu-rp' ), '', 400 );
    }
    $data = get_transient( 'eiu_pdf_preview_' . $token );
    if ( ! $data || empty( $data['file'] ) ) {
        wp_die( esc_html__( 'Preview not available.', 'eiu-rp' ), '', 404 );
    }
    // Path traversal guard: file must be inside wp-uploads.
    $uploads   = wp_upload_dir();
    $real_file = realpath( $data['file'] );
    $real_base = realpath( $uploads['basedir'] );
    if ( ! $real_file || ! $real_base || strpos( $real_file, $real_base ) !== 0 ) {
        wp_die( esc_html__( 'File access denied.', 'eiu-rp' ), '', 403 );
    }
    if ( ! file_exists( $real_file ) ) {
        wp_die( esc_html__( 'Preview not available.', 'eiu-rp' ), '', 404 );
    }
    // Serve inline (not as attachment) so browser embeds it.
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="preview.pdf"' );
    header( 'Content-Length: ' . filesize( $real_file ) );
    header( 'Cache-Control: private, max-age=3600' );
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    readfile( $real_file ); // phpcs:ignore
    exit;
} );

// Register the token-based file serve
add_action( 'init', function() {
    if ( ! isset( $_GET['eiu_rp_dl'] ) ) return;
    $token = sanitize_text_field( wp_unslash( $_GET['eiu_rp_dl'] ) );
    // Token must be exactly 32 alnum chars.
    if ( ! preg_match( '/^[A-Za-z0-9]{20,64}$/', $token ) ) {
        wp_die( esc_html__( 'Invalid download token.', 'eiu-rp' ), '', 400 );
    }
    $data = get_transient( 'eiu_rp_dl_' . $token );
    if ( ! $data ) {
        wp_die( esc_html__( 'Download link expired or invalid.', 'eiu-rp' ), '', 404 );
    }
    // Path traversal guard: file must be inside wp-uploads.
    $uploads   = wp_upload_dir();
    $real_file = realpath( $data['file_path'] );
    $real_base = realpath( $uploads['basedir'] );
    if ( ! $real_file || ! $real_base || strpos( $real_file, $real_base ) !== 0 ) {
        wp_die( esc_html__( 'File access denied.', 'eiu-rp' ), '', 403 );
    }
    if ( ! file_exists( $real_file ) ) {
        wp_die( esc_html__( 'File not found.', 'eiu-rp' ), '', 404 );
    }
    delete_transient( 'eiu_rp_dl_' . $token );
    $filename = ! empty( $data['file_name'] ) ? $data['file_name'] : basename( $real_file );
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    header( 'Content-Length: ' . filesize( $real_file ) );
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $real_file ); // phpcs:ignore
    exit;
} );

// v1.3: AJAX handler — upload an image and return the attachment ID + URL.
// Used by the submission form photo pickers when uploading a new image directly.
add_action( 'wp_ajax_eiu_rp_upload_media_image',        'eiu_rp_handle_media_image_upload' );
add_action( 'wp_ajax_nopriv_eiu_rp_upload_media_image', 'eiu_rp_handle_media_image_upload' );

function eiu_rp_handle_media_image_upload(): void {
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'eiu_rp_frontend' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
    }

    if ( empty( $_FILES['image']['tmp_name'] ) || $_FILES['image']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( array( 'message' => __( 'Upload failed.', 'eiu-rp' ) ) );
    }

    // Size cap: 5 MB.
    if ( $_FILES['image']['size'] > 5 * MB_IN_BYTES ) {
        wp_send_json_error( array( 'message' => __( 'Image must be under 5 MB.', 'eiu-rp' ) ) );
    }

    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }

    $allowed_mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    );

    $upload = wp_handle_upload( $_FILES['image'], array( 'test_form' => false, 'mimes' => $allowed_mimes ) );

    if ( isset( $upload['error'] ) ) {
        wp_send_json_error( array( 'message' => $upload['error'] ) );
    }

    $attachment_id = wp_insert_attachment( array(
        'guid'           => $upload['url'],
        'post_mime_type' => $upload['type'],
        'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
        'post_status'    => 'inherit',
    ), $upload['file'] );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

    wp_send_json_success( array(
        'attachment_id' => $attachment_id,
        'url'           => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
        'full_url'      => wp_get_attachment_image_url( $attachment_id, 'large' ),
    ) );
}

// v1.5: Secure file download for reviewers (frontend dashboard).
add_action( 'wp_ajax_eiu_rp_reviewer_download', 'eiu_rp_reviewer_download_file' );

function eiu_rp_reviewer_download_file(): void {
    // Must be logged in.
    if ( ! is_user_logged_in() ) {
        wp_die( esc_html__( 'Access denied.', 'eiu-rp' ), 403 );
    }

    $nonce      = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
    $article_id = absint( $_GET['article_id'] ?? 0 );

    if ( ! wp_verify_nonce( $nonce, 'eiu_rp_reviewer_dl_' . $article_id ) ) {
        wp_die( esc_html__( 'Security check failed.', 'eiu-rp' ), 403 );
    }

    // Confirm the current user is a reviewer assigned to this article.
    $user_id  = get_current_user_id();
    $reviewer = \EIU_RP\Models\Reviewer::get_by_user( $user_id );

    // Admins and editors can also download.
    $is_admin = current_user_can( 'eiu_manage_articles' );

    if ( ! $is_admin ) {
        if ( ! $reviewer ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ), 403 );
        }
        // Confirm assignment.
        global $wpdb;
        $assigned = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eiu_reviews
             WHERE article_id = %d AND reviewer_id = %d AND is_deleted = 0",
            $article_id, $reviewer->id
        ) );
        if ( ! $assigned ) {
            wp_die( esc_html__( 'You are not assigned to this article.', 'eiu-rp' ), 403 );
        }
    }

    $article = \EIU_RP\Models\Article::get( $article_id );
    if ( ! $article || empty( $article->file_path ) ) {
        wp_die( esc_html__( 'File not found.', 'eiu-rp' ), 404 );
    }

    // Path traversal guard.
    $uploads   = wp_upload_dir();
    $real_file = realpath( $article->file_path );
    $real_base = realpath( $uploads['basedir'] );
    if ( ! $real_file || ! $real_base || strpos( $real_file, $real_base ) !== 0 ) {
        wp_die( esc_html__( 'File access denied.', 'eiu-rp' ), 403 );
    }
    if ( ! file_exists( $real_file ) ) {
        wp_die( esc_html__( 'File not found.', 'eiu-rp' ), 404 );
    }

    $filename = $article->file_name ?: basename( $real_file );
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    header( 'Content-Length: ' . filesize( $real_file ) );
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $real_file ); // phpcs:ignore
    exit;
}
