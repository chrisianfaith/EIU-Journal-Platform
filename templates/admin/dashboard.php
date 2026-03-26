<?php
/**
 * Admin Dashboard Template.
 *
 * Variables: $total_articles, $pending, $under_review, $published,
 *            $total_reviewers, $total_reviews, $recent, $monthly
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Article;
use EIU_RP\Utils\Helpers;

$chart_labels = wp_json_encode( array_column( $monthly, 'label' ) );
$chart_data   = wp_json_encode( array_column( $monthly, 'count' ) );
?>
<div class="wrap eiu-rp-admin">

  <div class="eiu-rp-topbar">
    <h1 class="wp-heading-inline">
      <span class="dashicons dashicons-welcome-learn-more"></span>
      <?php esc_html_e( 'EIU JOURNAL SYSTEM', 'eiu-rp' ); ?>
    </h1>
    <a href="<?php echo esc_url( get_option( 'eiu_rp_submission_page_id' ) ? get_permalink( get_option( 'eiu_rp_submission_page_id' ) ) : home_url() ); ?>"
       target="_blank" class="page-title-action">
      <?php esc_html_e( 'View Submission Page', 'eiu-rp' ); ?>
    </a>
  </div>

  <?php if ( isset( $_GET['welcome_accepted'] ) ): ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Acknowledgement saved. Welcome to EIU Journal System!', 'eiu-rp' ); ?></p></div>
  <?php endif; ?>

  <!-- Stats Grid -->
  <div class="eiu-rp-stats-grid">
    <div class="eiu-stat-card eiu-stat-primary">
      <div class="eiu-stat-icon"><span class="dashicons dashicons-media-document"></span></div>
      <div class="eiu-stat-info">
        <span class="eiu-stat-num"><?php echo esc_html( number_format_i18n( $total_articles ) ); ?></span>
        <span class="eiu-stat-label"><?php esc_html_e( 'Total Articles', 'eiu-rp' ); ?></span>
      </div>
    </div>
    <div class="eiu-stat-card eiu-stat-warning">
      <div class="eiu-stat-icon"><span class="dashicons dashicons-clock"></span></div>
      <div class="eiu-stat-info">
        <span class="eiu-stat-num"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span>
        <span class="eiu-stat-label"><?php esc_html_e( 'Pending Review', 'eiu-rp' ); ?></span>
      </div>
    </div>
    <div class="eiu-stat-card eiu-stat-info">
      <div class="eiu-stat-icon"><span class="dashicons dashicons-visibility"></span></div>
      <div class="eiu-stat-info">
        <span class="eiu-stat-num"><?php echo esc_html( number_format_i18n( $under_review ) ); ?></span>
        <span class="eiu-stat-label"><?php esc_html_e( 'Under Review', 'eiu-rp' ); ?></span>
      </div>
    </div>
    <div class="eiu-stat-card eiu-stat-success">
      <div class="eiu-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
      <div class="eiu-stat-info">
        <span class="eiu-stat-num"><?php echo esc_html( number_format_i18n( $published ) ); ?></span>
        <span class="eiu-stat-label"><?php esc_html_e( 'Published', 'eiu-rp' ); ?></span>
      </div>
    </div>
    <div class="eiu-stat-card eiu-stat-purple">
      <div class="eiu-stat-icon"><span class="dashicons dashicons-groups"></span></div>
      <div class="eiu-stat-info">
        <span class="eiu-stat-num"><?php echo esc_html( number_format_i18n( $total_reviewers ) ); ?></span>
        <span class="eiu-stat-label"><?php esc_html_e( 'Active Reviewers', 'eiu-rp' ); ?></span>
      </div>
    </div>
    <div class="eiu-stat-card eiu-stat-teal">
      <div class="eiu-stat-icon"><span class="dashicons dashicons-edit-page"></span></div>
      <div class="eiu-stat-info">
        <span class="eiu-stat-num"><?php echo esc_html( number_format_i18n( $total_reviews ) ); ?></span>
        <span class="eiu-stat-label"><?php esc_html_e( 'Reviews Submitted', 'eiu-rp' ); ?></span>
      </div>
    </div>
  </div>

  <!-- Two-column layout -->
  <div class="eiu-rp-cols">

    <!-- Chart -->
    <div class="eiu-rp-card eiu-rp-col-chart">
      <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Monthly Submissions (6 months)', 'eiu-rp' ); ?></h3>
      <canvas id="eiu-submissions-chart" height="80"></canvas>
    </div>

    <!-- Quick Actions -->
    <div class="eiu-rp-card eiu-rp-col-actions">
      <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Quick Actions', 'eiu-rp' ); ?></h3>
      <ul class="eiu-rp-quick-actions">
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-articles' ) ); ?>" class="eiu-qa-link"><span class="dashicons dashicons-media-document"></span><?php esc_html_e( 'Manage Articles', 'eiu-rp' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers' ) ); ?>" class="eiu-qa-link"><span class="dashicons dashicons-admin-users"></span><?php esc_html_e( 'Manage Reviewers', 'eiu-rp' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviews' ) ); ?>" class="eiu-qa-link"><span class="dashicons dashicons-edit-page"></span><?php esc_html_e( 'View Reviews', 'eiu-rp' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-activity-log' ) ); ?>" class="eiu-qa-link"><span class="dashicons dashicons-list-view"></span><?php esc_html_e( 'Activity Log', 'eiu-rp' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reports' ) ); ?>" class="eiu-qa-link"><span class="dashicons dashicons-chart-bar"></span><?php esc_html_e( 'Reports', 'eiu-rp' ); ?></a></li>
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-settings' ) ); ?>" class="eiu-qa-link"><span class="dashicons dashicons-admin-settings"></span><?php esc_html_e( 'Settings', 'eiu-rp' ); ?></a></li>
      </ul>
    </div>

  </div>

  <!-- Recent Submissions -->
  <div class="eiu-rp-card">
    <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Recent Submissions', 'eiu-rp' ); ?></h3>
    <?php if ( empty( $recent_items ) ): ?>
      <p class="eiu-rp-empty"><?php esc_html_e( 'No articles submitted yet.', 'eiu-rp' ); ?></p>
    <?php else: ?>
      <table class="wp-list-table widefat fixed striped eiu-rp-table">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Title', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Author', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Status', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Submitted', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Action', 'eiu-rp' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $recent_items as $row ): ?>
            <tr>
              <td><strong><?php echo esc_html( $row['title'] ?? __( '(Untitled)', 'eiu-rp' ) ); ?></strong></td>
              <td><?php echo esc_html( $row['author_name'] ?? '' ); ?></td>
              <td><?php echo Helpers::status_badge( $row['status'] ?? 'pending' ); // phpcs:ignore ?></td>
              <td><?php echo esc_html( Helpers::time_ago( $row['submitted_at'] ?? '' ) ); ?></td>
              <td>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-articles&action=view&id=' . ( $row['id'] ?? 0 ) ) ); ?>" class="button button-small">
                  <?php esc_html_e( 'View', 'eiu-rp' ); ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="margin-top:12px;">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-articles' ) ); ?>" class="button">
          <?php esc_html_e( 'View All Articles', 'eiu-rp' ); ?>
        </a>
      </p>
    <?php endif; ?>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if ( typeof Chart === 'undefined' ) return;
  var ctx = document.getElementById('eiu-submissions-chart');
  if ( ! ctx ) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?php echo $chart_labels; // phpcs:ignore ?>,
      datasets: [{
        label: '<?php echo esc_js( __( 'Submissions', 'eiu-rp' ) ); ?>',
        data: <?php echo $chart_data; // phpcs:ignore ?>,
        backgroundColor: 'rgba(0,48,135,.75)',
        borderColor: '#003087',
        borderWidth: 1,
        borderRadius: 4,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });
});
</script>
