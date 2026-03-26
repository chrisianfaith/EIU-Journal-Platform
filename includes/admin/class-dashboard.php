<?php
/**
 * Admin Dashboard Page.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */

namespace EIU_RP\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Article;

class Dashboard {
    public function render(): void {
        global $wpdb;

        $total_articles  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_articles" );
        $pending         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_articles WHERE status = %s", Article::STATUS_PENDING ) );
        $under_review    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_articles WHERE status = %s", Article::STATUS_UNDER_REVIEW ) );
        $published       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_articles WHERE status = %s", Article::STATUS_PUBLISHED ) );
        $total_reviewers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_reviewers WHERE verified = 1" );
        $total_reviews   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_reviews WHERE is_deleted = 0 AND status = 'submitted'" );

        $recent = Article::query( array( 'per_page' => 8, 'page' => 1 ) );
        $recent_items = $recent['items'] ?? array();

        $monthly = array();
        for ( $i = 5; $i >= 0; $i-- ) {
            $year  = date( 'Y', strtotime( "-{$i} months" ) );
            $month = date( 'm', strtotime( "-{$i} months" ) );
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_articles WHERE YEAR(submitted_at) = %d AND MONTH(submitted_at) = %d",
                $year, $month
            ) );
            $monthly[] = array( 'label' => date( 'M Y', strtotime( "-{$i} months" ) ), 'count' => $count );
        }

        \EIU_RP\Utils\Template_Loader::get_template( 'admin/dashboard.php', compact(
            'total_articles','pending','under_review','published','total_reviewers','total_reviews','recent_items','monthly'
        ) );
    }
}
