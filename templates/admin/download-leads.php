<?php
/**
 * Admin: Download Leads Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap eiu-rp-admin">
  <h1 class="wp-heading-inline">
    <span class="dashicons dashicons-email-alt"></span>
    <?php esc_html_e( 'Download Leads', 'eiu-rp' ); ?>
    <span class="eiu-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
  </h1>

  <a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action" style="margin-left:12px;">
    <span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;"></span>
    <?php esc_html_e( 'Export CSV', 'eiu-rp' ); ?>
  </a>

  <hr class="wp-header-end">

  <p class="description" style="margin-bottom:16px;">
    <?php esc_html_e( 'Every email address entered to download an article is stored here. Use Export CSV to download the full list.', 'eiu-rp' ); ?>
  </p>

  <!-- Search -->
  <form method="get" style="margin-bottom:16px;">
    <input type="hidden" name="page" value="eiu-rp-download-leads">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
        placeholder="<?php esc_attr_e( 'Search by email…', 'eiu-rp' ); ?>"
        style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;min-width:240px;">
      <button type="submit" class="button"><?php esc_html_e( 'Search', 'eiu-rp' ); ?></button>
      <?php if ( $search ): ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-download-leads' ) ); ?>" class="button">
          <?php esc_html_e( 'Clear', 'eiu-rp' ); ?>
        </a>
      <?php endif; ?>
    </div>
  </form>

  <?php if ( empty( $rows ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-email-alt" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No download leads recorded yet.', 'eiu-rp' ); ?></p>
      <p style="font-size:13px;color:#6b7280;">
        <?php esc_html_e( 'Emails are captured when a visitor enters their email to download an article.', 'eiu-rp' ); ?>
      </p>
    </div>
  <?php else: ?>

    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <th width="50">#</th>
          <th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Article', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Downloaded At', 'eiu-rp' ); ?></th>
          <th width="130"><?php esc_html_e( 'IP Address', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $rows as $row ): ?>
          <tr>
            <td><?php echo esc_html( $row->id ); ?></td>
            <td>
              <strong><?php echo esc_html( $row->email ); ?></strong>
            </td>
            <td>
              <?php if ( ! empty( $row->article_title ) ): ?>
                <?php echo esc_html( $row->article_title ); ?>
                <span style="font-size:11px;color:#9ca3af;">&nbsp;#<?php echo esc_html( $row->article_id ); ?></span>
              <?php else: ?>
                <span style="color:#9ca3af;"><?php echo esc_html( sprintf( __( 'Article #%d', 'eiu-rp' ), $row->article_id ) ); ?></span>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row->requested_at ) ) ); ?></td>
            <td style="font-family:monospace;font-size:12px;color:#6b7280;"><?php echo esc_html( $row->ip ); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ): ?>
      <div class="tablenav bottom" style="margin-top:12px;">
        <div class="tablenav-pages">
          <span class="displaying-num">
            <?php echo esc_html( sprintf( _n( '%s item', '%s items', $total, 'eiu-rp' ), number_format_i18n( $total ) ) ); ?>
          </span>
          <?php
          $base = add_query_arg( array( 'page' => 'eiu-rp-download-leads', 's' => $search ), admin_url( 'admin.php' ) );
          echo paginate_links( array(
              'base'    => $base . '%_%',
              'format'  => '&paged=%#%',
              'current' => $page,
              'total'   => $total_pages,
          ) );
          ?>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
