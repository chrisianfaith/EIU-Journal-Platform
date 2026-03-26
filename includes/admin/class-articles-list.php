<?php
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }
use EIU_RP\Models\Article;
use EIU_RP\Models\Reviewer;
use EIU_RP\Models\Review;
use EIU_RP\Security\Security;

class Articles_List {
    public function render(): void {
        $action = sanitize_text_field( $_GET['action'] ?? 'list' );
        $id     = absint( $_GET['id'] ?? 0 );

        if ( $action === 'view' && $id ) {
            $article  = Article::get( $id );
            $reviews  = Review::get_by_article( $id );
            $reviewers_all = Reviewer::query( array( 'verified' => 1, 'per_page' => 200 ) );
            \EIU_RP\Utils\Template_Loader::get_template( 'admin/article-view.php', compact( 'article', 'reviews', 'reviewers_all' ) );
            return;
        }

        $args = array(
            'status'   => sanitize_text_field( $_GET['status'] ?? '' ),
            'search'   => sanitize_text_field( $_GET['s'] ?? '' ),
            'per_page' => 20,
            'page'     => max( 1, absint( $_GET['paged'] ?? 1 ) ),
        );
        $result = Article::query( $args );
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/articles-list.php', array(
            'items'    => $result['items'],
            'total'    => $result['total'],
            'per_page' => $args['per_page'],
            'page'     => $args['page'],
            'filters'  => $args,
        ) );
    }
}
