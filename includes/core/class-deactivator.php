<?php
/**
 * Plugin Deactivator.
 *
 * @package EIU_Research_Publication
 * @subpackage Core
 */

namespace EIU_RP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

    /**
     * Run deactivation routines.
     * NOTE: Data and roles are preserved. Use uninstall.php for full removal.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();

        // Clear any scheduled cron jobs.
        wp_clear_scheduled_hook( 'eiu_rp_daily_digest' );
        wp_clear_scheduled_hook( 'eiu_rp_review_reminders' );
    }
}
