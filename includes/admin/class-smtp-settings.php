<?php
/**
 * Admin: SMTP Settings.
 *
 * Stores SMTP credentials and applies them via the phpmailer_init hook.
 * Fully compatible with WP Mail SMTP — if WP Mail SMTP is active, these
 * settings are skipped to avoid conflicts.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Security\Security;

class SMTP_Settings {

    public function __construct() {
        add_action( 'admin_init',       array( $this, 'save' ) );
        add_action( 'phpmailer_init',   array( $this, 'configure_smtp' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'eiu_manage_settings' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/smtp-settings.php' );
    }

    public function save(): void {
        if ( ! isset( $_POST['eiu_smtp_save'] ) ) { return; }
        Security::verify_admin_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'eiu_smtp_settings' );
        if ( ! current_user_can( 'eiu_manage_settings' ) ) { return; }

        $fields = array(
            'eiu_smtp_enabled'  => 'checkbox',
            'eiu_smtp_host'     => 'text',
            'eiu_smtp_port'     => 'int',
            'eiu_smtp_auth'     => 'checkbox',
            'eiu_smtp_user'     => 'text',
            'eiu_smtp_pass'     => 'raw',   // Encrypted below
            'eiu_smtp_secure'   => 'text',  // tls | ssl | none
        );

        foreach ( $fields as $key => $type ) {
            $raw = $_POST[ $key ] ?? '';
            switch ( $type ) {
                case 'checkbox':
                    update_option( $key, ! empty( $raw ) ? '1' : '0' );
                    break;
                case 'int':
                    update_option( $key, absint( $raw ) );
                    break;
                case 'raw':
                    // Only update if a new value is provided (don't overwrite with blank)
                    if ( $raw !== '' ) {
                        // Basic encryption using WP auth key
                        update_option( $key, base64_encode( $raw . AUTH_KEY ) ); // phpcs:ignore
                    }
                    break;
                default:
                    update_option( $key, sanitize_text_field( wp_unslash( $raw ) ) );
            }
        }

        \EIU_RP\Models\Activity_Log::log( 'smtp_settings_saved', 'admin', 0, 'SMTP settings updated.' );
        wp_safe_redirect( add_query_arg( 'smtp-saved', '1', wp_get_referer() ) );
        exit;
    }

    /**
     * Apply SMTP settings to PHPMailer — only if enabled and WP Mail SMTP is NOT active.
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public function configure_smtp( $phpmailer ): void {
        // Skip if our SMTP is disabled
        if ( ! get_option( 'eiu_smtp_enabled', '0' ) ) {
            return;
        }

        // Skip if WP Mail SMTP plugin is active — let it manage SMTP
        if ( defined( 'WPMS_PLUGIN_VER' ) || defined( 'WPMAILSMTP_PLUGIN_VERSION' ) ||
             class_exists( 'WPMailSMTP\\WP\\Hooks' ) || class_exists( 'wp_mail_smtp' ) ) {
            return;
        }

        $host   = get_option( 'eiu_smtp_host', '' );
        $port   = (int) get_option( 'eiu_smtp_port', 587 );
        $auth   = get_option( 'eiu_smtp_auth', '1' );
        $user   = get_option( 'eiu_smtp_user', '' );
        $pass_e = get_option( 'eiu_smtp_pass', '' );
        $secure = get_option( 'eiu_smtp_secure', 'tls' );

        if ( ! $host ) {
            return;
        }

        // Decode password
        $pass = '';
        if ( $pass_e ) {
            $decoded = base64_decode( $pass_e ); // phpcs:ignore
            $pass    = str_replace( AUTH_KEY, '', $decoded );
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = $port;
        $phpmailer->SMTPAuth   = (bool) $auth;
        $phpmailer->Username   = $user;
        $phpmailer->Password   = $pass;
        $phpmailer->SMTPSecure = ( $secure === 'none' ) ? '' : $secure;
    }

    /**
     * Send a test email.
     *
     * @param string $to Recipient address.
     * @return bool|\WP_Error
     */
    public static function send_test( string $to ): bool {
        $result = wp_mail(
            $to,
            __( 'EIU Journal System — SMTP Test', 'eiu-rp' ),
            '<p>' . __( 'This is a test email from the EIU Research Publication plugin. If you received this, your SMTP settings are working correctly.', 'eiu-rp' ) . '</p>'
        );
        return $result;
    }
}
