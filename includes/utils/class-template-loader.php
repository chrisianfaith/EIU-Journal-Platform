<?php
namespace EIU_RP\Utils;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Template_Loader {
    public static function get_template( string $template, array $data = array() ): void {
        $theme_file  = get_stylesheet_directory() . '/eiu-rp/' . $template;
        $plugin_file = EIU_RP_PATH . 'templates/' . $template;
        $file = file_exists( $theme_file ) ? $theme_file : $plugin_file;
        if ( ! file_exists( $file ) ) {
            echo '<p>' . sprintf( esc_html__( 'Template not found: %s', 'eiu-rp' ), esc_html( $template ) ) . '</p>';
            return;
        }
        if ( ! empty( $data ) ) {
            extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        }
        include $file;
    }
}
