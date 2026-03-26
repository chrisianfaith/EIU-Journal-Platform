<?php
/**
 * Frontend: Researcher Login / Registration Page.
 *
 * URL: /researcher/
 * Shortcode: [eiu_researcher_login]
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_style( 'bootstrap-icons-eiu', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', array(), '1.11.3' );

// If already logged in, redirect to researcher dashboard
if ( is_user_logged_in() ) {
    $dashboard_id  = get_option( 'eiu_rp_researcher_dashboard_page_id' );
    $dashboard_url = $dashboard_id ? get_permalink( $dashboard_id ) : home_url();
    wp_safe_redirect( $dashboard_url );
    exit;
}

$login_nonce    = wp_create_nonce( 'eiu_researcher_login' );
$register_nonce = wp_create_nonce( 'eiu_researcher_register' );
$ajax_url       = admin_url( 'admin-ajax.php' );
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
#eiu-researcher-auth{
  --bb:#1a4988;--bbd:#123266;--br:#9a0805;
  max-width:480px;margin:48px auto 64px;
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
}
#eiu-researcher-auth .auth-card{background:#fff;border-radius:14px;box-shadow:0 4px 32px rgba(0,0,0,.1);overflow:hidden;}
#eiu-researcher-auth .auth-header{background:linear-gradient(135deg,var(--bbd),var(--bb));padding:28px 32px;text-align:center;}
#eiu-researcher-auth .auth-header h1{color:#fff;font-size:22px;font-weight:800;margin:10px 0 4px;}
#eiu-researcher-auth .auth-header p{color:rgba(255,255,255,.75);font-size:13px;margin:0;}
#eiu-researcher-auth .auth-tabs{display:flex;background:#f8f9fa;border-bottom:1px solid #e5e7eb;}
#eiu-researcher-auth .auth-tab{flex:1;padding:13px;text-align:center;font-weight:700;font-size:14px;cursor:pointer;color:#6b7280;border:none;background:none;transition:all .15s;}
#eiu-researcher-auth .auth-tab.active{color:var(--bb);background:#fff;box-shadow:inset 0 -2px 0 var(--bb);}
#eiu-researcher-auth .auth-body{padding:28px 32px;}
#eiu-researcher-auth .form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;}
#eiu-researcher-auth .form-control:focus{border-color:var(--bb);box-shadow:0 0 0 3px rgba(26,73,136,.1);}
#eiu-researcher-auth .btn-auth{width:100%;padding:12px;background:linear-gradient(135deg,var(--br),#720000);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:800;cursor:pointer;transition:opacity .15s;display:flex;align-items:center;justify-content:center;gap:8px;}
#eiu-researcher-auth .btn-auth:hover{opacity:.9;}
#eiu-researcher-auth .btn-auth:disabled{opacity:.6;cursor:not-allowed;}
#eiu-researcher-auth .eiu-msg{font-size:13px;border-radius:7px;padding:10px 14px;margin-bottom:14px;display:none;}
#eiu-researcher-auth .eiu-msg.ok{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
#eiu-researcher-auth .eiu-msg.err{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;}
#eiu-researcher-auth .auth-footer{text-align:center;margin-top:20px;font-size:13px;color:#6b7280;}
#eiu-researcher-auth .auth-footer a{color:var(--bb);font-weight:600;text-decoration:none;}
#eiu-researcher-auth .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;padding:0;}
</style>

<div id="eiu-researcher-auth">
  <div class="auth-card">
    <div class="auth-header">
      <i class="bi bi-journal-richtext" style="font-size:2.2rem;color:rgba(255,255,255,.8);"></i>
      <h1><?php echo esc_html( get_option('eiu_rp_term_system_name','EIU JOURNAL SYSTEM') ); ?></h1>
      <p><?php esc_html_e( 'Author Account', 'eiu-rp' ); ?></p>
    </div>

    <div class="auth-tabs">
      <button class="auth-tab active" id="tab-login" onclick="eiu_auth_tab('login')">
        <i class="bi bi-box-arrow-in-right me-1"></i><?php esc_html_e( 'Sign In', 'eiu-rp' ); ?>
      </button>
<!-- Account creation disabled: admin-only (v2.0) -->
    </div>

    <div class="auth-body">

      <!-- ── Login Form ── -->
      <div id="eiu-login-form">
        <div class="eiu-msg" id="login-msg"></div>
        <div class="mb-3">
          <label class="form-label"><i class="bi bi-envelope me-1"></i><?php esc_html_e( 'Email Address', 'eiu-rp' ); ?></label>
          <input type="email" class="form-control" id="login-email" autocomplete="username" placeholder="<?php esc_attr_e( 'your@email.com', 'eiu-rp' ); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label"><i class="bi bi-lock me-1"></i><?php esc_html_e( 'Password', 'eiu-rp' ); ?></label>
          <div style="position:relative;">
            <input type="password" class="form-control" id="login-pass" autocomplete="current-password" placeholder="<?php esc_attr_e( 'Enter your password', 'eiu-rp' ); ?>" style="padding-right:42px;">
            <button type="button" class="pw-toggle" onclick="eiu_tog('login-pass',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-3" style="font-size:13px;">
          <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
            <input type="checkbox" id="login-remember"> <?php esc_html_e( 'Keep me signed in', 'eiu-rp' ); ?>
          </label>
          <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="color:#1a4988;font-weight:600;text-decoration:none;">
            <?php esc_html_e( 'Forgot password?', 'eiu-rp' ); ?>
          </a>
        </div>
        <button type="button" class="btn-auth" id="login-btn">
          <i class="bi bi-box-arrow-in-right"></i><?php esc_html_e( 'Sign In', 'eiu-rp' ); ?>
        </button>
      </div>

      <!-- Register form disabled: only admins can create accounts -->
      <div id="eiu-register-form" style="display:none !important;">
        <div class="eiu-msg" id="register-msg"></div>
        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label"><?php esc_html_e( 'First Name', 'eiu-rp' ); ?> <span style="color:#9a0805;">*</span></label>
            <input type="text" class="form-control" id="reg-fname" placeholder="<?php esc_attr_e( 'First name', 'eiu-rp' ); ?>">
          </div>
          <div class="col-6">
            <label class="form-label"><?php esc_html_e( 'Last Name', 'eiu-rp' ); ?></label>
            <input type="text" class="form-control" id="reg-lname" placeholder="<?php esc_attr_e( 'Last name', 'eiu-rp' ); ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label"><?php esc_html_e( 'Email Address', 'eiu-rp' ); ?> <span style="color:#9a0805;">*</span></label>
          <input type="email" class="form-control" id="reg-email" placeholder="<?php esc_attr_e( 'your@email.com', 'eiu-rp' ); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label"><?php esc_html_e( 'Password', 'eiu-rp' ); ?> <span style="color:#9a0805;">*</span></label>
          <div style="position:relative;">
            <input type="password" class="form-control" id="reg-pass" placeholder="<?php esc_attr_e( 'Min. 8 characters', 'eiu-rp' ); ?>" style="padding-right:42px;">
            <button type="button" class="pw-toggle" onclick="eiu_tog('reg-pass',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button type="button" class="btn-auth" id="register-btn">
          <i class="bi bi-person-check"></i><?php esc_html_e( 'Create Account', 'eiu-rp' ); ?>
        </button>
        <p style="font-size:11px;color:#9ca3af;text-align:center;margin-top:12px;">
          <?php esc_html_e( 'By creating an account you agree to our terms and privacy policy.', 'eiu-rp' ); ?>
        </p>
      </div>

    </div><!-- .auth-body -->

    <div class="auth-footer" style="padding:0 32px 20px;">
      <a href="<?php echo esc_url( home_url() ); ?>"><i class="bi bi-arrow-left me-1"></i><?php esc_html_e( 'Back to homepage', 'eiu-rp' ); ?></a>
    <p style="text-align:center;font-size:11px;color:var(--c-muted,#9ca3af);margin-top:12px;">
      <?php esc_html_e( 'Account creation is managed by the administrator.', 'eiu-rp' ); ?>
    </p>
    </div>
  </div>
</div>

<script>
var eiu_ajax='<?php echo esc_js($ajax_url); ?>';
var eiu_login_nonce='<?php echo esc_js($login_nonce); ?>';
var eiu_reg_nonce='<?php echo esc_js($register_nonce); ?>';

function eiu_auth_tab(t){
  document.getElementById('eiu-login-form').style.display=t==='login'?'block':'none';
  document.getElementById('eiu-register-form').style.display=t==='register'?'block':'none';
  document.getElementById('tab-login').className='auth-tab'+(t==='login'?' active':'');
  document.getElementById('tab-register').className='auth-tab'+(t==='register'?' active':'');
}
function eiu_tog(id,btn){
  var i=document.getElementById(id);
  var ic=btn.querySelector('i');
  i.type=i.type==='password'?'text':'password';
  ic.className=i.type==='password'?'bi bi-eye':'bi bi-eye-slash';
}
function eiu_show_msg(id,msg,ok){
  var el=document.getElementById(id);
  el.textContent=msg;
  el.className='eiu-msg '+(ok?'ok':'err');
  el.style.display='block';
}

// Login
document.getElementById('login-btn').addEventListener('click',function(){
  var email=document.getElementById('login-email').value.trim();
  var pass=document.getElementById('login-pass').value;
  var remember=document.getElementById('login-remember').checked;
  var btn=this;
  if(!email||!pass){eiu_show_msg('login-msg','<?php echo esc_js(__('Please fill in all fields.','eiu-rp')); ?>',false);return;}
  btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
  var fd=new FormData();
  fd.append('action','eiu_rp_researcher_login');
  fd.append('nonce',eiu_login_nonce);
  fd.append('email',email);fd.append('password',pass);fd.append('remember',remember?1:0);
  fetch(eiu_ajax,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    if(res.success){
      eiu_show_msg('login-msg',res.data.message,true);
      setTimeout(()=>{window.location.href=res.data.redirect||window.location.href;},800);
    } else {
      eiu_show_msg('login-msg',(res.data&&res.data.message)||'<?php echo esc_js(__('Login failed.','eiu-rp')); ?>',false);
      btn.disabled=false;btn.innerHTML='<i class="bi bi-box-arrow-in-right"></i><?php echo esc_js(__('Sign In','eiu-rp')); ?>';
    }
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="bi bi-box-arrow-in-right"></i><?php echo esc_js(__('Sign In','eiu-rp')); ?>';});
});
// Enter key in login fields
['login-email','login-pass'].forEach(function(id){
  document.getElementById(id).addEventListener('keydown',function(e){if(e.key==='Enter')document.getElementById('login-btn').click();});
});

// Register
document.getElementById('register-btn').addEventListener('click',function(){
  var fn=document.getElementById('reg-fname').value.trim();
  var ln=document.getElementById('reg-lname').value.trim();
  var email=document.getElementById('reg-email').value.trim();
  var pass=document.getElementById('reg-pass').value;
  var btn=this;
  if(!fn||!email||!pass){eiu_show_msg('register-msg','<?php echo esc_js(__('Please fill in all required fields.','eiu-rp')); ?>',false);return;}
  btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
  var fd=new FormData();
  fd.append('action','eiu_rp_researcher_register');
  fd.append('nonce',eiu_reg_nonce);
  fd.append('first_name',fn);fd.append('last_name',ln);fd.append('email',email);fd.append('password',pass);
  fetch(eiu_ajax,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    if(res.success){
      eiu_show_msg('register-msg',res.data.message,true);
      setTimeout(()=>eiu_auth_tab('login'),2000);
      btn.disabled=false;btn.innerHTML='<i class="bi bi-person-check"></i><?php echo esc_js(__('Create Account','eiu-rp')); ?>';
    } else {
      eiu_show_msg('register-msg',(res.data&&res.data.message)||'<?php echo esc_js(__('Registration failed.','eiu-rp')); ?>',false);
      btn.disabled=false;btn.innerHTML='<i class="bi bi-person-check"></i><?php echo esc_js(__('Create Account','eiu-rp')); ?>';
    }
  }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="bi bi-person-check"></i><?php echo esc_js(__('Create Account','eiu-rp')); ?>';});
});
</script>
