<?php
/**
 * Admin Reviewer View Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Review;
use EIU_RP\Utils\Helpers;

if ( ! $reviewer ) {
    echo '<div class="wrap"><p>' . esc_html__( 'Reviewer not found.', 'eiu-rp' ) . '</p></div>';
    return;
}
?>
<div class="wrap eiu-rp-admin">
  <h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers' ) ); ?>" class="eiu-back-link">
      <span class="dashicons dashicons-arrow-left-alt2"></span>
    </a>
    <?php esc_html_e( 'Reviewer Profile', 'eiu-rp' ); ?>
    <?php if ( $reviewer->verified ): ?>
      <span class="eiu-rp-badge status-approved"><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></span>
    <?php else: ?>
      <span class="eiu-rp-badge status-pending"><?php esc_html_e( 'Unverified', 'eiu-rp' ); ?></span>
    <?php endif; ?>
  </h1>
  <hr class="wp-header-end">

  <div class="eiu-rp-cols">
    <div class="eiu-rp-card">
      <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Profile Information', 'eiu-rp' ); ?></h2>
      <table class="eiu-detail-table">
        <tr><th><?php esc_html_e( 'ID', 'eiu-rp' ); ?></th><td>#<?php echo esc_html( $reviewer->id ); ?></td></tr>
        <tr><th><?php esc_html_e( 'Full Name', 'eiu-rp' ); ?></th><td><strong><?php echo esc_html( $reviewer->full_name ); ?></strong></td></tr>
        <tr><th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th><td><a href="mailto:<?php echo esc_attr( $reviewer->email ); ?>"><?php echo esc_html( $reviewer->email ); ?></a></td></tr>
        <tr><th><?php esc_html_e( 'Organization', 'eiu-rp' ); ?></th><td><?php echo esc_html( $reviewer->organization ?: '—' ); ?></td></tr>
        <tr><th><?php esc_html_e( 'Specialization', 'eiu-rp' ); ?></th><td><?php echo nl2br( esc_html( $reviewer->specialization ?: '—' ) ); ?></td></tr>
        <tr><th><?php esc_html_e( 'Registered', 'eiu-rp' ); ?></th><td><?php echo esc_html( $reviewer->registered_at ); ?></td></tr>
        <tr><th><?php esc_html_e( 'WP User', 'eiu-rp' ); ?></th>
          <td>
            <?php if ( $reviewer->user_id ):
              $u = get_userdata( $reviewer->user_id );
              if ( $u ): ?>
                <a href="<?php echo esc_url( get_edit_user_link( $reviewer->user_id ) ); ?>"><?php echo esc_html( $u->user_login ); ?></a>
              <?php else: ?>
                <em><?php esc_html_e( 'User not found', 'eiu-rp' ); ?></em>
              <?php endif;
            else: echo '—'; endif; ?>
          </td>
        </tr>
      </table>

      <?php if ( ! $reviewer->verified ): ?>
        <div style="margin-top:16px;">
          <button class="button button-primary eiu-btn-verify-reviewer" data-id="<?php echo esc_attr( $reviewer->id ); ?>">
            <?php esc_html_e( 'Manually Verify Reviewer', 'eiu-rp' ); ?>
          </button>
        </div>
      <?php endif; ?>
    </div>

    <div class="eiu-rp-card">
      <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Review Summary', 'eiu-rp' ); ?></h2>
      <?php
      $total_reviews    = count( $reviews );
      $completed_reviews= count( array_filter( $reviews, fn($r) => $r['status'] === 'submitted' ) );
      $pending_reviews  = $total_reviews - $completed_reviews;
      ?>
      <div class="eiu-rp-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 0;">
        <div class="eiu-stat-card eiu-stat-primary">
          <div class="eiu-stat-info">
            <span class="eiu-stat-num"><?php echo esc_html( $total_reviews ); ?></span>
            <span class="eiu-stat-label"><?php esc_html_e( 'Total', 'eiu-rp' ); ?></span>
          </div>
        </div>
        <div class="eiu-stat-card eiu-stat-warning">
          <div class="eiu-stat-info">
            <span class="eiu-stat-num"><?php echo esc_html( $pending_reviews ); ?></span>
            <span class="eiu-stat-label"><?php esc_html_e( 'Pending', 'eiu-rp' ); ?></span>
          </div>
        </div>
        <div class="eiu-stat-card eiu-stat-success">
          <div class="eiu-stat-info">
            <span class="eiu-stat-num"><?php echo esc_html( $completed_reviews ); ?></span>
            <span class="eiu-stat-label"><?php esc_html_e( 'Done', 'eiu-rp' ); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Assigned Reviews -->
  <div class="eiu-rp-card">
    <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Assigned Reviews', 'eiu-rp' ); ?></h2>
    <?php if ( empty( $reviews ) ): ?>
      <p class="eiu-rp-empty"><?php esc_html_e( 'No reviews assigned yet.', 'eiu-rp' ); ?></p>
    <?php else: ?>
      <table class="wp-list-table widefat fixed striped eiu-rp-table">
        <thead>
          <tr>
            <th>#</th>
            <th><?php esc_html_e( 'Article', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Status', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Recommendation', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Assigned', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Due', 'eiu-rp' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $reviews as $rev ): ?>
            <tr>
              <td><?php echo esc_html( $rev['id'] ); ?></td>
              <td><?php echo esc_html( wp_trim_words( $rev['article_title'] ?? '—', 10 ) ); ?></td>
              <td><span class="eiu-rp-badge status-<?php echo esc_attr( $rev['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $rev['status'] ) ) ); ?></span></td>
              <td><?php echo $rev['recommendation'] ? esc_html( Review::recommendation_label( $rev['recommendation'] ) ) : '&mdash;'; ?></td>
              <td><?php echo esc_html( Helpers::time_ago( $rev['assigned_at'] ) ); ?></td>
              <td><?php echo $rev['due_date'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $rev['due_date'] ) ) ) : '&mdash;'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
