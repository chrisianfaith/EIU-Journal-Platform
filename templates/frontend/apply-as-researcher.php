<?php
/**
 * Frontend: Apply as Researcher.
 *
 * Step 1: Enter email → OTP sent
 * Step 2: Verify 6-digit OTP
 * Step 3: Complete and submit application form
 *
 * Shortcode: [eiu_apply_researcher]
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Redirect already-logged-in researchers to their dashboard
if ( is_user_logged_in() ) {
    $u     = wp_get_current_user();
    $roles = (array) $u->roles;
    if ( in_array( 'eiu_researcher', $roles, true ) ) {
        $dash_id = get_option( 'eiu_rp_researcher_dashboard_page_id' );
        $url     = $dash_id ? get_permalink( $dash_id ) : home_url( '/researcher-dashboard/' );
        wp_safe_redirect( esc_url_raw( $url ) );
        exit;
    }
}

wp_enqueue_style(
    'bootstrap-icons-eiu',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
    array(), '1.11.3'
);

// Nonces are fetched via AJAX on page load to avoid caching issues.
// Do NOT embed nonces in static HTML here.
$ajax_url   = admin_url( 'admin-ajax.php' );
?>
<style>
#eiu-apply{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:720px;margin:0 auto;padding:16px;}
#eiu-apply *{box-sizing:border-box;}
.ap-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:28px 32px;box-shadow:0 2px 10px rgba(0,0,0,.06);}
.ap-steps{display:flex;align-items:center;gap:0;margin-bottom:28px;}
.ap-step{display:flex;flex-direction:column;align-items:center;flex:1;}
.ap-step-circle{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;transition:.3s;}
.ap-step-circle.waiting{background:#f3f4f6;color:#9ca3af;border:2px solid #e5e7eb;}
.ap-step-circle.active{background:#1a4988;color:#fff;box-shadow:0 0 0 4px rgba(26,73,136,.15);}
.ap-step-circle.done{background:#059669;color:#fff;}
.ap-step-label{font-size:11px;font-weight:600;color:#6b7280;margin-top:5px;text-align:center;}
.ap-step-line{flex:1;height:2px;background:#e5e7eb;margin:0 -1px;margin-top:-22px;}
.ap-step-line.done{background:#059669;}
.ap-msg{padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:16px;display:none;}
.ap-msg.ok{background:#ecfdf5;color:#065f46;}
.ap-msg.err{background:#fef2f2;color:#991b1b;}
.ap-label{display:block;font-weight:700;font-size:13px;color:#374151;margin-bottom:5px;}
.ap-req{color:#e53e3e;}
.ap-input{width:100%;padding:10px 13px;border:1.5px solid #d1d5db;border-radius:7px;font-size:14px;color:#1a2535;transition:.2s;background:#fff;}
.ap-input:focus{border-color:#1a4988;box-shadow:0 0 0 3px rgba(26,73,136,.1);outline:none;}
.ap-select{appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'><path d='M7 7l3 3 3-3'/></svg>");background-repeat:no-repeat;background-position:right 10px center;padding-right:36px;}
.ap-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:8px;font-weight:700;font-size:14px;border:none;cursor:pointer;transition:.2s;}
.ap-btn-primary{background:#1a4988;color:#fff;}
.ap-btn-primary:hover{background:#153d72;}
.ap-btn-primary:disabled{opacity:.6;cursor:not-allowed;}
.ap-btn-ghost{background:#f3f4f6;color:#374151;}
.ap-btn-ghost:hover{background:#e5e7eb;}
.ap-otp-input{font-size:32px;font-weight:900;letter-spacing:.25em;text-align:center;font-family:monospace;width:100%;max-width:260px;padding:14px 20px;border:1.5px solid #d1d5db;border-radius:10px;}
.ap-otp-input:focus{border-color:#1a4988;box-shadow:0 0 0 3px rgba(26,73,136,.1);outline:none;}
.ap-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:540px){.ap-row{grid-template-columns:1fr;}}
.ap-section-title{font-size:15px;font-weight:800;color:#1a2535;margin:24px 0 14px;padding-bottom:8px;border-bottom:2px solid #eef4ff;}
.ap-section-title:first-child{margin-top:0;}
.ap-file-area{border:1.5px dashed #d1d5db;border-radius:8px;padding:16px;text-align:center;cursor:pointer;transition:.2s;background:#fafafa;}
.ap-file-area:hover{border-color:#1a4988;background:#eef4ff;}
.ap-file-name{font-size:12px;color:#6b7280;margin-top:6px;}
/* ── Upload card (new compact UI) ──────────────────── */
.ap-upload-card{
  background:#fff;
  border:1.5px solid #e5e7eb;
  border-radius:12px;
  padding:18px 20px;
  cursor:pointer;
  transition:border-color .18s,box-shadow .18s;
  user-select:none;
  min-height:72px;
  display:flex;
  align-items:center;
}
.ap-upload-card:hover,.ap-upload-card.ap-dz-hover{
  border-color:#1a4988;
  box-shadow:0 0 0 3px rgba(26,73,136,.08);
}
.ap-upload-card.ap-uc-selected{
  border-color:#10b981;
  background:#f0fdf4;
  cursor:default;
}
/* idle layout */
.ap-uc-idle{display:flex;align-items:center;gap:14px;width:100%;}
.ap-uc-icon-wrap{
  width:48px;height:48px;border-radius:10px;
  background:#eef4ff;border:1.5px solid #c7d9f8;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.ap-uc-icon{font-size:22px;color:#1a4988;}
.ap-uc-label{font-size:14px;font-weight:600;color:#374151;}
/* done layout */
.ap-uc-done{display:flex;align-items:center;gap:14px;width:100%;}
.ap-uc-badge{
  width:48px;height:48px;border-radius:10px;
  background:#d1fae5;border:1.5px solid #6ee7b7;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.ap-uc-badge-icon{font-size:24px;color:#059669;}
.ap-uc-info{display:flex;flex-direction:column;gap:2px;flex:1;min-width:0;}
.ap-uc-fname{font-size:14px;font-weight:700;color:#1a2535;word-break:break-all;}
.ap-uc-complete{font-size:13px;color:#2563eb;font-weight:600;}
.ap-uc-remove{
  background:none;border:none;color:#9ca3af;
  font-size:20px;line-height:1;cursor:pointer;
  padding:4px;border-radius:6px;flex-shrink:0;
  transition:color .15s,background .15s;
}
.ap-uc-remove:hover{color:#dc2626;background:#fef2f2;}
.ap-declaration{display:flex;align-items:flex-start;gap:10px;background:#f8fafc;border:1.5px solid #e5e7eb;border-radius:8px;padding:14px 16px;}
.ap-success{text-align:center;padding:40px 20px;display:none;}
.ap-drop-zone{border:2px dashed #d1d5db;border-radius:10px;padding:24px 20px;text-align:center;cursor:pointer;transition:.2s;background:#fafafa;position:relative;}
.ap-drop-zone:hover,.ap-dz-hover{border-color:#1a4988;background:#eef4ff;}
.ap-drop-zone:has(#ap-cv-name[style*="display:block"]),.ap-drop-zone:has(#ap-research-name[style*="display:block"]){border-color:#10b981;background:#f0fdf4;}
@media(max-width:540px){
  .ap-row{grid-template-columns:1fr;}
  .ap-card{padding:20px 16px;}
  .ap-otp-input{max-width:100%;}
  .ap-btn{padding:11px 18px;font-size:13px;}
}
.ap-success-icon{width:72px;height:72px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
</style>

<div id="eiu-apply">
  <!-- Step indicators -->
  <div class="ap-steps" id="ap-steps">
    <div class="ap-step">
      <div class="ap-step-circle active" id="ap-circle-1">1</div>
      <div class="ap-step-label"><?php esc_html_e( 'Verify Email', 'eiu-rp' ); ?></div>
    </div>
    <div class="ap-step-line" id="ap-line-1"></div>
    <div class="ap-step">
      <div class="ap-step-circle waiting" id="ap-circle-2">2</div>
      <div class="ap-step-label"><?php esc_html_e( 'Enter Code', 'eiu-rp' ); ?></div>
    </div>
    <div class="ap-step-line" id="ap-line-2"></div>
    <div class="ap-step">
      <div class="ap-step-circle waiting" id="ap-circle-3">3</div>
      <div class="ap-step-label"><?php esc_html_e( 'Application', 'eiu-rp' ); ?></div>
    </div>
  </div>

  <!-- STEP 1: Email entry -->
  <div id="ap-step1" class="ap-card">
    <h2 style="margin:0 0 8px;font-size:20px;color:#1a2535;"><?php echo esc_html( get_option('eiu_rp_term_join_as_author','Join as Author') ); ?></h2>
    <p style="color:#6b7280;font-size:14px;margin:0 0 20px;">
      <?php esc_html_e( 'To continue your registration, please enter your email address to receive a verification OTP.', 'eiu-rp' ); ?>
    </p>
    <div id="ap-msg1" class="ap-msg"></div>
    <div style="margin-bottom:16px;">
      <label for="ap-email" class="ap-label"><?php esc_html_e( 'Email Address', 'eiu-rp' ); ?> <span class="ap-req">*</span></label>
      <input type="email" id="ap-email" class="ap-input"
        placeholder="<?php esc_attr_e( 'your@email.com', 'eiu-rp' ); ?>">
    </div>
    <button type="button" id="ap-send-otp-btn" class="ap-btn ap-btn-primary">
      <i class="bi bi-envelope-fill"></i>
      <?php esc_html_e( 'Send Verification Code', 'eiu-rp' ); ?>
    </button>
  </div>

  <!-- STEP 2: OTP verification -->
  <div id="ap-step2" class="ap-card" style="display:none;">
    <h2 style="margin:0 0 8px;font-size:20px;color:#1a2535;"><?php esc_html_e( 'Enter Verification Code', 'eiu-rp' ); ?></h2>
    <p style="color:#6b7280;font-size:14px;margin:0 0 4px;" id="ap-otp-desc">
      <?php esc_html_e( 'A 6-digit code has been sent to your email. Please check your inbox and spam folder.', 'eiu-rp' ); ?>
    </p>
    <p style="font-weight:700;color:#1a4988;font-size:13px;margin:0 0 20px;" id="ap-otp-email-display"></p>
    <div id="ap-msg2" class="ap-msg"></div>
    <div style="text-align:center;margin-bottom:20px;">
      <label class="ap-label" style="text-align:center;margin-bottom:10px;"><?php esc_html_e( '6-Digit Verification Code', 'eiu-rp' ); ?></label>
      <input type="text" id="ap-otp-input" class="ap-otp-input"
        maxlength="6" inputmode="numeric" autocomplete="one-time-code"
        placeholder="000000">
      <p style="font-size:12px;color:#9ca3af;margin:8px 0 0;">
        <?php esc_html_e( 'Code expires in 10 minutes.', 'eiu-rp' ); ?>
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="button" id="ap-verify-otp-btn" class="ap-btn ap-btn-primary">
        <i class="bi bi-check-circle-fill"></i>
        <?php esc_html_e( 'Verify Code', 'eiu-rp' ); ?>
      </button>
      <button type="button" id="ap-resend-btn" class="ap-btn ap-btn-ghost">
        <?php esc_html_e( 'Resend Code', 'eiu-rp' ); ?>
      </button>
      <button type="button" id="ap-back-to-step1-btn" class="ap-btn ap-btn-ghost">
        <?php esc_html_e( 'Change Email', 'eiu-rp' ); ?>
      </button>
    </div>
  </div>

  <!-- STEP 3: Application Form -->
  <div id="ap-step3" style="display:none;">
    <div class="ap-card" style="margin-bottom:16px;background:#eef7ff;border-color:#b8d4f0;">
      <div style="display:flex;gap:10px;align-items:center;">
        <i class="bi bi-shield-check-fill" style="color:#1a4988;font-size:22px;flex-shrink:0;"></i>
        <div>
          <p style="margin:0;font-weight:700;font-size:13px;color:#1a4988;"><?php esc_html_e( 'Email Verified', 'eiu-rp' ); ?></p>
          <p style="margin:0;font-size:12px;color:#1a6988;" id="ap-verified-email-display"></p>
        </div>
      </div>
    </div>

    <form id="ap-application-form" enctype="multipart/form-data">
      <input type="hidden" name="action"      value="eiu_rp_submit_application">
      <input type="hidden" name="nonce"       value="<?php echo esc_attr( $form_nonce ); ?>">
      <input type="hidden" name="email"       id="ap-hidden-email">
      <input type="hidden" name="apply_token" id="ap-hidden-token">

      <div id="ap-msg3" class="ap-msg"></div>

      <!-- ── Personal Information ───────────────────────────────── -->
      <div class="ap-card" style="margin-bottom:16px;">
        <p class="ap-section-title"><i class="bi bi-person-fill" style="color:#1a4988;margin-right:6px;"></i><?php esc_html_e( 'Personal Information', 'eiu-rp' ); ?></p>
        <div class="ap-row">
          <div>
            <label class="ap-label" for="ap-full-name"><?php esc_html_e( 'Full Name', 'eiu-rp' ); ?> <span class="ap-req">*</span></label>
            <input type="text" id="ap-full-name" name="full_name" class="ap-input"
              placeholder="<?php esc_attr_e( 'e.g. Dr. Maria Santos', 'eiu-rp' ); ?>" required>
          </div>
          <div>
            <label class="ap-label" for="ap-title"><?php esc_html_e( 'Title / Honorific', 'eiu-rp' ); ?></label>
            <select id="ap-title" name="title" class="ap-input ap-select">
              <option value=""><?php esc_html_e( '— Select title —', 'eiu-rp' ); ?></option>
              <?php foreach ( array( 'Dr.', 'Prof.', 'Assoc. Prof.', 'Mr.', 'Ms.', 'Mrs.', 'Engr.', 'Atty.' ) as $t ): ?>
                <option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="ap-row" style="margin-top:14px;">
          <div>
            <label class="ap-label" for="ap-designation"><?php esc_html_e( 'Designation', 'eiu-rp' ); ?></label>
            <input type="text" id="ap-designation" name="designation" class="ap-input"
              placeholder="<?php esc_attr_e( 'e.g. Associate Professor', 'eiu-rp' ); ?>">
          </div>
          <div>
            <label class="ap-label" for="ap-country"><?php esc_html_e( 'Country', 'eiu-rp' ); ?> <span class="ap-req">*</span></label>
            <input type="text" id="ap-country" name="country" class="ap-input"
              placeholder="<?php esc_attr_e( 'e.g. Philippines', 'eiu-rp' ); ?>" required>
          </div>
        </div>
        <div class="ap-row" style="margin-top:14px;">
          <div>
            <label class="ap-label" for="ap-gender"><?php esc_html_e( 'Gender', 'eiu-rp' ); ?></label>
            <select id="ap-gender" name="gender" class="ap-input ap-select">
              <option value=""><?php esc_html_e( '— Select gender —', 'eiu-rp' ); ?></option>
              <option value="male"><?php esc_html_e( 'Male', 'eiu-rp' ); ?></option>
              <option value="female"><?php esc_html_e( 'Female', 'eiu-rp' ); ?></option>
              <option value="other"><?php esc_html_e( 'Other / Prefer not to say', 'eiu-rp' ); ?></option>
            </select>
          </div>
          <div>
            <label class="ap-label" for="ap-dob"><?php esc_html_e( 'Date of Birth', 'eiu-rp' ); ?></label>
            <input type="date" id="ap-dob" name="date_of_birth" class="ap-input">
          </div>
        </div>
        <div style="margin-top:14px;">
          <label class="ap-label" for="ap-student-num"><?php esc_html_e( 'EIU Student Number', 'eiu-rp' ); ?> <span style="color:#9ca3af;font-weight:500;font-size:12px;"><?php esc_html_e( '(Optional)', 'eiu-rp' ); ?></span></label>
          <input type="text" id="ap-student-num" name="student_number" class="ap-input"
            style="max-width:260px;" placeholder="<?php esc_attr_e( 'e.g. EIU-2024-00001', 'eiu-rp' ); ?>">
        </div>
      </div>

      <!-- ── Academic & Research ────────────────────────────────── -->
      <div class="ap-card" style="margin-bottom:16px;">
        <p class="ap-section-title"><i class="bi bi-mortarboard-fill" style="color:#1a4988;margin-right:6px;"></i><?php esc_html_e( 'Academic & Research Profile', 'eiu-rp' ); ?></p>

        <div style="margin-bottom:14px;">
          <label class="ap-label" for="ap-expertise"><?php esc_html_e( 'Area of Expertise', 'eiu-rp' ); ?> <span class="ap-req">*</span></label>
          <input type="text" id="ap-expertise" name="expertise" class="ap-input"
            placeholder="<?php esc_attr_e( 'e.g. Computer Science, Medical Research, Education', 'eiu-rp' ); ?>" required>
        </div>

        <div style="margin-bottom:14px;">
          <label class="ap-label" for="ap-academic-bg"><?php esc_html_e( 'Academic Background', 'eiu-rp' ); ?> <span class="ap-req">*</span></label>
          <textarea id="ap-academic-bg" name="academic_bg" class="ap-input" rows="4"
            style="resize:vertical;"
            placeholder="<?php esc_attr_e( 'Describe your educational qualifications and degrees…', 'eiu-rp' ); ?>" required></textarea>
        </div>

        <div>
          <label class="ap-label" for="ap-about"><?php esc_html_e( 'Brief Information About Yourself', 'eiu-rp' ); ?> <span class="ap-req">*</span></label>
          <textarea id="ap-about" name="about" class="ap-input" rows="5"
            style="resize:vertical;"
            placeholder="<?php esc_attr_e( 'Tell us about your research interests, publications, and what you hope to contribute…', 'eiu-rp' ); ?>" required></textarea>
        </div>
      </div>

      <!-- ── Document Uploads ───────────────────────────────────── -->
      <div class="ap-card" style="margin-bottom:16px;">
        <p class="ap-section-title"><i class="bi bi-paperclip" style="color:#1a4988;margin-right:6px;"></i><?php esc_html_e( 'Supporting Documents', 'eiu-rp' ); ?></p>
        <div style="background:#eef4ff;border:1px solid #b8d4f0;border-radius:8px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;">
          <i class="bi bi-info-circle-fill" style="color:#1a4988;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
          <p style="font-size:13px;color:#1a4988;margin:0;line-height:1.6;">
            <?php esc_html_e( 'Upload your CV/Resume and a sample of your research work. Accepted formats: PDF, DOC, DOCX — maximum 10 MB per file. Both documents help us evaluate your application more effectively.', 'eiu-rp' ); ?>
          </p>
        </div>

        <!-- CV Upload -->
        <div style="margin-bottom:16px;">
          <label class="ap-label" for="ap-cv-file">
            <i class="bi bi-file-earmark-person" style="color:#1a4988;margin-right:5px;"></i>
            <?php esc_html_e( 'Curriculum Vitae (CV / Resume)', 'eiu-rp' ); ?>
          </label>
          <!-- CV upload card -->
          <div class="ap-upload-card" id="ap-cv-zone"
            onclick="if(!this.dataset.uploaded)document.getElementById('ap-cv-file').click()"
            ondragover="event.preventDefault();this.classList.add('ap-dz-hover')"
            ondragleave="this.classList.remove('ap-dz-hover')"
            ondrop="event.preventDefault();this.classList.remove('ap-dz-hover');apHandleFile(event.dataTransfer.files[0],'ap-cv-file','ap-cv-zone')">
            <!-- Idle state -->
            <div class="ap-uc-idle" id="ap-cv-idle">
              <div class="ap-uc-icon-wrap">
                <i class="bi bi-file-earmark-plus ap-uc-icon"></i>
              </div>
              <span class="ap-uc-label"><?php esc_html_e( 'Click to upload', 'eiu-rp' ); ?></span>
            </div>
            <!-- Done state (hidden until file chosen) -->
            <div class="ap-uc-done" id="ap-cv-done" style="display:none;">
              <div class="ap-uc-badge">
                <i class="bi bi-patch-check-fill ap-uc-badge-icon"></i>
              </div>
              <div class="ap-uc-info">
                <span class="ap-uc-fname" id="ap-cv-fname"></span>
                <span class="ap-uc-complete"><?php esc_html_e( 'Upload complete', 'eiu-rp' ); ?></span>
              </div>
              <button type="button" class="ap-uc-remove"
                onclick="event.stopPropagation();apClearFile('ap-cv-file','ap-cv-zone')"
                title="<?php esc_attr_e('Remove file','eiu-rp'); ?>">&times;</button>
            </div>
          </div>
          <input type="file" id="ap-cv-file" name="cv_file" accept=".pdf,.doc,.docx" style="display:none;"
            onchange="apHandleFile(this.files[0],'ap-cv-file','ap-cv-zone')">
        </div>

        <!-- Research Work Upload -->
        <div>
          <label class="ap-label" for="ap-research-file">
            <i class="bi bi-file-earmark-text" style="color:#1a4988;margin-right:5px;"></i>
            <?php esc_html_e( 'Research Work Sample', 'eiu-rp' ); ?>
          </label>
          <!-- Research upload card -->
          <div class="ap-upload-card" id="ap-research-zone"
            onclick="if(!this.dataset.uploaded)document.getElementById('ap-research-file').click()"
            ondragover="event.preventDefault();this.classList.add('ap-dz-hover')"
            ondragleave="this.classList.remove('ap-dz-hover')"
            ondrop="event.preventDefault();this.classList.remove('ap-dz-hover');apHandleFile(event.dataTransfer.files[0],'ap-research-file','ap-research-zone')">
            <!-- Idle state -->
            <div class="ap-uc-idle" id="ap-research-idle">
              <div class="ap-uc-icon-wrap">
                <i class="bi bi-file-earmark-plus ap-uc-icon"></i>
              </div>
              <span class="ap-uc-label"><?php esc_html_e( 'Click to upload', 'eiu-rp' ); ?></span>
            </div>
            <!-- Done state (hidden until file chosen) -->
            <div class="ap-uc-done" id="ap-research-done" style="display:none;">
              <div class="ap-uc-badge">
                <i class="bi bi-patch-check-fill ap-uc-badge-icon"></i>
              </div>
              <div class="ap-uc-info">
                <span class="ap-uc-fname" id="ap-research-fname"></span>
                <span class="ap-uc-complete"><?php esc_html_e( 'Upload complete', 'eiu-rp' ); ?></span>
              </div>
              <button type="button" class="ap-uc-remove"
                onclick="event.stopPropagation();apClearFile('ap-research-file','ap-research-zone')"
                title="<?php esc_attr_e('Remove file','eiu-rp'); ?>">&times;</button>
            </div>
          </div>
          <input type="file" id="ap-research-file" name="research_file" accept=".pdf,.doc,.docx" style="display:none;"
            onchange="apHandleFile(this.files[0],'ap-research-file','ap-research-zone')">
        </div>
      </div>

      <!-- ── Declaration ────────────────────────────────────────── -->
      <div class="ap-card" style="margin-bottom:20px;">
        <div class="ap-declaration">
          <input type="checkbox" id="ap-declaration" name="declaration" value="1"
            style="width:18px;height:18px;cursor:pointer;flex-shrink:0;margin-top:1px;">
          <label for="ap-declaration" style="font-size:13px;color:#374151;line-height:1.6;cursor:pointer;">
            <strong><?php esc_html_e( 'Declaration:', 'eiu-rp' ); ?></strong>
            <?php esc_html_e( 'I understand that all submitted information is accurate and complete to the best of my knowledge. I consent to EIU Journal System storing and using this information for the purpose of evaluating my application.', 'eiu-rp' ); ?>
          </label>
        </div>
      </div>

      <!-- Submit -->
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <button type="submit" id="ap-submit-btn" class="ap-btn ap-btn-primary" style="font-size:15px;padding:13px 32px;">
          <i class="bi bi-send-fill"></i>
          <?php esc_html_e( 'Submit Application', 'eiu-rp' ); ?>
        </button>
        <p style="font-size:12px;color:#9ca3af;margin:0;">
          <?php esc_html_e( 'You will receive a confirmation email after submission.', 'eiu-rp' ); ?>
        </p>
      </div>
    </form>
  </div>

  <!-- Success screen -->
  <div id="ap-success" class="ap-card ap-success">
    <div class="ap-success-icon">
      <i class="bi bi-check-lg" style="font-size:36px;color:#059669;"></i>
    </div>
    <h2 style="margin:0 0 10px;font-size:22px;color:#1a2535;"><?php esc_html_e( 'Application Submitted!', 'eiu-rp' ); ?></h2>
    <p style="color:#6b7280;font-size:15px;max-width:460px;margin:0 auto 20px;line-height:1.7;">
      <?php esc_html_e( 'Thank you for applying. Our editorial team will review your application and contact you at the email address you provided. Please allow a few business days for a response.', 'eiu-rp' ); ?>
    </p>
    <a href="<?php echo esc_url( home_url() ); ?>" class="ap-btn ap-btn-primary">
      <i class="bi bi-house-fill"></i>
      <?php esc_html_e( 'Return to Homepage', 'eiu-rp' ); ?>
    </a>
  </div>
</div>

<script>
(function(){
'use strict';

var ajax          = '<?php echo esc_js( $ajax_url ); ?>';
var otpNonce      = '';
var formNonce     = '';
var verifiedEmail = '';
var applyToken    = '';
var noncesReady   = false;

/* Fetch fresh nonces immediately — bypasses any page cache */
function fetchNonces(callback){
  if(noncesReady){ if(callback) callback(); return; }
  var fd=new FormData();
  fd.append('action','eiu_rp_get_nonce');
  fd.append('for','apply');
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(res){
      if(res.success){
        otpNonce=res.data.otp_nonce||'';
        formNonce=res.data.form_nonce||'';
        document.querySelectorAll('input[name="nonce"]').forEach(function(el){el.value=formNonce;});
        noncesReady=true;
      }
      if(callback) callback();
    })
    .catch(function(){ if(callback) callback(); });
}

/* ── Upload card: file select / drop handler ─────────
   Defined on window so inline onchange/ondrop can reach it
   even though this code is inside an IIFE.
─────────────────────────────────────────────────── */
window.apHandleFile = function(file, inputId, zoneId){
  if(!file) return;
  var ext = file.name.split('.').pop().toLowerCase();
  if(!['pdf','doc','docx'].includes(ext)){
    alert('<?php echo esc_js( __( 'Only PDF, DOC, and DOCX files are accepted.', 'eiu-rp' ) ); ?>');
    return;
  }
  if(file.size > 10*1024*1024){
    alert('<?php echo esc_js( __( 'File must be under 10 MB.', 'eiu-rp' ) ); ?>');
    return;
  }

  /* Transfer file to the hidden <input> */
  var inp = document.getElementById(inputId);
  if(inp){
    try{ var dt=new DataTransfer(); dt.items.add(file); inp.files=dt.files; }catch(e){}
  }

  /* Derive sibling element IDs from the zone ID
     e.g. zoneId='ap-cv-zone' → prefix='ap-cv' */
  var prefix  = zoneId.replace('-zone','');
  var idleEl  = document.getElementById(prefix+'-idle');
  var doneEl  = document.getElementById(prefix+'-done');
  var fnameEl = document.getElementById(prefix+'-fname');
  var card    = document.getElementById(zoneId);

  /* Fill in filename */
  if(fnameEl){
    fnameEl.textContent = file.name + ' (' + Math.round(file.size/1024) + ' KB)';
  }

  /* Swap idle → done */
  if(idleEl) idleEl.style.display = 'none';
  if(doneEl) doneEl.style.display = 'flex';

  /* Add selected class so card goes green + non-clickable */
  if(card){
    card.classList.add('ap-uc-selected');
    card.style.cursor = 'default';
    /* Prevent the card onclick from re-opening the picker */
    card.setAttribute('data-uploaded','1');
  }
};

/* ── Clear / remove chosen file ─────────────────── */
window.apClearFile = function(inputId, zoneId){
  var inp = document.getElementById(inputId);
  if(inp){ inp.value=''; }

  var prefix = zoneId.replace('-zone','');
  var idleEl = document.getElementById(prefix+'-idle');
  var doneEl = document.getElementById(prefix+'-done');
  var card   = document.getElementById(zoneId);

  /* Swap done → idle */
  if(doneEl) doneEl.style.display = 'none';
  if(idleEl) idleEl.style.display = 'flex';

  /* Restore card */
  if(card){
    card.classList.remove('ap-uc-selected');
    card.style.cursor = 'pointer';
    card.removeAttribute('data-uploaded');
  }
};

/* ── UI helpers ─────────────────────────────────────── */
function setStep(n){
  for(var i=1;i<=3;i++){
    var c=document.getElementById('ap-circle-'+i);
    c.className='ap-step-circle '+(i<n?'done':i===n?'active':'waiting');
    if(i<n) c.innerHTML='<i class="bi bi-check-lg"></i>';
    else     c.textContent=i;
  }
  for(var j=1;j<=2;j++){
    var l=document.getElementById('ap-line-'+j);
    l.className='ap-step-line'+(j<n?' done':'');
  }
  document.getElementById('ap-step1').style.display=(n===1?'block':'none');
  document.getElementById('ap-step2').style.display=(n===2?'block':'none');
  document.getElementById('ap-step3').style.display=(n===3?'block':'none');
  document.getElementById('ap-steps').style.display=(n<=3?'flex':'none');
}

function msg(id, text, ok){
  var el=document.getElementById(id);
  el.textContent=text;
  el.className='ap-msg '+(ok?'ok':'err');
  el.style.display='block';
}
function clearMsg(id){ var el=document.getElementById(id); if(el){ el.style.display='none'; el.textContent=''; } }

function btnLoad(id,t){var b=document.getElementById(id);b.disabled=true;b.setAttribute('data-orig',b.innerHTML);b.innerHTML='<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:ap-spin .7s linear infinite;margin-right:6px;vertical-align:middle;"></span>'+t;}
function btnReset(id){var b=document.getElementById(id);b.disabled=false;b.innerHTML=b.getAttribute('data-orig')||b.innerHTML;}

/* Spinner CSS */
var styleEl=document.createElement('style');
styleEl.textContent='@keyframes ap-spin{to{transform:rotate(360deg)}}';
document.head.appendChild(styleEl);

/* ── Step 1: Send OTP ───────────────────────────────── */
document.getElementById('ap-send-otp-btn').addEventListener('click', function(){
  clearMsg('ap-msg1');
  var email = document.getElementById('ap-email').value.trim();
  if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
    msg('ap-msg1','<?php echo esc_js( __( 'Please enter a valid email address.', 'eiu-rp' ) ); ?>',false);
    return;
  }
  btnLoad('ap-send-otp-btn','<?php echo esc_js( __( 'Sending…', 'eiu-rp' ) ); ?>');
  fetchNonces(function(){
  var fd=new FormData();
  fd.append('action','eiu_rp_apply_send_otp');
  fd.append('nonce',otpNonce);
  fd.append('email',email);
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){var c=r.clone();return r.json().catch(function(){return c.text().then(function(){return{success:false,data:{message:'<?php echo esc_js( __( 'Server error. Please try again.', 'eiu-rp' ) ); ?>'}};});})})
    .then(function(res){
      btnReset('ap-send-otp-btn');
      if(res.success){
        verifiedEmail=email;
        document.getElementById('ap-otp-email-display').textContent=email;
        setStep(2);
        setTimeout(function(){document.getElementById('ap-otp-input').focus();},100);
      } else {
        msg('ap-msg1',(res.data&&res.data.message)||'<?php echo esc_js( __( 'Failed to send code.', 'eiu-rp' ) ); ?>',false);
      }
    }).catch(function(){btnReset('ap-send-otp-btn');msg('ap-msg1','<?php echo esc_js( __( 'Network error. Please try again.', 'eiu-rp' ) ); ?>',false);});
  }); /* end fetchNonces */
});
document.getElementById('ap-email').addEventListener('keydown',function(e){if(e.key==='Enter')document.getElementById('ap-send-otp-btn').click();});

/* ── Step 2: Verify OTP ─────────────────────────────── */
document.getElementById('ap-otp-input').addEventListener('input',function(){this.value=this.value.replace(/[^0-9]/g,'').slice(0,6);});
document.getElementById('ap-otp-input').addEventListener('keydown',function(e){if(e.key==='Enter')document.getElementById('ap-verify-otp-btn').click();});

document.getElementById('ap-verify-otp-btn').addEventListener('click',function(){
  clearMsg('ap-msg2');
  var code=document.getElementById('ap-otp-input').value.trim();
  if(code.length!==6){msg('ap-msg2','<?php echo esc_js( __( 'Please enter the 6-digit code.', 'eiu-rp' ) ); ?>',false);return;}
  btnLoad('ap-verify-otp-btn','<?php echo esc_js( __( 'Verifying…', 'eiu-rp' ) ); ?>');
  var fd=new FormData();
  fd.append('action','eiu_rp_apply_verify_otp');
  fd.append('nonce',otpNonce);
  fd.append('email',verifiedEmail);
  fd.append('otp',code);
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){var c=r.clone();return r.json().catch(function(){return c.text().then(function(){return{success:false,data:{message:'<?php echo esc_js( __( 'Server error.', 'eiu-rp' ) ); ?>'}};});})})
    .then(function(res){
      btnReset('ap-verify-otp-btn');
      if(res.success){
        applyToken=res.data.token;
        document.getElementById('ap-hidden-email').value=verifiedEmail;
        document.getElementById('ap-hidden-token').value=applyToken;
        document.getElementById('ap-verified-email-display').textContent='<?php echo esc_js( __( 'Verified email:', 'eiu-rp' ) ); ?> '+verifiedEmail;
        setStep(3);
        window.scrollTo({top:0,behavior:'smooth'});
      } else {
        msg('ap-msg2',(res.data&&res.data.message)||'<?php echo esc_js( __( 'Invalid code. Please try again.', 'eiu-rp' ) ); ?>',false);
      }
    }).catch(function(){btnReset('ap-verify-otp-btn');msg('ap-msg2','<?php echo esc_js( __( 'Network error.', 'eiu-rp' ) ); ?>',false);});
});

/* Resend */
document.getElementById('ap-resend-btn').addEventListener('click',function(){
  clearMsg('ap-msg2');
  document.getElementById('ap-otp-input').value='';
  var fd=new FormData();
  fd.append('action','eiu_rp_apply_send_otp');
  fd.append('nonce',otpNonce);
  fd.append('email',verifiedEmail);
  var btn=this;
  btn.disabled=true;
  fetch(ajax,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
    btn.disabled=false;
    msg('ap-msg2',res.data&&res.data.message?res.data.message:'<?php echo esc_js( __( 'Code sent.', 'eiu-rp' ) ); ?>',res.success);
  }).catch(function(){btn.disabled=false;});
});

/* Back to step 1 */
document.getElementById('ap-back-to-step1-btn').addEventListener('click',function(){
  clearMsg('ap-msg2');
  document.getElementById('ap-otp-input').value='';
  setStep(1);
  setTimeout(function(){document.getElementById('ap-email').focus();},100);
});

/* ── Step 3: Submit form ─────────────────────────────── */
document.getElementById('ap-application-form').addEventListener('submit',function(e){
  e.preventDefault();
  clearMsg('ap-msg3');
  var btn=document.getElementById('ap-submit-btn');

  // Client-side validation
  var missing=[];
  ['ap-full-name','ap-country','ap-expertise','ap-academic-bg','ap-about'].forEach(function(id){
    var el=document.getElementById(id);
    if(el&&!el.value.trim()){
      missing.push(id);
      el.style.borderColor='#e53e3e';
    } else if(el){
      el.style.borderColor='';
    }
  });
  if(!document.getElementById('ap-declaration').checked){
    msg('ap-msg3','<?php echo esc_js( __( 'Please accept the declaration to submit.', 'eiu-rp' ) ); ?>',false);
    return;
  }
  if(missing.length>0){
    msg('ap-msg3','<?php echo esc_js( __( 'Please complete all required fields (highlighted in red).', 'eiu-rp' ) ); ?>',false);
    document.getElementById(missing[0]).scrollIntoView({behavior:'smooth',block:'center'});
    return;
  }

  btnLoad('ap-submit-btn','<?php echo esc_js( __( 'Submitting…', 'eiu-rp' ) ); ?>');
  var fd=new FormData(this);
  fetch(ajax,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(res){
      btnReset('ap-submit-btn');
      if(res.success){
        document.getElementById('ap-steps').style.display='none';
        document.getElementById('ap-step3').style.display='none';
        document.getElementById('ap-success').style.display='block';
        window.scrollTo({top:0,behavior:'smooth'});
      } else {
        msg('ap-msg3',(res.data&&res.data.message)||'<?php echo esc_js( __( 'An error occurred. Please try again.', 'eiu-rp' ) ); ?>',false);
      }
    }).catch(function(){
      btnReset('ap-submit-btn');
      msg('ap-msg3','<?php echo esc_js( __( 'Network error. Please try again.', 'eiu-rp' ) ); ?>',false);
    });
});

// Clear red borders on input
document.querySelectorAll('.ap-input').forEach(function(el){
  el.addEventListener('input',function(){this.style.borderColor='';});
});

/* Pre-fetch nonces in background so first click is instant */
fetchNonces(null);
}());
</script>
