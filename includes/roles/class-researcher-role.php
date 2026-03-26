<?php
/**
 * Researcher Role — eiu_researcher.
 *
 * Capabilities:
 *   eiu_submit_articles  — submit new articles
 *   eiu_view_own_articles — view own submission status
 *   read                 — basic WP access
 *
 * @package EIU_Research_Publication
 * @subpackage Roles
 */
namespace EIU_RP\Roles;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Researcher_Role {

    /**
     * Register the role. Safe to call on every load — idempotent.
     */
    public static function register(): void {
        if ( get_role( 'eiu_researcher' ) ) {
            return;
        }
        add_role( 'eiu_researcher', __( 'Author', 'eiu-rp' ), array(
            'read'                  => true,
            'eiu_submit_articles'   => true,
            'eiu_view_own_articles' => true,
        ) );
    }

    /**
     * Check whether the given user can submit articles.
     * Allowed: administrators, editors, eiu_reviewer, eiu_researcher.
     *
     * @param int|\WP_User|null $user
     * @return bool
     */
    public static function can_submit( $user = null ): bool {
        if ( null === $user ) {
            $user = wp_get_current_user();
        } elseif ( is_int( $user ) ) {
            $user = get_user_by( 'id', $user );
        }
        if ( ! $user || ! $user->ID ) {
            return false;
        }
        $allowed_roles = array( 'administrator', 'editor', 'eiu_reviewer', 'eiu_researcher' );
        foreach ( $allowed_roles as $role ) {
            if ( in_array( $role, (array) $user->roles, true ) ) {
                return true;
            }
        }
        return false;
    }
}
