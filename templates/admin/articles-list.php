<?php
/**
 * Admin Articles List Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Article;
use EIU_RP\Utils\Helpers;

$statuses = array(
    ''                              => __( 'All', 'eiu-rp' ),
    Article::STATUS_PENDING         => __( 'Pending', 'eiu-rp' ),
    Article::STATUS_UNDER_REVIEW    => __( 'Under Review', 'eiu-rp' ),
    Article::STATUS_APPROVED        => __( 'Approved', 'eiu-rp' ),
    Article::STATUS_REJECTED        => __( 'Rejected', 'eiu-rp' ),
    Article::STATUS_PUBLISHED       => __( 'Published', 'eiu-rp' ),
    Article::STATUS_REVISION        => __( 'Revision Required', 'eiu-rp' ),
);
?>
<div class="wrap eiu-rp-admin">
  <h1 class="wp-heading-inline">
    <span class="dashicons dashicons-media-document"></span>
    <?php esc_html_e( 'Articles', 'eiu-rp' ); ?>
    <span class="eiu-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
  </h1>
  <hr class="wp-header-end">

  <!-- Filters -->
  <form method="get" class="eiu-rp-filter-bar">
    <input type="hidden" name="page" value="eiu-rp-articles">
    <div class="eiu-filter-row">
      <?php foreach ( $statuses as $val => $label ): ?>
        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'eiu-rp-articles', 'status' => $val ) , admin_url( 'admin.php' ) ) ); ?>"
           class="eiu-status-tab <?php echo $filters['status'] === $val ? 'active' : ''; ?>">
           <?php echo esc_html( $label ); ?>
        </a>
      <?php endforeach; ?>
      <span style="flex:1;"></span>
      <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search articles…', 'eiu-rp' ); ?>" class="eiu-search-input">
      <button type="submit" class="button"><?php esc_html_e( 'Search', 'eiu-rp' ); ?></button>
    </div>
  </form>

  <?php if ( empty( $items ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-media-document" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No articles found.', 'eiu-rp' ); ?></p>
    </div>
  <?php else: ?>
    <?php if ( current_user_can( 'manage_options' ) ): ?>
    <!-- Bulk delete toolbar — hidden until at least one checkbox is ticked -->
    <div id="eiu-bulk-bar" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;margin-bottom:12px;flex-wrap:wrap;">
      <span id="eiu-bulk-count" style="font-size:13px;font-weight:600;color:#856404;"></span>
      <button type="button" id="eiu-bulk-delete-btn" class="button"
        style="background:#dc2626;color:#fff;border-color:#dc2626;font-weight:700;">
        <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span>
        <?php esc_html_e( 'Delete Selected', 'eiu-rp' ); ?>
      </button>
      <button type="button" id="eiu-bulk-cancel-btn" class="button">
        <?php esc_html_e( 'Deselect All', 'eiu-rp' ); ?>
      </button>
      <span id="eiu-bulk-msg" style="font-size:13px;font-weight:600;"></span>
    </div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <?php if ( current_user_can( 'manage_options' ) ): ?>
          <th style="width:36px;text-align:center;">
            <input type="checkbox" id="eiu-select-all" title="<?php esc_attr_e('Select all','eiu-rp'); ?>">
          </th>
          <?php endif; ?>
          <th style="width:5%">#</th>
          <th><?php esc_html_e( 'Title', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Author', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Status', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'File', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Submitted', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $items as $row ): ?>
          <tr>
            <?php if ( current_user_can( 'manage_options' ) ): ?>
            <td style="text-align:center;">
              <input type="checkbox" class="eiu-article-cb" value="<?php echo esc_attr( $row['id'] ); ?>">
            </td>
            <?php endif; ?>
            <td><?php echo esc_html( $row['id'] ); ?></td>
            <td>
              <strong>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-articles&action=view&id=' . $row['id'] ) ); ?>">
                  <?php echo esc_html( $row['title'] ?: __( '(Untitled)', 'eiu-rp' ) ); ?>
                </a>
              </strong>
            </td>
            <td><?php echo esc_html( $row['author_name'] ); ?></td>
            <td><?php echo esc_html( $row['author_email'] ); ?></td>
            <td><?php echo Helpers::status_badge( $row['status'] ); // phpcs:ignore ?></td>
            <td>
              <?php if ( ! empty( $row['file_type'] ) ): ?>
                <span class="eiu-file-badge eiu-file-<?php echo esc_attr( $row['file_type'] ); ?>">
                  <?php echo esc_html( strtoupper( $row['file_type'] ) ); ?>
                </span>
              <?php endif; ?>
            </td>
            <td title="<?php echo esc_attr( $row['submitted_at'] ); ?>">
              <?php echo esc_html( Helpers::time_ago( $row['submitted_at'] ) ); ?>
            </td>
            <td>
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-articles&action=view&id=' . $row['id'] ) ); ?>" class="button button-small">
                <?php esc_html_e( 'View', 'eiu-rp' ); ?>
              </a>
              <?php if ( current_user_can( 'manage_options' ) ): ?>
              <button type="button" class="button button-small eiu-btn-delete-article"
                data-article-id="<?php echo esc_attr( $row['id'] ); ?>"
                data-article-title="<?php echo esc_attr( $row['title'] ?? '' ); ?>"
                style="color:#dc2626;border-color:#fca5a5;">
                <span class="dashicons dashicons-trash" style="font-size:14px;vertical-align:middle;margin-right:3px;"></span>
                <?php esc_html_e( 'Delete', 'eiu-rp' ); ?>
              </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo Helpers::pagination_links( $total, $per_page, $page, admin_url( 'admin.php?page=eiu-rp-articles&status=' . urlencode( $filters['status'] ) ) ); // phpcs:ignore ?>
  <?php endif; ?>
</div>
