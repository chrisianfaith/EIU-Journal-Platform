<?php
/**
 * Article Edit Handler (v1.2).
 * Admin and Reviewer can update article fields via AJAX from backend.
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */

namespace EIU_RP\API;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Security\Security;
use EIU_RP\Models\Article;
use EIU_RP\Models\Activity_Log;

/**
 * Class Article_Edit_Handler
 */
class Article_Edit_Handler {

    public function __construct() {
        add_action( 'wp_ajax_eiu_rp_save_article_edit',       array( $this, 'save' ) );
        add_action( 'wp_ajax_eiu_rp_upload_author_photo',     array( $this, 'upload_author_photo' ) );
        // v1.9: Reviewer-side article editing (uses frontend nonce, scoped to assigned articles)
        add_action( 'wp_ajax_eiu_rp_reviewer_edit_article',   array( $this, 'reviewer_edit' ) );
        add_action( 'wp_ajax_eiu_rp_reviewer_upload_file',    array( $this, 'reviewer_upload_file' ) );
        add_action( 'wp_ajax_eiu_rp_reviewer_upload_thumb',   array( $this, 'reviewer_upload_thumb' ) );
    }

    /**
     * Save all editable article fields.
     * Requires: eiu_manage_articles OR eiu_review_articles capability.
     */
    public function save(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_article_edit'
        );

        if ( ! current_user_can( 'eiu_manage_articles' ) && ! current_user_can( 'eiu_review_articles' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        if ( ! $article_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid article ID.', 'eiu-rp' ) ) );
        }

        // Build update data — only include fields that were sent.
        $data = array();

        $text_fields = array(
            'title', 'author_name', 'author_org', 'author_email',
            'coauthor_name', 'coauthor_org', 'coauthor_email',
            'contact_number', 'country', 'doi', 'issn',
            'keywords', 'disclosures', 'advisers', 'summary',
        );
        foreach ( $text_fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $data[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
            }
        }

        // Rich content fields — sanitize as post content.
        foreach ( array( 'abstract', 'article_content', 'references', 'author_affiliation' ) as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $data[ $f ] = wp_kses_post( wp_unslash( $_POST[ $f ] ) );
            }
        }

        // Subject taxonomy.
        if ( isset( $_POST['subject'] ) ) {
            $data['subject'] = sanitize_text_field( wp_unslash( $_POST['subject'] ) );
        }

        // Photo attachment IDs.
        if ( isset( $_POST['author_photo_id'] ) ) {
            $data['author_photo_id'] = Security::sanitize_int( $_POST['author_photo_id'] );
        }
        if ( isset( $_POST['coauthor_photo_id'] ) ) {
            $data['coauthor_photo_id'] = Security::sanitize_int( $_POST['coauthor_photo_id'] );
        }
        if ( isset( $_POST['thumbnail_attachment_id'] ) ) {
            $data['thumbnail_attachment_id'] = Security::sanitize_int( $_POST['thumbnail_attachment_id'] );
        }

        $result = Article::update( $article_id, $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message'    => __( 'Article saved successfully.', 'eiu-rp' ),
            'article_id' => $article_id,
        ) );
    }

    /**
     * Handle author/co-author photo upload.
     * Returns the attachment ID + URL for the admin to use.
     */
    public function upload_author_photo(): void {
        Security::verify_admin_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_article_edit'
        );

        if ( ! current_user_can( 'eiu_manage_articles' ) && ! current_user_can( 'eiu_review_articles' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        if ( empty( $_FILES['photo']['tmp_name'] ) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( array( 'message' => __( 'Upload failed.', 'eiu-rp' ) ) );
        }

        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $allowed_mime = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        );

        $upload = wp_handle_upload( $_FILES['photo'], array( 'test_form' => false, 'mimes' => $allowed_mime ) );

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
        ) );
    }
    /* ═══════════════════════════════════════════════════════════════
       v1.9  Reviewer-side article editing
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Save article fields on behalf of the reviewer.
     * Scope: abstract, references, thumbnail, file.
     * Requires: assigned reviewer for this article + eiu_rp_frontend nonce.
     */
    public function reviewer_edit(): void {
        ob_start();

        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_frontend'
        ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        if ( ! current_user_can( 'eiu_review_articles' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        if ( ! $article_id ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid article ID.', 'eiu-rp' ) ) );
        }

        // Verify the current reviewer is actually assigned to this article.
        if ( ! $this->reviewer_is_assigned( $article_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'You are not assigned to this article.', 'eiu-rp' ) ), 403 );
        }

        // Only allow the four fields the reviewer is permitted to edit.
        $data = array();

        if ( isset( $_POST['abstract'] ) ) {
            $data['abstract'] = wp_kses_post( wp_unslash( $_POST['abstract'] ) );
        }
        if ( isset( $_POST['references'] ) ) {
            $data['references'] = wp_kses_post( wp_unslash( $_POST['references'] ) );
        }
        if ( isset( $_POST['thumbnail_attachment_id'] ) && (int) $_POST['thumbnail_attachment_id'] > 0 ) {
            $data['thumbnail_attachment_id'] = Security::sanitize_int( $_POST['thumbnail_attachment_id'] );
        }
        // File replacement is handled by reviewer_upload_file — after upload, post the file meta separately.
        if ( isset( $_POST['new_file_path'] ) && isset( $_POST['new_file_name'] ) ) {
            global $wpdb;
            $article = Article::get( $article_id );
            if ( $article && $article->post_id ) {
                $wpdb->update(
                    $wpdb->prefix . 'eiu_articles',
                    array(
                        'file_path' => sanitize_text_field( wp_unslash( $_POST['new_file_path'] ) ),
                        'file_name' => sanitize_text_field( wp_unslash( $_POST['new_file_name'] ) ),
                        'file_type' => sanitize_text_field( wp_unslash( $_POST['new_file_type'] ?? '' ) ),
                        'updated_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $article_id ),
                    array( '%s', '%s', '%s', '%s' ),
                    array( '%d' )
                );
            }
        }

        if ( ! empty( $data ) ) {
            $result = Article::update( $article_id, $data );
            if ( is_wp_error( $result ) ) {
                ob_end_clean();
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
        }

        Activity_Log::log(
            'reviewer_edited_article',
            'article',
            $article_id,
            sprintf( 'Reviewer #%d edited article #%d fields: %s.', get_current_user_id(), $article_id, implode( ', ', array_keys( $data ) ) )
        );

        ob_end_clean();
        wp_send_json_success( array(
            'message' => __( 'Article updated successfully.', 'eiu-rp' ),
        ) );
    }

    /**
     * Replace the submitted article file (reviewer-side).
     * Accepts PDF, PPT, PPTX — same rules as original submission.
     */
    public function reviewer_upload_file(): void {
        ob_start();

        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_frontend'
        ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        if ( ! current_user_can( 'eiu_review_articles' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        if ( ! $this->reviewer_is_assigned( $article_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Not assigned.', 'eiu-rp' ) ), 403 );
        }

        if ( empty( $_FILES['article_file']['tmp_name'] ) || $_FILES['article_file']['error'] !== UPLOAD_ERR_OK ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'No file received.', 'eiu-rp' ) ) );
        }

        $article  = Article::get( $article_id );
        $uploader = new \EIU_RP\Security\File_Upload();
        $result   = $uploader->upload_article_file( $_FILES['article_file'], (int) ( $article->post_id ?? 0 ) );

        if ( is_wp_error( $result ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Persist new file path/name to DB
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'eiu_articles',
            array(
                'file_path'  => $result['path'],
                'file_name'  => $result['name'],
                'file_type'  => $result['type'] ?? pathinfo( $result['name'], PATHINFO_EXTENSION ),
                'updated_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $article_id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        Activity_Log::log( 'reviewer_replaced_file', 'article', $article_id,
            "Reviewer #" . get_current_user_id() . " replaced article file: " . $result['name'] );

        ob_end_clean();
        wp_send_json_success( array(
            'message'   => __( 'File replaced successfully.', 'eiu-rp' ),
            'file_name' => $result['name'],
            'file_path' => $result['path'],
            'file_type' => $result['type'] ?? '',
        ) );
    }

    /**
     * Upload a replacement thumbnail (reviewer-side).
     * Returns attachment_id + preview URL.
     */
    public function reviewer_upload_thumb(): void {
        ob_start();

        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
            'eiu_rp_frontend'
        ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }

        if ( ! current_user_can( 'eiu_review_articles' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }

        $article_id = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        if ( ! $this->reviewer_is_assigned( $article_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Not assigned.', 'eiu-rp' ) ), 403 );
        }

        if ( empty( $_FILES['thumbnail']['tmp_name'] ) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'No image received.', 'eiu-rp' ) ) );
        }

        if ( ! function_exists( 'media_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $allowed = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
        );

        $upload = wp_handle_upload( $_FILES['thumbnail'], array( 'test_form' => false, 'mimes' => $allowed ) );
        if ( isset( $upload['error'] ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $upload['error'] ) );
        }

        $att_id = wp_insert_attachment( array(
            'guid'           => $upload['url'],
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
            'post_status'    => 'inherit',
        ), $upload['file'] );

        if ( is_wp_error( $att_id ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $att_id->get_error_message() ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $upload['file'] ) );

        // Set as post thumbnail immediately.
        $article = Article::get( $article_id );
        if ( $article && $article->post_id ) {
            set_post_thumbnail( (int) $article->post_id, $att_id );
        }

        ob_end_clean();
        wp_send_json_success( array(
            'message'       => __( 'Thumbnail updated.', 'eiu-rp' ),
            'attachment_id' => $att_id,
            'url'           => wp_get_attachment_image_url( $att_id, 'medium' ) ?: $upload['url'],
        ) );
    }

    /**
     * Check that the logged-in reviewer is assigned to the given article.
     */
    private function reviewer_is_assigned( int $article_id ): bool {
        if ( ! $article_id ) {
            return false;
        }
        global $wpdb;
        $reviewer = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.id FROM {$wpdb->prefix}eiu_reviewers r
             INNER JOIN {$wpdb->prefix}eiu_reviews rv ON rv.reviewer_id = r.id
             WHERE r.wp_user_id = %d AND rv.article_id = %d AND r.is_deleted = 0
             LIMIT 1",
            get_current_user_id(),
            $article_id
        ) );
        return $reviewer !== null;
    }

}
