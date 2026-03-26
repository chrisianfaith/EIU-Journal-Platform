<?php
/**
 * Frontend Submission Handler.
 *
 * @package EIU_Research_Publication
 * @subpackage API
 */

namespace EIU_RP\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use EIU_RP\Security\Security;
use EIU_RP\Security\File_Upload;
use EIU_RP\Models\Article;
use EIU_RP\Models\Activity_Log;

/**
 * Class Submission_Handler
 *
 * Handles article submission AJAX requests.
 */
class Submission_Handler {

    public function __construct() {
        add_action( 'wp_ajax_eiu_rp_submit_article',        array( $this, 'handle' ) );
        add_action( 'wp_ajax_nopriv_eiu_rp_submit_article', array( $this, 'handle' ) );
        add_action( 'wp_ajax_eiu_rp_resubmit_article',      array( $this, 'resubmit' ) );
        add_action( 'init', array( $this, 'maybe_handle_verify' ) );
    }

    /**
     * Main submission handler.
     */
    public function handle(): void {
        // Record the buffer level BEFORE opening our own so we can flush
        // back to exactly this level — not all the way to 0 (which would
        // close WP core's own buffers and corrupt headers).
        $ob_level_before = ob_get_level();
        ob_start();

        // Verify nonce.
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        Security::verify_nonce( $nonce, 'eiu_rp_frontend' );

        // Rate limiting by IP.
        $ip = Security::get_ip();
        if ( ! Security::check_rate_limit( 'submission_' . $ip ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Too many submissions. Please try again in an hour.', 'eiu-rp' ),
            ), 429 );
        }

        // Sanitize all text fields.
        $fields = array(
            'article_title'   => 'text',
            'subject'         => 'text',
            'abstract'        => 'html',   // v1.1: rich content via wp_editor
            'author_name'     => 'text',
            'author_org'      => 'text',
            'author_email'    => 'email',
            'coauthor_name'    => 'text',
            'coauthor_org'     => 'text',
            'coauthor_email'   => 'email',
            'human_participants'=> 'text',
            'ethics_level'     => 'text',
            'contact_number'  => 'text',
            'country'         => 'text',
            'doi'             => 'text',
            'issn'            => 'text',
            'keywords'        => 'text',
            'disclosures'     => 'text',
            'advisers'        => 'text',
            'summary'         => 'text',
            'references'      => 'html',   // v1.2: rich references
            'author_affiliation' => 'html', // v2.2: formatted affiliation text
        );

        $data = Security::sanitize_post_fields( $fields );

        // Rename for model consistency.
        $data['title']       = $data['article_title'];
        $data['author_email'] = $data['author_email'];

        // Validate required fields. contact_number + country are optional (v1.1).
        // Always link submission to logged-in user's WP ID (0 if not logged in).
        $data['author_user_id'] = is_user_logged_in() ? get_current_user_id() : 0;

        $required = array( 'article_title', 'subject', 'abstract', 'author_name', 'author_email' );
        $missing  = Security::validate_required( $data, $required );

        if ( ! empty( $missing ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Please fill in all required fields.', 'eiu-rp' ),
                'fields'  => $missing,
            ), 422 );
        }

        // Validate email.
        if ( ! is_email( $data['author_email'] ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Please enter a valid email address.', 'eiu-rp' ),
                'fields'  => array( 'author_email' ),
            ), 422 );
        }

        // Handle file upload.
        if ( empty( $_FILES['article_file']['tmp_name'] ) ) {
            ob_end_clean();
            wp_send_json_error( array(
                'message' => __( 'Please upload your article file (PDF or PPT).', 'eiu-rp' ),
                'fields'  => array( 'article_file' ),
            ), 422 );
        }

        $uploader    = new File_Upload();
        $file_result = $uploader->upload_article_file( $_FILES['article_file'] );

        if ( is_wp_error( $file_result ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => $file_result->get_error_message() ), 422 );
        }

        $data['file_path'] = $file_result['path'];
        $data['file_name'] = $file_result['name'];
        $data['file_type'] = $file_result['type'];

        // v1.5: Ensure WP media includes are loaded once for all image processing.
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $image_mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );

        /**
         * Upload an image file and return the WP attachment ID.
         * Returns 0 on any failure — never throws.
         */
        $upload_image = static function( array $file ) use ( $image_mimes ): int {
            if ( empty( $file['tmp_name'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
                return 0;
            }
            $upload = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $image_mimes ) );
            if ( isset( $upload['error'] ) || empty( $upload['file'] ) ) {
                return 0;
            }
            $att_id = wp_insert_attachment(
                array(
                    'guid'           => $upload['url'],
                    'post_mime_type' => $upload['type'],
                    'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
                    'post_status'    => 'inherit',
                ),
                $upload['file']
            );
            if ( ! $att_id || is_wp_error( $att_id ) ) {
                return 0;
            }
            wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $upload['file'] ) );
            return (int) $att_id;
        };

        // ── Thumbnail ───────────────────────────────────────────────
        // Priority 1: attachment ID set by AJAX pre-upload (JS wrote it into hidden field)
        // Priority 2: file sent directly with the form (name="article_thumbnail")
        $thumb_att = Security::sanitize_int( $_POST['thumbnail_attachment_id'] ?? 0 );
        if ( ! $thumb_att && ! empty( $_FILES['article_thumbnail']['tmp_name'] ) ) {
            $thumb_att = $upload_image( $_FILES['article_thumbnail'] );
        }
        $data['thumbnail_attachment_id'] = $thumb_att;

        // ── Author photo ────────────────────────────────────────────
        $author_att = Security::sanitize_int( $_POST['author_photo_attachment_id'] ?? 0 );
        if ( ! $author_att && ! empty( $_FILES['author_photo']['tmp_name'] ) ) {
            $author_att = $upload_image( $_FILES['author_photo'] );
        }
        if ( $author_att ) { $data['author_photo_id'] = $author_att; }

        // ── Co-author photo ─────────────────────────────────────────
        $coauthor_att = Security::sanitize_int( $_POST['coauthor_photo_attachment_id'] ?? 0 );
        if ( ! $coauthor_att && ! empty( $_FILES['coauthor_photo']['tmp_name'] ) ) {
            $coauthor_att = $upload_image( $_FILES['coauthor_photo'] );
        }
        if ( $coauthor_att ) { $data['coauthor_photo_id'] = $coauthor_att; }

        // v2.0: Multiple co-authors — sent as co_authors[N][name/email/org/contribution]
        if ( ! empty( $_POST['co_authors'] ) && is_array( $_POST['co_authors'] ) ) {
            $co_authors_clean = array();
            foreach ( $_POST['co_authors'] as $ca ) {
                if ( ! is_array( $ca ) ) continue;
                $entry = array(
                    'name'         => sanitize_text_field( wp_unslash( $ca['name']         ?? '' ) ),
                    'email'        => sanitize_email( wp_unslash( $ca['email']        ?? '' ) ),
                    'org'          => sanitize_text_field( wp_unslash( $ca['org']          ?? '' ) ),
                    'contribution' => sanitize_text_field( wp_unslash( $ca['contribution'] ?? '' ) ),
                );
                // Only include rows where at least name or email is set
                if ( $entry['name'] || $entry['email'] ) {
                    $co_authors_clean[] = $entry;
                }
            }
            if ( ! empty( $co_authors_clean ) ) {
                $data['co_authors_json'] = wp_json_encode( $co_authors_clean );
                // Back-compat: also populate first co-author into legacy columns
                $first = $co_authors_clean[0];
                $data['coauthor_name']  = $first['name'];
                $data['coauthor_email'] = $first['email'];
                $data['coauthor_org']   = $first['org'];
            }
        }

        // v2.0: Ethics file upload
        if ( ! empty( $_FILES['ethics_file']['tmp_name'] ) && $_FILES['ethics_file']['error'] === UPLOAD_ERR_OK ) {
            $allowed_doc_mimes = array(
                'pdf'  => 'application/pdf',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );
            $ethics_upload = wp_handle_upload( $_FILES['ethics_file'], array( 'test_form' => false, 'mimes' => $allowed_doc_mimes ) );
            if ( ! isset( $ethics_upload['error'] ) ) {
                $data['ethics_file_path'] = $ethics_upload['file'];
                $data['ethics_file_name'] = basename( $ethics_upload['file'] );
            }
        }

        // v1.3: Advisers — sent as advisers[] array, join for storage.
        if ( ! empty( $_POST['advisers'] ) && is_array( $_POST['advisers'] ) ) {
            $adv = array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST['advisers'] ) ) );
            $data['advisers'] = implode( '; ', $adv );
        }

        // Create article.
        $article_id = Article::create( $data );

        if ( is_wp_error( $article_id ) ) {
            // Clean up uploaded file.
            $uploader->delete_file( $file_result['path'] );
            ob_end_clean();
            wp_send_json_error( array( 'message' => $article_id->get_error_message() ), 500 );
        }

        // Log the activity.
        Activity_Log::log(
            'article_submitted',
            'article',
            $article_id,
            sprintf( 'Article "%s" submitted by %s (%s)', $data['title'], $data['author_name'], $data['author_email'] )
        );

        // Flush back to exactly the level we were at before handle() opened its
        // own buffer. This drains any stray buffers opened by SMTP plugins inside
        // Article::create() → do_action → wp_mail, without closing WP core's own
        // buffers (which live below $ob_level_before).
        while ( ob_get_level() > $ob_level_before ) {
            ob_end_clean();
        }

        wp_send_json_success( array(
            'message'    => __( 'Your article has been submitted successfully. You will receive a confirmation email shortly.', 'eiu-rp' ),
            'article_id' => $article_id,
        ) );
    }

    /**
     * Handle reviewer email verification via GET parameter.
     */
    public function maybe_handle_verify(): void {
        if ( ! isset( $_GET['eiu_rp_verify'], $_GET['eiu_rp_key'] ) ) {
            return;
        }

        $reviewer_id = absint( $_GET['eiu_rp_verify'] );
        $key         = sanitize_text_field( wp_unslash( $_GET['eiu_rp_key'] ) );

        if ( \EIU_RP\Models\Reviewer::verify( $reviewer_id, $key ) ) {
            Activity_Log::log( 'reviewer_verified', 'reviewer', $reviewer_id, "Reviewer #{$reviewer_id} verified their email." );
            wp_safe_redirect( add_query_arg( 'eiu_rp_verified', '1', home_url() ) );
            exit;
        } else {
            wp_safe_redirect( add_query_arg( 'eiu_rp_verified', '0', home_url() ) );
            exit;
        }
    }

    /**
     * Handle article resubmission by a researcher.
     *
     * Called when a researcher revises and resubmits an article whose status
     * is revision_required. Updates the existing article record in-place:
     *  - Updates all editable fields (title, abstract, references, etc.)
     *  - Resets status to 'pending' so it enters the review queue again
     *  - Increments revision_count (already incremented on status set, but
     *    preserved here for completeness)
     *  - Does NOT overwrite the file unless the researcher uploads a new one
     *  - Fires 'eiu_rp_article_resubmitted' action for extensibility
     *
     * Permissions: must be logged in and must be the original author (matched
     * by email) OR have eiu_manage_articles capability.
     */
    public function resubmit(): void {
        // Security checks.
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        Security::verify_nonce( $nonce, 'eiu_rp_frontend' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'You must be logged in to resubmit an article.', 'eiu-rp' ) ), 401 );
        }

        $article_id = Security::sanitize_int( $_POST['article_id'] ?? 0 );
        if ( ! $article_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid article ID.', 'eiu-rp' ) ) );
        }

        $article = Article::get( $article_id );
        if ( ! $article ) {
            wp_send_json_error( array( 'message' => __( 'Article not found.', 'eiu-rp' ) ) );
        }

        // Verify article is in revision_required status.
        if ( $article->status !== Article::STATUS_REVISION ) {
            wp_send_json_error( array( 'message' => __( 'This article is not awaiting revision.', 'eiu-rp' ) ) );
        }

        // Permission: must be the author or an admin.
        $current_user   = wp_get_current_user();
        $is_admin       = current_user_can( 'eiu_manage_articles' );
        $is_author      = ( strtolower( $current_user->user_email ) === strtolower( $article->author_email ) );

        if ( ! $is_admin && ! $is_author ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to resubmit this article.', 'eiu-rp' ) ), 403 );
        }

        // Rate limit — separate key from initial submission.
        if ( ! Security::check_rate_limit( 'resubmit_' . Security::get_ip() ) ) {
            wp_send_json_error( array( 'message' => __( 'Too many resubmissions. Please try again later.', 'eiu-rp' ) ), 429 );
        }

        // Sanitize updated fields.
        $fields = array(
            'article_title'  => 'text',
            'subject'        => 'text',
            'abstract'       => 'html',
            'author_name'    => 'text',
            'author_org'     => 'text',
            'author_email'   => 'email',
            'coauthor_name'  => 'text',
            'coauthor_org'   => 'text',
            'coauthor_email' => 'email',
            'contact_number' => 'text',
            'country'        => 'text',
            'keywords'       => 'text',
            'disclosures'    => 'text',
            'advisers'       => 'text',
            'summary'        => 'text',
            'references'     => 'html',
        );
        $data = Security::sanitize_post_fields( $fields );
        $data['title'] = $data['article_title'];

        // Optional new file upload.
        $new_file_data = array();
        if ( ! empty( $_FILES['article_file']['tmp_name'] ) && $_FILES['article_file']['error'] === UPLOAD_ERR_OK ) {
            $uploader = new File_Upload();
            $upload   = $uploader->upload_article_file( $_FILES['article_file'], (int) $article->post_id );
            if ( is_wp_error( $upload ) ) {
                wp_send_json_error( array( 'message' => $upload->get_error_message() ) );
            }
            $new_file_data = array(
                'file_path' => $upload['path'],
                'file_name' => $upload['name'],
                'file_type' => $upload['type'],
            );
        }

        // Optional thumbnail replacement.
        if ( ! empty( $_FILES['article_thumbnail']['tmp_name'] ) && $_FILES['article_thumbnail']['error'] === UPLOAD_ERR_OK ) {
            if ( ! function_exists( 'wp_handle_upload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }
            $thumb_mimes  = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
            $thumb_upload = wp_handle_upload( $_FILES['article_thumbnail'], array( 'test_form' => false, 'mimes' => $thumb_mimes ) );
            if ( ! isset( $thumb_upload['error'] ) && ! empty( $thumb_upload['file'] ) ) {
                $thumb_att = wp_insert_attachment(
                    array( 'post_mime_type' => $thumb_upload['type'], 'post_title' => sanitize_file_name( basename( $thumb_upload['file'] ) ), 'post_status' => 'inherit' ),
                    $thumb_upload['file'],
                    (int) $article->post_id
                );
                if ( $thumb_att && ! is_wp_error( $thumb_att ) ) {
                    wp_update_attachment_metadata( $thumb_att, wp_generate_attachment_metadata( $thumb_att, $thumb_upload['file'] ) );
                    set_post_thumbnail( (int) $article->post_id, $thumb_att );
                    update_post_meta( (int) $article->post_id, '_eiu_thumbnail_attachment_id', $thumb_att );
                    $data['thumbnail_attachment_id'] = $thumb_att;
                }
            }
        }

        // Update the article record.
        $result = Article::update( $article_id, array_merge( $data, $new_file_data ) );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Reset status to pending — article re-enters the review queue.
        // revision_notes are NOT cleared so the history is preserved.
        Article::update_status( $article_id, Article::STATUS_PENDING );

        Activity_Log::log(
            'article_resubmitted',
            'article',
            $article_id,
            sprintf( 'Article #%d resubmitted by user %s after revision.', $article_id, $current_user->user_email )
        );

        do_action( 'eiu_rp_article_resubmitted', $article_id, $article );

        wp_send_json_success( array(
            'message'    => __( 'Your revised article has been resubmitted successfully. You will be notified when it is reviewed.', 'eiu-rp' ),
            'article_id' => $article_id,
        ) );
    }
}
