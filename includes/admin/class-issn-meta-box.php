<?php
/**
 * ISSN Meta Box — Admin & Reviewer can edit ISSN on article edit screen.
 *
 * FIX v1.1: Task 7 — backend ISSN editing.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */

namespace EIU_RP\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Class ISSN_Meta_Box
 */
class ISSN_Meta_Box {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register' ) );
        add_action( 'save_post_eiu_article', array( $this, 'save' ), 10, 2 );
    }

    /**
     * Register the meta box — visible to admins and eiu_reviewers only.
     */
    public function register(): void {
        if ( ! $this->current_user_can() ) {
            return;
        }

        add_meta_box(
            'eiu_rp_issn_meta',
            __( 'Publication Details (ISSN / DOI)', 'eiu-rp' ),
            array( $this, 'render' ),
            'eiu_article',
            'side',
            'high'
        );
    }

    /**
     * Render meta box HTML.
     *
     * @param \WP_Post $post Current post.
     */
    public function render( \WP_Post $post ): void {
        wp_nonce_field( 'eiu_rp_issn_save_' . $post->ID, 'eiu_rp_issn_nonce' );

        global $wpdb;
        $article = \EIU_RP\Models\Article::get_by_post( $post->ID );

        $issn = $article ? esc_attr( $article->issn ?? '' ) : '';
        $doi  = $article ? esc_attr( $article->doi  ?? '' ) : '';
        ?>
        <div style="display:flex;flex-direction:column;gap:10px;padding:4px 0;">

          <div>
            <label for="eiu_rp_issn_field" style="display:block;font-weight:600;font-size:12px;color:#3c434a;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">
              <?php esc_html_e( 'ISSN Number', 'eiu-rp' ); ?>
            </label>
            <input type="text" id="eiu_rp_issn_field" name="eiu_rp_issn"
              value="<?php echo $issn; ?>"
              placeholder="XXXX-XXXX"
              class="widefat"
              style="border-radius:4px;font-size:13px;">
            <p style="margin:4px 0 0;font-size:11px;color:#757575;">
              <?php esc_html_e( 'International Standard Serial Number', 'eiu-rp' ); ?>
            </p>
          </div>

          <div>
            <label for="eiu_rp_doi_field" style="display:block;font-weight:600;font-size:12px;color:#3c434a;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">
              <?php esc_html_e( 'DOI', 'eiu-rp' ); ?>
            </label>
            <input type="text" id="eiu_rp_doi_field" name="eiu_rp_doi"
              value="<?php echo $doi; ?>"
              placeholder="10.xxxx/xxxxx"
              class="widefat"
              style="border-radius:4px;font-size:13px;">
            <p style="margin:4px 0 0;font-size:11px;color:#757575;">
              <?php esc_html_e( 'Digital Object Identifier', 'eiu-rp' ); ?>
            </p>
          </div>

          <?php if ( $article ): ?>
            <div style="background:#f0f4ff;border:1px solid #c7d5f5;border-radius:4px;padding:8px 10px;font-size:11px;color:#374151;">
              <strong><?php esc_html_e( 'Ref:', 'eiu-rp' ); ?></strong> #<?php echo esc_html( $article->id ); ?> &nbsp;|&nbsp;
              <strong><?php esc_html_e( 'Status:', 'eiu-rp' ); ?></strong>
              <?php echo esc_html( \EIU_RP\Models\Article::status_label( $article->status ) ); ?>
            </div>
          <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Save ISSN + DOI when post is saved.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function save( int $post_id, \WP_Post $post ): void {
        // Autosave / revisions guard.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( wp_is_post_revision( $post_id ) ) { return; }

        // Nonce verification.
        $nonce = isset( $_POST['eiu_rp_issn_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['eiu_rp_issn_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_issn_save_' . $post_id ) ) { return; }

        if ( ! $this->current_user_can() ) { return; }

        $issn = isset( $_POST['eiu_rp_issn'] ) ? sanitize_text_field( wp_unslash( $_POST['eiu_rp_issn'] ) ) : '';
        $doi  = isset( $_POST['eiu_rp_doi']  ) ? sanitize_text_field( wp_unslash( $_POST['eiu_rp_doi']  ) ) : '';

        global $wpdb;
        $article = \EIU_RP\Models\Article::get_by_post( $post_id );
        if ( ! $article ) { return; }

        $wpdb->update(
            $wpdb->prefix . 'eiu_articles',
            array( 'issn' => $issn, 'doi' => $doi, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $article->id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        // Also store as post_meta for fast retrieval.
        update_post_meta( $post_id, '_eiu_issn', $issn );
        update_post_meta( $post_id, '_eiu_doi',  $doi );

        \EIU_RP\Models\Activity_Log::log(
            'issn_updated', 'article', $article->id,
            sprintf( 'ISSN updated to "%s" / DOI to "%s" for article #%d', $issn, $doi, $article->id )
        );
    }

    /**
     * Check whether current user can edit publication details.
     *
     * @return bool
     */
    private function current_user_can(): bool {
        return current_user_can( 'eiu_manage_articles' ) || current_user_can( 'eiu_review_articles' );
    }
}
