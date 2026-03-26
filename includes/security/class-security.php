<?php
/**
 * Security Layer.
 *
 * @package EIU_Research_Publication
 * @subpackage Security
 */

namespace EIU_RP\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Security
 *
 * Central security utilities: nonce management, sanitization, rate limiting.
 */
class Security {

    /**
     * Rate limit transient prefix.
     */
    const RATE_LIMIT_PREFIX = 'eiu_rp_rl_';

    /**
     * Max submissions per IP per hour.
     */
    const SUBMISSION_RATE_LIMIT = 20;

    public function __construct() {
        add_action( 'init', array( $this, 'setup_security_headers' ) );
    }

    /**
     * Output security-related HTTP headers on frontend.
     */
    public function setup_security_headers(): void {
        if ( is_admin() ) {
            return;
        }
        if ( ! headers_sent() ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        }
    }

    /**
     * Verify a frontend nonce and die on failure.
     *
     * @param string $nonce   Nonce value from request.
     * @param string $action  Nonce action.
     */
    public static function verify_nonce( string $nonce, string $action = 'eiu_rp_frontend' ): void {
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_send_json_error( array( 'message' => __( 'Security token expired. Please refresh and try again.', 'eiu-rp' ) ), 403 );
        }
    }

    /**
     * Verify an admin nonce and die on failure.
     *
     * @param string $nonce  Nonce value.
     * @param string $action Nonce action.
     */
    public static function verify_admin_nonce( string $nonce, string $action ): void {
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            wp_die( esc_html__( 'Security check failed.', 'eiu-rp' ), 403 );
        }
    }

    /**
     * Check whether the current request is within rate limit.
     *
     * @param string $identifier Unique identifier (IP, user ID, etc.).
     * @param int    $max        Max attempts allowed.
     * @param int    $window     Time window in seconds.
     * @return bool True if allowed, false if limit exceeded.
     */
    public static function check_rate_limit( string $identifier, int $max = self::SUBMISSION_RATE_LIMIT, int $window = HOUR_IN_SECONDS ): bool {
        $key     = self::RATE_LIMIT_PREFIX . md5( $identifier );
        $current = (int) get_transient( $key );

        if ( $current >= $max ) {
            return false;
        }

        if ( $current === 0 ) {
            set_transient( $key, 1, $window );
        } else {
            set_transient( $key, $current + 1, $window );
        }

        return true;
    }

    /**
     * Get the visitor's real IP address.
     *
     * @return string
     */
    public static function get_ip(): string {
        $keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated proxy chain.
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Sanitize a text field.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_text( string $value ): string {
        return sanitize_text_field( wp_unslash( $value ) );
    }

    /**
     * Sanitize a textarea / multi-line text field.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function sanitize_textarea( string $value ): string {
        return sanitize_textarea_field( wp_unslash( $value ) );
    }

    /**
     * Sanitize an email address.
     *
     * @param string $email Raw email.
     * @return string
     */
    public static function sanitize_email( string $email ): string {
        return sanitize_email( wp_unslash( $email ) );
    }

    /**
     * Sanitize a URL.
     *
     * @param string $url Raw URL.
     * @return string
     */
    public static function sanitize_url( string $url ): string {
        return esc_url_raw( wp_unslash( $url ) );
    }

    /**
     * Sanitize a positive integer.
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public static function sanitize_int( $value ): int {
        return absint( $value );
    }

    /**
     * Validates required fields from a request array.
     *
     * @param array $data   Data to validate.
     * @param array $fields Required field keys.
     * @return array List of missing field keys.
     */
    public static function validate_required( array $data, array $fields ): array {
        $missing = array();
        foreach ( $fields as $field ) {
            if ( empty( $data[ $field ] ) ) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Sanitize POST data array.
     *
     * @param array $fields Field => type mapping. Types: text, textarea, email, url, int.
     * @return array Sanitized values.
     */
    public static function sanitize_post_fields( array $fields ): array {
        $sanitized = array();
        foreach ( $fields as $key => $type ) {
            $raw = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ''; // phpcs:ignore WordPress.Security.NonceVerification
            switch ( $type ) {
                case 'email':
                    $sanitized[ $key ] = self::sanitize_email( (string) $raw );
                    break;
                case 'url':
                    $sanitized[ $key ] = self::sanitize_url( (string) $raw );
                    break;
                case 'int':
                    $sanitized[ $key ] = self::sanitize_int( $raw );
                    break;
                case 'textarea':
                    $sanitized[ $key ] = self::sanitize_textarea( (string) $raw );
                    break;
                case 'html': // v1.1: rich editor content
                    $sanitized[ $key ] = wp_kses_post( wp_unslash( (string) $raw ) );
                    break;
                default:
                    $sanitized[ $key ] = self::sanitize_text( (string) $raw );
            }
        }
        return $sanitized;
    }
}
