<?php
/**
 * Admin Reviewers List Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Utils\Helpers;
$admin_nonce = wp_create_nonce( 'eiu_rp_admin' );
?>
<div class="wrap eiu-rp-admin">
  <h1 class="wp-heading-inline">
    <span class="dashicons dashicons-admin-users"></span>
    <?php esc_html_e( 'Reviewers', 'eiu-rp' ); ?>
    <span class="eiu-count-badge"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
  </h1>

  <!-- Sync + Add buttons -->
  <span style="float:right;margin-top:6px;display:flex;gap:8px;">
    <button type="button" class="button" id="eiu-sync-reviewers-btn">
      <span class="dashicons dashicons-update" style="vertical-align:middle;margin-top:-2px;"></span>
      <?php esc_html_e( 'Sync WP Users', 'eiu-rp' ); ?>
    </button>
    <button type="button" class="button button-primary" id="eiu-add-reviewer-toggle">
      <span class="dashicons dashicons-plus-alt" style="vertical-align:middle;margin-top:-2px;"></span>
      <?php esc_html_e( 'Add Reviewer', 'eiu-rp' ); ?>
    </button>
  </span>
  <hr class="wp-header-end">

  <!-- Notices -->
  <div id="eiu-rv-notice" style="display:none;margin:8px 0;"></div>

  <!-- Add Reviewer inline form -->
  <div id="eiu-add-reviewer-form" style="display:none;background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;margin-bottom:16px;">
    <h3 style="margin-top:0;"><?php esc_html_e( 'Add New Reviewer', 'eiu-rp' ); ?></h3>
    <p style="color:#6b7280;font-size:13px;margin-top:-8px;">
      <?php esc_html_e( 'Creates a reviewer record and WP user account. The reviewer is verified immediately. They will need to set a password via the WordPress password reset link.', 'eiu-rp' ); ?>
    </p>
    <table class="form-table" style="max-width:640px;">
      <tr>
        <th><label for="eiu-rv-full-name"><?php esc_html_e( 'Full Name', 'eiu-rp' ); ?> <span style="color:#d63638;">*</span></label></th>
        <td><input type="text" id="eiu-rv-full-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Dr. John Smith', 'eiu-rp' ); ?>"></td>
      </tr>
      <tr>
        <th><label for="eiu-rv-email"><?php esc_html_e( 'Email Address', 'eiu-rp' ); ?> <span style="color:#d63638;">*</span></label></th>
        <td><input type="email" id="eiu-rv-email" class="regular-text" placeholder="reviewer@institution.edu"></td>
      </tr>
      <tr>
        <th><label for="eiu-rv-org"><?php esc_html_e( 'Organization', 'eiu-rp' ); ?></label></th>
        <td><input type="text" id="eiu-rv-org" class="regular-text"></td>
      </tr>
      <tr>
        <th><label for="eiu-rv-spec"><?php esc_html_e( 'Specialization', 'eiu-rp' ); ?></label></th>
        <td><input type="text" id="eiu-rv-spec" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Computer Science, Medical Research', 'eiu-rp' ); ?>"></td>
      </tr>
    </table>
    <p>
      <button type="button" class="button button-primary" id="eiu-rv-submit-btn">
        <?php esc_html_e( 'Create Reviewer', 'eiu-rp' ); ?>
      </button>
      <button type="button" class="button" id="eiu-rv-cancel-btn" style="margin-left:8px;">
        <?php esc_html_e( 'Cancel', 'eiu-rp' ); ?>
      </button>
      <span id="eiu-rv-form-msg" style="margin-left:12px;font-size:13px;"></span>
    </p>
  </div>

  <form method="get" class="eiu-rp-filter-bar">
    <input type="hidden" name="page" value="eiu-rp-reviewers">
    <div class="eiu-filter-row">
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers' ) ); ?>" class="eiu-status-tab <?php echo $filters['verified'] === '' ? 'active' : ''; ?>"><?php esc_html_e( 'All', 'eiu-rp' ); ?></a>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers&verified=1' ) ); ?>" class="eiu-status-tab <?php echo $filters['verified'] === 1 ? 'active' : ''; ?>"><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></a>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers&verified=0' ) ); ?>" class="eiu-status-tab <?php echo $filters['verified'] === 0 ? 'active' : ''; ?>"><?php esc_html_e( 'Unverified', 'eiu-rp' ); ?></a>
      <span style="flex:1"></span>
      <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search reviewers…', 'eiu-rp' ); ?>" class="eiu-search-input">
      <button type="submit" class="button"><?php esc_html_e( 'Search', 'eiu-rp' ); ?></button>
    </div>
  </form>

  <?php if ( empty( $items ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-admin-users" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No reviewers found.', 'eiu-rp' ); ?></p>
      <p style="color:#6b7280;font-size:13px;">
        <?php esc_html_e( 'Use "Add Reviewer" to create one, or "Sync WP Users" to import existing WordPress users who have the Reviewer role.', 'eiu-rp' ); ?>
      </p>
    </div>
  <?php else: ?>
    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php esc_html_e( 'Name', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Organization', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Specialization', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Registered', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody id="eiu-rv-table-body">
        <?php foreach ( $items as $row ): ?>
          <tr id="eiu-rv-row-<?php echo esc_attr( $row['id'] ); ?>">
            <td><?php echo esc_html( $row['id'] ); ?></td>
            <td><strong><?php echo esc_html( $row['full_name'] ); ?></strong></td>
            <td><a href="mailto:<?php echo esc_attr( $row['email'] ); ?>"><?php echo esc_html( $row['email'] ); ?></a></td>
            <td><?php echo esc_html( $row['organization'] ); ?></td>
            <td><?php echo esc_html( wp_trim_words( $row['specialization'], 8 ) ); ?></td>
            <td>
              <?php if ( $row['verified'] ): ?>
                <span class="eiu-rp-badge status-approved"><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></span>
              <?php else: ?>
                <span class="eiu-rp-badge status-pending"><?php esc_html_e( 'Pending', 'eiu-rp' ); ?></span>
                <button class="button button-small eiu-btn-verify-reviewer" data-id="<?php echo esc_attr( $row['id'] ); ?>" style="margin-left:6px;">
                  <?php esc_html_e( 'Verify', 'eiu-rp' ); ?>
                </button>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html( Helpers::time_ago( $row['registered_at'] ) ); ?></td>
            <td>
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers&action=view&id=' . $row['id'] ) ); ?>" class="button button-small">
                <?php esc_html_e( 'View', 'eiu-rp' ); ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo Helpers::pagination_links( $total, $per_page, $page, admin_url( 'admin.php?page=eiu-rp-reviewers' ) ); // phpcs:ignore ?>
  <?php endif; ?>
</div>

<script>
(function($){
  var nonce = '<?php echo esc_js( $admin_nonce ); ?>';
  var ajax  = ajaxurl;

  function notice(msg, ok) {
    var el = document.getElementById('eiu-rv-notice');
    el.innerHTML = '<div class="notice notice-' + (ok ? 'success' : 'error') + ' is-dismissible"><p>' + msg + '</p></div>';
    el.style.display = 'block';
    setTimeout(function(){ el.style.display = 'none'; }, 6000);
  }

  /* ── Sync WP Users ─────────────────────────────────────────── */
  document.getElementById('eiu-sync-reviewers-btn').addEventListener('click', function(){
    var btn = this;
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Syncing…', 'eiu-rp' ) ); ?>';
    $.post(ajax, { action: 'eiu_rp_admin_sync_reviewers', nonce: nonce }, function(res){
      btn.disabled = false;
      btn.innerHTML = '<span class="dashicons dashicons-update" style="vertical-align:middle;margin-top:-2px;"></span> <?php echo esc_js( __( 'Sync WP Users', 'eiu-rp' ) ); ?>';
      if (res.success) {
        notice(res.data.message, true);
        if (res.data.created > 0) setTimeout(function(){ location.reload(); }, 1500);
      } else {
        notice((res.data && res.data.message) || '<?php echo esc_js( __( 'Sync failed.', 'eiu-rp' ) ); ?>', false);
      }
    });
  });

  /* ── Toggle Add Reviewer form ───────────────────────────────── */
  document.getElementById('eiu-add-reviewer-toggle').addEventListener('click', function(){
    var form = document.getElementById('eiu-add-reviewer-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') document.getElementById('eiu-rv-full-name').focus();
  });
  document.getElementById('eiu-rv-cancel-btn').addEventListener('click', function(){
    document.getElementById('eiu-add-reviewer-form').style.display = 'none';
  });

  /* ── Submit Add Reviewer ────────────────────────────────────── */
  document.getElementById('eiu-rv-submit-btn').addEventListener('click', function(){
    var btn     = this;
    var msgEl   = document.getElementById('eiu-rv-form-msg');
    var name    = document.getElementById('eiu-rv-full-name').value.trim();
    var email   = document.getElementById('eiu-rv-email').value.trim();
    var org     = document.getElementById('eiu-rv-org').value.trim();
    var spec    = document.getElementById('eiu-rv-spec').value.trim();

    if (!name || !email) {
      msgEl.style.color = '#d63638';
      msgEl.textContent = '<?php echo esc_js( __( 'Name and email are required.', 'eiu-rp' ) ); ?>';
      return;
    }

    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Creating…', 'eiu-rp' ) ); ?>';
    msgEl.textContent = '';

    $.post(ajax, {
      action:         'eiu_rp_admin_create_reviewer',
      nonce:          nonce,
      full_name:      name,
      email:          email,
      organization:   org,
      specialization: spec,
    }, function(res){
      btn.disabled = false;
      btn.textContent = '<?php echo esc_js( __( 'Create Reviewer', 'eiu-rp' ) ); ?>';
      if (res.success) {
        notice(res.data.message, true);
        document.getElementById('eiu-add-reviewer-form').style.display = 'none';
        document.getElementById('eiu-rv-full-name').value = '';
        document.getElementById('eiu-rv-email').value = '';
        document.getElementById('eiu-rv-org').value = '';
        document.getElementById('eiu-rv-spec').value = '';
        setTimeout(function(){ location.reload(); }, 1200);
      } else {
        msgEl.style.color = '#d63638';
        msgEl.textContent = (res.data && res.data.message) || '<?php echo esc_js( __( 'Error creating reviewer.', 'eiu-rp' ) ); ?>';
      }
    });
  });

  /* ── Inline Verify button ───────────────────────────────────── */
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.eiu-btn-verify-reviewer');
    if (!btn) return;
    var id = btn.dataset.id;
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Verifying…', 'eiu-rp' ) ); ?>';
    $.post(ajax, { action: 'eiu_rp_admin_verify_reviewer', nonce: nonce, reviewer_id: id }, function(res){
      if (res.success) {
        var cell = btn.closest('td');
        cell.innerHTML = '<span class="eiu-rp-badge status-approved"><?php echo esc_js( __( 'Verified', 'eiu-rp' ) ); ?></span>';
        notice(res.data.message, true);
      } else {
        btn.disabled = false;
        btn.textContent = '<?php echo esc_js( __( 'Verify', 'eiu-rp' ) ); ?>';
        notice((res.data && res.data.message) || '<?php echo esc_js( __( 'Error.', 'eiu-rp' ) ); ?>', false);
      }
    });
  });

}(jQuery));
</script>

  <form method="get" class="eiu-rp-filter-bar">
    <input type="hidden" name="page" value="eiu-rp-reviewers">
    <div class="eiu-filter-row">
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers' ) ); ?>" class="eiu-status-tab <?php echo $filters['verified'] === '' ? 'active' : ''; ?>"><?php esc_html_e( 'All', 'eiu-rp' ); ?></a>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers&verified=1' ) ); ?>" class="eiu-status-tab <?php echo $filters['verified'] === 1 ? 'active' : ''; ?>"><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></a>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers&verified=0' ) ); ?>" class="eiu-status-tab <?php echo $filters['verified'] === 0 ? 'active' : ''; ?>"><?php esc_html_e( 'Unverified', 'eiu-rp' ); ?></a>
      <span style="flex:1"></span>
      <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search reviewers…', 'eiu-rp' ); ?>" class="eiu-search-input">
      <button type="submit" class="button"><?php esc_html_e( 'Search', 'eiu-rp' ); ?></button>
    </div>
  </form>

  <?php if ( empty( $items ) ): ?>
    <div class="eiu-rp-empty-state">
      <span class="dashicons dashicons-admin-users" style="font-size:48px;color:#ccc;"></span>
      <p><?php esc_html_e( 'No reviewers found.', 'eiu-rp' ); ?></p>
    </div>
  <?php else: ?>
    <table class="wp-list-table widefat fixed striped eiu-rp-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?php esc_html_e( 'Name', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Organization', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Specialization', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Registered', 'eiu-rp' ); ?></th>
          <th><?php esc_html_e( 'Actions', 'eiu-rp' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $items as $row ): ?>
          <tr>
            <td><?php echo esc_html( $row['id'] ); ?></td>
            <td><strong><?php echo esc_html( $row['full_name'] ); ?></strong></td>
            <td><a href="mailto:<?php echo esc_attr( $row['email'] ); ?>"><?php echo esc_html( $row['email'] ); ?></a></td>
            <td><?php echo esc_html( $row['organization'] ); ?></td>
            <td><?php echo esc_html( wp_trim_words( $row['specialization'], 8 ) ); ?></td>
            <td>
              <?php if ( $row['verified'] ): ?>
                <span class="eiu-rp-badge status-approved"><?php esc_html_e( 'Verified', 'eiu-rp' ); ?></span>
              <?php else: ?>
                <span class="eiu-rp-badge status-pending"><?php esc_html_e( 'Pending', 'eiu-rp' ); ?></span>
                <button class="button button-small eiu-btn-verify-reviewer" data-id="<?php echo esc_attr( $row['id'] ); ?>" style="margin-left:6px;">
                  <?php esc_html_e( 'Verify', 'eiu-rp' ); ?>
                </button>
              <?php endif; ?>
            </td>
            <td><?php echo esc_html( Helpers::time_ago( $row['registered_at'] ) ); ?></td>
            <td>
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-reviewers&action=view&id=' . $row['id'] ) ); ?>" class="button button-small">
                <?php esc_html_e( 'View', 'eiu-rp' ); ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php echo Helpers::pagination_links( $total, $per_page, $page, admin_url( 'admin.php?page=eiu-rp-reviewers' ) ); // phpcs:ignore ?>
  <?php endif; ?>
</div>
