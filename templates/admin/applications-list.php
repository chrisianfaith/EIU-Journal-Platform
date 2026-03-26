<?php
/**
 * Admin: Researcher Applications List.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Application;
use EIU_RP\Utils\Helpers;

$admin_nonce = wp_create_nonce( 'eiu_rp_admin' );
$all_reviewers = \EIU_RP\Models\Reviewer::query( array( 'per_page' => 200 ) )['items'] ?? array();

$status_tabs = array(
    ''                           => __( 'All', 'eiu-rp' ),
    Application::STATUS_PENDING  => __( 'Pending', 'eiu-rp' ),
    Application::STATUS_REVIEWING => __( 'Under Review', 'eiu-rp' ),
    Application::STATUS_APPROVED  => __( 'Approved', 'eiu-rp' ),
    Application::STATUS_REJECTED  => __( 'Rejected', 'eiu-rp' ),
    Application::STATUS_MORE_INFO => __( 'More Info Required', 'eiu-rp' ),
);

$status_badge_class = array(
    Application::STATUS_PENDING   => 'status-pending',
    Application::STATUS_REVIEWING => 'status-review',
    Application::STATUS_APPROVED  => 'status-approved',
    Application::STATUS_REJECTED  => 'status-rejected',
    Application::STATUS_MORE_INFO => 'status-revision',
);
?>
<div class="wrap eiu-rp-admin">
  <h1 class="wp-heading-inline">
    <span class="dashicons dashicons-groups"></span>
    <?php esc_html_e( 'Author Applications', 'eiu-rp' ); ?>
    <span class="eiu-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
  </h1>
  <hr class="wp-header-end">

  <div id="eiu-app-notice" style="display:none;margin:8px 0;"></div>

  <!-- Filters -->
  <form method="get" class="eiu-rp-filter-bar">
    <input type="hidden" name="page" value="eiu-rp-applications">
    <div class="eiu-filter-row">
      <?php foreach ( $status_tabs as $tab_status => $tab_label ): ?>
        <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'eiu-rp-applications', 'status' => $tab_status ), admin_url( 'admin.php' ) ) ); ?>"
          class="eiu-status-tab <?php echo ( $filters['status'] === $tab_status ) ? 'active' : ''; ?>">
          <?php echo esc_html( $tab_label ); ?>
        </a>
      <?php endforeach; ?>
      <span style="flex:1;"></span>
      <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>"
        placeholder="<?php esc_attr_e( 'Search by name, email, expertise…', 'eiu-rp' ); ?>"
        class="eiu-search-input">
      <button type="submit" class="button"><?php esc_html_e( 'Search', 'eiu-rp' ); ?></button>
    </div>
  </form>

  <?php if ( empty( $items ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-groups" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No applications found.', 'eiu-rp' ); ?></p>
      <p style="font-size:13px;color:#6b7280;">
        <?php esc_html_e( 'Applications submitted via the [eiu_apply_researcher] shortcode will appear here.', 'eiu-rp' ); ?>
      </p>
    </div>
  <?php else: ?>
    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <th width="40">#</th>
          <th><?php esc_html_e( 'Name', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Expertise', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Country', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Status', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Assigned Reviewer', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Submitted', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $items as $app ): ?>
          <?php $badge = $status_badge_class[ $app->status ] ?? 'status-pending'; ?>
          <tr id="eiu-app-row-<?php echo esc_attr( $app->id ); ?>">
            <td><?php echo esc_html( $app->id ); ?></td>
            <td><strong><?php echo esc_html( $app->full_name ); ?></strong></td>
            <td><a href="mailto:<?php echo esc_attr( $app->email ); ?>"><?php echo esc_html( $app->email ); ?></a></td>
            <td><?php echo esc_html( wp_trim_words( $app->expertise, 6 ) ); ?></td>
            <td><?php echo esc_html( $app->country ); ?></td>
            <td>
              <span class="eiu-rp-badge <?php echo esc_attr( $badge ); ?>" id="eiu-app-badge-<?php echo esc_attr( $app->id ); ?>">
                <?php echo esc_html( Application::status_label( $app->status ) ); ?>
              </span>
            </td>
            <td>
              <?php if ( $app->assigned_reviewer_id ): ?>
                <?php $rv = \EIU_RP\Models\Reviewer::get( (int) $app->assigned_reviewer_id ); ?>
                <?php echo $rv ? esc_html( $rv->full_name ) : '—'; ?>
              <?php else: ?>
                <span style="color:#9ca3af;"><?php esc_html_e( 'None', 'eiu-rp' ); ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html( Helpers::time_ago( $app->submitted_at ) ); ?></td>
            <td style="white-space:nowrap;">
              <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'eiu-rp-applications', 'action' => 'view', 'id' => $app->id ), admin_url( 'admin.php' ) ) ); ?>"
                class="button button-small"><?php esc_html_e( 'View', 'eiu-rp' ); ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo \EIU_RP\Utils\Helpers::pagination_links( $total, $per_page, $page, admin_url( 'admin.php?page=eiu-rp-applications' ) ); // phpcs:ignore ?>
  <?php endif; ?>
</div>
