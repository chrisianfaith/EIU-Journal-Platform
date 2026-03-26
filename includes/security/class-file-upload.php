<?php
/**
 * Secure File Upload Handler.
 *
 * @package EIU_Research_Publication
 * @subpackage Security
 */

namespace EIU_RP\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class File_Upload
 */
class File_Upload {

    /**
     * Allowed MIME types mapped to their extensions.
     *
     * @var array
     */
    private array $allowed_mimes = array(
        'pdf'  => 'application/pdf',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    );

    /**
     * Upload a submitted article file securely.
     *
     * @param array  $file   $_FILES element.
     * @param int    $post_id Associated post ID.
     * @return array|WP_Error Array with 'path', 'url', 'name', 'type' or WP_Error.
     */
    public function upload_article_file( array $file, int $post_id = 0 ) {
        // Basic presence check.
        if ( empty( $file['tmp_name'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error( 'upload_error', __( 'File upload failed. Please try again.', 'eiu-rp' ) );
        }

        // Use the admin-configured max file size (default 20 MB).
        $max_mb    = max( 1, (int) get_option( 'eiu_rp_max_file_size_mb', 20 ) );
        $max_bytes = $max_mb * MB_IN_BYTES;
        if ( $file['size'] > $max_bytes ) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %d: Maximum file size in megabytes */
                    __( 'File exceeds the maximum allowed size of %d MB. Please compress it first.', 'eiu-rp' ),
                    $max_mb
                )
            );
        }

        // Extension check.
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! isset( $this->allowed_mimes[ $ext ] ) ) {
            return new \WP_Error( 'invalid_type', __( 'Invalid file type. Accepted formats: PDF, PPT, PPTX.', 'eiu-rp' ) );
        }

        // MIME type verification (server-side, not relying on client claim).
        $finfo     = finfo_open( FILEINFO_MIME_TYPE );
        $real_mime = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        // PDF can also be detected as application/octet-stream in some environments.
        $valid_mimes = array_values( $this->allowed_mimes );
        $valid_mimes[] = 'application/octet-stream'; // Fallback for restricted servers.
        if ( ! in_array( $real_mime, $valid_mimes, true ) ) {
            return new \WP_Error( 'mime_mismatch', __( 'File content does not match the declared file type.', 'eiu-rp' ) );
        }

        // Build upload directory.
        $upload_dir = $this->get_upload_dir();
        if ( is_wp_error( $upload_dir ) ) {
            return $upload_dir;
        }

        // Generate a safe, randomized filename.
        $safe_name = $this->generate_safe_filename( $file['name'], $ext );
        $dest_path = trailingslashit( $upload_dir['path'] ) . $safe_name;
        $dest_url  = trailingslashit( $upload_dir['url'] ) . $safe_name;

        // Move file.
        if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
            return new \WP_Error( 'move_failed', __( 'Could not save the uploaded file.', 'eiu-rp' ) );
        }

        // Protect directory with .htaccess (serve via WP only).
        $this->protect_directory( $upload_dir['path'] );

        return array(
            'path' => $dest_path,
            'url'  => $dest_url,
            'name' => $file['name'],
            'type' => $ext,
            'size' => $file['size'],
        );
    }

    /**
     * Get or create the article upload directory.
     *
     * @return array|WP_Error
     */
    private function get_upload_dir() {
        $uploads   = wp_upload_dir();
        $base_path = trailingslashit( $uploads['basedir'] ) . 'eiu-articles';
        $base_url  = trailingslashit( $uploads['baseurl'] ) . 'eiu-articles';

        // Sub-directory by year-month.
        $sub       = date( 'Y/m' );
        $full_path = $base_path . '/' . $sub;
        $full_url  = $base_url . '/' . $sub;

        if ( ! wp_mkdir_p( $full_path ) ) {
            return new \WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', 'eiu-rp' ) );
        }

        return array(
            'path' => $full_path,
            'url'  => $full_url,
        );
    }

    /**
     * Generate a safe, non-guessable filename.
     *
     * @param string $original Original filename.
     * @param string $ext      Sanitized extension.
     * @return string
     */
    private function generate_safe_filename( string $original, string $ext ): string {
        $base     = sanitize_file_name( pathinfo( $original, PATHINFO_FILENAME ) );
        $base     = substr( $base, 0, 50 ); // Limit length.
        $random   = wp_generate_password( 16, false );
        $ts       = time();
        return "{$ts}_{$random}_{$base}.{$ext}";
    }

    /**
     * Write an .htaccess to prevent direct access to upload directory.
     *
     * @param string $dir Directory path.
     */
    private function protect_directory( string $dir ): void {
        $htaccess = trailingslashit( dirname( $dir ) ) . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $content = "Options -Indexes\n";
            $content .= "Order deny,allow\n";
            $content .= "Deny from all\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $htaccess, $content );
        }
    }

    /**
     * Delete an uploaded file by its path.
     *
     * @param string $path Absolute file path.
     * @return bool
     */
    public function delete_file( string $path ): bool {
        if ( ! $path || ! file_exists( $path ) ) {
            return false;
        }
        // Ensure the file is within the uploads directory.
        $uploads = wp_upload_dir();
        if ( strpos( $path, $uploads['basedir'] ) !== 0 ) {
            return false;
        }
        return unlink( $path );
    }
}
