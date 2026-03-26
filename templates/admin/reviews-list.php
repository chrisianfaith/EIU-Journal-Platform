<?php
/**
 * Admin Reviews List Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Models\Review;
use EIU_RP\Utils\Helpers;
?>
<div class="wrap eiu-rp-admin">
  <h1 class="wp-heading-inline">
    <span class="dashicons dashicons-edit-page"></span>
    <?php esc_html_e( 'Reviews', 'eiu-rp' ); ?>
    <span class="eiu-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
  </h1>
  <hr class="wp-header-end">

  <?php if ( empty( $items ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-edit-page" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No reviews found.', 'eiu-rp' ); ?></p>
    </div>
  <?php else: ?>
    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php esc_html_e( 'Article', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Reviewer', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Status', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Recommendation', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Due', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Submitted', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $items as $row ): ?>
          <tr>
            <td><?php echo esc_html( $row['id'] ); ?></td>
            <td><?php echo esc_html( wp_trim_words( $row['article_title'] ?? '—', 8 ) ); ?></td>
            <td><?php echo esc_html( $row['reviewer_name'] ); ?></td>
            <td><span class="eiu-rp-badge status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $row['status'] ) ) ); ?></span></td>
            <td><?php echo $row['recommendation'] ? esc_html( Review::recommendation_label( $row['recommendation'] ) ) : '&mdash;'; ?></td>
            <td><?php echo $row['due_date'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row['due_date'] ) ) ) : '&mdash;'; ?></td>
            <td><?php echo $row['submitted_at'] ? esc_html( Helpers::time_ago( $row['submitted_at'] ) ) : '&mdash;'; ?></td>
            <td>
              <?php if ( $row['status'] === 'submitted' ): ?>
                <button class="button button-small eiu-btn-approve-review" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Approve', 'eiu-rp' ); ?></button>
                <button class="button button-small eiu-btn-reject-review" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Reject', 'eiu-rp' ); ?></button>
              <?php endif; ?>
              <button class="button button-small button-link-delete eiu-btn-delete-review" data-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Delete', 'eiu-rp' ); ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo Helpers::pagination_links( $total, $per_page, $page, admin_url( 'admin.php?page=eiu-rp-reviews' ) ); // phpcs:ignore ?>
  <?php endif; ?>
</div>
