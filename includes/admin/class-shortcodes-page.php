<?php
/**
 * Admin: Shortcodes Reference Page.
 *
 * @package EIU_Research_Publication
 * @subpackage Admin
 */
namespace EIU_RP\Admin;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Shortcodes_Page {
    public function render(): void {
        if ( ! current_user_can( 'eiu_manage_settings' ) ) {
            wp_die( esc_html__( 'Access denied.', 'eiu-rp' ) );
        }
        \EIU_RP\Utils\Template_Loader::get_template( 'admin/shortcodes-page.php' );
    }
}
