<?php
/**
 * Activity Log Model.
 *
 * @package EIU_Research_Publication
 * @subpackage Models
 */

namespace EIU_RP\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Activity_Log
 *
 * Handles writing and reading the activity log table.
 */
class Activity_Log {

    /**
     * @var string
     */
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'eiu_activity_log';
    }

    /**
     * Write a log entry.
     *
     * @param string $action      Short action slug, e.g. 'article_submitted'.
     * @param string $object_type Object type, e.g. 'article', 'review'.
     * @param int    $object_id   Object ID.
     * @param string $description Human-readable description.
     * @param int    $user_id     User ID (0 for guests).
     * @return int|false Inserted row ID or false on failure.
     */
    public static function log(
        string $action,
        string $object_type = '',
        int    $object_id   = 0,
        string $description = '',
        int    $user_id     = 0
    ) {
        global $wpdb;

        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }

        $ip         = \EIU_RP\Security\Security::get_ip();
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
            : '';

        $result = $wpdb->insert(
            $wpdb->prefix . 'eiu_activity_log',
            array(
                'user_id'     => $user_id,
                'action'      => sanitize_key( $action ),
                'object_type' => sanitize_text_field( $object_type ),
                'object_id'   => absint( $object_id ),
                'description' => sanitize_textarea_field( $description ),
                'ip_address'  => $ip,
                'user_agent'  => substr( $user_agent, 0, 500 ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Query activity logs with pagination and filtering.
     *
     * @param array $args {
     *     @type int    $user_id     Filter by user.
     *     @type string $action      Filter by action.
     *     @type string $object_type Filter by object type.
     *     @type int    $object_id   Filter by object ID.
     *     @type string $date_from   Date range start (Y-m-d).
     *     @type string $date_to     Date range end (Y-m-d).
     *     @type int    $per_page    Records per page (default 50).
     *     @type int    $page        Current page (default 1).
     *     @type string $orderby     Column (default 'created_at').
     *     @type string $order       ASC|DESC (default 'DESC').
     * }
     * @return array { 'items' => array, 'total' => int }
     */
    public static function query( array $args = array() ): array {
        global $wpdb;

        $defaults = array(
            'user_id'     => 0,
            'action'      => '',
            'object_type' => '',
            'object_id'   => 0,
            'date_from'   => '',
            'date_to'     => '',
            'per_page'    => 50,
            'page'        => 1,
            'orderby'     => 'created_at',
            'order'       => 'DESC',
        );

        $args    = wp_parse_args( $args, $defaults );
        $table   = $wpdb->prefix . 'eiu_activity_log';
        $where   = array( '1=1' );
        $formats = array();

        if ( ! empty( $args['user_id'] ) ) {
            $where[]   = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
        }
        if ( ! empty( $args['action'] ) ) {
            $where[]   = $wpdb->prepare( 'action = %s', $args['action'] );
        }
        if ( ! empty( $args['object_type'] ) ) {
            $where[]   = $wpdb->prepare( 'object_type = %s', $args['object_type'] );
        }
        if ( ! empty( $args['object_id'] ) ) {
            $where[]   = $wpdb->prepare( 'object_id = %d', $args['object_id'] );
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where[]   = $wpdb->prepare( 'created_at >= %s', $args['date_from'] . ' 00:00:00' );
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where[]   = $wpdb->prepare( 'created_at <= %s', $args['date_to'] . ' 23:59:59' );
        }

        $where_sql = implode( ' AND ', $where );

        // Whitelist orderby.
        $allowed_orderby = array( 'created_at', 'user_id', 'action', 'object_type' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $offset  = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $limit   = absint( $args['per_page'] );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
        $items = $wpdb->get_results(
            "SELECT l.*, u.user_login FROM {$table} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}",
            ARRAY_A
        );
        // phpcs:enable

        return array(
            'items' => $items ?: array(),
            'total' => $total,
        );
    }

    /**
     * Log admin welcome screen acceptance.
     *
     * @param int $user_id User ID.
     */
    public static function log_welcome_accept( int $user_id ): void {
        // Store in user meta.
        update_user_meta( $user_id, 'eiu_rp_welcome_accepted', current_time( 'mysql' ) );

        // Append to the option array.
        $accepted = (array) get_option( 'eiu_rp_welcome_accepted', array() );
        $accepted[ $user_id ] = array(
            'time' => current_time( 'mysql' ),
            'ip'   => \EIU_RP\Security\Security::get_ip(),
        );
        update_option( 'eiu_rp_welcome_accepted', $accepted );

        // Write to log.
        self::log(
            'welcome_accepted',
            'admin',
            $user_id,
            sprintf( 'User #%d accepted the monitoring disclaimer.', $user_id ),
            $user_id
        );
    }
}
