<?php
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Models\Reviewer;
use EIU_RP\Models\Review;

class Reviewers_List {
    public function render(): void {
        $action = sanitize_text_field( $_GET['action'] ?? 'list' );
        $id     = absint( $_GET['id'] ?? 0 );

        if ( $action === 'view' && $id ) {
            $reviewer = Reviewer::get( $id );
            $reviews  = Review::get_by_reviewer( $id );
            \EIU_RP\Utils\Template_Loader::get_template( 'admin/reviewer-view.php', compact( 'reviewer', 'reviews' ) );
            return;
        }

        $args = array(
            'verified'  => isset( $_GET['verified'] ) && $_GET['verified'] !== '' ? (int) $_GET['verified'] : '',
            'search'    => sanitize_text_field( $_GET['s'] ?? '' ),
            'per_page'  => 20,
            'page'      => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        );
        $result = Reviewer::query( $args );
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/reviewers-list.php', array(
            'items'    => $result['items'],
            'total'    => $result['total'],
            'per_page' => $args['per_page'],
            'page'     => $args['page'],
            'filters'  => $args,
        ) );
    }
}
