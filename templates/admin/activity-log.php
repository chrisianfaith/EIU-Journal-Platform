<?php
/**
 * Admin Activity Log Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Utils\Helpers;
?>
<div class="wrap eiu-rp-admin">
  <h1 class="wp-heading-inline">
    <span class="dashicons dashicons-list-view"></span>
    <?php esc_html_e( 'Activity Log', 'eiu-rp' ); ?>
    <span class="eiu-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
  </h1>
  <p class="description"><?php esc_html_e( 'All user actions within the EIU Journal System are logged here and accessible only to administrators.', 'eiu-rp' ); ?></p>
  <hr class="wp-header-end">

  <form method="get" class="eiu-rp-filter-bar">
    <input type="hidden" name="page" value="eiu-rp-activity-log">
    <div class="eiu-filter-row" style="flex-wrap:wrap;gap:8px;">
      <input type="search" name="action_filter" value="<?php echo esc_attr( $filters['action'] ); ?>" placeholder="<?php esc_attr_e( 'Filter by action…', 'eiu-rp' ); ?>" class="eiu-search-input" style="width:160px;">
      <input type="text" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" placeholder="<?php esc_attr_e( 'From (YYYY-MM-DD)', 'eiu-rp' ); ?>" class="eiu-search-input" style="width:160px;">
      <input type="text" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" placeholder="<?php esc_attr_e( 'To (YYYY-MM-DD)', 'eiu-rp' ); ?>" class="eiu-search-input" style="width:160px;">
      <button type="submit" class="button"><?php esc_html_e( 'Filter', 'eiu-rp' ); ?></button>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-activity-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'eiu-rp' ); ?></a>
    </div>
  </form>

  <?php if ( empty( $items ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-list-view" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No activity found.', 'eiu-rp' ); ?></p>
    </div>
  <?php else: ?>
    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <th style="width:5%">#</th>
          <th style="width:12%"><?php esc_html_e( 'User', 'eiu-rp' ); ?></th>
          <th style="width:14%"><?php esc_html_e( 'Action', 'eiu-rp' ); ?></th>
          <th style="width:10%"><?php esc_html_e( 'Object', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Description', 'eiu-rp' ); ?></th>
          <th style="width:12%"><?php esc_html_e( 'IP Address', 'eiu-rp' ); ?></th>
          <th style="width:13%"><?php esc_html_e( 'Date', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $items as $row ): ?>
          <tr>
            <td><?php echo esc_html( $row['id'] ); ?></td>
            <td><?php echo $row['user_login'] ? '<strong>' . esc_html( $row['user_login'] ) . '</strong>' : '<em>' . esc_html__( 'Guest', 'eiu-rp' ) . '</em>'; ?></td>
            <td><code class="eiu-action-code"><?php echo esc_html( $row['action'] ); ?></code></td>
            <td><?php echo $row['object_type'] ? esc_html( $row['object_type'] ) . ' <small>#' . esc_html( $row['object_id'] ) . '</small>' : '&mdash;'; ?></td>
            <td><?php echo esc_html( $row['description'] ); ?></td>
            <td><code><?php echo esc_html( $row['ip_address'] ); ?></code></td>
            <td title="<?php echo esc_attr( $row['created_at'] ); ?>"><?php echo esc_html( Helpers::time_ago( $row['created_at'] ) ); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo Helpers::pagination_links( $total, $per_page, $page, admin_url( 'admin.php?page=eiu-rp-activity-log' ) ); // phpcs:ignore ?>
  <?php endif; ?>
</div>
