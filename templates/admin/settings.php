<?php
/**
 * Admin Settings Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$from_name     = get_option( 'eiu_rp_from_name', get_option( 'blogname' ) );
$from_email    = get_option( 'eiu_rp_from_email', get_option( 'admin_email' ) );
$notify_email  = get_option( 'eiu_rp_submission_notification_email', get_option( 'admin_email' ) );
$max_file_size = get_option( 'eiu_rp_max_file_size_mb', 20 );
$review_days   = get_option( 'eiu_rp_review_days_due', 14 );
$subjects      = (array) get_option( 'eiu_rp_subjects', array() );
?>
<div class="wrap eiu-rp-admin">
  <h1>
    <span class="dashicons dashicons-admin-settings"></span>
    <?php esc_html_e( 'Settings', 'eiu-rp' ); ?>
  </h1>
  <hr class="wp-header-end">

  <?php if ( isset( $_GET['settings-updated'] ) ): ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'eiu-rp' ); ?></p></div>
  <?php endif; ?>

  <form method="post" action="">
    <?php wp_nonce_field( 'eiu_rp_settings' ); ?>
    <input type="hidden" name="eiu_rp_settings_save" value="1">

    <div class="eiu-rp-settings-grid">

      <div class="eiu-rp-card">
        <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Email Settings', 'eiu-rp' ); ?></h2>
        <table class="form-table">
          <tr>
            <th><label for="eiu_rp_from_name"><?php esc_html_e( 'From Name', 'eiu-rp' ); ?></label></th>
            <td><input type="text" id="eiu_rp_from_name" name="eiu_rp_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text"></td>
          </tr>
          <tr>
            <th><label for="eiu_rp_from_email"><?php esc_html_e( 'From Email', 'eiu-rp' ); ?></label></th>
            <td><input type="email" id="eiu_rp_from_email" name="eiu_rp_from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text"></td>
          </tr>
          <tr>
            <th><label for="eiu_rp_submission_notification_email"><?php esc_html_e( 'Submission Notification Email', 'eiu-rp' ); ?></label></th>
            <td>
              <input type="email" id="eiu_rp_submission_notification_email" name="eiu_rp_submission_notification_email" value="<?php echo esc_attr( $notify_email ); ?>" class="regular-text">
              <p class="description"><?php esc_html_e( 'Admin email address to notify when a new article is submitted.', 'eiu-rp' ); ?></p>
            </td>
          </tr>
        </table>
      </div>

      <div class="eiu-rp-card">
        <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Submission Settings', 'eiu-rp' ); ?></h2>
        <table class="form-table">
          <tr>
            <th><label for="eiu_rp_max_file_size_mb"><?php esc_html_e( 'Max File Size (MB)', 'eiu-rp' ); ?></label></th>
            <td><input type="number" id="eiu_rp_max_file_size_mb" name="eiu_rp_max_file_size_mb" value="<?php echo esc_attr( $max_file_size ); ?>" class="small-text" min="1" max="100"></td>
          </tr>
          <tr>
            <th><label for="eiu_rp_review_days_due"><?php esc_html_e( 'Default Review Period (days)', 'eiu-rp' ); ?></label></th>
            <td>
              <input type="number" id="eiu_rp_review_days_due" name="eiu_rp_review_days_due" value="<?php echo esc_attr( $review_days ); ?>" class="small-text" min="1" max="365">
              <p class="description"><?php esc_html_e( 'Number of days from assignment to review due date.', 'eiu-rp' ); ?></p>
            </td>
          </tr>
        </table>
      </div>

      <div class="eiu-rp-card">
        <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Subjects', 'eiu-rp' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Enter one subject per line. These appear in the article submission form.', 'eiu-rp' ); ?></p>
        <textarea id="eiu_rp_subjects" name="eiu_rp_subjects" rows="12" class="large-text"><?php echo esc_textarea( implode( "\n", $subjects ) ); ?></textarea>
      </div>

      <div class="eiu-rp-card">
        <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Pages', 'eiu-rp' ); ?></h2>
        <table class="form-table">
          <tr>
            <th><?php esc_html_e( 'Submission Page', 'eiu-rp' ); ?></th>
            <td>
              <?php $pid = get_option( 'eiu_rp_submission_page_id' ); ?>
              <?php if ( $pid ): ?>
                <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
                &nbsp;&mdash;&nbsp;
                <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php esc_html_e( 'Edit', 'eiu-rp' ); ?></a>
              <?php else: ?>
                <em><?php esc_html_e( 'Not created yet.', 'eiu-rp' ); ?></em>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th><?php esc_html_e( 'Reviewer Dashboard Page', 'eiu-rp' ); ?></th>
            <td>
              <?php $pid = get_option( 'eiu_rp_reviewer_page_id' ); ?>
              <?php if ( $pid ): ?>
                <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
                &nbsp;&mdash;&nbsp;
                <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php esc_html_e( 'Edit', 'eiu-rp' ); ?></a>
              <?php else: ?>
                <em><?php esc_html_e( 'Not created yet.', 'eiu-rp' ); ?></em>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th><?php esc_html_e( 'Publication Listing Page', 'eiu-rp' ); ?></th>
            <td>
              <?php $pid = get_option( 'eiu_rp_listing_page_id' ); ?>
              <?php if ( $pid ): ?>
                <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
              <?php else: ?>
                <em><?php esc_html_e( 'Not created yet.', 'eiu-rp' ); ?></em>
              <?php endif; ?>
            </td>
          </tr>
        </table>
      </div>

    </div>

    <!-- ── Auto Reviewer Assignment ────────────────────────────────── -->
    <div class="eiu-rp-card" style="grid-column:1/-1;">
      <h2 class="eiu-rp-card-title">
        <span class="dashicons dashicons-groups" style="color:#1a4988;margin-right:6px;"></span>
        <?php esc_html_e( 'Auto Reviewer Assignment', 'eiu-rp' ); ?>
      </h2>
      <p style="margin:0 0 16px;font-size:13px;color:#6b7280;">
        <?php esc_html_e( 'When a new article is submitted it will be automatically assigned to all reviewers in this list. You can add or remove reviewers at any time without affecting existing assignments.', 'eiu-rp' ); ?>
      </p>

      <!-- Mode selector -->
      <?php $assign_mode = get_option( 'eiu_rp_auto_assign_mode', 'all' ); ?>
      <table class="form-table" style="margin-bottom:20px;">
        <tr>
          <th style="width:200px;"><?php esc_html_e( 'Assignment Mode', 'eiu-rp' ); ?></th>
          <td>
            <label style="display:inline-flex;align-items:center;gap:8px;margin-right:24px;cursor:pointer;">
              <input type="radio" name="eiu_assign_mode_radio" value="all"
                <?php checked( $assign_mode, 'all' ); ?> onchange="eiu_save_mode('all')">
              <strong><?php esc_html_e( 'Auto-assign to all default reviewers', 'eiu-rp' ); ?></strong>
            </label>
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="eiu_assign_mode_radio" value="subject"
                <?php checked( $assign_mode, 'subject' ); ?> onchange="eiu_save_mode('subject')">
              <strong><?php esc_html_e( 'Smart — match reviewer specialisation to article subject', 'eiu-rp' ); ?></strong>
              <span style="font-size:12px;color:#6b7280;"><?php esc_html_e('(falls back to all verified reviewers if no match)', 'eiu-rp'); ?></span>
            </label>
            <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">
              <input type="radio" name="eiu_assign_mode_radio" value="none"
                <?php checked( $assign_mode, 'none' ); ?> onchange="eiu_save_mode('none')">
              <strong><?php esc_html_e( 'Disabled (manual assignment only)', 'eiu-rp' ); ?></strong>
            </label>
            <p class="description" style="margin-top:6px;" id="eiu-mode-status"></p>
          </td>
        </tr>
      </table>

      <!-- Add reviewer row -->
      <?php
      // Show ALL non-deleted reviewers (verified or not) so the admin can assign them.
      // The verified flag controls reviewer dashboard access, not assignment eligibility.
      $all_verified = \EIU_RP\Models\Reviewer::query( array( 'per_page' => 200 ) )['items'] ?? array();
      $default_ids  = array_filter( array_map( 'absint', (array) get_option( 'eiu_rp_default_reviewers', array() ) ) );
      $default_revs = \EIU_RP\API\Auto_Assignment::get_default_reviewers();
      ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
        <select id="eiu-add-dr-select" style="max-width:340px;padding:7px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px;">
          <option value=""><?php esc_html_e( '— Select a verified reviewer to add —', 'eiu-rp' ); ?></option>
          <?php foreach ( $all_verified as $rv ):
            if ( in_array( (int) $rv['id'], $default_ids, true ) ) continue;
            $verified_badge = $rv['verified'] ? '' : ' [unverified]'; ?>
            <option value="<?php echo esc_attr( $rv['id'] ); ?>">
              <?php echo esc_html( $rv['full_name'] . ' (' . $rv['email'] . ')' . $verified_badge ); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="button button-primary" onclick="eiu_add_default_reviewer()">
          <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;margin-top:-2px;"></span>
          <?php esc_html_e( 'Add to Default List', 'eiu-rp' ); ?>
        </button>
        <span id="eiu-dr-msg" style="font-size:13px;"></span>
      </div>

      <!-- Current default reviewers list -->
      <table class="wp-list-table widefat fixed striped eiu-rp-table" id="eiu-dr-table">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Reviewer', 'eiu-rp' ); ?></th>
            <th><?php esc_html_e( 'Email', 'eiu-rp' ); ?></th>
            <th style="width:120px;"><?php esc_html_e( 'Action', 'eiu-rp' ); ?></th>
          </tr>
        </thead>
        <tbody id="eiu-dr-tbody">
          <?php if ( empty( $default_revs ) ): ?>
            <tr id="eiu-dr-empty">
              <td colspan="3" style="font-style:italic;color:#9ca3af;">
                <?php esc_html_e( 'No default reviewers set. Add reviewers above to enable auto-assignment.', 'eiu-rp' ); ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ( $default_revs as $dr ): ?>
              <tr id="eiu-dr-row-<?php echo esc_attr( $dr->id ); ?>">
                <td><strong><?php echo esc_html( $dr->full_name ); ?></strong></td>
                <td><?php echo esc_html( $dr->email ); ?></td>
                <td>
                  <button type="button" class="button button-small button-link-delete"
                    onclick="eiu_remove_default_reviewer(<?php echo esc_attr( $dr->id ); ?>, this)">
                    <span class="dashicons dashicons-trash" style="font-size:14px;vertical-align:middle;"></span>
                    <?php esc_html_e( 'Remove', 'eiu-rp' ); ?>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- ── Terminology & Labels — comprehensive ──────────────────────── -->
    <?php
    if ( class_exists( 'EIU_RP\\Utils\\Terminology' ) ) {
        $term_groups  = \EIU_RP\Utils\Terminology::groups();
        $term_defaults = \EIU_RP\Utils\Terminology::defaults();
    } else {
        $term_groups  = array();
        $term_defaults = array();
    }
    ?>
    <div class="eiu-rp-card" style="grid-column:1/-1;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
        <h2 class="eiu-rp-card-title" style="margin:0;">
          <span class="dashicons dashicons-editor-textcolor" style="margin-right:6px;vertical-align:middle;"></span>
          <?php esc_html_e( 'Terminology &amp; Labels', 'eiu-rp' ); ?>
        </h2>
        <input type="search" id="eiu-term-search"
          placeholder="<?php esc_attr_e('Search labels…','eiu-rp'); ?>"
          style="padding:6px 12px;border:1px solid #ccc;border-radius:6px;font-size:13px;min-width:220px;">
      </div>
      <p class="description" style="margin-bottom:20px;">
        <?php esc_html_e( 'Customise every user-facing label across the Author Portal, Reviewer Dashboard, and Submission Form. Leave any field blank to use the built-in default shown as the placeholder.', 'eiu-rp' ); ?>
      </p>

      <?php foreach ( $term_groups as $group_key => $group ): ?>
      <div class="eiu-term-group" id="eiu-tg-<?php echo esc_attr($group_key); ?>" style="margin-bottom:28px;">
        <h3 style="font-size:13px;font-weight:700;color:#1a4988;margin:0 0 10px;padding:8px 14px;background:#eef4ff;border-left:4px solid #1a4988;border-radius:0 6px 6px 0;">
          <?php echo esc_html( $group['label'] ); ?>
        </h3>
        <table class="form-table" style="margin:0;">
          <?php foreach ( $group['keys'] as $key ):
            $opt_key  = 'eiu_rp_term_' . $key;
            $value    = get_option( $opt_key, '' );
            $default  = $term_defaults[ $key ] ?? '';
            // Convert key to readable label: replace _ with space, title-case
            $readable = ucwords( str_replace( '_', ' ', $key ) );
          ?>
          <tr class="eiu-term-row" data-label="<?php echo esc_attr(strtolower($readable . ' ' . $default)); ?>">
            <th style="width:200px;padding:8px 0;">
              <label for="term_<?php echo esc_attr($key); ?>" style="font-size:13px;font-weight:600;color:#374151;">
                <?php echo esc_html( $readable ); ?>
              </label>
            </th>
            <td style="padding:6px 0;">
              <input type="text"
                id="term_<?php echo esc_attr($key); ?>"
                name="<?php echo esc_attr($opt_key); ?>"
                value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo esc_attr($default); ?>"
                class="regular-text eiu-term-input"
                style="width:100%;max-width:460px;">
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <?php endforeach; ?>

      <p style="font-size:12px;color:#9ca3af;margin:0;">
        <?php esc_html_e( 'Changes take effect immediately after clicking Save Settings below. No code changes required.', 'eiu-rp' ); ?>
      </p>
    </div>

    <script>
    (function(){
      var search = document.getElementById('eiu-term-search');
      if (!search) return;
      search.addEventListener('input', function(){
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('.eiu-term-row').forEach(function(row){
          var lbl = (row.dataset.label || '').toLowerCase();
          var inp = row.querySelector('.eiu-term-input');
          var val = inp ? inp.value.toLowerCase() : '';
          row.style.display = (!q || lbl.includes(q) || val.includes(q)) ? '' : 'none';
        });
        document.querySelectorAll('.eiu-term-group').forEach(function(grp){
          var visible = Array.from(grp.querySelectorAll('.eiu-term-row')).some(function(r){ return r.style.display !== 'none'; });
          grp.style.display = visible ? '' : 'none';
        });
      });
    }());
    </script>
    </div>

    <div class="eiu-rp-settings-grid">
      <p class="submit" style="grid-column:1/-1;">
        <button type="submit" class="button button-primary button-large">
          <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
          <?php esc_html_e( 'Save Settings', 'eiu-rp' ); ?>
        </button>
      </p>
    </div>
  </form>

  <div class="eiu-rp-card" style="margin-top:24px;">
    <h2 class="eiu-rp-card-title"><?php esc_html_e( 'Plugin Information', 'eiu-rp' ); ?></h2>
    <table class="form-table">
      <tr><th><?php esc_html_e( 'Version', 'eiu-rp' ); ?></th><td><?php echo esc_html( EIU_RP_VERSION ); ?></td></tr>
      <tr><th><?php esc_html_e( 'Developer', 'eiu-rp' ); ?></th><td>Christian Manaoat</td></tr>
      <tr><th><?php esc_html_e( 'Author', 'eiu-rp' ); ?></th><td>EIU IT Department</td></tr>
      <tr><th><?php esc_html_e( 'Contact', 'eiu-rp' ); ?></th><td><a href="mailto:support@eiu.ac">support@eiu.ac</a></td></tr>
      <tr><th><?php esc_html_e( 'Website', 'eiu-rp' ); ?></th><td><a href="https://eiu.ac" target="_blank">https://eiu.ac</a></td></tr>
      <tr><th><?php esc_html_e( 'License', 'eiu-rp' ); ?></th><td>2021-989820-PH</td></tr>
      <tr><th><?php esc_html_e( 'Activated', 'eiu-rp' ); ?></th><td><?php echo esc_html( get_option( 'eiu_rp_activated_at', '—' ) ); ?></td></tr>
    </table>
  </div>
</div>

<script>
/* Auto Assignment JS — Settings page */
(function($){
  // Use PHP-generated nonce as authoritative value - never empty
  var nonce = '<?php echo esc_js( wp_create_nonce( "eiu_rp_admin" ) ); ?>';
  var ajaxUrl = ajaxurl || '';

  function showDrMsg(msg, ok){
    var el = document.getElementById('eiu-dr-msg');
    el.textContent = msg;
    el.style.color = ok ? '#166534' : '#991b1b';
    setTimeout(function(){ el.textContent = ''; }, 4000);
  }

  window.eiu_save_mode = function(mode){
    $.post(ajaxUrl, { action:'eiu_rp_save_assign_mode', nonce:nonce, mode:mode }, function(res){
      var el = document.getElementById('eiu-mode-status');
      el.style.color = res.success ? '#166534' : '#991b1b';
      el.textContent = res.success
        ? '<?php echo esc_js(__('Mode saved.','eiu-rp')); ?>'
        : (res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error saving mode.','eiu-rp')); ?>');
      setTimeout(function(){ el.textContent = ''; }, 3000);
    });
  };

  window.eiu_add_default_reviewer = function(){
    var sel = document.getElementById('eiu-add-dr-select');
    var id  = parseInt(sel.value, 10);
    if(!id){ showDrMsg('<?php echo esc_js(__('Please select a reviewer.','eiu-rp')); ?>', false); return; }

    $.post(ajaxUrl, { action:'eiu_rp_add_default_reviewer', nonce:nonce, reviewer_id:id }, function(res){
      if(res.success){
        // Remove empty row if present
        var emptyRow = document.getElementById('eiu-dr-empty');
        if(emptyRow) emptyRow.remove();

        // Add new row to table
        var tbody = document.getElementById('eiu-dr-tbody');
        var tr = document.createElement('tr');
        tr.id = 'eiu-dr-row-' + res.data.reviewer_id;
        tr.innerHTML =
          '<td><strong>' + esc(res.data.full_name) + '</strong></td>' +
          '<td>' + esc(res.data.email) + '</td>' +
          '<td><button type="button" class="button button-small button-link-delete" onclick="eiu_remove_default_reviewer(' + res.data.reviewer_id + ', this)">' +
          '<span class="dashicons dashicons-trash" style="font-size:14px;vertical-align:middle;"></span> <?php echo esc_js(__('Remove','eiu-rp')); ?>' +
          '</button></td>';
        tbody.appendChild(tr);

        // Remove from select
        sel.querySelector('option[value="' + id + '"]').remove();
        sel.value = '';
        showDrMsg(res.data.message, true);
      } else {
        showDrMsg(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error.','eiu-rp')); ?>', false);
      }
    });
  };

  window.eiu_remove_default_reviewer = function(id, btn){
    if(!confirm('<?php echo esc_js(__('Remove this reviewer from the default assignment list?','eiu-rp')); ?>')) return;
    $.post(ajaxUrl, { action:'eiu_rp_remove_default_reviewer', nonce:nonce, reviewer_id:id }, function(res){
      if(res.success){
        var row = document.getElementById('eiu-dr-row-' + id);
        if(row){
          // Add reviewer back to the select dropdown
          var sel = document.getElementById('eiu-add-dr-select');
          var name = row.querySelector('strong').textContent;
          var email = row.cells[1].textContent;
          var opt = document.createElement('option');
          opt.value = id; opt.textContent = name + ' (' + email + ')';
          sel.appendChild(opt);
          row.remove();
        }
        // Show empty message if no rows left
        var tbody = document.getElementById('eiu-dr-tbody');
        if(tbody.rows.length === 0){
          var tr = document.createElement('tr'); tr.id = 'eiu-dr-empty';
          tr.innerHTML = '<td colspan="3" style="font-style:italic;color:#9ca3af;"><?php echo esc_js(__('No default reviewers set.','eiu-rp')); ?></td>';
          tbody.appendChild(tr);
        }
        showDrMsg(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Removed.','eiu-rp')); ?>', true);
      } else {
        showDrMsg(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error.','eiu-rp')); ?>', false);
      }
    });
  };

  function esc(str){ return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}(jQuery));
</script>
