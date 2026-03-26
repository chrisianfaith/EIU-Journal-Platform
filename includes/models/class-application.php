<?php
/**
 * Researcher Application Model.
 *
 * @package EIU_Research_Publication
 * @subpackage Models
 */
namespace EIU_RP\Models;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Application {

    const STATUS_PENDING     = 'pending';
    const STATUS_REVIEWING   = 'reviewing';
    const STATUS_APPROVED    = 'approved';
    const STATUS_REJECTED    = 'rejected';
    const STATUS_MORE_INFO   = 'more_info_required';

    /**
     * Create a new application record.
     *
     * @param array $data Sanitized field data.
     * @return int|WP_Error Row ID or error.
     */
    public static function create( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'eiu_researcher_applications',
            array(
                'full_name'       => $data['full_name']       ?? '',
                'title'           => $data['title']           ?? '',
                'designation'     => $data['designation']     ?? '',
                'country'         => $data['country']         ?? '',
                'academic_bg'     => $data['academic_bg']     ?? '',
                'gender'          => $data['gender']          ?? '',
                'date_of_birth'   => $data['date_of_birth']   ?? '',
                'student_number'  => $data['student_number']  ?? '',
                'email'           => $data['email']           ?? '',
                'expertise'       => $data['expertise']       ?? '',
                'about'           => $data['about']           ?? '',
                'cv_file_path'    => $data['cv_file_path']    ?? '',
                'cv_file_name'    => $data['cv_file_name']    ?? '',
                'research_file_path' => $data['research_file_path'] ?? '',
                'research_file_name' => $data['research_file_name'] ?? '',
                'status'          => self::STATUS_PENDING,
                'submitted_at'    => current_time( 'mysql' ),
            ),
            array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' )
        );

        if ( ! $result ) {
            return new \WP_Error( 'db_insert_failed', __( 'Could not save application.', 'eiu-rp' ) );
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Get a single application by ID.
     */
    public static function get( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eiu_researcher_applications WHERE id = %d",
            $id
        ) );
    }

    /**
     * Query applications with optional filters.
     *
     * @return array { items, total }
     */
    public static function query( array $args = array() ): array {
        global $wpdb;
        $defaults = array(
            'status'   => '',
            'search'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'submitted_at',
            'order'    => 'DESC',
        );
        $args  = wp_parse_args( $args, $defaults );
        $where = array( '1=1' );

        if ( $args['status'] !== '' ) {
            $where[] = $wpdb->prepare( 'status = %s', $args['status'] );
        }
        if ( ! empty( $args['search'] ) ) {
            $like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = $wpdb->prepare(
                '(full_name LIKE %s OR email LIKE %s OR expertise LIKE %s)',
                $like, $like, $like
            );
        }

        $where_sql = implode( ' AND ', $where );
        $table     = $wpdb->prefix . 'eiu_researcher_applications';
        $allowed   = array( 'submitted_at', 'full_name', 'email', 'status' );
        $orderby   = in_array( $args['orderby'], $allowed, true ) ? $args['orderby'] : 'submitted_at';
        $order     = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $offset    = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $limit     = absint( $args['per_page'] );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
        $items = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}"
        );
        // phpcs:enable

        return array( 'items' => $items ?: array(), 'total' => $total );
    }

    /**
     * Update application status + optional admin notes.
     */
    public static function update_status( int $id, string $status, string $admin_notes = '', int $assigned_reviewer_id = 0 ): bool {
        global $wpdb;
        $data   = array( 'status' => $status );
        $format = array( '%s' );

        if ( $admin_notes !== '' ) {
            $data['admin_notes'] = $admin_notes;
            $format[]            = '%s';
        }
        if ( $assigned_reviewer_id > 0 ) {
            $data['assigned_reviewer_id'] = $assigned_reviewer_id;
            $format[]                     = '%d';
        }
        if ( $status === self::STATUS_APPROVED ) {
            $data['approved_at'] = current_time( 'mysql' );
            $format[]            = '%s';
        }

        return $wpdb->update(
            $wpdb->prefix . 'eiu_researcher_applications',
            $data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        ) !== false;
    }

    /**
     * Status label map.
     */
    public static function status_label( string $status ): string {
        $map = array(
            self::STATUS_PENDING    => __( 'Pending Review', 'eiu-rp' ),
            self::STATUS_REVIEWING  => __( 'Under Review', 'eiu-rp' ),
            self::STATUS_APPROVED   => __( 'Approved', 'eiu-rp' ),
            self::STATUS_REJECTED   => __( 'Rejected', 'eiu-rp' ),
            self::STATUS_MORE_INFO  => __( 'More Information Required', 'eiu-rp' ),
        );
        return $map[ $status ] ?? ucfirst( $status );
    }
}
