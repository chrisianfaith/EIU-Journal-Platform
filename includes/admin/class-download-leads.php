<?php
/**
 * Admin: Download Leads — view and export emails captured during article downloads.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Download_Leads {

    public function __construct() {
        // Handle CSV export before any output
        add_action( 'admin_init', array( $this, 'maybe_export_csv' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'eiu_manage_articles' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'eiu_download_leads';
        $per_page = 30;
        $page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        // Optional: filter by article or search by email
        $search     = sanitize_text_field( $_GET['s'] ?? '' );
        $where      = '1=1';
        $where_vals = array();

        if ( $search ) {
            $where       .= ' AND dl.email LIKE %s';
            $where_vals[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var(
            $where_vals
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} dl WHERE {$where}", ...$where_vals )
                : "SELECT COUNT(*) FROM {$table} dl WHERE {$where}"
        );

        $rows = $wpdb->get_results(
            $where_vals
                ? $wpdb->prepare(
                    "SELECT dl.*, p.post_title as article_title
                     FROM {$table} dl
                     LEFT JOIN {$wpdb->posts} p
                       ON p.ID = (SELECT post_id FROM {$wpdb->prefix}eiu_articles WHERE id = dl.article_id LIMIT 1)
                     WHERE {$where}
                     ORDER BY dl.requested_at DESC
                     LIMIT %d OFFSET %d",
                    ...[...$where_vals, $per_page, $offset]
                  )
                : $wpdb->prepare(
                    "SELECT dl.*, p.post_title as article_title
                     FROM {$table} dl
                     LEFT JOIN {$wpdb->posts} p
                       ON p.ID = (SELECT post_id FROM {$wpdb->prefix}eiu_articles WHERE id = dl.article_id LIMIT 1)
                     WHERE {$where}
                     ORDER BY dl.requested_at DESC
                     LIMIT %d OFFSET %d",
                    $per_page, $offset
                  )
        ) ?: array();
        // phpcs:enable

        $total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
        $export_url  = wp_nonce_url(
            add_query_arg( array(
                'page'          => 'eiu-rp-download-leads',
                'action'        => 'export_csv',
                's'             => $search,
            ), admin_url( 'admin.php' ) ),
            'eiu_rp_export_leads'
        );

        \EIU_RP\Utils\Template_Loader::get_template( 'admin/download-leads.php', compact(
            'rows', 'total', 'total_pages', 'page', 'per_page', 'search', 'export_url'
        ) );
    }

    /**
     * If admin requests CSV export, stream the file and exit.
     */
    public function maybe_export_csv(): void {
        if (
            ! isset( $_GET['page'], $_GET['action'] ) ||
            $_GET['page'] !== 'eiu-rp-download-leads' ||
            $_GET['action'] !== 'export_csv'
        ) {
            return;
        }

        if ( ! current_user_can( 'eiu_manage_articles' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }

        check_admin_referer( 'eiu_rp_export_leads' );

        global $wpdb;
        $table  = $wpdb->prefix . 'eiu_download_leads';
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $where  = '1=1';
        $args   = array();

        if ( $search ) {
            $where  .= ' AND dl.email LIKE %s';
            $args[]  = '%' . $wpdb->esc_like( $search ) . '%';
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $args
                ? $wpdb->prepare(
                    "SELECT dl.id, dl.article_id, dl.email, dl.requested_at, dl.ip,
                            (SELECT p.post_title FROM {$wpdb->posts} p
                             INNER JOIN {$wpdb->prefix}eiu_articles a ON a.post_id = p.ID
                             WHERE a.id = dl.article_id LIMIT 1) as article_title
                     FROM {$table} dl WHERE {$where} ORDER BY dl.requested_at DESC",
                    ...$args
                  )
                : "SELECT dl.id, dl.article_id, dl.email, dl.requested_at, dl.ip,
                          (SELECT p.post_title FROM {$wpdb->posts} p
                           INNER JOIN {$wpdb->prefix}eiu_articles a ON a.post_id = p.ID
                           WHERE a.id = dl.article_id LIMIT 1) as article_title
                   FROM {$table} dl WHERE {$where} ORDER BY dl.requested_at DESC"
        ) ?: array();
        // phpcs:enable

        $filename = 'eiu-download-leads-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM so Excel opens it correctly
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'ID', 'Article Title', 'Article ID', 'Email', 'Downloaded At', 'IP Address' ) );

        foreach ( $rows as $row ) {
            fputcsv( $out, array(
                $row->id,
                $row->article_title ?? '',
                $row->article_id,
                $row->email,
                $row->requested_at,
                $row->ip,
            ) );
        }
        fclose( $out );
        exit;
    }
}
