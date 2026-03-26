<?php
namespace EIU_RP\Roles;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Roles_Manager {
    public function __construct() {
        add_filter( 'user_has_cap', array( $this, 'grant_caps' ), 10, 4 );
        // Ensure admin caps are present on every load (safe after updates that skip activation hook).
        add_action( 'init', array( $this, 'ensure_admin_caps' ), 1 );
    }

    public function grant_caps( array $all_caps, array $caps, array $args, \WP_User $user ): array {
        if ( in_array( 'eiu_reviewer', (array) $user->roles, true ) ) {
            $all_caps['eiu_review_articles']   = true;
            $all_caps['eiu_manage_own_review'] = true;
            $all_caps['read']                  = true;
        }
        return $all_caps;
    }

    /**
     * Re-apply all plugin capabilities to the administrator role on every load.
     * Safe to call repeatedly — WP_Role::add_cap() is idempotent.
     * Fixes the case where the plugin is updated by ZIP upload (activation
     * hook does not fire on update, so caps may be missing).
     */
    public function ensure_admin_caps(): void {
        $admin = get_role( 'administrator' );
        if ( ! $admin ) {
            return;
        }
        $caps = array(
            'eiu_manage_articles',
            'eiu_manage_reviewers',
            'eiu_manage_reviews',
            'eiu_view_activity_log',
            'eiu_manage_settings',
        );
        foreach ( $caps as $cap ) {
            if ( ! isset( $admin->capabilities[ $cap ] ) ) {
                $admin->add_cap( $cap );
            }
        }
    }
}
