<?php
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Models\Activity_Log;

class Activity_Log_Page {
    public function render(): void {
        if ( ! current_user_can( 'eiu_view_activity_log' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }
        $args = array(
            'user_id'     => absint( $_GET['user_id'] ?? 0 ),
            'action'      => sanitize_text_field( $_GET['action_filter'] ?? '' ),
            'object_type' => sanitize_text_field( $_GET['object_type'] ?? '' ),
            'date_from'   => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'     => sanitize_text_field( $_GET['date_to'] ?? '' ),
            'per_page'    => 50,
            'page'        => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        );
        $result = Activity_Log::query( $args );
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/activity-log.php', array(
            'items'    => $result['items'],
            'total'    => $result['total'],
            'per_page' => $args['per_page'],
            'page'     => $args['page'],
            'filters'  => $args,
        ) );
    }
}
