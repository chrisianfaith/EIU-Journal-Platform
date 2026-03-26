<?php
/**
 * Core Plugin Bootstrap.
 *
 * @package EIU_Research_Publication
 * @subpackage Core
 */

namespace EIU_RP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 *
 * Main plugin orchestration class — singleton pattern.
 */
final class Plugin {

    private static ?Plugin $instance = null;
    public string $version = EIU_RP_VERSION;
    private array $services = array();

    private function __construct() {
        $this->load_dependencies();
        $this->init_services();
        $this->register_hooks();
    }

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_dependencies(): void {
        require_once EIU_RP_PATH . 'includes/core/class-activator.php';
        require_once EIU_RP_PATH . 'includes/core/class-deactivator.php';
        require_once EIU_RP_PATH . 'includes/core/class-post-types.php';
        require_once EIU_RP_PATH . 'includes/core/class-taxonomies.php';
        require_once EIU_RP_PATH . 'includes/models/class-article.php';
        require_once EIU_RP_PATH . 'includes/models/class-reviewer.php';
        require_once EIU_RP_PATH . 'includes/models/class-review.php';
        require_once EIU_RP_PATH . 'includes/models/class-activity-log.php';
        require_once EIU_RP_PATH . 'includes/roles/class-roles-manager.php';
        require_once EIU_RP_PATH . 'includes/roles/class-researcher-role.php'; // v1.6
        require_once EIU_RP_PATH . 'includes/security/class-security.php';
        require_once EIU_RP_PATH . 'includes/security/class-file-upload.php';
        require_once EIU_RP_PATH . 'includes/email/class-mailer.php';
        require_once EIU_RP_PATH . 'includes/email/class-email-templates.php';
        require_once EIU_RP_PATH . 'includes/admin/class-admin.php';
        require_once EIU_RP_PATH . 'includes/admin/class-dashboard.php';
        require_once EIU_RP_PATH . 'includes/admin/class-articles-list.php';
        require_once EIU_RP_PATH . 'includes/admin/class-reviewers-list.php';
        require_once EIU_RP_PATH . 'includes/admin/class-reviews-list.php';
        require_once EIU_RP_PATH . 'includes/admin/class-settings.php';
        require_once EIU_RP_PATH . 'includes/admin/class-activity-log-page.php';
        require_once EIU_RP_PATH . 'includes/admin/class-reports.php';
        require_once EIU_RP_PATH . 'includes/admin/class-issn-meta-box.php';
        require_once EIU_RP_PATH . 'includes/admin/class-email-template-editor.php'; // v1.6
        require_once EIU_RP_PATH . 'includes/admin/class-shortcodes-page.php';       // v1.6
        require_once EIU_RP_PATH . 'includes/admin/class-smtp-settings.php';         // v1.6
        require_once EIU_RP_PATH . 'includes/core/class-upgrader.php';
        Upgrader::maybe_upgrade();
        require_once EIU_RP_PATH . 'includes/api/class-article-edit-handler.php';
        require_once EIU_RP_PATH . 'includes/api/class-submission-handler.php';
        require_once EIU_RP_PATH . 'includes/api/class-reviewer-handler.php';
        require_once EIU_RP_PATH . 'includes/api/class-review-handler.php';
        require_once EIU_RP_PATH . 'includes/api/class-auto-assignment.php'; // v1.5
        require_once EIU_RP_PATH . 'includes/api/class-researcher-handler.php'; // v1.6
        require_once EIU_RP_PATH . 'includes/models/class-application.php';     // v1.9
        require_once EIU_RP_PATH . 'includes/api/class-application-handler.php'; // v1.9
        require_once EIU_RP_PATH . 'includes/admin/class-applications-list.php'; // v1.9
        require_once EIU_RP_PATH . 'includes/admin/class-download-leads.php';     // v1.9
        require_once EIU_RP_PATH . 'includes/api/class-frontend-handler.php';
        require_once EIU_RP_PATH . 'includes/utils/class-helpers.php';
        require_once EIU_RP_PATH . 'includes/utils/class-terminology.php'; // v1.9
        require_once EIU_RP_PATH . 'includes/utils/class-template-loader.php';
    }

    private function init_services(): void {
        // Register researcher role on every load (idempotent).
        \EIU_RP\Roles\Researcher_Role::register();

        $this->services['post_types']           = new Post_Types();
        $this->services['taxonomies']           = new Taxonomies();
        $this->services['roles']                = new \EIU_RP\Roles\Roles_Manager();
        $this->services['security']             = new \EIU_RP\Security\Security();
        $this->services['file_upload']          = new \EIU_RP\Security\File_Upload();
        $this->services['mailer']               = new \EIU_RP\Email\Mailer();
        $this->services['submission_handler']   = new \EIU_RP\API\Submission_Handler();
        $this->services['reviewer_handler']     = new \EIU_RP\API\Reviewer_Handler();
        $this->services['review_handler']       = new \EIU_RP\API\Review_Handler();
        $this->services['auto_assignment']      = new \EIU_RP\API\Auto_Assignment(); // v1.5
        $this->services['researcher_handler']   = new \EIU_RP\API\Researcher_Handler(); // v1.6
        $this->services['frontend_handler']     = new \EIU_RP\API\Frontend_Handler();
        $this->services['article_edit_handler']   = new \EIU_RP\API\Article_Edit_Handler();
        $this->services['application_handler']    = new \EIU_RP\API\Application_Handler(); // v1.9

        if ( is_admin() ) {
            $this->services['admin']                = new \EIU_RP\Admin\Admin();
            $this->services['email_tpl_editor']     = new \EIU_RP\Admin\Email_Template_Editor(); // v1.6
            $this->services['smtp_settings']        = new \EIU_RP\Admin\SMTP_Settings();         // v1.6
        }
    }

    private function register_hooks(): void {
        add_action( 'plugins_loaded',        array( 'EIU_RP\\Core\\Upgrader', 'maybe_upgrade' ), 5 );
        // v1.5: flush rate-limit transients once after rate limit was raised.
        add_action( 'init', array( $this, 'maybe_flush_rate_limits' ) );
        add_action( 'init',                  array( $this, 'load_textdomain' ) );
        add_action( 'init',                  array( $this, 'register_shortcodes' ) );
        add_action( 'wp_head',               array( $this, 'inject_article_og_meta' ), 1 ); // v2.0.1: OG/social share meta
        add_action( 'wp_head',               array( $this, 'inject_article_theme_overrides' ), 20 ); // v2.0.1: hide theme post meta + native comments
        add_filter( 'comments_template',     array( $this, 'suppress_native_comments_template' ) );  // v2.0.1: hide WP native comment form on articles
        add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_frontend_assets' ) );
        // v2.1: Enqueue full TinyMCE / wp_editor scripts on pages that contain
        // editor shortcodes. Must run on wp_enqueue_scripts so scripts are
        // registered before wp_head() fires — wp_editor() called inside a
        // shortcode/the_content() fires AFTER wp_head, so without this hook the
        // TinyMCE initialisation scripts are never output to the page.
        add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_editor_assets' ) );
        add_filter( 'the_content',           array( $this, 'inject_article_content' ) );
        // v1.3: auto-create reviewer profile on login, ensure reviewer-access page exists
        add_action( 'wp_login',              array( $this, 'maybe_create_reviewer_profile' ), 10, 2 );
        add_action( 'admin_init',            array( $this, 'maybe_create_reviewer_access_page' ) );
        // v1.7: Personalised nav-bar user menu for logged-in researchers/reviewers.
        add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_nav_user_menu_assets' ) );
        add_action( 'wp_footer',             array( $this, 'render_nav_user_menu' ) );
        // v1.8: Server-side hide of login menu items when user is logged in.
        add_filter( 'wp_nav_menu_items',     array( $this, 'hide_login_menu_items' ), 20, 2 );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            EIU_RP_TEXT_DOMAIN,
            false,
            dirname( EIU_RP_BASENAME ) . '/languages'
        );
    }

    public function register_shortcodes(): void {
        add_shortcode( 'eiu_submission_form',       array( $this, 'render_submission_form' ) );
        add_shortcode( 'eiu_reviewer_dashboard',    array( $this, 'render_reviewer_dashboard' ) );
        add_shortcode( 'eiu_article_list',          array( $this, 'render_article_list' ) );
        add_shortcode( 'eiu_article_categories',    array( $this, 'render_article_categories' ) );
        add_shortcode( 'eiu_article_header',        array( $this, 'render_article_header' ) );   // v2.0.1
        add_shortcode( 'eiu_article_search',        array( $this, 'render_article_search' ) );   // v2.0.1
        add_shortcode( 'eiu_researcher_dashboard',  array( $this, 'render_researcher_dashboard' ) ); // v1.6
        add_shortcode( 'eiu_researcher_login',      array( $this, 'render_researcher_login' ) );      // v1.6
        add_shortcode( 'eiu_unified_login',         array( $this, 'render_unified_login' ) );         // v1.9
        add_shortcode( 'eiu_apply_researcher',      array( $this, 'render_apply_researcher' ) );      // v1.9
    }

    public function enqueue_frontend_assets(): void {
        global $post;
        $load = false;
        if ( $post && has_shortcode( $post->post_content, 'eiu_submission_form' ) )    $load = true;
        if ( $post && has_shortcode( $post->post_content, 'eiu_reviewer_dashboard' ) ) $load = true;
        if ( is_singular( 'eiu_article' ) ) $load = true;
        if ( ! $load ) return;

        wp_enqueue_style( 'eiu-rp-frontend', EIU_RP_URL . 'assets/css/frontend.css', array(), EIU_RP_VERSION );
        wp_enqueue_script( 'eiu-rp-frontend', EIU_RP_URL . 'assets/js/frontend.js', array( 'jquery' ), EIU_RP_VERSION, true );
        wp_localize_script( 'eiu-rp-frontend', 'eiuRP', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'eiu_rp_frontend' ),
            'i18n'    => array(
                'submitting'   => __( 'Submitting…', 'eiu-rp' ),
                'success'      => __( 'Article submitted successfully.', 'eiu-rp' ),
                'error'        => __( 'An error occurred. Please try again.', 'eiu-rp' ),
                'fileSizeErr'  => __( 'File size exceeds the allowed limit.', 'eiu-rp' ),
                'fileTypeErr'  => __( 'Invalid file type. Accepted: PDF, PPT.', 'eiu-rp' ),
            ),
        ) );
    }

    /**
     * v2.1: Enqueue WordPress editor (TinyMCE) scripts on any frontend page
     * that carries a shortcode containing a wp_editor() instance.
     *
     * WHY THIS IS NEEDED
     * ------------------
     * wp_editor() is called inside a shortcode, which is executed inside
     * the_content(). By that point wp_head() has already fired and the
     * browser has already received the <head> section. If wp_enqueue_editor()
     * was not called BEFORE wp_head(), TinyMCE's JS init scripts are never
     * included and the editor degrades to a plain <textarea>.
     *
     * This method is hooked on wp_enqueue_scripts (runs before wp_head) and
     * checks whether the current page carries a relevant shortcode. When it
     * does, it calls wp_enqueue_editor() which registers and enqueues every
     * script TinyMCE needs (tinymce.min.js, editor.min.js, etc.) so they are
     * output inside <head> and the full editor initialises correctly.
     *
     * SCOPE
     * -----
     * Only fires on pages that actually contain one of the three shortcodes
     * that host a wp_editor() instance:
     *   [eiu_submission_form]    — Researcher Abstract + References
     *   [eiu_researcher_dashboard] — Resubmit Abstract
     *   [eiu_reviewer_dashboard]   — Reviewer Comments
     *
     * Does NOT load editor scripts on any other page.
     */
    public function enqueue_editor_assets(): void {
        global $post;
        if ( ! $post ) {
            return;
        }

        $editor_shortcodes = array(
            'eiu_submission_form',
            'eiu_researcher_dashboard',
            'eiu_reviewer_dashboard',
        );

        $needs_editor = false;
        foreach ( $editor_shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                $needs_editor = true;
                break;
            }
        }

        if ( ! $needs_editor ) {
            return;
        }

        // wp_enqueue_media() adds wp-plupload, media-editor, and the thickbox
        // CSS/JS needed for the WP media uploader modal.
        // v2.2: TinyMCE replaced with Quill.js (CDN, same as Bootstrap).
        // wp_enqueue_editor() is no longer needed — Quill has no WP dependency.
        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }
    }

    /**
     * v1.7: Enqueue the nav user-menu stylesheet (sitewide, tiny — only when logged in).
     * The JS data payload is added here so it is available on every frontend page,
     * not just pages that carry a plugin shortcode.
     */
    /**
     * v1.7 (fixed): Enqueue nav user-menu using its OWN dedicated handle.
     *
     * Root-cause of the previous invisible widget:
     *   wp_add_inline_script( 'eiu-rp-frontend', ... ) was called on a handle
     *   that is only enqueued on shortcode pages. WordPress silently discards
     *   inline scripts attached to un-registered handles, so eiuNavUser was
     *   never printed and nav-user-menu.js never loaded on other pages.
     *
     * Fix: use a standalone 'eiu-rp-nav-menu' handle that is always
     * registered+enqueued independently, with wp_localize_script for data.
     */
    public function enqueue_nav_user_menu_assets(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        $user          = wp_get_current_user();
        $roles         = (array) $user->roles;
        $is_researcher = in_array( 'eiu_researcher', $roles, true );
        $is_reviewer   = in_array( 'eiu_reviewer',   $roles, true );
        if ( ! $is_researcher && ! $is_reviewer && ! current_user_can( 'administrator' ) ) {
            return;
        }

        if ( $is_reviewer ) {
            $role_label    = __( 'Reviewer', 'eiu-rp' );
            $dashboard_id  = get_option( 'eiu_rp_reviewer_access_page_id' );
            $dashboard_url = $dashboard_id ? get_permalink( $dashboard_id ) : home_url();
        } elseif ( $is_researcher ) {
            $role_label    = __( 'Author', 'eiu-rp' );
            $dashboard_id  = get_option( 'eiu_rp_researcher_dashboard_page_id' );
            $dashboard_url = $dashboard_id ? get_permalink( $dashboard_id ) : home_url();
        } else {
            $role_label    = __( 'Admin', 'eiu-rp' );
            $dashboard_url = admin_url();
        }

        $avatar_url  = get_avatar_url( $user->ID, array( 'size' => 80, 'default' => 'identicon' ) );
        $profile_url = ( $is_researcher || $is_reviewer )
            ? add_query_arg( 'tab', 'profile', $dashboard_url )
            : admin_url( 'profile.php' );

        /* CSS — always enqueue sitewide */
        wp_enqueue_style(
            'eiu-rp-nav-menu',
            EIU_RP_URL . 'assets/css/nav-user-menu.css',
            array(),
            EIU_RP_VERSION
        );

        /* JS — dedicated handle, no jQuery, loads in footer */
        wp_register_script(
            'eiu-rp-nav-menu',
            EIU_RP_URL . 'assets/js/nav-user-menu.js',
            array(),
            EIU_RP_VERSION,
            true
        );

        /* wp_localize_script works reliably because the handle is registered above */

        // Collect all login-page URLs the plugin knows about so JS can
        // find and hide login menu items by exact href match as well.
        $researcher_login_id  = get_option( 'eiu_rp_researcher_login_page_id' );
        $reviewer_access_id   = get_option( 'eiu_rp_reviewer_access_page_id' );
        $unified_login_id     = get_option( 'eiu_rp_unified_login_page_id' );
        $login_page_urls      = array_values( array_filter( array(
            $unified_login_id    ? get_permalink( $unified_login_id )    : '',
            $researcher_login_id ? get_permalink( $researcher_login_id ) : '',
            $reviewer_access_id  ? get_permalink( $reviewer_access_id )  : '',
            home_url( '/login/' ),
            home_url( '/researcher/' ),
            home_url( '/reviewer/' ),
            wp_login_url(),
        ) ) );

        wp_localize_script(
            'eiu-rp-nav-menu',
            'eiuNavUser',
            array(
                'name'          => $user->display_name ?: $user->user_login,
                'firstName'     => get_user_meta( $user->ID, 'first_name', true ) ?: $user->display_name,
                'role'          => $role_label,
                'avatarUrl'     => esc_url_raw( $avatar_url ),
                'dashboardUrl'  => esc_url_raw( $dashboard_url ),
                'profileUrl'    => esc_url_raw( $profile_url ),
                'logoutUrl'     => esc_url_raw( wp_logout_url( home_url() ) ),
                'loginPageUrls' => array_map( 'esc_url_raw', $login_page_urls ),
            )
        );

        wp_enqueue_script( 'eiu-rp-nav-menu' );
    }

    /**
     * v1.7: Output the nav user-menu widget HTML into wp_footer.
     * The widget is hidden by default; JS positions it inside the nav.
     * This approach is theme-agnostic — it does not require Walker customisation.
     */
    public function render_nav_user_menu(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;
        if ( ! in_array( 'eiu_researcher', $roles, true )
            && ! in_array( 'eiu_reviewer', $roles, true )
            && ! current_user_can( 'administrator' ) ) {
            return;
        }
        // The widget is rendered here and moved into the nav via JS.
        ?>
        <div id="eiu-nav-user-widget" aria-label="<?php esc_attr_e( 'User menu', 'eiu-rp' ); ?>" style="display:none;">
          <div class="eiu-nav-trigger" role="button" tabindex="0" aria-haspopup="true" aria-expanded="false">
            <img class="eiu-nav-avatar" src="" alt="" aria-hidden="true">
            <span class="eiu-nav-greeting"></span>
            <svg class="eiu-nav-caret" width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true">
              <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="eiu-nav-dropdown" role="menu">
            <a class="eiu-nav-dd-item" href="#" data-eiu-href="dashboard" role="menuitem">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
              <?php esc_html_e( 'Dashboard', 'eiu-rp' ); ?>
            </a>
            <a class="eiu-nav-dd-item" href="#" data-eiu-href="profile" role="menuitem">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <?php esc_html_e( 'My Profile', 'eiu-rp' ); ?>
            </a>
            <div class="eiu-nav-dd-divider" aria-hidden="true"></div>
            <a class="eiu-nav-dd-item eiu-nav-dd-logout" href="#" data-eiu-href="logout" role="menuitem">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              <?php esc_html_e( 'Sign Out', 'eiu-rp' ); ?>
            </a>
          </div>
        </div>
        <?php
    }

    /**
     * v1.8: Server-side filter — hide login menu items for logged-in researchers/reviewers.
     *
     * Adds class="eiu-rp-login-hidden" to any <li> whose anchor href matches
     * a known login URL or whose text/class signals "login / sign in / account".
     * The class is set to display:none via the nav-user-menu.css stylesheet.
     *
     * This runs server-side so the hiding works even before JS initialises,
     * and on cached pages that may serve cached HTML to logged-in users.
     *
     * @param string   $items HTML string of menu items.
     * @param stdClass $args  Menu arguments.
     * @return string Filtered items HTML.
     */
    public function hide_login_menu_items( string $items, object $args ): string {
        if ( ! is_user_logged_in() ) {
            return $items;
        }
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;
        if ( ! in_array( 'eiu_researcher', $roles, true )
            && ! in_array( 'eiu_reviewer',   $roles, true )
            && ! current_user_can( 'administrator' ) ) {
            return $items;
        }

        // Build the list of login URLs to match against.
        $researcher_login_id = get_option( 'eiu_rp_researcher_login_page_id' );
        $reviewer_access_id  = get_option( 'eiu_rp_reviewer_access_page_id' );
        $unified_login_id    = get_option( 'eiu_rp_unified_login_page_id' );

        $login_urls = array_filter( array(
            $unified_login_id    ? get_permalink( $unified_login_id )    : '',
            $researcher_login_id ? get_permalink( $researcher_login_id ) : '',
            $reviewer_access_id  ? get_permalink( $reviewer_access_id )  : '',
            home_url( '/login/' ),
            home_url( '/researcher/' ),
            home_url( '/reviewer/' ),
            wp_login_url(),
        ) );

        // Use DOMDocument to parse and annotate matching <li> elements.
        if ( ! class_exists( 'DOMDocument' ) ) {
            return $items;
        }

        libxml_use_internal_errors( true );
        $doc = new \DOMDocument();
        // Wrap in a root element; use UTF-8 charset meta so entities are preserved.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><ul>' . $items . '</ul>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $li_nodes = $doc->getElementsByTagName( 'li' );
        $to_hide  = array();

        foreach ( $li_nodes as $li ) {
            $anchors = $li->getElementsByTagName( 'a' );
            foreach ( $anchors as $a ) {
                $href      = $a->getAttribute( 'href' );
                $link_text = strtolower( trim( $a->textContent ) );

                // Match by exact URL.
                $url_match = false;
                foreach ( $login_urls as $login_url ) {
                    if ( $href && rtrim( $href, '/' ) === rtrim( $login_url, '/' ) ) {
                        $url_match = true;
                        break;
                    }
                }

                // Match by link text or href fragment.
                $text_match = $url_match
                    || str_contains( $href, 'wp-login' )
                    || str_contains( $href, '/login' )
                    || in_array( $link_text, array( 'login', 'sign in', 'log in', 'account', 'researcher login', 'reviewer login' ), true );

                if ( $text_match ) {
                    $to_hide[] = $li;
                    break; // only need to match one anchor per <li>
                }
            }

            // Also match by <li> class names.
            $class = $li->getAttribute( 'class' );
            if ( preg_match( '/\b(login|my-account|account)\b/i', $class ) ) {
                $to_hide[] = $li;
            }
        }

        // Apply the hiding class to matched items.
        foreach ( $to_hide as $li ) {
            $existing = $li->getAttribute( 'class' );
            $li->setAttribute( 'class', trim( $existing . ' eiu-rp-login-hidden' ) );
        }

        if ( empty( $to_hide ) ) {
            return $items; // nothing to change — return original string untouched
        }

        // Extract only the <li> children from our wrapper <ul>.
        $ul = $doc->getElementsByTagName( 'ul' )->item( 0 );
        if ( ! $ul ) {
            return $items;
        }

        $output = '';
        foreach ( $ul->childNodes as $child ) {
            $output .= $doc->saveHTML( $child );
        }

        return $output ?: $items;
    }

        public function render_submission_form( array $atts = array() ): string {
        $atts = shortcode_atts( array( 'redirect' => '' ), $atts, 'eiu_submission_form' );

        // Access control: only researchers, reviewers, and admins may submit.
        if ( ! \EIU_RP\Roles\Researcher_Role::can_submit() ) {
            $unified_login_id = get_option( 'eiu_rp_unified_login_page_id' );
            $login_url        = $unified_login_id
                ? add_query_arg( 'role', 'researcher', get_permalink( $unified_login_id ) )
                : home_url( '/login/' );
            $admin_email      = sanitize_email( get_option( 'admin_email', '' ) );
            $contact_url      = $admin_email ? 'mailto:' . $admin_email . '?subject=' . rawurlencode( 'Request Researcher Access' ) : '';

            ob_start();
            ?>
            <div id="eiu-submit-wall" style="
                max-width:540px;margin:48px auto 64px;
                font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
            ">
              <div style="background:#fff;border-radius:14px;box-shadow:0 8px 40px rgba(26,73,136,.12);overflow:hidden;border:1px solid #e2e8f0;">

                <!-- Header strip -->
                <div style="background:#1a4988;padding:32px 36px 28px;text-align:center;">
                  <div style="width:64px;height:64px;border-radius:16px;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                    <i class="bi bi-shield-lock-fill" style="font-size:28px;color:#fff;"></i>
                  </div>
                  <h2 style="color:#fff;font-size:20px;font-weight:800;margin:0 0 6px;letter-spacing:-.01em;">
                    <?php esc_html_e( 'Access Restricted', 'eiu-rp' ); ?>
                  </h2>
                  <p style="color:rgba(255,255,255,.7);font-size:13px;margin:0;">
                    <?php esc_html_e( 'EIU JOURNAL SYSTEM', 'eiu-rp' ); ?>
                  </p>
                </div>

                <!-- Body -->
                <div style="padding:30px 36px 36px;">
                  <p style="font-size:15px;color:#374151;line-height:1.75;margin:0 0 20px;">
                    <?php esc_html_e( 'To submit an article, you must be a registered Author or Reviewer.', 'eiu-rp' ); ?>
                  </p>

                  <!-- What you need -->
                  <div style="background:#f0f4f9;border-radius:10px;padding:16px 20px;margin-bottom:24px;">
                    <p style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#1a4988;margin:0 0 10px;">
                      <?php esc_html_e( 'What you need to proceed:', 'eiu-rp' ); ?>
                    </p>
                    <ul style="margin:0;padding-left:18px;font-size:14px;color:#475569;line-height:1.8;">
                      <li><?php esc_html_e( 'An active Author or Reviewer account', 'eiu-rp' ); ?></li>
                      <li><?php esc_html_e( 'Approval from the EIU editorial administrator', 'eiu-rp' ); ?></li>
                    </ul>
                  </div>

                  <!-- Action buttons -->
                  <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <?php if ( ! is_user_logged_in() ): ?>
                      <a href="<?php echo esc_url( $login_url ); ?>"
                        style="flex:1;min-width:160px;display:inline-flex;align-items:center;justify-content:center;gap:8px;
                               background:#1a4988;color:#fff;text-decoration:none;border-radius:9px;
                               padding:12px 18px;font-size:13px;font-weight:700;transition:background .15s;">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <?php esc_html_e( 'Sign In', 'eiu-rp' ); ?>
                      </a>
                    <?php endif; ?>

                    <?php if ( $contact_url ): ?>
                      <a href="<?php echo esc_attr( $contact_url ); ?>"
                        style="flex:1;min-width:160px;display:inline-flex;align-items:center;justify-content:center;gap:8px;
                               background:#fff;color:#1a4988;text-decoration:none;border-radius:9px;
                               padding:12px 18px;font-size:13px;font-weight:700;border:1.5px solid #1a4988;transition:all .15s;">
                        <i class="bi bi-envelope"></i>
                        <?php esc_html_e( 'Contact Admin', 'eiu-rp' ); ?>
                      </a>
                    <?php endif; ?>

                    <a href="<?php echo esc_url( $login_url ); ?>"
                      style="flex:1;min-width:160px;display:inline-flex;align-items:center;justify-content:center;gap:8px;
                             background:#990000;color:#fff;text-decoration:none;border-radius:9px;
                             padding:12px 18px;font-size:13px;font-weight:700;transition:background .15s;">
                      <i class="bi bi-person-plus"></i>
                      <?php echo esc_html( get_option('eiu_rp_term_join_as_author','Join as Author') ); ?>
                    </a>
                  </div>

                  <p style="text-align:center;margin:20px 0 0;font-size:12px;color:#94a3b8;">
                    <?php esc_html_e( 'Already have an account?', 'eiu-rp' ); ?>
                    <a href="<?php echo esc_url( $login_url ); ?>" style="color:#1a4988;font-weight:600;text-decoration:none;">
                      <?php esc_html_e( 'Sign in here', 'eiu-rp' ); ?>
                    </a>
                  </p>
                </div>

              </div>
            </div>
            <?php
            return ob_get_clean();
        }

        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/submission-form.php', array(
            'redirect' => esc_url( $atts['redirect'] ),
        ) );
        return ob_get_clean();
    }

    public function render_researcher_dashboard( array $atts = array() ): string {
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/researcher-dashboard.php' );
        return ob_get_clean();
    }


    /**
     * v1.9: Unified login shortcode renderer.
     * This is the new canonical login page for all roles.
     */
    public function render_unified_login( array $atts = array() ): string {
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/unified-login.php' );
        return ob_get_clean();
    }

    /**
     * v1.9: Render the Apply as Researcher page.
     */
    public function render_apply_researcher(): string {
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/apply-as-researcher.php' );
        return ob_get_clean();
    }

    /**
     * Researcher login shortcode — delegates to the unified login template.
     * Kept for full backward compatibility with existing page content.
     * [eiu_researcher_login] now renders the same unified form.
     */
    public function render_researcher_login( array $atts = array() ): string {
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/unified-login.php' );
        return ob_get_clean();
    }

    public function render_reviewer_dashboard( array $atts = array() ): string {
        // v1.9: Unauthenticated users are redirected to the unified login page
        // instead of seeing an inline login form inside the dashboard template.
        if ( ! is_user_logged_in() ) {
            $unified_id  = get_option( 'eiu_rp_unified_login_page_id' );
            $login_url   = $unified_id
                ? get_permalink( $unified_id )
                : home_url( '/login/' );
            $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $login_url   = add_query_arg( 'redirect_to', rawurlencode( $current_url ), $login_url );
            wp_safe_redirect( esc_url_raw( $login_url ) );
            exit;
        }
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/reviewer-dashboard.php' );
        return ob_get_clean();
    }

    public function render_article_list( array $atts = array() ): string {
        $atts = shortcode_atts( array(
            'subject'     => '',
            'per_page'    => 10,
            'status'      => 'published',
            'show_header' => 'true',   // v2.0.1: pass show_header=false to hide the Articles pill
            'show_search' => 'true',   // v2.0.1: pass show_search=false to hide the search bar
        ), $atts, 'eiu_article_list' );
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/article-list.php', $atts );
        return ob_get_clean();
    }

    /**
     * v2.0.1: Standalone Articles header pill shortcode.
     * Usage: [eiu_article_header]
     * Renders the blue 'Articles N' pill that shows the total published count.
     */
    public function render_article_header( array $atts = array() ): string {
        $atts  = shortcode_atts( array( 'subject' => '', 'status' => 'published' ), $atts, 'eiu_article_header' );
        $args  = array( 'per_page' => 1, 'page' => 1, 'status' => \EIU_RP\Models\Article::STATUS_PUBLISHED );
        if ( ! empty( $atts['subject'] ) ) { $args['subject'] = sanitize_text_field( $atts['subject'] ); }
        $total = \EIU_RP\Models\Article::query( $args )['total'];
        ob_start();
        ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
        .eiu-al-header-pill{display:inline-flex;align-items:center;gap:10px;background:#1a4988;color:#fff;padding:10px 28px;border-radius:8px;font-size:17px;font-weight:700;letter-spacing:.2px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;}
        </style>
        <div class="eiu-al-header-pill">
          <i class="bi bi-journals"></i>
          <?php esc_html_e( 'Articles', 'eiu-rp' ); ?>
          <?php if ( $total > 0 ): ?>
            <span style="background:rgba(255,255,255,.18);padding:2px 10px;border-radius:20px;font-size:13px;"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * v2.0.1: Standalone article search bar shortcode.
     * Usage: [eiu_article_search placeholder="Search…" max_width="440px"]
     * Renders the live-search input. Filters any [eiu_article_list] on the same page.
     */
    public function render_article_search( array $atts = array() ): string {
        $atts = shortcode_atts( array(
            'placeholder' => __( 'Search articles, authors…', 'eiu-rp' ),
            'max_width'   => '440px',
        ), $atts, 'eiu_article_search' );
        ob_start();
        ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <style>
        .eiu-al-search-wrap-sa{position:relative;max-width:<?php echo esc_attr($atts['max_width']); ?>;}
        .eiu-al-search-wrap-sa .eiu-sa-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;z-index:5;}
        .eiu-al-search-wrap-sa input{padding-left:38px!important;border-radius:8px;border-color:#e5e7eb;width:100%;}
        .eiu-al-search-wrap-sa input:focus{border-color:#1a4988;box-shadow:0 0 0 .2rem rgba(26,73,136,.15);outline:none;}
        </style>
        <div class="eiu-al-search-wrap-sa">
          <i class="bi bi-search eiu-sa-icon"></i>
          <input type="text" id="eiu-al-search" class="form-control"
            placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>">
        </div>
        <script>
        (function(){
          var s=document.getElementById('eiu-al-search');
          if(!s||s.dataset.bound) return;
          s.dataset.bound='1';
          s.addEventListener('input',function(){
            var q=this.value.toLowerCase();
            document.querySelectorAll('.eiu-al-card').forEach(function(c){
              var t=(c.textContent||'').toLowerCase();
              c.closest('.mb-4')?c.closest('.mb-4').style.display=(!q||t.includes(q)?'':'none'):
              c.style.display=(!q||t.includes(q)?'':'none');
            });
          });
        }());
        </script>
        <?php
        return ob_get_clean();
    }

    public function inject_article_content( string $content ): string {
        if ( ! is_singular( 'eiu_article' ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/article-detail.php' );
        return ob_get_clean();
    }

    public function render_article_categories( array $atts = array() ): string {
        $atts = shortcode_atts( array( 'style' => 'pills', 'columns' => 3 ), $atts, 'eiu_article_categories' );
        ob_start();
        \EIU_RP\Utils\Template_Loader::get_template( 'frontend/categories.php', $atts );
        return ob_get_clean();
    }

    /**
     * v1.3: On login — if user has eiu_reviewer role but no reviewer record, auto-create it.
     * This fixes "Your reviewer profile could not be found" for existing WP users.
     */
    public function maybe_create_reviewer_profile( string $user_login, \WP_User $user ): void {
        if ( ! in_array( 'eiu_reviewer', (array) $user->roles, true ) ) {
            return;
        }
        $existing = \EIU_RP\Models\Reviewer::get_by_user( $user->ID );
        if ( $existing ) {
            return;
        }
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'eiu_reviewers',
            array(
                'user_id'          => $user->ID,
                'full_name'        => $user->display_name ?: $user->user_login,
                'email'            => $user->user_email,
                'organization'     => '',
                'specialization'   => '',
                'verified'         => 1,
                'verification_key' => '',
                'registered_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        \EIU_RP\Models\Activity_Log::log(
            'reviewer_profile_auto_created', 'reviewer', (int) $wpdb->insert_id,
            "Reviewer profile auto-created on login for user #{$user->ID} ({$user->user_email})"
        );
    }

    /**
     * v1.3: Ensure /reviewer-access page exists. Safe to call on every admin_init.
     */
    public function maybe_create_reviewer_access_page(): void {
        $id = (int) get_option( 'eiu_rp_reviewer_access_page_id' );
        if ( $id && get_post( $id ) ) {
            return;
        }
        $existing = get_page_by_path( 'reviewer-access' );
        if ( $existing ) {
            update_option( 'eiu_rp_reviewer_access_page_id', $existing->ID );
            return;
        }
        $new_id = wp_insert_post( array(
            'post_title'   => __( 'Reviewer Access', 'eiu-rp' ),
            'post_name'    => 'reviewer-access',
            'post_content' => '[eiu_reviewer_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
        ) );
        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( 'eiu_rp_reviewer_access_page_id', $new_id );
        }
    }

    /**
     * v1.5: One-time flush of all rate-limit transients.
     * Clears any IPs blocked under the old limit of 5/hr after the limit was raised to 20.
     */
    public function maybe_flush_rate_limits(): void {
        if ( get_option( 'eiu_rp_rl_flushed_v15' ) ) {
            return;
        }
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_eiu\_rp\_rl\_%' OR option_name LIKE '\_transient\_timeout\_eiu\_rp\_rl\_%'"
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        update_option( 'eiu_rp_rl_flushed_v15', 1 );
    }

    public function get_service( string $service ) {
        return $this->services[ $service ] ?? null;
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception( 'Cannot unserialize singleton.' ); }
    /**
     * v2.0.1: Inject Open Graph + Twitter Card meta tags for eiu_article posts.
     * These control the preview shown when a reader shares an article on
     * Facebook, LinkedIn, X (Twitter), Instagram, WhatsApp, etc.
     */
    public function inject_article_og_meta(): void {
        if ( ! is_singular( 'eiu_article' ) ) {
            return;
        }

        $post_id = get_the_ID();
        $article = \EIU_RP\Models\Article::get_by_post( (int) $post_id );
        if ( ! $article ) {
            return;
        }

        // Title
        $title = wp_strip_all_tags( $article->title ?? get_the_title( $post_id ) );

        // Description: first 60 words of abstract
        $abstract_raw = get_post_meta( $post_id, '_eiu_abstract', true );
        if ( empty( $abstract_raw ) ) {
            $abstract_raw = $article->abstract ?? '';
        }
        $abstract_text = wp_strip_all_tags( $abstract_raw );
        $words         = preg_split( '/\s+/', trim( $abstract_text ), -1, PREG_SPLIT_NO_EMPTY );
        $description   = implode( ' ', array_slice( $words, 0, 60 ) );
        if ( count( $words ) > 60 ) {
            $description .= '…';
        }

        // Author
        $author = wp_strip_all_tags( $article->author_name ?? '' );
        if ( ! empty( $article->coauthor_name ) ) {
            $author .= ', ' . wp_strip_all_tags( $article->coauthor_name );
        }

        // URL + image
        $url        = esc_url( get_the_permalink( $post_id ) );
        $image_url  = get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
        $site_name  = esc_attr( get_option( 'eiu_rp_term_system_name', 'EIU JOURNAL SYSTEM' ) );

        echo "<!-- EIU Research: Open Graph / Social Share Meta -->
";
        echo '<meta property="og:type"        content="article" />' . "
";
        echo '<meta property="og:site_name"   content="' . esc_attr( $site_name ) . '" />' . "
";
        echo '<meta property="og:title"       content="' . esc_attr( $title ) . '" />' . "
";
        echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "
";
        echo '<meta property="og:url"         content="' . esc_url( $url ) . '" />' . "
";
        if ( $image_url ) {
            echo '<meta property="og:image"   content="' . esc_url( $image_url ) . '" />' . "
";
        }
        if ( $author ) {
            echo '<meta property="article:author" content="' . esc_attr( $author ) . '" />' . "
";
        }

        // Twitter / X Card
        echo '<meta name="twitter:card"        content="summary_large_image" />' . "
";
        echo '<meta name="twitter:title"       content="' . esc_attr( $title ) . '" />' . "
";
        echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "
";
        if ( $image_url ) {
            echo '<meta name="twitter:image"  content="' . esc_url( $image_url ) . '" />' . "
";
        }

        // LinkedIn uses OG tags — already emitted above.
        // Instagram link-in-bio tools also use OG — already emitted above.
    }

    /**
     * v2.0.1: Suppress WordPress native comments_template for eiu_article post type.
     * The plugin renders its own comment section inside article-detail.php.
     * Returning a blank template file path prevents the theme from appending
     * the native "Leave a Reply" / "N thoughts on…" block after the content.
     *
     * @param string $template Path to the comments template.
     * @return string
     */
    public function suppress_native_comments_template( string $template ): string {
        if ( is_singular( 'eiu_article' ) ) {
            // Return path to an empty PHP file so WP includes nothing.
            return EIU_RP_PATH . 'templates/frontend/empty.php';
        }
        return $template;
    }

    /**
     * v2.0.1: Inject CSS overrides into wp_head for eiu_article single pages.
     * Hides theme-generated post meta elements (e.g. "By / March 21, 2026")
     * that are meaningless for research articles and duplicate plugin data.
     */
    public function inject_article_theme_overrides(): void {
        if ( ! is_singular( 'eiu_article' ) ) {
            return;
        }
        echo '<style id="eiu-rp-article-theme-overrides">
/* Hide theme post meta (author byline + date) on research article singles */
.single-eiu_article .entry-meta,
.single-eiu_article .post-meta,
.single-eiu_article .byline,
.single-eiu_article .posted-on,
.single-eiu_article .entry-footer,
.single-eiu_article .post-footer,
.single-eiu_article .author-info,
.single-eiu_article span.author,
.single-eiu_article span.date,
.single-eiu_article .wp-block-post-date,
.single-eiu_article .wp-block-post-author,
.single-eiu_article .wp-block-post-author__name,
.single-eiu_article .post-date,
.single-eiu_article .post-author,
.single-eiu_article time.entry-date,
.single-eiu_article .cat-links,
.single-eiu_article .tags-links {
    display: none !important;
}
</style>' . "
";
    }

}
