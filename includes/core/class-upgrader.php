<?php
/**
 * Plugin Upgrader — runs DB migrations on version bump.
 *
 * v1.2 additions:
 *   - eiu_articles: keywords, disclosures, references columns
 *   - eiu_download_leads table (if not exists)
 *
 * @package EIU_Research_Publication
 * @subpackage Core
 */

namespace EIU_RP\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Class Upgrader
 */
class Upgrader {

    /** Current schema version. */
    const SCHEMA_VERSION = '2.3';

    /**
     * Run migrations if needed. Called on plugins_loaded.
     *
     * Also bootstraps the database tables if they are missing entirely —
     * this happens when the plugin is installed/updated by uploading a ZIP
     * (WordPress does not fire register_activation_hook in that case).
     */
    public static function maybe_upgrade(): void {
        // ── Bootstrap guard ───────────────────────────────────────────
        // If the primary table does not exist at all, run the full
        // activation routine regardless of schema version. This covers:
        //  • First-ever install via ZIP upload (no activation hook).
        //  • Re-install after manual table deletion.
        //  • Multisite sub-sites that were never individually activated.
        global $wpdb;
        $primary_table = $wpdb->prefix . 'eiu_articles';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $primary_table )
        );
        if ( $table_exists !== $primary_table ) {
            // Tables are missing — run full activation silently.
            \EIU_RP\Core\Activator::bootstrap_tables();
            // Schedule page creation on init (wp_insert_post needs rewrite ready).
            update_option( 'eiu_rp_create_pages_deferred', '1' );
            // Reset schema version so all migrations run afterwards.
            delete_option( 'eiu_rp_schema_version' );
        }
        // ── End bootstrap guard ───────────────────────────────────────

        $stored = get_option( 'eiu_rp_schema_version', '1.0' );

        // If page-creation was deferred from a previous request (flag still set),
        // re-register the init hook even if the schema version is already current.
        if ( get_option( 'eiu_rp_create_researcher_pages' ) ) {
            add_action( 'init', array( static::class, 'create_researcher_pages' ), 99 );
        }

        // Full page set deferred from the bootstrap-guard path above.
        if ( get_option( 'eiu_rp_create_pages_deferred' ) ) {
            add_action( 'init', array( static::class, 'create_all_pages_deferred' ), 98 );
        }

        // v1.5: Unified login page creation deferred.
        if ( get_option( 'eiu_rp_create_unified_login_page' ) ) {
            add_action( 'init', array( static::class, 'create_unified_login_page' ), 99 );
        }

        // v1.9: Apply as Researcher page creation deferred.
        if ( get_option( 'eiu_rp_create_apply_page' ) ) {
            add_action( 'init', array( static::class, 'create_apply_page' ), 99 );
        }

        if ( version_compare( $stored, self::SCHEMA_VERSION, '>=' ) ) {
            return;
        }

        // Write the new schema version BEFORE running migrations so that a
        // fatal mid-migration doesn't leave the plugin in a crash loop on
        // every subsequent page load.
        update_option( 'eiu_rp_schema_version', self::SCHEMA_VERSION );

        self::run_migrations( $stored );
    }

    /**
     * Execute migrations in sequence.
     *
     * @param string $from_version Version upgrading from.
     */
    private static function run_migrations( string $from_version ): void {
        global $wpdb;

        if ( version_compare( $from_version, '1.2', '<' ) ) {
            self::migrate_1_2();
        }
        if ( version_compare( $from_version, '1.3', '<' ) ) {
            self::migrate_1_3();
        }
        if ( version_compare( $from_version, '1.4', '<' ) ) {
            self::migrate_1_4();
        }
        if ( version_compare( $from_version, '1.5', '<' ) ) {
            self::migrate_1_5();
        }
        if ( version_compare( $from_version, '1.6', '<' ) ) {
            self::migrate_1_6();
            self::migrate_1_7();
            self::migrate_1_8();
        }
        if ( version_compare( $from_version, '1.9', '<' ) ) {
            self::migrate_1_9();
        }
        if ( version_compare( $from_version, '2.0', '<' ) ) {
            self::migrate_2_0();
        }
        if ( version_compare( $from_version, '2.1', '<' ) ) {
            self::migrate_2_1();
        }
        if ( version_compare( $from_version, '2.2', '<' ) ) {
            self::migrate_2_2();
        }
        if ( version_compare( $from_version, '2.3', '<' ) ) {
            self::migrate_2_3();
        }
    }

    /**
     * v1.2: Add keywords, disclosures, references, author_photo columns.
     */
    private static function migrate_1_2(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eiu_articles';

        $existing = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore
        $cols     = wp_list_pluck( $existing, 'Field' );

        $to_add = array(
            'keywords'    => "ALTER TABLE `{$table}` ADD COLUMN `keywords`    VARCHAR(1000) NOT NULL DEFAULT '' AFTER `issn`",
            'disclosures' => "ALTER TABLE `{$table}` ADD COLUMN `disclosures` TEXT          NOT NULL DEFAULT '' AFTER `keywords`",
            'references'  => "ALTER TABLE `{$table}` ADD COLUMN `references`  LONGTEXT      NOT NULL DEFAULT '' AFTER `disclosures`",
            'advisers'    => "ALTER TABLE `{$table}` ADD COLUMN `advisers`    VARCHAR(500)  NOT NULL DEFAULT '' AFTER `references`",
            'summary'     => "ALTER TABLE `{$table}` ADD COLUMN `summary`     TEXT          NOT NULL DEFAULT '' AFTER `advisers`",
        );

        foreach ( $to_add as $col => $sql ) {
            if ( ! in_array( $col, $cols, true ) ) {
                $wpdb->query( $sql ); // phpcs:ignore
            }
        }

        // Download leads table.
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_download_leads (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            article_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            email        VARCHAR(200)        NOT NULL DEFAULT '',
            requested_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip           VARCHAR(45)         NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY article_id (article_id),
            KEY email (email)
        ) {$charset};");
    }

    /**
     * v1.3: Register researcher role + schedule page creation on init.
     *
     * wp_insert_post() must NOT be called during plugins_loaded because
     * $wp_rewrite is null at that point (get_page_permastruct() fatal).
     * __() must NOT be called here either — translations aren't loaded yet.
     *
     * Instead we set a flag option and hook into 'init' to do the actual
     * page creation once WordPress is fully initialised.
     */
    private static function migrate_1_3(): void {
        // Role registration is safe at plugins_loaded (no rewrite/translation needed).
        \EIU_RP\Roles\Researcher_Role::register();

        // Set a flag so the init hook knows pages still need to be created.
        update_option( 'eiu_rp_create_researcher_pages', '1' );

        // Hook into init to create the pages — $wp_rewrite is ready there.
        add_action( 'init', array( static::class, 'create_researcher_pages' ), 99 );
    }

    /**
     * Create the researcher portal pages.
     * Called on the 'init' hook so $wp_rewrite and translations are available.
     * Safe to call multiple times — skips pages that already exist.
     */
    public static function create_researcher_pages(): void {
        // Only run when the flag is set (i.e. migration pending).
        if ( ! get_option( 'eiu_rp_create_researcher_pages' ) ) {
            return;
        }

        $pages = array(
            array(
                'option'  => 'eiu_rp_researcher_login_page_id',
                'slug'    => 'researcher',
                'title'   => 'Researcher Portal',   // plain string — no __() needed for slug/title
                'content' => '[eiu_researcher_login]',
            ),
            array(
                'option'  => 'eiu_rp_researcher_dashboard_page_id',
                'slug'    => 'researcher-dashboard',
                'title'   => 'Researcher Dashboard',
                'content' => '[eiu_researcher_dashboard]',
            ),
        );

        foreach ( $pages as $page ) {
            $existing_id = get_option( $page['option'] );
            if ( $existing_id && get_post( $existing_id ) ) {
                continue;
            }
            $existing = get_page_by_path( $page['slug'] );
            if ( $existing ) {
                update_option( $page['option'], $existing->ID );
                continue;
            }
            $new_id = wp_insert_post( array(
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ) );
            if ( $new_id && ! is_wp_error( $new_id ) ) {
                update_option( $page['option'], $new_id );
            }
        }

        // Clear the flag so this never runs again.
        delete_option( 'eiu_rp_create_researcher_pages' );
    }

    /**
     * Create ALL plugin pages on the init hook — called when the bootstrap
     * guard detected missing tables and scheduled deferred page creation.
     * Delegates to Activator which already has idempotent page-creation logic.
     */
    public static function create_all_pages_deferred(): void {
        if ( ! get_option( 'eiu_rp_create_pages_deferred' ) ) {
            return;
        }
        // Re-use Activator's full page-creation logic via reflection into
        // the public create_pages path. Because create_pages() is private,
        // we call run_activation() on a minimal stub — or simply re-run the
        // page list inline (same data, safe to duplicate-check).
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
            // v1.9: Unified login page.
            array(
                'title'   => 'Login',
                'slug'    => 'login',
                'content' => '[eiu_unified_login]',
                'option'  => 'eiu_rp_unified_login_page_id',
            ),
            // v1.9: Apply as Researcher page.
            array(
                'title'   => 'Apply as Researcher',
                'slug'    => 'apply-as-researcher',
                'content' => '[eiu_apply_researcher]',
                'option'  => 'eiu_rp_apply_page_id',
            ),
        );

        foreach ( $pages as $page ) {
            $existing_id = get_option( $page['option'] );
            if ( $existing_id && get_post( $existing_id ) ) {
                continue; // page already exists
            }
            $existing_by_slug = get_page_by_path( $page['slug'] );
            if ( $existing_by_slug ) {
                update_option( $page['option'], $existing_by_slug->ID );
                continue;
            }
            $new_id = wp_insert_post( array(
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
            ) );
            if ( $new_id && ! is_wp_error( $new_id ) ) {
                update_option( $page['option'], $new_id );
            }
        }

        delete_option( 'eiu_rp_create_pages_deferred' );
        // Also flush rewrites so the new page slugs resolve.
        flush_rewrite_rules();
    }

    /**
     * v1.5: Schedule creation of the Unified Login page for existing installs.
     *
     * The page cannot be created during plugins_loaded (wp_insert_post needs
     * $wp_rewrite). We set a flag and hook into init instead, exactly the same
     * pattern used by migrate_1_3 for researcher pages.
     */
    private static function migrate_1_5(): void {
        update_option( 'eiu_rp_create_unified_login_page', '1' );
        add_action( 'init', array( static::class, 'create_unified_login_page' ), 99 );
    }

    /**
     * Create the unified login page. Called on init hook.
     */
    public static function create_unified_login_page(): void {
        if ( ! get_option( 'eiu_rp_create_unified_login_page' ) ) {
            return;
        }

        $existing_id = get_option( 'eiu_rp_unified_login_page_id' );
        if ( $existing_id && get_post( $existing_id ) ) {
            delete_option( 'eiu_rp_create_unified_login_page' );
            return;
        }

        $existing_by_slug = get_page_by_path( 'login' );
        if ( $existing_by_slug ) {
            update_option( 'eiu_rp_unified_login_page_id', $existing_by_slug->ID );
            // Update content to use the unified login shortcode if it doesn't already
            if ( strpos( $existing_by_slug->post_content, 'eiu_unified_login' ) === false ) {
                wp_update_post( array(
                    'ID'           => $existing_by_slug->ID,
                    'post_content' => '[eiu_unified_login]',
                ) );
            }
            delete_option( 'eiu_rp_create_unified_login_page' );
            return;
        }

        $new_id = wp_insert_post( array(
            'post_title'   => 'Login',
            'post_name'    => 'login',
            'post_content' => '[eiu_unified_login]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
        ) );

        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( 'eiu_rp_unified_login_page_id', $new_id );
            flush_rewrite_rules();
        }

        delete_option( 'eiu_rp_create_unified_login_page' );
    }

    /**
     * v1.6: Add is_deleted column to eiu_reviewers.
     *
     * The OTP send handler queries eiu_reviewers with AND is_deleted = 0.
     * The column was previously missing from this table (it existed only on
     * eiu_reviews), causing an "Unknown column" MySQL error that corrupted
     * the AJAX JSON response and produced a client-side "Network error".
     *
     * Uses ALTER TABLE … ADD COLUMN only if the column is absent — safe to
     * run on any install regardless of current state. No data is affected.
     */
    private static function migrate_1_6(): void {
        global $wpdb;
        $table    = $wpdb->prefix . 'eiu_reviewers';
        $existing = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore
        $cols     = wp_list_pluck( $existing, 'Field' );

        if ( ! in_array( 'is_deleted', $cols, true ) ) {
            $wpdb->query( // phpcs:ignore
                "ALTER TABLE `{$table}` ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_active`"
            );
        }
    }

    /**
     * v1.4: Add revision_notes column to eiu_articles.
     *
     * Stores the reviewer feedback sent with a "Revision Required" decision.
     * No data is lost — the column defaults to empty string so existing rows
     * are unaffected.
     */
    private static function migrate_1_4(): void {
        global $wpdb;
        $table    = $wpdb->prefix . 'eiu_articles';
        $existing = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A ); // phpcs:ignore
        $cols     = wp_list_pluck( $existing, 'Field' );

        if ( ! in_array( 'revision_notes', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `revision_notes` LONGTEXT NOT NULL DEFAULT '' AFTER `status`" ); // phpcs:ignore
        }
        if ( ! in_array( 'revision_count', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `revision_count` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `revision_notes`" ); // phpcs:ignore
        }
    }
    /**
     * Schema 1.7 — Add profile_photo_id to eiu_reviewers,
     * co_reviewer_id and reviewer_notes to eiu_reviews,
     * and profile_photo_id to wp_usermeta (via user meta — no schema change).
     */
    private static function migrate_1_7(): void {
        global $wpdb;

        // eiu_reviewers: profile photo attachment ID
        $cols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}eiu_reviewers", 0 );
        if ( ! in_array( 'profile_photo_id', $cols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}eiu_reviewers ADD COLUMN profile_photo_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER is_deleted" );
        }

        // eiu_reviews: co_reviewer free-text field + reviewer notes
        $rcols = $wpdb->get_col( "DESCRIBE {$wpdb->prefix}eiu_reviews", 0 );
        if ( ! in_array( 'co_reviewer', $rcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}eiu_reviews ADD COLUMN co_reviewer TEXT NOT NULL DEFAULT '' AFTER admin_notes" );
        }
        if ( ! in_array( 'reviewer_notes', $rcols, true ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}eiu_reviews ADD COLUMN reviewer_notes LONGTEXT NOT NULL DEFAULT '' AFTER co_reviewer" );
        }
    }

    /**
     * Schema 1.8 — Expand co_reviewer from VARCHAR(255) to TEXT.
     * This allows storing JSON arrays of multiple reviewer IDs without truncation.
     */
    private static function migrate_1_8(): void {
        global $wpdb;
        // Check current column type
        $col = $wpdb->get_row( $wpdb->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'co_reviewer'",
            DB_NAME,
            $wpdb->prefix . 'eiu_reviews'
        ) );
        if ( $col && strtolower( $col->COLUMN_TYPE ) === 'varchar(255)' ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}eiu_reviews MODIFY COLUMN co_reviewer TEXT NOT NULL DEFAULT ''" );
        }
    }

    /**
     * Schema 1.9 — Create eiu_researcher_applications table.
     * Safe to call on existing installs — uses CREATE TABLE IF NOT EXISTS.
     */
    private static function migrate_1_9(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}eiu_researcher_applications (
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
        ) {$charset};" );

        // Schedule Apply as Researcher page creation on init.
        update_option( 'eiu_rp_create_apply_page', '1' );
        add_action( 'init', array( static::class, 'create_apply_page' ), 99 );
    }

    /**
     * Create the Apply as Researcher page. Called on init hook.
     */
    public static function create_apply_page(): void {
        if ( ! get_option( 'eiu_rp_create_apply_page' ) ) {
            return;
        }
        $existing_id = get_option( 'eiu_rp_apply_page_id' );
        if ( $existing_id && get_post( $existing_id ) ) {
            delete_option( 'eiu_rp_create_apply_page' );
            return;
        }
        $existing = get_page_by_path( 'apply-as-researcher' );
        if ( $existing ) {
            update_option( 'eiu_rp_apply_page_id', $existing->ID );
            delete_option( 'eiu_rp_create_apply_page' );
            return;
        }
        $new_id = wp_insert_post( array(
            'post_title'   => 'Apply as Researcher',
            'post_name'    => 'apply-as-researcher',
            'post_content' => '[eiu_apply_researcher]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
        ) );
        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( 'eiu_rp_apply_page_id', $new_id );
            flush_rewrite_rules();
        }
        delete_option( 'eiu_rp_create_apply_page' );
    }

    /**
     * v2.0: Add multiple co-authors JSON column, ethics declaration columns,
     * and ethics document file columns to eiu_articles.
     */
    private static function migrate_2_0(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eiu_articles';
        $cols  = array(
            'co_authors_json'      => "ALTER TABLE `{$table}` ADD COLUMN `co_authors_json`      LONGTEXT     NOT NULL DEFAULT '' AFTER `coauthor_email`",
            'human_participants'   => "ALTER TABLE `{$table}` ADD COLUMN `human_participants`   VARCHAR(50)  NOT NULL DEFAULT '' AFTER `co_authors_json`",
            'ethics_level'         => "ALTER TABLE `{$table}` ADD COLUMN `ethics_level`         VARCHAR(50)  NOT NULL DEFAULT '' AFTER `human_participants`",
            'ethics_file_path'     => "ALTER TABLE `{$table}` ADD COLUMN `ethics_file_path`     VARCHAR(500) NOT NULL DEFAULT '' AFTER `ethics_level`",
            'ethics_file_name'     => "ALTER TABLE `{$table}` ADD COLUMN `ethics_file_name`     VARCHAR(255) NOT NULL DEFAULT '' AFTER `ethics_file_path`",
            'published_at'         => "ALTER TABLE `{$table}` ADD COLUMN `published_at`         DATETIME     DEFAULT NULL AFTER `ethics_file_name`",
        );
        foreach ( $cols as $col => $sql ) {
            $exists = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE '{$col}'" ); // phpcs:ignore
            if ( empty( $exists ) ) {
                $wpdb->query( $sql ); // phpcs:ignore
            }
        }
    }

    /**
     * v2.1: Open comments on all existing published eiu_article posts.
     * Fixes articles that were published before comments support was added to the CPT.
     */
    private static function migrate_2_1(): void {
        global $wpdb;

        // Open comments on every eiu_article post that currently has them closed.
        $wpdb->query(
            "UPDATE {$wpdb->posts}
             SET comment_status = 'open'
             WHERE post_type = 'eiu_article'
             AND post_status NOT IN ('trash','auto-draft')
             AND comment_status = 'closed'"
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /**
     * v2.2: Add author_affiliation (rich text) column to eiu_articles.
     */
    private static function migrate_2_2(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eiu_articles';
        $col   = 'author_affiliation';
        $exists = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE '{$col}'" ); // phpcs:ignore
        if ( empty( $exists ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `author_affiliation` LONGTEXT NOT NULL DEFAULT '' AFTER `author_org`" ); // phpcs:ignore
        }
    }

    /**
     * Schema 2.3: add author_user_id to eiu_articles.
     * Links every submission to the WordPress user who submitted it.
     * Existing rows are back-filled by matching author_email to wp_users.
     */
    private static function migrate_2_3(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'eiu_articles';

        // Add column if it doesn't exist yet.
        $col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'author_user_id'" ); // phpcs:ignore
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `author_user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0 AFTER `author_email`" ); // phpcs:ignore
            $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `author_user_id` (`author_user_id`)" ); // phpcs:ignore
        }

        // Back-fill: for every article with a known author_email, find the WP user and set author_user_id.
        $articles = $wpdb->get_results( "SELECT id, author_email FROM `{$table}` WHERE author_user_id = 0 AND author_email != ''" ); // phpcs:ignore
        foreach ( (array) $articles as $row ) {
            $wp_user = get_user_by( 'email', $row->author_email );
            if ( $wp_user ) {
                $wpdb->update( $table, array( 'author_user_id' => $wp_user->ID ), array( 'id' => $row->id ), array( '%d' ), array( '%d' ) );
            }
        }
    }

}