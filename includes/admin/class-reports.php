<?php
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Reports {
    public function render(): void {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $by_status = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$prefix}eiu_articles GROUP BY status", ARRAY_A );

        $by_subject = $wpdb->get_results(
            "SELECT t.name as subject, COUNT(tr.object_id) as count
             FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tt.taxonomy = 'eiu_subject'
             GROUP BY t.term_id ORDER BY count DESC LIMIT 10", ARRAY_A );

        $reviewer_perf = $wpdb->get_results(
            "SELECT rv.full_name, rv.email,
                    COUNT(r.id) as total_assigned,
                    SUM(CASE WHEN r.status = 'submitted' THEN 1 ELSE 0 END) as completed,
                    AVG(CASE WHEN r.submitted_at IS NOT NULL
                        THEN DATEDIFF(r.submitted_at, r.assigned_at) ELSE NULL END) as avg_days
             FROM {$prefix}eiu_reviewers rv
             LEFT JOIN {$prefix}eiu_reviews r ON rv.id = r.reviewer_id AND r.is_deleted = 0
             GROUP BY rv.id ORDER BY total_assigned DESC LIMIT 20", ARRAY_A );

        $monthly_trend = array();
        for ( $i = 11; $i >= 0; $i-- ) {
            $year  = date( 'Y', strtotime( "-{$i} months" ) );
            $month = date( 'm', strtotime( "-{$i} months" ) );
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}eiu_articles WHERE YEAR(submitted_at)=%d AND MONTH(submitted_at)=%d",
                $year, $month ) );
            $monthly_trend[] = array( 'label' => date( 'M Y', strtotime( "-{$i} months" ) ), 'count' => $count );
        }

        \EIU_RP\Utils\Template_Loader::get_template( 'admin/reports.php',
            compact( 'by_status','by_subject','reviewer_perf','monthly_trend' ) );
    }
}
