<?php
/**
 * Plugin Activator.
 *
 * @package EIU_Research_Publication
 * @subpackage Core
 */

namespace EIU_RP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Activator
 *
 * Runs on plugin activation: creates tables, roles, default options.
 */
class Activator {

    /**
     * Public entry-point for the Upgrader to call when tables are missing
     * on an installation where the activation hook was never fired (e.g.
     * ZIP upload / FTP copy). Safe to call repeatedly — all DDL statements
     * use CREATE TABLE IF NOT EXISTS / ALTER TABLE … ADD COLUMN (checked
     * first), so no data is ever overwritten or lost.
     */
    public static function bootstrap_tables(): void {
        self::create_tables();
        self::add_roles();
        self::set_defaults();
        // Pages are NOT created here because wp_insert_post() requires
        // $wp_rewrite to be initialised, which is not guaranteed at
        // plugins_loaded. The Upgrader's init-hook deferred path handles
        // page creation safely.
        update_option( 'eiu_rp_version', EIU_RP_VERSION );
        update_option( 'eiu_rp_activated_at', current_time( 'mysql' ) );
    }

    /**
     * Run activation routines.
     *
     * @param bool $network_wide Whether activated network-wide.
     */
    public static function activate( bool $network_wide = false ): void {
        if ( $network_wide && is_multisite() ) {
            $sites = get_sites( array( 'fields' => 'ids' ) );
            foreach ( $sites as $site_id ) {
                switch_to_blog( $site_id );
                self::run_activation();
                restore_current_blog();
            }
        } else {
            self::run_activation();
        }
    }

    /**
     * Core activation logic for a single site.
     */
    private static function run_activation(): void {
        self::create_tables();
        self::add_roles();
        self::set_defaults();
        self::create_pages();
        self::flush_rewrites();

        update_option( 'eiu_rp_version', EIU_RP_VERSION );
        update_option( 'eiu_rp_activated_at', current_time( 'mysql' ) );
    }

    /**
     * Create custom database tables.
     */
    public static function create_tables(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $tables = array();

        // Articles table (extended meta).
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_articles (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id       BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            author_name   VARCHAR(200) NOT NULL DEFAULT '',
            author_email  VARCHAR(200) NOT NULL DEFAULT '',
            author_org    VARCHAR(255) NOT NULL DEFAULT '',
            coauthor_name  VARCHAR(200) NOT NULL DEFAULT '',
            coauthor_email VARCHAR(200) NOT NULL DEFAULT '',
            coauthor_org   VARCHAR(255) NOT NULL DEFAULT '',
            contact_number VARCHAR(50)  NOT NULL DEFAULT '',
            country        VARCHAR(100) NOT NULL DEFAULT '',
            doi            VARCHAR(255) NOT NULL DEFAULT '',
            issn           VARCHAR(50)  NOT NULL DEFAULT '',
            file_path      VARCHAR(500) NOT NULL DEFAULT '',
            file_name      VARCHAR(255) NOT NULL DEFAULT '',
            file_type      VARCHAR(20)  NOT NULL DEFAULT '',
            status         VARCHAR(50)  NOT NULL DEFAULT 'pending',
            submitted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            submitted_ip   VARCHAR(45)  NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY submitted_at (submitted_at)
        ) $charset;";

        // Reviewers table.
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_reviewers (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id          BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            full_name        VARCHAR(200) NOT NULL DEFAULT '',
            email            VARCHAR(200) NOT NULL DEFAULT '',
            organization     VARCHAR(255) NOT NULL DEFAULT '',
            specialization   VARCHAR(500) NOT NULL DEFAULT '',
            verified         TINYINT(1)  NOT NULL DEFAULT 0,
            verification_key VARCHAR(64) NOT NULL DEFAULT '',
            registered_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_active      DATETIME             DEFAULT NULL,
            is_deleted       TINYINT(1)  NOT NULL DEFAULT 0,
            profile_photo_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY verified (verified)
        ) $charset;";

        // Reviews table.
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_reviews (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            article_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            reviewer_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            assigned_at   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            submitted_at  DATETIME            DEFAULT NULL,
            due_date      DATE                DEFAULT NULL,
            status        VARCHAR(50) NOT NULL DEFAULT 'assigned',
            recommendation VARCHAR(50) NOT NULL DEFAULT '',
            comments      LONGTEXT    NOT NULL DEFAULT '',
            admin_notes   TEXT        NOT NULL DEFAULT '',
            is_deleted    TINYINT(1)  NOT NULL DEFAULT 0,
            co_reviewer   TEXT         NOT NULL DEFAULT '',
            reviewer_notes LONGTEXT    NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY article_id (article_id),
            KEY reviewer_id (reviewer_id),
            KEY status (status)
        ) $charset;";

        // Activity log table.
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_activity_log (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            action      VARCHAR(100) NOT NULL DEFAULT '',
            object_type VARCHAR(50)  NOT NULL DEFAULT '',
            object_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            description TEXT         NOT NULL DEFAULT '',
            ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
            user_agent  VARCHAR(500) NOT NULL DEFAULT '',
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) $charset;";

        // Download leads table.
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_download_leads (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            article_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            email       VARCHAR(200) NOT NULL DEFAULT '',
            requested_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip          VARCHAR(45) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY article_id (article_id),
            KEY email (email)
        ) $charset;";

        // Notifications log table.
        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_researcher_applications (
            id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name            VARCHAR(200)         NOT NULL DEFAULT '',
            title                VARCHAR(100)         NOT NULL DEFAULT '',
            designation          VARCHAR(200)         NOT NULL DEFAULT '',
            country              VARCHAR(100)         NOT NULL DEFAULT '',
            academic_bg          TEXT                 NOT NULL DEFAULT '',
            gender               VARCHAR(20)          NOT NULL DEFAULT '',
            date_of_birth        DATE                 DEFAULT NULL,
            student_number       VARCHAR(100)         NOT NULL DEFAULT '',
            email                VARCHAR(200)         NOT NULL DEFAULT '',
            expertise            VARCHAR(500)         NOT NULL DEFAULT '',
            about                LONGTEXT             NOT NULL DEFAULT '',
            cv_file_path         VARCHAR(500)         NOT NULL DEFAULT '',
            cv_file_name         VARCHAR(255)         NOT NULL DEFAULT '',
            research_file_path   VARCHAR(500)         NOT NULL DEFAULT '',
            research_file_name   VARCHAR(255)         NOT NULL DEFAULT '',
            status               VARCHAR(50)          NOT NULL DEFAULT 'pending',
            admin_notes          TEXT                 NOT NULL DEFAULT '',
            assigned_reviewer_id BIGINT(20) UNSIGNED  NOT NULL DEFAULT 0,
            submitted_at         DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approved_at          DATETIME             DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY email (email)
        ) $charset;";

        $tables[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_notifications (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            type        VARCHAR(100) NOT NULL DEFAULT '',
            subject     VARCHAR(500) NOT NULL DEFAULT '',
            message     LONGTEXT     NOT NULL DEFAULT '',
            sent_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status      VARCHAR(20)  NOT NULL DEFAULT 'sent',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY sent_at (sent_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    /**
     * Register custom roles.
     */
    public static function add_roles(): void {
        // Researcher role — can submit articles (v1.6).
        \EIU_RP\Roles\Researcher_Role::register();

        // Reviewer role.
        add_role( 'eiu_reviewer', 'Reviewer', array(
            'read'                  => true,
            'eiu_review_articles'   => true,
            'eiu_manage_own_review' => true,
        ) );

        // Editor role with extra caps.
        $editor = get_role( 'editor' );
        if ( $editor ) {
            $editor->add_cap( 'eiu_manage_articles' );
            $editor->add_cap( 'eiu_manage_reviewers' );
        }

        // Admin gets all caps.
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'eiu_manage_articles' );
            $admin->add_cap( 'eiu_manage_reviewers' );
            $admin->add_cap( 'eiu_manage_reviews' );
            $admin->add_cap( 'eiu_view_activity_log' );
            $admin->add_cap( 'eiu_manage_settings' );
        }
    }

    /**
     * Set default plugin options.
     */
    public static function set_defaults(): void {
        $defaults = array(
            'eiu_rp_submission_notification_email' => get_option( 'admin_email' ),
            'eiu_rp_from_name'                     => get_option( 'blogname' ),
            'eiu_rp_from_email'                    => get_option( 'admin_email' ),
            'eiu_rp_max_file_size_mb'              => 20,
            'eiu_rp_allowed_file_types'            => array( 'pdf', 'ppt', 'pptx' ),
            'eiu_rp_review_days_due'               => 14,
            'eiu_rp_welcome_accepted'              => array(),
            'eiu_rp_subjects'                      => array(
                'Computer Science',
                'Engineering',
                'Natural Sciences',
                'Social Sciences',
                'Medicine',
                'Business',
                'Law',
                'Humanities',
                'Education',
                'Other',
            ),
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Create default plugin pages if they don't exist.
     */
    private static function create_pages(): void {
        $pages = array(
            array(
                'title'   => 'Submit Article',
                'slug'    => 'eiu-submit-article',
                'content' => '[eiu_submission_form]',
                'option'  => 'eiu_rp_submission_page_id',
            ),
            array(
                'title'   => 'Reviewer Dashboard',
                'slug'    => 'eiu-reviewer-dashboard',
                'content' => '[eiu_reviewer_dashboard]',
                'option'  => 'eiu_rp_reviewer_page_id',
            ),
            array(
                'title'   => 'Research Publications',
                'slug'    => 'research-publications',
                'content' => '[eiu_article_list]',
                'option'  => 'eiu_rp_listing_page_id',
            ),
            array(
                'title'   => 'Reviewer Access',
                'slug'    => 'reviewer-access',
                'content' => '[eiu_reviewer_dashboard]',
                'option'  => 'eiu_rp_reviewer_access_page_id',
            ),
            // v1.6: Researcher portal pages.
            array(
                'title'   => 'Researcher Portal',
                'slug'    => 'researcher',
                'content' => '[eiu_researcher_login]',
                'option'  => 'eiu_rp_researcher_login_page_id',
            ),
            array(
                'title'   => 'Researcher Dashboard',
                'slug'    => 'researcher-dashboard',
                'content' => '[eiu_researcher_dashboard]',
                'option'  => 'eiu_rp_researcher_dashboard_page_id',
            ),
            // v1.9: Unified login — single entry point for all roles.
            array(
                'title'   => 'Login',
                'slug'    => 'login',
                'content' => '[eiu_unified_login]',
                'option'  => 'eiu_rp_unified_login_page_id',
            ),
        );

        foreach ( $pages as $page ) {
            $existing = get_option( $page['option'] );
            if ( $existing && get_post( $existing ) ) {
                continue;
            }

            $existing_page = get_page_by_path( $page['slug'] );
            if ( $existing_page ) {
                update_option( $page['option'], $existing_page->ID );
                continue;
            }

            $id = wp_insert_post( array(
                'post_title'   => wp_strip_all_tags( $page['title'] ),
                'post_name'    => sanitize_title( $page['slug'] ),
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id(),
            ) );

            if ( $id && ! is_wp_error( $id ) ) {
                update_option( $page['option'], $id );
            }
        }
    }

    /**
     * Schedule rewrite flush (safe for multisite).
     */
    private static function flush_rewrites(): void {
        update_option( 'eiu_rp_flush_rewrites', 1 );
    }
}
