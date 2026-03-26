<?php
/**
 * Admin Main Class.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */

namespace EIU_RP\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin
 *
 * Registers the admin menu, enqueues admin assets, and boots admin submodules.
 */
class Admin {

    /**
     * Sub-page instances.
     *
     * @var array
     */
    private array $pages = array();

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',             array( $this, 'handle_welcome' ) );
        add_action( 'admin_notices',          array( $this, 'show_welcome_screen' ) );
        add_filter( 'plugin_action_links_' . EIU_RP_BASENAME, array( $this, 'plugin_action_links' ) );

        // AJAX handlers — registered in __construct so they fire on admin-ajax.php
        // (admin_menu does NOT fire during AJAX requests)
        add_action( 'wp_ajax_eiu_rp_smtp_test',                  array( $this, 'handle_smtp_test' ) );
        add_action( 'wp_ajax_eiu_rp_admin_delete_article',        array( $this, 'delete_article' ) );
        add_action( 'wp_ajax_eiu_rp_admin_bulk_delete_articles',  array( $this, 'bulk_delete_articles' ) );

        $this->pages['dashboard']         = new Dashboard();
        $this->pages['articles']          = new Articles_List();
        $this->pages['reviewers']         = new Reviewers_List();
        $this->pages['reviews']           = new Reviews_List();
        $this->pages['activity_log']      = new Activity_Log_Page();
        $this->pages['reports']           = new Reports();
        $this->pages['settings']          = new Settings();
        $this->pages['email_templates']   = new Email_Template_Editor();  // v1.6
        $this->pages['shortcodes']        = new Shortcodes_Page();         // v1.6
        $this->pages['smtp']              = new SMTP_Settings();            // v1.6
        $this->pages['applications']      = new Applications_List();        // v1.9
        $this->pages['download_leads']    = new Download_Leads();           // v1.9

        // v1.1: ISSN/DOI meta box
        new ISSN_Meta_Box();
    }

    /**
     * Register admin menu and submenus.
     */
    public function register_menu(): void {
        add_menu_page(
            get_option('eiu_rp_term_system_name',__('EIU JOURNAL SYSTEM','eiu-rp')),
            get_option('eiu_rp_term_system_name',__('EIU JOURNAL SYSTEM','eiu-rp')),
            'eiu_manage_articles',
            'eiu-rp',
            array( $this->pages['dashboard'], 'render' ),
            'dashicons-welcome-learn-more',
            25
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Dashboard', 'eiu-rp' ),
            __( 'Dashboard', 'eiu-rp' ),
            'eiu_manage_articles',
            'eiu-rp',
            array( $this->pages['dashboard'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Articles', 'eiu-rp' ),
            __( 'Articles', 'eiu-rp' ),
            'eiu_manage_articles',
            'eiu-rp-articles',
            array( $this->pages['articles'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Reviewers', 'eiu-rp' ),
            __( 'Reviewers', 'eiu-rp' ),
            'eiu_manage_reviewers',
            'eiu-rp-reviewers',
            array( $this->pages['reviewers'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Reviews', 'eiu-rp' ),
            __( 'Reviews', 'eiu-rp' ),
            'eiu_manage_reviews',
            'eiu-rp-reviews',
            array( $this->pages['reviews'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Activity Log', 'eiu-rp' ),
            __( 'Activity Log', 'eiu-rp' ),
            'eiu_view_activity_log',
            'eiu-rp-activity-log',
            array( $this->pages['activity_log'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Reports', 'eiu-rp' ),
            __( 'Reports', 'eiu-rp' ),
            'eiu_manage_articles',
            'eiu-rp-reports',
            array( $this->pages['reports'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Settings', 'eiu-rp' ),
            __( 'Settings', 'eiu-rp' ),
            'eiu_manage_settings',
            'eiu-rp-settings',
            array( $this->pages['settings'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Email Templates', 'eiu-rp' ),
            __( 'Email Templates', 'eiu-rp' ),
            'eiu_manage_settings',
            'eiu-rp-email-templates',
            array( $this->pages['email_templates'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'SMTP Settings', 'eiu-rp' ),
            __( 'SMTP', 'eiu-rp' ),
            'eiu_manage_settings',
            'eiu-rp-smtp',
            array( $this->pages['smtp'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Applications', 'eiu-rp' ),
            __( 'Applications', 'eiu-rp' ),
            'eiu_manage_articles',
            'eiu-rp-applications',
            array( $this->pages['applications'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Download Leads', 'eiu-rp' ),
            __( 'Download Leads', 'eiu-rp' ),
            'eiu_manage_articles',
            'eiu-rp-download-leads',
            array( $this->pages['download_leads'], 'render' )
        );

        add_submenu_page(
            'eiu-rp',
            __( 'Shortcodes Reference', 'eiu-rp' ),
            __( 'Shortcodes', 'eiu-rp' ),
            'eiu_manage_settings',
            'eiu-rp-shortcodes',
            array( $this->pages['shortcodes'], 'render' )
        );

    }

    /**
     * Enqueue admin assets only on plugin pages.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( string $hook ): void {
        // v2.0.1: Match any EIU RP admin page by hook substring.
        // The WP-generated hook name is sanitize_title(parent_slug).'_page_'.child_slug
        // which depends on the registered parent menu slug ('eiu-rp'), not the title.
        // Using strpos avoids the brittle exact-match list that broke when slugs changed.
        if ( strpos( $hook, 'eiu-rp' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'eiu-rp-admin',
            EIU_RP_URL . 'assets/css/admin.css',
            array( 'wp-admin' ),
            EIU_RP_VERSION
        );

        wp_enqueue_script(
            'eiu-rp-admin',
            EIU_RP_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-util' ),
            EIU_RP_VERSION,
            true
        );

        wp_localize_script( 'eiu-rp-admin', 'eiuRPAdmin', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'eiu_rp_admin' ),
            'deleteNonce'  => wp_create_nonce( 'eiu_rp_delete_article' ),
            'bulkNonce'    => wp_create_nonce( 'eiu_rp_bulk_delete_articles' ),
            'i18n'    => array(
                'confirm_delete'   => __( 'Are you sure you want to delete this item?', 'eiu-rp' ),
                'confirm_assign'   => __( 'Assign this reviewer to the article?', 'eiu-rp' ),
                'saved'            => __( 'Changes saved.', 'eiu-rp' ),
                'error'            => __( 'An error occurred. Please try again.', 'eiu-rp' ),
            ),
        ) );

        // Chart.js for reports dashboard.
        if ( $hook === 'toplevel_page_eiu-rp' || $hook === 'eiu-research_page_eiu-rp-reports' ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
        }
    }

    /**
     * Handle welcome screen acceptance.
     */
    public function handle_welcome(): void {
        if ( ! is_admin() || ! isset( $_POST['eiu_rp_welcome_accept'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_rp_welcome' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'eiu-rp' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        \EIU_RP\Models\Activity_Log::log_welcome_accept( get_current_user_id() );

        wp_safe_redirect( admin_url( 'admin.php?page=eiu-rp&welcome_accepted=1' ) );
        exit;
    }

    /**
     * Show the welcome/disclaimer screen to admins who haven't accepted yet.
     */
    public function show_welcome_screen(): void {
        $current_screen = get_current_screen();
        if ( ! $current_screen || strpos( $current_screen->id, 'eiu-rp' ) === false ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $user_id  = get_current_user_id();
        $accepted = get_user_meta( $user_id, 'eiu_rp_welcome_accepted', true );

        if ( $accepted ) {
            return;
        }

        // Show the modal overlay.
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/welcome-modal.php' );
    }

    /**
     * Add action links to the plugin list.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function plugin_action_links( array $links ): array {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=eiu-rp' ) . '">' . __( 'Dashboard', 'eiu-rp' ) . '</a>',
            '<a href="' . admin_url( 'admin.php?page=eiu-rp-settings' ) . '">' . __( 'Settings', 'eiu-rp' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    /**
     * AJAX: send test SMTP email — v1.6
     */
    public function handle_smtp_test(): void {
        if ( ! current_user_can( 'eiu_manage_settings' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'eiu-rp' ) ), 403 );
        }
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'eiu_smtp_test' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'eiu-rp' ) ), 403 );
        }
        $to = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email( $to ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'eiu-rp' ) ) );
        }
        $sent = SMTP_Settings::send_test( $to );
        if ( $sent ) {
            wp_send_json_success( array( 'message' => __( 'Test email sent. Check your inbox.', 'eiu-rp' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Send failed. Check SMTP settings and the Activity Log.', 'eiu-rp' ) ) );
        }
    }
    /* ── v2.0.1: Delete article — Main Admin only ──────────────────── */

    /**
     * AJAX handler: permanently delete an article + its uploaded file + WP post.
     * Requires manage_options (Main Administrator only).
     */
    public function delete_article(): void {
        // Capture any stray output — SMTP plugins call ob_start during wp_mail
        // which can corrupt the JSON response if not flushed before wp_send_json_*.
        ob_start();

        // Standard WordPress AJAX nonce check — trusted by all security plugins.
        // Accepts both the dedicated delete nonce AND the general admin nonce.
        $nonce = sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ?? $_POST['nonce'] ?? '' ) );
        $nonce_ok = wp_verify_nonce( $nonce, 'eiu_rp_delete_article' )
                 || wp_verify_nonce( $nonce, 'eiu_rp_admin' );
        if ( ! $nonce_ok ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'eiu-rp' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Only the Main Administrator can delete articles.', 'eiu-rp' ) ) );
        }

        $article_id = \EIU_RP\Security\Security::sanitize_int( $_POST['article_id'] ?? 0 );
        if ( ! $article_id ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Invalid article ID.', 'eiu-rp' ) ) );
        }

        $article = \EIU_RP\Models\Article::get( $article_id );
        if ( ! $article ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Article not found.', 'eiu-rp' ) ) );
        }

        $title  = $article->title ?? "#{$article_id}";
        $result = \EIU_RP\Models\Article::delete( $article_id );

        if ( ! $result ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Could not delete article. Please try again.', 'eiu-rp' ) ) );
        }

        \EIU_RP\Models\Activity_Log::log(
            'article_deleted',
            'article',
            $article_id,
            sprintf( 'Article "%s" (#%d) permanently deleted by admin #%d.', $title, $article_id, get_current_user_id() )
        );

        while ( ob_get_level() > 0 ) { ob_end_clean(); }
        wp_send_json_success( array(
            'message'    => sprintf( __( 'Article "%s" has been permanently deleted.', 'eiu-rp' ), esc_html( $title ) ),
            'article_id' => $article_id,
        ) );
    }


    /**
     * v2.0.1: Bulk delete multiple articles — Main Admin only.
     */
    public function bulk_delete_articles(): void {
        ob_start();

        $nonce = sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ?? $_POST['nonce'] ?? '' ) );
        $nonce_ok = wp_verify_nonce( $nonce, 'eiu_rp_bulk_delete_articles' )
                 || wp_verify_nonce( $nonce, 'eiu_rp_admin' );
        if ( ! $nonce_ok ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'eiu-rp' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Only the Main Administrator can delete articles.', 'eiu-rp' ) ) );
        }

        $raw_ids = $_POST['article_ids'] ?? array();
        if ( ! is_array( $raw_ids ) || empty( $raw_ids ) ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'No articles selected.', 'eiu-rp' ) ) );
        }

        $ids     = array_filter( array_map( 'absint', $raw_ids ) );
        $deleted = 0;
        $failed  = 0;

        foreach ( $ids as $id ) {
            $article = \EIU_RP\Models\Article::get( $id );
            if ( ! $article ) { $failed++; continue; }

            $result = \EIU_RP\Models\Article::delete( $id );
            if ( $result ) {
                \EIU_RP\Models\Activity_Log::log(
                    'article_deleted', 'article', $id,
                    sprintf( 'Article #%d bulk-deleted by admin #%d.', $id, get_current_user_id() )
                );
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ( $deleted === 0 ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => __( 'Could not delete the selected articles. Please try again.', 'eiu-rp' ) ) );
        }

        $msg = sprintf(
            _n( '%d article deleted successfully.', '%d articles deleted successfully.', $deleted, 'eiu-rp' ),
            $deleted
        );
        if ( $failed > 0 ) {
            $msg .= ' ' . sprintf( __( '(%d could not be deleted.)', 'eiu-rp' ), $failed );
        }

        while ( ob_get_level() > 0 ) { ob_end_clean(); }
        wp_send_json_success( array(
            'message' => $msg,
            'deleted' => $deleted,
            'failed'  => $failed,
        ) );
    }

}
