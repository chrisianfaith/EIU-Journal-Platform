<?php
/**
 * Plugin Name:       EIU Research Publication
 * Plugin URI:        https://eiu.ac
 * Description:       Enterprise-grade academic research publication platform with article submission, peer review workflows, reviewer management, and full administrative control.
 * Version:           2.0.1
 * Author:            EIU IT Department
 * Author URI:        https://eiu.ac
 * License:           2021-989820-PH
 * License URI:       https://eiu.ac/license
 * Text Domain:       eiu-rp
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        false
 *
 * @package           EIU_Research_Publication
 * @author            Christian Manaoat
 * @contact           support@eiu.ac
 * @developer         Christian Manaoat
 * @since             1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'EIU_RP_VERSION',      '2.0.2' );
define( 'EIU_RP_FILE',         __FILE__ );
define( 'EIU_RP_PATH',         plugin_dir_path( __FILE__ ) );
define( 'EIU_RP_URL',          plugin_dir_url( __FILE__ ) );
define( 'EIU_RP_BASENAME',     plugin_basename( __FILE__ ) );
define( 'EIU_RP_MIN_PHP',      '7.4' );
define( 'EIU_RP_MIN_WP',       '5.8' );
define( 'EIU_RP_TEXT_DOMAIN',  'eiu-rp' );

/**
 * Check requirements before loading.
 */
function eiu_rp_check_requirements() {
    $errors = array();

    if ( version_compare( PHP_VERSION, EIU_RP_MIN_PHP, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Required PHP version, 2: Current PHP version */
            esc_html__( 'EIU Research Publication requires PHP %1$s or higher. You are running PHP %2$s.', 'eiu-rp' ),
            EIU_RP_MIN_PHP,
            PHP_VERSION
        );
    }

    global $wp_version;
    if ( version_compare( $wp_version, EIU_RP_MIN_WP, '<' ) ) {
        $errors[] = sprintf(
            /* translators: 1: Required WP version, 2: Current WP version */
            esc_html__( 'EIU Research Publication requires WordPress %1$s or higher. You are running WordPress %2$s.', 'eiu-rp' ),
            EIU_RP_MIN_WP,
            $wp_version
        );
    }

    return $errors;
}

/**
 * Display admin notices for requirement failures.
 */
function eiu_rp_requirements_notice() {
    $errors = eiu_rp_check_requirements();
    foreach ( $errors as $error ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
    }
}

// Bail if requirements not met.
if ( ! empty( eiu_rp_check_requirements() ) ) {
    add_action( 'admin_notices', 'eiu_rp_requirements_notice' );
    return;
}

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function( $class ) {
    $prefix = 'EIU_RP\\';
    $len    = strlen( $prefix );

    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative = substr( $class, $len );
    $parts    = explode( '\\', $relative );
    $file     = EIU_RP_PATH . 'includes/';

    // Map namespace segments to folders.
    $map = array(
        'Admin'    => 'admin',
        'API'      => 'api',
        'Core'     => 'core',
        'Email'    => 'email',
        'Models'   => 'models',
        'Roles'    => 'roles',
        'Security' => 'security',
        'Utils'    => 'utils',
    );

    if ( isset( $map[ $parts[0] ] ) ) {
        $file .= $map[ $parts[0] ] . '/';
        array_shift( $parts );
    }

    $file .= 'class-' . strtolower( str_replace( '_', '-', implode( '-', $parts ) ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Returns the main plugin instance.
 *
 * @return EIU_RP\Core\Plugin
 */
function eiu_rp() {
    return EIU_RP\Core\Plugin::instance();
}

// Boot the plugin.
add_action( 'plugins_loaded', 'eiu_rp' );

// Activation / deactivation / uninstall hooks.
register_activation_hook(   EIU_RP_FILE, array( 'EIU_RP\Core\Activator',   'activate'   ) );
register_deactivation_hook( EIU_RP_FILE, array( 'EIU_RP\Core\Deactivator', 'deactivate' ) );

/**
 * ── Prevent WordPress.org update-API calls for this private plugin ───────
 *
 * This plugin is hosted privately on eiu.ac, NOT on wordpress.org. When
 * WordPress cannot reach api.wordpress.org (outbound HTTPS blocked on the
 * server), it throws:
 *
 *   Warning: plugins_api(): An unexpected error occurred … WordPress could
 *   not establish a secure connection to WordPress.org …
 *
 * Three independent layers of protection are applied so the error is
 * suppressed on every WordPress version ≥ 5.8:
 *
 *   1. "Update URI: false" in the plugin header — official WP 5.8+ field
 *      that explicitly marks the plugin as having no wordpress.org listing.
 *      WP skips the API call entirely when this field is present.
 *
 *   2. pre_set_site_transient_update_plugins filter — strips this plugin
 *      from the update-check payload before WP sends it to the API. Even
 *      if the header field is missed, no API call is made for this plugin.
 *
 *   3. plugins_api filter — returns false immediately for this plugin's
 *      slug so WP's info-request handler aborts without an outbound call.
 *
 * None of these changes affect other installed plugins, the WP admin update
 * screen, or any data stored in the database.
 */

/**
 * Layer 2: Remove this plugin from the update-check transient payload.
 *
 * Fires just before WordPress sends the plugin list to api.wordpress.org.
 * Removing the plugin from $transient->checked prevents the API from being
 * queried for this plugin's update status.
 *
 * @param  object $transient
 * @return object
 */
function eiu_rp_remove_from_update_check( $transient ) {
    if ( ! is_object( $transient ) ) {
        return $transient;
    }

    $basename = plugin_basename( EIU_RP_FILE );

    // Remove from the "checked" list so WP does not query the API for us.
    if ( isset( $transient->checked[ $basename ] ) ) {
        unset( $transient->checked[ $basename ] );
    }

    // Remove any stale "response" entry (would show a false update notice).
    if ( isset( $transient->response[ $basename ] ) ) {
        unset( $transient->response[ $basename ] );
    }

    // Remove from no_update list (keep it clean).
    if ( isset( $transient->no_update[ $basename ] ) ) {
        unset( $transient->no_update[ $basename ] );
    }

    return $transient;
}

/* ==========================================================================
 * v2.0.2 — Article Delete AJAX handlers registered at plugin root level.
 * Registering here (not inside Admin class) guarantees these actions exist
 * on every admin-ajax.php request regardless of class instantiation order.
 * ========================================================================== */

/**
 * Shared nonce + permission check for article delete actions.
 * Returns article_id (int > 0) on success, or sends JSON error and exits.
 */
function eiu_rp_delete_check_auth() {
    $nonce = sanitize_text_field( wp_unslash(
        $_POST['_ajax_nonce'] ?? $_POST['nonce'] ?? ''
    ) );

    $ok = wp_verify_nonce( $nonce, 'eiu_rp_delete_article' )
       || wp_verify_nonce( $nonce, 'eiu_rp_bulk_delete_articles' )
       || wp_verify_nonce( $nonce, 'eiu_rp_admin' );

    if ( ! $ok ) {
        wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page.' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Only the Main Administrator can delete articles.' ) );
    }
}

/**
 * AJAX: delete a single article — Main Admin only.
 */
function eiu_rp_ajax_delete_article() {
    ob_start();
    eiu_rp_delete_check_auth();

    $article_id = absint( $_POST['article_id'] ?? 0 );
    if ( ! $article_id ) {
        ob_end_clean();
        wp_send_json_error( array( 'message' => 'Invalid article ID.' ) );
    }

    // Load Article model if not already loaded.
    if ( ! class_exists( '\EIU_RP\Models\Article' ) ) {
        require_once plugin_dir_path( EIU_RP_FILE ) . 'includes/models/class-article.php';
    }

    $article = \EIU_RP\Models\Article::get( $article_id );
    if ( ! $article ) {
        ob_end_clean();
        wp_send_json_error( array( 'message' => 'Article not found.' ) );
    }

    $title  = $article->title ?? "#$article_id";
    $result = \EIU_RP\Models\Article::delete( $article_id );

    if ( ! $result ) {
        ob_end_clean();
        wp_send_json_error( array( 'message' => 'Could not delete the article. Please try again.' ) );
    }

    if ( class_exists( '\EIU_RP\Models\Activity_Log' ) ) {
        \EIU_RP\Models\Activity_Log::log(
            'article_deleted', 'article', $article_id,
            sprintf( 'Article "%s" (#%d) deleted by admin #%d.', $title, $article_id, get_current_user_id() )
        );
    }

    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success( array(
        'message'    => sprintf( 'Article "%s" has been permanently deleted.', esc_html( $title ) ),
        'article_id' => $article_id,
    ) );
}
add_action( 'wp_ajax_eiu_rp_admin_delete_article', 'eiu_rp_ajax_delete_article' );

/**
 * AJAX: bulk delete multiple articles — Main Admin only.
 */
function eiu_rp_ajax_bulk_delete_articles() {
    ob_start();
    eiu_rp_delete_check_auth();

    $raw_ids = $_POST['article_ids'] ?? array();
    if ( ! is_array( $raw_ids ) || empty( $raw_ids ) ) {
        ob_end_clean();
        wp_send_json_error( array( 'message' => 'No articles selected.' ) );
    }

    if ( ! class_exists( '\EIU_RP\Models\Article' ) ) {
        require_once plugin_dir_path( EIU_RP_FILE ) . 'includes/models/class-article.php';
    }

    $ids     = array_filter( array_map( 'absint', $raw_ids ) );
    $deleted = 0;
    $failed  = 0;

    foreach ( $ids as $id ) {
        $article = \EIU_RP\Models\Article::get( $id );
        if ( ! $article ) { $failed++; continue; }
        if ( \EIU_RP\Models\Article::delete( $id ) ) {
            if ( class_exists( '\EIU_RP\Models\Activity_Log' ) ) {
                \EIU_RP\Models\Activity_Log::log(
                    'article_deleted', 'article', $id,
                    sprintf( 'Article #%d bulk-deleted by admin #%d.', $id, get_current_user_id() )
                );
            }
            $deleted++;
        } else {
            $failed++;
        }
    }

    if ( $deleted === 0 ) {
        ob_end_clean();
        wp_send_json_error( array( 'message' => 'Could not delete the selected articles. Please try again.' ) );
    }

    $msg = $deleted . ( $deleted === 1 ? ' article' : ' articles' ) . ' deleted successfully.';
    if ( $failed > 0 ) {
        $msg .= " ($failed could not be deleted.)";
    }

    while ( ob_get_level() > 0 ) { ob_end_clean(); }
    wp_send_json_success( array(
        'message' => $msg,
        'deleted' => $deleted,
        'failed'  => $failed,
    ) );
}
add_action( 'wp_ajax_eiu_rp_admin_bulk_delete_articles', 'eiu_rp_ajax_bulk_delete_articles' );

add_filter( 'pre_set_site_transient_update_plugins', 'eiu_rp_remove_from_update_check', 20 );

/**
 * Layer 3: Short-circuit the plugins_api() call for this plugin's slug.
 *
 * plugins_api() is called when WP tries to fetch plugin information (e.g.
 * on the plugin detail modal in wp-admin). Returning false from this filter
 * causes WP to skip the outbound API call entirely for our plugin.
 *
 * @param  false|object|array $result  Existing result (false = not handled).
 * @param  string             $action  API action (e.g. 'plugin_information').
 * @param  object             $args    Request arguments including $args->slug.
 * @return false|object|array
 */
function eiu_rp_bypass_plugins_api( $result, $action, $args ) {
    // Only intercept calls for our own slug.
    $our_slug = dirname( plugin_basename( EIU_RP_FILE ) );
    if ( ! empty( $args->slug ) && $args->slug === $our_slug ) {
        // Return a minimal object so WP displays basic info without an API call.
        return (object) array(
            'name'     => 'EIU Research Publication',
            'slug'     => $our_slug,
            'version'  => EIU_RP_VERSION,
            'author'   => '<a href="https://eiu.ac">EIU IT Department</a>',
            'homepage' => 'https://eiu.ac',
            'sections' => array(
                'description' => 'Enterprise-grade academic research publication platform. Privately hosted — no wordpress.org listing.',
            ),
        );
    }
    return $result;
}
add_filter( 'plugins_api', 'eiu_rp_bypass_plugins_api', 20, 3 );
