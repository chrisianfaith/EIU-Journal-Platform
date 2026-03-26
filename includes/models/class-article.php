<?php
/**
 * Article Model.
 *
 * @package EIU_Research_Publication
 * @subpackage Models
 */

namespace EIU_RP\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Article
 *
 * CRUD operations for the eiu_articles table plus WP post integration.
 */
class Article {

    /** Article statuses. */
    const STATUS_PENDING    = 'pending';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED   = 'approved';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_PUBLISHED  = 'published';
    const STATUS_REVISION   = 'revision_required';

    /**
     * Create a new article record (DB row + WP post).
     *
     * @param array $data Sanitized article data.
     * @return int|WP_Error New article ID or error.
     */
    public static function create( array $data ) {
        global $wpdb;

        // Create the WP post first.
        $post_id = wp_insert_post( array(
            'post_title'     => $data['title'],
            'post_content'   => $data['abstract'],
            'post_status'    => 'pending',
            'post_type'      => 'eiu_article',
            'post_author'    => 0,
            'comment_status' => 'open',   // Always allow comments on articles
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Assign subject taxonomy if provided.
        if ( ! empty( $data['subject'] ) ) {
            wp_set_object_terms( $post_id, sanitize_text_field( $data['subject'] ), 'eiu_subject' );
        }

        // Insert extended data row.
        $result = $wpdb->insert(
            $wpdb->prefix . 'eiu_articles',
            array(
                'post_id'          => $post_id,
                'author_name'      => $data['author_name']      ?? '',
                'author_email'     => $data['author_email']     ?? '',
                'author_user_id'   => $data['author_user_id']   ?? 0,
                'author_org'       => $data['author_org']       ?? '',
                'coauthor_name'  => $data['coauthor_name']  ?? '',
                'coauthor_email' => $data['coauthor_email'] ?? '',
                'coauthor_org'   => $data['coauthor_org']   ?? '',
                'contact_number' => $data['contact_number'] ?? '',
                'country'       => $data['country']       ?? '',
                'doi'           => $data['doi']           ?? '',
                'issn'          => $data['issn']          ?? '',
                'file_path'     => $data['file_path']     ?? '',
                'file_name'     => $data['file_name']     ?? '',
                'file_type'     => $data['file_type']     ?? '',
                'status'        => self::STATUS_PENDING,
                'submitted_ip'  => \EIU_RP\Security\Security::get_ip(),
                'submitted_at'  => current_time( 'mysql' ),
            ),
            array( '%d','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' )
        );

        if ( ! $result ) {
            wp_delete_post( $post_id, true );
            return new \WP_Error( 'db_insert_failed', __( 'Could not save article data.', 'eiu-rp' ) );
        }

        $article_id = $wpdb->insert_id;

        // FIX v1.1: Set post thumbnail if thumbnail_attachment_id was passed.
        if ( ! empty( $data['thumbnail_attachment_id'] ) ) {
            set_post_thumbnail( $post_id, absint( $data['thumbnail_attachment_id'] ) );
        }

        // FIX v1.1: Store abstract as post_meta for clean retrieval (avoids double display).
        if ( ! empty( $data['abstract'] ) ) {
            update_post_meta( $post_id, '_eiu_abstract', $data['abstract'] );
        }

        // v1.3: Save author/coauthor photo attachment IDs as post meta.
        if ( ! empty( $data["author_photo_id"] ) ) {
            update_post_meta( $post_id, '_eiu_author_photo_id', absint( $data['author_photo_id'] ) );
        }
        if ( ! empty( $data["coauthor_photo_id"] ) ) {
            update_post_meta( $post_id, '_eiu_coauthor_photo_id', absint( $data['coauthor_photo_id'] ) );
        }

        // v1.2: Save extended fields as post meta (works before DB migration runs)
        foreach ( array( 'keywords', 'disclosures', 'advisers', 'summary' ) as $ef ) {
            if ( ! empty( $data[ $ef ] ) ) {
                update_post_meta( $post_id, '_eiu_' . $ef, sanitize_text_field( $data[ $ef ] ) );
            }
        }
        if ( ! empty( $data['references'] ) ) {
            update_post_meta( $post_id, '_eiu_references', wp_kses_post( $data['references'] ) );
        }
        // v2.2: Save author_affiliation to post_meta as fallback for pre-migration sites.
        if ( ! empty( $data['author_affiliation'] ) ) {
            update_post_meta( $post_id, '_eiu_author_affiliation', wp_kses_post( $data['author_affiliation'] ) );
        }

        // v2.2: author_affiliation — store in post_meta as fallback;
        // also write to DB column if it exists.
        if ( ! empty( $data['author_affiliation'] ) ) {
            $clean_affil = wp_kses_post( $data['author_affiliation'] );
            update_post_meta( $post_id, '_eiu_author_affiliation', $clean_affil );
            $col_exists = $wpdb->get_results( // phpcs:ignore
                "SHOW COLUMNS FROM `{$wpdb->prefix}eiu_articles` LIKE 'author_affiliation'"
            );
            if ( ! empty( $col_exists ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'eiu_articles',
                    array( 'author_affiliation' => $clean_affil ),
                    array( 'id' => $wpdb->insert_id ),
                    array( '%s' ), array( '%d' )
                );
            }
        }

        // Fire the created action in its own isolated buffer.
        // on_article_submitted() sends wp_mail() to admin + author + all reviewers.
        // SMTP plugins may call ob_start() inside wp_mail — wrapping here prevents
        // those stray buffers from escaping into the caller's buffer stack.
        ob_start();
        do_action( 'eiu_rp_article_created', $article_id, $post_id, $data );
        ob_end_clean();

        return $article_id;
    }

    /**
     * Get a single article by its row ID.
     *
     * @param int $id Article row ID.
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, p.post_title as title, p.post_content as abstract
                 FROM {$wpdb->prefix}eiu_articles a
                 LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
                 WHERE a.id = %d",
                $id
            )
        );
        // v2.2: If author_affiliation column doesn't exist yet (pre-migration),
        // fall back to post_meta so the field still renders everywhere.
        if ( $row && ! isset( $row->author_affiliation ) ) {
            $post_id = (int) ( $row->post_id ?? 0 );
            $row->author_affiliation = $post_id
                ? ( get_post_meta( $post_id, '_eiu_author_affiliation', true ) ?: '' )
                : '';
        }
        return $row;
    }

    /**
     * Get article by its associated post ID.
     *
     * @param int $post_id WP post ID.
     * @return object|null
     */
    public static function get_by_post( int $post_id ): ?object {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.*, p.post_title as title, p.post_content as abstract
                 FROM {$wpdb->prefix}eiu_articles a
                 LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
                 WHERE a.post_id = %d",
                $post_id
            )
        );
        // v2.2: If author_affiliation column doesn't exist yet (pre-migration),
        // OR if it exists but is empty, fall back to post_meta.
        if ( $row ) {
            if ( ! isset( $row->author_affiliation ) || $row->author_affiliation === '' ) {
                $meta_affil = get_post_meta( $post_id, '_eiu_author_affiliation', true );
                if ( $meta_affil ) {
                    $row->author_affiliation = $meta_affil;
                } elseif ( ! isset( $row->author_affiliation ) ) {
                    $row->author_affiliation = '';
                }
            }
        }
        return $row;
    }

    /**
     * Update article status.
     *
     * @param int    $id     Article row ID.
     * @param string $status New status.
     * @return bool
     */
    /**
     * Update article status.
     *
     * @param int    $id             Article row ID.
     * @param string $status         New status slug.
     * @param string $revision_notes Optional reviewer feedback (only stored for revision_required).
     * @return bool
     */
    public static function update_status( int $id, string $status, string $revision_notes = '', string $published_at = '' ): bool {
        global $wpdb;

        $valid_statuses = array(
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_PUBLISHED,
            self::STATUS_REVISION,
        );

        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }

        $update_data = array(
            'status'     => $status,
            'updated_at' => current_time( 'mysql' ),
        );
        $update_fmt  = array( '%s', '%s' );

        // Store revision notes when setting revision_required status.
        if ( $status === self::STATUS_REVISION && $revision_notes !== '' ) {
            $update_data['revision_notes'] = $revision_notes;
            $update_fmt[]                  = '%s';
            // Increment revision count
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}eiu_articles SET revision_count = revision_count + 1 WHERE id = %d",
                $id
            ) );
        }

        // Resolve published_at date (used for backdating + WP post_date).
        $resolved_dt = '';
        if ( $status === self::STATUS_PUBLISHED ) {
            if ( $published_at !== '' ) {
                $clean_dt = sanitize_text_field( $published_at );
                if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $clean_dt ) ) {
                    $clean_dt .= ' 00:00:00';
                }
                if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $clean_dt ) ) {
                    $resolved_dt = $clean_dt;
                }
            }
            if ( $resolved_dt === '' ) {
                $resolved_dt = current_time( 'mysql' );
            }
        }

        // Main status update — only the core columns that always exist.
        // published_at is handled separately below so a missing column
        // (pre-migration site) never causes this critical update to fail.
        $result = $wpdb->update(
            $wpdb->prefix . 'eiu_articles',
            $update_data,
            array( 'id' => $id ),
            $update_fmt,
            array( '%d' )
        );

        // Best-effort: write published_at separately so a missing column
        // never blocks publishing. Suppress errors — column may not exist yet.
        if ( $resolved_dt !== '' ) {
            $col_exists = $wpdb->get_results( // phpcs:ignore
                "SHOW COLUMNS FROM `{$wpdb->prefix}eiu_articles` LIKE 'published_at'"
            );
            if ( ! empty( $col_exists ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'eiu_articles',
                    array( 'published_at' => $resolved_dt ),
                    array( 'id' => $id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }

        // $result is false on SQL error, 0 on no-change, positive int on update.
        // Treat 0 as success (status was already the same value — idempotent).
        $ok = ( $result !== false );

        if ( $ok ) {
            // Sync WP post status.
            $article = self::get( $id );
            if ( $article && $article->post_id ) {
                $wp_status = $status === self::STATUS_PUBLISHED ? 'publish' : ( $status === self::STATUS_REJECTED ? 'trash' : 'pending' );
                $wp_post_args = array(
                    'ID'             => (int) $article->post_id,
                    'post_status'    => $wp_status,
                    'comment_status' => 'open',
                );
                if ( $wp_status === 'publish' && $resolved_dt !== '' ) {
                    $wp_post_args['post_date']     = $resolved_dt;
                    $wp_post_args['post_date_gmt'] = get_gmt_from_date( $resolved_dt );
                }
                wp_update_post( $wp_post_args );
            }
            do_action( 'eiu_rp_article_status_changed', $id, $status );
            if ( $status === self::STATUS_REVISION ) {
                do_action( 'eiu_rp_revision_required', $id, $revision_notes );
            }
        }

        return $ok;
    }

    /**
     * Query articles with filtering and pagination.
     *
     * @param array $args Query arguments.
     * @return array { 'items' => array, 'total' => int }
     */
    public static function query( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'status'   => '',
            'subject'  => '',
            'search'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'submitted_at',
            'order'    => 'DESC',
        );

        $args    = wp_parse_args( $args, $defaults );
        $table   = $wpdb->prefix . 'eiu_articles';
        $where   = array( '1=1' );

        if ( ! empty( $args['status'] ) ) {
            $where[] = $wpdb->prepare( 'a.status = %s', $args['status'] );
        }
        if ( ! empty( $args['search'] ) ) {
            $like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(p.post_title LIKE %s OR a.author_name LIKE %s OR a.author_email LIKE %s)', $like, $like, $like );
        }

        $where_sql = implode( ' AND ', $where );
        $allowed_orderby = array( 'submitted_at', 'updated_at', 'status', 'author_name' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? 'a.' . $args['orderby'] : 'a.submitted_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $offset  = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $limit   = absint( $args['per_page'] );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} a LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID WHERE {$where_sql}"
        );
        $items = $wpdb->get_results(
            "SELECT a.*, p.post_title as title FROM {$table} a LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}",
            ARRAY_A
        );
        // phpcs:enable

        return array(
            'items' => $items ?: array(),
            'total' => $total,
        );
    }

    /**
     * Delete an article and its associated post and file.
     *
     * @param int $id Article row ID.
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;

        $article = self::get( $id );
        if ( ! $article ) {
            return false;
        }

        // Delete file.
        if ( ! empty( $article->file_path ) ) {
            $uploader = new \EIU_RP\Security\File_Upload();
            $uploader->delete_file( $article->file_path );
        }

        // Delete WP post.
        if ( ! empty( $article->post_id ) ) {
            wp_delete_post( (int) $article->post_id, true );
        }

        // Delete DB row.
        $result = $wpdb->delete(
            $wpdb->prefix . 'eiu_articles',
            array( 'id' => $id ),
            array( '%d' )
        );

        return $result !== false;
    }


    /**
     * Update an existing article's metadata (v1.2).
     * Admin / Reviewer can edit title, abstract, ISSN, DOI, content, author photo.
     *
     * @param int   $id   Article row ID.
     * @param array $data Sanitized update fields.
     * @return bool|WP_Error
     */
    public static function update( int $id, array $data ) {
        global $wpdb;

        $article = self::get( $id );
        if ( ! $article ) {
            return new \WP_Error( 'not_found', __( 'Article not found.', 'eiu-rp' ) );
        }

        $post_id = (int) $article->post_id;

        // --- Update WP post (title + content) ---
        $post_update = array( 'ID' => $post_id );
        if ( isset( $data['title'] ) && $data['title'] !== '' ) {
            $post_update['post_title'] = sanitize_text_field( $data['title'] );
        }
        if ( isset( $data['abstract'] ) ) {
            $post_update['post_content'] = wp_kses_post( $data['abstract'] );
            update_post_meta( $post_id, '_eiu_abstract', wp_kses_post( $data['abstract'] ) );
        }
        if ( isset( $data['article_content'] ) ) {
            update_post_meta( $post_id, '_eiu_article_content', wp_kses_post( $data['article_content'] ) );
        }
        if ( count( $post_update ) > 1 ) {
            wp_update_post( $post_update );
        }

        // --- Update taxonomy ---
        if ( ! empty( $data['subject'] ) ) {
            wp_set_object_terms( $post_id, sanitize_text_field( $data['subject'] ), 'eiu_subject' );
        }

        // --- Update eiu_articles row ---
        $row = array();
        $fmt = array();
        $allowed = array(
            'author_name'    => '%s',
            'author_email'   => '%s',
            'author_org'     => '%s',
            'coauthor_name'  => '%s',
            'coauthor_email' => '%s',
            'coauthor_org'   => '%s',
            'contact_number' => '%s',
            'country'        => '%s',
            'doi'            => '%s',
            'issn'           => '%s',
            'keywords'       => '%s',
            'disclosures'    => '%s',
            // 'references' handled below via wp_kses_post
        );
        foreach ( $allowed as $field => $f ) {
            if ( array_key_exists( $field, $data ) ) {
                $row[ $field ] = sanitize_text_field( $data[ $field ] );
                $fmt[]         = $f;
            }
        }
        $row['updated_at'] = current_time( 'mysql' );
        $fmt[]             = '%s';

        if ( count( $row ) > 1 ) {
            $wpdb->update(
                $wpdb->prefix . 'eiu_articles',
                $row,
                array( 'id' => $id ),
                $fmt,
                array( '%d' )
            );
        }

        // --- Author affiliation (rich HTML — store via post meta + DB row) ---
        if ( isset( $data['author_affiliation'] ) ) {
            $clean_affil = wp_kses_post( $data['author_affiliation'] );
            update_post_meta( $post_id, '_eiu_author_affiliation', $clean_affil );
            // Also write to DB column if it exists (post-migration).
            $affil_col = $wpdb->get_results( // phpcs:ignore
                "SHOW COLUMNS FROM `{$wpdb->prefix}eiu_articles` LIKE 'author_affiliation'"
            );
            if ( ! empty( $affil_col ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'eiu_articles',
                    array( 'author_affiliation' => $clean_affil, 'updated_at' => current_time( 'mysql' ) ),
                    array( 'id' => $id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
        }

        // --- References (rich HTML — store via post meta, also in DB row if column exists) ---
        if ( isset( $data['references'] ) ) {
            $clean_refs = wp_kses_post( $data['references'] );
            update_post_meta( $post_id, '_eiu_references', $clean_refs );
            // Also update DB column (available after v1.2 migration)
            $wpdb->update(
                $wpdb->prefix . 'eiu_articles',
                array( 'references' => $clean_refs, 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => $id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }

        // --- v2.2: Author affiliation (rich HTML) ---
        // Always save to post_meta so get_by_post() fallback works on pre-migration sites.
        // Also write to DB column when it exists.
        if ( isset( $data['author_affiliation'] ) ) {
            $clean_affil = wp_kses_post( $data['author_affiliation'] );
            // post_meta: reliable fallback — always written
            update_post_meta( $post_id, '_eiu_author_affiliation', $clean_affil );
            // DB column: only when it exists (safe for pre-migration installs)
            $col_exists = $wpdb->get_results( // phpcs:ignore
                "SHOW COLUMNS FROM `{$wpdb->prefix}eiu_articles` LIKE 'author_affiliation'"
            );
            if ( ! empty( $col_exists ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'eiu_articles',
                    array( 'author_affiliation' => $clean_affil, 'updated_at' => current_time( 'mysql' ) ),
                    array( 'id' => $id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
        }

        // --- Author photo ---
        if ( ! empty( $data['author_photo_id'] ) ) {
            update_post_meta( $post_id, '_eiu_author_photo_id', absint( $data['author_photo_id'] ) );
        }
        if ( ! empty( $data['coauthor_photo_id'] ) ) {
            update_post_meta( $post_id, '_eiu_coauthor_photo_id', absint( $data['coauthor_photo_id'] ) );
        }

        // --- Thumbnail ---
        if ( ! empty( $data['thumbnail_attachment_id'] ) ) {
            set_post_thumbnail( $post_id, absint( $data['thumbnail_attachment_id'] ) );
        }

        \EIU_RP\Models\Activity_Log::log(
            'article_updated', 'article', $id,
            sprintf( 'Article #%d updated by user #%d', $id, get_current_user_id() )
        );

        do_action( 'eiu_rp_article_updated', $id, $post_id, $data );

        return true;
    }

    /**
     * Return a human-readable status label.
     *
     * @param string $status Status slug.
     * @return string
     */
    public static function status_label( string $status ): string {
        $labels = array(
            self::STATUS_PENDING      => __( 'Pending', 'eiu-rp' ),
            self::STATUS_UNDER_REVIEW => __( 'Under Review', 'eiu-rp' ),
            self::STATUS_APPROVED     => __( 'Approved', 'eiu-rp' ),
            self::STATUS_REJECTED     => __( 'Rejected', 'eiu-rp' ),
            self::STATUS_PUBLISHED    => __( 'Published', 'eiu-rp' ),
            self::STATUS_REVISION     => __( 'Revision Required', 'eiu-rp' ),
        );
        return $labels[ $status ] ?? ucfirst( $status );
    }
}
