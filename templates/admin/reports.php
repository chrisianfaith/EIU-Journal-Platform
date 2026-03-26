<?php
/**
 * Admin Reports Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Article;

$trend_labels = wp_json_encode( array_column( $monthly_trend, 'label' ) );
$trend_data   = wp_json_encode( array_column( $monthly_trend, 'count' ) );

$status_labels = wp_json_encode( array_column( $by_status, 'status' ) );
$status_data   = wp_json_encode( array_column( $by_status, 'count' ) );
?>
<div class="wrap eiu-rp-admin">
  <h1>
    <span class="dashicons dashicons-chart-bar"></span>
    <?php esc_html_e( 'Reports & Analytics', 'eiu-rp' ); ?>
  </h1>
  <hr class="wp-header-end">

  <div class="eiu-rp-cols">
    <div class="eiu-rp-card" style="flex:2;">
      <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Submissions Over 12 Months', 'eiu-rp' ); ?></h3>
      <canvas id="eiu-trend-chart" height="80"></canvas>
    </div>
    <div class="eiu-rp-card" style="flex:1;">
      <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Articles by Status', 'eiu-rp' ); ?></h3>
      <canvas id="eiu-status-chart" height="160"></canvas>
    </div>
  </div>

  <div class="eiu-rp-cols">

    <div class="eiu-rp-card">
      <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Submissions by Subject', 'eiu-rp' ); ?></h3>
      <?php if ( empty( $by_subject ) ): ?>
        <p class="eiu-rp-empty"><?php esc_html_e( 'No data yet.', 'eiu-rp' ); ?></p>
      <?php else: ?>
        <table class="wp-list-table widefat fixed eiu-rp-table">
          <thead><tr><th><?php esc_html_e( 'Subject', 'eiu-rp' ); ?></th><th><?php esc_html_e( 'Count', 'eiu-rp' ); ?></th></tr></thead>
          <tbody>
            <?php foreach ( $by_subject as $row ): ?>
              <tr>
                <td><?php echo esc_html( $row['subject'] ); ?></td>
                <td><strong><?php echo esc_html( $row['count'] ); ?></strong></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="eiu-rp-card">
      <h3 class="eiu-rp-card-title"><?php esc_html_e( 'Reviewer Performance', 'eiu-rp' ); ?></h3>
      <?php if ( empty( $reviewer_perf ) ): ?>
        <p class="eiu-rp-empty"><?php esc_html_e( 'No reviewer data yet.', 'eiu-rp' ); ?></p>
      <?php else: ?>
        <table class="wp-list-table widefat fixed eiu-rp-table">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Reviewer', 'eiu-rp' ); ?></th>
              <th><?php esc_html_e( 'Assigned', 'eiu-rp' ); ?></th>
              <th><?php esc_html_e( 'Completed', 'eiu-rp' ); ?></th>
              <th><?php esc_html_e( 'Avg. Days', 'eiu-rp' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $reviewer_perf as $r ): ?>
              <tr>
                <td>
                  <strong><?php echo esc_html( $r['full_name'] ); ?></strong><br>
                  <small><?php echo esc_html( $r['email'] ); ?></small>
                </td>
                <td><?php echo esc_html( $r['total_assigned'] ); ?></td>
                <td><?php echo esc_html( $r['completed'] ); ?></td>
                <td><?php echo $r['avg_days'] ? esc_html( round( $r['avg_days'], 1 ) ) : '&mdash;'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if ( typeof Chart === 'undefined' ) return;

  new Chart(document.getElementById('eiu-trend-chart'), {
    type: 'line',
    data: {
      labels: <?php echo $trend_labels; // phpcs:ignore ?>,
      datasets: [{
        label: '<?php echo esc_js( __( 'Submissions', 'eiu-rp' ) ); ?>',
        data: <?php echo $trend_data; // phpcs:ignore ?>,
        borderColor: '#003087',
        backgroundColor: 'rgba(0,48,135,.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.3,
        pointRadius: 4,
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
  });

  new Chart(document.getElementById('eiu-status-chart'), {
    type: 'doughnut',
    data: {
      labels: <?php echo $status_labels; // phpcs:ignore ?>,
      datasets: [{
        data: <?php echo $status_data; // phpcs:ignore ?>,
        backgroundColor: ['#f59e0b','#3b82f6','#10b981','#ef4444','#8b5cf6','#06b6d4'],
        borderWidth: 2,
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
});
</script>
