<?php
/**
 * Admin: Single Application View.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Application;

$admin_nonce   = wp_create_nonce( 'eiu_rp_admin' );
$all_reviewers = \EIU_RP\Models\Reviewer::query( array( 'per_page' => 200 ) )['items'] ?? array();

$status_options = array(
    Application::STATUS_PENDING   => __( 'Pending Review', 'eiu-rp' ),
    Application::STATUS_REVIEWING => __( 'Under Review', 'eiu-rp' ),
    Application::STATUS_APPROVED  => __( 'Approved', 'eiu-rp' ),
    Application::STATUS_REJECTED  => __( 'Rejected', 'eiu-rp' ),
    Application::STATUS_MORE_INFO => __( 'More Information Required', 'eiu-rp' ),
);

$badge_class = array(
    Application::STATUS_PENDING   => 'status-pending',
    Application::STATUS_REVIEWING => 'status-review',
    Application::STATUS_APPROVED  => 'status-approved',
    Application::STATUS_REJECTED  => 'status-rejected',
    Application::STATUS_MORE_INFO => 'status-revision',
);
?>
<div class="wrap eiu-rp-admin">
  <h1 style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=eiu-rp-applications' ) ); ?>"
      style="text-decoration:none;color:#1a4988;font-size:13px;font-weight:600;">
      &larr; <?php esc_html_e( 'Back to Applications', 'eiu-rp' ); ?>
    </a>
    <button type="button" id="eiu-app-delete-btn"
      data-app-id="<?php echo esc_attr( $app->id ); ?>"
      data-nonce="<?php echo esc_attr( $admin_nonce ); ?>"
      data-email="<?php echo esc_attr( $app->email ); ?>"
      class="button"
      style="color:#dc2626;border-color:#dc2626;font-weight:600;font-size:13px;">
      <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span>
      <?php esc_html_e( 'Delete Application', 'eiu-rp' ); ?>
    </button>
  </h1>

  <div id="eiu-app-notice" style="display:none;margin:12px 0;"></div>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-top:16px;align-items:start;">

    <!-- Left: Application Details -->
    <div>
      <!-- Header card -->
      <div class="eiu-rp-card" style="margin-bottom:20px;">
        <div class="eiu-rp-card-header" style="display:flex;align-items:center;gap:16px;padding:20px 24px;background:#f8fafc;border-bottom:1px solid #e5e7eb;">
          <div style="width:52px;height:52px;border-radius:50%;background:#1a4988;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:800;flex-shrink:0;">
            <?php echo esc_html( strtoupper( substr( $app->full_name, 0, 1 ) ) ); ?>
          </div>
          <div>
            <h2 style="margin:0;font-size:18px;color:#1a2535;"><?php echo esc_html( $app->full_name ); ?></h2>
            <?php if ( $app->title || $app->designation ): ?>
              <p style="margin:2px 0 0;font-size:13px;color:#6b7280;">
                <?php echo esc_html( implode( ', ', array_filter( array( $app->title, $app->designation ) ) ) ); ?>
              </p>
            <?php endif; ?>
          </div>
          <div style="margin-left:auto;">
            <span class="eiu-rp-badge <?php echo esc_attr( $badge_class[ $app->status ] ?? 'status-pending' ); ?>">
              <?php echo esc_html( Application::status_label( $app->status ) ); ?>
            </span>
          </div>
        </div>

        <div style="padding:20px 24px;">
          <table class="form-table" style="margin:0;">
            <tr>
              <th style="width:160px;padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px;"><?php esc_html_e( 'Reference ID', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;">#<?php echo esc_html( $app->id ); ?></td>
            </tr>
            <tr>
              <th style="padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;"><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;"><a href="mailto:<?php echo esc_attr( $app->email ); ?>"><?php echo esc_html( $app->email ); ?></a></td>
            </tr>
            <tr>
              <th style="padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;"><?php esc_html_e( 'Country', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;"><?php echo esc_html( $app->country ); ?></td>
            </tr>
            <tr>
              <th style="padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;"><?php esc_html_e( 'Gender', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;"><?php echo esc_html( ucfirst( $app->gender ?: '—' ) ); ?></td>
            </tr>
            <?php if ( $app->date_of_birth ): ?>
            <tr>
              <th style="padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;"><?php esc_html_e( 'Date of Birth', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $app->date_of_birth ) ) ); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ( $app->student_number ): ?>
            <tr>
              <th style="padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;"><?php esc_html_e( 'Student Number', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;"><?php echo esc_html( $app->student_number ); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
              <th style="padding:8px 0;color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;"><?php esc_html_e( 'Submitted', 'eiu-rp' ); ?></th>
              <td style="padding:8px 0;"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $app->submitted_at ) ) ); ?></td>
            </tr>
          </table>
        </div>
      </div>

      <!-- Expertise -->
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 20px;margin:0;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#1a2535;">
          <i class="dashicons dashicons-star-filled" style="color:#1a4988;margin-right:4px;vertical-align:middle;"></i>
          <?php esc_html_e( 'Area of Expertise', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 20px;font-size:14px;color:#374151;"><?php echo esc_html( $app->expertise ); ?></div>
      </div>

      <!-- Academic Background -->
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 20px;margin:0;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#1a2535;">
          <i class="dashicons dashicons-welcome-learn-more" style="color:#1a4988;margin-right:4px;vertical-align:middle;"></i>
          <?php esc_html_e( 'Academic Background', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 20px;font-size:14px;color:#374151;line-height:1.7;white-space:pre-wrap;"><?php echo esc_html( $app->academic_bg ); ?></div>
      </div>

      <!-- About -->
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 20px;margin:0;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#1a2535;">
          <i class="dashicons dashicons-admin-users" style="color:#1a4988;margin-right:4px;vertical-align:middle;"></i>
          <?php esc_html_e( 'About the Applicant', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 20px;font-size:14px;color:#374151;line-height:1.7;white-space:pre-wrap;"><?php echo esc_html( $app->about ); ?></div>
      </div>

      <!-- Uploaded Files -->
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 20px;margin:0;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#1a2535;">
          <i class="dashicons dashicons-paperclip" style="color:#1a4988;margin-right:4px;vertical-align:middle;"></i>
          <?php esc_html_e( 'Uploaded Documents', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 20px;">
          <?php if ( $app->cv_file_name ): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:8px;">
              <span class="dashicons dashicons-media-document" style="color:#1a4988;font-size:20px;"></span>
              <div>
                <p style="margin:0;font-weight:600;font-size:13px;"><?php esc_html_e( 'CV / Resume', 'eiu-rp' ); ?></p>
                <p style="margin:2px 0 0;font-size:12px;color:#6b7280;"><?php echo esc_html( $app->cv_file_name ); ?></p>
              </div>
              <?php if ( $app->cv_file_path && file_exists( $app->cv_file_path ) ): ?>
                <a href="<?php echo esc_url( wp_get_upload_dir()['baseurl'] . str_replace( wp_get_upload_dir()['basedir'], '', $app->cv_file_path ) ); ?>"
                  download class="button button-small" style="margin-left:auto;"
                  target="_blank">
                  <?php esc_html_e( 'Download', 'eiu-rp' ); ?>
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <p style="color:#9ca3af;font-size:13px;margin:0 0 8px;"><?php esc_html_e( 'No CV uploaded.', 'eiu-rp' ); ?></p>
          <?php endif; ?>

          <?php if ( $app->research_file_name ): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;">
              <span class="dashicons dashicons-media-text" style="color:#1a4988;font-size:20px;"></span>
              <div>
                <p style="margin:0;font-weight:600;font-size:13px;"><?php esc_html_e( 'Research Work Sample', 'eiu-rp' ); ?></p>
                <p style="margin:2px 0 0;font-size:12px;color:#6b7280;"><?php echo esc_html( $app->research_file_name ); ?></p>
              </div>
              <?php if ( $app->research_file_path && file_exists( $app->research_file_path ) ): ?>
                <a href="<?php echo esc_url( wp_get_upload_dir()['baseurl'] . str_replace( wp_get_upload_dir()['basedir'], '', $app->research_file_path ) ); ?>"
                  download class="button button-small" style="margin-left:auto;"
                  target="_blank">
                  <?php esc_html_e( 'Download', 'eiu-rp' ); ?>
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <p style="color:#9ca3af;font-size:13px;margin:8px 0 0;"><?php esc_html_e( 'No research work uploaded.', 'eiu-rp' ); ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ( $app->admin_notes ): ?>
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 20px;margin:0;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:700;color:#1a2535;">
          <?php esc_html_e( 'Admin Notes', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 20px;font-size:13px;color:#374151;white-space:pre-wrap;background:#fffbeb;border-left:4px solid #fbbf24;border-radius:0 6px 6px 0;">
          <?php echo esc_html( $app->admin_notes ); ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: Actions -->
    <div>

      <!-- Assign Reviewer -->
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 16px;margin:0;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;color:#1a2535;background:#f8fafc;">
          <i class="dashicons dashicons-admin-users" style="color:#1a4988;margin-right:4px;vertical-align:middle;"></i>
          <?php esc_html_e( 'Assign Reviewer', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 16px;">
          <?php if ( $app->assigned_reviewer_id ): ?>
            <?php $rv = \EIU_RP\Models\Reviewer::get( (int) $app->assigned_reviewer_id ); ?>
            <p style="font-size:13px;margin:0 0 10px;">
              <strong><?php esc_html_e( 'Currently assigned:', 'eiu-rp' ); ?></strong><br>
              <?php echo $rv ? esc_html( $rv->full_name . ' (' . $rv->email . ')' ) : __( 'Unknown reviewer', 'eiu-rp' ); ?>
            </p>
          <?php endif; ?>
          <select id="eiu-app-reviewer-select" class="widefat" style="font-size:13px;">
            <option value=""><?php esc_html_e( '— Select a reviewer —', 'eiu-rp' ); ?></option>
            <?php foreach ( $all_reviewers as $rv ): ?>
              <option value="<?php echo esc_attr( $rv['id'] ); ?>"
                <?php selected( $app->assigned_reviewer_id, $rv['id'] ); ?>>
                <?php echo esc_html( $rv['full_name'] . ' (' . $rv['email'] . ')' ); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="eiu-app-assign-btn"
            data-app-id="<?php echo esc_attr( $app->id ); ?>"
            class="button button-primary" style="width:100%;margin-top:8px;">
            <?php esc_html_e( 'Assign Reviewer', 'eiu-rp' ); ?>
          </button>
        </div>
      </div>

      <!-- Update Status -->
      <div class="eiu-rp-card" style="margin-bottom:16px;">
        <h3 style="padding:14px 16px;margin:0;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;color:#1a2535;background:#f8fafc;">
          <i class="dashicons dashicons-yes-alt" style="color:#1a4988;margin-right:4px;vertical-align:middle;"></i>
          <?php esc_html_e( 'Update Status', 'eiu-rp' ); ?>
        </h3>
        <div style="padding:14px 16px;">
          <p style="font-size:11px;color:#6b7280;margin:0 0 8px;"><?php esc_html_e( 'Setting Approved will automatically create a researcher account and email credentials.', 'eiu-rp' ); ?></p>
          <select id="eiu-app-status-select" class="widefat" style="font-size:13px;">
            <?php foreach ( $status_options as $s => $label ): ?>
              <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $app->status, $s ); ?>>
                <?php echo esc_html( $label ); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label style="display:block;font-weight:600;font-size:12px;margin:10px 0 4px;color:#374151;">
            <?php esc_html_e( 'Notes / Feedback (emailed to applicant)', 'eiu-rp' ); ?>
          </label>
          <textarea id="eiu-app-admin-notes" rows="4" class="widefat" style="font-size:13px;resize:vertical;"
            placeholder="<?php esc_attr_e( 'Optional: enter notes or feedback for the applicant…', 'eiu-rp' ); ?>"><?php echo esc_textarea( $app->admin_notes ); ?></textarea>
          <button type="button" id="eiu-app-status-btn"
            data-app-id="<?php echo esc_attr( $app->id ); ?>"
            class="button button-primary" style="width:100%;margin-top:8px;">
            <?php esc_html_e( 'Save Status', 'eiu-rp' ); ?>
          </button>
          <span id="eiu-app-status-msg" style="display:block;font-size:12px;margin-top:6px;"></span>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  var nonce = '<?php echo esc_js( $admin_nonce ); ?>';
  var ajax  = ajaxurl;

  function notice(msg, ok) {
    var el = document.getElementById('eiu-app-notice');
    el.innerHTML = '<div class="notice notice-' + (ok ? 'success' : 'error') + ' is-dismissible"><p>' + msg + '</p></div>';
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  /* Assign reviewer */
  document.getElementById('eiu-app-assign-btn').addEventListener('click', function(){
    var appId     = this.dataset.appId;
    var reviewerId = document.getElementById('eiu-app-reviewer-select').value;
    var btn        = this;
    if (!reviewerId) { notice('<?php echo esc_js( __( 'Please select a reviewer.', 'eiu-rp' ) ); ?>', false); return; }
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Assigning…', 'eiu-rp' ) ); ?>';
    var fd = new FormData();
    fd.append('action','eiu_rp_application_assign_reviewer');
    fd.append('nonce', nonce);
    fd.append('application_id', appId);
    fd.append('reviewer_id', reviewerId);
    fetch(ajax,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
      btn.disabled = false;
      btn.textContent = '<?php echo esc_js( __( 'Assign Reviewer', 'eiu-rp' ) ); ?>';
      notice(res.data && res.data.message ? res.data.message : (res.success ? '<?php echo esc_js( __( 'Assigned.', 'eiu-rp' ) ); ?>' : '<?php echo esc_js( __( 'Error.', 'eiu-rp' ) ); ?>'), res.success);
    }).catch(()=>{ btn.disabled=false; btn.textContent='<?php echo esc_js( __( 'Assign Reviewer', 'eiu-rp' ) ); ?>'; });
  });

  /* Update status */
  document.getElementById('eiu-app-status-btn').addEventListener('click', function(){
    var appId  = this.dataset.appId;
    var status = document.getElementById('eiu-app-status-select').value;
    var notes  = document.getElementById('eiu-app-admin-notes').value;
    var msgEl  = document.getElementById('eiu-app-status-msg');
    var btn    = this;
    btn.disabled = true;
    btn.textContent = '<?php echo esc_js( __( 'Saving…', 'eiu-rp' ) ); ?>';
    msgEl.textContent = '';
    var fd = new FormData();
    fd.append('action','eiu_rp_application_set_status');
    fd.append('nonce', nonce);
    fd.append('application_id', appId);
    fd.append('status', status);
    fd.append('admin_notes', notes);
    fetch(ajax,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
      btn.disabled = false;
      btn.textContent = '<?php echo esc_js( __( 'Save Status', 'eiu-rp' ) ); ?>';
      if (res.success) {
        notice(res.data.message, true);
        if (res.data.status_label) {
          msgEl.style.color = '#065f46';
          msgEl.textContent = res.data.status_label;
        }
      } else {
        notice((res.data && res.data.message) || '<?php echo esc_js( __( 'Error.', 'eiu-rp' ) ); ?>', false);
      }
    }).catch(()=>{ btn.disabled=false; btn.textContent='<?php echo esc_js( __( 'Save Status', 'eiu-rp' ) ); ?>'; });
  });
}());

  /* ── Delete Application (v2.0.1) ─────────────────────────── */
  var delBtn = document.getElementById('eiu-app-delete-btn');
  if (delBtn) {
    delBtn.addEventListener('click', function(){
      var appId = this.dataset.appId;
      var nonce = this.dataset.nonce;
      var email = this.dataset.email;

      // Confirmation dialog with user-delete option
      var confirmed = confirm(
        '<?php echo esc_js( __( 'Are you sure you want to permanently delete this application? This cannot be undone.', 'eiu-rp' ) ); ?>'
        + '\n\n' + '<?php echo esc_js( __( 'Application email:', 'eiu-rp' ) ); ?> ' + email
      );
      if (!confirmed) return;

      var deleteUser = confirm(
        '<?php echo esc_js( __( 'Also delete the WordPress user account associated with this email?', 'eiu-rp' ) ); ?>'
        + '\n' + '(' + email + ')'
        + '\n\n' + '<?php echo esc_js( __( 'Click OK to delete the user, Cancel to keep it.', 'eiu-rp' ) ); ?>'
      );

      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner is-active" style="float:none;vertical-align:middle;margin:0 4px 0 0;"></span>'
        + '<?php echo esc_js( __( 'Deleting…', 'eiu-rp' ) ); ?>';

      var noticeEl = document.getElementById('eiu-app-notice');

      var fd = new FormData();
      fd.append('action',         'eiu_rp_delete_application');
      fd.append('nonce',          nonce);
      fd.append('application_id', appId);
      fd.append('delete_user',    deleteUser ? '1' : '0');

      fetch(ajaxurl, {method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res.success) {
            noticeEl.innerHTML = '<div class="notice notice-success inline" style="margin:0;"><p>'
              + (res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Deleted.', 'eiu-rp' ) ); ?>')
              + (res.data && res.data.user_deleted ? ' <?php echo esc_js( __( 'User account also removed.', 'eiu-rp' ) ); ?>' : '')
              + '</p></div>';
            noticeEl.style.display = 'block';
            // Redirect back to list after 2 seconds
            setTimeout(function(){
              window.location.href = '<?php echo esc_js( admin_url('admin.php?page=eiu-rp-applications') ); ?>';
            }, 2000);
          } else {
            btn.disabled = false;
            btn.innerHTML = '<span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span>'
              + '<?php echo esc_js( __( 'Delete Application', 'eiu-rp' ) ); ?>';
            noticeEl.innerHTML = '<div class="notice notice-error inline" style="margin:0;"><p>'
              + (res.data && res.data.message ? res.data.message : '<?php echo esc_js( __( 'Delete failed.', 'eiu-rp' ) ); ?>')
              + '</p></div>';
            noticeEl.style.display = 'block';
          }
        })
        .catch(function(){
          btn.disabled = false;
          btn.innerHTML = '<span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:4px;"></span>'
            + '<?php echo esc_js( __( 'Delete Application', 'eiu-rp' ) ); ?>';
          noticeEl.innerHTML = '<div class="notice notice-error inline" style="margin:0;"><p>'
            + '<?php echo esc_js( __( 'Network error. Please try again.', 'eiu-rp' ) ); ?>'
            + '</p></div>';
          noticeEl.style.display = 'block';
        });
    });
  }
</script>
