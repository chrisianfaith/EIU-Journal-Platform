<?php
/**
 * Frontend: Unified Login Page — v2.0
 *
 * Single login for all roles. Researcher tab: standard email+password.
 * Reviewer tab: Email OTP gate first, then password.
 *
 * Shortcode: [eiu_unified_login]
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_style(
    'bootstrap-icons-eiu',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    array(), '1.11.3'
);

// ── Already-logged-in guard ──────────────────────────────────────
if ( is_user_logged_in() ) {
    $current = wp_get_current_user();
    $roles   = (array) $current->roles;
    if ( in_array( 'eiu_reviewer', $roles, true ) ) {
        $page_id = get_option( 'eiu_rp_reviewer_access_page_id' );
        $url     = $page_id ? get_permalink( $page_id ) : home_url( '/reviewer-dashboard/' );
    } elseif ( in_array( 'eiu_researcher', $roles, true ) ) {
        $page_id = get_option( 'eiu_rp_researcher_dashboard_page_id' );
        $url     = $page_id ? get_permalink( $page_id ) : home_url( '/researcher-dashboard/' );
    } elseif ( current_user_can( 'manage_options' ) ) {
        $url = admin_url();
    } else {
        $url = home_url();
    }
    wp_safe_redirect( esc_url_raw( $url ) );
    exit;
}

// Nonces fetched dynamically via AJAX to avoid stale cached values.
$ajax_url    = admin_url( 'admin-ajax.php' );

$redirect_to = sanitize_url( wp_unslash( $_GET['redirect_to'] ?? '' ) );
if ( $redirect_to && strpos( $redirect_to, home_url() ) !== 0 ) {
    $redirect_to = '';
}

$initial_role = sanitize_key( $_GET['role'] ?? 'researcher' );
if ( ! in_array( $initial_role, array( 'researcher', 'reviewer' ), true ) ) {
    $initial_role = 'researcher';
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Cabinet+Grotesk:wght@700;800;900&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
#eiu-unified-login {
  --blue:#1a4988;--blue-dark:#123266;--red:#990000;
  --green-bg:#d1fae5;--green-tx:#065f46;--green-br:#6ee7b7;
  --err-bg:#fef2f2;--err-tx:#991b1b;--err-br:#fecaca;
  --warn-bg:#fffbeb;--warn-tx:#92400e;--warn-br:#fcd34d;
  --info-bg:#eef4ff;--info-tx:#1e3a6e;--info-br:#bcd0f5;
  --border:#e2e8f0;--muted:#6b7280;--radius:12px;
  font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
  max-width:500px;margin:48px auto 72px;
}
#eiu-unified-login .ul-card{background:#fff;border-radius:var(--radius);box-shadow:0 8px 40px rgba(26,73,136,.12),0 2px 8px rgba(0,0,0,.06);overflow:hidden;border:1px solid var(--border);}
#eiu-unified-login .ul-head{background:var(--blue);padding:30px 36px 26px;text-align:center;}
#eiu-unified-login .ul-head-icon{width:58px;height:58px;border-radius:14px;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;margin:0 auto 13px;}
#eiu-unified-login .ul-head h1{font-family:'Cabinet Grotesk',sans-serif;font-size:20px;font-weight:800;color:#fff;margin:0 0 4px;letter-spacing:-.01em;}
#eiu-unified-login .ul-head p{color:rgba(255,255,255,.65);font-size:13px;margin:0;}
#eiu-unified-login .ul-role-tabs{display:flex;background:#f3f4f6;border-bottom:1px solid var(--border);gap:0;padding:10px 12px;gap:8px;}
#eiu-unified-login .ul-role-tab{flex:1;padding:12px 16px;text-align:center;font-size:13px;font-weight:700;color:var(--muted);cursor:pointer;border:none;background:#fff;border-radius:9px;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1.5px solid #e2e8f0;}
/* Researcher tab — EIU Blue */
#eiu-unified-login .ul-role-tab[id="ul-tab-researcher"]:hover{color:#1a4988;border-color:#b8d0f0;background:#eef4ff;}
#eiu-unified-login .ul-role-tab[id="ul-tab-researcher"].active{color:#fff;background:#1a4988;border-color:#1a4988;box-shadow:0 3px 10px rgba(26,73,136,.3);}
/* Reviewer tab — EIU Red */
#eiu-unified-login .ul-role-tab[id="ul-tab-reviewer"]:hover{color:#990000;border-color:#fecaca;background:#fef2f2;}
#eiu-unified-login .ul-role-tab[id="ul-tab-reviewer"].active{color:#fff;background:#990000;border-color:#990000;box-shadow:0 3px 10px rgba(153,0,0,.3);}
#eiu-unified-login .ul-role-tab-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:background .18s;}
#eiu-unified-login .ul-role-tab[id="ul-tab-researcher"] .ul-role-tab-icon{background:#eef4ff;color:#1a4988;}
#eiu-unified-login .ul-role-tab[id="ul-tab-researcher"].active .ul-role-tab-icon{background:rgba(255,255,255,.2);color:#fff;}
#eiu-unified-login .ul-role-tab[id="ul-tab-reviewer"] .ul-role-tab-icon{background:#fef2f2;color:#990000;}
#eiu-unified-login .ul-role-tab[id="ul-tab-reviewer"].active .ul-role-tab-icon{background:rgba(255,255,255,.2);color:#fff;}
#eiu-unified-login .ul-role-tab-label{display:flex;flex-direction:column;text-align:left;}
#eiu-unified-login .ul-role-tab-label strong{font-size:13px;font-weight:800;line-height:1.2;}
#eiu-unified-login .ul-role-tab-label span{font-size:10px;font-weight:500;opacity:.7;line-height:1.3;}
/* v2.0: Reviewer tab uses red, not teal — old overrides removed */

#eiu-unified-login .ul-body{padding:18px 36px 26px;}
#eiu-unified-login .ul-steps{display:flex;align-items:center;gap:0;margin-bottom:22px;}
#eiu-unified-login .ul-step{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#94a3b8;flex:1;}
#eiu-unified-login .ul-step.active{color:var(--blue);}
#eiu-unified-login .ul-step.done{color:#059669;}
#eiu-unified-login .ul-step-num{width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;background:#e2e8f0;color:#94a3b8;border:2px solid #e2e8f0;transition:all .2s;}
#eiu-unified-login .ul-step.active .ul-step-num{background:var(--blue);color:#fff;border-color:var(--blue);}
#eiu-unified-login .ul-step.done .ul-step-num{background:#059669;color:#fff;border-color:#059669;}
#eiu-unified-login .ul-step-line{flex:1;height:2px;background:#e2e8f0;margin:0 6px;}
#eiu-unified-login .ul-step-line.done{background:#059669;}
#eiu-unified-login .ul-alert{border-radius:9px;padding:13px 16px;margin-bottom:18px;font-size:13px;line-height:1.6;}
#eiu-unified-login .ul-alert-info{background:var(--info-bg);color:var(--info-tx);border:1px solid var(--info-br);}
#eiu-unified-login .ul-alert-warn{background:var(--warn-bg);color:var(--warn-tx);border:1px solid var(--warn-br);}
#eiu-unified-login .ul-alert-title{font-weight:800;display:block;margin-bottom:4px;}
#eiu-unified-login .ul-msg{border-radius:8px;padding:11px 14px;margin-bottom:18px;font-size:13px;font-weight:500;display:none;}
#eiu-unified-login .ul-msg.ok{background:var(--green-bg);color:var(--green-tx);border:1px solid var(--green-br);}
#eiu-unified-login .ul-msg.err{background:var(--err-bg);color:var(--err-tx);border:1px solid var(--err-br);}
#eiu-unified-login .ul-label{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#374151;margin-bottom:6px;}
#eiu-unified-login .ul-input{display:block;width:100%;background:#f8fafc;border:1.5px solid var(--border);border-radius:9px;padding:12px 14px;font-family:'DM Sans',sans-serif;font-size:14px;color:#1a2535;transition:border-color .18s,box-shadow .18s;outline:none;}
#eiu-unified-login .ul-input::placeholder{color:#94a3b8;}
#eiu-unified-login .ul-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(26,73,136,.1);background:#fff;}
#eiu-unified-login .ul-otp-input{display:block;width:100%;max-width:220px;margin:0 auto;background:#f8fafc;border:1.5px solid var(--border);border-radius:9px;padding:14px 18px;font-family:'Cabinet Grotesk',monospace;font-size:30px;font-weight:900;letter-spacing:.22em;text-align:center;color:var(--blue);outline:none;transition:border-color .18s,box-shadow .18s;}
#eiu-unified-login .ul-otp-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(26,73,136,.1);background:#fff;}
#eiu-unified-login .ul-pw-wrap{position:relative;}
#eiu-unified-login .ul-pw-wrap .ul-input{padding-right:44px;}
#eiu-unified-login .ul-pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px;transition:color .15s;}
#eiu-unified-login .ul-pw-toggle:hover{color:var(--blue);}
#eiu-unified-login .ul-meta-row{display:flex;align-items:center;justify-content:space-between;margin:14px 0 22px;font-size:13px;}
#eiu-unified-login .ul-remember{display:flex;align-items:center;gap:7px;cursor:pointer;color:var(--muted);}
#eiu-unified-login .ul-forgot{color:var(--blue);text-decoration:none;font-weight:600;}
#eiu-unified-login .ul-forgot:hover{color:var(--red);}
#eiu-unified-login .ul-submit,#eiu-unified-login .ul-btn-secondary{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px;border:none;border-radius:9px;font-family:'Cabinet Grotesk',sans-serif;font-size:15px;font-weight:800;cursor:pointer;letter-spacing:.01em;transition:background .18s,transform .12s;}
#eiu-unified-login .ul-submit{background:var(--blue);color:#fff;}
#eiu-unified-login .ul-submit:hover{background:var(--blue-dark);transform:translateY(-1px);}
#eiu-unified-login .ul-submit:active{transform:none;}
#eiu-unified-login .ul-submit:disabled{opacity:.6;cursor:not-allowed;transform:none;}
#eiu-unified-login .ul-btn-secondary{background:#f0f4f9;color:var(--blue);font-size:13px;padding:10px 18px;margin-top:10px;}
#eiu-unified-login .ul-btn-secondary:hover{background:#dde8f5;}
#eiu-unified-login .ul-resend-row{text-align:center;margin-top:12px;font-size:13px;color:var(--muted);}
#eiu-unified-login .ul-resend-btn{background:none;border:none;color:var(--blue);cursor:pointer;font-weight:600;font-size:13px;font-family:'DM Sans',sans-serif;padding:0;}
#eiu-unified-login .ul-resend-btn:disabled{color:#94a3b8;cursor:default;}
#eiu-unified-login .ul-footer{padding:0 36px 24px;text-align:center;font-size:12px;color:var(--muted);}
#eiu-unified-login .ul-footer a{color:var(--blue);font-weight:600;text-decoration:none;}
#eiu-unified-login .ul-footer a:hover{color:var(--red);}
#eiu-unified-login .ul-admin-note{font-size:11px;color:#94a3b8;text-align:center;margin-top:10px;}
.ul-spinner{display:inline-block;width:16px;height:16px;border:2.5px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:ul-spin .7s linear infinite;flex-shrink:0;}
.ul-spinner-dark{display:inline-block;width:14px;height:14px;border:2px solid rgba(26,73,136,.25);border-top-color:#1a4988;border-radius:50%;animation:ul-spin .7s linear infinite;}
@keyframes ul-spin{to{transform:rotate(360deg);}}
@media(max-width:520px){
  #eiu-unified-login{margin:20px 12px 48px;}
  #eiu-unified-login .ul-head,#eiu-unified-login .ul-body,#eiu-unified-login .ul-footer{padding-left:22px;padding-right:22px;}
}
</style>

<div id="eiu-unified-login">
  <div class="ul-card">

    <div class="ul-head">
      <div class="ul-head-icon">
        <i class="bi bi-journal-richtext" style="color:#fff;font-size:26px;"></i>
      </div>
      <h1><?php echo esc_html( get_option('eiu_rp_term_system_name','EIU JOURNAL SYSTEM') ); ?></h1>
      <p><?php esc_html_e( 'Sign in to access your account', 'eiu-rp' ); ?></p>
    </div>

    <div class="ul-role-tabs" role="tablist" style="border-bottom:none;">
      <button class="ul-role-tab <?php echo $initial_role==='researcher'?'active':''; ?>"
        id="ul-tab-researcher" role="tab" data-role="researcher"
        aria-selected="<?php echo $initial_role==='researcher'?'true':'false'; ?>"
        onclick="ulSwitchRole('researcher')">
        <div class="ul-role-tab-icon"><i class="bi bi-person-fill"></i></div>
        <div class="ul-role-tab-label">
          <strong><?php esc_html_e('Author','eiu-rp'); ?></strong>
          <span><?php esc_html_e('Submit & track articles','eiu-rp'); ?></span>
        </div>
      </button>
      <button class="ul-role-tab <?php echo $initial_role==='reviewer'?'active':''; ?>"
        id="ul-tab-reviewer" role="tab" data-role="reviewer"
        aria-selected="<?php echo $initial_role==='reviewer'?'true':'false'; ?>"
        onclick="ulSwitchRole('reviewer')">
        <div class="ul-role-tab-icon"><i class="bi bi-shield-check"></i></div>
        <div class="ul-role-tab-label">
          <strong><?php esc_html_e('Reviewer','eiu-rp'); ?></strong>
          <span><?php esc_html_e('Review assigned articles','eiu-rp'); ?></span>
        </div>
      </button>
    </div>

    <!-- Active role indicator banner — colour-coded per role -->
    <div style="padding:10px 36px 0;">
      <div id="ul-role-banner"
        style="border-radius:8px;padding:9px 14px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;transition:background .2s,color .2s,border-color .2s;
          background:<?php echo $initial_role==='researcher'?'#eef4ff':'#fef2f2'; ?>;
          color:<?php echo $initial_role==='researcher'?'#1a4988':'#990000'; ?>;
          border:1px solid <?php echo $initial_role==='researcher'?'#b8d0f0':'#fecaca'; ?>;">
        <i class="<?php echo $initial_role==='researcher'?'bi bi-person-fill':'bi bi-shield-check'; ?>" id="ul-role-banner-icon"></i>
        <span id="ul-role-banner-text">
          <?php echo $initial_role==='researcher'
            ? esc_html__('Signing in as Author','eiu-rp')
            : esc_html__('Signing in as Reviewer — OTP required','eiu-rp'); ?>
        </span>
      </div>
    </div>

    <div class="ul-body">
      <div class="ul-msg" id="ul-msg" role="alert" aria-live="polite"></div>
      <input type="hidden" id="ul-role-field" value="<?php echo esc_attr($initial_role); ?>">
      <input type="hidden" id="ul-redirect-to" value="<?php echo esc_attr($redirect_to); ?>">
      <input type="hidden" id="ul-otp-token" value="">
      <input type="hidden" id="ul-verified-email" value="">

      <!-- RESEARCHER PANEL -->
      <div id="ul-panel-researcher" <?php echo $initial_role!=='researcher'?'style="display:none;"':''; ?>>
        <div class="mb-3">
          <label class="ul-label" for="ul-r-email"><?php esc_html_e('Email Address','eiu-rp'); ?></label>
          <input type="email" class="ul-input" id="ul-r-email" autocomplete="username"
            placeholder="<?php esc_attr_e('your@email.com','eiu-rp'); ?>">
        </div>
        <div class="mb-2">
          <label class="ul-label" for="ul-r-pass"><?php esc_html_e('Password','eiu-rp'); ?></label>
          <div class="ul-pw-wrap">
            <input type="password" class="ul-input" id="ul-r-pass" autocomplete="current-password"
              placeholder="<?php esc_attr_e('••••••••','eiu-rp'); ?>">
            <button type="button" class="ul-pw-toggle" id="ul-r-pw-toggle"
              aria-label="<?php esc_attr_e('Toggle password visibility','eiu-rp'); ?>">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="ul-meta-row">
          <label class="ul-remember">
            <input type="checkbox" id="ul-r-remember" style="accent-color:#1a4988;cursor:pointer;">
            <?php esc_html_e('Keep me signed in','eiu-rp'); ?>
          </label>
          <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="ul-forgot">
            <?php esc_html_e('Forgot password?','eiu-rp'); ?>
          </a>
        </div>
        <button type="button" class="ul-submit" id="ul-r-submit">
          <i class="bi bi-box-arrow-in-right" id="ul-r-icon"></i>
          <span id="ul-r-text"><?php esc_html_e('Sign In','eiu-rp'); ?></span>
        </button>
      </div>

      <!-- REVIEWER PANEL -->
      <div id="ul-panel-reviewer" <?php echo $initial_role!=='reviewer'?'style="display:none;"':''; ?>>

        <!-- Step indicator -->
        <div class="ul-steps" id="ul-rv-steps">
          <div class="ul-step active" id="ul-step-1">
            <div class="ul-step-num">1</div>
            <span><?php esc_html_e('Verify Email','eiu-rp'); ?></span>
          </div>
          <div class="ul-step-line" id="ul-step-line-1"></div>
          <div class="ul-step" id="ul-step-2">
            <div class="ul-step-num">2</div>
            <span><?php esc_html_e('Enter Code','eiu-rp'); ?></span>
          </div>
          <div class="ul-step-line" id="ul-step-line-2"></div>
          <div class="ul-step" id="ul-step-3">
            <div class="ul-step-num">3</div>
            <span><?php esc_html_e('Sign In','eiu-rp'); ?></span>
          </div>
        </div>

        <!-- Step 1 -->
        <div id="ul-rv-step1">
          <div class="ul-alert ul-alert-info">
            <span class="ul-alert-title">
              <i class="bi bi-shield-check me-2"></i><?php esc_html_e('Reviewer Verification Required','eiu-rp'); ?>
            </span>
            <?php esc_html_e('For your security, reviewer logins require email verification. Please enter your registered reviewer email address to receive a one-time code.','eiu-rp'); ?>
          </div>
          <div class="mb-3">
            <label class="ul-label" for="ul-rv-email"><?php esc_html_e('Reviewer Email Address','eiu-rp'); ?></label>
            <input type="email" class="ul-input" id="ul-rv-email" autocomplete="username"
              placeholder="<?php esc_attr_e('e.g. myname@eiu.ac','eiu-rp'); ?>">
          </div>
          <button type="button" class="ul-submit" id="ul-rv-send-otp">
            <i class="bi bi-envelope-fill" id="ul-send-icon"></i>
            <span id="ul-send-text"><?php esc_html_e('Send Verification Code','eiu-rp'); ?></span>
          </button>
        </div>

        <!-- Step 2 -->
        <div id="ul-rv-step2" style="display:none;">
          <div class="ul-alert ul-alert-warn">
            <span class="ul-alert-title">
              <i class="bi bi-envelope-open me-2"></i><?php esc_html_e('Check Your Inbox','eiu-rp'); ?>
            </span>
            <span id="ul-otp-sent-to"></span><br>
            <span style="font-size:12px;"><?php esc_html_e('Please check both your inbox and spam/junk folder. The code expires in 5 minutes.','eiu-rp'); ?></span>
          </div>
          <div class="mb-3" style="text-align:center;">
            <label class="ul-label" for="ul-rv-otp" style="text-align:left;"><?php esc_html_e('6-Digit Code','eiu-rp'); ?></label>
            <input type="text" class="ul-otp-input" id="ul-rv-otp"
              maxlength="6" inputmode="numeric" pattern="[0-9]*"
              autocomplete="one-time-code" placeholder="000000">
            <p style="font-size:12px;color:#94a3b8;margin-top:6px;"><?php esc_html_e('Enter the 6-digit code sent to your email.','eiu-rp'); ?></p>
          </div>
          <button type="button" class="ul-submit" id="ul-rv-verify-otp">
            <i class="bi bi-check-circle-fill" id="ul-verify-icon"></i>
            <span id="ul-verify-text"><?php esc_html_e('Verify Code','eiu-rp'); ?></span>
          </button>
          <div class="ul-resend-row">
            <?php esc_html_e("Didn't receive the code?",'eiu-rp'); ?>
            <button type="button" class="ul-resend-btn" id="ul-rv-resend" disabled>
              <?php esc_html_e('Resend','eiu-rp'); ?>
              <span id="ul-resend-countdown" style="font-weight:400;color:#94a3b8;"></span>
            </button>
          </div>
          <button type="button" class="ul-btn-secondary" id="ul-rv-back-to-step1">
            <i class="bi bi-arrow-left"></i>
            <?php esc_html_e('Back — use a different email','eiu-rp'); ?>
          </button>
        </div>

        <!-- Step 3 -->
        <div id="ul-rv-step3" style="display:none;">
          <div class="ul-alert" style="background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;">
            <span class="ul-alert-title">
              <i class="bi bi-check-circle-fill me-2"></i><?php esc_html_e('Email Verified','eiu-rp'); ?>
            </span>
            <span id="ul-verified-display"></span>
          </div>
          <div class="mb-2">
            <label class="ul-label" for="ul-rv-pass"><?php esc_html_e('Password','eiu-rp'); ?></label>
            <div class="ul-pw-wrap">
              <input type="password" class="ul-input" id="ul-rv-pass" autocomplete="current-password"
                placeholder="<?php esc_attr_e('••••••••','eiu-rp'); ?>">
              <button type="button" class="ul-pw-toggle" id="ul-rv-pw-toggle"
                aria-label="<?php esc_attr_e('Toggle password visibility','eiu-rp'); ?>">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <div class="ul-meta-row">
            <label class="ul-remember">
              <input type="checkbox" id="ul-rv-remember" style="accent-color:#1a4988;cursor:pointer;">
              <?php esc_html_e('Keep me signed in','eiu-rp'); ?>
            </label>
            <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="ul-forgot">
              <?php esc_html_e('Forgot password?','eiu-rp'); ?>
            </a>
          </div>
          <button type="button" class="ul-submit" id="ul-rv-submit">
            <i class="bi bi-box-arrow-in-right" id="ul-rv-icon"></i>
            <span id="ul-rv-text"><?php esc_html_e('Sign In','eiu-rp'); ?></span>
          </button>
        </div>

      </div><!-- /reviewer panel -->
    </div><!-- .ul-body -->

    <div class="ul-footer">
      <a href="<?php echo esc_url(home_url()); ?>">
        <i class="bi bi-arrow-left me-1"></i><?php esc_html_e('Back to homepage','eiu-rp'); ?>
      </a>
      <p class="ul-admin-note"><?php esc_html_e('Account creation is managed by the administrator.','eiu-rp'); ?></p>
    </div>
  </div>
</div>

<script>
(function(){
'use strict';
var ajax=<?php echo wp_json_encode($ajax_url); ?>;
var nonceLogin='';
var nonceOtp='';
var loginNoncesReady=false;

/* Fetch fresh nonces — never relies on cached page HTML */
function fetchLoginNonces(callback){
  if(loginNoncesReady){ if(callback) callback(); return; }
  var fd=new FormData();
  fd.append('action','eiu_rp_get_nonce');
  fd.append('for','login');
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(res){
      if(res.success){
        nonceLogin=res.data.login_nonce||'';
        nonceOtp=res.data.otp_nonce||'';
        loginNoncesReady=true;
      }
      if(callback) callback();
    })
    .catch(function(){ if(callback) callback(); });
}

function g(id){return document.getElementById(id);}
function show(id){var e=g(id);if(e)e.style.display='';}
function hide(id){var e=g(id);if(e)e.style.display='none';}
function showMsg(t,ok){var e=g('ul-msg');if(!e)return;e.textContent=t;e.className='ul-msg '+(ok?'ok':'err');e.style.display='block';e.scrollIntoView({behavior:'smooth',block:'nearest'});}
function clearMsg(){var e=g('ul-msg');if(e){e.style.display='none';e.className='ul-msg';}}

function btnLoad(iconId,textId,btnId,txt,white){
  var b=g(btnId);if(b)b.disabled=true;
  var ic=g(iconId);if(ic)ic.outerHTML='<span class="'+(white?'ul-spinner':'ul-spinner-dark')+'" id="'+iconId+'"></span>';
  var tx=g(textId);if(tx)tx.textContent=txt;
}
function btnReset(cls,iconId,textId,btnId,txt){
  var b=g(btnId);if(b)b.disabled=false;
  var s=g(iconId);if(s)s.outerHTML='<i class="bi '+cls+'" id="'+iconId+'"></i>';
  var tx=g(textId);if(tx)tx.textContent=txt;
}

window.ulSwitchRole=function(role){
  g('ul-role-field').value=role;
  document.querySelectorAll('.ul-role-tab').forEach(function(t){
    var a=t.id==='ul-tab-'+role;
    t.classList.toggle('active',a);
    t.setAttribute('aria-selected',a?'true':'false');
  });
  role==='researcher'?(show('ul-panel-researcher'),hide('ul-panel-reviewer')):(hide('ul-panel-researcher'),show('ul-panel-reviewer'));
  /* Update colour-coded role banner */
  var banner=g('ul-role-banner');
  var bannerIcon=g('ul-role-banner-icon');
  var bannerText=g('ul-role-banner-text');
  if(banner){
    if(role==='researcher'){
      banner.style.background='#eef4ff';banner.style.color='#1a4988';banner.style.borderColor='#b8d0f0';
    } else {
      banner.style.background='#fef2f2';banner.style.color='#990000';banner.style.borderColor='#fecaca';
    }
  }
  if(bannerIcon) bannerIcon.className=role==='researcher'?'bi bi-person-fill':'bi bi-shield-check';
  if(bannerText) bannerText.textContent=role==='researcher'
    ? <?php echo wp_json_encode(__('Signing in as Author','eiu-rp')); ?>
    : <?php echo wp_json_encode(__('Signing in as Reviewer — OTP required','eiu-rp')); ?>;
  clearMsg();
};

/* Researcher pw toggle */
var rpt=g('ul-r-pw-toggle'),rpi=g('ul-r-pass');
if(rpt&&rpi)rpt.addEventListener('click',function(){var s=rpi.type==='password';rpi.type=s?'text':'password';this.querySelector('i').className=s?'bi bi-eye-slash':'bi bi-eye';});

/* Researcher submit */
g('ul-r-submit').addEventListener('click',function(){
  var email=(g('ul-r-email').value||'').trim();
  var pw=g('ul-r-pass').value||'';
  var rem=g('ul-r-remember').checked;
  var redir=g('ul-redirect-to').value;
  if(!email||!pw){showMsg(<?php echo wp_json_encode(__('Please enter your email and password.','eiu-rp')); ?>,false);return;}
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){showMsg(<?php echo wp_json_encode(__('Please enter a valid email address.','eiu-rp')); ?>,false);return;}
  btnLoad('ul-r-icon','ul-r-text','ul-r-submit',<?php echo wp_json_encode(__('Signing in\u2026','eiu-rp')); ?>,true);
  fetchLoginNonces(function(){
  var fd=new FormData();
  fd.append('action','eiu_rp_unified_login');fd.append('nonce',nonceLogin);
  fd.append('email',email);fd.append('password',pw);fd.append('remember',rem?'1':'0');
  fd.append('role_hint','researcher');fd.append('redirect_to',redir);
  fetch(ajax,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
    if(res.success){showMsg(res.data.message||<?php echo wp_json_encode(__('Logged in! Redirecting\u2026','eiu-rp')); ?>,true);setTimeout(function(){window.location.href=res.data.redirect;},600);}
    else{showMsg((res.data&&res.data.message)||<?php echo wp_json_encode(__('Login failed.','eiu-rp')); ?>,false);btnReset('bi-box-arrow-in-right','ul-r-icon','ul-r-text','ul-r-submit',<?php echo wp_json_encode(__('Sign In','eiu-rp')); ?>);}
  }).catch(function(){showMsg(<?php echo wp_json_encode(__('Network error.','eiu-rp')); ?>,false);btnReset('bi-box-arrow-in-right','ul-r-icon','ul-r-text','ul-r-submit',<?php echo wp_json_encode(__('Sign In','eiu-rp')); ?>);});
  }); /* end fetchLoginNonces */
});
['ul-r-email','ul-r-pass'].forEach(function(id){var e=g(id);if(e)e.addEventListener('keydown',function(ev){if(ev.key==='Enter')g('ul-r-submit').click();});});

/* ── REVIEWER OTP FLOW ── */
var rvToken='';
var resendTimer=null;

function setStep(n){
  for(var i=1;i<=3;i++){var s=g('ul-step-'+i);if(!s)continue;s.classList.remove('active','done');if(i<n)s.classList.add('done');else if(i===n)s.classList.add('active');}
  for(var j=1;j<=2;j++){var l=g('ul-step-line-'+j);if(!l)continue;l.classList.toggle('done',j<n);}
}

function startCountdown(secs){
  var btn=g('ul-rv-resend'),cd=g('ul-resend-countdown');
  if(!btn||!cd)return;
  btn.disabled=true;
  var rem=secs;
  function tick(){rem--;if(rem<=0){btn.disabled=false;cd.textContent='';}else{cd.textContent=' ('+rem+'s)';resendTimer=setTimeout(tick,1000);}}
  cd.textContent=' ('+rem+'s)';resendTimer=setTimeout(tick,1000);
}

/* Reviewer pw toggle */
var rvpt=g('ul-rv-pw-toggle'),rvpi=g('ul-rv-pass');
if(rvpt&&rvpi)rvpt.addEventListener('click',function(){var s=rvpi.type==='password';rvpi.type=s?'text':'password';this.querySelector('i').className=s?'bi bi-eye-slash':'bi bi-eye';});

/* OTP digits only */
var oi=g('ul-rv-otp');
if(oi)oi.addEventListener('input',function(){this.value=this.value.replace(/[^0-9]/g,'').slice(0,6);});

function doSendOtp(email){
  btnLoad('ul-send-icon','ul-send-text','ul-rv-send-otp',<?php echo wp_json_encode(__('Sending…','eiu-rp')); ?>,true);
  clearMsg();
  var fd=new FormData();fd.append('action','eiu_rp_send_reviewer_otp');fd.append('nonce',nonceOtp);fd.append('email',email);  /* nonce refreshed by fetchLoginNonces */
  fd.set ? fd.set('nonce',nonceOtp) : (fd.append('nonce',nonceOtp));
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){
      /* Clone the response so we can read the body twice if JSON parsing fails */
      var clone=r.clone();
      return r.json().catch(function(){
        /* Non-JSON response — server likely returned a PHP error.
           Read as text so we can surface something useful. */
        return clone.text().then(function(txt){
          return {success:false,data:{message:<?php echo wp_json_encode(__('Server error. Please try again or contact the administrator.','eiu-rp')); ?>}};
        });
      });
    })
    .then(function(res){
      if(res.success){
        g('ul-verified-email').value=email;
        g('ul-otp-sent-to').textContent=<?php echo wp_json_encode(__('A verification code was sent to:','eiu-rp')); ?>+' '+email;
        if(oi)oi.value='';
        hide('ul-rv-step1');show('ul-rv-step2');setStep(2);clearMsg();
        if(oi)oi.focus();startCountdown(60);
      } else {showMsg((res.data&&res.data.message)||<?php echo wp_json_encode(__('Failed to send code.','eiu-rp')); ?>,false);}
      btnReset('bi-envelope-fill','ul-send-icon','ul-send-text','ul-rv-send-otp',<?php echo wp_json_encode(__('Send Verification Code','eiu-rp')); ?>);
    }).catch(function(){
      showMsg(<?php echo wp_json_encode(__('Request failed. Please check your connection and try again.','eiu-rp')); ?>,false);
      btnReset('bi-envelope-fill','ul-send-icon','ul-send-text','ul-rv-send-otp',<?php echo wp_json_encode(__('Send Verification Code','eiu-rp')); ?>);
    });
}

g('ul-rv-send-otp').addEventListener('click',function(){
  var email=(g('ul-rv-email').value||'').trim();
  if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){showMsg(<?php echo wp_json_encode(__('Please enter a valid email address.','eiu-rp')); ?>,false);return;}
  doSendOtp(email);
});
g('ul-rv-email').addEventListener('keydown',function(e){if(e.key==='Enter')g('ul-rv-send-otp').click();});

g('ul-rv-resend').addEventListener('click',function(){
  var email=g('ul-verified-email').value;if(!email)return;
  if(resendTimer)clearTimeout(resendTimer);doSendOtp(email);
});

g('ul-rv-back-to-step1').addEventListener('click',function(){
  hide('ul-rv-step2');show('ul-rv-step1');setStep(1);clearMsg();
  rvToken='';g('ul-otp-token').value='';
});

g('ul-rv-verify-otp').addEventListener('click',function(){
  var code=(g('ul-rv-otp').value||'').trim();
  var email=g('ul-verified-email').value;
  if(code.length!==6){showMsg(<?php echo wp_json_encode(__('Please enter the 6-digit code.','eiu-rp')); ?>,false);return;}
  btnLoad('ul-verify-icon','ul-verify-text','ul-rv-verify-otp',<?php echo wp_json_encode(__('Verifying\u2026','eiu-rp')); ?>,true);
  clearMsg();
  /* Refresh nonce before verifying */
  fetchLoginNonces(function(){
  var fd=new FormData();fd.append('action','eiu_rp_verify_reviewer_otp');fd.append('nonce',nonceOtp);fd.append('email',email);fd.append('otp',code);
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){var c=r.clone();return r.json().catch(function(){return c.text().then(function(){return {success:false,data:{message:<?php echo wp_json_encode(__('Server error. Please try again.','eiu-rp')); ?>}};});});})
    .then(function(res){
    if(res.success){
      rvToken=res.data.token;g('ul-otp-token').value=rvToken;
      g('ul-verified-display').textContent=<?php echo wp_json_encode(__('Verified:','eiu-rp')); ?>+' '+email;
      hide('ul-rv-step2');show('ul-rv-step3');setStep(3);clearMsg();
      var pf=g('ul-rv-pass');if(pf)pf.focus();
    } else {
      showMsg((res.data&&res.data.message)||<?php echo wp_json_encode(__('Invalid code. Please try again.','eiu-rp')); ?>,false);
      btnReset('bi-check-circle-fill','ul-verify-icon','ul-verify-text','ul-rv-verify-otp',<?php echo wp_json_encode(__('Verify Code','eiu-rp')); ?>);
    }
  }).catch(function(){showMsg(<?php echo wp_json_encode(__('Network error.','eiu-rp')); ?>,false);btnReset('bi-check-circle-fill','ul-verify-icon','ul-verify-text','ul-rv-verify-otp',<?php echo wp_json_encode(__('Verify Code','eiu-rp')); ?>);});
  }); /* end fetchLoginNonces */
});
g('ul-rv-otp').addEventListener('keydown',function(e){if(e.key==='Enter')g('ul-rv-verify-otp').click();});

g('ul-rv-submit').addEventListener('click',function(){
  var email=g('ul-verified-email').value;
  var pw=g('ul-rv-pass').value||'';
  var rem=g('ul-rv-remember').checked;
  var redir=g('ul-redirect-to').value;
  var token=g('ul-otp-token').value;
  if(!pw){showMsg(<?php echo wp_json_encode(__('Please enter your password.','eiu-rp')); ?>,false);return;}
  if(!token){showMsg(<?php echo wp_json_encode(__('Verification session expired. Please start again.','eiu-rp')); ?>,false);return;}
  btnLoad('ul-rv-icon','ul-rv-text','ul-rv-submit',<?php echo wp_json_encode(__('Signing in\u2026','eiu-rp')); ?>,true);
  clearMsg();
  var fd=new FormData();
  fd.append('action','eiu_rp_unified_login');fd.append('nonce',nonceLogin);
  fd.append('email',email);fd.append('password',pw);fd.append('remember',rem?'1':'0');
  fd.append('role_hint','reviewer');fd.append('otp_token',token);fd.append('redirect_to',redir);
  fetch(ajax,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
    if(res.success){showMsg(res.data.message||<?php echo wp_json_encode(__('Logged in! Redirecting\u2026','eiu-rp')); ?>,true);setTimeout(function(){window.location.href=res.data.redirect;},600);}
    else{showMsg((res.data&&res.data.message)||<?php echo wp_json_encode(__('Login failed.','eiu-rp')); ?>,false);btnReset('bi-box-arrow-in-right','ul-rv-icon','ul-rv-text','ul-rv-submit',<?php echo wp_json_encode(__('Sign In','eiu-rp')); ?>);}
  }).catch(function(){showMsg(<?php echo wp_json_encode(__('Network error.','eiu-rp')); ?>,false);btnReset('bi-box-arrow-in-right','ul-rv-icon','ul-rv-text','ul-rv-submit',<?php echo wp_json_encode(__('Sign In','eiu-rp')); ?>);});
});
g('ul-rv-pass').addEventListener('keydown',function(e){if(e.key==='Enter')g('ul-rv-submit').click();});

/* role-reviewer teal class removed v2.0 — role identity handled by ul-role-banner */

/* Pre-fetch login nonces passively so first interaction is instant */
fetchLoginNonces(null);
}());
</script>
