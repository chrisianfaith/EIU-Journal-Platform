<?php
/**
 * Admin: Researcher Applications List.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Application;
use EIU_RP\Utils\Helpers;

class Applications_List {

    public function render(): void {
        if ( ! current_user_can( 'eiu_manage_articles' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }
        $action = sanitize_text_field( $_GET['action'] ?? 'list' );
        $id     = absint( $_GET['id'] ?? 0 );

        if ( $action === 'view' && $id ) {
            $app = Application::get( $id );
            if ( $app ) {
                \EIU_RP\Utils\Template_Loader::get_template(
                    'admin/application-view.php', array( 'app' => $app )
                );
                return;
            }
        }

        $args   = array(
            'status'   => sanitize_text_field( $_GET['status'] ?? '' ),
            'search'   => sanitize_text_field( $_GET['s']      ?? '' ),
            'per_page' => 20,
            'page'     => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        );
        $result = Application::query( $args );
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/applications-list.php', array(
            'items'    => $result['items'],
            'total'    => $result['total'],
            'per_page' => $args['per_page'],
            'page'     => $args['page'],
            'filters'  => $args,
        ) );
    }
}
