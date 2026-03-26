<?php
/**
 * Email Mailer.
 *
 * @package EIU_Research_Publication
 * @subpackage Email
 */

namespace EIU_RP\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Mailer
 *
 * Sends all plugin emails via wp_mail with HTML support and logging.
 */
class Mailer {

    public function __construct() {
        // Hook into article/review events to send notifications.
        add_action( 'eiu_rp_article_created',        array( $this, 'on_article_submitted' ), 10, 3 );
        add_action( 'eiu_rp_article_status_changed', array( $this, 'on_article_status_changed' ), 10, 2 );
        add_action( 'eiu_rp_reviewer_assigned',      array( $this, 'on_reviewer_assigned' ), 10, 3 );
        add_action( 'eiu_rp_reviewer_registered',    array( $this, 'on_reviewer_registered' ), 10, 3 );
        add_action( 'eiu_rp_review_submitted',       array( $this, 'on_review_submitted' ), 10, 1 );
        // v2.2: Co-reviewer collaboration notifications.
        add_action( 'eiu_rp_co_reviewer_assigned',   array( $this, 'on_co_reviewer_assigned' ), 10, 4 );
        add_action( 'eiu_rp_reviewer_notes_saved',   array( $this, 'on_reviewer_notes_saved' ), 10, 4 );
        // v1.9: Researcher application workflow.
        add_action( 'eiu_rp_application_submitted',         array( $this, 'on_application_submitted' ),         10, 2 );
        add_action( 'eiu_rp_researcher_approved',            array( $this, 'on_researcher_approved' ),           10, 4 );
        add_action( 'eiu_rp_application_status_changed',    array( $this, 'on_application_status_changed' ),    10, 3 );
        // v2.0.1: Notify reviewer when admin assigns an application.
        add_action( 'eiu_rp_application_reviewer_assigned', array( $this, 'on_application_reviewer_assigned' ), 10, 2 );
        // Log wp_mail failures to help diagnose delivery issues.
        add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ), 10, 1 );
    }

    /** Plugin-configured from_name/from_email (empty = let WP/SMTP plugin decide). */
    private string $current_from_name  = '';
    private string $current_from_email = '';

    /**
     * wp_mail_from_name filter — only called when admin has set a custom from-name.
     * Runs at priority 20 so SMTP plugins (WP Mail SMTP, etc.) that hook at 1 or 10 win.
     *
     * @param string $name Default from name.
     * @return string
     */
    public function filter_from_name( string $name ): string {
        return $this->current_from_name ?: $name;
    }

    /**
     * wp_mail_from filter — only called when admin has set a custom from-email.
     * Runs at priority 20 so SMTP plugins that hook at 1 or 10 win.
     *
     * @param string $email Default from email.
     * @return string
     */
    public function filter_from_email( string $email ): string {
        return $this->current_from_email ?: $email;
    }

    /**
     * Log wp_mail failures to the activity log so they appear in the admin.
     *
     * @param \WP_Error $error
     */
    public function on_mail_failed( $error ): void {
        if ( ! is_wp_error( $error ) ) { return; }
        \EIU_RP\Models\Activity_Log::log(
            'email_failed', 'email', 0,
            'wp_mail failed: ' . $error->get_error_message()
        );
    }

    /**
     * Send a single email via wp_mail().
     *
     * WP SMTP compatibility notes:
     * - Always uses wp_mail() — never touches PHPMailer directly.
     * - Only applies from_name/from_email filters when the admin has explicitly
     *   configured custom values in EIU Settings. If the fields are left at their
     *   WP defaults, filters are not added, allowing WP Mail SMTP / other SMTP
     *   plugins to manage the from address unobstructed.
     * - Filters run at priority 20, lower than WP Mail SMTP's priority 1/10,
     *   so SMTP plugins always take precedence.
     * - The wp_mail_content_type filter is added and immediately removed after
     *   the call, preventing it from leaking into other plugins' mail calls.
     *
     * @param string|array $to       Recipient(s).
     * @param string       $subject  Email subject.
     * @param string       $body     HTML email body.
     * @param int          $user_id  Optional user ID for logging.
     * @param string       $type     Notification type slug.
     * @return bool
     */
    public function send( $to, string $subject, string $body, int $user_id = 0, string $type = '' ): bool {
        // Read admin-configured from values.
        $admin_from_email = get_option( 'eiu_rp_from_email', '' );
        $admin_from_name  = get_option( 'eiu_rp_from_name',  '' );

        // WP defaults — used to detect "no custom setting".
        $wp_default_email = get_option( 'admin_email' );
        $wp_default_name  = get_option( 'blogname' );

        // Only apply our from filters when the admin has set a value that differs
        // from the WP default. This lets WP Mail SMTP manage from-address freely.
        $apply_from_email = $admin_from_email && $admin_from_email !== $wp_default_email;
        $apply_from_name  = $admin_from_name  && $admin_from_name  !== $wp_default_name;

        $this->current_from_email = $apply_from_email ? $admin_from_email : '';
        $this->current_from_name  = $apply_from_name  ? $admin_from_name  : '';

        // Always set HTML content type (scoped: added before, removed after call).
        add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );

        // Add from filters only when custom values are configured, at priority 20
        // so they don't override WP Mail SMTP (which hooks at 1).
        if ( $apply_from_email ) {
            add_filter( 'wp_mail_from',      array( $this, 'filter_from_email' ), 20 );
        }
        if ( $apply_from_name ) {
            add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ),  20 );
        }

        $result = wp_mail( $to, $subject, $this->wrap_template( $subject, $body ) );

        // Remove all filters immediately after sending to prevent leaking.
        remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
        if ( $apply_from_email ) {
            remove_filter( 'wp_mail_from',      array( $this, 'filter_from_email' ), 20 );
        }
        if ( $apply_from_name ) {
            remove_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ),  20 );
        }

        // Log notification.
        if ( $type ) {
            $this->log_notification( $user_id, $type, $subject, $body, $result );
        }

        return $result;
    }

    /**
     * Content-type filter callback.
     *
     * @return string
     */
    public function set_html_content_type(): string {
        return 'text/html';
    }

    /**
     * Wrap email body in the default HTML template.
     *
     * @param string $subject Email subject.
     * @param string $body    Email body HTML.
     * @return string Full HTML email.
     */
    private function wrap_template( string $subject, string $body ): string {
        $template = Email_Templates::get( 'default' );
        return str_replace(
            array( '{{subject}}', '{{body}}', '{{site_name}}', '{{site_url}}', '{{year}}' ),
            array(
                esc_html( $subject ),
                $body,
                esc_html( get_option( 'blogname' ) ),
                esc_url( home_url() ),
                date( 'Y' ),
            ),
            $template
        );
    }

    /**
     * Log a sent notification to the DB.
     */
    private function log_notification( int $user_id, string $type, string $subject, string $body, bool $sent ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'eiu_notifications',
            array(
                'user_id' => $user_id,
                'type'    => $type,
                'subject' => $subject,
                'message' => $body,
                'sent_at' => current_time( 'mysql' ),
                'status'  => $sent ? 'sent' : 'failed',
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    // ── Event Handlers ──────────────────────────────────────────────────────────

    /**
     * Notify admin when a new article is submitted.
     *
     * @param int   $article_id Row ID.
     * @param int   $post_id    WP post ID.
     * @param array $data       Submitted data.
     */
    public function on_article_submitted( int $article_id, int $post_id, array $data ): void {
        $admin_email = get_option( 'eiu_rp_submission_notification_email', get_option( 'admin_email' ) );
        $subject     = Email_Templates::subject( 'article_submitted', array( 'article_title' => $data['title'] ?? '' ) );
        $body        = Email_Templates::article_submitted( $article_id, $data );

        $this->send( $admin_email, $subject, $body, 0, 'article_submitted' );

        // Confirm to author.
        if ( ! empty( $data['author_email'] ) ) {
            $author_subject = Email_Templates::subject( 'article_received', array( 'article_title' => $data['title'] ?? '' ) );
            $author_body   = Email_Templates::article_received( $article_id, $data );
            $this->send( $data['author_email'], $author_subject, $author_body, 0, 'article_received_confirmation' );
        }

        // v1.3: Notify all verified reviewers that a new submission is available.
        $reviewers = \EIU_RP\Models\Reviewer::query( array( 'verified' => 1, 'per_page' => 200 ) );
        if ( ! empty( $reviewers['items'] ) ) {
            $reviewer_subject = sprintf(
                __( 'New submission available for review: %s', 'eiu-rp' ),
                $data['title'] ?? ''
            );
            foreach ( $reviewers['items'] as $rv_row ) {
                if ( empty( $rv_row['email'] ) ) {
                    continue;
                }
                $rv_obj = (object) $rv_row;
                $rv_body = Email_Templates::new_submission_reviewer_notice( $article_id, $data, $rv_obj );
                $this->send( $rv_row['email'], $reviewer_subject, $rv_body, (int) ( $rv_row['user_id'] ?? 0 ), 'new_submission_reviewer_notice' );
            }
        }
    }

    /**
     * Notify author when their article status changes.
     *
     * @param int    $article_id Article row ID.
     * @param string $status     New status.
     */
    public function on_article_status_changed( int $article_id, string $status ): void {
        $article = \EIU_RP\Models\Article::get( $article_id );
        if ( ! $article || empty( $article->author_email ) ) {
            return;
        }

        $label   = \EIU_RP\Models\Article::status_label( $status );
        $subject = Email_Templates::subject( 'status_changed', array( 'article_title' => $article->title ?? '', 'status' => $label ) );
        $body    = Email_Templates::article_status_changed( $article_id, $article, $label );

        // Fire accepted/rejected specialised templates when applicable.
        if ( $status === \EIU_RP\Models\Article::STATUS_APPROVED ) {
            $subject = Email_Templates::subject( 'article_accepted', array( 'article_title' => $article->title ?? '' ) );
            $body    = Email_Templates::article_accepted( $article_id, $article );
        } elseif ( $status === \EIU_RP\Models\Article::STATUS_REJECTED ) {
            $subject = Email_Templates::subject( 'article_rejected', array( 'article_title' => $article->title ?? '' ) );
            $body    = Email_Templates::article_rejected( $article_id, $article );
        }

        $this->send( $article->author_email, $subject, $body, 0, 'article_status_' . $status );
    }

    /**
     * Notify reviewer when assigned.
     *
     * @param int $review_id   Review row ID.
     * @param int $article_id  Article row ID.
     * @param int $reviewer_id Reviewer row ID.
     */
    public function on_reviewer_assigned( int $review_id, int $article_id, int $reviewer_id ): void {
        $reviewer = \EIU_RP\Models\Reviewer::get( $reviewer_id );
        $article  = \EIU_RP\Models\Article::get( $article_id );

        if ( ! $reviewer || ! $article ) {
            return;
        }

        $subject = Email_Templates::subject( 'reviewer_assigned', array(
            'article_title'  => $article->title ?? '',
            'reviewer_name'  => $reviewer->full_name ?? '',
        ) );
        $body    = Email_Templates::reviewer_assigned( $review_id, $article, $reviewer );

        $this->send( $reviewer->email, $subject, $body, (int) $reviewer->user_id, 'reviewer_assigned' );
    }

    /**
     * Send verification email to new reviewer.
     *
     * @param int   $reviewer_id Reviewer row ID.
     * @param int   $user_id     WP user ID.
     * @param array $data        Registration data.
     */
    public function on_reviewer_registered( int $reviewer_id, int $user_id, array $data ): void {
        $reviewer = \EIU_RP\Models\Reviewer::get( $reviewer_id );
        if ( ! $reviewer ) {
            return;
        }

        $verify_url = add_query_arg( array(
            'eiu_rp_verify'   => $reviewer_id,
            'eiu_rp_key'      => get_user_meta( $user_id, 'eiu_rp_verification_key', true ),
        ), home_url() );

        $subject = __( 'Verify your Reviewer Account', 'eiu-rp' );
        $body    = Email_Templates::reviewer_verify( $reviewer, $verify_url );

        $this->send( $reviewer->email, $subject, $body, $user_id, 'reviewer_verification' );
    }

    /**
     * Notify admin when a review is submitted.
     *
     * @param int $review_id Review row ID.
     */
    public function on_review_submitted( int $review_id ): void {
        $review      = \EIU_RP\Models\Review::get( $review_id );
        $admin_email = get_option( 'admin_email' );

        if ( ! $review ) {
            return;
        }

        $subject = Email_Templates::subject( 'review_submitted', array(
            'article_title'  => $review->article_title ?? '',
            'reviewer_name'  => $review->reviewer_name ?? '',
        ) );
        $body    = Email_Templates::review_submitted( $review );

        $this->send( $admin_email, $subject, $body, 0, 'review_submitted' );
    }

    /**
     * Send revision-required notification to the researcher.
     *
     * Fires on the eiu_rp_revision_required action, which is triggered inside
     * Article::update_status() only when status becomes revision_required.
     * This keeps the email separate from the generic status-change email so we
     * can include the reviewer notes and the direct edit link.
     *
     * @param int    $article_id     Article row ID.
     * @param string $revision_notes Reviewer feedback captured at review submission.
     */
    public function on_revision_required( int $article_id, string $revision_notes ): void {
        $article = \EIU_RP\Models\Article::get( $article_id );
        if ( ! $article || empty( $article->author_email ) ) {
            return;
        }

        // Build direct edit/resubmit URL pointing to the researcher dashboard.
        $dashboard_id  = get_option( 'eiu_rp_researcher_dashboard_page_id' );
        $dashboard_url = $dashboard_id
            ? add_query_arg( array( 'tab' => 'resubmit', 'article_id' => $article_id ), get_permalink( $dashboard_id ) )
            : home_url( '/researcher-dashboard/?tab=resubmit&article_id=' . $article_id );

        $subject = Email_Templates::subject( 'revision_required', array(
            'article_title' => $article->title ?? '',
            'author_name'   => $article->author_name ?? '',
        ) );

        $body = Email_Templates::revision_required( $article_id, $article, $revision_notes, $dashboard_url );

        // Log + send. Use a unique type key so duplicate-send guard applies per article.
        $sent = $this->send(
            $article->author_email,
            $subject,
            $body,
            0,
            'revision_required_' . $article_id
        );

        \EIU_RP\Models\Activity_Log::log(
            'revision_email_sent',
            'article',
            $article_id,
            $sent
                ? sprintf( 'Revision-required email sent to %s for article #%d.', $article->author_email, $article_id )
                : sprintf( 'FAILED to send revision-required email to %s for article #%d.', $article->author_email, $article_id )
        );
    }
    /**
     * v2.2: Send notification to a co-reviewer when they are assigned.
     *
     * @param int    $review_id    Review row ID.
     * @param object $co_reviewer  Co-reviewer object (recipient).
     * @param object $lead_reviewer Lead reviewer object (who assigned them).
     * @param object $article      Article object.
     */
    public function on_co_reviewer_assigned( int $review_id, object $co_reviewer, object $lead_reviewer, object $article ): void {
        if ( empty( $co_reviewer->email ) ) {
            return;
        }

        $login_url = get_permalink( get_option( 'eiu_rp_reviewer_access_page_id' ) ) ?: home_url();

        $subject = Email_Templates::subject( 'co_reviewer_assigned', array(
            'article_title'      => $article->title ?? '',
            'co_reviewer_name'   => $co_reviewer->full_name,
            'lead_reviewer_name' => $lead_reviewer->full_name,
            'site_name'          => get_option( 'blogname' ),
        ) );

        $body = Email_Templates::co_reviewer_assigned_body( $co_reviewer, $lead_reviewer, $article, $login_url );

        $sent = $this->send( $co_reviewer->email, $subject, $body, (int) ( $co_reviewer->user_id ?? 0 ), 'co_reviewer_assigned' );

        Activity_Log::log(
            $sent ? 'co_reviewer_email_sent' : 'co_reviewer_email_failed',
            'review',
            $review_id,
            sprintf(
                'Co-reviewer assignment email %s to %s for review #%d.',
                $sent ? 'sent' : 'FAILED',
                $co_reviewer->email,
                $review_id
            )
        );
    }

    /**
     * v2.2: Notify all current co-reviewers when the lead reviewer saves private notes.
     *
     * @param int    $review_id    Review row ID.
     * @param object $lead_reviewer Reviewer who wrote the notes.
     * @param string $co_reviewer_json JSON-encoded array of co-reviewer IDs.
     * @param string $notes        The notes content (HTML).
     */
    public function on_reviewer_notes_saved( int $review_id, object $lead_reviewer, string $co_reviewer_json, string $notes ): void {
        $co_ids = json_decode( $co_reviewer_json, true );
        if ( ! is_array( $co_ids ) || empty( $co_ids ) ) {
            return;
        }

        global $wpdb;
        $review  = \EIU_RP\Models\Review::get( $review_id );
        $article = $review ? \EIU_RP\Models\Article::get( (int) $review->article_id ) : null;
        if ( ! $article ) {
            return;
        }

        $login_url = get_permalink( get_option( 'eiu_rp_reviewer_access_page_id' ) ) ?: home_url();

        foreach ( $co_ids as $co_id ) {
            $co_rv = \EIU_RP\Models\Reviewer::get( absint( $co_id ) );
            if ( ! $co_rv || empty( $co_rv->email ) ) {
                continue;
            }

            $subject = Email_Templates::subject( 'reviewer_notes_shared', array(
                'article_title'      => $article->title ?? '',
                'co_reviewer_name'   => $co_rv->full_name,
                'lead_reviewer_name' => $lead_reviewer->full_name,
                'site_name'          => get_option( 'blogname' ),
            ) );

            $body = Email_Templates::reviewer_notes_shared_body( $co_rv, $lead_reviewer, $article, $notes, $login_url );

            $this->send( $co_rv->email, $subject, $body, (int) ( $co_rv->user_id ?? 0 ), 'reviewer_notes_shared' );
        }
    }

    /**
     * v1.9: New application submitted — notify admin + confirm to applicant.
     *
     * @param int    $app_id Application row ID.
     * @param array  $data   Submitted data.
     */
    public function on_application_submitted( int $app_id, array $data ): void {
        $app = \EIU_RP\Models\Application::get( $app_id );
        if ( ! $app ) {
            return;
        }

        // Admin alert
        $admin_email = get_option( 'eiu_rp_submission_notification_email', get_option( 'admin_email' ) );
        $admin_subject = Email_Templates::subject( 'application_submitted', array(
            'full_name' => $app->full_name,
            'app_id'    => $app_id,
        ) );
        $admin_body = Email_Templates::application_submitted_admin_body( $app_id, $app );
        $this->send( $admin_email, $admin_subject, $admin_body, 0, 'application_submitted' );

        // Applicant confirmation
        $subject = Email_Templates::subject( 'application_received', array(
            'full_name' => $app->full_name,
            'app_id'    => $app_id,
        ) );
        $body = Email_Templates::application_received_body( $app_id, $app );
        $this->send( $app->email, $subject, $body, 0, 'application_received' );
    }

    /**
     * v1.9: Application approved — send credentials to new researcher.
     *
     * @param object $app      Application object.
     * @param string $username Generated username.
     * @param string $password Plain-text generated password.
     * @param int    $user_id  New WP user ID.
     */
    public function on_researcher_approved( object $app, string $username, string $password, int $user_id ): void {
        $login_url = get_permalink( get_option( 'eiu_rp_unified_login_page_id' ) )
            ?: get_permalink( get_option( 'eiu_rp_researcher_login_page_id' ) )
            ?: home_url( '/login/' );

        $subject = Email_Templates::subject( 'application_approved', array(
            'full_name' => $app->full_name,
            'site_name' => get_option( 'blogname' ),
        ) );
        $body = Email_Templates::application_approved_body( $app, $username, $password, $login_url );
        $sent = $this->send( $app->email, $subject, $body, $user_id, 'application_approved' );

        Activity_Log::log(
            $sent ? 'credentials_email_sent' : 'credentials_email_failed',
            'application',
            (int) $app->id,
            sprintf( 'Credentials email %s to %s.', $sent ? 'sent' : 'FAILED', $app->email )
        );
    }

    /**
     * v1.9: Application status changed — notify applicant of rejection or more-info.
     *
     * @param int    $app_id      Application row ID.
     * @param string $status      New status slug.
     * @param string $admin_notes Admin feedback.
     */
    public function on_application_status_changed( int $app_id, string $status, string $admin_notes ): void {
        $app = \EIU_RP\Models\Application::get( $app_id );
        if ( ! $app ) {
            return;
        }

        if ( $status === \EIU_RP\Models\Application::STATUS_REJECTED ) {
            $subject = Email_Templates::subject( 'application_rejected', array( 'full_name' => $app->full_name ) );
            $body    = Email_Templates::application_rejected_body( $app, $admin_notes );
            $this->send( $app->email, $subject, $body, 0, 'application_rejected' );

        } elseif ( $status === \EIU_RP\Models\Application::STATUS_MORE_INFO ) {
            $apply_url = get_permalink( get_option( 'eiu_rp_apply_page_id' ) ) ?: home_url();
            $subject   = Email_Templates::subject( 'application_more_info', array( 'full_name' => $app->full_name ) );
            $body      = Email_Templates::application_more_info_body( $app, $admin_notes, $apply_url );
            $this->send( $app->email, $subject, $body, 0, 'application_more_info' );
        }
        // Approved is handled by on_researcher_approved via do_action('eiu_rp_researcher_approved')
    }

    /**
     * v2.0.1: Reviewer assigned to an application — notify the reviewer.
     *
     * @param int $app_id      Application row ID.
     * @param int $reviewer_id Reviewer row ID (eiu_reviewers.id).
     */
    public function on_application_reviewer_assigned( int $app_id, int $reviewer_id ): void {
        $app      = \EIU_RP\Models\Application::get( $app_id );
        $reviewer = \EIU_RP\Models\Reviewer::get( $reviewer_id );

        if ( ! $app || ! $reviewer || empty( $reviewer->email ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $admin_url = admin_url( 'admin.php?page=eiu-rp-applications&action=view&id=' . $app_id );

        // Try to build a frontend reviewer dashboard link to the application tab.
        $rv_page_id   = get_option( 'eiu_rp_reviewer_access_page_id' );
        $frontend_url = $rv_page_id
            ? add_query_arg( array( 'tab' => 'applications', 'app_id' => $app_id ), get_permalink( $rv_page_id ) )
            : $admin_url;

        $subject = Email_Templates::subject( 'application_reviewer_assigned', array(
            'full_name'    => $reviewer->full_name,
            'site_name'    => $site_name,
        ) );

        $body = Email_Templates::application_reviewer_assigned_body( $app, $reviewer, $frontend_url );
        $this->send( $reviewer->email, $subject, $body, 0, 'application_reviewer_assigned' );

        Activity_Log::log(
            'application_reviewer_notified',
            'application',
            $app_id,
            sprintf( 'Reviewer %s notified of assignment to application #%d.', $reviewer->email, $app_id )
        );
    }

}
