<?php
namespace EIU_RP\Utils;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Helpers {
    public static function pagination_links( int $total, int $per_page, int $current_page, string $base_url ): string {
        $total_pages = (int) ceil( $total / $per_page );
        if ( $total_pages <= 1 ) { return ''; }
        $html  = '<div class="eiu-rp-pagination tablenav"><div class="tablenav-pages">';
        $html .= '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total, 'eiu-rp' ), number_format_i18n( $total ) ) . '</span>';
        $html .= paginate_links( array(
            'base'      => add_query_arg( 'paged', '%#%', $base_url ),
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ) );
        $html .= '</div></div>';
        return $html;
    }

    public static function status_badge( ?string $status ): string {
        $status = $status ?? 'pending';
        $map = array(
            'pending'           => 'status-pending',
            'under_review'      => 'status-review',
            'approved'          => 'status-approved',
            'published'         => 'status-published',
            'rejected'          => 'status-rejected',
            'revision_required' => 'status-revision',
        );
        $class = $map[ $status ] ?? 'status-default';
        $label = \EIU_RP\Models\Article::status_label( $status );
        return '<span class="eiu-rp-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
    }

    public static function time_ago( string $datetime ): string {
        return human_time_diff( strtotime( $datetime ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'eiu-rp' );
    }

    public static function subjects_list(): array {
        return (array) get_option( 'eiu_rp_subjects', array() );
    }
}
