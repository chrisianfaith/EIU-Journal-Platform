<?php
/**
 * Reviewer Model.
 *
 * @package EIU_Research_Publication
 * @subpackage Models
 */

namespace EIU_RP\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Reviewer
 */
class Reviewer {

    /**
     * Register a new reviewer (creates WP user if needed).
     *
     * @param array $data Sanitized data.
     * @return int|WP_Error Reviewer row ID or error.
     */
    public static function register( array $data ) {
        global $wpdb;

        // Check for existing user.
        $user = get_user_by( 'email', $data['email'] );
        if ( ! $user ) {
            $user_id = wp_create_user(
                sanitize_user( explode( '@', $data['email'] )[0] . '_' . wp_generate_password( 4, false ) ),
                wp_generate_password( 16 ),
                $data['email']
            );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
            wp_update_user( array( 'ID' => $user_id, 'role' => 'eiu_reviewer', 'display_name' => $data['full_name'] ) );
        } else {
            $user_id = $user->ID;
            // Add reviewer role without removing existing.
            $wp_user = new \WP_User( $user_id );
            $wp_user->add_role( 'eiu_reviewer' );
        }

        // Check if reviewer record exists.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}eiu_reviewers WHERE user_id = %d",
            $user_id
        ) );
        if ( $existing ) {
            return (int) $existing;
        }

        $verification_key = wp_generate_password( 32, false );

        $result = $wpdb->insert(
            $wpdb->prefix . 'eiu_reviewers',
            array(
                'user_id'          => $user_id,
                'full_name'        => $data['full_name'],
                'email'            => $data['email'],
                'organization'     => $data['organization'] ?? '',
                'specialization'   => $data['specialization'] ?? '',
                'verified'         => 0,
                'verification_key' => $verification_key,
                'registered_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( ! $result ) {
            return new \WP_Error( 'db_insert_failed', __( 'Could not create reviewer record.', 'eiu-rp' ) );
        }

        $reviewer_id = $wpdb->insert_id;

        // Store verification key in user meta for email link.
        update_user_meta( $user_id, 'eiu_rp_verification_key', $verification_key );

        do_action( 'eiu_rp_reviewer_registered', $reviewer_id, $user_id, $data );

        return $reviewer_id;
    }

    /**
     * Get a single reviewer by row ID.
     *
     * @param int $id Row ID.
     * @return object|null
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eiu_reviewers WHERE id = %d AND is_deleted = 0",
            $id
        ) );
    }

    /**
     * Get reviewer by WP user ID.
     *
     * @param int $user_id WP user ID.
     * @return object|null
     */
    public static function get_by_user( int $user_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eiu_reviewers WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Verify a reviewer by their verification key.
     *
     * @param int    $reviewer_id Reviewer ID.
     * @param string $key         Verification key.
     * @return bool
     */
    public static function verify( int $reviewer_id, string $key ): bool {
        global $wpdb;

        $reviewer = self::get( $reviewer_id );
        if ( ! $reviewer || $reviewer->verified ) {
            return false;
        }
        if ( ! hash_equals( $reviewer->verification_key, $key ) ) {
            return false;
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'eiu_reviewers',
            array( 'verified' => 1, 'verification_key' => '' ),
            array( 'id' => $reviewer_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * Query reviewers.
     *
     * @param array $args Query args.
     * @return array { 'items' => array, 'total' => int }
     */
    public static function query( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'verified'  => '',
            'search'    => '',
            'per_page'  => 20,
            'page'      => 1,
            'orderby'   => 'registered_at',
            'order'     => 'DESC',
        );

        $args  = wp_parse_args( $args, $defaults );
        $where = array( 'is_deleted = 0' );

        if ( $args['verified'] !== '' ) {
            $where[] = $wpdb->prepare( 'verified = %d', (int) $args['verified'] );
        }
        if ( ! empty( $args['search'] ) ) {
            $like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare( '(full_name LIKE %s OR email LIKE %s OR organization LIKE %s)', $like, $like, $like );
        }

        $where_sql = implode( ' AND ', $where );
        $table     = $wpdb->prefix . 'eiu_reviewers';
        $allowed   = array( 'registered_at', 'full_name', 'email', 'verified' );
        $orderby   = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'registered_at';
        $order     = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $limit     = absint( $args['per_page'] );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}",
            ARRAY_A
        );
        // phpcs:enable

        return array(
            'items' => $items ?: array(),
            'total' => $total,
        );
    }
}
