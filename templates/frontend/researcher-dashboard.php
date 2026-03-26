<?php
/**
 * Frontend: Researcher Dashboard — v2.0 "Crystalline"
 *
 * Tabs: Overview | My Submissions | Submit Article | Profile
 * - Submit Article is inline (no redirect)
 * - Modern Crystalline design: IBM Plex Sans + Syne
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Utils\Terminology;

wp_enqueue_style( 'bootstrap-icons-eiu', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', array(), '1.11.3' );

// Auth guard — redirect to unified login if not authenticated
if ( ! is_user_logged_in() ) {
    $unified_id  = get_option( 'eiu_rp_unified_login_page_id' );
    $login_url   = $unified_id ? get_permalink( $unified_id ) : home_url( '/login/' );
    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $login_url   = add_query_arg( 'redirect_to', rawurlencode( $current_url ), $login_url );
    wp_safe_redirect( esc_url_raw( $login_url ) );
    exit;
}

$current_user = wp_get_current_user();

// Cross-role access protection: reviewers trying to access researcher dashboard
// get redirected to their own dashboard instead.
if ( in_array( 'eiu_reviewer', (array) $current_user->roles, true )
    && ! in_array( 'eiu_researcher', (array) $current_user->roles, true )
    && ! current_user_can( 'manage_options' ) ) {
    $reviewer_page_id  = get_option( 'eiu_rp_reviewer_access_page_id' );
    $reviewer_dashboard = $reviewer_page_id ? get_permalink( $reviewer_page_id ) : home_url( '/reviewer-dashboard/' );
    wp_safe_redirect( esc_url_raw( $reviewer_dashboard ) );
    exit;
}

if ( ! \EIU_RP\Roles\Researcher_Role::can_submit( $current_user ) ) {
    echo '<div style="max-width:500px;margin:40px auto;text-align:center;font-family:\'DM Sans\',sans-serif;">';
    echo '<i class="bi bi-lock" style="font-size:3rem;color:#9ca3af;display:block;margin-bottom:12px;"></i>';
    echo '<h2 style="font-size:18px;font-weight:700;margin:0 0 8px;">' . esc_html__( 'Access Restricted', 'eiu-rp' ) . '</h2>';
    echo '<p style="color:#6b7280;font-size:14px;">' . esc_html__( 'Your account does not have access to the researcher dashboard.', 'eiu-rp' ) . '</p>';
    echo '</div>';
    return;
}

$user_id      = get_current_user_id();
$display_name = $current_user->display_name ?: $current_user->user_login;
$user_email   = $current_user->user_email;
$rsd_profile_photo_id  = (int) get_user_meta( $user_id, 'eiu_profile_photo_id', true );
$rsd_profile_photo_url = $rsd_profile_photo_id
    ? wp_get_attachment_image_url( $rsd_profile_photo_id, 'thumbnail' )
    : '';
$first_name   = get_user_meta( $user_id, 'first_name', true ) ?: explode(' ', $display_name)[0];
$last_name    = get_user_meta( $user_id, 'last_name',  true );
$phone        = get_user_meta( $user_id, 'eiu_phone',       true );
$country      = get_user_meta( $user_id, 'eiu_country',     true );
$nationality  = get_user_meta( $user_id, 'eiu_nationality', true );
$expertise    = get_user_meta( $user_id, 'eiu_expertise',   true );

// My submissions
global $wpdb;
// Match by user_id (new submissions) OR by email (legacy/pre-2.3 submissions).
// SHOW COLUMNS guard ensures query works before migration runs.
$_uid_col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}eiu_articles LIKE 'author_user_id'" ); // phpcs:ignore
if ( ! empty( $_uid_col ) && $user_id ) {
    $my_articles = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.*, p.post_title as title
         FROM {$wpdb->prefix}eiu_articles a
         LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
         WHERE ( a.author_user_id = %d AND a.author_user_id != 0 )
            OR ( a.author_email = %s )
         ORDER BY a.submitted_at DESC",
        $user_id, $user_email
    ), ARRAY_A );
} else {
    $my_articles = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.*, p.post_title as title
         FROM {$wpdb->prefix}eiu_articles a
         LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
         WHERE a.author_email = %s
         ORDER BY a.submitted_at DESC",
        $user_email
    ), ARRAY_A );
}

$page_url     = get_permalink();
$tab          = sanitize_key( $_GET['tab'] ?? 'overview' );
if ( ! in_array( $tab, ['overview','submissions','submit','resubmit','profile'], true ) ) { $tab = 'overview'; }
$logout_url    = wp_logout_url( $page_url );
$profile_nonce = wp_create_nonce( 'eiu_researcher_profile' );
$ajax_url      = admin_url( 'admin-ajax.php' );
$resubmit_nonce = wp_create_nonce( 'eiu_rp_frontend' );

// For the resubmit tab: which article is being revised?
$resubmit_article_id = absint( $_GET['article_id'] ?? 0 );
$resubmit_article    = null;
if ( $tab === 'resubmit' && $resubmit_article_id ) {
    $resubmit_article = \EIU_RP\Models\Article::get( $resubmit_article_id );
    // Security: must be the article author
    if ( $resubmit_article && strtolower( $resubmit_article->author_email ) !== strtolower( $user_email ) ) {
        $resubmit_article = null;
    }
    // Feature 5: Lock editing on published articles — researchers cannot edit
    // once published. They may only edit when status is revision_required.
    $editable_statuses = array( 'revision_required' );
    if ( $resubmit_article && ! in_array( $resubmit_article->status, $editable_statuses, true ) ) {
        $resubmit_article_locked = $resubmit_article; // keep for locked message display
        $resubmit_article        = null;
    }
}

// Count articles currently awaiting revision by this researcher
$revision_count = count( array_filter( $my_articles, fn($a) => ($a['status']??'') === 'revision_required' ) );

// Status config
$status_cfg = array(
    'pending'           => ['Pending Review',    'st-pending'],
    'under_review'      => ['Under Review',      'st-review'],
    'approved'          => ['Approved',           'st-approved'],
    'published'         => ['Published',          'st-published'],
    'rejected'          => ['Rejected',           'st-rejected'],
    'revision_required' => ['Revision Required',  'st-revision'],
);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Cabinet+Grotesk:wght@700;800;900&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
.eiu-quill-wrap{border:1.5px solid #e5e7eb;border-radius:8px;overflow:hidden;background:#fff;}
.eiu-quill-wrap .ql-toolbar{background:#f8f9fa;border:none;border-bottom:1px solid #e5e7eb;}
.eiu-quill-wrap .ql-container{border:none;font-size:15px;}
.eiu-quill-wrap .ql-editor{line-height:1.8;padding:14px 16px;color:#212529;}
.eiu-quill-wrap .ql-editor.ql-blank::before{color:#9ca3af;font-style:normal;}
</style>
<style>
/* ══════════════════════════════════════════════════════════════
   EIU Researcher Dashboard v3.0 "Altitude"
   Design: DM Sans body · Cabinet Grotesk display
   Scoped to #eiu-rsd — zero global leaks
══════════════════════════════════════════════════════════════ */

#eiu-rsd *, #eiu-rsd *::before, #eiu-rsd *::after { box-sizing: border-box; }

#eiu-rsd {
  --font-display: 'Cabinet Grotesk', sans-serif;
  --font-body:    'DM Sans', sans-serif;

  --navy:         #0a1628;
  --navy-mid:     #112040;
  --blue:         #1a4988;
  --blue-bright:  #2563eb;
  --coral:        #990000;  /* EIU brand red */
  --coral-light:  #fef2f2;
  --amber:        #f59e0b;
  --amber-light:  #fffbeb;
  --emerald:      #059669;
  --emerald-light:#ecfdf5;
  --violet:       #7c3aed;
  --violet-light: #f5f3ff;

  --bg:           #f7f8fc;
  --surface:      #ffffff;
  --surface-2:    #f0f3f9;
  --border:       #e2e8f0;
  --border-focus: #2563eb;

  --text-primary:   #0f172a;
  --text-secondary: #475569;
  --text-muted:     #94a3b8;

  /* EIU Brand sidebar — light, clean, institutional */
  --sidebar-bg:         #ffffff;
  --sidebar-border:     #e8eef6;
  --sidebar-text:       #4a5568;
  --sidebar-text-muted: #94a3b8;
  --sidebar-active-bg:  #eef4ff;
  --sidebar-active-txt: #1a4988;
  --sidebar-hover-bg:   #f5f8ff;
  --sidebar-accent:     #990000;
  --sidebar-blue:       #1a4988;
  --sidebar-accent: #f59e0b;

  --shadow-xs:  0 1px 2px rgba(0,0,0,0.06);
  --shadow-sm:  0 2px 8px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.05);
  --shadow-md:  0 4px 20px rgba(0,0,0,0.09), 0 2px 8px rgba(0,0,0,0.06);
  --shadow-lg:  0 16px 48px rgba(0,0,0,0.12), 0 4px 16px rgba(0,0,0,0.08);

  --radius-sm:  8px;
  --radius-md:  12px;
  --radius-lg:  16px;
  --radius-xl:  20px;

  font-family: var(--font-body);
  font-size: 15px;
  line-height: 1.6;
  color: var(--text-primary);

  display: flex;
  min-height: 800px;
  background: var(--bg);
  border-radius: var(--radius-xl);
  overflow: hidden;
  box-shadow: var(--shadow-lg);
  margin: 0 0 40px;
}

/* ── SIDEBAR — Light EIU Brand Theme ─────────────────────── */
#eiu-rsd .rsd-sb {
  width: 280px; flex-shrink: 0;
  background: var(--sidebar-bg);
  display: flex; flex-direction: column;
  position: relative; z-index: 10;
  border-right: 1px solid var(--sidebar-border);
  box-shadow: 2px 0 12px rgba(26,73,136,0.06);
  overflow: hidden;
}
#eiu-rsd .rsd-sb::before { display: none; }

/* Brand strip — EIU blue */
#eiu-rsd .rsd-brand {
  padding: 0;
  border-bottom: 1px solid var(--sidebar-border);
}
#eiu-rsd .rsd-brand > div {
  background: var(--sidebar-blue);
  padding: 22px 24px;
  display: flex; align-items: center; gap: 13px;
}
#eiu-rsd .rsd-brand-icon {
  width: 40px; height: 40px; border-radius: var(--radius-sm); flex-shrink: 0;
  background: rgba(255,255,255,0.15);
  border: 1.5px solid rgba(255,255,255,0.25);
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s;
}
#eiu-rsd .rsd-brand-icon:hover { background: rgba(255,255,255,0.22); }
#eiu-rsd .rsd-brand-name {
  font-family: var(--font-display); font-size: 15px; font-weight: 800;
  color: #fff; line-height: 1.2; letter-spacing: -0.01em;
}
#eiu-rsd .rsd-brand-role {
  font-size: 11px; font-weight: 500; color: rgba(255,255,255,0.6); margin-top: 2px;
}

/* User chip */
#eiu-rsd .rsd-user {
  padding: 16px 20px;
  border-bottom: 1px solid var(--sidebar-border);
  display: flex; align-items: center; gap: 12px;
  background: #fafbff;
}
#eiu-rsd .rsd-av {
  width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
  background: var(--sidebar-blue);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-size: 17px; font-weight: 800; color: #fff;
  border: 2px solid #c8d9f0;
  box-shadow: 0 2px 8px rgba(26,73,136,0.18);
}
#eiu-rsd .rsd-uname {
  color: #1a2535; font-size: 14px; font-weight: 700; margin: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;
}
#eiu-rsd .rsd-uemail {
  color: #64748b; font-size: 11px; margin: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;
}

/* Nav */
#eiu-rsd .rsd-nav { flex: 1; padding: 10px 0; overflow-y: auto; scrollbar-width: none; }
#eiu-rsd .rsd-nav::-webkit-scrollbar { display: none; }
#eiu-rsd .rsd-nav-sec {
  padding: 14px 20px 5px; font-size: 9px; font-weight: 800;
  letter-spacing: 0.1em; text-transform: uppercase; color: var(--sidebar-text-muted, #94a3b8);
}
#eiu-rsd .rsd-nav a {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 20px; font-size: 14px; font-weight: 500;
  color: var(--sidebar-text); text-decoration: none;
  border-left: 3px solid transparent;
  transition: all 0.15s ease; margin: 1px 0;
}
#eiu-rsd .rsd-nav a:hover { color: var(--sidebar-blue); background: var(--sidebar-hover-bg); }
#eiu-rsd .rsd-nav a.active {
  color: var(--sidebar-active-txt); background: var(--sidebar-active-bg);
  border-left-color: var(--sidebar-blue); font-weight: 700;
}
#eiu-rsd .rsd-nav a i { font-size: 16px; flex-shrink: 0; width: 20px; text-align: center; color: #94a3b8; transition: color 0.15s; }
#eiu-rsd .rsd-nav a:hover i, #eiu-rsd .rsd-nav a.active i { color: var(--sidebar-blue); }
#eiu-rsd .rsd-badge {
  margin-left: auto; background: var(--sidebar-accent); color: #fff;
  border-radius: 20px; padding: 2px 8px; font-size: 10px; font-weight: 800; line-height: 1.6;
}

/* Submit highlight — red accent */
#eiu-rsd .rsd-nav a.rsd-submit-lnk {
  margin: 8px 14px; border-radius: var(--radius-sm); border-left: none; padding: 10px 16px;
  background: #fff5f5; border: 1.5px solid #fecaca;
  color: var(--sidebar-accent); font-weight: 700;
}
#eiu-rsd .rsd-nav a.rsd-submit-lnk:hover { background: #fef2f2; border-color: #fca5a5; color: #7f1d1d; }
#eiu-rsd .rsd-nav a.rsd-submit-lnk.active { background: #fee2e2; border-color: var(--sidebar-accent); color: #7f1d1d; }
#eiu-rsd .rsd-nav a.rsd-submit-lnk i { color: var(--sidebar-accent); }

/* Footer — dark, high-contrast sign-out */
#eiu-rsd .rsd-footer {
  padding: 0;
  border-top: 2px solid rgba(0,0,0,0.15);
  background: #0a1628;
  flex-shrink: 0;
}
#eiu-rsd .rsd-footer a {
  display: flex; align-items: center; gap: 10px;
  padding: 16px 22px;
  font-size: 13px; font-weight: 700;
  color: #f1f5f9; text-decoration: none;
  transition: background 0.18s ease, color 0.18s ease;
  letter-spacing: 0.02em;
}
#eiu-rsd .rsd-footer a:hover { background: #990000; color: #ffffff; }
#eiu-rsd .rsd-footer a:active { background: #720000; color: #ffffff; }
#eiu-rsd .rsd-footer a i { font-size: 16px; opacity: 1; }

/* ── MAIN ─────────────────────────────────────────────────── */
#eiu-rsd .rsd-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

/* Topbar */
#eiu-rsd .rsd-topbar {
  background: var(--surface); padding: 20px 36px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 5;
}
#eiu-rsd .rsd-topbar-title {
  font-family: var(--font-display); font-size: 20px; font-weight: 800;
  color: var(--text-primary); margin: 0; letter-spacing: -0.02em;
}
#eiu-rsd .rsd-stat-pills { display: flex; gap: 6px; }
#eiu-rsd .rsd-stat-pill {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 18px; border-radius: var(--radius-sm);
  background: var(--surface-2); border: 1px solid var(--border); min-width: 72px;
}
#eiu-rsd .rsd-stat-num { font-family: var(--font-display); font-size: 22px; font-weight: 900; line-height: 1; }
#eiu-rsd .rsd-stat-lbl { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-top: 2px; }

/* Body */
#eiu-rsd .rsd-body { flex: 1; overflow-y: auto; padding: 36px 36px 48px; background: var(--bg); }

/* ── CARDS ──────────────────────────────────────────────── */
#eiu-rsd .rsd-card {
  background: var(--surface); border-radius: var(--radius-lg);
  border: 1px solid var(--border); box-shadow: var(--shadow-sm);
  margin-bottom: 20px; overflow: hidden; transition: box-shadow 0.2s;
}
#eiu-rsd .rsd-card:hover { box-shadow: var(--shadow-md); }
#eiu-rsd .rsd-card-head {
  padding: 20px 28px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  background: linear-gradient(to right, #fdfdff, var(--surface));
}
#eiu-rsd .rsd-card-title {
  font-family: var(--font-display); font-size: 15px; font-weight: 800;
  margin: 0; display: flex; align-items: center; gap: 10px; letter-spacing: -0.01em;
}
#eiu-rsd .rsd-card-body { padding: 28px; }
/* Rich text editor wrapper — full TinyMCE */
#eiu-rsd .rsd-editor-wrap { border: 1.5px solid var(--sidebar-border, #e8eef6); border-radius: 8px; overflow: hidden; }
#eiu-rsd .rsd-editor-wrap .wp-editor-container { border: none !important; }
#eiu-rsd .rsd-editor-wrap .mce-tinymce,
#eiu-rsd .rsd-editor-wrap .mce-container { border: none !important; box-shadow: none !important; }
@media(max-width:600px){
  #eiu-rsd .rsd-editor-wrap .mce-toolbar-grp { overflow-x: auto; }
  .mce-menu { max-width: 90vw !important; }
}

/* ── KPI GRID ───────────────────────────────────────────── */
#eiu-rsd .rsd-kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 18px; margin-bottom: 28px; }
#eiu-rsd .rsd-kpi {
  background: var(--surface); border-radius: var(--radius-md);
  border: 1px solid var(--border); padding: 24px 22px;
  display: flex; align-items: flex-start; gap: 16px;
  box-shadow: var(--shadow-xs); transition: all 0.2s ease; cursor: default;
}
#eiu-rsd .rsd-kpi:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
#eiu-rsd .rsd-kpi-icon { width: 50px; height: 50px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; flex-shrink: 0; }
#eiu-rsd .rsd-kpi-num { font-family: var(--font-display); font-size: 32px; font-weight: 900; line-height: 1; letter-spacing: -0.03em; }
#eiu-rsd .rsd-kpi-lbl { font-size: 12px; font-weight: 500; color: var(--text-muted); margin-top: 5px; }

/* ── ARTICLE ROWS ───────────────────────────────────────── */
#eiu-rsd .rsd-row {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md);
  padding: 20px 24px; margin-bottom: 12px;
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
  transition: all 0.18s ease;
}
#eiu-rsd .rsd-row:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); border-color: #dde4f0; }
#eiu-rsd .rsd-row-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; line-height: 1.45; }
#eiu-rsd .rsd-row-meta { font-size: 13px; color: var(--text-muted); }

/* ── STATUS PILLS ───────────────────────────────────────── */
#eiu-rsd .rsd-st { display: inline-flex; align-items: center; gap: 5px; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600; white-space: nowrap; letter-spacing: 0.01em; }
#eiu-rsd .st-pending  { background: var(--amber-light); color: #92400e; border: 1px solid #fde68a; }
#eiu-rsd .st-review   { background: var(--violet-light); color: #5b21b6; border: 1px solid #ddd6fe; }
#eiu-rsd .st-approved { background: var(--emerald-light); color: #065f46; border: 1px solid #a7f3d0; }
#eiu-rsd .st-published{ background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
#eiu-rsd .st-rejected { background: var(--coral-light); color: #9a0805; border: 1px solid #fecaca; }
#eiu-rsd .st-revision { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

/* ── BUTTONS ────────────────────────────────────────────── */
#eiu-rsd .rsd-btn {
  display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px;
  border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 13px; font-weight: 600;
  cursor: pointer; text-decoration: none; transition: all 0.16s; border: none; white-space: nowrap;
}
#eiu-rsd .rsd-btn-primary { background: var(--blue); color: #fff; box-shadow: 0 2px 8px rgba(26,73,136,0.28); }
#eiu-rsd .rsd-btn-primary:hover { background: #123266; color: #fff; text-decoration: none; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,73,136,0.38); }
#eiu-rsd .rsd-btn-accent { background: linear-gradient(135deg,#f59e0b,#d97706); color: #0a1628; font-weight: 700; box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
#eiu-rsd .rsd-btn-accent:hover { opacity: 0.92; color: #0a1628; text-decoration: none; transform: translateY(-1px); }
#eiu-rsd .rsd-btn-ghost { background: transparent; color: var(--blue); border: 1.5px solid var(--border); }
#eiu-rsd .rsd-btn-ghost:hover { background: var(--blue); color: #fff; border-color: var(--blue); }
#eiu-rsd .rsd-btn-sm { padding: 6px 14px; font-size: 12px; }

/* ── NOTICES ────────────────────────────────────────────── */
#eiu-rsd .rsd-notice { border-radius: var(--radius-sm); padding: 14px 18px; font-size: 14px; margin-bottom: 20px; display: none; font-weight: 500; }
#eiu-rsd .rsd-ok  { background: var(--emerald-light); color: #065f46; border: 1px solid #a7f3d0; }
#eiu-rsd .rsd-err { background: var(--coral-light); color: #9a0805; border: 1px solid #fecaca; }

/* ── FORM ───────────────────────────────────────────────── */
#eiu-rsd .form-control:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
#eiu-rsd .rsd-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block; }
#eiu-rsd .rsd-save-btn {
  background: var(--blue); color: #fff; border: none; border-radius: var(--radius-sm);
  padding: 11px 26px; font-family: var(--font-body); font-size: 14px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 7px;
  transition: all 0.16s; box-shadow: 0 2px 8px rgba(26,73,136,0.25);
}
#eiu-rsd .rsd-save-btn:hover { background: #123266; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,73,136,0.35); }
#eiu-rsd .rsd-save-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* ── EMPTY ──────────────────────────────────────────────── */
#eiu-rsd .rsd-empty {
  text-align: center; padding: 64px 24px;
  background: var(--surface); border-radius: var(--radius-lg);
  border: 2px dashed var(--border); color: var(--text-muted);
}
#eiu-rsd .rsd-empty i { font-size: 3.2rem; display: block; margin-bottom: 16px; opacity: 0.3; }

/* ── ANIMATION ──────────────────────────────────────────── */
@keyframes rsd-fadeup {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: none; }
}
#eiu-rsd .rsd-body > * { animation: rsd-fadeup 0.3s ease both; }
#eiu-rsd .rsd-body > *:nth-child(1) { animation-delay: 0.04s; }
#eiu-rsd .rsd-body > *:nth-child(2) { animation-delay: 0.08s; }
#eiu-rsd .rsd-body > *:nth-child(3) { animation-delay: 0.12s; }
#eiu-rsd .rsd-kpi { animation: rsd-fadeup 0.3s ease both; }
#eiu-rsd .rsd-kpi:nth-child(1) { animation-delay: 0.05s; }
#eiu-rsd .rsd-kpi:nth-child(2) { animation-delay: 0.10s; }
#eiu-rsd .rsd-kpi:nth-child(3) { animation-delay: 0.15s; }
#eiu-rsd .rsd-kpi:nth-child(4) { animation-delay: 0.20s; }

/* ── RESPONSIVE ─────────────────────────────────────────── */
@media(max-width: 1024px) {
  #eiu-rsd .rsd-kpi-grid { grid-template-columns: repeat(2, 1fr); }
  #eiu-rsd .rsd-sb { width: 240px; }
  #eiu-rsd .rsd-body { padding: 28px 28px 40px; }
}
@media(max-width: 768px) {
  #eiu-rsd { flex-direction: column; border-radius: var(--radius-md); min-height: auto; }
  #eiu-rsd .rsd-sb { width: 100%; overflow: visible; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
  #eiu-rsd .rsd-nav {
    display: flex; flex-direction: row; overflow-x: auto;
    padding: 6px 10px; flex: none; border-top: 1px solid var(--sidebar-border);
    scrollbar-width: none; gap: 4px;
  }
  #eiu-rsd .rsd-nav::-webkit-scrollbar { display: none; }
  #eiu-rsd .rsd-nav-sec { display: none; }
  #eiu-rsd .rsd-nav a {
    flex-direction: column; gap: 4px; padding: 8px 12px;
    border-left: none; border-bottom: 3px solid transparent;
    border-radius: var(--radius-sm); font-size: 11px; text-align: center;
    flex-shrink: 0; min-width: 60px;
  }
  #eiu-rsd .rsd-nav a i { width: auto; font-size: 17px; }
  #eiu-rsd .rsd-nav a.active { border-left: none; border-bottom-color: var(--sidebar-accent); }
  #eiu-rsd .rsd-nav a.rsd-submit-lnk { margin: 4px; padding: 8px 10px; border: none; }
  #eiu-rsd .rsd-sb::before { display: none; }
  #eiu-rsd .rsd-topbar { padding: 16px 20px; }
  #eiu-rsd .rsd-body { padding: 20px 16px 32px; }
  #eiu-rsd .rsd-card-body { padding: 20px; }
  #eiu-rsd .rsd-card-head { padding: 16px 20px; }
  #eiu-rsd .rsd-stat-grid { grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
  #eiu-rsd .rsd-kpi { padding: 18px 16px; gap: 12px; }
  #eiu-rsd .rsd-stat-pills { display: none; }
  #eiu-rsd .rsd-user { display: none; }
  #eiu-rsd .rsd-footer { display: block; }
  #eiu-rsd .rsd-footer a { padding: 13px 16px; font-size: 12px; }
}
@media(max-width: 480px) {
  #eiu-rsd .rsd-kpi-grid { grid-template-columns: 1fr 1fr; }
  #eiu-rsd .rsd-kpi-num { font-size: 26px; }
  #eiu-rsd .rsd-brand > div { padding: 16px 14px 14px; }
}

/* ── MOBILE FULL OPTIMIZATION ─────────────────────── */
@media(max-width: 768px) {
  /* Layout: stack sidebar on top */
  #eiu-rsd { flex-direction: column; border-radius: 0; min-height: 100vh; }
  #eiu-rsd .rsd-sb { width: 100%; position: sticky; top: 0; z-index: 100; }
  /* Nav: horizontal scrollable tabs */
  #eiu-rsd .rsd-nav {
    display: flex; flex-direction: row; overflow-x: auto; -webkit-overflow-scrolling: touch;
    padding: 8px 12px; gap: 6px; border-top: 1px solid var(--sidebar-border);
    scrollbar-width: none; flex-wrap: nowrap;
  }
  #eiu-rsd .rsd-nav::-webkit-scrollbar { display: none; }
  #eiu-rsd .rsd-nav a {
    flex-direction: column; gap: 3px; padding: 8px 14px; border-left: none;
    border-bottom: 3px solid transparent; border-radius: 8px;
    font-size: 11px; text-align: center; flex-shrink: 0; min-width: 64px;
    white-space: nowrap;
  }
  #eiu-rsd .rsd-nav a i { font-size: 18px; width: auto; }
  #eiu-rsd .rsd-nav a.active { border-left: none; border-bottom-color: var(--sidebar-accent); background: rgba(255,255,255,.07); }
  /* Body: full width, comfortable padding */
  #eiu-rsd .rsd-body { padding: 16px 14px 32px; }
  #eiu-rsd .rsd-card-body { padding: 16px; }
  #eiu-rsd .rsd-card-head { padding: 14px 16px; flex-wrap: wrap; gap: 8px; }
  /* KPI grid: 2 columns */
  #eiu-rsd .rsd-kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 18px; }
  #eiu-rsd .rsd-kpi { padding: 14px 12px; gap: 10px; }
  #eiu-rsd .rsd-kpi-icon { width: 40px; height: 40px; font-size: 18px; }
  #eiu-rsd .rsd-kpi-num { font-size: 24px; }
  /* Article rows: vertical stack */
  #eiu-rsd .rsd-row { flex-direction: column; gap: 10px; padding: 14px 16px; }
  #eiu-rsd .rsd-row > div:last-child { width: 100%; }
  /* Buttons: touch-friendly */
  #eiu-rsd .rsd-btn { min-height: 44px; padding: 10px 16px; }
  #eiu-rsd .rsd-save-btn { min-height: 44px; width: 100%; justify-content: center; }
  /* Forms: full-width inputs */
  #eiu-rsd .form-control, #eiu-rsd .form-select { font-size: 16px; } /* prevent iOS zoom */
  .row.g-3 { --bs-gutter-x: 0.75rem; }
  /* Hide non-essential sidebar elements */
  #eiu-rsd .rsd-user { display: none; }
  #eiu-rsd .rsd-stat-pills { display: none; }
  #eiu-rsd .rsd-nav-sec { display: none; }
  /* Resubmit form: stack columns */
  #eiu-rsd .rsd-card .row.g-3 > [class*='col-md-'] { flex: 0 0 100%; max-width: 100%; }
}
@media(max-width: 400px) {
  #eiu-rsd .rsd-kpi-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
  #eiu-rsd .rsd-kpi-num { font-size: 22px; }
  #eiu-rsd .rsd-kpi-lbl { font-size: 11px; }
  #eiu-rsd .rsd-kpi-icon { width: 36px; height: 36px; font-size: 16px; }
  #eiu-rsd .rsd-body { padding: 12px 10px 28px; }
  #eiu-rsd .rsd-card-body { padding: 12px; }
  #eiu-rsd .rsd-nav a { min-width: 56px; padding: 6px 10px; }
}
</style>

<?php
$totals = array_count_values( array_column( $my_articles, 'status' ) );
$total  = count( $my_articles );
?>
<div id="eiu-rsd">

  <!-- ── SIDEBAR ─────────────────────────────────────────── -->
  <aside class="rsd-sb">

    <!-- Brand -->
    <div class="rsd-brand">
      <div>
        <div class="rsd-brand-icon"><i class="bi bi-journal-richtext" style="color:#fff;font-size:18px;"></i></div>
        <div>
          <div class="rsd-brand-name"><?php echo esc_html( get_option('eiu_rp_term_system_name','EIU JOURNAL SYSTEM') ); ?></div>
          <div class="rsd-brand-role"><?php echo esc_html( get_option('eiu_rp_term_author_portal','Author Portal') ); ?></div>
        </div>
      </div>
    </div>

    <!-- User -->
    <div class="rsd-user">
      <div class="rsd-av" id="rsd-sidebar-av" style="<?php echo $rsd_profile_photo_url ? 'background:none;padding:0;overflow:hidden;' : ''; ?>">
        <?php if ( $rsd_profile_photo_url ): ?>
          <img src="<?php echo esc_url($rsd_profile_photo_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        <?php else: ?>
          <?php echo esc_html(strtoupper(substr($display_name,0,1))); ?>
        <?php endif; ?>
      </div>
      <div style="min-width:0;">
        <p class="rsd-uname"><?php echo esc_html($display_name); ?></p>
        <p class="rsd-uemail"><?php echo esc_html($user_email); ?></p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="rsd-nav">
      <div class="rsd-nav-sec"><?php esc_html_e('Main','eiu-rp'); ?></div>
      <?php
      $nav_items = [
        'overview'    => ['bi-speedometer2',       __('Overview','eiu-rp'),        0],
        'submissions' => ['bi-file-earmark-text',   __('My Submissions','eiu-rp'),  $total],
      ];
      foreach ($nav_items as $slug => [$icon, $label, $cnt]):
        $href   = add_query_arg('tab',$slug,$page_url);
        $active = $tab === $slug;
      ?>
        <a href="<?php echo esc_url($href); ?>" class="<?php echo $active ? 'active' : ''; ?>">
          <i class="bi <?php echo esc_attr($icon); ?>"></i>
          <?php echo esc_html($label); ?>
          <?php if ($cnt > 0): ?><span class="rsd-badge"><?php echo esc_html($cnt); ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php if ($revision_count > 0): ?>
        <a href="<?php echo esc_url(add_query_arg('tab','submissions',$page_url)); ?>"
          style="color:#c2410c;background:#fff7ed;border-left:3px solid #f97316;font-weight:700;margin:2px 0;">
          <i class="bi bi-pencil-square" style="color:#c2410c;"></i>
          <?php esc_html_e('Revision Required','eiu-rp'); ?>
          <span class="rsd-badge" style="background:#f97316;"><?php echo esc_html($revision_count); ?></span>
        </a>
      <?php endif; ?>

      <div class="rsd-nav-sec" style="margin-top:6px;"><?php esc_html_e('Actions','eiu-rp'); ?></div>
      <a href="<?php echo esc_url(add_query_arg('tab','submit',$page_url)); ?>"
        class="rsd-submit-lnk<?php echo ($tab==='submit'||$tab==='resubmit')?' active':''; ?>">
        <i class="bi bi-plus-circle-fill"></i><?php echo esc_html( get_option('eiu_rp_term_submit_manuscript','Submit Manuscript') ); ?>
      </a>

      <div class="rsd-nav-sec" style="margin-top:6px;"><?php esc_html_e('Settings','eiu-rp'); ?></div>
      <a href="<?php echo esc_url(add_query_arg('tab','profile',$page_url)); ?>"
        class="<?php echo $tab==='profile'?'active':''; ?>">
        <i class="bi bi-person-gear"></i><?php Terminology::e('profile'); ?>
      </a>
    </nav>

    <!-- Footer — dark sign-out -->
    <div class="rsd-footer">
      <a href="<?php echo esc_url($logout_url); ?>">
        <i class="bi bi-box-arrow-right"></i>
        <span><?php Terminology::e('sign_out'); ?></span>
      </a>
    </div>

  </aside>

  <!-- ── MAIN ─────────────────────────────────────────────── -->
  <div class="rsd-main">

    <!-- Topbar -->
    <div class="rsd-topbar">
      <h1 class="rsd-topbar-title">
        <?php
        $titles = ['overview'=>Terminology::get('overview'),'submissions'=>get_option('eiu_rp_term_my_submissions',__('My Submissions','eiu-rp')),'submit'=>get_option('eiu_rp_term_submit_manuscript',__('Submit Manuscript','eiu-rp')),'resubmit'=>Terminology::get('revise_resubmit'),'profile'=>__('Profile Settings','eiu-rp')];
        echo esc_html($titles[$tab] ?? __('Overview','eiu-rp'));
        ?>
      </h1>
      <div class="rsd-stat-pills">
        <div class="rsd-stat-pill"><span class="rsd-stat-num" style="color:var(--c-blue);"><?php echo esc_html($total); ?></span><span class="rsd-stat-lbl"><?php Terminology::e('total'); ?></span></div>
        <div class="rsd-stat-pill"><span class="rsd-stat-num" style="color:var(--c-amber);"><?php echo esc_html($totals['pending']??0); ?></span><span class="rsd-stat-lbl"><?php Terminology::e('pending'); ?></span></div>
        <div class="rsd-stat-pill"><span class="rsd-stat-num" style="color:var(--c-green);"><?php echo esc_html(($totals['published']??0)+($totals['approved']??0)); ?></span><span class="rsd-stat-lbl"><?php Terminology::e('published'); ?></span></div>
      </div>
    </div>

    <!-- Body -->
    <div class="rsd-body">
      <div class="rsd-notice rsd-ok"  id="rsd-ok-msg"></div>
      <div class="rsd-notice rsd-err" id="rsd-err-msg"></div>

<?php
/* ═══ OVERVIEW ══════════════════════════════════════════ */
if ($tab === 'overview'): ?>

  <div class="rsd-kpi-grid">
    <div class="rsd-kpi">
      <div class="rsd-kpi-icon" style="background:linear-gradient(135deg,#1a4988,#2563eb);"><i class="bi bi-journals"></i></div>
      <div><div class="rsd-kpi-num" style="color:var(--c-blue);"><?php echo esc_html($total); ?></div><div class="rsd-kpi-lbl"><?php Terminology::e('all_submissions'); ?></div></div>
    </div>
    <div class="rsd-kpi">
      <div class="rsd-kpi-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b);"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="rsd-kpi-num" style="color:var(--c-amber);"><?php echo esc_html($totals['pending']??0); ?></div><div class="rsd-kpi-lbl"><?php Terminology::e('pending'); ?></div></div>
    </div>
    <div class="rsd-kpi">
      <div class="rsd-kpi-icon" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);"><i class="bi bi-eye"></i></div>
      <div><div class="rsd-kpi-num" style="color:var(--c-purple);"><?php echo esc_html($totals['under_review']??0); ?></div><div class="rsd-kpi-lbl"><?php Terminology::e('in_review'); ?></div></div>
    </div>
    <div class="rsd-kpi">
      <div class="rsd-kpi-icon" style="background:linear-gradient(135deg,#16a34a,#15803d);"><i class="bi bi-check2-circle"></i></div>
      <div><div class="rsd-kpi-num" style="color:var(--c-green);"><?php echo esc_html(($totals['published']??0)+($totals['approved']??0)); ?></div><div class="rsd-kpi-lbl"><?php Terminology::e('published'); ?></div></div>
    </div>
  </div>

  <?php if (!empty($my_articles)): ?>
  <div class="rsd-card">
    <div class="rsd-card-head">
      <h3 class="rsd-card-title"><i class="bi bi-clock-history" style="color:var(--c-blue);"></i><?php Terminology::e('recent_submissions'); ?></h3>
      <a href="<?php echo esc_url(add_query_arg('tab','submissions',$page_url)); ?>" class="rsd-btn rsd-btn-ghost rsd-btn-sm"><?php Terminology::e('view_all'); ?></a>
    </div>
    <div class="rsd-card-body" style="padding:14px 20px;">
      <?php foreach (array_slice($my_articles,0,4) as $art):
        $s = $art['status']??'pending';
        [$slabel,$scls] = $status_cfg[$s] ?? [ucwords(str_replace('_',' ',$s)),'st-pending'];
      ?>
        <div class="rsd-row">
          <div style="flex:1;min-width:0;">
            <p class="rsd-row-title"><?php echo esc_html($art['title']??__('(Untitled)','eiu-rp')); ?></p>
            <p class="rsd-row-meta"><i class="bi bi-calendar3 me-1"></i><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($art['submitted_at']??''))); ?></p>
          </div>
          <span class="rsd-st <?php echo esc_attr($scls); ?>"><?php echo esc_html(__($slabel,'eiu-rp')); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
    <div class="rsd-empty"><i class="bi bi-inbox"></i><p style="font-weight:600;margin:0 0 4px;"><?php Terminology::e('no_submissions_yet'); ?></p></div>
  <?php endif; ?>

  <!-- Submit CTA banner -->
  <div style="background:linear-gradient(135deg,#0e1b4a,#1a3060);border-radius:var(--r);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:4px;">
    <div>
      <p style="font-family:'Cabinet Grotesk',sans-serif;font-size:16px;font-weight:800;color:#fff;margin:0 0 4px;"><?php Terminology::e('ready_to_share'); ?></p>
      <p style="font-size:13px;color:rgba(255,255,255,.5);margin:0;"><?php Terminology::e('submit_directly_desc'); ?></p>
    </div>
    <a href="<?php echo esc_url(add_query_arg('tab','submit',$page_url)); ?>" class="rsd-btn rsd-btn-accent">
      <i class="bi bi-plus-circle-fill"></i><?php echo esc_html( get_option('eiu_rp_term_submit_manuscript','Submit Manuscript') ); ?>
    </a>
  </div>

<?php
/* ═══ SUBMISSIONS ════════════════════════════════════════ */
elseif ($tab === 'submissions'): ?>

  <?php if (empty($my_articles)): ?>
    <div class="rsd-empty"><i class="bi bi-file-earmark-x"></i><p style="font-weight:600;margin:0;"><?php Terminology::e('no_submissions_found'); ?></p></div>
  <?php else:
    foreach ($my_articles as $art):
      $s = $art['status']??'pending';
      [$slabel,$scls] = $status_cfg[$s] ?? [ucwords(str_replace('_',' ',$s)),'st-pending'];
      $is_revision    = ($s === 'revision_required');
      $is_published   = ($s === 'published');
      $rev_notes      = $is_revision ? ($art['revision_notes'] ?? '') : '';
  ?>
    <div class="rsd-row"
      data-article-id="<?php echo esc_attr($art['id']); ?>"
      data-status="<?php echo esc_attr($s); ?>"
      style="<?php echo $is_revision ? 'border-color:#fed7aa;background:#fffbf5;' : ''; ?>">
      <div style="flex:1;min-width:0;">
        <?php if ($is_revision): ?>
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
            <i class="bi bi-exclamation-triangle-fill" style="color:#f97316;font-size:13px;"></i>
            <span style="font-size:11px;font-weight:700;color:#c2410c;text-transform:uppercase;letter-spacing:.04em;">
              <?php esc_html_e('Action Required','eiu-rp'); ?>
            </span>
          </div>
        <?php endif; ?>
        <?php if ($is_published): ?>
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
            <i class="bi bi-patch-check-fill" style="color:#059669;font-size:13px;"></i>
            <span style="font-size:11px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:.04em;">
              <?php esc_html_e('Published — Editing Locked','eiu-rp'); ?>
            </span>
          </div>
        <?php endif; ?>
        <p class="rsd-row-title"><?php echo esc_html($art['title']??__('(Untitled)','eiu-rp')); ?></p>
        <p class="rsd-row-meta">
          <i class="bi bi-calendar3 me-1"></i><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($art['submitted_at']??''))); ?>
          <?php if (!empty($art['file_name'])): ?> &middot; <i class="bi bi-file-earmark me-1"></i><?php echo esc_html($art['file_name']); ?><?php endif; ?>
          <?php if (!empty($art['revision_count']) && (int)$art['revision_count'] > 0): ?>
            &middot; <i class="bi bi-arrow-repeat me-1"></i><?php echo sprintf(esc_html__('Revision %d','eiu-rp'), (int)$art['revision_count']); ?>
          <?php endif; ?>
        </p>
        <?php if ($is_revision && $rev_notes): ?>
          <div style="margin-top:10px;background:#fff;border-left:3px solid #1a4988;padding:10px 14px;border-radius:0 6px 6px 0;font-size:13px;color:#374151;line-height:1.7;">
            <strong style="display:block;font-size:11px;color:#1a4988;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">
              <i class="bi bi-chat-quote-fill me-1"></i><?php Terminology::e('reviewer_feedback'); ?>
            </strong>
            <?php echo wp_kses_post($rev_notes); ?> <?php /* v2.1: rich HTML from reviewer TinyMCE editor */ ?>
          </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;">
        <span class="rsd-st <?php echo esc_attr($scls); ?>"><?php echo esc_html(__($slabel,'eiu-rp')); ?></span>
        <?php if ($is_revision): ?>
          <a href="<?php echo esc_url(add_query_arg(['tab'=>'resubmit','article_id'=>$art['id']],$page_url)); ?>"
            class="rsd-btn rsd-btn-primary"
            style="background:#1a4988;font-size:12px;padding:7px 14px;">
            <i class="bi bi-pencil-square"></i><?php Terminology::e('revise_resubmit'); ?>
          </a>
        <?php endif; ?>
        <?php if ($is_published): ?>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:#6b7280;background:#f3f4f6;border-radius:6px;padding:5px 10px;">
            <i class="bi bi-lock-fill" style="color:#9ca3af;"></i><?php esc_html_e('Read-only','eiu-rp'); ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>

<?php
/* ═══ SUBMIT ═════════════════════════════════════════════ */
elseif ($tab === 'submit'): ?>

  <p style="font-size:13px;color:var(--c-muted);margin:0 0 20px;">
    <?php esc_html_e('Complete all required steps below to submit your research.','eiu-rp'); ?>
  </p>
  <?php
  ob_start();
  \EIU_RP\Utils\Template_Loader::get_template( 'frontend/submission-form.php', array( 'redirect' => '' ) );
  echo ob_get_clean();
  ?>

<?php
/* ═══ RESUBMIT (Revise & Resubmit) ══════════════════════ */
elseif ($tab === 'resubmit'): ?>

  <?php if (!$resubmit_article): ?>
    <?php
    $locked_status = isset($resubmit_article_locked) ? ($resubmit_article_locked->status ?? '') : '';
    $is_published_lock = ($locked_status === 'published');
    ?>
    <?php if ($is_published_lock): ?>
    <!-- Feature 5: Published article — editing locked -->
    <div class="rsd-empty" style="text-align:center;">
      <div style="width:64px;height:64px;border-radius:50%;background:#e8eef8;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="bi bi-lock-fill" style="font-size:26px;color:#1a4988;"></i>
      </div>
      <p style="font-weight:800;font-size:16px;margin:0 0 8px;color:#1a2535;"><?php esc_html_e('Article is Published','eiu-rp'); ?></p>
      <p style="font-size:13px;color:#6b7280;margin:0 0 6px;max-width:380px;margin-left:auto;margin-right:auto;line-height:1.7;">
        <?php esc_html_e('This article has been published and can no longer be edited. Only articles in "Revision Required" status may be modified by researchers.','eiu-rp'); ?>
      </p>
      <p style="font-size:12px;color:#9ca3af;margin:0 0 20px;"><?php esc_html_e('Please contact the administrator if you need to make changes to a published article.','eiu-rp'); ?></p>
      <a href="<?php echo esc_url(add_query_arg('tab','submissions',$page_url)); ?>" class="rsd-btn rsd-btn-primary">
        <i class="bi bi-arrow-left"></i><?php Terminology::e('back_to_submissions'); ?>
      </a>
    </div>
    <?php else: ?>
    <div class="rsd-empty">
      <i class="bi bi-exclamation-triangle"></i>
      <p style="font-weight:600;margin:0 0 6px;"><?php esc_html_e('Article Not Found','eiu-rp'); ?></p>
      <p style="font-size:13px;margin:0 0 16px;"><?php esc_html_e('The article you are trying to revise could not be found, or it is not currently in revision-required status.','eiu-rp'); ?></p>
      <a href="<?php echo esc_url(add_query_arg('tab','submissions',$page_url)); ?>" class="rsd-btn rsd-btn-primary">
        <i class="bi bi-arrow-left"></i><?php Terminology::e('back_to_submissions'); ?>
      </a>
    </div>
    <?php endif; ?>
  <?php else:
    // Load post meta for pre-fill
    $rs_post_id    = (int) $resubmit_article->post_id;
    $rs_abstract   = $rs_post_id ? get_post_meta($rs_post_id,'_eiu_abstract',true) : ($resubmit_article->abstract??'');
    $rs_references = $rs_post_id ? get_post_meta($rs_post_id,'_eiu_references',true) : '';
    $rs_rev_notes  = $resubmit_article->revision_notes ?? '';
  ?>

    <a href="<?php echo esc_url(add_query_arg('tab','submissions',$page_url)); ?>"
      style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--c-blue);text-decoration:none;margin-bottom:18px;">
      <i class="bi bi-arrow-left"></i><?php Terminology::e('back_to_submissions'); ?>
    </a>

    <!-- Reviewer Feedback Banner -->
    <?php if ($rs_rev_notes): ?>
    <div style="background:#fffbeb;border:1.5px solid #fcd34d;border-radius:10px;padding:18px 22px;margin-bottom:22px;">
      <div style="display:flex;align-items:flex-start;gap:12px;">
        <i class="bi bi-chat-quote-fill" style="color:#d97706;font-size:22px;flex-shrink:0;margin-top:2px;"></i>
        <div>
          <p style="font-family:'Cabinet Grotesk',sans-serif;font-size:14px;font-weight:800;color:#92400e;margin:0 0 8px;">
            <?php esc_html_e('Reviewer Feedback — Please address these points before resubmitting:','eiu-rp'); ?>
          </p>
          <div style="font-size:14px;color:#78350f;line-height:1.8;white-space:pre-wrap;">
            <?php echo wp_kses_post($rs_rev_notes); ?> <?php /* v2.1: rich HTML from reviewer TinyMCE editor */ ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Article info header -->
    <div class="rsd-card" style="margin-bottom:20px;">
      <div class="rsd-card-head">
        <h3 class="rsd-card-title">
          <i class="bi bi-pencil-square" style="color:var(--c-blue);"></i>
          <?php esc_html_e('Revise & Resubmit Article','eiu-rp'); ?>
        </h3>
        <?php if (!empty($resubmit_article->revision_count)): ?>
          <span style="font-size:12px;background:#e8eef8;color:#1a4988;border-radius:20px;padding:3px 12px;font-weight:700;">
            <?php echo sprintf(esc_html__('Revision %d','eiu-rp'), (int)$resubmit_article->revision_count); ?>
          </span>
        <?php endif; ?>
      </div>
      <div class="rsd-card-body" style="padding:16px 22px;">
        <p style="margin:0;font-size:14px;color:var(--c-muted);">
          <i class="bi bi-journal-text me-1" style="color:#1a4988;"></i>
          <strong style="color:var(--c-text);"><?php echo esc_html(get_the_title($rs_post_id) ?: __('(Untitled)','eiu-rp')); ?></strong>
        </p>
        <p style="margin:6px 0 0;font-size:12px;color:var(--c-muted);">
          <?php esc_html_e('Update the fields below to address the reviewer\'s feedback, then click Resubmit. Your original file is preserved unless you upload a new one.','eiu-rp'); ?>
        </p>
      </div>
    </div>

    <!-- Resubmit form (pre-filled) -->
    <div id="rsd-resubmit-notice-ok"  class="rsd-notice rsd-ok"></div>
    <div id="rsd-resubmit-notice-err" class="rsd-notice rsd-err"></div>

    <form id="rsd-resubmit-form" enctype="multipart/form-data">
      <input type="hidden" name="action"     value="eiu_rp_resubmit_article">
      <input type="hidden" name="nonce"      value="<?php echo esc_attr($resubmit_nonce); ?>">
      <input type="hidden" name="article_id" value="<?php echo esc_attr($resubmit_article_id); ?>">

      <!-- Article Title -->
      <div class="rsd-card">
        <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-type-h1" style="color:var(--c-blue);"></i><?php esc_html_e('Article Title','eiu-rp'); ?></h3></div>
        <div class="rsd-card-body">
          <input type="text" name="article_title" class="form-control form-control-lg"
            value="<?php echo esc_attr(get_the_title($rs_post_id) ?: $resubmit_article->title ?? ''); ?>"
            placeholder="<?php esc_attr_e('Article title','eiu-rp'); ?>" required>
        </div>
      </div>

      <!-- Thumbnail update -->
      <div class="rsd-card">
        <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-image" style="color:var(--c-blue);"></i><?php esc_html_e('Article Thumbnail','eiu-rp'); ?></h3></div>
        <div class="rsd-card-body">
          <?php
          $rs_thumb_id  = $rs_post_id ? (int)get_post_meta($rs_post_id,'_eiu_thumbnail_attachment_id',true) : 0;
          $rs_thumb_url = $rs_thumb_id ? wp_get_attachment_image_url($rs_thumb_id,'medium') : '';
          if (!$rs_thumb_url && $rs_post_id) $rs_thumb_url = get_the_post_thumbnail_url($rs_post_id,'medium');
          ?>
          <?php if ($rs_thumb_url): ?>
            <img id="rsd-thumb-preview" src="<?php echo esc_url($rs_thumb_url); ?>" alt="" style="max-width:220px;border-radius:8px;border:1.5px solid #e5e7eb;margin-bottom:10px;display:block;">
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 10px;"><?php esc_html_e('Current thumbnail. Upload below to replace.','eiu-rp'); ?></p>
          <?php endif; ?>
          <input type="file" name="article_thumbnail" id="rsd-resubmit-thumb-file" class="form-control" accept="image/jpeg,image/png,image/webp"
            onchange="(function(inp){if(!inp.files[0])return;var r=new FileReader();r.onload=function(e){var el=document.getElementById('rsd-thumb-preview');if(!el){el=document.createElement('img');el.id='rsd-thumb-preview';el.style='max-width:220px;border-radius:8px;border:1.5px solid #e5e7eb;margin-bottom:10px;display:block;';inp.closest('.rsd-card-body').prepend(el);}el.src=e.target.result;};r.readAsDataURL(inp.files[0]);})(this)">
          <p style="font-size:12px;color:var(--c-muted);margin:6px 0 0;"><?php esc_html_e('JPG, PNG or WebP. Leave blank to keep existing thumbnail.','eiu-rp'); ?></p>
        </div>
      </div>

      <!-- Subject + Keywords -->
      <div class="rsd-card">
        <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-tags" style="color:var(--c-blue);"></i><?php esc_html_e('Subject & Keywords','eiu-rp'); ?></h3></div>
        <div class="rsd-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="rsd-label"><?php esc_html_e('Subject / Category','eiu-rp'); ?></label>
              <?php $subjects = \EIU_RP\Utils\Helpers::subjects_list(); ?>
              <select name="subject" class="form-select">
                <option value=""><?php esc_html_e('-- Select subject --','eiu-rp'); ?></option>
                <?php foreach ($subjects as $subj): ?>
                  <option value="<?php echo esc_attr($subj); ?>" <?php selected($subj, $resubmit_article->subject ?? ''); ?>><?php echo esc_html($subj); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="rsd-label"><?php esc_html_e('Keywords','eiu-rp'); ?></label>
              <?php $rs_keywords = $rs_post_id ? get_post_meta($rs_post_id,'_eiu_keywords',true) : ($resubmit_article->keywords ?? ''); ?>
              <input type="text" name="keywords" class="form-control"
                value="<?php echo esc_attr($rs_keywords); ?>"
                placeholder="<?php esc_attr_e('comma separated','eiu-rp'); ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Abstract — WYSIWYG editor (Researcher) -->
      <div class="rsd-card">
        <div class="rsd-card-head">
          <h3 class="rsd-card-title"><i class="bi bi-text-paragraph" style="color:var(--c-blue);"></i><?php esc_html_e('Abstract','eiu-rp'); ?></h3>
          <span style="font-size:11px;background:#eef4ff;color:#1a4988;border-radius:20px;padding:2px 10px;font-weight:700;">
            <i class="bi bi-type-bold me-1"></i><?php esc_html_e('Rich Text Editor','eiu-rp'); ?>
          </span>
        </div>
        <div class="rsd-card-body" style="padding:0;">
          <!-- Quill Rich Text Editor — Abstract (Researcher Resubmit) -->
          <?php $rs_abstract_editor_id = 'rsd_abstract_' . absint($resubmit_article->id ?? 0); ?>
          <div class="eiu-quill-wrap" id="rsd-quill-wrap">
            <div id="rsd-abstract-quill" style="min-height:260px;"></div>
          </div>
          <textarea
            id="<?php echo esc_attr($rs_abstract_editor_id); ?>"
            name="abstract"
            style="display:none;"><?php echo esc_textarea(wp_kses_post($rs_abstract)); ?></textarea>
        </div>
      </div>

      <!-- Author Details -->
      <div class="rsd-card">
        <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-person-fill" style="color:var(--c-blue);"></i><?php esc_html_e('Author Details','eiu-rp'); ?></h3></div>
        <div class="rsd-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="rsd-label"><?php esc_html_e('Author Name','eiu-rp'); ?></label>
              <input type="text" name="author_name" class="form-control"
                value="<?php echo esc_attr($resubmit_article->author_name ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="rsd-label"><?php esc_html_e('Author Email','eiu-rp'); ?></label>
              <input type="email" name="author_email" class="form-control"
                value="<?php echo esc_attr($resubmit_article->author_email ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="rsd-label"><?php esc_html_e('Organization','eiu-rp'); ?></label>
              <input type="text" name="author_org" class="form-control"
                value="<?php echo esc_attr($resubmit_article->author_org ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="rsd-label"><?php esc_html_e('Country','eiu-rp'); ?></label>
              <input type="text" name="country" class="form-control"
                value="<?php echo esc_attr($resubmit_article->country ?? ''); ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- References -->
      <?php if ($rs_references): ?>
      <div class="rsd-card">
        <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-journals" style="color:var(--c-blue);"></i><?php esc_html_e('References','eiu-rp'); ?></h3></div>
        <div class="rsd-card-body">
          <textarea name="references" class="form-control" rows="6" style="resize:vertical;"><?php echo esc_textarea($rs_references); ?></textarea>
        </div>
      </div>
      <?php endif; ?>

      <!-- File upload (optional replacement) -->
      <div class="rsd-card">
        <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-file-earmark-arrow-up" style="color:var(--c-blue);"></i><?php esc_html_e('Updated File (Optional)','eiu-rp'); ?></h3></div>
        <div class="rsd-card-body">
          <?php if (!empty($resubmit_article->file_name)): ?>
            <p style="font-size:13px;color:var(--c-muted);margin:0 0 12px;">
              <i class="bi bi-file-earmark me-1"></i>
              <?php esc_html_e('Current file:','eiu-rp'); ?> <strong><?php echo esc_html($resubmit_article->file_name); ?></strong>
              &mdash; <?php esc_html_e('leave blank to keep the existing file.','eiu-rp'); ?>
            </p>
          <?php endif; ?>
          <input type="file" name="article_file" class="form-control" accept=".pdf,.ppt,.pptx">
        </div>
      </div>

      <!-- Submit button -->
      <div style="display:flex;align-items:center;gap:14px;margin-top:8px;padding:20px 0;">
        <button type="submit" class="rsd-save-btn" id="rsd-resubmit-btn">
          <i class="bi bi-send-fill"></i><?php esc_html_e('Resubmit Article','eiu-rp'); ?>
        </button>
        <a href="<?php echo esc_url(add_query_arg('tab','submissions',$page_url)); ?>"
          style="font-size:13px;color:var(--c-muted);text-decoration:none;">
          <?php esc_html_e('Cancel','eiu-rp'); ?>
        </a>
      </div>
    </form>

  <?php endif; // end resubmit_article check ?>

<?php
/* ═══ PROFILE ════════════════════════════════════════════ */
elseif ($tab === 'profile'): ?>

  <div class="rsd-card" style="max-width:640px;">
    <div class="rsd-card-head"><h3 class="rsd-card-title"><i class="bi bi-person-gear" style="color:var(--c-blue);"></i><?php esc_html_e('Profile Settings','eiu-rp'); ?></h3></div>
    <div class="rsd-card-body">
      <div class="rsd-notice rsd-ok"  id="prof-ok"></div>
      <div class="rsd-notice rsd-err" id="prof-err"></div>

      <!-- v2.1: Profile Photo Upload -->
      <div style="display:flex;align-items:center;gap:18px;margin-bottom:24px;">
        <div style="position:relative;flex-shrink:0;">
          <div id="rsd-photo-circle"
            style="width:72px;height:72px;border-radius:50%;background:var(--c-blue,#1a4988);display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid #e8eef8;cursor:pointer;font-size:26px;font-weight:800;color:#fff;">
            <?php if ($rsd_profile_photo_url): ?>
              <img src="<?php echo esc_url($rsd_profile_photo_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <span><?php echo esc_html(strtoupper(substr($display_name,0,1))); ?></span>
            <?php endif; ?>
          </div>
          <div style="position:absolute;bottom:0;right:0;width:22px;height:22px;background:var(--c-blue,#1a4988);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;"
            onclick="document.getElementById('rsd-photo-file').click()">
            <i class="bi bi-camera-fill" style="font-size:10px;color:#fff;"></i>
          </div>
        </div>
        <div>
          <p style="font-size:13px;font-weight:700;margin:0 0 4px;"><?php esc_html_e('Profile Photo','eiu-rp'); ?></p>
          <p style="font-size:12px;color:var(--c-muted);margin:0 0 8px;"><?php esc_html_e('JPG, PNG or WebP · max 3 MB','eiu-rp'); ?></p>
          <button type="button" class="rsd-btn" style="padding:6px 14px;font-size:13px;"
            onclick="document.getElementById('rsd-photo-file').click()">
            <i class="bi bi-upload"></i><?php esc_html_e('Upload Photo','eiu-rp'); ?>
          </button>
          <input type="file" id="rsd-photo-file" accept="image/jpeg,image/png,image/webp" style="display:none;">
          <span id="rsd-photo-msg" style="font-size:12px;margin-left:8px;"></span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div><label class="rsd-label"><?php esc_html_e('First Name','eiu-rp'); ?> <span style="color:var(--c-red);">*</span></label><input type="text" class="form-control" id="prof-fname" value="<?php echo esc_attr($first_name); ?>"></div>
        <div><label class="rsd-label"><?php esc_html_e('Last Name','eiu-rp'); ?></label><input type="text" class="form-control" id="prof-lname" value="<?php echo esc_attr($last_name); ?>"></div>
      </div>
      <div style="margin-bottom:16px;"><label class="rsd-label"><?php esc_html_e('Display Name','eiu-rp'); ?> <span style="color:var(--c-red);">*</span></label><input type="text" class="form-control" id="prof-name" value="<?php echo esc_attr($display_name); ?>"></div>
      <div style="margin-bottom:16px;"><label class="rsd-label"><?php esc_html_e('Email','eiu-rp'); ?></label><input type="email" class="form-control" value="<?php echo esc_attr($user_email); ?>" disabled style="background:#f8f9fa;color:#9ca3af;"><p style="font-size:11px;color:var(--c-muted);margin:4px 0 0;"><?php esc_html_e('Contact admin to change email.','eiu-rp'); ?></p></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div><label class="rsd-label"><?php esc_html_e('Phone','eiu-rp'); ?></label><input type="tel" class="form-control" id="prof-phone" value="<?php echo esc_attr($phone); ?>"></div>
        <div><label class="rsd-label"><?php esc_html_e('Country','eiu-rp'); ?></label><input type="text" class="form-control" id="prof-country" value="<?php echo esc_attr($country); ?>"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
        <div><label class="rsd-label"><?php esc_html_e('Nationality','eiu-rp'); ?></label><input type="text" class="form-control" id="prof-nationality" value="<?php echo esc_attr($nationality); ?>"></div>
        <div><label class="rsd-label"><?php esc_html_e('Area of Expertise','eiu-rp'); ?></label><input type="text" class="form-control" id="prof-expertise" value="<?php echo esc_attr($expertise); ?>" placeholder="<?php esc_attr_e('e.g. Computer Science…','eiu-rp'); ?>"></div>
      </div>
      <hr style="border-color:var(--c-border);margin:4px 0 20px;">
      <p style="font-size:13px;font-weight:700;margin:0 0 14px;"><?php esc_html_e('Change Password','eiu-rp'); ?></p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
        <div><label class="rsd-label"><?php esc_html_e('New Password','eiu-rp'); ?></label><input type="password" class="form-control" id="prof-pass" autocomplete="new-password" placeholder="<?php esc_attr_e('Leave blank to keep current','eiu-rp'); ?>"></div>
        <div><label class="rsd-label"><?php esc_html_e('Confirm Password','eiu-rp'); ?></label><input type="password" class="form-control" id="prof-pass2" autocomplete="new-password" placeholder="<?php esc_attr_e('Repeat new password','eiu-rp'); ?>"></div>
      </div>
      <button type="button" class="rsd-save-btn" id="prof-save">
        <i class="bi bi-floppy-fill"></i><?php Terminology::e('save_changes'); ?>
      </button>
    </div>
  </div>

<?php endif; ?>

    </div><!-- .rsd-body -->
  </div><!-- .rsd-main -->
</div><!-- #eiu-rsd -->

<script>
(function(){
'use strict';
var ajax  = '<?php echo esc_js($ajax_url); ?>';
var nonce = '<?php echo esc_js($profile_nonce); ?>';

function showMsg(id,msg,ok){
  var el=document.getElementById(id);
  if(!el) return;
  el.textContent=msg;
  el.className='rsd-notice '+(ok?'rsd-ok':'rsd-err');
  el.style.display='block';
  el.scrollIntoView({behavior:'smooth',block:'nearest'});
  setTimeout(()=>{el.style.display='none';},6000);
}

var saveBtn=document.getElementById('prof-save');
if(saveBtn){
  saveBtn.addEventListener('click',function(){
    saveBtn.disabled=true;
    saveBtn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';
    var fd=new FormData();
    fd.append('action','eiu_rp_update_researcher_profile');
    fd.append('nonce',nonce);
    fd.append('display_name',document.getElementById('prof-name').value||'');
    fd.append('first_name',document.getElementById('prof-fname').value||'');
    fd.append('last_name',document.getElementById('prof-lname').value||'');
    fd.append('phone',document.getElementById('prof-phone').value||'');
    fd.append('country',document.getElementById('prof-country').value||'');
    fd.append('nationality',document.getElementById('prof-nationality').value||'');
    fd.append('expertise',document.getElementById('prof-expertise').value||'');
    fd.append('new_password',document.getElementById('prof-pass').value||'');
    fd.append('confirm_password',document.getElementById('prof-pass2').value||'');
    fetch(ajax,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
      showMsg(res.success?'prof-ok':'prof-err',(res.data&&res.data.message)?res.data.message:'<?php echo esc_js(__('An error occurred.','eiu-rp')); ?>',res.success);
      saveBtn.disabled=false;
      saveBtn.innerHTML='<i class="bi bi-floppy-fill"></i><?php echo esc_js(__('Save Changes','eiu-rp')); ?>';
    }).catch(()=>{
      saveBtn.disabled=false;
      saveBtn.innerHTML='<i class="bi bi-floppy-fill"></i><?php echo esc_js(__('Save Changes','eiu-rp')); ?>';
    });
  });
}

/* ── Resubmit form ──────────────────────────────────────── */
var resubmitForm = document.getElementById('rsd-resubmit-form');
if(resubmitForm){
  resubmitForm.addEventListener('submit', function(e){
    e.preventDefault();
    var btn = document.getElementById('rsd-resubmit-btn');
    var title = resubmitForm.querySelector('[name="article_title"]');
    if(!title || !title.value.trim()){
      showMsg('rsd-resubmit-notice-err','<?php echo esc_js(__('Article title is required.','eiu-rp')); ?>',false);
      return;
    }
    /* Sync Quill content to hidden textarea before FormData reads it */
    if(window.rsd_quill_instance){ var rsdTa=document.getElementById(<?php echo isset($rs_abstract_editor_id)?wp_json_encode($rs_abstract_editor_id):"''"; ?>); if(rsdTa) rsdTa.value=window.rsd_quill_instance.root.innerHTML; }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><?php echo esc_js(__('Submitting…','eiu-rp')); ?>';
    var fd = new FormData(resubmitForm);
    fetch(ajax,{method:'POST',body:fd})
      .then(r=>r.json())
      .then(res=>{
        if(res.success){
          showMsg('rsd-resubmit-notice-ok',(res.data&&res.data.message)?res.data.message:'<?php echo esc_js(__('Resubmitted successfully.','eiu-rp')); ?>',true);
          btn.innerHTML='<i class="bi bi-check-circle-fill me-1"></i><?php echo esc_js(__('Submitted','eiu-rp')); ?>';
          // Redirect to submissions tab after 2.5s
          setTimeout(function(){
            window.location.href='<?php echo esc_js(add_query_arg('tab','submissions',$page_url)); ?>';
          },2500);
        } else {
          showMsg('rsd-resubmit-notice-err',(res.data&&res.data.message)?res.data.message:'<?php echo esc_js(__('An error occurred. Please try again.','eiu-rp')); ?>',false);
          btn.disabled=false;
          btn.innerHTML='<i class="bi bi-send-fill"></i><?php echo esc_js(__('Resubmit Article','eiu-rp')); ?>';
        }
      })
      .catch(()=>{
        showMsg('rsd-resubmit-notice-err','<?php echo esc_js(__('Network error. Please try again.','eiu-rp')); ?>',false);
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-send-fill"></i><?php echo esc_js(__('Resubmit Article','eiu-rp')); ?>';
      });
  });
}

/* ════════════════════════════════════════════════════════════════════
   Feature 4: Real-time status sync — poll every 30 s on the
   submissions tab and refresh the list when status changes.
   Uses a lightweight AJAX endpoint that returns the current article
   statuses for this researcher, comparing them against the rendered
   data-status attributes. On any change, a reload notification banner
   is shown so the researcher can see the update.
   ════════════════════════════════════════════════════════════════════ */
(function(){
  var pollAjax='<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  var pollNonce='<?php echo esc_js(wp_create_nonce('eiu_rp_frontend')); ?>';
  var currentTab='<?php echo esc_js($tab); ?>';
  var pollInterval=null;
  var notifyShown=false;

  function getRenderedStatuses(){
    var map={};
    document.querySelectorAll('.rsd-row[data-article-id]').forEach(function(row){
      map[row.getAttribute('data-article-id')]=row.getAttribute('data-status');
    });
    return map;
  }

  function showStatusChangeNotice(){
    if(notifyShown) return;
    notifyShown=true;
    var notice=document.createElement('div');
    notice.id='rsd-status-update-notice';
    notice.style.cssText='position:fixed;bottom:24px;right:24px;background:#1a4988;color:#fff;padding:14px 20px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.25);z-index:9999;font-size:14px;font-weight:600;display:flex;align-items:center;gap:12px;cursor:pointer;max-width:360px;animation:rsd-slide-in .3s ease;';
    notice.innerHTML='<i class="bi bi-arrow-repeat" style="font-size:18px;flex-shrink:0;"></i><span><?php echo esc_js(__('Article status updated — click to refresh','eiu-rp')); ?></span>';
    notice.addEventListener('click',function(){ window.location.reload(); });
    document.body.appendChild(notice);
  }

  function pollStatuses(){
    /* Only poll when on the submissions tab */
    if(currentTab!=='submissions') return;
    var rendered=getRenderedStatuses();
    var ids=Object.keys(rendered);
    if(!ids.length) return;

    var fd=new FormData();
    fd.append('action','eiu_rp_get_article_statuses');
    fd.append('nonce',pollNonce);
    fd.append('ids',ids.join(','));

    fetch(pollAjax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(!res.success||!res.data) return;
        var changed=false;
        ids.forEach(function(id){
          if(res.data[id] && res.data[id]!==rendered[id]) changed=true;
        });
        if(changed) showStatusChangeNotice();
      })
      .catch(function(){/* silent */});
  }

  /* Start polling only on submissions tab */
  if(currentTab==='submissions'){
    pollInterval=setInterval(pollStatuses, 30000);
  }

  /* data-article-id and data-status are set directly in PHP on each .rsd-row.
     The fallback JS that read from badge class regex is no longer needed. */

  /* Pulse animation */
  if(!document.getElementById('rsd-poll-style')){
    var st=document.createElement('style');
    st.id='rsd-poll-style';
    st.textContent='@keyframes rsd-slide-in{from{transform:translateX(120%);opacity:0}to{transform:none;opacity:1}}';
    document.head.appendChild(st);
  }
}());
}());
</script>

<script>
/* v2.1: Researcher profile photo upload */
(function(){
'use strict';
var ajax  = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
var nonce = '<?php echo esc_js(wp_create_nonce("eiu_rp_frontend")); ?>';

var rsdPhotoFile = document.getElementById('rsd-photo-file');
if (rsdPhotoFile) {
  rsdPhotoFile.addEventListener('change', function(){
    var file = this.files[0];
    if (!file) return;
    var msgEl = document.getElementById('rsd-photo-msg');
    if (msgEl){ msgEl.textContent='<?php echo esc_js(__("Uploading…","eiu-rp")); ?>';msgEl.style.color='#6b7280'; }
    var fd = new FormData();
    fd.append('action','eiu_rp_upload_profile_photo');
    fd.append('nonce', nonce);
    fd.append('photo', file);
    fetch(ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(res.success){
          var imgUrl = res.data.thumb_url || res.data.full_url;
          /* Update profile circle on the profile tab */
          var circle=document.getElementById('rsd-photo-circle');
          if(circle) circle.innerHTML='<img src="'+imgUrl+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
          /* Also update sidebar avatar */
          var sidebarAv=document.getElementById('rsd-sidebar-av');
          if(sidebarAv){ sidebarAv.innerHTML='<img src="'+imgUrl+'" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'; sidebarAv.style.background='none'; sidebarAv.style.padding='0'; sidebarAv.style.overflow='hidden'; }
          if(msgEl){msgEl.textContent=res.data.message;msgEl.style.color='#059669';}
        } else {
          if(msgEl){msgEl.textContent=(res.data&&res.data.message)||'<?php echo esc_js(__("Upload failed.","eiu-rp")); ?>';msgEl.style.color='#991b1b';}
        }
      })
      .catch(function(){
        if(msgEl){msgEl.textContent='<?php echo esc_js(__("Network error.","eiu-rp")); ?>';msgEl.style.color='#991b1b';}
      });
  });
}
}());
</script>
<!-- Quill Rich Text Editor — Researcher Resubmit Abstract -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
<style>
#rsd-quill-wrap.eiu-quill-wrap .ql-editor { min-height:260px; }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
<?php if ( isset($rs_abstract_editor_id) ): ?>
(function(){
'use strict';
var rsd_ajax  = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
var rsd_nonce = '<?php echo esc_js(wp_create_nonce("eiu_rp_frontend")); ?>';
var rsd_ta_id = <?php echo wp_json_encode($rs_abstract_editor_id); ?>;
var rsd_initial = <?php echo wp_json_encode(wp_kses_post($rs_abstract)); ?>;

function rsd_img_handler(q){
  var inp=document.createElement('input');
  inp.type='file'; inp.accept='image/jpeg,image/png,image/webp';
  inp.click();
  inp.onchange=function(){
    var file=inp.files[0]; if(!file) return;
    var fd=new FormData();
    fd.append('action','eiu_rp_upload_media_image');
    fd.append('nonce',rsd_nonce); fd.append('image',file);
    fetch(rsd_ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(res.success&&res.data&&res.data.url){
          var range=q.getSelection(true);
          q.insertEmbed(range.index,'image',res.data.url,'user');
        }
      });
  };
}

function rsd_init_quill(){
  var container=document.getElementById('rsd-abstract-quill');
  if(!container||window.rsd_quill_instance) return;
  window.rsd_quill_instance=new Quill(container,{
    theme:'snow',
    placeholder:'<?php echo esc_js(__("Write your revised abstract here…","eiu-rp")); ?>',
    modules:{
      toolbar:{
        container:[
          [{'header':[1,2,3,false]},{'font':[]},{'size':['small',false,'large']}],
          ['bold','italic','underline','strike'],
          [{'color':[]},{'background':[]}],
          [{'list':'ordered'},{'list':'bullet'}],
          [{'align':[]}],
          ['blockquote','code-block'],
          ['link','image'],
          ['clean']
        ],
        handlers:{image:function(){rsd_img_handler(window.rsd_quill_instance);}}
      }
    }
  });

  /* Pre-fill with existing abstract content */
  if(rsd_initial){
    window.rsd_quill_instance.root.innerHTML=rsd_initial;
    var ta=document.getElementById(rsd_ta_id);
    if(ta) ta.value=rsd_initial;
  }

  window.rsd_quill_instance.on('text-change',function(){
    var ta=document.getElementById(rsd_ta_id);
    if(ta) ta.value=window.rsd_quill_instance.root.innerHTML;
  });
}

if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',rsd_init_quill);
} else {
  rsd_init_quill();
}
}());
<?php endif; ?>
</script>


<?php if ( isset($rs_abstract_editor_id) ): ?>
<!-- ═══════════════════════════════════════════════════════════════════════
  TinyMCE 5 — Standalone CDN loader for Researcher Resubmit Abstract editor.
  ═════════════════════════════════════════════════════════════════════════ -->

<?php endif; ?>
