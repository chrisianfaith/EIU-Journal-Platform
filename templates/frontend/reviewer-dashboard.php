<?php
/**
 * Frontend Reviewer Dashboard — v2.0 "Crystalline"
 *
 * Revamped layout: fixed left sidebar + scrollable right content.
 * Sections: Dashboard | Assigned Articles | Reviews | Submit Article | Reviewers | Profile
 * - Inline article submission (no redirect)
 * - Modern Crystalline design system
 * - IBM Plex Sans + Syne display font
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Utils\Terminology;

wp_enqueue_style( 'bootstrap-icons-eiu', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', array(), '1.11.3' );

// ── v1.9: Unauthenticated redirect ───────────────────────────────
// The plugin's render_reviewer_dashboard() shortcode handler redirects
// unauthenticated users to the unified login page before this template
// is ever loaded. This guard is a safety fallback for edge cases where
// the template is invoked directly (e.g. custom page builders).
if ( ! is_user_logged_in() ) {
    $unified_id  = get_option( 'eiu_rp_unified_login_page_id' );
    $login_url   = $unified_id ? get_permalink( $unified_id ) : home_url( '/login/' );
    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $login_url   = add_query_arg( 'redirect_to', rawurlencode( $current_url ), $login_url );
    wp_safe_redirect( esc_url_raw( $login_url ) );
    exit;
}


// ── Resolve reviewer profile ──────────────────────────────────────
$user_id  = get_current_user_id();

// Cross-role access protection: researchers trying to access reviewer
// dashboard get redirected to their own dashboard instead.
$_rv_current = wp_get_current_user();
if ( in_array( 'eiu_researcher', (array) $_rv_current->roles, true )
    && ! in_array( 'eiu_reviewer', (array) $_rv_current->roles, true )
    && ! current_user_can( 'manage_options' ) ) {
    $researcher_page_id = get_option( 'eiu_rp_researcher_dashboard_page_id' );
    $researcher_url     = $researcher_page_id ? get_permalink( $researcher_page_id ) : home_url( '/researcher-dashboard/' );
    wp_safe_redirect( esc_url_raw( $researcher_url ) );
    exit;
}

$reviewer = \EIU_RP\Models\Reviewer::get_by_user( $user_id );

if ( ! $reviewer ) {
    $wp_user = wp_get_current_user();
    if ( in_array( 'eiu_reviewer', (array) $wp_user->roles, true ) ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'eiu_reviewers', array(
            'user_id'=>$wp_user->ID, 'full_name'=>$wp_user->display_name ?: $wp_user->user_login,
            'email'=>$wp_user->user_email, 'organization'=>'', 'specialization'=>'',
            'verified'=>1, 'verification_key'=>'', 'registered_at'=>current_time('mysql'),
        ), array('%d','%s','%s','%s','%s','%d','%s','%s') );
        $reviewer = \EIU_RP\Models\Reviewer::get_by_user($wp_user->ID);
    }
    if ( ! $reviewer ) :
        echo '<div style="padding:48px;text-align:center;font-family:\'DM Sans\',sans-serif;">';
        echo '<i class="bi bi-person-x" style="font-size:3rem;color:#9ca3af;"></i>';
        echo '<h2 style="font-size:18px;font-weight:700;margin:12px 0 8px;">' . esc_html__('Account Not Linked','eiu-rp') . '</h2>';
        echo '<a href="' . esc_url(wp_logout_url(get_permalink())) . '" style="display:inline-block;background:#1a4988;color:#fff;padding:9px 22px;border-radius:7px;font-weight:600;font-size:14px;text-decoration:none;"><i class="bi bi-arrow-clockwise me-1"></i>' . esc_html__('Log out and retry','eiu-rp') . '</a>';
        echo '</div>';
        return;
    endif;
}

// Allow admins / researchers to also use this dashboard
if ( ! $reviewer ) { return; }

// ── Data ──────────────────────────────────────────────────────────
$reviews       = \EIU_RP\Models\Review::get_by_reviewer( (int) $reviewer->id );
$nonce         = wp_create_nonce('eiu_rp_frontend');
$pending       = array_filter($reviews, fn($r) => in_array($r['status']??'',['assigned','in_progress'],true));
$submitted     = array_filter($reviews, fn($r) => ($r['status']??'')==='submitted');
$completed     = array_filter($reviews, fn($r) => in_array($r['status']??'',['approved','rejected'],true));
$all_reviewers = \EIU_RP\Models\Reviewer::query(['verified'=>1,'per_page'=>100])['items']??[];
$page_url      = get_permalink();
$tab           = sanitize_key($_GET['tab'] ?? 'dashboard');
if ( !in_array($tab, ['dashboard','articles','reviews','submit','reviewers','profile','applications'], true) ) $tab='dashboard';
$article_id    = absint($_GET['article_id'] ?? 0);

// v2.0.1: Load applications assigned to this reviewer.
// Fix: collect ALL eiu_reviewers row IDs that belong to this WP user
// (accounts for auto-created rows and any legacy ID mismatches),
// then query applications matching any of those IDs.
$app_id_view    = absint($_GET['app_id'] ?? 0);
global $wpdb;
// Gather every eiu_reviewers.id linked to the current WP user.
$_rv_ids = $wpdb->get_col( $wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}eiu_reviewers WHERE user_id = %d AND is_deleted = 0",
    $user_id
) );
if ( empty($_rv_ids) ) { $_rv_ids = array( (int) $reviewer->id ); }
$_rv_ids       = array_map('intval', $_rv_ids);
$_rv_ids_in    = implode(',', $_rv_ids); // safe: all cast to int
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$reviewer_apps = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}eiu_researcher_applications
     WHERE assigned_reviewer_id IN ({$_rv_ids_in})
     ORDER BY submitted_at DESC"
) ?: array();
$logout_url    = wp_logout_url(get_permalink());

// Submission form data
$subjects   = \EIU_RP\Utils\Helpers::subjects_list();
$sf_nonce   = wp_create_nonce('eiu_rp_frontend');

// Profile save handler
$profile_saved  = false;
$profile_errors = array();
if ( $tab === 'profile' && isset($_POST['eiu_profile_save']) &&
     wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['eiu_profile_nonce']??'')),'eiu_profile_save') ) {
    global $wpdb;
    $new_name = sanitize_text_field(wp_unslash($_POST['full_name']??''));
    $new_spec = sanitize_text_field(wp_unslash($_POST['specialization']??''));
    $new_org  = sanitize_text_field(wp_unslash($_POST['organization']??''));
    if ( empty($new_name) ) {
        $profile_errors[] = __('Full name is required.','eiu-rp');
    } else {
        $wpdb->update( $wpdb->prefix.'eiu_reviewers',
            array('full_name'=>$new_name,'specialization'=>$new_spec,'organization'=>$new_org),
            array('id'=>(int)$reviewer->id), array('%s','%s','%s'), array('%d') );
        wp_update_user(array('ID'=>$user_id,'display_name'=>$new_name));
        $reviewer = \EIU_RP\Models\Reviewer::get_by_user($user_id);
        $profile_saved = true;
    }
}
// v2.1: get reviewer profile photo URL for display
$rv_profile_photo_id  = get_user_meta( $user_id, 'eiu_profile_photo_id', true );
if ( ! $rv_profile_photo_id && isset($reviewer->profile_photo_id) ) {
    $rv_profile_photo_id = (int) $reviewer->profile_photo_id;
}
$rv_profile_photo_url = $rv_profile_photo_id
    ? wp_get_attachment_image_url( (int) $rv_profile_photo_id, 'thumbnail' )
    : '';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Cabinet+Grotesk:wght@700;800;900&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
.eiu-quill-wrap{border:1.5px solid #e5e7eb;border-radius:8px;overflow:hidden;background:#fff;}
.eiu-quill-wrap .ql-toolbar{background:#f8f9fa;border:none;border-bottom:1px solid #e5e7eb;}
.eiu-quill-wrap .ql-container{border:none;font-size:14px;}
.eiu-quill-wrap .ql-editor{line-height:1.7;padding:12px 14px;color:#212529;}
.eiu-quill-wrap .ql-editor.ql-blank::before{color:#9ca3af;font-style:normal;}
</style>
<style>
/* ══════════════════════════════════════════════════════════════
   EIU Reviewer Dashboard v3.0 "Altitude"
   Design: DM Sans body · Cabinet Grotesk display
   Scoped to #eiu-rv2 — zero global leaks
══════════════════════════════════════════════════════════════ */

#eiu-rv2 *, #eiu-rv2 *::before, #eiu-rv2 *::after { box-sizing: border-box; }

#eiu-rv2 {
  --font-display: 'Cabinet Grotesk', sans-serif;
  --font-body:    'DM Sans', sans-serif;

  /* Brand palette */
  --navy:         #0a1628;
  --navy-mid:     #112040;
  --navy-light:   #1e3a5f;
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

  /* Surfaces */
  --bg:           #f7f8fc;
  --surface:      #ffffff;
  --surface-2:    #f0f3f9;
  --border:       #e2e8f0;
  --border-focus: #2563eb;

  /* Text */
  --text-primary:   #0f172a;
  --text-secondary: #475569;
  --text-muted:     #94a3b8;
  --text-xmuted:    #cbd5e1;

  /* Sidebar */
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

  /* Effects */
  --shadow-xs:  0 1px 2px rgba(0,0,0,0.06);
  --shadow-sm:  0 2px 8px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.05);
  --shadow-md:  0 4px 20px rgba(0,0,0,0.09), 0 2px 8px rgba(0,0,0,0.06);
  --shadow-lg:  0 16px 48px rgba(0,0,0,0.12), 0 4px 16px rgba(0,0,0,0.08);
  --shadow-sidebar: 4px 0 24px rgba(0,0,0,0.18);

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
  position: relative;
}

/* ── SIDEBAR ──────────────────────────────────────────────── */
/* ── SIDEBAR — Light EIU Brand Theme ─────────────────────── */
#eiu-rv2 .rv2-sidebar {
  width: 280px;
  flex-shrink: 0;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 10;
  border-right: 1px solid var(--sidebar-border);
  box-shadow: 2px 0 12px rgba(26,73,136,0.06);
  overflow: hidden;
}
#eiu-rv2 .rv2-sidebar::before,
#eiu-rv2 .rv2-sidebar::after { display: none; }

/* Brand strip — EIU blue header */
#eiu-rv2 .rv2-brand {
  padding: 0;
  border-bottom: 1px solid var(--sidebar-border);
}
#eiu-rv2 .rv2-brand > div {
  background: var(--sidebar-blue);
  padding: 22px 24px;
  display: flex;
  align-items: center;
  gap: 13px;
}
#eiu-rv2 .rv2-brand-icon {
  width: 40px; height: 40px;
  border-radius: var(--radius-sm);
  background: rgba(255,255,255,0.15);
  border: 1.5px solid rgba(255,255,255,0.25);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background 0.2s;
}
#eiu-rv2 .rv2-brand-icon:hover { background: rgba(255,255,255,0.22); }
#eiu-rv2 .rv2-brand-name {
  font-family: var(--font-display);
  font-size: 15px; font-weight: 800;
  color: #ffffff; line-height: 1.2;
  letter-spacing: -0.01em;
}
#eiu-rv2 .rv2-brand-role {
  font-size: 11px; font-weight: 500;
  color: rgba(255,255,255,0.6);
  margin-top: 2px; letter-spacing: 0.02em;
}

/* User chip — on white */
#eiu-rv2 .rv2-user {
  padding: 16px 20px;
  border-bottom: 1px solid var(--sidebar-border);
  display: flex; align-items: center; gap: 12px;
  background: #fafbff;
}
#eiu-rv2 .rv2-av {
  width: 42px; height: 42px;
  border-radius: 50%; flex-shrink: 0;
  background: var(--sidebar-blue);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display);
  font-size: 17px; font-weight: 800; color: #fff;
  border: 2px solid #c8d9f0;
  box-shadow: 0 2px 8px rgba(26,73,136,0.18);
}
#eiu-rv2 .rv2-uname {
  color: #1a2535; font-size: 14px; font-weight: 700;
  margin: 0; line-height: 1.3;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 170px;
}
#eiu-rv2 .rv2-uemail {
  color: #64748b; font-size: 11px; margin: 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 170px;
}
#eiu-rv2 .rv2-verified {
  display: inline-flex; align-items: center; gap: 4px;
  background: #ecfdf5; border: 1px solid #a7f3d0;
  border-radius: 20px; padding: 2px 8px;
  font-size: 10px; font-weight: 700;
  color: #059669; margin-top: 3px;
}

/* Navigation — clean light */
#eiu-rv2 .rv2-nav {
  flex: 1; padding: 10px 0;
  overflow-y: auto; scrollbar-width: none;
}
#eiu-rv2 .rv2-nav::-webkit-scrollbar { display: none; }

#eiu-rv2 .rv2-nav-section {
  padding: 14px 20px 5px;
  font-size: 9px; font-weight: 800;
  letter-spacing: 0.1em; text-transform: uppercase;
  color: var(--sidebar-text-muted, #94a3b8);
}
#eiu-rv2 .rv2-nav a {
  display: flex; align-items: center; gap: 11px;
  padding: 11px 20px;
  font-size: 14px; font-weight: 500;
  color: var(--sidebar-text);
  text-decoration: none;
  border-left: 3px solid transparent;
  border-radius: 0;
  transition: all 0.15s ease;
  position: relative;
  margin: 1px 0;
}
#eiu-rv2 .rv2-nav a:hover {
  color: var(--sidebar-blue);
  background: var(--sidebar-hover-bg);
}
#eiu-rv2 .rv2-nav a.active {
  color: var(--sidebar-active-txt);
  background: var(--sidebar-active-bg);
  border-left-color: var(--sidebar-blue);
  font-weight: 700;
}
#eiu-rv2 .rv2-nav a i {
  font-size: 16px; flex-shrink: 0;
  width: 20px; text-align: center;
  color: #94a3b8;
  transition: color 0.15s;
}
#eiu-rv2 .rv2-nav a:hover i { color: var(--sidebar-blue); }
#eiu-rv2 .rv2-nav a.active i { color: var(--sidebar-blue); }

#eiu-rv2 .rv2-badge {
  margin-left: auto;
  background: var(--sidebar-accent);
  color: #fff;
  border-radius: 20px; padding: 2px 8px;
  font-size: 10px; font-weight: 800; line-height: 1.6;
}
#eiu-rv2 .rv2-badge.green { background: #059669; }

/* Submit highlight — red accent */
#eiu-rv2 .rv2-nav a.rv2-submit-link {
  margin: 8px 14px;
  border-radius: var(--radius-sm);
  border-left: none;
  padding: 10px 16px;
  background: #fff5f5;
  border: 1.5px solid #fecaca;
  color: var(--sidebar-accent);
  font-weight: 700;
}
#eiu-rv2 .rv2-nav a.rv2-submit-link:hover {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #7f1d1d;
}
#eiu-rv2 .rv2-nav a.rv2-submit-link.active {
  background: #fee2e2;
  border-color: var(--sidebar-accent);
  color: #7f1d1d;
}
#eiu-rv2 .rv2-nav a.rv2-submit-link i { color: var(--sidebar-accent); }

/* Sidebar footer — dark, high-contrast sign-out */
#eiu-rv2 .rv2-footer {
  padding: 0;
  border-top: 2px solid rgba(0,0,0,0.15);
  background: #0a1628;
  flex-shrink: 0;
}
#eiu-rv2 .rv2-footer a {
  display: flex; align-items: center; gap: 10px;
  padding: 16px 22px;
  font-size: 13px; font-weight: 700;
  color: #f1f5f9; text-decoration: none;
  transition: background 0.18s ease, color 0.18s ease;
  letter-spacing: 0.02em;
}
#eiu-rv2 .rv2-footer a:hover { background: #990000; color: #ffffff; }
#eiu-rv2 .rv2-footer a:active { background: #720000; color: #ffffff; }
#eiu-rv2 .rv2-footer a i { font-size: 16px; opacity: 1; }

/* ── MAIN ─────────────────────────────────────────────────── */
#eiu-rv2 .rv2-main {
  flex: 1; min-width: 0;
  display: flex; flex-direction: column;
  background: var(--bg);
}

/* Topbar */
#eiu-rv2 .rv2-topbar {
  background: var(--surface);
  padding: 20px 36px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  justify-content: space-between; gap: 16px; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 5;
  box-shadow: 0 1px 0 var(--border);
}
#eiu-rv2 .rv2-topbar-title {
  font-family: var(--font-display);
  font-size: 20px; font-weight: 800;
  color: var(--text-primary); margin: 0;
  letter-spacing: -0.02em;
}
#eiu-rv2 .rv2-topbar-stats { display: flex; gap: 6px; }
#eiu-rv2 .rv2-stat-pill {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 18px;
  border-radius: var(--radius-sm);
  background: var(--surface-2);
  border: 1px solid var(--border);
  min-width: 72px;
  transition: box-shadow 0.15s;
}
#eiu-rv2 .rv2-stat-pill:hover { box-shadow: var(--shadow-sm); }
#eiu-rv2 .rv2-stat-num {
  font-family: var(--font-display);
  font-size: 22px; font-weight: 900; line-height: 1;
}
#eiu-rv2 .rv2-stat-lbl {
  font-size: 10px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--text-muted); margin-top: 2px;
}

/* Scrollable body */
#eiu-rv2 .rv2-body {
  flex: 1; overflow-y: auto;
  padding: 36px 36px 48px;
}

/* ── CARDS ──────────────────────────────────────────────── */
#eiu-rv2 .rv2-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  margin-bottom: 20px;
  overflow: hidden;
  transition: box-shadow 0.2s ease;
}
#eiu-rv2 .rv2-card:hover { box-shadow: var(--shadow-md); }
/* ── ARTICLE CONTENT TABS ─────────────────────────────── */
/* ── ARTICLE CONTENT TABS — clean typographic style, no fills ─── */
#eiu-rv2 .rv2-art-tabs {
  display: flex; align-items: stretch; gap: 0;
  border-bottom: 1.5px solid #e0e6ef;
  margin: 0; padding: 0 8px;
  overflow-x: auto; scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  background: transparent;
}
#eiu-rv2 .rv2-art-tabs::-webkit-scrollbar { display: none; }

#eiu-rv2 .rv2-art-tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 13px 16px 11px;
  font-size: 13px; font-weight: 500;
  color: #8494a9;
  border: none; background: transparent;
  cursor: pointer; white-space: nowrap;
  border-bottom: 2px solid transparent;
  margin-bottom: -1.5px;
  transition: color .18s ease, border-color .18s ease;
  letter-spacing: .01em; flex-shrink: 0;
}

/* Hover: text darkens slightly, no background */
#eiu-rv2 .rv2-art-tab:hover {
  color: #2d4a6e;
  background: transparent;
  border-bottom-color: transparent;
}

/* Active: dark navy text + single fine underline — no fill at all */
#eiu-rv2 .rv2-art-tab.active {
  color: #1a3558;
  font-weight: 600;
  background: transparent;
  border-bottom-color: #1a4988;
}

#eiu-rv2 .rv2-art-tab.active i { color: #1a4988; }
#eiu-rv2 .rv2-art-tab i { font-size: 14px; color: currentColor; transition: color .18s ease; }

#eiu-rv2 .rv2-art-tab:focus-visible {
  outline: 2px solid #1a4988;
  outline-offset: -2px;
  border-radius: 4px 4px 0 0;
}

#eiu-rv2 .rv2-tab-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 18px; height: 18px; padding: 0 5px;
  background: #eef2f8; color: #5a6a82; border-radius: 9px;
  font-size: 10px; font-weight: 700; letter-spacing: 0;
  transition: background .18s ease, color .18s ease;
}
#eiu-rv2 .rv2-art-tab.active .rv2-tab-badge {
  background: #e0e9f7; color: #1a4988;
}

#eiu-rv2 .rv2-tab-panel { display: none; }
#eiu-rv2 .rv2-tab-panel.active {
  display: block;
  animation: rv2PanelFade .16s ease both;
}
@keyframes rv2PanelFade {
  from { opacity: 0; transform: translateY(3px); }
  to   { opacity: 1; transform: none; }
}

/* ── MOBILE TAB STRIP (≤ 640px) ─────────────────────────────── */
@media(max-width: 640px) {
  #eiu-rv2 .rv2-art-tabs {
    gap: 2px; border-bottom: none; padding: 4px;
    background: #f2f5f9;
    border-radius: 10px;
    border: 1px solid #dde4ee;
    margin: 0 0 0;
  }
  #eiu-rv2 .rv2-art-tab {
    flex: 1; min-width: 0; justify-content: center;
    padding: 8px 6px; flex-direction: column; gap: 3px;
    border-bottom: none; border-radius: 7px; margin-bottom: 0;
    color: #8494a9; background: transparent;
  }
  #eiu-rv2 .rv2-art-tab i { font-size: 16px; }
  #eiu-rv2 .rv2-art-tab .rv2-tab-label { font-size: 10px; font-weight: 600; letter-spacing: .01em; }
  #eiu-rv2 .rv2-art-tab:hover {
    color: #2d4a6e; background: transparent;
  }
  /* Mobile active: white lifted card — still no blue fill */
  #eiu-rv2 .rv2-art-tab.active {
    background: #ffffff;
    color: #1a3558;
    font-weight: 600;
    border-bottom: none;
    box-shadow: 0 1px 4px rgba(0,0,0,.10);
  }
  #eiu-rv2 .rv2-art-tab.active i { color: #1a4988; }
  #eiu-rv2 .rv2-tab-badge { display: none; }
}

#eiu-rv2 .rv2-card-head {
  padding: 20px 28px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center;
  justify-content: space-between; gap: 12px; flex-wrap: wrap;
  background: linear-gradient(to right, #fdfdff, var(--surface));
}
#eiu-rv2 .rv2-card-title {
  font-family: var(--font-display);
  font-size: 15px; font-weight: 800;
  color: var(--text-primary); margin: 0;
  display: flex; align-items: center; gap: 10px;
  letter-spacing: -0.01em;
}
#eiu-rv2 .rv2-card-body { padding: 28px; }

/* ── KPI GRID ───────────────────────────────────────────── */
#eiu-rv2 .rv2-stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 28px;
}
#eiu-rv2 .rv2-kpi {
  background: var(--surface);
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  padding: 24px 22px;
  display: flex; align-items: flex-start; gap: 16px;
  box-shadow: var(--shadow-xs);
  transition: all 0.2s ease;
  cursor: default;
}
#eiu-rv2 .rv2-kpi:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}
#eiu-rv2 .rv2-kpi-icon {
  width: 50px; height: 50px;
  border-radius: var(--radius-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; color: #fff; flex-shrink: 0;
}
#eiu-rv2 .rv2-kpi-num {
  font-family: var(--font-display);
  font-size: 32px; font-weight: 900; line-height: 1;
  letter-spacing: -0.03em;
}
#eiu-rv2 .rv2-kpi-lbl {
  font-size: 12px; font-weight: 500;
  color: var(--text-muted);
  margin-top: 5px; letter-spacing: 0.01em;
}

/* ── ARTICLE ROWS ───────────────────────────────────────── */
#eiu-rv2 .rv2-art-row {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 20px 24px;
  margin-bottom: 12px;
  display: flex; align-items: flex-start;
  justify-content: space-between; gap: 16px;
  transition: all 0.18s ease;
  flex-wrap: wrap;
}
#eiu-rv2 .rv2-art-row:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-1px);
  border-color: #dde4f0;
}
#eiu-rv2 .rv2-art-title {
  font-size: 15px; font-weight: 600;
  color: var(--text-primary); margin: 0 0 6px;
  line-height: 1.45;
}
#eiu-rv2 .rv2-art-meta {
  font-size: 13px; color: var(--text-muted);
}

/* ── STATUS PILLS ───────────────────────────────────────── */
#eiu-rv2 .rv2-pill {
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 20px; padding: 4px 12px;
  font-size: 12px; font-weight: 600; white-space: nowrap;
  letter-spacing: 0.01em;
}
#eiu-rv2 .rv2-pill.pill-assigned,
#eiu-rv2 .rv2-pill.pill-in_progress { background: var(--amber-light); color: #92400e; border: 1px solid #fde68a; }
#eiu-rv2 .rv2-pill.pill-submitted    { background: var(--violet-light); color: #5b21b6; border: 1px solid #ddd6fe; }
#eiu-rv2 .rv2-pill.pill-approved     { background: var(--emerald-light); color: #065f46; border: 1px solid #a7f3d0; }
#eiu-rv2 .rv2-pill.pill-rejected     { background: var(--coral-light); color: #9a0805; border: 1px solid #fecaca; }

/* ── BUTTONS ────────────────────────────────────────────── */
#eiu-rv2 .rv2-btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 18px;
  border-radius: var(--radius-sm);
  font-family: var(--font-body);
  font-size: 13px; font-weight: 600;
  cursor: pointer; text-decoration: none;
  transition: all 0.16s ease; border: none;
  white-space: nowrap; letter-spacing: 0.01em;
}
#eiu-rv2 .rv2-btn-primary {
  background: var(--blue); color: #fff;
  box-shadow: 0 2px 8px rgba(26,73,136,0.28);
}
#eiu-rv2 .rv2-btn-primary:hover {
  background: #123266; color: #fff;
  box-shadow: 0 4px 14px rgba(26,73,136,0.38);
  transform: translateY(-1px); text-decoration: none;
}
#eiu-rv2 .rv2-btn-accent {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #0a1628; font-weight: 700;
  box-shadow: 0 2px 8px rgba(245,158,11,0.3);
}
#eiu-rv2 .rv2-btn-accent:hover {
  opacity: 0.92; color: #0a1628; text-decoration: none;
  transform: translateY(-1px); box-shadow: 0 4px 14px rgba(245,158,11,0.4);
}
#eiu-rv2 .rv2-btn-ghost {
  background: transparent; color: var(--blue);
  border: 1.5px solid var(--border);
}
#eiu-rv2 .rv2-btn-ghost:hover {
  background: var(--blue); color: #fff;
  border-color: var(--blue);
}
#eiu-rv2 .rv2-btn-sm { padding: 6px 14px; font-size: 12px; }

/* ── NOTICES ────────────────────────────────────────────── */
#eiu-rv2 .rv2-notice {
  border-radius: var(--radius-sm);
  padding: 14px 18px; font-size: 14px;
  margin-bottom: 20px; display: none;
  font-weight: 500;
}
#eiu-rv2 .rv2-notice-ok  { background: var(--emerald-light); color: #065f46; border: 1px solid #a7f3d0; }
#eiu-rv2 .rv2-notice-err { background: var(--coral-light); color: #9a0805; border: 1px solid #fecaca; }

/* ── FORM ELEMENTS ──────────────────────────────────────── */
#eiu-rv2 .form-control:focus {
  border-color: var(--border-focus);
  box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
#eiu-rv2 .rv2-label {
  font-size: 13px; font-weight: 600;
  color: var(--text-secondary); margin-bottom: 6px; display: block;
}
#eiu-rv2 .rv2-radio-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0; }
#eiu-rv2 .rv2-radio-opt input { position: absolute; opacity: 0; width: 0; height: 0; }
#eiu-rv2 .rv2-radio-box {
  display: inline-flex; align-items: center;
  padding: 8px 16px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  font-size: 13px; font-weight: 500; color: var(--text-secondary);
  background: var(--surface); cursor: pointer;
  transition: all 0.15s;
}
#eiu-rv2 .rv2-radio-opt:has(input:checked) .rv2-radio-box {
  background: var(--blue); color: #fff; border-color: var(--blue);
  box-shadow: 0 2px 8px rgba(26,73,136,0.25);
}
#eiu-rv2 .rv2-radio-opt:hover .rv2-radio-box { border-color: var(--blue); }

/* ── REVIEW BOX ─────────────────────────────────────────── */
#eiu-rv2 .rv2-review-box {
  background: linear-gradient(to bottom right, #f8faff, #f1f5fd);
  border: 1px solid #dce6ff; border-radius: var(--radius-md);
  padding: 24px 28px; margin-top: 20px;
}
#eiu-rv2 .rv2-review-box h4 {
  font-family: var(--font-display);
  font-size: 13px; font-weight: 800;
  color: var(--blue);
  text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 18px;
}
/* rv2-review-ta removed v2.1 — replaced by TinyMCE WYSIWYG editor */
#eiu-rv2 .rv2-submit-review {
  background: var(--blue); color: #fff; border: none;
  border-radius: var(--radius-sm);
  padding: 11px 24px; font-family: var(--font-body);
  font-size: 14px; font-weight: 700; cursor: pointer;
  display: inline-flex; align-items: center; gap: 7px;
  transition: all 0.16s;
  box-shadow: 0 2px 8px rgba(26,73,136,0.25);
}
#eiu-rv2 .rv2-submit-review:hover {
  background: #123266;
  box-shadow: 0 4px 14px rgba(26,73,136,0.35);
  transform: translateY(-1px);
}
#eiu-rv2 .rv2-submit-review:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* ── Editor wrap ─────────────────────────────────────────── */
#eiu-rv2 .rv2-editor-wrap { border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
#eiu-rv2 .rsd-editor-wrap { border: 1.5px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
#eiu-rv2 .rv2-editor-wrap .wp-editor-container,
#eiu-rv2 .rv2-editor-wrap .mce-tinymce { border: none !important; box-shadow: none !important; }
@media(max-width:600px){
  #eiu-rv2 .rv2-editor-wrap .mce-toolbar-grp { overflow-x: auto; }
  .mce-menu { max-width: 90vw !important; }
}

/* ── META TABLE ─────────────────────────────────────────── */
#eiu-rv2 .rv2-meta-table { width: 100%; border-collapse: collapse; font-size: 14px; }
#eiu-rv2 .rv2-meta-table th {
  text-align: left; padding: 12px 20px 12px 0;
  color: var(--text-muted); font-weight: 600;
  font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;
  border-bottom: 1px solid var(--border); width: 180px; vertical-align: top;
}
#eiu-rv2 .rv2-meta-table td {
  padding: 12px 0;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border);
  line-height: 1.6;
}
#eiu-rv2 .rv2-meta-table tr:last-child th,
#eiu-rv2 .rv2-meta-table tr:last-child td { border-bottom: none; }

/* ── ABSTRACT BOX ───────────────────────────────────────── */
#eiu-rv2 .rv2-abstract {
  background: transparent;
  border-left: none;
  padding: 0;
  border-radius: 0;
  line-height: 1.85; font-size: 15px; color: var(--text-secondary);
  margin: 0;
}
#eiu-rv2 .rv2-abstract p:first-child { margin-top: 0; }
#eiu-rv2 .rv2-abstract p:last-child  { margin-bottom: 0; }
#eiu-rv2 .rv2-abstract p:empty,
#eiu-rv2 .rv2-abstract br:first-child { display: none; }

/* ── REVIEWER DIRECTORY ─────────────────────────────────── */
#eiu-rv2 .rv2-reviewer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}
#eiu-rv2 .rv2-reviewer-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 20px;
  display: flex; align-items: flex-start; gap: 14px;
  transition: all 0.18s ease;
}
#eiu-rv2 .rv2-reviewer-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
#eiu-rv2 .rv2-r-av {
  width: 42px; height: 42px; border-radius: 50%;
  background: linear-gradient(135deg, var(--blue), #2563eb);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-size: 16px;
  font-weight: 800; color: #fff; flex-shrink: 0;
}

/* ── EMPTY STATE ────────────────────────────────────────── */
#eiu-rv2 .rv2-empty {
  text-align: center; padding: 64px 24px;
  background: var(--surface);
  border-radius: var(--radius-lg);
  border: 2px dashed var(--border);
  color: var(--text-muted);
}
#eiu-rv2 .rv2-empty i { font-size: 3.2rem; display: block; margin-bottom: 16px; opacity: 0.3; }

/* ── ANIMATION ──────────────────────────────────────────── */
@keyframes rv2-fadeup {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: none; }
}
#eiu-rv2 .rv2-body > * {
  animation: rv2-fadeup 0.3s ease both;
}
#eiu-rv2 .rv2-body > *:nth-child(1) { animation-delay: 0.04s; }
#eiu-rv2 .rv2-body > *:nth-child(2) { animation-delay: 0.08s; }
#eiu-rv2 .rv2-body > *:nth-child(3) { animation-delay: 0.12s; }
#eiu-rv2 .rv2-body > *:nth-child(4) { animation-delay: 0.16s; }
#eiu-rv2 .rv2-kpi { animation: rv2-fadeup 0.3s ease both; }
#eiu-rv2 .rv2-kpi:nth-child(1) { animation-delay: 0.05s; }
#eiu-rv2 .rv2-kpi:nth-child(2) { animation-delay: 0.10s; }
#eiu-rv2 .rv2-kpi:nth-child(3) { animation-delay: 0.15s; }
#eiu-rv2 .rv2-kpi:nth-child(4) { animation-delay: 0.20s; }

/* ── RESPONSIVE ─────────────────────────────────────────── */
@media(max-width: 1024px) {
  #eiu-rv2 .rv2-stat-grid { grid-template-columns: repeat(2, 1fr); }
  #eiu-rv2 .rv2-sidebar { width: 240px; }
  #eiu-rv2 .rv2-body { padding: 28px 28px 40px; }
}
@media(max-width: 768px) {
  #eiu-rv2 { flex-direction: column; border-radius: var(--radius-md); min-height: auto; }
  #eiu-rv2 .rv2-sidebar { width: 100%; overflow: visible; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
  #eiu-rv2 .rv2-nav {
    display: flex; flex-direction: row;
    overflow-x: auto; padding: 6px 10px;
    flex: none; border-top: 1px solid var(--sidebar-border);
    scrollbar-width: none; gap: 4px;
  }
  #eiu-rv2 .rv2-nav::-webkit-scrollbar { display: none; }
  #eiu-rv2 .rv2-nav-section { display: none; }
  #eiu-rv2 .rv2-nav a {
    flex-direction: column; gap: 4px;
    padding: 8px 12px;
    border-left: none; border-bottom: 3px solid transparent;
    border-radius: var(--radius-sm);
    font-size: 11px; text-align: center;
    flex-shrink: 0; min-width: 60px;
  }
  #eiu-rv2 .rv2-nav a i { width: auto; font-size: 17px; }
  #eiu-rv2 .rv2-nav a.active { border-left: none; border-bottom-color: var(--sidebar-accent); }
  #eiu-rv2 .rv2-nav a.rv2-submit-link { margin: 4px; padding: 8px 10px; border: none; }
  #eiu-rv2 .rv2-sidebar::before, #eiu-rv2 .rv2-sidebar::after { display: none; }
  #eiu-rv2 .rv2-topbar { padding: 16px 20px; }
  #eiu-rv2 .rv2-body { padding: 20px 16px 32px; }
  #eiu-rv2 .rv2-card-body { padding: 20px; }
  #eiu-rv2 .rv2-card-head { padding: 16px 20px; }
  #eiu-rv2 .rv2-stat-grid { grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
  #eiu-rv2 .rv2-kpi { padding: 18px 16px; gap: 12px; }
  #eiu-rv2 .rv2-topbar-stats { display: none; }
  #eiu-rv2 .rv2-user { display: none; }
  #eiu-rv2 .rv2-footer { display: block; }
  #eiu-rv2 .rv2-footer a { padding: 13px 16px; font-size: 12px; }
  #eiu-rv2 .rv2-meta-table th, #eiu-rv2 .rv2-meta-table td { display: block; width: 100%; }
  #eiu-rv2 .rv2-meta-table th { padding: 10px 0 2px; border-bottom: none; font-size: 11px; }
  #eiu-rv2 .rv2-meta-table td { padding: 0 0 10px; }
  #eiu-rv2 .rv2-meta-table { display: block; }
  #eiu-rv2 .rv2-meta-table tbody, #eiu-rv2 .rv2-meta-table tr { display: block; }
  #eiu-rv2 .rv2-reviewer-grid { grid-template-columns: 1fr 1fr; }
}
@media(max-width: 480px) {
  #eiu-rv2 .rv2-stat-grid { grid-template-columns: 1fr 1fr; }
  #eiu-rv2 .rv2-kpi-num { font-size: 26px; }
  #eiu-rv2 .rv2-reviewer-grid { grid-template-columns: 1fr; }
  #eiu-rv2 .rv2-brand > div { padding: 16px 14px 14px; }
}

/* ── REVIEWER MOBILE FULL OPTIMIZATION ─────────────── */
@media(max-width: 768px) {
  #eiu-rv2 { flex-direction: column; min-height: 100vh; border-radius: 0; }
  #eiu-rv2 .rv2-sb { width: 100%; position: sticky; top: 0; z-index: 100; }
  #eiu-rv2 .rv2-nav {
    display: flex; flex-direction: row; overflow-x: auto; -webkit-overflow-scrolling: touch;
    padding: 8px 12px; gap: 6px; border-top: 1px solid rgba(255,255,255,.1);
    scrollbar-width: none; flex-wrap: nowrap;
  }
  #eiu-rv2 .rv2-nav::-webkit-scrollbar { display: none; }
  #eiu-rv2 .rv2-nav a {
    flex-direction: column; gap: 3px; padding: 8px 14px; border-left: none;
    border-bottom: 3px solid transparent; border-radius: 8px;
    font-size: 11px; text-align: center; flex-shrink: 0;
    min-width: 64px; white-space: nowrap;
  }
  #eiu-rv2 .rv2-nav a i { font-size: 18px; width: auto; }
  #eiu-rv2 .rv2-nav a.active { border-left: none; border-bottom-color: var(--rv-accent); }
  #eiu-rv2 .rv2-body { padding: 16px 14px 32px; }
  #eiu-rv2 .rv2-card-body { padding: 16px; }
  #eiu-rv2 .rv2-card-head { padding: 14px 16px; flex-wrap: wrap; gap: 8px; }
  /* Article list: stack rows */
  #eiu-rv2 .rv2-art-row { flex-direction: column; gap: 10px; padding: 14px 16px; }
  #eiu-rv2 .rv2-art-row > div:last-child { display: flex; gap: 8px; flex-wrap: wrap; }
  /* Buttons: touch-friendly */
  #eiu-rv2 .rv2-btn { min-height: 44px; padding: 10px 16px; }
  /* Form inputs: prevent iOS zoom */
  #eiu-rv2 .form-control, #eiu-rv2 .form-select { font-size: 16px; }
  /* Co-reviewer rows: stack on mobile */
  #eiu-rv2 .rv-co-row { flex-wrap: wrap; gap: 8px; }
  #eiu-rv2 .rv-co-row .rv-co-badge { margin-left: 0; }
  /* Status controls: stack */
  #eiu-rv2 [style*='display:flex;align-items:flex-end'] { flex-direction: column; align-items: stretch !important; }
  /* Hide sidebar user info */
  #eiu-rv2 .rv2-user { display: none; }
  #eiu-rv2 .rv2-nav-sec { display: none; }
  /* Article detail: full width */
  #eiu-rv2 .rv2-art-detail { flex-direction: column; }
}
@media(max-width: 400px) {
  #eiu-rv2 .rv2-body { padding: 12px 10px 28px; }
  #eiu-rv2 .rv2-card-body { padding: 12px; }
  #eiu-rv2 .rv2-nav a { min-width: 56px; padding: 6px 10px; }
  #eiu-rv2 .rv2-btn { font-size: 13px; padding: 9px 14px; }
}
</style>

<div id="eiu-rv2">

  <!-- ── SIDEBAR ─────────────────────────────────────────── -->
  <aside class="rv2-sidebar">

    <!-- Brand -->
    <div class="rv2-brand">
      <div>
        <div class="rv2-brand-icon"><i class="bi bi-journal-richtext" style="color:#fff;font-size:18px;"></i></div>
        <div>
          <div class="rv2-brand-name"><?php echo esc_html( get_option('eiu_rp_term_system_name','EIU JOURNAL SYSTEM') ); ?></div>
          <div class="rv2-brand-role"><?php Terminology::e('reviewer_portal'); ?></div>
        </div>
      </div>
    </div>

    <!-- User -->
    <div class="rv2-user">
      <div class="rv2-av" id="rv2-sidebar-av" style="<?php echo $rv_profile_photo_url ? 'background:none;padding:0;overflow:hidden;' : ''; ?>">
        <?php if ( $rv_profile_photo_url ): ?>
          <img src="<?php echo esc_url($rv_profile_photo_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        <?php else: ?>
          <?php echo esc_html(strtoupper(substr($reviewer->full_name,0,1))); ?>
        <?php endif; ?>
      </div>
      <div style="min-width:0;">
        <p class="rv2-uname"><?php echo esc_html($reviewer->full_name); ?></p>
        <p class="rv2-uemail"><?php echo esc_html($reviewer->email); ?></p>
        <?php if ($reviewer->verified): ?>
          <span class="rv2-verified"><i class="bi bi-patch-check-fill"></i><?php Terminology::e('verified'); ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="rv2-nav">
      <div class="rv2-nav-section"><?php esc_html_e('Main','eiu-rp'); ?></div>
      <?php
      $nav_items = [
        'dashboard'    => ['bi-speedometer2',       __('Dashboard','eiu-rp'),          0],
        'articles'     => ['bi-file-earmark-text',   __('Assigned Articles','eiu-rp'),  count($reviews)],
        'reviews'      => ['bi-pencil-square',        __('My Reviews','eiu-rp'),         count($pending)],
        'applications' => ['bi-people-fill',           __('Applications','eiu-rp'),       count($reviewer_apps)],
      ];
      foreach ($nav_items as $slug => [$icon, $label, $cnt]):
        $href = add_query_arg('tab', $slug, $page_url);
        $active = ($tab === $slug);
      ?>
        <a href="<?php echo esc_url($href); ?>" class="<?php echo $active ? 'active' : ''; ?>" style="position:relative;">
          <i class="bi <?php echo esc_attr($icon); ?>"></i>
          <?php echo esc_html($label); ?>
          <?php if ($cnt > 0): ?><span class="rv2-badge"><?php echo esc_html($cnt); ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>

      <div class="rv2-nav-section" style="margin-top:6px;"><?php esc_html_e('Actions','eiu-rp'); ?></div>

      <?php $submit_href = add_query_arg('tab','submit',$page_url); ?>
      <a href="<?php echo esc_url($submit_href); ?>" class="rv2-submit-link<?php echo $tab==='submit'?' active':''; ?>">
        <i class="bi bi-plus-circle-fill"></i>
        <?php echo esc_html( get_option('eiu_rp_term_submit_manuscript','Submit Manuscript') ); ?>
      </a>

      <div class="rv2-nav-section" style="margin-top:6px;"><?php esc_html_e('More','eiu-rp'); ?></div>
      <?php
      $more_items = [
        'reviewers' => ['bi-people',      __('Reviewers','eiu-rp'),  0],
        'profile'   => ['bi-person-gear', __('Profile','eiu-rp'),    0],
      ];
      foreach ($more_items as $slug => [$icon, $label, $cnt]):
        $href = add_query_arg('tab', $slug, $page_url);
      ?>
        <a href="<?php echo esc_url($href); ?>" class="<?php echo $tab===$slug ? 'active' : ''; ?>">
          <i class="bi <?php echo esc_attr($icon); ?>"></i>
          <?php echo esc_html($label); ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Footer — dark sign-out -->
    <div class="rv2-footer">
      <a href="<?php echo esc_url($logout_url); ?>">
        <i class="bi bi-box-arrow-right"></i>
        <span><?php Terminology::e('sign_out'); ?></span>
      </a>
    </div>

  </aside>

  <!-- ── MAIN ─────────────────────────────────────────────── -->
  <div class="rv2-main">

    <!-- Topbar -->
    <div class="rv2-topbar">
      <h1 class="rv2-topbar-title">
        <?php
        $page_titles = [
          'dashboard'    => Terminology::get('overview'),
          'articles'     => $article_id ? __('Article Detail','eiu-rp') : Terminology::get('assigned_articles'),
          'reviews'      => __('My Reviews','eiu-rp'),
          'applications' => __('Applications','eiu-rp'),
          'submit'    => get_option('eiu_rp_term_submit_manuscript',__('Submit Manuscript','eiu-rp')),
          'reviewers' => Terminology::get('reviewer_directory'),
          'profile'   => Terminology::get('profile_settings'),
        ];
        echo esc_html($page_titles[$tab] ?? __('Overview','eiu-rp'));
        ?>
      </h1>
      <div class="rv2-topbar-stats">
        <div class="rv2-stat-pill">
          <span class="rv2-stat-num" style="color:var(--c-accent2);"><?php echo esc_html(count($reviews)); ?></span>
          <span class="rv2-stat-lbl"><?php esc_html_e('Total','eiu-rp'); ?></span>
        </div>
        <div class="rv2-stat-pill">
          <span class="rv2-stat-num" style="color:var(--c-amber);"><?php echo esc_html(count($pending)); ?></span>
          <span class="rv2-stat-lbl"><?php Terminology::e('pending'); ?></span>
        </div>
        <div class="rv2-stat-pill">
          <span class="rv2-stat-num" style="color:var(--c-green);"><?php echo esc_html(count($submitted)); ?></span>
          <span class="rv2-stat-lbl"><?php esc_html_e('Done','eiu-rp'); ?></span>
        </div>
      </div>
    </div>

    <!-- Content Body -->
    <div class="rv2-body">

      <!-- Global notices -->
      <div class="rv2-notice rv2-notice-ok"  id="eiu-rv-ok"></div>
      <div class="rv2-notice rv2-notice-err" id="eiu-rv-err"></div>

<?php
/* ═══ ARTICLE FULL DETAIL ═══════════════════════════════ */
if ( $tab === 'articles' && $article_id ):
  $view_rev = null;
  foreach ($reviews as $r) {
    if ((int)($r['article_id']??0)===$article_id) { $view_rev=$r; break; }
  }
  if (!$view_rev): ?>
    <p style="color:#991b1b;"><?php esc_html_e('Article not found or not assigned to you.','eiu-rp'); ?></p>
  <?php else:
    $art      = \EIU_RP\Models\Article::get($article_id);
    $post_id  = (int)($view_rev['post_id']??0);
    $abstract = $post_id ? get_post_meta($post_id,'_eiu_abstract',true) : '';
    if (!$abstract && $art) $abstract = $art->abstract??'';
    $art_body = $post_id ? get_post_meta($post_id,'_eiu_article_content',true) : '';
    $refs     = $post_id ? get_post_meta($post_id,'_eiu_references',true) : '';
    $dl_url   = '';
    if ($art && !empty($art->file_path) && file_exists($art->file_path)) {
      $dl_url = wp_nonce_url(
        add_query_arg(['eiu_rp_reviewer_download'=>1,'article_id'=>$article_id], admin_url('admin-ajax.php')),
        'eiu_rp_reviewer_dl_'.$article_id
      );
    }
  ?>
    <a href="<?php echo esc_url(add_query_arg('tab','articles',$page_url)); ?>" class="rv2-btn rv2-btn-ghost rv2-btn-sm" style="margin-bottom:16px;">
      <i class="bi bi-arrow-left"></i><?php Terminology::e('back_to_articles'); ?>
    </a>

    <div class="rv2-card" style="margin-bottom:16px;">
      <div style="background:linear-gradient(135deg,#f8f9ff,#eef2ff);padding:20px;border-radius:12px 12px 0 0;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
          <div>
            <h2 style="font-family:'Cabinet Grotesk',sans-serif;font-size:18px;font-weight:800;margin:0 0 8px;color:var(--c-text);"><?php echo esc_html($art->title??''); ?></h2>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <?php
              $s = $view_rev['status']??'assigned';
              $pill_map = [
                'assigned'=>'pill-assigned','in_progress'=>'pill-in_progress',
                'submitted'=>'pill-submitted','approved'=>'pill-approved','rejected'=>'pill-rejected',
              ];
              ?>
              <span class="rv2-pill <?php echo esc_attr($pill_map[$s]??'pill-assigned'); ?>">
                <?php echo esc_html(ucwords(str_replace('_',' ',$s))); ?>
              </span>
              <?php if ($art && !empty($art->subject)): ?>
                <span style="font-size:12px;background:#e8eef8;color:#1a4988;border-radius:20px;padding:3px 10px;font-weight:600;"><?php echo esc_html($art->subject); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($dl_url): ?>
            <a href="<?php echo esc_url($dl_url); ?>" class="rv2-btn rv2-btn-accent">
              <i class="bi bi-download"></i><?php Terminology::e('download_file'); ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
      <div style="padding:20px;">
        <table class="rv2-meta-table">
          <?php if ($art && !empty($art->author_name)): ?>
            <tr><th><?php esc_html_e('Author','eiu-rp'); ?></th><td><?php echo esc_html($art->author_name); ?><?php if(!empty($art->author_email)) echo ' &lt;' . esc_html($art->author_email) . '&gt;'; ?></td></tr>
          <?php endif; ?>
          <?php if ($art && !empty($art->coauthor_name)): ?>
            <tr><th><?php esc_html_e('Co-Author','eiu-rp'); ?></th><td><?php echo esc_html($art->coauthor_name); ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($view_rev['assigned_at'])): ?>
            <tr><th><?php esc_html_e('Assigned','eiu-rp'); ?></th><td><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($view_rev['assigned_at']))); ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($view_rev['due_date'])): ?>
            <tr><th><?php esc_html_e('Due Date','eiu-rp'); ?></th><td><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($view_rev['due_date']))); ?></td></tr>
          <?php endif; ?>
        </table>
      </div>
    </div>

    <?php
    /* WYSIWYG editor IDs — unique per article to avoid TinyMCE conflicts */
    $rv_comments_editor_id = 'rv_comments_' . absint($article_id);
    $rv_upload_nonce       = wp_create_nonce('eiu_rp_frontend');
    $rv_ajax_url           = admin_url('admin-ajax.php');
    $rv_has_review_form    = in_array($view_rev['status']??'', ['assigned','in_progress'], true);
    ?>

    <!-- ── Elegant content tabs ───────────────────────────────── -->
    <div class="rv2-card" style="overflow:hidden;">
      <!-- Tab strip -->
      <nav class="rv2-art-tabs" role="tablist" id="rv2-art-tabstrip">
        <?php if ($abstract): ?>
        <button type="button" class="rv2-art-tab active" role="tab"
          aria-controls="rv2-panel-abstract" aria-selected="true"
          onclick="rv2SwitchTab(this,'rv2-panel-abstract')">
          <i class="bi bi-file-text"></i>
          <span class="rv2-tab-label"><?php Terminology::e('abstract_label'); ?></span>
        </button>
        <?php endif; ?>

        <?php if ($refs): ?>
        <button type="button" class="rv2-art-tab<?php echo !$abstract?' active':''; ?>" role="tab"
          aria-controls="rv2-panel-refs"
          onclick="rv2SwitchTab(this,'rv2-panel-refs')">
          <i class="bi bi-journals"></i>
          <span class="rv2-tab-label"><?php Terminology::e('references_label'); ?></span>
        </button>
        <?php endif; ?>

        <?php if ($rv_has_review_form): ?>
        <button type="button" class="rv2-art-tab<?php echo (!$abstract && !$refs)?' active':''; ?>" role="tab"
          aria-controls="rv2-panel-review"
          onclick="rv2SwitchTab(this,'rv2-panel-review')" id="rv2-tab-review">
          <i class="bi bi-pencil-square"></i>
          <span class="rv2-tab-label"><?php echo esc_html( get_option('eiu_rp_term_my_review',__('My Review','eiu-rp')) ); ?></span>
        </button>
        <?php endif; ?>

        <button type="button" class="rv2-art-tab" role="tab"
          aria-controls="rv2-panel-collab"
          onclick="rv2SwitchTab(this,'rv2-panel-collab')">
          <i class="bi bi-people-fill"></i>
          <span class="rv2-tab-label"><?php Terminology::e('collaborate_tab'); ?></span>
        </button>

        <button type="button" class="rv2-art-tab" role="tab"
          aria-controls="rv2-panel-status"
          onclick="rv2SwitchTab(this,'rv2-panel-status')">
          <i class="bi bi-sliders"></i>
          <span class="rv2-tab-label"><?php Terminology::e('status_tab'); ?></span>
        </button>

        <button type="button" class="rv2-art-tab" role="tab"
          aria-controls="rv2-panel-edit"
          onclick="rv2SwitchTab(this,'rv2-panel-edit')">
          <i class="bi bi-pencil-fill"></i>
          <span class="rv2-tab-label"><?php Terminology::e('edit_tab'); ?></span>
        </button>
      </nav>

      <!-- Abstract panel -->
      <?php if ($abstract): ?>
      <div class="rv2-tab-panel active rv2-card-body" id="rv2-panel-abstract" role="tabpanel" style="margin-top:0;">
        <div class="rv2-abstract"><?php echo wp_kses_post($abstract); ?></div>
      </div>
      <?php endif; ?>

      <!-- References panel -->
      <?php if ($refs): ?>
      <div class="rv2-tab-panel<?php echo !$abstract?' active':''; ?> rv2-card-body" id="rv2-panel-refs" role="tabpanel"
        style="font-size:13px;line-height:1.9;color:var(--c-text2);">
        <?php echo wp_kses_post(nl2br($refs)); ?>
      </div>
      <?php endif; ?>

      <!-- Review form panel -->
      <?php if ($rv_has_review_form): ?>
      <div class="rv2-tab-panel<?php echo (!$abstract && !$refs)?' active':''; ?>" id="rv2-panel-review" role="tabpanel">
        <div class="rv2-card-body">
    <?php if ( in_array($view_rev['status']??'', ['assigned','in_progress'], true) ): ?>
    <div class="">
      <div class="rv2-card-head">
        <h3 class="rv2-card-title"><i class="bi bi-pencil-square" style="color:var(--c-purple);"></i><?php Terminology::e('submit_review'); ?></h3>
        <span style="font-size:11px;background:#f3e8ff;color:#6d28d9;border-radius:20px;padding:2px 10px;font-weight:700;">
          <i class="bi bi-type-bold me-1"></i><?php esc_html_e('Rich Text Editor','eiu-rp'); ?>
        </span>
      </div>
      <div class="rv2-card-body">
        <div class="rv2-review-box">
          <h4><?php Terminology::e('review_decision'); ?></h4>
          <form class="eiu-rv-rform">
            <input type="hidden" name="action" value="eiu_rp_submit_review">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="review_id" value="<?php echo esc_attr($view_rev['id']??0); ?>">
            <div class="rv2-radio-group">
              <?php foreach ([
                'accept'         => [Terminology::get('accept'),         'bi-check-circle-fill',  '#059669'],
                'minor_revision' => [Terminology::get('minor_revision'), 'bi-pencil-fill',         '#d97706'],
                'major_revision' => [Terminology::get('major_revision'), 'bi-exclamation-triangle-fill', '#dc2626'],
                'reject'         => [Terminology::get('reject'),         'bi-x-circle-fill',       '#991b1b'],
              ] as $val => [$lbl, $icon, $clr]): ?>
                <label class="rv2-radio-opt">
                  <input type="radio" name="recommendation" value="<?php echo esc_attr($val); ?>">
                  <span class="rv2-radio-box">
                    <i class="bi <?php echo esc_attr($icon); ?>" style="color:<?php echo esc_attr($clr); ?>;font-size:13px;"></i>
                    <?php echo esc_html($lbl); ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <!-- Revision callout — visible only for minor/major revision selections -->
            <div id="rv2-revision-callout" style="display:none;margin-top:14px;background:#fffbeb;border:1.5px solid #fcd34d;border-radius:8px;padding:12px 16px;">
              <p style="font-size:13px;font-weight:700;color:#92400e;margin:0 0 4px;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?php esc_html_e('Revision Notes Required','eiu-rp'); ?>
              </p>
              <p style="font-size:12px;color:#78350f;margin:0;">
                <?php esc_html_e('When requesting revisions, you must provide detailed feedback (at least 20 characters). These notes will be emailed directly to the researcher and displayed in their dashboard.','eiu-rp'); ?>
              </p>
            </div>

            <div style="margin-top:14px;">
              <span class="rv2-label" id="rv2-comments-label">
                <?php Terminology::e('comments_for_author'); ?> <span style="color:var(--c-red);">*</span>
              </span>
              <div id="rv2-comments-hint" style="display:none;font-size:11px;color:#78350f;margin-top:4px;margin-bottom:8px;">
                <i class="bi bi-info-circle me-1"></i>
                <?php esc_html_e('Minimum 20 characters required for revision feedback.','eiu-rp'); ?>
              </div>
              <!-- Quill Rich Text Editor — Reviewer Comments -->
              <div class="eiu-quill-wrap" id="rv-quill-wrap" style="margin-top:6px;">
                <div id="rv-comments-quill" style="min-height:220px;"></div>
              </div>
              <textarea
                id="<?php echo esc_attr($rv_comments_editor_id); ?>"
                name="comments"
                style="display:none;"></textarea>
            </div>
            <div style="margin-top:14px;">
              <button type="submit" class="rv2-submit-review">
                <i class="bi bi-send-fill"></i><?php Terminology::e('submit_review'); ?>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php elseif (($view_rev['status']??'') === 'submitted'): ?>
    <div style="background:#f5f3ff;border:1px solid #ede9fe;border-radius:10px;padding:18px 20px;margin-bottom:16px;">
      <i class="bi bi-send-check-fill me-2" style="color:var(--c-purple);"></i>
      <strong><?php Terminology::e('your_review_submitted'); ?></strong>
      <?php if (!empty($view_rev['comments'])): ?>
        <div style="font-size:13px;color:var(--c-muted);margin:8px 0 0;line-height:1.7;"><?php echo wp_kses_post($view_rev['comments']); ?></div>
      <?php endif; ?>
    </div>
    <?php endif; // in_array status ?>
        </div><!-- rv2-card-body -->
      </div><!-- rv2-panel-review -->
      <?php endif; // rv_has_review_form ?>

      <!-- Collaborate panel (Co-Reviewer + Private Notes) -->
      <div class="rv2-tab-panel" id="rv2-panel-collab" role="tabpanel">
        <div class="rv2-card-body">
    <!-- ── v2.2: Co-Reviewer Assignment + Private Notes panel ─────── -->
    <?php
    $saved_co_ids = array();
    if ( ! empty($view_rev['co_reviewer']) ) {
        $decoded      = json_decode( $view_rev['co_reviewer'], true );
        $saved_co_ids = is_array($decoded) ? array_map('intval', $decoded) : array();
    }
    $co_reviewer_options = array_filter( $all_reviewers, fn($r) => (int)($r['id']??0) !== (int)($reviewer->id??0) );
    ?>
    <div class="rv2-card" style="margin-top:16px;">
      <div class="rv2-card-head">
        <h3 class="rv2-card-title"><i class="bi bi-people-fill" style="color:var(--c-accent2);"></i><?php Terminology::e('co_reviewer_assignment'); ?></h3>
        <span style="font-size:11px;background:#eef4ff;color:#1a4988;border-radius:20px;padding:2px 10px;font-weight:700;">
          <?php echo count($saved_co_ids); ?> <?php esc_html_e('assigned','eiu-rp'); ?>
        </span>
      </div>
      <div class="rv2-card-body">
        <div id="rv-co-assign-ok"  style="display:none;padding:8px 14px;border-radius:6px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <div id="rv-co-assign-err" style="display:none;padding:8px 14px;border-radius:6px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600;margin-bottom:12px;"></div>

        <p style="font-size:13px;color:var(--c-muted);margin:0 0 14px;">
          <i class="bi bi-info-circle me-1"></i>
          <?php esc_html_e('Select reviewers below and click Assign. Each newly assigned co-reviewer will receive an email notification.','eiu-rp'); ?>
        </p>

        <?php if ( empty($co_reviewer_options) ): ?>
          <p style="font-size:13px;color:var(--c-muted);font-style:italic;"><?php esc_html_e('No other verified reviewers available.','eiu-rp'); ?></p>
        <?php else: ?>
        <!-- Reviewer checklist -->
        <div style="border:1.5px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:14px;">
          <?php foreach ($co_reviewer_options as $cr):
            $is_assigned = in_array((int)$cr['id'], $saved_co_ids, true);
          ?>
          <div class="rv-co-row" data-id="<?php echo esc_attr($cr['id']); ?>"
            style="display:flex;align-items:center;gap:12px;padding:11px 14px;border-bottom:1px solid #f0f2f5;background:<?php echo $is_assigned ? '#f0fdf4' : '#fff'; ?>;">
            <input type="checkbox" class="rv-co-check" value="<?php echo esc_attr($cr['id']); ?>"
              <?php echo $is_assigned ? 'checked' : ''; ?>
              style="width:16px;height:16px;cursor:pointer;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
              <strong style="font-size:13px;"><?php echo esc_html($cr['full_name']); ?></strong>
              <span style="font-size:11px;color:var(--c-muted);margin-left:6px;"><?php echo esc_html($cr['email']); ?></span>
              <?php if (!empty($cr['specialization'])): ?>
                <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?php echo esc_html(wp_trim_words($cr['specialization'],6)); ?></div>
              <?php endif; ?>
            </div>
            <?php if ($is_assigned): ?>
              <span class="rv-co-badge-<?php echo esc_attr($cr['id']); ?>" style="font-size:11px;background:#dcfce7;color:#166534;border-radius:20px;padding:2px 10px;font-weight:700;white-space:nowrap;">
                <i class="bi bi-check-circle-fill me-1"></i><?php esc_html_e('Assigned','eiu-rp'); ?>
              </span>
            <?php else: ?>
              <span class="rv-co-badge-<?php echo esc_attr($cr['id']); ?>" style="display:none;font-size:11px;background:#dcfce7;color:#166534;border-radius:20px;padding:2px 10px;font-weight:700;white-space:nowrap;">
                <i class="bi bi-check-circle-fill me-1"></i><?php esc_html_e('Assigned','eiu-rp'); ?>
              </span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="button" class="rv2-btn rv2-btn-primary" id="rv-assign-co-btn"
            data-review-id="<?php echo esc_attr($view_rev['id']??0); ?>">
            <i class="bi bi-person-check-fill"></i><?php Terminology::e('assign_co_reviewers_btn'); ?>
          </button>
          <button type="button" class="rv2-btn rv2-btn-ghost rv2-btn-sm" id="rv-co-select-all">
            <?php Terminology::e('select_all'); ?>
          </button>
          <button type="button" class="rv2-btn rv2-btn-ghost rv2-btn-sm" id="rv-co-clear-all">
            <?php Terminology::e('clear_all'); ?>
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Private Notes panel (notifies co-reviewers on save) ─── -->
    <div class="rv2-card" style="margin-top:16px;">
      <div class="rv2-card-head">
        <h3 class="rv2-card-title"><i class="bi bi-journal-lock" style="color:var(--c-accent2);"></i><?php Terminology::e('private_notes'); ?></h3>
        <span style="font-size:11px;color:var(--c-muted);font-weight:500;"><?php esc_html_e('Co-reviewers are notified by email when notes are saved','eiu-rp'); ?></span>
      </div>
      <div class="rv2-card-body">
        <div id="rv-notes-ok"  style="display:none;padding:8px 14px;border-radius:6px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <div id="rv-notes-err" style="display:none;padding:8px 14px;border-radius:6px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <textarea id="rv-reviewer-notes" class="form-control" rows="6"
          placeholder="<?php esc_attr_e('Internal comments, annotations, reference checks… Co-reviewers will receive these notes by email.','eiu-rp'); ?>"><?php echo esc_textarea($view_rev['reviewer_notes']??''); ?></textarea>
        <p style="font-size:11px;color:var(--c-muted);margin:6px 0 14px;">
          <i class="bi bi-send me-1"></i><?php esc_html_e('Saving notes will automatically notify all assigned co-reviewers by email.','eiu-rp'); ?>
        </p>
        <button type="button" class="rv2-btn rv2-btn-primary" id="rv-save-notes-btn"
          data-review-id="<?php echo esc_attr($view_rev['id']??0); ?>">
          <i class="bi bi-send-fill"></i><?php Terminology::e('save_notify_btn'); ?>
        </button>
      </div>
    </div>

        </div><!-- rv2-card-body (collab) -->
      </div><!-- rv2-panel-collab -->

      <!-- Status panel -->
      <div class="rv2-tab-panel" id="rv2-panel-status" role="tabpanel">
        <div class="rv2-card-body">
        <h3 class="rv2-card-title" style="padding:0 0 14px;"><i class="bi bi-sliders" style="color:var(--c-accent2);margin-right:6px;"></i><?php Terminology::e('update_article_status'); ?></h3>
      </div>
        <div id="rv-status-ok"  style="display:none;padding:8px 14px;border-radius:6px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <div id="rv-status-err" style="display:none;padding:8px 14px;border-radius:6px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <div style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
          <div style="flex:1;min-width:200px;">
            <label class="rv2-label"><?php Terminology::e('set_article_status'); ?></label>
            <select id="rv-status-select" class="form-select">
              <?php
              $article_statuses = array(
                \EIU_RP\Models\Article::STATUS_PENDING      => __('Pending','eiu-rp'),
                \EIU_RP\Models\Article::STATUS_UNDER_REVIEW => __('Under Review','eiu-rp'),
                \EIU_RP\Models\Article::STATUS_APPROVED     => __('Approved','eiu-rp'),
                \EIU_RP\Models\Article::STATUS_REJECTED     => __('Rejected','eiu-rp'),
                \EIU_RP\Models\Article::STATUS_PUBLISHED    => __('Published','eiu-rp'),
                \EIU_RP\Models\Article::STATUS_REVISION     => __('Revision Required','eiu-rp'),
              );
              $cur_art_status = $art ? ($art->status ?? '') : '';
              foreach ($article_statuses as $val => $lbl): ?>
                <option value="<?php echo esc_attr($val); ?>" <?php selected($val,$cur_art_status); ?>>
                  <?php echo esc_html($lbl); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="button" class="rv2-btn rv2-btn-accent" id="rv-update-status-btn"
            data-review-id="<?php echo esc_attr($view_rev['id']??0); ?>">
            <i class="bi bi-check-circle"></i><?php Terminology::e('update_status_btn'); ?>
          </button>
        </div>
          <!-- Publish date (backdating) — shown only when Published is selected -->
          <div id="rv-publish-date-wrap" style="display:none;margin:10px 0;">
            <label class="rv2-label" style="font-size:12px;margin-bottom:4px;">
              <i class="bi bi-calendar-event" style="color:#1a4988;margin-right:5px;"></i>
              <?php esc_html_e('Publish Date','eiu-rp'); ?>
              <span style="font-size:11px;color:#6b7280;font-weight:400;margin-left:6px;"><?php esc_html_e('(leave blank for today)','eiu-rp'); ?></span>
            </label>
            <input type="date" id="rv-publish-date" class="form-control"
              max="<?php echo esc_attr(date('Y-m-d')); ?>"
              style="max-width:220px;font-size:13px;">
            <p style="font-size:11px;color:#6b7280;margin:4px 0 0;"><?php esc_html_e('You may choose a past date to backdate the publication.','eiu-rp'); ?></p>
          </div>
        <div id="rv-revision-notes-wrap" style="display:none;margin-top:10px;">
          <label class="rv2-label"><?php Terminology::e('revision_notes_label'); ?> <span style="color:var(--c-red);">*</span></label>
          <textarea id="rv-revision-notes-field" class="form-control" rows="4"
            placeholder="<?php esc_attr_e('Describe what the researcher needs to revise…','eiu-rp'); ?>"></textarea>
        </div><!-- rv2-card-body (status) -->
      </div><!-- rv2-panel-status -->

      <!-- ── Edit Article panel ────────────────────────────────── -->
      <div class="rv2-tab-panel" id="rv2-panel-edit" role="tabpanel">
        <div class="rv2-card-body" id="rv-edit-wrap">

          <?php
          // Pre-load current values for the form
          $edit_abstract  = get_post_meta($post_id,'_eiu_abstract',true) ?: ($art->abstract ?? '');
          $edit_refs      = get_post_meta($post_id,'_eiu_references',true) ?: ($art->references ?? '');
          $edit_thumb_id  = (int) get_post_thumbnail_id($post_id);
          $edit_thumb_url = $edit_thumb_id ? wp_get_attachment_image_url($edit_thumb_id,'medium') : '';
          $edit_file_name = $art->file_name ?? '';
          $edit_nonce     = wp_create_nonce('eiu_rp_frontend');
          $edit_article_id = (int)($view_rev['article_id'] ?? 0);
          ?>

          <div id="rv-edit-ok"  style="display:none;padding:10px 16px;border-radius:8px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600;margin-bottom:16px;"></div>
          <div id="rv-edit-err" style="display:none;padding:10px 16px;border-radius:8px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600;margin-bottom:16px;"></div>

          <p style="font-size:13px;color:#6b7280;margin:0 0 20px;line-height:1.6;">
            <i class="bi bi-info-circle-fill" style="color:#1a4988;margin-right:6px;"></i>
            <?php esc_html_e('You may edit the abstract, references, thumbnail, and submitted article file on behalf of the researcher. All changes are logged to the activity log.','eiu-rp'); ?>
          </p>

          <!-- Thumbnail -->
          <div style="margin-bottom:24px;">
            <label class="rv2-label" style="display:block;font-weight:700;font-size:13px;margin-bottom:10px;">
              <i class="bi bi-image" style="color:#1a4988;margin-right:5px;"></i><?php esc_html_e('Article Thumbnail','eiu-rp'); ?>
            </label>
            <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;">
              <div id="rv-edit-thumb-preview" style="width:120px;height:80px;border-radius:8px;overflow:hidden;background:#f3f4f6;border:1.5px dashed #d1d5db;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <?php if ($edit_thumb_url): ?>
                  <img id="rv-edit-thumb-img" src="<?php echo esc_url($edit_thumb_url); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <?php else: ?>
                  <i class="bi bi-image" id="rv-edit-thumb-img" style="font-size:28px;color:#d1d5db;"></i>
                <?php endif; ?>
              </div>
              <div>
                <label class="rv2-btn rv2-btn-ghost rv2-btn-sm" for="rv-edit-thumb-file" style="cursor:pointer;margin-bottom:6px;">
                  <i class="bi bi-upload"></i><?php Terminology::e('upload_new_thumbnail'); ?>
                </label>
                <input type="file" id="rv-edit-thumb-file" accept="image/jpeg,image/png,image/webp" style="display:none;">
                <p style="font-size:12px;color:#9ca3af;margin:4px 0 0;"><?php esc_html_e('JPG, PNG or WebP — recommended 800×400px','eiu-rp'); ?></p>
                <div id="rv-thumb-upload-status" style="font-size:12px;margin-top:4px;"></div>
              </div>
            </div>
          </div>

          <!-- Abstract -->
          <div style="margin-bottom:20px;">
            <label class="rv2-label" style="display:block;font-weight:700;font-size:13px;margin-bottom:8px;">
              <i class="bi bi-file-text" style="color:#1a4988;margin-right:5px;"></i><?php Terminology::e('abstract_label'); ?>
            </label>
            <textarea id="rv-edit-abstract" class="form-control" rows="6"
              style="font-size:14px;line-height:1.7;resize:vertical;"
              placeholder="<?php esc_attr_e('Enter the abstract…','eiu-rp'); ?>"><?php echo esc_textarea($edit_abstract); ?></textarea>
          </div>

          <!-- References -->
          <div style="margin-bottom:20px;">
            <label class="rv2-label" style="display:block;font-weight:700;font-size:13px;margin-bottom:8px;">
              <i class="bi bi-journals" style="color:#1a4988;margin-right:5px;"></i><?php Terminology::e('references_label'); ?>
            </label>
            <textarea id="rv-edit-references" class="form-control" rows="6"
              style="font-size:13px;line-height:1.8;font-family:monospace;resize:vertical;"
              placeholder="<?php esc_attr_e('Enter references, one per line…','eiu-rp'); ?>"><?php echo esc_textarea(wp_strip_all_tags($edit_refs)); ?></textarea>
          </div>

          <!-- Submitted Article File -->
          <div style="margin-bottom:24px;">
            <label class="rv2-label" style="display:block;font-weight:700;font-size:13px;margin-bottom:8px;">
              <i class="bi bi-file-earmark-pdf" style="color:#1a4988;margin-right:5px;"></i><?php Terminology::e('submitted_article_file'); ?>
            </label>
            <?php if ($edit_file_name): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:10px;flex-wrap:wrap;">
              <i class="bi bi-file-earmark-check" style="color:#1a4988;font-size:18px;"></i>
              <span id="rv-edit-filename" style="font-size:13px;font-weight:600;color:#374151;flex:1;min-width:0;word-break:break-all;"><?php echo esc_html($edit_file_name); ?></span>
              <span style="font-size:11px;color:#9ca3af;"><?php Terminology::e('current_file'); ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <label class="rv2-btn rv2-btn-ghost rv2-btn-sm" for="rv-edit-article-file" style="cursor:pointer;">
                <i class="bi bi-arrow-repeat"></i><?php Terminology::e('replace_article_file'); ?>
              </label>
              <input type="file" id="rv-edit-article-file" accept=".pdf,.ppt,.pptx" style="display:none;">
              <span style="font-size:12px;color:#9ca3af;"><?php esc_html_e('PDF, PPT, PPTX accepted','eiu-rp'); ?></span>
            </div>
            <div id="rv-file-upload-status" style="font-size:12px;margin-top:6px;"></div>
          </div>

          <!-- Save button -->
          <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <button type="button" id="rv-edit-save-btn"
              class="rv2-btn rv2-btn-primary"
              data-article-id="<?php echo esc_attr($edit_article_id); ?>"
              data-nonce="<?php echo esc_attr($edit_nonce); ?>">
              <i class="bi bi-check2-circle"></i><?php Terminology::e('save_changes'); ?>
            </button>
            <span style="font-size:12px;color:#9ca3af;"><?php esc_html_e('Abstract and references are saved as plain text. Thumbnail and file are uploaded immediately on selection.','eiu-rp'); ?></span>
          </div>

        </div><!-- rv2-card-body (edit) -->
      </div><!-- rv2-panel-edit -->

    </div><!-- rv2-card (the big tabbed card) -->

<?php endif; // end article detail
/* ═══ DASHBOARD TAB ══════════════════════════════════════ */
elseif ($tab === 'dashboard'): ?>

  <div class="rv2-stat-grid">
    <div class="rv2-kpi">
      <div class="rv2-kpi-icon" style="background:linear-gradient(135deg,#1a4988,#2563eb);"><i class="bi bi-journals"></i></div>
      <div><div class="rv2-kpi-num" style="color:var(--c-accent2);"><?php echo esc_html(count($reviews)); ?></div><div class="rv2-kpi-lbl"><?php Terminology::e('total_assigned'); ?></div></div>
    </div>
    <div class="rv2-kpi">
      <div class="rv2-kpi-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b);"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="rv2-kpi-num" style="color:var(--c-amber);"><?php echo esc_html(count($pending)); ?></div><div class="rv2-kpi-lbl"><?php Terminology::e('pending'); ?></div></div>
    </div>
    <div class="rv2-kpi">
      <div class="rv2-kpi-icon" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);"><i class="bi bi-send-check-fill"></i></div>
      <div><div class="rv2-kpi-num" style="color:var(--c-purple);"><?php echo esc_html(count($submitted)); ?></div><div class="rv2-kpi-lbl"><?php Terminology::e('submitted_kpi'); ?></div></div>
    </div>
    <div class="rv2-kpi">
      <div class="rv2-kpi-icon" style="background:linear-gradient(135deg,#16a34a,#15803d);"><i class="bi bi-check2-all"></i></div>
      <div><div class="rv2-kpi-num" style="color:var(--c-green);"><?php echo esc_html(count($completed)); ?></div><div class="rv2-kpi-lbl"><?php Terminology::e('completed_kpi'); ?></div></div>
    </div>
  </div>

  <?php if (!empty($pending)): ?>
  <div class="rv2-card">
    <div class="rv2-card-head">
      <h3 class="rv2-card-title"><i class="bi bi-exclamation-circle" style="color:var(--c-amber);"></i><?php Terminology::e('awaiting_your_review'); ?></h3>
      <a href="<?php echo esc_url(add_query_arg('tab','articles',$page_url)); ?>" class="rv2-btn rv2-btn-ghost rv2-btn-sm"><?php Terminology::e('view_all'); ?></a>
    </div>
    <div class="rv2-card-body" style="padding:14px 20px;">
      <?php foreach(array_slice($pending,0,4) as $r):
        $title = !empty($r['post_id']) ? get_the_title($r['post_id']) : (__('(Untitled)','eiu-rp'));
      ?>
        <div class="rv2-art-row">
          <div>
            <p class="rv2-art-title"><?php echo esc_html($title); ?></p>
            <p class="rv2-art-meta"><i class="bi bi-calendar3 me-1"></i><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($r['assigned_at']??''))); ?></p>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span class="rv2-pill pill-assigned"><?php Terminology::e('pending'); ?></span>
            <a href="<?php echo esc_url(add_query_arg(['tab'=>'articles','article_id'=>$r['article_id']],$page_url)); ?>" class="rv2-btn rv2-btn-primary rv2-btn-sm"><?php esc_html_e('Review','eiu-rp'); ?></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Quick submit CTA -->
  <div style="background:linear-gradient(135deg,#0e1b4a,#1a3060);border-radius:var(--r);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
      <p style="font-family:'Cabinet Grotesk',sans-serif;font-size:16px;font-weight:800;color:#fff;margin:0 0 4px;"><?php Terminology::e('have_research_to_submit'); ?></p>
      <p style="font-size:13px;color:rgba(255,255,255,.55);margin:0;"><?php Terminology::e('submit_directly_rv_desc'); ?></p>
    </div>
    <a href="<?php echo esc_url(add_query_arg('tab','submit',$page_url)); ?>" class="rv2-btn rv2-btn-accent">
      <i class="bi bi-plus-circle-fill"></i><?php echo esc_html( get_option('eiu_rp_term_submit_manuscript','Submit Manuscript') ); ?>
    </a>
  </div>

<?php
/* ═══ ARTICLES LIST ══════════════════════════════════════ */
elseif ($tab === 'articles'): ?>

  <?php if (empty($reviews)): ?>
    <div class="rv2-empty"><i class="bi bi-inbox"></i><p style="font-weight:600;margin:0;"><?php Terminology::e('no_articles_assigned'); ?></p></div>
  <?php else: ?>
    <?php foreach ($reviews as $r):
      $s     = $r['status']??'assigned';
      $title = !empty($r['post_id']) ? get_the_title($r['post_id']) : __('(Untitled)','eiu-rp');
      $pm    = ['assigned'=>'pill-assigned','in_progress'=>'pill-in_progress','submitted'=>'pill-submitted','approved'=>'pill-approved','rejected'=>'pill-rejected'];
    ?>
      <div class="rv2-art-row">
        <div style="flex:1;min-width:0;">
          <p class="rv2-art-title"><?php echo esc_html($title); ?></p>
          <p class="rv2-art-meta"><i class="bi bi-calendar3 me-1"></i><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($r['assigned_at']??''))); ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;flex-wrap:wrap;">
          <span class="rv2-pill <?php echo esc_attr($pm[$s]??'pill-assigned'); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$s))); ?></span>
          <a href="<?php echo esc_url(add_query_arg(['tab'=>'articles','article_id'=>$r['article_id']],$page_url)); ?>" class="rv2-btn rv2-btn-primary rv2-btn-sm"><?php Terminology::e('view_and_review'); ?></a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php
/* ═══ REVIEWS TAB ════════════════════════════════════════ */
elseif ($tab === 'reviews'): ?>

  <?php if (empty($submitted) && empty($completed)): ?>
    <div class="rv2-empty"><i class="bi bi-pencil-square"></i><p style="font-weight:600;margin:0 0 4px;"><?php esc_html_e('No completed reviews yet.','eiu-rp'); ?></p></div>
  <?php else:
    foreach (array_merge(iterator_to_array(new ArrayObject($submitted)), iterator_to_array(new ArrayObject($completed))) as $r):
      $s = $r['status']??'submitted';
      $title = !empty($r['post_id']) ? get_the_title($r['post_id']) : __('(Untitled)','eiu-rp');
      $pm = ['submitted'=>'pill-submitted','approved'=>'pill-approved','rejected'=>'pill-rejected'];
    ?>
      <div class="rv2-art-row">
        <div style="flex:1;min-width:0;">
          <p class="rv2-art-title"><?php echo esc_html($title); ?></p>
          <p class="rv2-art-meta"><?php esc_html_e('Recommendation:','eiu-rp'); ?> <strong><?php echo esc_html(ucwords(str_replace('_',' ',$r['recommendation']??'—'))); ?></strong></p>
          <?php if (!empty($r['comments'])): ?>
            <p class="rv2-art-meta" style="margin-top:3px;"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($r['comments']),20)); ?></p>
          <?php endif; ?>
        </div>
        <span class="rv2-pill <?php echo esc_attr($pm[$s]??'pill-submitted'); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$s))); ?></span>
      </div>
    <?php endforeach; endif; ?>

<?php
/* ═══ SUBMIT ARTICLE ════════════════════════════════════ */
elseif ($tab === 'submit'):
  if ( ! \EIU_RP\Roles\Researcher_Role::can_submit() ): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:18px 22px;color:#991b1b;">
      <?php esc_html_e('You do not have permission to submit articles.','eiu-rp'); ?>
    </div>
  <?php else: ?>
    <div class="rv2-sf-wrap">
      <p style="font-size:13px;color:var(--c-muted);margin:0 0 20px;">
        <?php esc_html_e('Complete all required steps and submit your research article below.','eiu-rp'); ?>
      </p>

      <?php
      // Embed the full submission form template
      ob_start();
      \EIU_RP\Utils\Template_Loader::get_template( 'frontend/submission-form.php', array( 'redirect' => '' ) );
      $sf_html = ob_get_clean();
      echo $sf_html;
      ?>
    </div>
  <?php endif; ?>

<?php
/* ═══ REVIEWERS DIRECTORY ═══════════════════════════════ */
elseif ($tab === 'reviewers'): ?>

  <div class="rv2-reviewer-grid">
    <?php if (empty($all_reviewers)): ?>
      <p style="color:var(--c-muted);"><?php esc_html_e('No reviewers found.','eiu-rp'); ?></p>
    <?php else:
      foreach ($all_reviewers as $rv):
        $init = strtoupper(substr($rv->full_name??'R',0,1));
      ?>
        <div class="rv2-reviewer-card">
          <div class="rv2-r-av"><?php echo esc_html($init); ?></div>
          <div style="min-width:0;">
            <p style="font-size:13px;font-weight:700;color:var(--c-text);margin:0 0 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($rv->full_name??''); ?></p>
            <p style="font-size:12px;color:var(--c-muted);margin:0 0 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($rv->specialization??''); ?></p>
            <?php if ($rv->verified): ?>
              <span class="rv2-verified"><i class="bi bi-patch-check-fill"></i><?php Terminology::e('verified'); ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
  </div>

<?php
/* ═══ APPLICATIONS TAB — v2.0.1 ════════════════════════════════ */
elseif ($tab === 'applications'):

  $app_nonce = wp_create_nonce('eiu_rp_frontend');

  if ($app_id_view && $tab === 'applications'):
    /* ── Single application detail view ── */
    $view_app = \EIU_RP\Models\Application::get($app_id_view);
    if (!$view_app || !in_array((int)$view_app->assigned_reviewer_id, $_rv_ids, true)): ?>
      <p style="color:#991b1b;"><?php esc_html_e('Application not found or not assigned to you.','eiu-rp'); ?></p>
    <?php else:
      $badge_map = [
        'pending'              => 'pill-assigned',
        'reviewing'            => 'pill-in_progress',
        'approved'             => 'pill-approved',
        'rejected'             => 'pill-rejected',
        'more_info_required'   => 'pill-in_progress',
      ];
    ?>
    <a href="<?php echo esc_url(add_query_arg('tab','applications',$page_url)); ?>" class="rv2-btn rv2-btn-ghost rv2-btn-sm" style="margin-bottom:16px;">
      <i class="bi bi-arrow-left"></i><?php esc_html_e('Back to Applications','eiu-rp'); ?>
    </a>

    <div class="rv2-card" style="margin-bottom:16px;">
      <div style="background:linear-gradient(135deg,#f8f9ff,#eef2ff);padding:20px;border-radius:12px 12px 0 0;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;">
          <div>
            <h2 style="font-size:18px;font-weight:700;margin:0 0 6px;color:#1a2535;"><?php echo esc_html($view_app->full_name); ?></h2>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <span class="rv2-pill <?php echo esc_attr($badge_map[$view_app->status] ?? 'pill-assigned'); ?>">
                <?php echo esc_html(\EIU_RP\Models\Application::status_label($view_app->status)); ?>
              </span>
              <?php if ($view_app->expertise): ?>
                <span style="font-size:12px;background:#e8eef8;color:#1a4988;border-radius:20px;padding:3px 10px;font-weight:600;"><?php echo esc_html($view_app->expertise); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div style="padding:20px 24px;">
        <table class="rv2-meta-table">
          <tr><th><?php esc_html_e('Email','eiu-rp'); ?></th><td><?php echo esc_html($view_app->email); ?></td></tr>
          <?php if ($view_app->designation): ?><tr><th><?php esc_html_e('Designation','eiu-rp'); ?></th><td><?php echo esc_html($view_app->designation); ?></td></tr><?php endif; ?>
          <?php if ($view_app->country): ?><tr><th><?php esc_html_e('Country','eiu-rp'); ?></th><td><?php echo esc_html($view_app->country); ?></td></tr><?php endif; ?>
          <?php if ($view_app->gender): ?><tr><th><?php esc_html_e('Gender','eiu-rp'); ?></th><td><?php echo esc_html(ucfirst($view_app->gender)); ?></td></tr><?php endif; ?>
          <?php if ($view_app->date_of_birth): ?><tr><th><?php esc_html_e('Date of Birth','eiu-rp'); ?></th><td><?php echo esc_html($view_app->date_of_birth); ?></td></tr><?php endif; ?>
          <tr><th><?php esc_html_e('Submitted','eiu-rp'); ?></th><td><?php echo esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),strtotime($view_app->submitted_at))); ?></td></tr>
        </table>
      </div>
    </div>

    <?php if ($view_app->academic_bg): ?>
    <div class="rv2-card" style="margin-bottom:12px;">
      <div class="rv2-card-head"><h3 class="rv2-card-title"><i class="bi bi-mortarboard-fill" style="color:var(--c-accent2);"></i><?php esc_html_e('Academic Background','eiu-rp'); ?></h3></div>
      <div class="rv2-card-body" style="white-space:pre-wrap;font-size:14px;line-height:1.7;"><?php echo esc_html($view_app->academic_bg); ?></div>
    </div>
    <?php endif; ?>

    <?php if ($view_app->about): ?>
    <div class="rv2-card" style="margin-bottom:12px;">
      <div class="rv2-card-head"><h3 class="rv2-card-title"><i class="bi bi-person-fill" style="color:var(--c-accent2);"></i><?php esc_html_e('About the Applicant','eiu-rp'); ?></h3></div>
      <div class="rv2-card-body" style="white-space:pre-wrap;font-size:14px;line-height:1.7;"><?php echo esc_html($view_app->about); ?></div>
    </div>
    <?php endif; ?>

    <!-- Documents -->
    <div class="rv2-card" style="margin-bottom:16px;">
      <div class="rv2-card-head"><h3 class="rv2-card-title"><i class="bi bi-paperclip" style="color:var(--c-accent2);"></i><?php esc_html_e('Uploaded Documents','eiu-rp'); ?></h3></div>
      <div class="rv2-card-body">
        <?php
        $upload_base = wp_upload_dir()['basedir'];
        $upload_url  = wp_upload_dir()['baseurl'];
        foreach ([
          ['cv_file_path','cv_file_name',       __('CV / Resume','eiu-rp'),        'bi-file-earmark-person'],
          ['research_file_path','research_file_name',__('Research Work','eiu-rp'), 'bi-file-earmark-text'],
        ] as [$path_k,$name_k,$label,$icon]):
          if (!empty($view_app->$name_k)):
            $dl_link = file_exists($view_app->$path_k) ? ($upload_url.str_replace($upload_base,'',$view_app->$path_k)) : '';
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:7px;margin-bottom:8px;flex-wrap:wrap;">
          <i class="bi <?php echo esc_attr($icon); ?>" style="color:#1a4988;font-size:18px;"></i>
          <div style="flex:1;min-width:0;">
            <p style="margin:0;font-weight:600;font-size:13px;"><?php echo esc_html($label); ?></p>
            <p style="margin:0;font-size:12px;color:#6b7280;word-break:break-all;"><?php echo esc_html($view_app->$name_k); ?></p>
          </div>
          <?php if ($dl_link): ?>
          <a href="<?php echo esc_url($dl_link); ?>" download class="rv2-btn rv2-btn-ghost rv2-btn-sm" target="_blank"><?php esc_html_e('Download','eiu-rp'); ?></a>
          <?php endif; ?>
        </div>
        <?php endif; endforeach; ?>
      </div>
    </div>

    <!-- Update Status -->
    <div class="rv2-card">
      <div class="rv2-card-head"><h3 class="rv2-card-title"><i class="bi bi-check-circle" style="color:var(--c-accent2);"></i><?php esc_html_e('Update Application Status','eiu-rp'); ?></h3></div>
      <div class="rv2-card-body">
        <div id="rv-appv-ok"  style="display:none;padding:8px 14px;border-radius:6px;background:#ecfdf5;color:#065f46;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <div id="rv-appv-err" style="display:none;padding:8px 14px;border-radius:6px;background:#fef2f2;color:#991b1b;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
          <i class="bi bi-info-circle-fill" style="color:#1a4988;margin-right:5px;"></i>
          <?php esc_html_e('Setting Approved will automatically create an Author account and send login credentials to the applicant.','eiu-rp'); ?>
        </p>
        <select id="rv-appv-status" class="form-select" style="max-width:280px;margin-bottom:10px;">
          <option value=""><?php esc_html_e('-- Select a decision --','eiu-rp'); ?></option>
          <option value="approved"><?php esc_html_e('Approved','eiu-rp'); ?></option>
          <option value="rejected"><?php esc_html_e('Rejected','eiu-rp'); ?></option>
          <option value="more_info_required"><?php esc_html_e('More Information Required','eiu-rp'); ?></option>
        </select>
        <label style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;color:#374151;"><?php esc_html_e('Notes / Feedback (sent to applicant):','eiu-rp'); ?></label>
        <textarea id="rv-appv-notes" class="form-control" rows="3" style="max-width:480px;margin-bottom:10px;font-size:13px;"
          placeholder="<?php esc_attr_e('Optional feedback for the applicant…','eiu-rp'); ?>"></textarea>
        <button type="button" class="rv2-btn rv2-btn-primary" id="rv-appv-submit-btn"
          data-app-id="<?php echo esc_attr($view_app->id); ?>"
          data-nonce="<?php echo esc_attr($app_nonce); ?>">
          <i class="bi bi-check2-circle"></i><?php esc_html_e('Save Decision','eiu-rp'); ?>
        </button>
      </div>
    </div>

    <?php endif; // view_app check

  else:
    /* ── Applications list view ── */
    if (empty($reviewer_apps)): ?>
      <div class="rv2-empty">
        <i class="bi bi-people-fill"></i>
        <p style="font-weight:600;margin:0 0 4px;"><?php esc_html_e('No applications assigned yet.','eiu-rp'); ?></p>
        <p style="font-size:13px;color:var(--c-muted);"><?php esc_html_e('Applications assigned to you by the admin will appear here.','eiu-rp'); ?></p>
      </div>
    <?php else:
      $status_badge = [
        'pending'            => ['pill-assigned',     __('Pending','eiu-rp')],
        'reviewing'          => ['pill-in_progress',  __('Under Review','eiu-rp')],
        'approved'           => ['pill-approved',     __('Approved','eiu-rp')],
        'rejected'           => ['pill-rejected',     __('Rejected','eiu-rp')],
        'more_info_required' => ['pill-in_progress',  __('More Info Required','eiu-rp')],
      ];
    ?>
    <?php foreach ($reviewer_apps as $ra):
      [$badge_cls, $badge_lbl] = $status_badge[$ra->status] ?? ['pill-assigned',ucfirst($ra->status)];
    ?>
    <div class="rv2-art-row" style="margin-bottom:10px;">
      <div style="flex:1;min-width:0;">
        <p style="margin:0 0 4px;font-weight:700;font-size:15px;color:var(--c-text);"><?php echo esc_html($ra->full_name); ?></p>
        <p style="margin:0;font-size:13px;color:var(--c-muted);">
          <?php echo esc_html($ra->email); ?>
          &middot; <?php echo esc_html($ra->expertise ?: __('No expertise listed','eiu-rp')); ?>
          &middot; <i class="bi bi-calendar3"></i> <?php echo esc_html(wp_date(get_option('date_format'),strtotime($ra->submitted_at))); ?>
        </p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <span class="rv2-pill <?php echo esc_attr($badge_cls); ?>"><?php echo esc_html($badge_lbl); ?></span>
        <a href="<?php echo esc_url(add_query_arg(['tab'=>'applications','app_id'=>$ra->id],$page_url)); ?>"
           class="rv2-btn rv2-btn-primary rv2-btn-sm"><?php esc_html_e('Review','eiu-rp'); ?></a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  <?php endif; // list vs detail ?>

<?php
/* ═══ PROFILE ════════════════════════════════════════════ */
elseif ($tab === 'profile'): ?>

  <?php if ($profile_saved): ?>
    <div class="rv2-notice rv2-notice-ok" style="display:block;"><i class="bi bi-check-circle-fill me-2"></i><?php esc_html_e('Profile updated.','eiu-rp'); ?></div>
  <?php elseif (!empty($profile_errors)): ?>
    <div class="rv2-notice rv2-notice-err" style="display:block;"><i class="bi bi-exclamation-circle-fill me-2"></i><?php echo esc_html(implode(' ',$profile_errors)); ?></div>
  <?php endif; ?>

  <div class="rv2-card" style="max-width:640px;">
    <div class="rv2-card-head"><h3 class="rv2-card-title"><i class="bi bi-person-gear" style="color:var(--c-accent2);"></i><?php esc_html_e('Reviewer Information','eiu-rp'); ?></h3></div>
    <div class="rv2-card-body">

      <!-- Profile Photo Upload -->
      <div style="display:flex;align-items:center;gap:18px;margin-bottom:22px;">
        <div style="position:relative;flex-shrink:0;">
          <div id="rv-photo-circle" style="width:72px;height:72px;border-radius:50%;background:var(--c-accent2,#1a4988);display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid #e8eef8;cursor:pointer;font-size:26px;font-weight:800;color:#fff;">
            <?php if ($rv_profile_photo_url): ?>
              <img id="rv-photo-img" src="<?php echo esc_url($rv_profile_photo_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <span id="rv-photo-initial"><?php echo esc_html(strtoupper(substr($reviewer->full_name??'R',0,1))); ?></span>
            <?php endif; ?>
          </div>
          <div style="position:absolute;bottom:0;right:0;width:22px;height:22px;background:#1a4988;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;" onclick="document.getElementById('rv-photo-file').click()">
            <i class="bi bi-camera-fill" style="font-size:10px;color:#fff;"></i>
          </div>
        </div>
        <div>
          <p style="font-size:13px;font-weight:700;margin:0 0 4px;"><?php esc_html_e('Profile Photo','eiu-rp'); ?></p>
          <p style="font-size:12px;color:var(--c-muted);margin:0 0 8px;"><?php esc_html_e('JPG, PNG or WebP · max 3 MB','eiu-rp'); ?></p>
          <button type="button" class="rv2-btn rv2-btn-ghost rv2-btn-sm" onclick="document.getElementById('rv-photo-file').click()">
            <i class="bi bi-upload"></i><?php Terminology::e('upload_photo'); ?>
          </button>
          <input type="file" id="rv-photo-file" accept="image/jpeg,image/png,image/webp" style="display:none;">
          <span id="rv-photo-msg" style="font-size:12px;margin-left:8px;"></span>
        </div>
      </div>

      <form method="post">
        <?php wp_nonce_field('eiu_profile_save','eiu_profile_nonce'); ?>
        <input type="hidden" name="eiu_profile_save" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
          <div>
            <label class="rv2-label"><?php esc_html_e('Full Name','eiu-rp'); ?> <span style="color:var(--c-red);">*</span></label>
            <input type="text" name="full_name" class="form-control" value="<?php echo esc_attr($reviewer->full_name??''); ?>" required>
          </div>
          <div>
            <label class="rv2-label"><?php esc_html_e('Organization','eiu-rp'); ?></label>
            <input type="text" name="organization" class="form-control" value="<?php echo esc_attr($reviewer->organization??''); ?>">
          </div>
        </div>
        <div style="margin-bottom:20px;">
          <label class="rv2-label"><?php esc_html_e('Specialization / Research Area','eiu-rp'); ?></label>
          <input type="text" name="specialization" class="form-control" value="<?php echo esc_attr($reviewer->specialization??''); ?>" placeholder="<?php esc_attr_e('e.g. Computer Science, Medical Research…','eiu-rp'); ?>">
        </div>
        <hr style="border-color:var(--c-border);margin:0 0 20px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
          <div>
            <label class="rv2-label"><?php esc_html_e('Email','eiu-rp'); ?></label>
            <input type="email" class="form-control" value="<?php echo esc_attr($reviewer->email??''); ?>" disabled style="background:#f8f9fa;color:#9ca3af;">
            <p style="font-size:11px;color:var(--c-muted);margin:4px 0 0;"><?php esc_html_e('Contact admin to change email.','eiu-rp'); ?></p>
          </div>
          <div>
            <label class="rv2-label"><?php Terminology::e('status_tab'); ?></label>
            <div style="padding:10px 14px;background:#f8f9fa;border:1px solid var(--c-border);border-radius:6px;">
              <?php if ($reviewer->verified): ?>
                <span class="rv2-verified"><i class="bi bi-patch-check-fill"></i><?php esc_html_e('Verified Reviewer','eiu-rp'); ?></span>
              <?php else: ?>
                <span style="font-size:11px;background:#fef9c3;color:#854d0e;border-radius:20px;padding:2px 10px;font-weight:700;"><i class="bi bi-clock me-1"></i><?php esc_html_e('Pending Verification','eiu-rp'); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <button type="submit" class="rv2-btn rv2-btn-primary">
          <i class="bi bi-floppy-fill"></i><?php Terminology::e('save_changes'); ?>
        </button>
      </form>
    </div>
  </div>

<?php endif; ?>

    </div><!-- .rv2-body -->
  </div><!-- .rv2-main -->
</div><!-- #eiu-rv2 -->

<script>
(function(){
  var ajaxUrl = typeof eiuRP !== 'undefined' ? eiuRP.ajaxUrl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
  var okEl  = document.getElementById('eiu-rv-ok');
  var errEl = document.getElementById('eiu-rv-err');

  function showMsg(el, msg){
    el.innerHTML = msg;
    el.style.display = 'block';
    el.scrollIntoView({behavior:'smooth', block:'nearest'});
    setTimeout(function(){ el.style.display='none'; }, 7000);
  }

  var dash = document.getElementById('eiu-rv2');
  if (!dash) return;

  // ── Revision callout: show/hide when radio changes ─────────────
  dash.addEventListener('change', function(e){
    var radio = e.target;
    if (radio.name !== 'recommendation') return;
    var callout = document.getElementById('rv2-revision-callout');
    var hint    = document.getElementById('rv2-comments-hint');
    var label   = document.getElementById('rv2-comments-label');
    var isRev   = (radio.value === 'minor_revision' || radio.value === 'major_revision');
    if (callout) callout.style.display = isRev ? 'block' : 'none';
    if (hint)    hint.style.display    = isRev ? 'block' : 'none';
    /* Update TinyMCE editor placeholder text via the body content when empty */
    var editorId = <?php echo isset($rv_comments_editor_id) ? wp_json_encode($rv_comments_editor_id) : "''"; ?>;
    if (typeof tinymce !== 'undefined' && editorId) {
      var ed = tinymce.get(editorId);
      if (ed) {
        /* Only update placeholder if editor is currently empty */
        var currentText = ed.getContent({format:'text'}).trim();
        if (!currentText) {
          /* Set the placeholder hint in the editor's status bar */
          var statusbar = ed.getContainer ? ed.getContainer().querySelector('.mce-statusbar') : null;
          if (statusbar) {
            statusbar.title = isRev
              ? '<?php echo esc_js(__("Describe exactly what needs to be changed. Be specific.","eiu-rp")); ?>'
              : '<?php echo esc_js(__("Provide your detailed review comments here.","eiu-rp")); ?>';
          }
        }
      }
    }
  });

  dash.addEventListener('submit', function(e){
    var form = e.target.closest('.eiu-rv-rform');
    if (!form) return;
    e.preventDefault();
    var rec      = form.querySelector('[name="recommendation"]:checked');
    var isRev    = rec && (rec.value === 'minor_revision' || rec.value === 'major_revision');

    /* Read content from Quill editor */
    var comments = '';
    var editorId = <?php echo isset($rv_comments_editor_id) ? wp_json_encode($rv_comments_editor_id) : "''"; ?>;
    if (typeof Quill !== 'undefined' && window.rv_quill_instance) {
      comments = window.rv_quill_instance.root.innerHTML.trim();
      /* Also sync to textarea */
      var ta = document.getElementById(editorId);
      if (ta) ta.value = comments;
    }
    /* Fallback: read from hidden textarea */
    if (!comments || comments === '<p><br></p>') {
      var ta2 = document.getElementById(editorId);
      if (ta2) comments = ta2.value.trim();
    }
    /* Strip tags for length check (user may write a single image with no text) */
    var commentsText = (comments === '<p><br></p>' ? '' : comments.replace(/<[^>]+>/g,'').trim());

    if (!rec){
      showMsg(errEl, '<i class="bi bi-exclamation-circle me-2"></i><?php echo esc_js(__("Please select a recommendation.","eiu-rp")); ?>');
      return;
    }
    if (!commentsText){
      showMsg(errEl, '<i class="bi bi-exclamation-circle me-2"></i><?php echo esc_js(__("Please enter your review comments.","eiu-rp")); ?>');
      return;
    }
    /* Revision recommendations require detailed notes (enforced server-side too) */
    if (isRev && commentsText.length < 20){
      showMsg(errEl, '<i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo esc_js(__("Revision notes must be at least 20 characters. Please provide detailed feedback for the researcher.","eiu-rp")); ?>');
      return;
    }
    var btn = form.querySelector('.rv2-submit-review');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><?php echo esc_js(__("Submitting…","eiu-rp")); ?>';

    /* Build FormData manually so we can inject the rich HTML comments */
    var fd = new FormData(form);
    fd.set('comments', comments);

    fetch(ajaxUrl, {method:'POST', body: fd})
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.success){
          showMsg(okEl, '<i class="bi bi-check-circle-fill me-2"></i>' + (res.data&&res.data.message ? res.data.message : '<?php echo esc_js(__("Review submitted.","eiu-rp")); ?>'));
          var box = form.closest('.rv2-review-box');
          if (box) box.innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:#7c3aed;font-weight:600;"><i class="bi bi-send-check-fill"></i><?php echo esc_js(__("Review submitted successfully.","eiu-rp")); ?></div>';
        } else {
          showMsg(errEl, '<i class="bi bi-exclamation-circle me-2"></i>' + (res.data&&res.data.message ? res.data.message : '<?php echo esc_js(__("An error occurred.","eiu-rp")); ?>'));
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send-fill"></i><?php echo esc_js(__("Submit Review","eiu-rp")); ?>';
        }
      })
      .catch(function(){
        showMsg(errEl, '<i class="bi bi-wifi-off me-2"></i><?php echo esc_js(__("Network error.","eiu-rp")); ?>');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i><?php echo esc_js(__("Submit Review","eiu-rp")); ?>';
      });
  });
}());
</script>

<script>
/* ═══════════════════════════════════════════════════════════════════════
   v2.1: Reviewer Enhancements JS
   - Profile photo upload
   - Save co-reviewer + notes
   - Update article status
   ═════════════════════════════════════════════════════════════════════ */
(function(){
'use strict';
var ajax  = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
var nonce = '<?php echo esc_js(wp_create_nonce("eiu_rp_frontend")); ?>';

function showMsg2(id, msg) {
  var el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(function(){ el.style.display = 'none'; }, 5000);
}

/* ── Profile photo upload ─────────────────────────────────────── */
var photoFile = document.getElementById('rv-photo-file');
if (photoFile) {
  photoFile.addEventListener('change', function(){
    var file = this.files[0];
    if (!file) return;
    var msgEl = document.getElementById('rv-photo-msg');
    if (msgEl) { msgEl.textContent = '<?php echo esc_js(__("Uploading…","eiu-rp")); ?>'; msgEl.style.color='#6b7280'; }
    var fd = new FormData();
    fd.append('action','eiu_rp_upload_profile_photo');
    fd.append('nonce', nonce);
    fd.append('photo', file);
    fetch(ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(res.success){
          var imgUrl=res.data.thumb_url||res.data.full_url;
          /* Update profile circle on profile tab */
          var circle=document.getElementById('rv-photo-circle');
          if(circle){ circle.innerHTML='<img src="'+imgUrl+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'; }
          /* Also update sidebar avatar */
          var sidebarAv=document.getElementById('rv2-sidebar-av');
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

/* ── Assign Co-Reviewers ───────────────────────────────────────── */
var assignCoBtn=document.getElementById('rv-assign-co-btn');
if(assignCoBtn){
  /* Select All / Clear All helpers */
  var selAll=document.getElementById('rv-co-select-all');
  var clrAll=document.getElementById('rv-co-clear-all');
  if(selAll) selAll.addEventListener('click',function(){ document.querySelectorAll('.rv-co-check').forEach(function(c){c.checked=true;}); });
  if(clrAll) clrAll.addEventListener('click',function(){ document.querySelectorAll('.rv-co-check').forEach(function(c){c.checked=false;}); });

  assignCoBtn.addEventListener('click',function(){
    var reviewId=this.dataset.reviewId||'';
    var checked=[];
    document.querySelectorAll('.rv-co-check:checked').forEach(function(c){ checked.push(c.value); });
    var btn=this;
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span><?php echo esc_js(__("Assigning…","eiu-rp")); ?>';
    var fd=new FormData();
    fd.append('action','eiu_rp_assign_co_reviewer');
    fd.append('nonce',nonce);
    fd.append('review_id',reviewId);
    fd.append('co_reviewer_ids',JSON.stringify(checked));
    fetch(ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-person-check-fill"></i><?php echo esc_js(__("Assign Selected as Co-Reviewers","eiu-rp")); ?>';
        if(res.success){
          showMsg2('rv-co-assign-ok',res.data.message||'<?php echo esc_js(__("Assigned.","eiu-rp")); ?>');
          /* Update row backgrounds and badges for newly notified reviewers */
          var notified=res.data.notified||[];
          document.querySelectorAll('.rv-co-check').forEach(function(chk){
            var id=chk.value;
            var row=chk.closest('.rv-co-row');
            var badge=document.querySelector('.rv-co-badge-'+id);
            if(chk.checked){
              if(row) row.style.background='#f0fdf4';
              if(badge){ badge.style.display=''; }
            } else {
              if(row) row.style.background='#fff';
              if(badge){ badge.style.display='none'; }
            }
          });
          /* Update assigned count badge */
          var countBadge=assignCoBtn.closest('.rv2-card');
          if(countBadge){
            var cb=countBadge.querySelector('.rv2-card-head span');
            if(cb) cb.textContent=checked.length+' <?php echo esc_js(__("assigned","eiu-rp")); ?>';
          }
        } else {
          showMsg2('rv-co-assign-err',(res.data&&res.data.message)||'<?php echo esc_js(__("Error.","eiu-rp")); ?>');
        }
      })
      .catch(function(){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-person-check-fill"></i><?php echo esc_js(__("Assign Selected as Co-Reviewers","eiu-rp")); ?>';
        showMsg2('rv-co-assign-err','<?php echo esc_js(__("Network error.","eiu-rp")); ?>');
      });
  });
}

/* ── Save Private Notes (notifies co-reviewers) ─────────────────── */
var saveNotesBtn=document.getElementById('rv-save-notes-btn');
if(saveNotesBtn){
  saveNotesBtn.addEventListener('click',function(){
    var reviewId=(this.dataset.reviewId)||'';
    /* Collect currently checked co-reviewer IDs */
    var coRevIds=[];
    document.querySelectorAll('.rv-co-check:checked').forEach(function(c){ coRevIds.push(c.value); });
    var coReviewer=JSON.stringify(coRevIds);
    var notes=(document.getElementById('rv-reviewer-notes')||{}).value||'';
    var btn=this;
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>';
    var fd=new FormData();
    fd.append('action','eiu_rp_reviewer_save_notes');
    fd.append('nonce',nonce);
    fd.append('review_id',reviewId);
    fd.append('co_reviewer',coReviewer);
    fd.append('reviewer_notes',notes);
    fetch(ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-send-fill"></i><?php echo esc_js(__("Save & Notify Co-Reviewers","eiu-rp")); ?>';
        showMsg2(res.success?'rv-notes-ok':'rv-notes-err',(res.data&&res.data.message)||'');
      })
      .catch(function(){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-send-fill"></i><?php echo esc_js(__("Save & Notify Co-Reviewers","eiu-rp")); ?>';
      });
  });
}

/* ── Article content tab switcher — exposed on window so onclick can reach it ── */
/* NOTE: defined inside an IIFE so we explicitly assign to window */
/* ── v2.0.1: Reviewer Application Status Update ─────────────────── */
(function(){
  'use strict';
  var submitBtn = document.getElementById('rv-appv-submit-btn');
  if (!submitBtn) return;
  var ajax = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

  submitBtn.addEventListener('click', function(){
    var appId  = this.dataset.appId;
    var nonce  = this.dataset.nonce;
    var status = (document.getElementById('rv-appv-status')||{}).value||'';
    var notes  = (document.getElementById('rv-appv-notes')||{}).value||'';
    var okEl   = document.getElementById('rv-appv-ok');
    var errEl  = document.getElementById('rv-appv-err');

    if (!status) {
      if(errEl){errEl.textContent='<?php echo esc_js(__('Please select a decision.','eiu-rp')); ?>';errEl.style.display='block';}
      return;
    }
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><?php echo esc_js(__('Saving…','eiu-rp')); ?>';
    if(okEl) okEl.style.display='none';
    if(errEl) errEl.style.display='none';
    var fd = new FormData();
    fd.append('action','eiu_rp_reviewer_application_set_status');
    fd.append('nonce',nonce);
    fd.append('application_id',appId);
    fd.append('status',status);
    fd.append('admin_notes',notes);
    fetch(ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-check2-circle"></i><?php echo esc_js(__('Save Decision','eiu-rp')); ?>';
        if(res.success){
          if(okEl){okEl.textContent=(res.data&&res.data.message)||'<?php echo esc_js(__('Saved.','eiu-rp')); ?>';okEl.style.display='block';}
          var sel=document.getElementById('rv-appv-status');
          var txt=document.getElementById('rv-appv-notes');
          if(sel) sel.disabled=true;
          if(txt) txt.disabled=true;
          btn.disabled=true;
        } else {
          if(errEl){errEl.textContent=(res.data&&res.data.message)||'<?php echo esc_js(__('Error. Please try again.','eiu-rp')); ?>';errEl.style.display='block';}
        }
      })
      .catch(function(){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-check2-circle"></i><?php echo esc_js(__('Save Decision','eiu-rp')); ?>';
        if(errEl){errEl.textContent='<?php echo esc_js(__('Network error.','eiu-rp')); ?>';errEl.style.display='block';}
      });
  });
}());

window.rv2SwitchTab = function(btn, panelId){
  /* Deactivate all tabs and panels */
  document.querySelectorAll('#rv2-art-tabstrip .rv2-art-tab').forEach(function(t){
    t.classList.remove('active');
    t.setAttribute('aria-selected','false');
  });
  document.querySelectorAll('#eiu-rv2 .rv2-tab-panel').forEach(function(p){
    p.classList.remove('active');
  });
  /* Activate the clicked tab */
  btn.classList.add('active');
  btn.setAttribute('aria-selected','true');
  var panel = document.getElementById(panelId);
  if(panel){
    panel.classList.add('active');
    if(window.innerWidth <= 640){
      panel.scrollIntoView({behavior:'smooth', block:'nearest'});
    }
  }
};

/* Also wire via event delegation as a belt-and-braces fallback */
(function(){
  var strip = document.getElementById('rv2-art-tabstrip');
  if(!strip) return;
  strip.addEventListener('click', function(e){
    var btn = e.target.closest('.rv2-art-tab');
    if(!btn) return;
    var panelId = btn.getAttribute('aria-controls');
    if(panelId) window.rv2SwitchTab(btn, panelId);
  });
}());

/* ── Reviewer Edit Article ─────────────────────────────────────── */
(function(){
'use strict';
var editWrap=document.getElementById('rv-edit-wrap');
if(!editWrap) return;

var saveBtn  = document.getElementById('rv-edit-save-btn');
var okEl     = document.getElementById('rv-edit-ok');
var errEl    = document.getElementById('rv-edit-err');
var ajaxUrl  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

function editMsg(el,msg){ el.textContent=msg; el.style.display='block'; setTimeout(function(){ el.style.display='none'; },6000); }

/* ── Thumbnail instant upload on file select ── */
var thumbInput=document.getElementById('rv-edit-thumb-file');
if(thumbInput){
  thumbInput.addEventListener('change',function(){
    var file=this.files[0]; if(!file) return;
    var status=document.getElementById('rv-thumb-upload-status');
    status.textContent='<?php echo esc_js(__('Uploading…','eiu-rp')); ?>';
    status.style.color='#6b7280';
    var fd=new FormData();
    fd.append('action','eiu_rp_reviewer_upload_thumb');
    fd.append('nonce',saveBtn.dataset.nonce);
    fd.append('article_id',saveBtn.dataset.articleId);
    fd.append('thumbnail',file);
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(res.success){
          status.textContent='<?php echo esc_js(__('Thumbnail updated.','eiu-rp')); ?>';
          status.style.color='#065f46';
          var preview=document.getElementById('rv-edit-thumb-preview');
          if(preview){
            preview.innerHTML='<img src="'+res.data.url+'" style="width:100%;height:100%;object-fit:cover;border-radius:8px;" alt="">';
          }
        } else {
          status.textContent=(res.data&&res.data.message)||'<?php echo esc_js(__('Upload failed.','eiu-rp')); ?>';
          status.style.color='#991b1b';
        }
      })
      .catch(function(){ status.textContent='<?php echo esc_js(__('Network error.','eiu-rp')); ?>'; status.style.color='#991b1b'; });
  });
}

/* ── Article file instant upload on select ── */
var fileInput=document.getElementById('rv-edit-article-file');
if(fileInput){
  fileInput.addEventListener('change',function(){
    var file=this.files[0]; if(!file) return;
    var status=document.getElementById('rv-file-upload-status');
    status.textContent='<?php echo esc_js(__('Uploading file…','eiu-rp')); ?>';
    status.style.color='#6b7280';
    var fd=new FormData();
    fd.append('action','eiu_rp_reviewer_upload_file');
    fd.append('nonce',saveBtn.dataset.nonce);
    fd.append('article_id',saveBtn.dataset.articleId);
    fd.append('article_file',file);
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(res.success){
          status.textContent='<?php echo esc_js(__('File replaced.','eiu-rp')); ?> '+(res.data.file_name||'');
          status.style.color='#065f46';
          var nameEl=document.getElementById('rv-edit-filename');
          if(nameEl && res.data.file_name) nameEl.textContent=res.data.file_name;
        } else {
          status.textContent=(res.data&&res.data.message)||'<?php echo esc_js(__('Upload failed.','eiu-rp')); ?>';
          status.style.color='#991b1b';
        }
      })
      .catch(function(){ status.textContent='<?php echo esc_js(__('Network error.','eiu-rp')); ?>'; status.style.color='#991b1b'; });
  });
}

/* ── Save abstract + references ── */
if(saveBtn){
  saveBtn.addEventListener('click',function(){
    var articleId=this.dataset.articleId;
    var nonce    =this.dataset.nonce;
    var abstract =(document.getElementById('rv-edit-abstract')||{}).value||'';
    var refs     =(document.getElementById('rv-edit-references')||{}).value||'';
    var btn=this;
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span><?php echo esc_js(__('Saving…','eiu-rp')); ?>';
    var fd=new FormData();
    fd.append('action','eiu_rp_reviewer_edit_article');
    fd.append('nonce',nonce);
    fd.append('article_id',articleId);
    fd.append('abstract',abstract);
    fd.append('references',refs);
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-check2-circle"></i><?php echo esc_js(__('Save Changes','eiu-rp')); ?>';
        if(res.success){
          editMsg(okEl,(res.data&&res.data.message)||'<?php echo esc_js(__('Saved.','eiu-rp')); ?>');
          /* Refresh abstract panel content live */
          var absPanel=document.getElementById('rv2-panel-abstract');
          if(absPanel){
            var absDiv=absPanel.querySelector('.rv2-abstract');
            if(absDiv) absDiv.textContent=abstract;
          }
        } else {
          editMsg(errEl,(res.data&&res.data.message)||'<?php echo esc_js(__('Error saving.','eiu-rp')); ?>');
        }
      })
      .catch(function(){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-check2-circle"></i><?php echo esc_js(__('Save Changes','eiu-rp')); ?>';
        editMsg(errEl,'<?php echo esc_js(__('Network error.','eiu-rp')); ?>');
      });
  });
}
}());

/* ── Revision notes toggle ────────────────────────────────────── */
var statusSelect=document.getElementById('rv-status-select');
var revNotesWrap=document.getElementById('rv-revision-notes-wrap');
var publishDateWrap=document.getElementById('rv-publish-date-wrap');
if(statusSelect){
  statusSelect.addEventListener('change',function(){
    if(revNotesWrap)   revNotesWrap.style.display=(this.value==='revision_required')?'block':'none';
    if(publishDateWrap) publishDateWrap.style.display=(this.value==='published')?'block':'none';
  });
}

/* ── Update article status ────────────────────────────────────── */
var updateStatusBtn=document.getElementById('rv-update-status-btn');
if(updateStatusBtn){
  updateStatusBtn.addEventListener('click',function(){
    var reviewId=(this.dataset.reviewId)||'';
    var newStatus=(document.getElementById('rv-status-select')||{}).value||'';
    var revNotes=(document.getElementById('rv-revision-notes-field')||{}).value||'';
    if(!newStatus) return;
    var btn=this;
    btn.disabled=true;
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>';
    var fd=new FormData();
    var publishDate=(document.getElementById('rv-publish-date')||{}).value||'';
    fd.append('action','eiu_rp_reviewer_update_status');
    fd.append('nonce',nonce);
    fd.append('review_id',reviewId);
    fd.append('status',newStatus);
    fd.append('revision_notes',revNotes);
    fd.append('published_at',publishDate);
    fetch(ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-check-circle"></i><?php echo esc_js(__("Update Status","eiu-rp")); ?>';
        showMsg2(res.success?'rv-status-ok':'rv-status-err',(res.data&&res.data.message)||'');
        if(res.success&&revNotesWrap) revNotesWrap.style.display='none';
      })
      .catch(function(){
        btn.disabled=false;
        btn.innerHTML='<i class="bi bi-check-circle"></i><?php echo esc_js(__("Update Status","eiu-rp")); ?>';
      });
  });
}

}());
</script>
<!-- Quill Rich Text Editor — Reviewer Comments -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
<style>
#rv-quill-wrap.eiu-quill-wrap .ql-editor { min-height:220px; }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
<?php if ( isset($rv_comments_editor_id) ): ?>
(function(){
'use strict';
var rv_ajax  = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
var rv_nonce = '<?php echo esc_js(wp_create_nonce("eiu_rp_frontend")); ?>';
var rv_ta_id = <?php echo wp_json_encode($rv_comments_editor_id); ?>;

function rv_img_handler(q){
  var inp=document.createElement('input');
  inp.type='file'; inp.accept='image/jpeg,image/png,image/webp';
  inp.click();
  inp.onchange=function(){
    var file=inp.files[0]; if(!file) return;
    var fd=new FormData();
    fd.append('action','eiu_rp_upload_media_image');
    fd.append('nonce',rv_nonce); fd.append('image',file);
    fetch(rv_ajax,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(res){
        if(res.success&&res.data&&res.data.url){
          var range=q.getSelection(true);
          q.insertEmbed(range.index,'image',res.data.url,'user');
        }
      });
  };
}

function rv_init_quill(){
  var container=document.getElementById('rv-comments-quill');
  if(!container||window.rv_quill_instance) return;
  window.rv_quill_instance=new Quill(container,{
    theme:'snow',
    placeholder:'<?php echo esc_js(__("Provide your detailed review comments here… (bold, lists, links, images all supported)","eiu-rp")); ?>',
    modules:{
      toolbar:{
        container:[
          [{'header':[1,2,3,false]},'bold','italic','underline'],
          [{'list':'ordered'},{'list':'bullet'},'blockquote'],
          ['link','image','clean']
        ],
        handlers:{image:function(){rv_img_handler(window.rv_quill_instance);}}
      }
    }
  });
  window.rv_quill_instance.on('text-change',function(){
    var ta=document.getElementById(rv_ta_id);
    if(ta) ta.value=window.rv_quill_instance.root.innerHTML;
  });
}

if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',rv_init_quill);
} else {
  rv_init_quill();
}
}());
<?php endif; ?>
</script>


<?php if ( isset($rv_comments_editor_id) ): ?>
<!-- ═══════════════════════════════════════════════════════════════════════
  TinyMCE 5 — Standalone CDN loader for Reviewer Comments editor.
  ═════════════════════════════════════════════════════════════════════════ -->

<?php endif; ?>
