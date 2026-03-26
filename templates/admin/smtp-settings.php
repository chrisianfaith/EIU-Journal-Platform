<?php
/**
 * Admin: SMTP Settings Template.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// WP Mail SMTP detection
$smtp_plugin_active = defined( 'WPMS_PLUGIN_VER' ) || defined( 'WPMAILSMTP_PLUGIN_VERSION' )
    || class_exists( 'WPMailSMTP\\WP\\Hooks' );

$host   = get_option( 'eiu_smtp_host', '' );
$port   = get_option( 'eiu_smtp_port', 587 );
$auth   = get_option( 'eiu_smtp_auth', '1' );
$user   = get_option( 'eiu_smtp_user', '' );
$secure = get_option( 'eiu_smtp_secure', 'tls' );
$enabled = get_option( 'eiu_smtp_enabled', '0' );
?>
<div class="wrap eiu-rp-admin">
  <h1>
    <span class="dashicons dashicons-email" style="color:#1a4988;margin-right:6px;"></span>
    <?php esc_html_e( 'SMTP Settings', 'eiu-rp' ); ?>
  </h1>
  <hr class="wp-header-end">

  <?php if ( isset( $_GET['smtp-saved'] ) ): ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SMTP settings saved.', 'eiu-rp' ); ?></p></div>
  <?php endif; ?>

  <?php if ( $smtp_plugin_active ): ?>
    <div class="notice notice-info" style="border-left-color:#1a4988;">
      <p>
        <strong><?php esc_html_e( 'WP Mail SMTP detected.', 'eiu-rp' ); ?></strong>
        <?php esc_html_e( 'Your SMTP plugin is managing email delivery. The settings below are disabled to avoid conflicts. Configure SMTP through your WP Mail SMTP plugin.', 'eiu-rp' ); ?>
      </p>
    </div>
  <?php endif; ?>

  <div class="eiu-rp-card" style="max-width:700px;">
    <h2 class="eiu-rp-card-title"><?php esc_html_e( 'SMTP Configuration', 'eiu-rp' ); ?></h2>
    <p style="font-size:13px;color:#6b7280;margin:0 0 20px;">
      <?php esc_html_e( 'Configure an SMTP server for reliable email delivery. If you have WP Mail SMTP or another SMTP plugin installed, use that instead — these settings will be ignored automatically.', 'eiu-rp' ); ?>
    </p>

    <form method="post" action="">
      <?php wp_nonce_field( 'eiu_smtp_settings' ); ?>
      <input type="hidden" name="eiu_smtp_save" value="1">
      <?php $disabled = $smtp_plugin_active ? 'disabled' : ''; ?>

      <table class="form-table">
        <tr>
          <th><?php esc_html_e( 'Enable Custom SMTP', 'eiu-rp' ); ?></th>
          <td>
            <label>
              <input type="checkbox" name="eiu_smtp_enabled" value="1"
                <?php checked( $enabled, '1' ); ?> <?php echo esc_attr($disabled); ?>>
              <?php esc_html_e( 'Use the SMTP settings below for sending emails', 'eiu-rp' ); ?>
            </label>
          </td>
        </tr>
        <tr>
          <th><label for="eiu_smtp_host"><?php esc_html_e( 'SMTP Host', 'eiu-rp' ); ?></label></th>
          <td>
            <input type="text" id="eiu_smtp_host" name="eiu_smtp_host" class="regular-text"
              value="<?php echo esc_attr( $host ); ?>" placeholder="smtp.gmail.com" <?php echo esc_attr($disabled); ?>>
          </td>
        </tr>
        <tr>
          <th><label for="eiu_smtp_port"><?php esc_html_e( 'SMTP Port', 'eiu-rp' ); ?></label></th>
          <td>
            <input type="number" id="eiu_smtp_port" name="eiu_smtp_port" class="small-text"
              value="<?php echo esc_attr( $port ); ?>" min="1" max="65535" <?php echo esc_attr($disabled); ?>>
            <p class="description"><?php esc_html_e( 'Common ports: 587 (TLS), 465 (SSL), 25 (no encryption — not recommended)', 'eiu-rp' ); ?></p>
          </td>
        </tr>
        <tr>
          <th><?php esc_html_e( 'Encryption', 'eiu-rp' ); ?></th>
          <td>
            <?php foreach ( array( 'tls' => 'TLS (recommended)', 'ssl' => 'SSL', 'none' => 'None' ) as $val => $lbl ): ?>
              <label style="margin-right:18px;">
                <input type="radio" name="eiu_smtp_secure" value="<?php echo esc_attr($val); ?>"
                  <?php checked( $secure, $val ); ?> <?php echo esc_attr($disabled); ?>>
                <?php echo esc_html($lbl); ?>
              </label>
            <?php endforeach; ?>
          </td>
        </tr>
        <tr>
          <th><?php esc_html_e( 'SMTP Authentication', 'eiu-rp' ); ?></th>
          <td>
            <label>
              <input type="checkbox" name="eiu_smtp_auth" value="1"
                <?php checked( $auth, '1' ); ?> <?php echo esc_attr($disabled); ?>>
              <?php esc_html_e( 'Use SMTP username and password', 'eiu-rp' ); ?>
            </label>
          </td>
        </tr>
        <tr>
          <th><label for="eiu_smtp_user"><?php esc_html_e( 'Username / Email', 'eiu-rp' ); ?></label></th>
          <td>
            <input type="text" id="eiu_smtp_user" name="eiu_smtp_user" class="regular-text"
              autocomplete="off"
              value="<?php echo esc_attr( $user ); ?>" <?php echo esc_attr($disabled); ?>>
          </td>
        </tr>
        <tr>
          <th><label for="eiu_smtp_pass"><?php esc_html_e( 'Password / App Password', 'eiu-rp' ); ?></label></th>
          <td>
            <input type="password" id="eiu_smtp_pass" name="eiu_smtp_pass" class="regular-text"
              autocomplete="new-password"
              placeholder="<?php esc_attr_e( 'Leave blank to keep current password', 'eiu-rp' ); ?>" <?php echo esc_attr($disabled); ?>>
            <p class="description"><?php esc_html_e( 'For Gmail, use an App Password (not your account password).', 'eiu-rp' ); ?></p>
          </td>
        </tr>
      </table>

      <?php if ( ! $smtp_plugin_active ): ?>
        <p class="submit" style="display:flex;align-items:center;gap:12px;">
          <button type="submit" class="button button-primary button-large">
            <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
            <?php esc_html_e( 'Save SMTP Settings', 'eiu-rp' ); ?>
          </button>
          <button type="button" class="button button-secondary" id="eiu-smtp-test-btn">
            <span class="dashicons dashicons-email-alt" style="vertical-align:middle;"></span>
            <?php esc_html_e( 'Send Test Email', 'eiu-rp' ); ?>
          </button>
          <span id="eiu-smtp-test-result" style="font-size:13px;"></span>
        </p>
      <?php endif; ?>
    </form>
  </div>
</div>

<script>
(function(){
var testBtn = document.getElementById('eiu-smtp-test-btn');
if(!testBtn) return;
testBtn.addEventListener('click', function(){
  var result = document.getElementById('eiu-smtp-test-result');
  var email = prompt('<?php echo esc_js(__('Enter email address to receive the test message:','eiu-rp')); ?>', '<?php echo esc_js(get_option('admin_email')); ?>');
  if(!email) return;
  testBtn.disabled = true;
  result.textContent = '<?php echo esc_js(__('Sending…','eiu-rp')); ?>';
  result.style.color = '#6b7280';
  fetch(ajaxurl, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action: 'eiu_rp_smtp_test',
      nonce:  '<?php echo esc_js(wp_create_nonce('eiu_smtp_test')); ?>',
      email:  email
    })
  }).then(r=>r.json()).then(res=>{
    result.textContent = res.success
      ? '<?php echo esc_js(__('Test email sent! Check your inbox.','eiu-rp')); ?>'
      : (res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Send failed.','eiu-rp')); ?>');
    result.style.color = res.success ? '#166534' : '#991b1b';
    testBtn.disabled = false;
  }).catch(()=>{
    result.textContent = '<?php echo esc_js(__('Network error.','eiu-rp')); ?>';
    result.style.color = '#991b1b';
    testBtn.disabled = false;
  });
});
}());
</script>
