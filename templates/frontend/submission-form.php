<?php
/**
 * Frontend Article Submission Form — v1.5
 *
 * v1.5 changes:
 *  - Author name + email auto-filled when user is logged in (fields remain editable).
 *
 * v1.4 changes:
 *  - Thumbnail: direct upload only (file picker + drag-drop). Media Library removed.
 *  - Author/Co-Author photo: direct upload only. Media Library removed.
 *  - DOI field: removed from frontend (DB column preserved, data intact).
 *  - ISSN field: removed from frontend (admin/reviewer edit only via backend).
 *  - wp_enqueue_media() removed (no longer needed without wp.media picker).
 *  - openMediaPicker() JS removed entirely.
 *  - Step tabs renumbered to reflect removed sections.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$subjects = \EIU_RP\Utils\Helpers::subjects_list();
$nonce    = wp_create_nonce( 'eiu_rp_frontend' );
$max_mb   = 5;

$abstract_editor_id   = 'eiu_abstract_' . uniqid();
$references_editor_id = 'eiu_references_' . uniqid();

// v1.5: Pre-fill author details from the logged-in user profile.
$prefill_author_name  = '';
$prefill_author_email = '';
$is_prefilled         = false;
if ( is_user_logged_in() ) {
    $sf_user              = wp_get_current_user();
    $prefill_author_name  = trim( $sf_user->display_name ?: $sf_user->user_login );
    $fn = get_user_meta( $sf_user->ID, 'first_name', true );
    $ln = get_user_meta( $sf_user->ID, 'last_name',  true );
    $full = trim( $fn . ' ' . $ln );
    if ( $full ) {
        $prefill_author_name = $full;
    }
    $prefill_author_email = $sf_user->user_email;
    $is_prefilled         = ( $prefill_author_name || $prefill_author_email );
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.eiu-sf-wrap {
  --br: #990000; --brd: #720000;
  --bb: #1a4988; --bbd: #123266; --bbl: #e8eef8;
  --cb: #dee2e6; --cbg: #f8f9fa;
  max-width:820px; margin:0 auto;
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; color:#212529;
}
/* Header */
.eiu-sf-header { background:linear-gradient(135deg,var(--bbd) 0%,var(--bb) 65%,#2460a7 100%); padding:30px 36px 26px; border-radius:12px 12px 0 0; }
.eiu-sf-header h1 { font-size:clamp(20px,3.5vw,28px); font-weight:800; color:#fff; margin:0 0 6px; }
.eiu-sf-header h1 span { color:#f5c842; }
.eiu-sf-header p { color:rgba(255,255,255,.78); font-size:13px; margin:0; }
.eiu-sf-header p span { color:#f5c842; }
/* Tabs */
/* ── Wizard progress bar ──────────────────── */
.eiu-sf-progress-bar-wrap { background:var(--bbd); padding:0 28px 0; }
.eiu-sf-progress-track { background:rgba(255,255,255,.15); border-radius:99px; height:4px; overflow:hidden; }
.eiu-sf-progress-fill { height:100%; background:#f5c842; border-radius:99px; transition:width .35s ease; }
/* ── Step indicator pill strip ─────────────── */
.eiu-sf-tabs { background:var(--bbd); padding:10px 28px 12px; display:flex; gap:0; overflow-x:auto; scrollbar-width:none; align-items:center; }
.eiu-sf-tabs::-webkit-scrollbar { display:none; }
.eiu-sf-tab { padding:6px 10px; font-size:11px; font-weight:600; color:rgba(255,255,255,.45); white-space:nowrap; cursor:default; display:flex; align-items:center; gap:5px; opacity:.55; transition:opacity .2s; }
.eiu-sf-tab.is-active { color:#fff; opacity:1; }
.eiu-sf-tab.is-done { color:rgba(255,255,255,.75); opacity:.85; }
.eiu-sf-tab .num { width:18px; height:18px; border-radius:50%; background:rgba(255,255,255,.12); display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; flex-shrink:0; }
.eiu-sf-tab.is-active .num { background:#f5c842; color:var(--bbd); }
.eiu-sf-tab.is-done .num { background:rgba(255,255,255,.3); color:#fff; }
.eiu-sf-tab-sep { color:rgba(255,255,255,.2); font-size:10px; flex-shrink:0; padding:0 2px; }
/* ── Wizard sections: only active shown ─────── */
.eiu-sf-section[data-step] { display:none; }
.eiu-sf-section[data-step].is-active-step {
  display:block;
  animation: eiu-step-in .28s ease both;
}
@keyframes eiu-step-in {
  from { opacity:0; transform:translateY(10px); }
  to   { opacity:1; transform:translateY(0); }
}
/* ── Wizard nav buttons ──────────────────────── */
.eiu-sf-wizard-nav { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:20px 32px; border-top:1px solid #e9ecef; background:#f8f9fa; border-radius:0 0 12px 12px; flex-wrap:wrap; }
.eiu-sf-wizard-nav .step-label { font-size:12px; color:#6c757d; font-weight:600; text-align:center; flex:1; }
.eiu-sf-nav-btn { display:inline-flex; align-items:center; gap:7px; padding:11px 22px; border-radius:8px; font-size:14px; font-weight:700; border:none; cursor:pointer; transition:all .15s; text-decoration:none; }
.eiu-sf-nav-prev { background:#fff; border:1.5px solid #dee2e6; color:#495057; }
.eiu-sf-nav-prev:hover { background:#f0f4f9; border-color:var(--bb); color:var(--bb); }
.eiu-sf-nav-prev:disabled { opacity:.4; cursor:not-allowed; }
.eiu-sf-nav-next { background:var(--bb); color:#fff; box-shadow:0 2px 8px rgba(26,73,136,.25); }
.eiu-sf-nav-next:hover { background:var(--bbd); box-shadow:0 4px 12px rgba(26,73,136,.35); transform:translateY(-1px); }
/* Card */
.eiu-sf-card { background:#fff; border:1px solid var(--cb); border-top:none; border-radius:0 0 12px 12px; }
/* Sections */
.eiu-sf-section { padding:22px 32px; border-bottom:1px solid #f0f2f5; }
.eiu-sf-section:last-of-type { border-bottom:none; }
.eiu-sf-section-head { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.eiu-sf-line { flex:1; height:1px; background:#e9ecef; }
.eiu-sf-tag { display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:6px; font-size:13px; font-weight:700; white-space:nowrap; }
.tag-red   { background:var(--br); color:#fff; }
.tag-blue  { background:var(--bb); color:#fff; }
.tag-dark  { background:#1a2f50;    color:#fff; }
.tag-muted { background:#e9ecef; color:#495057; }
/* Inputs */
.eiu-sf-wrap .form-control:focus, .eiu-sf-wrap .form-select:focus { border-color:var(--bb); box-shadow:0 0 0 .2rem rgba(26,73,136,.15); }
.eiu-sf-title-input { font-size:1.1rem !important; font-weight:500 !important; }
.eiu-sf-optional-note { font-size:11px; color:#6c757d; font-style:italic; margin-left:4px; }
/* Author block */
.eiu-sf-author-block { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:18px 18px 10px; }
/* ── Photo upload (circular) ── */
.eiu-photo-circle-wrap { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.eiu-photo-circle {
  width:56px; height:56px; border-radius:50%; flex-shrink:0;
  background:var(--bbl); border:2px solid var(--cb); overflow:hidden;
  display:flex; align-items:center; justify-content:center;
  font-size:20px; font-weight:800; color:var(--bb); cursor:pointer; position:relative;
  transition:border-color .15s;
}
.eiu-photo-circle:hover { border-color:var(--bb); }
.eiu-photo-circle img { width:100%; height:100%; object-fit:cover; display:block; }
.eiu-photo-circle .overlay {
  position:absolute; inset:0; background:rgba(26,73,136,.55);
  display:flex; align-items:center; justify-content:center;
  opacity:0; transition:opacity .2s; border-radius:50%;
}
.eiu-photo-circle:hover .overlay { opacity:1; }
.eiu-photo-circle .overlay i { color:#fff; font-size:18px; }
.eiu-photo-btn { font-size:12px; line-height:1.3; }
.eiu-photo-btn p { font-size:11px; color:#6c757d; margin:4px 0 0; }
/* ── Thumbnail picker (WP media) ── */
.eiu-sf-thumb-area {
  border:2px dashed var(--cb); border-radius:10px; background:var(--cbg);
  cursor:pointer; transition:border-color .15s, background .15s;
  position:relative; aspect-ratio:16/9; max-height:200px;
  display:flex; align-items:center; justify-content:center; overflow:hidden;
}
.eiu-sf-thumb-area:hover { border-color:var(--bb); background:var(--bbl); }
.eiu-sf-thumb-area img.preview { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:none; }
.eiu-sf-thumb-area .thumb-overlay {
  position:absolute; inset:0; background:rgba(26,73,136,.55);
  display:flex; align-items:center; justify-content:center;
  opacity:0; transition:opacity .2s;
}
.eiu-sf-thumb-area.has-image:hover .thumb-overlay { opacity:1; }
.eiu-sf-thumb-placeholder { text-align:center; padding:0 16px; pointer-events:none; }
/* ── Advisers dynamic list ── */
.eiu-adviser-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.eiu-adviser-row input { flex:1; }
.eiu-adviser-remove { background:none; border:1px solid #dee2e6; border-radius:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#6c757d; flex-shrink:0; transition:all .15s; }
.eiu-adviser-remove:hover { background:var(--br); border-color:var(--br); color:#fff; }
.eiu-adviser-add { font-size:13px; font-weight:600; color:var(--bb); background:var(--bbl); border:1px solid #b8d0f0; border-radius:6px; padding:6px 14px; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; gap:6px; }
.eiu-adviser-add:hover { background:var(--bb); color:#fff; }
/* File drop */
.eiu-sf-file-drop { border:2px dashed var(--cb); border-radius:10px; background:var(--cbg); padding:28px 20px; text-align:center; cursor:pointer; position:relative; transition:border-color .15s,background .15s; }
.eiu-sf-file-drop:hover, .eiu-sf-file-drop.drag-over { border-color:var(--bb); background:var(--bbl); }
.eiu-sf-file-drop input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.eiu-sf-file-chosen { display:flex; align-items:center; gap:12px; padding:12px 16px; background:var(--bbl); border-radius:8px; border:1px solid #b8d0f0; }
.eiu-sf-file-chosen .fname { flex:1; font-size:14px; font-weight:500; color:var(--bb); word-break:break-all; }
/* Editor — full TinyMCE responsive */
.eiu-sf-editor-wrap { border:1.5px solid var(--cb); border-radius:8px; overflow:hidden; position:relative; }
.eiu-sf-editor-wrap .wp-editor-container { border:none !important; }
.eiu-sf-editor-wrap .mce-tinymce,
.eiu-sf-editor-wrap .mce-container { border:none !important; box-shadow:none !important; }
/* Loading placeholder — overlays the editor area until TinyMCE inits */
.eiu-editor-placeholder {
  position:absolute; inset:0; z-index:5;
  background:#f8f9fa; border-radius:8px;
}
/* Keep toolbars readable on mobile */
@media(max-width:600px){
  .eiu-sf-editor-wrap .mce-toolbar-grp { overflow-x:auto; }
  .eiu-sf-editor-wrap .mce-btn-group { flex-wrap:wrap; }
  .mce-menu { max-width:90vw !important; }
}
/* Loading placeholder — overlays textarea, hidden via JS when TinyMCE fires editor.on('init') */
.eiu-editor-placeholder {
  display:flex; align-items:center; justify-content:center;
  min-height:280px; background:#f8f9fa;
  border-radius:8px;
}
.eiu-editor-placeholder-inner {
  display:flex; align-items:center;
  font-size:13px; font-weight:600; color:#6c757d;
}
@keyframes eiu-spin { to { transform:rotate(360deg); } }
/* Submit */
.eiu-sf-submit-area { padding:26px 32px; text-align:center; background:linear-gradient(180deg,#f8f9fa 0%,#fff 100%); border-top:1px solid #e9ecef; border-radius:0 0 12px 12px; }
.eiu-sf-submit-btn { min-width:200px; padding:14px 48px; font-size:1.05rem; font-weight:800; background:linear-gradient(135deg,var(--br) 0%,var(--brd) 100%); color:#fff; border:none; border-radius:8px; cursor:pointer; letter-spacing:.3px; box-shadow:0 4px 16px rgba(153,0,0,.28); transition:all .15s; }
.eiu-sf-submit-btn:hover { transform:translateY(-1px); box-shadow:0 6px 22px rgba(153,0,0,.38); }
.eiu-sf-submit-btn:disabled { opacity:.65; cursor:not-allowed; transform:none; }
/* Footer */
.eiu-sf-footer { display:flex; align-items:center; gap:14px; justify-content:center; background:linear-gradient(135deg,var(--bbd) 0%,var(--bb) 100%); color:#fff; padding:20px 32px; border-radius:0 0 12px 12px; text-decoration:none; transition:opacity .15s; }
.eiu-sf-footer:hover { opacity:.92; color:#fff; text-decoration:none; }
.eiu-sf-footer strong { color:#f5c842; }
/* Errors */
.eiu-err { font-size:12px; color:var(--br); display:none; margin-top:4px; }

/* v1.5 Auto-fill badge and pre-filled input highlight */
.eiu-sf-autofill-badge {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 10px; font-weight: 700; letter-spacing: .03em;
  background: #ecfdf5; color: #065f46;
  border: 1px solid #a7f3d0; border-radius: 20px;
  padding: 1px 8px; margin-left: 6px; vertical-align: middle;
  font-style: normal;
}
.eiu-sf-autofilled {
  background: #f0fdf4 !important;
  border-color: #6ee7b7 !important;
}
.eiu-sf-autofilled:focus {
  background: #fff !important;
  border-color: var(--bb) !important;
}

/* ── Ethics upload card (reuses ap-upload-card styles) ─ */
.eiu-ethics-upload-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:14px 16px;cursor:pointer;transition:border-color .18s,box-shadow .18s;user-select:none;min-height:60px;display:flex;align-items:center;margin-top:8px;}
.eiu-ethics-upload-card:hover,.eiu-ethics-upload-card.eiu-ethics-hover{border-color:#1a4988;box-shadow:0 0 0 3px rgba(26,73,136,.08);}
.eiu-ethics-upload-card.ap-uc-selected{border-color:#10b981;background:#f0fdf4;cursor:default;}
.eiu-eu-idle{display:flex;align-items:center;gap:12px;width:100%;}
.eiu-eu-done{display:flex;align-items:center;gap:12px;width:100%;}

/* ── Disclosures v1.6 ──────────────────────── */
.eiu-disclosure-block { background:var(--bbl,#e8eef8); border-radius:10px; padding:18px 20px; }
.eiu-disclosure-title { font-size:14px; font-weight:800; color:var(--bb,#1a4988); margin-bottom:8px; display:flex; align-items:center; }
.eiu-disclosure-placeholder { font-size:13px; color:#6b7280; line-height:1.7; margin-bottom:12px; border-left:3px solid var(--bb,#1a4988); padding-left:12px; }
.eiu-disclosure-question { font-size:13px; font-weight:600; color:#374151; margin-bottom:12px; }
.eiu-radio-group { display:flex; gap:10px; flex-wrap:wrap; }
.eiu-radio-opt { cursor:pointer; }
.eiu-radio-opt input { position:absolute; opacity:0; width:0; height:0; }
.eiu-radio-box { display:inline-flex; align-items:center; padding:8px 18px; border:1.5px solid #d1d5db; border-radius:7px; font-size:13px; font-weight:600; color:#374151; background:#fff; transition:all .13s; }
.eiu-radio-opt:has(input:checked) .eiu-radio-box { background:var(--bb,#1a4988); color:#fff; border-color:var(--bb,#1a4988); }
.eiu-radio-opt:hover .eiu-radio-box { border-color:var(--bb,#1a4988); }

@media(max-width:640px) {
  .eiu-sf-header,.eiu-sf-section,.eiu-sf-submit-area,.eiu-sf-footer { padding-left:16px; padding-right:16px; }
  .eiu-sf-wizard-nav { padding:14px 16px; flex-wrap:wrap; gap:8px; }
  .eiu-sf-nav-btn { padding:9px 14px; font-size:13px; }
  .eiu-sf-wizard-nav .step-label { order:-1; width:100%; text-align:center; }
  .eiu-sf-progress-bar-wrap { padding:0 16px; }
  .eiu-sf-tabs { padding:8px 16px 10px; }
}
</style>

<div class="eiu-sf-wrap" id="eiu-rp-submission-wrap">

  <div class="eiu-sf-header">
    <h1><?php echo esc_html( get_option('eiu_rp_term_submission_form_title','Submit Your Manuscript') ); ?></h1>
    <p><?php esc_html_e('All submissions are reviewed by the EIU Editorial Board. Fields marked','eiu-rp'); ?> <span>*</span> <?php esc_html_e('are required.','eiu-rp'); ?></p>
  </div>

  <?php
  // v2.0: Wizard step definitions
  $wizard_steps = array(
    array('bi-type-h1',      __('Title','eiu-rp')),
    array('bi-image',        __('Thumbnail','eiu-rp')),
    array('bi-file-richtext',__('Abstract','eiu-rp')),
    array('bi-tags',         __('Categories','eiu-rp')),
    array('bi-person-fill',  __('Author','eiu-rp')),
    array('bi-person-plus',  __('Co-Author','eiu-rp')),
    array('bi-people',       __('Advisers','eiu-rp')),
    array('bi-shield-check', __('Disclosures','eiu-rp')),
    array('bi-book',         __('References','eiu-rp')),
    array('bi-cloud-upload', __('Files','eiu-rp')),
  );
  $total_steps = count($wizard_steps);
  ?>

  <!-- Progress bar -->
  <div class="eiu-sf-progress-bar-wrap">
    <div class="eiu-sf-progress-track">
      <div class="eiu-sf-progress-fill" id="eiu-sf-progress-fill" style="width:<?php echo round(100/$total_steps); ?>%"></div>
    </div>
  </div>

  <!-- Step pill strip -->
  <div class="eiu-sf-tabs" id="eiu-sf-tabs">
    <?php foreach ( $wizard_steps as $i => $t ): ?>
      <div class="eiu-sf-tab <?php echo $i===0?'is-active':''; ?>" id="eiu-sf-tab-<?php echo $i; ?>">
        <span class="num"><?php echo $i===0?'<i class="bi bi-check-lg" style="font-size:9px;"></i>':($i+1); ?></span>
        <i class="bi <?php echo esc_attr($t[0]); ?>"></i>
        <?php echo esc_html($t[1]); ?>
      </div>
      <?php if($i < $total_steps-1): ?><span class="eiu-sf-tab-sep">›</span><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="alert alert-success rounded-0 mb-0 d-none" id="eiu-sf-success"></div>
  <div class="alert alert-danger  rounded-0 mb-0 d-none" id="eiu-sf-error"></div>

  <div class="eiu-sf-card">
  <form id="eiu-rp-submission-form" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="action" value="eiu_rp_submit_article">
    <input type="hidden" name="nonce"  value="<?php echo esc_attr($nonce); ?>">
    <input type="hidden" name="thumbnail_attachment_id" id="eiu-thumb-att-id" value="">
    <input type="hidden" name="author_photo_attachment_id" id="eiu-author-att-id" value="">
    <input type="hidden" name="coauthor_photo_attachment_id" id="eiu-coauthor-att-id" value="">

    <!-- 1. Title -->
    <div class="eiu-sf-section is-active-step" data-step="0">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-blue"><i class="bi bi-type-h1"></i> 1. <?php esc_html_e('Article Title','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge" style="background:var(--br);color:#fff;font-size:11px;"><?php esc_html_e('Required','eiu-rp'); ?></span>
      </div>
      <input type="text" name="article_title" class="form-control form-control-lg eiu-sf-title-input"
        placeholder="<?php esc_attr_e('Enter the full title of your research article…','eiu-rp'); ?>" required>
      <div class="eiu-err" id="err-article_title"></div>
    </div>

    <!-- 2. Thumbnail — direct upload only (v1.4: Media Library removed) -->
    <div class="eiu-sf-section" data-step="1">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-muted"><i class="bi bi-image"></i> 2. <?php esc_html_e('Article Thumbnail','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge bg-warning text-dark" style="font-size:11px;"><?php esc_html_e('Optional','eiu-rp'); ?></span>
      </div>
      <div class="row g-3 align-items-start">
        <div class="col-md-5">
          <!-- Clickable drop zone — triggers file picker directly -->
          <div class="eiu-sf-thumb-area" id="eiu-thumb-area" title="<?php esc_attr_e('Click or drag an image here','eiu-rp'); ?>">
            <img src="" alt="" class="preview" id="eiu-thumb-img">
            <div class="thumb-overlay">
              <span class="text-white fw-semibold small"><i class="bi bi-arrow-repeat me-1"></i><?php esc_html_e('Change','eiu-rp'); ?></span>
            </div>
            <div class="eiu-sf-thumb-placeholder" id="eiu-thumb-ph">
              <i class="bi bi-cloud-upload text-secondary" style="font-size:2rem;display:block;margin-bottom:6px;"></i>
              <p class="mb-1 small fw-semibold" style="color:#495057;"><?php esc_html_e('Click to upload or drag & drop','eiu-rp'); ?></p>
              <p class="mb-0 small text-muted"><?php esc_html_e('JPG, PNG, WebP · 800×500px recommended','eiu-rp'); ?></p>
            </div>
          </div>
          <!-- Hidden file input — triggered by click on drop zone -->
          <!-- name="article_thumbnail" ensures direct upload if AJAX pre-upload fails -->
          <input type="file" name="article_thumbnail" id="eiu-thumb-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
          <div class="mt-2 d-flex gap-2">
            <button type="button" class="btn btn-sm fw-semibold" id="eiu-thumb-upload-btn"
              style="background:var(--bb);color:#fff;border:none;border-radius:6px;">
              <i class="bi bi-upload me-1"></i><?php esc_html_e('Choose Image','eiu-rp'); ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger d-none" id="eiu-thumb-clear-btn">
              <i class="bi bi-x me-1"></i><?php esc_html_e('Remove','eiu-rp'); ?>
            </button>
          </div>
        </div>
        <div class="col-md-7">
          <div class="p-3 rounded-3" style="background:#f8f9fa;border:1px solid #e9ecef;font-size:13px;">
            <p class="mb-2 fw-semibold" style="color:#495057;"><i class="bi bi-info-circle text-primary me-1"></i><?php esc_html_e('Tips','eiu-rp'); ?></p>
            <ul class="mb-0 text-muted ps-3" style="line-height:1.9;">
              <li><?php esc_html_e('Click "Choose Image" or drag a file onto the box','eiu-rp'); ?></li>
              <li><?php esc_html_e('Accepted formats: JPG, PNG, WebP','eiu-rp'); ?></li>
              <li><?php esc_html_e('Min 800 × 500 pixels recommended','eiu-rp'); ?></li>
              <li><?php esc_html_e('Max file size: 5 MB','eiu-rp'); ?></li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Abstract -->
    <div class="eiu-sf-section" data-step="2">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-red"><i class="bi bi-file-richtext"></i> 3. <?php esc_html_e('Abstract','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge" style="background:var(--br);color:#fff;font-size:11px;"><?php esc_html_e('Required','eiu-rp'); ?></span>
      </div>
      <!-- Quill Rich Text Editor — Abstract (Researcher) -->
      <div class="eiu-quill-wrap" id="eiu-abstract-quill-wrap">
        <div id="eiu-abstract-quill" style="min-height:260px;"></div>
      </div>
      <textarea id="<?php echo esc_attr($abstract_editor_id); ?>"
        name="abstract"
        class="eiu-quill-hidden"
        style="display:none;"></textarea>
      <div class="eiu-err" id="err-abstract"></div>
    </div>

    <!-- 4. Categories & Keywords -->
    <div class="eiu-sf-section" data-step="3">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-muted"><i class="bi bi-tags"></i> 4. <?php esc_html_e('Categories & Keywords','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge" style="background:var(--br);color:#fff;font-size:11px;"><?php esc_html_e('Required','eiu-rp'); ?></span>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-semibold text-secondary text-uppercase" style="letter-spacing:.5px;"><?php esc_html_e('Subject / Category','eiu-rp'); ?> <span style="color:var(--br);">*</span></label>
          <select name="subject" class="form-select" required>
            <option value=""><?php esc_html_e('— Select a subject area —','eiu-rp'); ?></option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
            <?php endforeach; ?>
          </select>
          <div class="eiu-err" id="err-subject"></div>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold text-secondary text-uppercase" style="letter-spacing:.5px;"><?php esc_html_e('Keywords','eiu-rp'); ?></label>
          <input type="text" name="keywords" class="form-control" placeholder="<?php esc_attr_e('e.g. immunology, virology (comma separated)','eiu-rp'); ?>">
        </div>
      </div>
    </div>

    <!-- 5. Author Details with photo -->
    <div class="eiu-sf-section" data-step="4">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-blue"><i class="bi bi-person-fill"></i> 5. <?php esc_html_e('Author Details','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge" style="background:var(--br);color:#fff;font-size:11px;"><?php esc_html_e('Required','eiu-rp'); ?></span>
      </div>
      <div class="eiu-sf-author-block">
        <!-- Author photo — direct upload only (v1.4) -->
        <div class="eiu-photo-circle-wrap">
          <div class="eiu-photo-circle" id="eiu-author-photo"
            title="<?php esc_attr_e('Click to upload author photo','eiu-rp'); ?>">
            <span id="eiu-author-initial">A</span>
            <img src="" alt="" id="eiu-author-photo-img" style="display:none;">
            <div class="overlay"><i class="bi bi-camera"></i></div>
          </div>
          <div class="eiu-photo-btn">
            <strong style="font-size:13px;color:#374151;"><?php esc_html_e('Author Photo','eiu-rp'); ?></strong>
            <div class="mt-1">
              <button type="button" class="btn btn-sm" id="eiu-author-upload-btn"
                style="font-size:11px;background:var(--bbl);color:var(--bb);border:1px solid #b8d0f0;">
                <i class="bi bi-upload me-1"></i><?php esc_html_e('Upload Photo','eiu-rp'); ?>
              </button>
            </div>
            <input type="file" name="author_photo" id="eiu-author-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
            <p><?php esc_html_e('Optional · JPG/PNG/WebP · circular avatar','eiu-rp'); ?></p>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label"><?php esc_html_e('Full Name','eiu-rp'); ?> <span style="color:var(--br);">*</span>
              <?php if ($is_prefilled && $prefill_author_name): ?>
                <span class="eiu-sf-autofill-badge"><i class="bi bi-person-check-fill me-1"></i><?php esc_html_e('Auto-filled','eiu-rp'); ?></span>
              <?php endif; ?>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-person text-secondary"></i></span>
              <input type="text" name="author_name" class="form-control<?php echo ($is_prefilled && $prefill_author_name) ? ' eiu-sf-autofilled' : ''; ?>"
                placeholder="<?php esc_attr_e('Full name','eiu-rp'); ?>"
                value="<?php echo esc_attr($prefill_author_name); ?>"
                required>
            </div>
            <div class="eiu-err" id="err-author_name"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label"><?php esc_html_e('Email Address','eiu-rp'); ?> <span style="color:var(--br);">*</span>
              <?php if ($is_prefilled && $prefill_author_email): ?>
                <span class="eiu-sf-autofill-badge"><i class="bi bi-envelope-check-fill me-1"></i><?php esc_html_e('Auto-filled','eiu-rp'); ?></span>
              <?php endif; ?>
            </label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-envelope text-secondary"></i></span>
              <input type="email" name="author_email" class="form-control<?php echo ($is_prefilled && $prefill_author_email) ? ' eiu-sf-autofilled' : ''; ?>"
                placeholder="author@example.com"
                value="<?php echo esc_attr($prefill_author_email); ?>"
                required>
            </div>
            <div class="eiu-err" id="err-author_email"></div>
          </div>
        </div>
        <!-- Affiliation (v2.2) — optional, supports basic HTML via Quill -->
        <div style="margin-top:16px;">
          <label class="form-label">
            <?php esc_html_e('Affiliation','eiu-rp'); ?>
            <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:6px;"><?php esc_html_e('(Optional — e.g. university, department, institution)','eiu-rp'); ?></span>
          </label>
          <!-- Quill editor for formatted affiliation text -->
          <div class="eiu-quill-wrap" id="eiu-affiliation-quill-wrap" style="min-height:90px;">
            <div id="eiu-affiliation-quill" style="min-height:64px;"></div>
          </div>
          <textarea id="eiu-affiliation-ta" name="author_affiliation"
            class="eiu-quill-hidden" style="display:none;"></textarea>
          <p style="font-size:11px;color:#9ca3af;margin:4px 0 0;"><?php esc_html_e('You may use bold, italic, or links to format your affiliation (e.g. <b>EIU</b>, Faculty of Engineering).','eiu-rp'); ?></p>
        </div>
      </div>
    </div>

    <!-- 6. Co-Authors — dynamic, multiple (v2.0) -->
    <div class="eiu-sf-section" data-step="5">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-red"><i class="bi bi-person-plus"></i> 6. <?php esc_html_e('Co-Author Details','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge bg-warning text-dark" style="font-size:11px;"><?php esc_html_e('Optional','eiu-rp'); ?></span>
      </div>
      <p class="small text-muted mb-3"><?php esc_html_e('Add one or more co-authors. Click + to add additional co-authors.','eiu-rp'); ?></p>

      <div id="eiu-coauthors-list">
        <!-- First co-author row (always shown) -->
        <div class="eiu-coauthor-row" style="background:#fafbff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;margin-bottom:12px;position:relative;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:13px;font-weight:700;color:#1a4988;"><i class="bi bi-person-plus me-1"></i><?php esc_html_e('Co-Author','eiu-rp'); ?> <span class="eiu-ca-num">1</span></span>
            <button type="button" class="eiu-coauthor-remove btn btn-sm"
              style="font-size:11px;color:#dc2626;border-color:#fca5a5;display:none;"
              title="<?php esc_attr_e('Remove co-author','eiu-rp'); ?>">
              <i class="bi bi-x-circle me-1"></i><?php esc_html_e('Remove','eiu-rp'); ?>
            </button>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Full Name','eiu-rp'); ?></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-person text-secondary"></i></span>
                <input type="text" name="co_authors[0][name]" class="form-control eiu-ca-name" placeholder="<?php esc_attr_e('Full name','eiu-rp'); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Email Address','eiu-rp'); ?></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope text-secondary"></i></span>
                <input type="email" name="co_authors[0][email]" class="form-control eiu-ca-email" placeholder="coauthor@example.com">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Organization / Institution','eiu-rp'); ?></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-building text-secondary"></i></span>
                <input type="text" name="co_authors[0][org]" class="form-control eiu-ca-org" placeholder="<?php esc_attr_e('University or institution','eiu-rp'); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Contribution','eiu-rp'); ?> <span class="text-muted fw-normal"><?php esc_html_e('(optional)','eiu-rp'); ?></span></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-pencil text-secondary"></i></span>
                <input type="text" name="co_authors[0][contribution]" class="form-control eiu-ca-contrib" placeholder="<?php esc_attr_e('e.g. Data analysis, Writing','eiu-rp'); ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Hidden template for cloning -->
      <template id="eiu-coauthor-template">
        <div class="eiu-coauthor-row" style="background:#fafbff;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;margin-bottom:12px;position:relative;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:13px;font-weight:700;color:#1a4988;"><i class="bi bi-person-plus me-1"></i><?php esc_html_e('Co-Author','eiu-rp'); ?> <span class="eiu-ca-num"></span></span>
            <button type="button" class="eiu-coauthor-remove btn btn-sm"
              style="font-size:11px;color:#dc2626;border-color:#fca5a5;"
              title="<?php esc_attr_e('Remove co-author','eiu-rp'); ?>">
              <i class="bi bi-x-circle me-1"></i><?php esc_html_e('Remove','eiu-rp'); ?>
            </button>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Full Name','eiu-rp'); ?></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-person text-secondary"></i></span>
                <input type="text" name="" class="form-control eiu-ca-name" placeholder="<?php esc_attr_e('Full name','eiu-rp'); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Email Address','eiu-rp'); ?></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope text-secondary"></i></span>
                <input type="email" name="" class="form-control eiu-ca-email" placeholder="coauthor@example.com">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Organization / Institution','eiu-rp'); ?></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-building text-secondary"></i></span>
                <input type="text" name="" class="form-control eiu-ca-org" placeholder="<?php esc_attr_e('University or institution','eiu-rp'); ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold"><?php esc_html_e('Contribution','eiu-rp'); ?> <span class="text-muted fw-normal"><?php esc_html_e('(optional)','eiu-rp'); ?></span></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-pencil text-secondary"></i></span>
                <input type="text" name="" class="form-control eiu-ca-contrib" placeholder="<?php esc_attr_e('e.g. Data analysis, Writing','eiu-rp'); ?>">
              </div>
            </div>
          </div>
        </div>
      </template>

      <button type="button" class="eiu-adviser-add mt-2" id="eiu-add-coauthor">
        <i class="bi bi-plus-circle"></i>
        <?php esc_html_e('Add Another Co-Author','eiu-rp'); ?>
      </button>
    </div>

    <!-- 7. Advisers (dynamic, optional) -->
    <div class="eiu-sf-section" data-step="6">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-muted"><i class="bi bi-people"></i> 7. <?php esc_html_e('Advisers','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge bg-warning text-dark" style="font-size:11px;"><?php esc_html_e('Optional','eiu-rp'); ?></span>
      </div>
      <p class="small text-muted mb-3"><?php esc_html_e('Add names of thesis advisers or supervisors. Click + to add more.','eiu-rp'); ?></p>
      <div id="eiu-advisers-list">
        <div class="eiu-adviser-row">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-person-badge text-secondary"></i></span>
            <input type="text" name="advisers[]" class="form-control"
              placeholder="<?php esc_attr_e('Adviser full name','eiu-rp'); ?>">
          </div>
          <button type="button" class="eiu-adviser-remove" title="<?php esc_attr_e('Remove','eiu-rp'); ?>" style="display:none;">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </div>
      <button type="button" class="eiu-adviser-add mt-2" id="eiu-add-adviser">
        <i class="bi bi-plus-circle"></i>
        <?php esc_html_e('Add Another Adviser','eiu-rp'); ?>
      </button>
    </div>

    <!-- 8. Disclosures + Contact Details -->
    <div class="eiu-sf-section" data-step="7">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-muted"><i class="bi bi-shield-check"></i> 8. <?php esc_html_e('Disclosures & Contact','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge bg-warning text-dark" style="font-size:11px;"><?php esc_html_e('Required','eiu-rp'); ?></span>
      </div>

      <!-- Human Participants / Ethics Declaration (v2.0) -->
      <div class="eiu-disclosure-block mb-4" id="eiu-ethics-block">
        <div class="eiu-disclosure-title">
          <i class="bi bi-people-fill me-2" style="color:var(--bb);"></i>
          <?php esc_html_e('Human Participants in Research','eiu-rp'); ?>
          <span style="margin-left:8px;font-size:11px;background:var(--br);color:#fff;border-radius:4px;padding:1px 6px;font-weight:700;"><?php esc_html_e('Required','eiu-rp'); ?></span>
        </div>
        <p class="eiu-disclosure-placeholder">
          <?php esc_html_e('Did you involve human participants in your research? For example, surveys, interviews, observations, etc.?','eiu-rp'); ?>
        </p>
        <div class="eiu-radio-group" style="flex-direction:column;gap:12px;">

          <!-- Option 1: Full Ethics Approval -->
          <label class="eiu-radio-opt" style="align-items:flex-start;">
            <input type="radio" name="human_participants" value="full_ethics" required id="eiu-hp-full">
            <span class="eiu-radio-box" style="margin-top:2px;flex-shrink:0;">
              <?php esc_html_e('Full Ethics Approval Required','eiu-rp'); ?>
            </span>
          </label>
          <div id="eiu-hp-full-detail" style="display:none;margin:4px 0 4px 34px;padding:14px 16px;background:#fff8f0;border:1px solid #fed7aa;border-radius:8px;">
            <p style="font-size:13px;color:#92400e;margin:0 0 10px;"><i class="bi bi-info-circle me-1"></i>
              <?php esc_html_e('Yes, my study involves human participants and includes vulnerable populations, sensitive data, or interventions.','eiu-rp'); ?>
            </p>
            <label class="form-label small fw-semibold"><?php esc_html_e('Please upload official IRB or ethics committee approval documentation.','eiu-rp'); ?> <span style="color:var(--br);">*</span></label>
            <div class="eiu-ethics-upload-card" id="eiu-ethics-full-zone"
              onclick="if(!this.dataset.uploaded)document.getElementById('eiu-ethics-full-file').click()"
              ondragover="event.preventDefault();this.classList.add('eiu-ethics-hover')"
              ondragleave="this.classList.remove('eiu-ethics-hover')"
              ondrop="event.preventDefault();this.classList.remove('eiu-ethics-hover');eiu_ethics_handle(event.dataTransfer.files[0],'eiu-ethics-full-file','eiu-ethics-full-zone')">
              <div class="eiu-eu-idle" id="eiu-ethics-full-idle">
                <div class="ap-uc-icon-wrap"><i class="bi bi-file-earmark-plus ap-uc-icon"></i></div>
                <span class="ap-uc-label"><?php esc_html_e('Click to upload IRB / Ethics Approval','eiu-rp'); ?></span>
              </div>
              <div class="eiu-eu-done" id="eiu-ethics-full-done" style="display:none;">
                <div class="ap-uc-badge"><i class="bi bi-patch-check-fill ap-uc-badge-icon"></i></div>
                <div class="ap-uc-info">
                  <span class="ap-uc-fname" id="eiu-ethics-full-fname"></span>
                  <span class="ap-uc-complete"><?php esc_html_e('Upload complete','eiu-rp'); ?></span>
                </div>
                <button type="button" class="ap-uc-remove"
                  onclick="event.stopPropagation();eiu_ethics_clear('eiu-ethics-full-file','eiu-ethics-full-zone')"
                  title="<?php esc_attr_e('Remove','eiu-rp'); ?>">&times;</button>
              </div>
            </div>
            <input type="file" id="eiu-ethics-full-file" name="ethics_file"
              accept=".pdf,.doc,.docx" style="display:none;"
              onchange="eiu_ethics_handle(this.files[0],'eiu-ethics-full-file','eiu-ethics-full-zone')">
            <p style="font-size:11px;color:#9ca3af;margin:6px 0 0;"><?php esc_html_e('PDF, DOC, DOCX — max 10 MB','eiu-rp'); ?></p>
          </div>

          <!-- Option 2: Low-Risk -->
          <label class="eiu-radio-opt" style="align-items:flex-start;">
            <input type="radio" name="human_participants" value="low_risk" id="eiu-hp-low">
            <span class="eiu-radio-box" style="margin-top:2px;flex-shrink:0;">
              <?php esc_html_e('Low-Risk Study with Declaration','eiu-rp'); ?>
            </span>
          </label>
          <div id="eiu-hp-low-detail" style="display:none;margin:4px 0 4px 34px;padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
            <p style="font-size:13px;color:#166534;margin:0 0 10px;"><i class="bi bi-info-circle me-1"></i>
              <?php esc_html_e('Yes, my study involves human participants but is considered low-risk (e.g., voluntary, anonymized, non-sensitive data).','eiu-rp'); ?>
            </p>
            <label class="form-label small fw-semibold"><?php esc_html_e('Please upload a structured ethics self-declaration confirming adherence to recognized ethical standards.','eiu-rp'); ?> <span style="color:var(--br);">*</span></label>
            <div class="eiu-ethics-upload-card" id="eiu-ethics-low-zone"
              onclick="if(!this.dataset.uploaded)document.getElementById('eiu-ethics-low-file').click()"
              ondragover="event.preventDefault();this.classList.add('eiu-ethics-hover')"
              ondragleave="this.classList.remove('eiu-ethics-hover')"
              ondrop="event.preventDefault();this.classList.remove('eiu-ethics-hover');eiu_ethics_handle(event.dataTransfer.files[0],'eiu-ethics-low-file','eiu-ethics-low-zone')">
              <div class="eiu-eu-idle" id="eiu-ethics-low-idle">
                <div class="ap-uc-icon-wrap"><i class="bi bi-file-earmark-plus ap-uc-icon"></i></div>
                <span class="ap-uc-label"><?php esc_html_e('Click to upload Ethics Self-Declaration','eiu-rp'); ?></span>
              </div>
              <div class="eiu-eu-done" id="eiu-ethics-low-done" style="display:none;">
                <div class="ap-uc-badge"><i class="bi bi-patch-check-fill ap-uc-badge-icon"></i></div>
                <div class="ap-uc-info">
                  <span class="ap-uc-fname" id="eiu-ethics-low-fname"></span>
                  <span class="ap-uc-complete"><?php esc_html_e('Upload complete','eiu-rp'); ?></span>
                </div>
                <button type="button" class="ap-uc-remove"
                  onclick="event.stopPropagation();eiu_ethics_clear('eiu-ethics-low-file','eiu-ethics-low-zone')"
                  title="<?php esc_attr_e('Remove','eiu-rp'); ?>">&times;</button>
              </div>
            </div>
            <input type="file" id="eiu-ethics-low-file" name="ethics_file"
              accept=".pdf,.doc,.docx" style="display:none;"
              onchange="eiu_ethics_handle(this.files[0],'eiu-ethics-low-file','eiu-ethics-low-zone')">
            <p style="font-size:11px;color:#9ca3af;margin:6px 0 0;"><?php esc_html_e('PDF, DOC, DOCX — max 10 MB','eiu-rp'); ?></p>
          </div>

          <!-- Option 3: No Human Participants -->
          <label class="eiu-radio-opt" style="align-items:flex-start;">
            <input type="radio" name="human_participants" value="none" id="eiu-hp-none">
            <span class="eiu-radio-box" style="margin-top:2px;flex-shrink:0;">
              <?php esc_html_e('No Human Participants','eiu-rp'); ?>
            </span>
          </label>
          <div id="eiu-hp-none-detail" style="display:none;margin:4px 0 4px 34px;padding:10px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;">
            <p style="font-size:13px;color:#374151;margin:0;"><i class="bi bi-check-circle-fill me-2" style="color:#10b981;"></i>
              <?php esc_html_e('No, my study does not involve human participants. No ethics documentation is required.','eiu-rp'); ?>
            </p>
          </div>

        </div><!-- /.eiu-radio-group -->
      </div><!-- /#eiu-ethics-block -->

      <!-- Acknowledgements -->
      <div class="eiu-disclosure-block mb-4">
        <div class="eiu-disclosure-title">
          <i class="bi bi-hand-thumbs-up me-2" style="color:var(--bb);"></i>
          <?php esc_html_e('Acknowledgements','eiu-rp'); ?>
        </div>
        <p class="eiu-disclosure-placeholder">
          <?php esc_html_e('Lorem ipsum dolor sit amet, consectetur adipiscing elit. The authors confirm that this manuscript has not been submitted elsewhere and that all contributors are listed. All funding sources, institutional affiliations, and any assistance received during the preparation of the work should be acknowledged.','eiu-rp'); ?>
        </p>
        <label class="form-label small fw-semibold text-secondary mt-2">
          <?php esc_html_e('Do you have acknowledgements to declare?','eiu-rp'); ?> <span style="color:var(--br);">*</span>
        </label>
        <div class="eiu-radio-group">
          <label class="eiu-radio-opt">
            <input type="radio" name="acknowledgements" value="yes" required>
            <span class="eiu-radio-box"><?php esc_html_e('Yes','eiu-rp'); ?></span>
          </label>
          <label class="eiu-radio-opt">
            <input type="radio" name="acknowledgements" value="no">
            <span class="eiu-radio-box"><?php esc_html_e('No','eiu-rp'); ?></span>
          </label>
        </div>
        <div id="eiu-ack-detail" style="display:none;margin-top:10px;">
          <textarea name="acknowledgements_detail" class="form-control" rows="3"
            placeholder="<?php esc_attr_e('Please provide your acknowledgements here…','eiu-rp'); ?>"></textarea>
        </div>
      </div>

      <!-- Intellectual Property -->
      <div class="eiu-disclosure-block mb-4">
        <div class="eiu-disclosure-title">
          <i class="bi bi-lightbulb me-2" style="color:var(--bb);"></i>
          <?php esc_html_e('Intellectual Property: Patents &amp; Copyrights','eiu-rp'); ?>
        </div>
        <p class="eiu-disclosure-question">
          <?php esc_html_e('Do you have any patents (planned, pending, or issued) that may be broadly relevant to this work? If yes, provide details below.','eiu-rp'); ?>
        </p>
        <div class="eiu-radio-group">
          <label class="eiu-radio-opt">
            <input type="radio" name="ip_patents" value="none" required>
            <span class="eiu-radio-box"><?php esc_html_e('No relevant patents or copyrights','eiu-rp'); ?></span>
          </label>
          <label class="eiu-radio-opt">
            <input type="radio" name="ip_patents" value="yes">
            <span class="eiu-radio-box"><?php esc_html_e('Yes, there are relevant patents/copyrights','eiu-rp'); ?></span>
          </label>
        </div>
        <div id="eiu-patent-detail" style="display:none;margin-top:10px;">
          <textarea name="ip_patents_detail" class="form-control" rows="3"
            placeholder="<?php esc_attr_e('Please describe the relevant patents or copyrights…','eiu-rp'); ?>"></textarea>
        </div>
      </div>

      <!-- Conflict of Interest / General Disclosures -->
      <div class="mb-3">
        <label class="form-label small fw-semibold text-secondary">
          <?php esc_html_e('Conflict of Interest / Other Disclosures','eiu-rp'); ?>
          <span class="eiu-sf-optional-note"><?php esc_html_e('(optional)','eiu-rp'); ?></span>
        </label>
        <input type="text" name="disclosures" class="form-control" placeholder="<?php esc_attr_e('Funding sources, conflicts of interest…','eiu-rp'); ?>">
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label small fw-semibold text-secondary"><?php esc_html_e('Contact Number','eiu-rp'); ?> <span class="eiu-sf-optional-note"><?php esc_html_e('(optional)','eiu-rp'); ?></span></label>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-telephone text-secondary"></i></span>
            <input type="tel" name="contact_number" class="form-control" placeholder="+1 234 567 8900">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold text-secondary"><?php esc_html_e('Country','eiu-rp'); ?> <span class="eiu-sf-optional-note"><?php esc_html_e('(optional)','eiu-rp'); ?></span></label>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-globe text-secondary"></i></span>
            <input type="text" name="country" class="form-control" placeholder="<?php esc_attr_e('Your country','eiu-rp'); ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- 9. References -->
    <div class="eiu-sf-section" data-step="8">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-blue"><i class="bi bi-book"></i> 9. <?php esc_html_e('References','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge bg-warning text-dark" style="font-size:11px;"><?php esc_html_e('Optional','eiu-rp'); ?></span>
      </div>
      <!-- Quill Rich Text Editor — References -->
      <div class="eiu-quill-wrap" id="eiu-references-quill-wrap">
        <div id="eiu-references-quill" style="min-height:130px;"></div>
      </div>
      <textarea id="<?php echo esc_attr($references_editor_id); ?>"
        name="references"
        class="eiu-quill-hidden"
        style="display:none;"></textarea>
    </div>

    <!-- 10. Files Upload -->
    <div class="eiu-sf-section" data-step="9">
      <div class="eiu-sf-section-head">
        <span class="eiu-sf-tag tag-dark"><i class="bi bi-cloud-arrow-up"></i> 12. <?php esc_html_e('Files Upload','eiu-rp'); ?></span>
        <div class="eiu-sf-line"></div>
        <span class="badge" style="background:var(--br);color:#fff;font-size:11px;"><?php esc_html_e('Required','eiu-rp'); ?></span>
      </div>
      <div class="eiu-sf-file-drop" id="eiu-file-drop">
        <input type="file" name="article_file" id="article_file" accept=".pdf,.ppt,.pptx" required>
        <div id="eiu-file-inner">
          <i class="bi bi-cloud-upload" style="font-size:2.4rem;color:var(--bb);display:block;margin-bottom:10px;"></i>
          <p class="fw-semibold mb-2" style="color:#495057;"><?php esc_html_e('Drag & drop your article file here','eiu-rp'); ?></p>
          <button type="button" class="btn btn-sm px-4 fw-semibold"
            style="background:var(--bb);color:#fff;border-radius:6px;"
            onclick="document.getElementById('article_file').click()">
            <i class="bi bi-folder2-open me-1"></i><?php esc_html_e('Browse Files','eiu-rp'); ?>
          </button>
          <p class="mt-2 mb-0 small text-muted"><?php printf(esc_html__('Accepted: PDF, PPT, PPTX  ·  Max %d MB','eiu-rp'), $max_mb); ?></p>
        </div>
        <div id="eiu-file-chosen" class="d-none eiu-sf-file-chosen">
          <i class="bi bi-file-earmark-text fs-4" style="color:var(--bb);flex-shrink:0;"></i>
          <span class="fname" id="eiu-file-name"></span>
          <span class="badge" style="background:var(--bb);color:#fff;"><i class="bi bi-check-lg me-1"></i><?php esc_html_e('Ready','eiu-rp'); ?></span>
          <button type="button" id="eiu-clear-file" class="btn btn-sm btn-outline-danger ms-auto"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>
      <div class="eiu-err" id="err-article_file"></div>
      <div class="mt-2 p-2 rounded-3" style="background:#fff8e1;border:1px solid #ffe082;font-size:12px;color:#5d4037;">
        <i class="bi bi-info-circle-fill me-1" style="color:#f59e0b;"></i>
        <?php esc_html_e('If your file exceeds 5 MB, please compress it using:','eiu-rp'); ?>
        <a href="https://tools.pdf24.org/en/compress-pdf" target="_blank" rel="noopener noreferrer" style="color:#1a4988;font-weight:600;">tools.pdf24.org/en/compress-pdf</a>
      </div>
    </div>

    <!-- Wizard nav bar (replaces old submit area — shown on all steps) -->
    <div class="eiu-sf-wizard-nav" id="eiu-wizard-nav">

      <!-- Prev -->
      <button type="button" id="eiu-wizard-prev" class="eiu-sf-nav-btn eiu-sf-nav-prev" style="visibility:hidden;">
        <i class="bi bi-arrow-left"></i> <?php esc_html_e('Previous','eiu-rp'); ?>
      </button>

      <!-- Centre label + draft save -->
      <div style="text-align:center;flex:1;">
        <div class="step-label" id="eiu-wizard-step-label">
          <?php echo esc_html(sprintf(__('Step %1$d of %2$d','eiu-rp'), 1, 10)); ?>
        </div>
        <?php if ( is_user_logged_in() ): ?>
        <button type="button" id="eiu-save-draft-btn"
          style="background:none;border:none;color:var(--bb);font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:4px;padding:4px 0;opacity:.7;transition:opacity .15s;">
          <i class="bi bi-floppy"></i> <?php esc_html_e('Save draft','eiu-rp'); ?>
        </button>
        <?php endif; ?>
        <div id="eiu-draft-msg" style="font-size:12px;margin-top:4px;min-height:16px;text-align:center;display:none;"></div>
      </div>

      <!-- Next / Submit -->
      <button type="button" id="eiu-wizard-next" class="eiu-sf-nav-btn eiu-sf-nav-next">
        <?php esc_html_e('Next','eiu-rp'); ?> <i class="bi bi-arrow-right"></i>
      </button>

      <!-- Final submit (hidden until last step) -->
      <button type="submit" id="eiu-submit-btn" class="eiu-sf-submit-btn" style="display:none;">
        <span id="eiu-btn-text"><i class="bi bi-send-fill me-2"></i><?php esc_html_e('Submit','eiu-rp'); ?></span>
        <span id="eiu-btn-spin" class="d-none">
          <span class="spinner-border spinner-border-sm me-2"></span>
          <?php esc_html_e('Submitting…','eiu-rp'); ?>
        </span>
      </button>

    </div>

    <p style="text-align:center;font-size:11px;color:#9ca3af;padding:0 32px 16px;margin:0;">
      <i class="bi bi-lock-fill me-1"></i>
      <?php esc_html_e('By submitting, you confirm this work is original and you have the right to publish it.','eiu-rp'); ?>
    </p>

  </form>
  </div><!-- .eiu-sf-card -->

  <?php if (get_option('eiu_rp_listing_page_id')): ?>
    <a href="<?php echo esc_url(get_permalink(get_option('eiu_rp_listing_page_id'))); ?>" class="eiu-sf-footer">
      <i class="bi bi-search" style="font-size:1.3rem;flex-shrink:0;"></i>
      <span class="flex-grow-1"><?php echo esc_html( get_option('eiu_rp_term_explore_link','Explore Research to See All Submitted Articles') ); ?></span>
      <i class="bi bi-arrow-right"></i>
    </a>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
'use strict';

/* ─────────────────────────────────────────────────────────────────────────
   EIU Submission Wizard v2.0
   One-step-at-a-time navigation with validation, progress bar, and smooth
   transitions. All original form functionality (file upload, TinyMCE,
   draft save, AJAX submit) is preserved exactly.
   ─────────────────────────────────────────────────────────────────────── */
var sections   = document.querySelectorAll('.eiu-sf-section[data-step]');
var tabs       = document.querySelectorAll('.eiu-sf-tab');
var totalSteps = sections.length;
var curStep    = 0;

var prevBtn  = document.getElementById('eiu-wizard-prev');
var nextBtn  = document.getElementById('eiu-wizard-next');
var submitBtn= document.getElementById('eiu-submit-btn');
var stepLbl  = document.getElementById('eiu-wizard-step-label');
var progressFill = document.getElementById('eiu-sf-progress-fill');

/* ── Per-step required field selectors ── */
var stepValidation = {
  0: ['article_title'],      // Title
  1: [],                     // Thumbnail optional
  2: [],                     // Abstract — validated via TinyMCE below
  3: ['subject'],            // Categories
  4: ['author_name','author_email'], // Author (author_org removed — field not in form)
  5: [],                     // Co-Author optional
  6: [],                     // Advisers optional
  7: ['acknowledgements','human_participants'],   // Disclosures (radios required)
  8: [],                     // References optional
  9: ['article_file'],       // Files
};

function updateWizardUI(){
  /* Show only active section */
  sections.forEach(function(s,i){
    s.classList.toggle('is-active-step', i===curStep);
  });
  /* Update pill strip */
  tabs.forEach(function(t,i){
    t.classList.remove('is-active','is-done');
    if(i===curStep)    t.classList.add('is-active');
    else if(i<curStep) t.classList.add('is-done');
  });
  /* Step label */
  if(stepLbl) stepLbl.textContent = <?php echo wp_json_encode(__('Step %1$s of %2$s','eiu-rp')); ?>
    .replace('%1$s', curStep+1).replace('%2$s', totalSteps);
  /* Progress bar */
  if(progressFill){
    var pct = Math.round(((curStep+1)/totalSteps)*100);
    progressFill.style.width = pct+'%';
  }
  /* Prev button */
  if(prevBtn){
    prevBtn.style.visibility = curStep===0 ? 'hidden' : 'visible';
  }
  /* Next / Submit toggle */
  var isLast = curStep === totalSteps-1;
  if(nextBtn)   nextBtn.style.display  = isLast ? 'none'  : '';
  if(submitBtn) submitBtn.style.display= isLast ? ''      : 'none';
  /* Scroll to top of the card smoothly */
  var wrap = document.getElementById('eiu-rp-submission-wrap');
  if(wrap) wrap.scrollIntoView({behavior:'smooth',block:'start'});
  /* ── Deferred TinyMCE init ───────────────────────────────────────────
     TinyMCE cannot initialise into a display:none element — doing so
     produces a blank white box with no toolbar. We trigger init
     the first time a step containing an editor becomes visible.
     eiu_initQuill() is defined in the Quill init block below.
     It is safe to call multiple times — it checks whether the editor
     is already active before calling tinymce.init().
  ─────────────────────────────────────────────────────────────────── */
  /* Initialise Quill editor when step becomes visible */
  if(typeof window.eiu_initQuill === 'function'){
    window.eiu_initQuill(curStep);
  }
}

function validateStep(n){
  var fields = stepValidation[n] || [];
  var ok = true;
  /* Abstract (step 2) — Quill editor */
  if(n===2){
    var txt='';
    if(typeof Quill!=='undefined' && window.eiu_abstract_quill){
      txt=window.eiu_abstract_quill.getText().trim();
    }
    if(!txt){
      var absTa=document.getElementById(abstractEditorId);
      if(absTa) txt=(absTa.value||'').replace(/<[^>]+>/g,'').trim();
    }
    if(!txt){
      var errEl=document.getElementById('err-abstract');
      if(errEl){errEl.textContent=<?php echo wp_json_encode(__('Please enter an abstract.','eiu-rp')); ?>;errEl.style.display='block';}
      ok=false;
    } else {
      var errEl2=document.getElementById('err-abstract');
      if(errEl2) errEl2.style.display='none';
    }
  }
    /* Standard inputs/selects */
  fields.forEach(function(name){
    var els=document.querySelectorAll('[name="'+name+'"]');
    var val='';
    els.forEach(function(el){
      if((el.type==='radio'||el.type==='checkbox')&&el.checked) val=el.value;
      else if(el.type!=='radio'&&el.type!=='checkbox') val=(el.value||'').trim();
    });
    var errId='err-'+name;
    var errEl=document.getElementById(errId);
    if(!val){
      ok=false;
      if(errEl){errEl.textContent=<?php echo wp_json_encode(__('This field is required.','eiu-rp')); ?>;errEl.style.display='block';}
      /* Focus the first empty field */
      if(els[0]&&ok===false) els[0].focus();
    } else {
      if(errEl) errEl.style.display='none';
    }
  });
  /* File upload (step 9) */
  if(n===9){
    var fileInput=document.getElementById('article_file');
    var errFile=document.getElementById('err-article_file');
    if(!fileInput||!fileInput.files||!fileInput.files.length){
      ok=false;
      if(errFile){errFile.textContent=<?php echo wp_json_encode(__('Please upload your article file.','eiu-rp')); ?>;errFile.style.display='block';}
    } else if(errFile){
      errFile.style.display='none';
    }
  }
  return ok;
}

window.eiu_goto_step = function(n){
  if(n<0||n>=totalSteps) return;
  /* Moving forward: validate current step first */
  if(n>curStep && !validateStep(curStep)) return;
  curStep=n;
  updateWizardUI();
};

if(prevBtn) prevBtn.addEventListener('click',function(){ eiu_goto_step(curStep-1); });
if(nextBtn) nextBtn.addEventListener('click',function(){ eiu_goto_step(curStep+1); });

/* Init */
updateWizardUI();

/* ── File-to-data-url helper ─────────────────── */
function fileToDataUrl(file,cb){
  var reader=new FileReader();
  reader.onload=function(e){cb(e.target.result);};
  reader.readAsDataURL(file);
}

/* ── Pending upload tracker (prevents submit race condition) ──
 * When a file is chosen, we increment a counter. When the AJAX
 * upload returns (success or fail), we decrement. The form submit
 * button is disabled while any upload is in flight.
 * ─────────────────────────────────────────────────────────────── */
var pendingUploads = 0;

function uploadStart(){
  pendingUploads++;
  var sb=document.getElementById('eiu-submit-btn');
  if(sb){ sb.disabled=true; sb.title='<?php echo esc_js(__('Uploading image, please wait…','eiu-rp')); ?>'; }
  /* Safety timeout: if upload takes >30s something went wrong — unlock the button */
  setTimeout(function(){
    if(pendingUploads>0){
      pendingUploads=0;
      var sb2=document.getElementById('eiu-submit-btn');
      if(sb2){ sb2.disabled=false; sb2.title=''; }
    }
  }, 30000);
}
function uploadDone(){
  pendingUploads=Math.max(0,pendingUploads-1);
  if(pendingUploads===0){
    var sb=document.getElementById('eiu-submit-btn');
    if(sb){ sb.disabled=false; sb.title=''; }
  }
}

/* ── Upload image via AJAX → get attachment ID ── */
var ajaxUploadNonce='<?php echo esc_js(wp_create_nonce('eiu_rp_frontend')); ?>';
var ajaxUploadUrl='<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
function uploadImageAjax(file, onSuccess){
  uploadStart();
  var fd=new FormData();
  fd.append('action','eiu_rp_upload_media_image');
  fd.append('nonce',ajaxUploadNonce);
  fd.append('image',file);
  fetch(ajaxUploadUrl,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(res){
      if(res.success && onSuccess) onSuccess(res.data);
    })
    .catch(function(){/* silent — ID stays empty */})
    .finally(function(){ uploadDone(); });
}

/* ── Thumbnail: direct upload + drag-drop only ── */
var thumbArea  = document.getElementById('eiu-thumb-area');
var thumbImg   = document.getElementById('eiu-thumb-img');
var thumbPH    = document.getElementById('eiu-thumb-ph');
var thumbAttId = document.getElementById('eiu-thumb-att-id');
var thumbFile  = document.getElementById('eiu-thumb-file-input');
var thumbUpBtn = document.getElementById('eiu-thumb-upload-btn');
var thumbClear = document.getElementById('eiu-thumb-clear-btn');

function setThumb(src, attId){
  thumbImg.src=src; thumbImg.style.display='block';
  thumbPH.style.display='none';
  thumbArea.classList.add('has-image');
  if(attId) thumbAttId.value=attId;
  if(thumbClear) thumbClear.classList.remove('d-none');
}
function clearThumb(){
  thumbImg.src=''; thumbImg.style.display='none';
  thumbPH.style.display='';
  thumbArea.classList.remove('has-image');
  thumbAttId.value='';
  if(thumbClear) thumbClear.classList.add('d-none');
  thumbFile.value='';
}

/* "Choose Image" button → file picker */
if(thumbUpBtn) thumbUpBtn.addEventListener('click',function(e){
  e.stopPropagation(); thumbFile.click();
});
/* Clicking the drop zone itself also opens file picker */
if(thumbArea) thumbArea.addEventListener('click',function(e){
  if(e.target.closest('#eiu-thumb-clear-btn')) return;
  thumbFile.click();
});
/* File selected via picker */
if(thumbFile) thumbFile.addEventListener('change',function(){
  var f=this.files[0]; if(!f) return;
  fileToDataUrl(f,function(src){ setThumb(src,''); });
  uploadImageAjax(f,function(data){ thumbAttId.value=data.attachment_id||''; });
});
/* Drag & drop */
if(thumbArea){
  thumbArea.addEventListener('dragover',function(e){e.preventDefault();this.classList.add('drag-over');});
  thumbArea.addEventListener('dragleave',function(){this.classList.remove('drag-over');});
  thumbArea.addEventListener('drop',function(e){
    e.preventDefault(); this.classList.remove('drag-over');
    var f=e.dataTransfer&&e.dataTransfer.files[0];
    if(f&&f.type.startsWith('image/')){
      var dt=new DataTransfer(); dt.items.add(f); thumbFile.files=dt.files;
      thumbFile.dispatchEvent(new Event('change'));
    }
  });
}
if(thumbClear) thumbClear.addEventListener('click',function(e){e.stopPropagation();clearThumb();});

/* ── Photo picker: direct upload only ───────────
 * Used for both author and co-author photos.
 * No wp.media — file input only.
 * opts: { circleId, imgId, initialId, attInputId, fileInputId, uploadBtnId }
 * ─────────────────────────────────────────────── */
function setupPhotoPicker(opts){
  var circle  = document.getElementById(opts.circleId);
  var img     = document.getElementById(opts.imgId);
  var initial = document.getElementById(opts.initialId);
  var attIn   = document.getElementById(opts.attInputId);
  var fileIn  = document.getElementById(opts.fileInputId);
  var uplBtn  = document.getElementById(opts.uploadBtnId);

  if(!circle||!img||!fileIn) return;

  function setPhoto(src, attId){
    img.src=src; img.style.display='block';
    if(initial) initial.style.display='none';
    if(attId) attIn.value=attId;
  }

  /* Circle click → file picker */
  circle.addEventListener('click',function(){ fileIn.click(); });
  /* Upload button click → file picker */
  if(uplBtn) uplBtn.addEventListener('click',function(e){ e.stopPropagation(); fileIn.click(); });

  /* File chosen */
  fileIn.addEventListener('change',function(){
    var f=this.files[0]; if(!f) return;
    fileToDataUrl(f,function(src){ setPhoto(src,''); });
    uploadImageAjax(f,function(data){ attIn.value=data.attachment_id||''; });
  });
}

/* Author name → update initial letter */
var authorNameInput=document.querySelector('[name="author_name"]');
if(authorNameInput) {
  // Populate initial from pre-filled value on load (v1.5 auto-fill)
  (function(){
    var ini=document.getElementById('eiu-author-initial');
    var img=document.getElementById('eiu-author-photo-img');
    if(ini && img && img.style.display==='none' && authorNameInput.value){
      ini.textContent = authorNameInput.value.charAt(0).toUpperCase() || 'A';
    }
  }());
  authorNameInput.addEventListener('input',function(){
    var ini=document.getElementById('eiu-author-initial');
    var img=document.getElementById('eiu-author-photo-img');
    if(ini&&img&&img.style.display==='none') ini.textContent=this.value.charAt(0).toUpperCase()||'A';
  });
}

setupPhotoPicker({
  circleId:'eiu-author-photo',   imgId:'eiu-author-photo-img',   initialId:'eiu-author-initial',
  attInputId:'eiu-author-att-id', fileInputId:'eiu-author-file-input', uploadBtnId:'eiu-author-upload-btn'
});

/* Co-author name → update initial letter */
var coaNameInput=document.getElementById('eiu-coauthor-name-input');
if(coaNameInput) coaNameInput.addEventListener('input',function(){
  var ini=document.getElementById('eiu-coauthor-initial');
  var img=document.getElementById('eiu-coauthor-photo-img');
  if(ini&&img&&img.style.display==='none') ini.textContent=this.value.charAt(0).toUpperCase()||'C';
});

setupPhotoPicker({
  circleId:'eiu-coauthor-photo',   imgId:'eiu-coauthor-photo-img',   initialId:'eiu-coauthor-initial',
  attInputId:'eiu-coauthor-att-id', fileInputId:'eiu-coauthor-file-input', uploadBtnId:'eiu-coauthor-upload-btn'
});

/* ── Advisers: dynamic rows ──────────────────── */
/* ── Multi-Co-Author dynamic rows (v2.0) ─────────────────── */
(function(){
  var caList  = document.getElementById('eiu-coauthors-list');
  var addBtn  = document.getElementById('eiu-add-coauthor');
  var tmpl    = document.getElementById('eiu-coauthor-template');
  if(!caList || !addBtn || !tmpl) return;

  function reindex(){
    var rows = caList.querySelectorAll('.eiu-coauthor-row');
    rows.forEach(function(row, idx){
      var num = row.querySelector('.eiu-ca-num');
      if(num) num.textContent = idx+1;
      row.querySelector('.eiu-ca-name').name  = 'co_authors['+idx+'][name]';
      row.querySelector('.eiu-ca-email').name = 'co_authors['+idx+'][email]';
      row.querySelector('.eiu-ca-org').name   = 'co_authors['+idx+'][org]';
      row.querySelector('.eiu-ca-contrib').name = 'co_authors['+idx+'][contribution]';
      // Show remove button only when more than 1 row
      var rmBtn = row.querySelector('.eiu-coauthor-remove');
      if(rmBtn) rmBtn.style.display = rows.length > 1 ? '' : 'none';
    });
  }

  addBtn.addEventListener('click', function(){
    var clone = tmpl.content.cloneNode(true);
    caList.appendChild(clone);
    reindex();
    var lastRow = caList.querySelector('.eiu-coauthor-row:last-child');
    if(lastRow) lastRow.querySelector('.eiu-ca-name').focus();
  });

  caList.addEventListener('click', function(e){
    var btn = e.target.closest('.eiu-coauthor-remove');
    if(btn){ btn.closest('.eiu-coauthor-row').remove(); reindex(); }
  });

  reindex();
}());

/* ── Ethics upload card handlers ─────────────────────────── */
window.eiu_ethics_handle = function(file, inputId, zoneId){
  if(!file) return;
  var ext = file.name.split('.').pop().toLowerCase();
  if(!['pdf','doc','docx'].includes(ext)){
    alert('<?php echo esc_js(__('Only PDF, DOC, DOCX accepted.','eiu-rp')); ?>'); return;
  }
  if(file.size > 10*1024*1024){
    alert('<?php echo esc_js(__('File must be under 10 MB.','eiu-rp')); ?>'); return;
  }
  var inp = document.getElementById(inputId);
  if(inp){ try{ var dt=new DataTransfer(); dt.items.add(file); inp.files=dt.files; }catch(e){} }
  /* derive idle/done/fname from zoneId: eiu-ethics-full-zone → eiu-ethics-full */
  var prefix = zoneId.replace('-zone','');
  var idleEl  = document.getElementById(prefix+'-idle');
  var doneEl  = document.getElementById(prefix+'-done');
  var fnameEl = document.getElementById(prefix+'-fname');
  if(fnameEl) fnameEl.textContent = file.name+' ('+Math.round(file.size/1024)+' KB)';
  if(idleEl) idleEl.style.display = 'none';
  if(doneEl) doneEl.style.display = 'flex';
  var card = document.getElementById(zoneId);
  if(card){ card.classList.add('ap-uc-selected'); card.style.cursor='default'; card.setAttribute('data-uploaded','1'); }
};
window.eiu_ethics_clear = function(inputId, zoneId){
  var inp = document.getElementById(inputId);
  if(inp) inp.value = '';
  var prefix = zoneId.replace('-zone','');
  var idleEl = document.getElementById(prefix+'-idle');
  var doneEl = document.getElementById(prefix+'-done');
  var card   = document.getElementById(zoneId);
  if(doneEl) doneEl.style.display = 'none';
  if(idleEl) idleEl.style.display = 'flex';
  if(card){ card.classList.remove('ap-uc-selected'); card.style.cursor='pointer'; card.removeAttribute('data-uploaded'); }
};

/* Ethics radio show/hide panels */
(function(){
  var radios = document.querySelectorAll('input[name="human_participants"]');
  var panels = {
    'full_ethics': document.getElementById('eiu-hp-full-detail'),
    'low_risk':    document.getElementById('eiu-hp-low-detail'),
    'none':        document.getElementById('eiu-hp-none-detail'),
  };
  function showPanel(val){
    Object.keys(panels).forEach(function(k){
      if(panels[k]) panels[k].style.display = (k===val) ? 'block' : 'none';
    });
  }
  radios.forEach(function(r){
    r.addEventListener('change', function(){ showPanel(this.value); });
  });
}());

var advList=document.getElementById('eiu-advisers-list');
document.getElementById('eiu-add-adviser').addEventListener('click',function(){
  var row=document.createElement('div');
  row.className='eiu-adviser-row';
  row.innerHTML='<div class="input-group">'+
    '<span class="input-group-text bg-white"><i class="bi bi-person-badge text-secondary"></i></span>'+
    '<input type="text" name="advisers[]" class="form-control" placeholder="<?php echo esc_js(__('Adviser full name','eiu-rp')); ?>">'+
    '<button type="button" class="eiu-adviser-remove" title="<?php echo esc_js(__('Remove','eiu-rp')); ?>">'+
      '<i class="bi bi-x"></i>'+
    '</button>';
  advList.appendChild(row);
  updateAdviserRemoveButtons();
  row.querySelector('input').focus();
});
advList.addEventListener('click',function(e){
  var btn=e.target.closest('.eiu-adviser-remove');
  if(btn){btn.closest('.eiu-adviser-row').remove();updateAdviserRemoveButtons();}
});
function updateAdviserRemoveButtons(){
  var rows=advList.querySelectorAll('.eiu-adviser-row');
  rows.forEach(function(r){
    var btn=r.querySelector('.eiu-adviser-remove');
    if(btn) btn.style.display=rows.length>1?'':'none';
  });
}
updateAdviserRemoveButtons();

/* ── Article file drop ───────────────────────── */
var fi=document.getElementById('article_file');
var fDrop=document.getElementById('eiu-file-drop');
var fInner=document.getElementById('eiu-file-inner');
var fChosn=document.getElementById('eiu-file-chosen');
var fName=document.getElementById('eiu-file-name');
var fClear=document.getElementById('eiu-clear-file');
var MAX_MB=5;

function showFile(name){fName.textContent=name;fInner.classList.add('d-none');fChosn.classList.remove('d-none');}
function clearFile(){if(fi)fi.value='';fChosn.classList.add('d-none');fInner.classList.remove('d-none');}
function validateFile(file){
  var ext=file.name.split('.').pop().toLowerCase();
  if(!['pdf','ppt','pptx'].includes(ext)){setErr('article_file','<?php echo esc_js(__('Invalid file type. Accepted: PDF, PPT, PPTX.','eiu-rp')); ?>');return false;}
  if(file.size>MAX_MB*1024*1024){setErr('article_file','<?php echo esc_js(__('File exceeds 5 MB. Please compress it first.','eiu-rp')); ?>');return false;}
  clearErr('article_file');return true;
}
if(fi)fi.addEventListener('change',function(){if(this.files[0]&&validateFile(this.files[0]))showFile(this.files[0].name);else clearFile();});
if(fClear)fClear.addEventListener('click',function(e){e.preventDefault();clearFile();clearErr('article_file');});
if(fDrop){
  fDrop.addEventListener('dragover',function(e){e.preventDefault();this.classList.add('drag-over');});
  fDrop.addEventListener('dragleave',function(){this.classList.remove('drag-over');});
  fDrop.addEventListener('drop',function(e){
    e.preventDefault();this.classList.remove('drag-over');
    var f=e.dataTransfer&&e.dataTransfer.files[0];
    if(f){var dt=new DataTransfer();dt.items.add(f);fi.files=dt.files;if(validateFile(f))showFile(f.name);}
  });
}

/* ── Validation helpers ──────────────────────── */
function setErr(id,msg){var el=document.getElementById('err-'+id);if(el){el.textContent=msg;el.style.display='block';}var inp=document.querySelector('[name="'+id+'"]');if(inp)inp.classList.add('is-invalid');}
function clearErr(id){var el=document.getElementById('err-'+id);if(el){el.textContent='';el.style.display='none';}var inp=document.querySelector('[name="'+id+'"]');if(inp)inp.classList.remove('is-invalid');}
function clearAllErrors(){document.querySelectorAll('.eiu-err').forEach(function(e){e.textContent='';e.style.display='none';});document.querySelectorAll('.is-invalid').forEach(function(e){e.classList.remove('is-invalid');});document.getElementById('eiu-sf-success').classList.add('d-none');document.getElementById('eiu-sf-error').classList.add('d-none');}
/* abstractEditorId and referencesEditorId are declared in the TinyMCE block below.
   They are hoisted to window scope so the wizard validation code can access them. */
var abstractEditorId   = <?php echo wp_json_encode($abstract_editor_id); ?>;
var referencesEditorId = <?php echo wp_json_encode($references_editor_id); ?>;

/* getEditorContent: read content from a TinyMCE 5 editor by its element ID,
   with textarea fallback. Returns plain text for non-empty validation.
   For abstract specifically, we use abstractEditorId directly. */
function getEditorContent(editorId){
  if(typeof Quill!=='undefined'){
    if(editorId===abstractEditorId && window.eiu_abstract_quill) return window.eiu_abstract_quill.getText().trim();
    if(editorId===referencesEditorId && window.eiu_references_quill) return window.eiu_references_quill.getText().trim();
  }
  var ta=document.getElementById(editorId);
  if(ta && ta.value) return ta.value.replace(/<[^>]+>/g,'').trim();
  return '';
}

/* ── Form submit ─────────────────────────────── */
var form=document.getElementById('eiu-rp-submission-form');
var btn=document.getElementById('eiu-submit-btn');
var btnText=document.getElementById('eiu-btn-text');
var btnSpin=document.getElementById('eiu-btn-spin');
var sucEl=document.getElementById('eiu-sf-success');
var errEl=document.getElementById('eiu-sf-error');
var ajaxUrl=typeof eiuRP!=='undefined'?eiuRP.ajaxUrl:'<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
var nonce=typeof eiuRP!=='undefined'?eiuRP.nonce:'<?php echo esc_js($nonce); ?>';

if(form)form.addEventListener('submit',function(e){
  e.preventDefault();
  // Block submit if an image upload AJAX is still in flight
  if(pendingUploads>0){
    errEl.innerHTML='<i class="bi bi-hourglass-split me-2"></i><?php echo esc_js(__('Please wait — image upload in progress…','eiu-rp')); ?>';
    errEl.classList.remove('d-none');
    return;
  }
  clearAllErrors();
  /* Sync Quill to hidden textareas before FormData reads them */
  if(window.eiu_abstract_quill){ document.getElementById(abstractEditorId).value=window.eiu_abstract_quill.root.innerHTML; }
  if(window.eiu_references_quill){ document.getElementById(referencesEditorId).value=window.eiu_references_quill.root.innerHTML; }
  var fd=new FormData(this);
  fd.set('nonce',nonce);
  var required={
    article_title:'<?php echo esc_js(__('Article title is required.','eiu-rp')); ?>',
    subject:      '<?php echo esc_js(__('Please select a subject.','eiu-rp')); ?>',
    author_name:  '<?php echo esc_js(__('Author name is required.','eiu-rp')); ?>',
    author_email: '<?php echo esc_js(__('Author email is required.','eiu-rp')); ?>',
  };
  var hasErr=false;
  Object.keys(required).forEach(function(k){if(!fd.get(k)||!fd.get(k).toString().trim()){setErr(k,required[k]);hasErr=true;}});
  var abst=getEditorContent(abstractEditorId);
  if(!abst){setErr('abstract','<?php echo esc_js(__('Abstract is required.','eiu-rp')); ?>');hasErr=true;}
  var email=fd.get('author_email');
  if(email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){setErr('author_email','<?php echo esc_js(__('Valid email required.','eiu-rp')); ?>');hasErr=true;}
  if(!fi||!fi.files.length){setErr('article_file','<?php echo esc_js(__('Please upload your article file.','eiu-rp')); ?>');hasErr=true;}
  if(hasErr){
    errEl.innerHTML='<i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo esc_js(__('Please correct the highlighted fields.','eiu-rp')); ?>';
    errEl.classList.remove('d-none');
    var firstBad=form.querySelector('.is-invalid');
    if(firstBad)firstBad.closest('.eiu-sf-section').scrollIntoView({behavior:'smooth',block:'start'});
    return;
  }
  btn.disabled=true;btnText.classList.add('d-none');btnSpin.classList.remove('d-none');
  fetch(ajaxUrl,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(res){
      if(res.success){
        sucEl.innerHTML='<i class="bi bi-check-circle-fill me-2"></i>'+res.data.message;
        sucEl.classList.remove('d-none');
        form.reset();
        if(window.eiu_abstract_quill)    window.eiu_abstract_quill.setContents([]);
        if(window.eiu_references_quill)  window.eiu_references_quill.setContents([]);
        if(window.eiu_affiliation_quill) window.eiu_affiliation_quill.setContents([]);
        clearFile();clearThumb();
        // Guard all DOM lookups — elements may not exist if the co-author
        // section was rebuilt by a later update (e.g. multi-co-author UI).
        var _api=document.getElementById('eiu-author-photo-img');   if(_api) _api.style.display='none';
        var _aii=document.getElementById('eiu-author-initial');     if(_aii) _aii.style.display='';
        var _cpi=document.getElementById('eiu-coauthor-photo-img'); if(_cpi) _cpi.style.display='none';
        var _cii=document.getElementById('eiu-coauthor-initial');   if(_cii) _cii.style.display='';
        sucEl.scrollIntoView({behavior:'smooth',block:'start'});
      } else {
        var msg=(res.data&&res.data.message)?res.data.message:'<?php echo esc_js(__('An error occurred.','eiu-rp')); ?>';
        errEl.innerHTML='<i class="bi bi-exclamation-triangle-fill me-2"></i>'+msg;
        errEl.classList.remove('d-none');
        if(res.data&&res.data.fields)res.data.fields.forEach(function(f){setErr(f,'<?php echo esc_js(__('This field is required.','eiu-rp')); ?>');});
      }
    })
    .catch(function(){
      errEl.innerHTML='<i class="bi bi-wifi-off me-2"></i><?php echo esc_js(__('Network error. Please try again.','eiu-rp')); ?>';
      errEl.classList.remove('d-none');
    })
    .finally(function(){
      btn.disabled=false;btnSpin.classList.add('d-none');btnText.classList.remove('d-none');
    });
});

/* ── Disclosure radio show/hide detail panels ── */
(function(){
  function toggleDetail(radioName, showValue, panelId){
    var radios = document.querySelectorAll('input[name="' + radioName + '"]');
    var panel  = document.getElementById(panelId);
    if (!panel || !radios.length) return;
    radios.forEach(function(r){
      r.addEventListener('change', function(){
        panel.style.display = (r.value === showValue && r.checked) ? 'block' : 'none';
      });
    });
  }
  toggleDetail('acknowledgements', 'yes', 'eiu-ack-detail');
  toggleDetail('ip_patents', 'yes', 'eiu-patent-detail');
}());

/* ── Save & Continue Later ─────────────────── */
(function(){
  var saveBtn = document.getElementById('eiu-save-draft-btn');
  if (!saveBtn) return;

  var draftMsg = document.getElementById('eiu-draft-msg');
  var ajaxUrl  = typeof eiuRP !== 'undefined' ? eiuRP.ajaxUrl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  var nonce    = typeof eiuRP !== 'undefined' ? eiuRP.nonce   : '<?php echo esc_js(wp_create_nonce('eiu_rp_frontend')); ?>';

  /* Pending draft content for Quill editors — loaded before editors exist */
  window.eiu_pending_draft = null;

  function collectFormData(){
    /* FIX: correct form ID is 'eiu-rp-submission-form' */
    var form = document.getElementById('eiu-rp-submission-form');
    if (!form) return {};
    var data = {};
    var inputs = form.querySelectorAll('input:not([type="file"]),select,textarea');
    inputs.forEach(function(el){
      if (!el.name) return;
      if (el.type === 'radio' || el.type === 'checkbox'){
        if (el.checked) data[el.name] = el.value;
      } else {
        data[el.name] = el.value;
      }
    });
    /* Capture Quill editor HTML content */
    if (window.eiu_abstract_quill)     data[abstractEditorId]   = window.eiu_abstract_quill.root.innerHTML;
    if (window.eiu_references_quill)   data[referencesEditorId] = window.eiu_references_quill.root.innerHTML;
    if (window.eiu_affiliation_quill)  data['eiu-affiliation-ta'] = window.eiu_affiliation_quill.root.innerHTML;
    return data;
  }

  saveBtn.addEventListener('click', function(){
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    var draftJson = JSON.stringify(collectFormData());
    var fd = new FormData();
    fd.append('action',     'eiu_rp_save_draft_submission');
    fd.append('nonce',      nonce);
    fd.append('draft_data', draftJson);

    fetch(ajaxUrl, { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        draftMsg.style.display  = 'block';
        draftMsg.style.color    = res.success ? '#166534' : '#991b1b';
        draftMsg.textContent    = (res.data && res.data.message)
          ? res.data.message
          : '<?php echo esc_js(__('Draft saved.','eiu-rp')); ?>';
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-floppy"></i><?php echo esc_js(__('Save &amp; Continue Later','eiu-rp')); ?>';
        setTimeout(function(){ draftMsg.style.display = 'none'; }, 4000);
      })
      .catch(function(){
        draftMsg.style.display = 'block';
        draftMsg.style.color   = '#991b1b';
        draftMsg.textContent   = '<?php echo esc_js(__('Network error.','eiu-rp')); ?>';
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-floppy"></i><?php echo esc_js(__('Save &amp; Continue Later','eiu-rp')); ?>';
      });
  });

  /* ── Auto-load draft on page load ─────────────────────────────────────
     FIX 1: Correct form ID ('eiu-rp-submission-form').
     FIX 2: Remove stale tinymce.get() call — TinyMCE is gone.
     FIX 3: Quill editors don't exist yet at page-load time (they are
             lazy-initialised when the user navigates to step 2 or 8).
             Store the draft abstract/references HTML in window.eiu_pending_draft
             so eiu_initQuill() can apply them as soon as the editor exists.
  ────────────────────────────────────────────────────────────────────── */
  (function(){
    var fd = new FormData();
    fd.append('action', 'eiu_rp_load_draft_submission');
    fd.append('nonce',  nonce);
    fetch(ajaxUrl, { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (!res.success || !res.data || !res.data.draft_data) return;
        var draft;
        try { draft = JSON.parse(res.data.draft_data); } catch(e){ return; }

        /* FIX: correct form ID */
        var form = document.getElementById('eiu-rp-submission-form');
        if (!form || !draft) return;

        /* Stash Quill content — applied later when editors initialise */
        if (draft[abstractEditorId])   {
          window.eiu_pending_draft = window.eiu_pending_draft || {};
          window.eiu_pending_draft.abstract = draft[abstractEditorId];
        }
        if (draft[referencesEditorId]) {
          window.eiu_pending_draft = window.eiu_pending_draft || {};
          window.eiu_pending_draft.references = draft[referencesEditorId];
        }

        /* Apply all regular form fields immediately */
        Object.keys(draft).forEach(function(name){
          /* Skip Quill editor fields — handled above */
          if (name === abstractEditorId || name === referencesEditorId) return;

          var el = form.querySelector('[name="' + name + '"]');
          if (!el) return; /* FIX: removed stale tinymce.get() fallback */

          if (el.type === 'radio'){
            var radEl = form.querySelector('[name="' + name + '"][value="' + draft[name] + '"]');
            if (radEl){ radEl.checked = true; radEl.dispatchEvent(new Event('change')); }
          } else if (el.type === 'checkbox'){
            el.checked = (el.value === draft[name]);
          } else {
            el.value = draft[name];
          }
        });
      })
      .catch(function(){});
  }());
}());

}());

</script>

<!-- ═══════════════════════════════════════════════════════════════════════
  Quill.js Rich Text Editor — Abstract and References
  Loaded from jsdelivr (same CDN as Bootstrap, confirmed working on this server).
  Zero WordPress dependency. No iframes. Works in any theme or shortcode.
  ═════════════════════════════════════════════════════════════════════════ -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
<style>
/* ── Quill editor custom styling ──────────────────────────────────── */
.eiu-quill-wrap { border:1.5px solid var(--cb,#dee2e6); border-radius:8px; overflow:hidden; background:#fff; }
.eiu-quill-wrap .ql-toolbar { background:#f8f9fa; border:none; border-bottom:1px solid #dee2e6; flex-wrap:wrap; }
.eiu-quill-wrap .ql-container { border:none; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; font-size:15px; }
.eiu-quill-wrap .ql-editor { min-height:260px; line-height:1.8; padding:14px 16px; color:#212529; }
.eiu-quill-wrap .ql-editor.ql-blank::before { color:#9ca3af; font-style:normal; }
#eiu-references-quill-wrap .ql-editor { min-height:130px; }
/* Responsive toolbar */
@media(max-width:600px){
  .eiu-quill-wrap .ql-toolbar .ql-formats { margin-bottom:4px; }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
(function(){
'use strict';

/* Toolbar config — bold, italic, underline, strike, headings, lists,
   alignment, link, image, blockquote, code, clean */
var EIU_QUILL_TOOLBAR_FULL = [
  [{ 'header': [1, 2, 3, 4, false] }, { 'font': [] }, { 'size': ['small', false, 'large'] }],
  ['bold', 'italic', 'underline', 'strike'],
  [{ 'color': [] }, { 'background': [] }],
  [{ 'list': 'ordered' }, { 'list': 'bullet' }],
  [{ 'indent': '-1' }, { 'indent': '+1' }],
  [{ 'align': [] }],
  ['blockquote', 'code-block'],
  ['link', 'image'],
  ['clean']
];

var EIU_QUILL_TOOLBAR_LITE = [
  ['bold', 'italic', 'underline'],
  [{ 'list': 'ordered' }, { 'list': 'bullet' }],
  ['link'],
  ['clean']
];

/* ── Image upload handler — uses existing EIU AJAX endpoint ────────── */
var eiu_q_ajax  = (typeof eiuRP !== 'undefined') ? eiuRP.ajaxUrl : '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
var eiu_q_nonce = '<?php echo esc_js(wp_create_nonce("eiu_rp_frontend")); ?>';

function eiu_quill_img_handler(quillInstance) {
  var input = document.createElement('input');
  input.setAttribute('type', 'file');
  input.setAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
  input.click();
  input.onchange = function() {
    var file = input.files[0];
    if (!file) return;
    var fd = new FormData();
    fd.append('action', 'eiu_rp_upload_media_image');
    fd.append('nonce',  eiu_q_nonce);
    fd.append('image',  file);
    fetch(eiu_q_ajax, { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.success && res.data && res.data.url) {
          var range = quillInstance.getSelection(true);
          quillInstance.insertEmbed(range.index, 'image', res.data.url, 'user');
        }
      })
      .catch(function(){});
  };
}

/* ── Sync Quill HTML to hidden textarea ────────────────────────────── */
function eiu_sync_quill(quillInstance, textareaId) {
  var ta = document.getElementById(textareaId);
  if (ta) ta.value = quillInstance.root.innerHTML;
}

/* ── Create a Quill instance ───────────────────────────────────────── */
function eiu_make_quill(containerId, textareaId, toolbar, placeholder, initialContent) {
  var container = document.getElementById(containerId);
  if (!container) return null;

  var q = new Quill(container, {
    theme:       'snow',
    placeholder: placeholder || '',
    modules: {
      toolbar: {
        container: toolbar,
        handlers: {
          image: function() { eiu_quill_img_handler(q); }
        }
      }
    }
  });

  /* Pre-fill with existing content (resubmit / draft reload) */
  if (initialContent) {
    q.root.innerHTML = initialContent;
    eiu_sync_quill(q, textareaId);
  }

  /* Sync on every change */
  q.on('text-change', function() {
    eiu_sync_quill(q, textareaId);
  });

  return q;
}

/* ══════════════════════════════════════════════════════════════════════
   Lazy init — called by updateWizardUI() when a step becomes visible.
   Step 2 = Abstract, Step 8 = References.
   ═════════════════════════════════════════════════════════════════════ */
window.eiu_initQuill = function(step) {
  if (typeof Quill === 'undefined') return;

  if (step === 2 && !window.eiu_abstract_quill) {
    window.eiu_abstract_quill = eiu_make_quill(
      'eiu-abstract-quill',
      <?php echo wp_json_encode($abstract_editor_id); ?>,
      EIU_QUILL_TOOLBAR_FULL,
      '<?php echo esc_js(__("Write your abstract here… (bold, headings, lists, images all supported)","eiu-rp")); ?>'
    );
    /* Apply pending draft content saved before this editor existed */
    if (window.eiu_pending_draft && window.eiu_pending_draft.abstract && window.eiu_abstract_quill) {
      window.eiu_abstract_quill.root.innerHTML = window.eiu_pending_draft.abstract;
      /* Sync to hidden textarea */
      var ta = document.getElementById(<?php echo wp_json_encode($abstract_editor_id); ?>);
      if (ta) ta.value = window.eiu_pending_draft.abstract;
      window.eiu_pending_draft.abstract = null;
    }
  }

  // v2.2: Affiliation editor — step 4 = Author Details
  if (step === 4 && !window.eiu_affiliation_quill) {
    window.eiu_affiliation_quill = eiu_make_quill(
      'eiu-affiliation-quill',
      'eiu-affiliation-ta',
      EIU_QUILL_TOOLBAR_LITE,
      '<?php echo esc_js(__("e.g. Department of Computer Science, EIU, Bangkok","eiu-rp")); ?>'
    );
  }

  if (step === 8 && !window.eiu_references_quill) {
    window.eiu_references_quill = eiu_make_quill(
      'eiu-references-quill',
      <?php echo wp_json_encode($references_editor_id); ?>,
      EIU_QUILL_TOOLBAR_LITE,
      '<?php echo esc_js(__("List your references here…","eiu-rp")); ?>'
    );
    /* Apply pending draft content */
    if (window.eiu_pending_draft && window.eiu_pending_draft.references && window.eiu_references_quill) {
      window.eiu_references_quill.root.innerHTML = window.eiu_pending_draft.references;
      var ta2 = document.getElementById(<?php echo wp_json_encode($references_editor_id); ?>);
      if (ta2) ta2.value = window.eiu_pending_draft.references;
      window.eiu_pending_draft.references = null;
    }
  }
};

/* Init immediately if step 2 is already active (edge case) */
document.addEventListener('DOMContentLoaded', function(){
  var absSection = document.querySelector('.eiu-sf-section[data-step="2"]');
  if (absSection && absSection.classList.contains('is-active-step')) {
    window.eiu_initQuill(2);
  }
});

}());
</script>
