<?php
/**
 * Email Templates.
 *
 * @package EIU_Research_Publication
 * @subpackage Email
 */

namespace EIU_RP\Email;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Email_Templates
 *
 * Returns HTML strings for each email type.
 * Templates can be overridden via the 'eiu_rp_email_template_{type}' filter.
 */
class Email_Templates {

    /**
     * Get a base template by slug.
     *
     * @param string $slug Template slug.
     * @return string HTML template string.
     */
    public static function get( string $slug ): string {
        $template = self::default_wrapper();
        return apply_filters( "eiu_rp_email_template_{$slug}", $template );
    }

    /**
     * Default HTML email wrapper.
     *
     * @return string
     */
    private static function default_wrapper(): string {
        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{subject}}</title>
  <style>
    body { margin:0; padding:0; background:#f4f6f9; font-family: Arial, Helvetica, sans-serif; }
    .wrapper { max-width:600px; margin:40px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    .header { background:#003087; padding:28px 32px; text-align:center; }
    .header h1 { color:#ffffff; margin:0; font-size:22px; letter-spacing:.5px; }
    .body { padding:32px; color:#333333; line-height:1.7; font-size:15px; }
    .body h2 { color:#003087; margin-top:0; font-size:18px; }
    .info-table { width:100%; border-collapse:collapse; margin:20px 0; }
    .info-table td { padding:10px 14px; border:1px solid #e2e8f0; font-size:14px; }
    .info-table td:first-child { background:#f8fafc; font-weight:bold; width:35%; color:#555; }
    .btn { display:inline-block; padding:12px 28px; background:#003087; color:#ffffff!important; text-decoration:none; border-radius:5px; font-weight:bold; font-size:15px; margin:16px 0; }
    .footer { background:#f8fafc; padding:20px 32px; text-align:center; font-size:12px; color:#888; border-top:1px solid #e2e8f0; }
    .status-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:13px; font-weight:bold; }
    .status-pending { background:#fef9c3; color:#854d0e; }
    .status-approved { background:#dcfce7; color:#166534; }
    .status-rejected { background:#fee2e2; color:#991b1b; }
    .status-review { background:#dbeafe; color:#1e40af; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>EIU Research Publication</h1>
    </div>
    <div class="body">
      {{body}}
    </div>
    <div class="footer">
      <p>&copy; {{year}} {{site_name}} &mdash; <a href="{{site_url}}" style="color:#003087;">{{site_url}}</a></p>
      <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>
  </div>
</body>
</html>';
    }

    /**
     * Article submitted — admin notification body.
     *
     * @param int   $article_id Article row ID.
     * @param array $data       Submitted data.
     * @return string
     */
    public static function article_submitted( int $article_id, array $data ): string {
        $admin_url = admin_url( 'admin.php?page=eiu-rp-articles&action=view&id=' . $article_id );

        $body  = '<h2>' . esc_html__( 'New Article Submission', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . esc_html__( 'A new article has been submitted for review.', 'eiu-rp' ) . '</p>';
        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Title', 'eiu-rp' ) . '</td><td>' . esc_html( $data['title'] ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Author', 'eiu-rp' ) . '</td><td>' . esc_html( $data['author_name'] ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Email', 'eiu-rp' ) . '</td><td>' . esc_html( $data['author_email'] ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Organization', 'eiu-rp' ) . '</td><td>' . esc_html( $data['author_org'] ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Subject', 'eiu-rp' ) . '</td><td>' . esc_html( $data['subject'] ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Country', 'eiu-rp' ) . '</td><td>' . esc_html( $data['country'] ?? '' ) . '</td></tr>';
        if ( ! empty( $data['doi'] ) ) {
            $body .= '<tr><td>' . esc_html__( 'DOI', 'eiu-rp' ) . '</td><td>' . esc_html( $data['doi'] ) . '</td></tr>';
        }
        $body .= '</table>';
        $body .= '<a href="' . esc_url( $admin_url ) . '" class="btn">' . esc_html__( 'View Submission', 'eiu-rp' ) . '</a>';

        return self::render( 'article_submitted', apply_filters( 'eiu_rp_email_body_article_submitted', $body, $article_id, $data ), array(
            'article_title'  => $data['title'] ?? '',
            'author_name'    => $data['author_name'] ?? '',
            'author_email'   => $data['author_email'] ?? '',
            'subject'        => $data['subject'] ?? '',
            'article_id'     => $article_id,
            'admin_url'      => admin_url( 'admin.php?page=eiu-rp-articles&action=view&id=' . $article_id ),
            'site_name'      => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Article received — author confirmation body.
     *
     * @param int   $article_id Article row ID.
     * @param array $data       Submitted data.
     * @return string
     */
    public static function article_received( int $article_id, array $data ): string {
        $body  = '<h2>' . esc_html__( 'Thank you for your submission', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . sprintf(
            esc_html__( 'Dear %s,', 'eiu-rp' ),
            esc_html( $data['author_name'] ?? 'Author' )
        ) . '</p>';
        $body .= '<p>' . esc_html__( 'We have received your article submission. Our editorial team will review it and contact you with further updates.', 'eiu-rp' ) . '</p>';
        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Title', 'eiu-rp' ) . '</td><td>' . esc_html( $data['title'] ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . $article_id . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Status', 'eiu-rp' ) . '</td><td><span class="status-badge status-pending">' . esc_html__( 'Pending Review', 'eiu-rp' ) . '</span></td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Submitted', 'eiu-rp' ) . '</td><td>' . current_time( 'F j, Y g:i A' ) . '</td></tr>';
        $body .= '</table>';
        $body .= '<p>' . esc_html__( 'If you have any questions, please contact us at support@eiu.ac.', 'eiu-rp' ) . '</p>';

        return self::render( 'article_received', apply_filters( 'eiu_rp_email_body_article_received', $body, $article_id, $data ), array(
            'article_title'   => $data['title'] ?? '',
            'author_name'     => $data['author_name'] ?? '',
            'article_id'      => $article_id,
            'submission_date' => current_time( 'F j, Y' ),
            'site_name'       => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Article status changed body.
     *
     * @param int    $article_id Article row ID.
     * @param object $article    Article object.
     * @param string $label      Human-readable status label.
     * @return string
     */
    public static function article_status_changed( int $article_id, object $article, string $label ): string {
        $body  = '<h2>' . esc_html__( 'Article Status Update', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . sprintf(
            esc_html__( 'Dear %s,', 'eiu-rp' ),
            esc_html( $article->author_name )
        ) . '</p>';
        $body .= '<p>' . sprintf(
            esc_html__( 'The status of your article has been updated.', 'eiu-rp' )
        ) . '</p>';
        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Title', 'eiu-rp' ) . '</td><td>' . esc_html( $article->title ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . $article_id . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'New Status', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $label ) . '</strong></td></tr>';
        $body .= '</table>';
        $body .= '<p>' . esc_html__( 'You will receive further communications as the review process progresses.', 'eiu-rp' ) . '</p>';

        return self::render( 'status_changed', apply_filters( 'eiu_rp_email_body_status_changed', $body, $article_id, $article, $label ), array(
            'article_title' => $article->title ?? '',
            'author_name'   => $article->author_name ?? '',
            'status'        => $label,
            'article_id'    => $article_id,
            'site_name'     => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Reviewer assigned body.
     *
     * @param int    $review_id Review row ID.
     * @param object $article   Article object.
     * @param object $reviewer  Reviewer object.
     * @return string
     */
    public static function reviewer_assigned( int $review_id, object $article, object $reviewer ): string {
        $dashboard_url = get_permalink( get_option( 'eiu_rp_reviewer_page_id' ) ) ?: home_url();

        $body  = '<h2>' . esc_html__( 'Review Assignment', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $reviewer->full_name ) ) . '</p>';
        $body .= '<p>' . esc_html__( 'You have been assigned to review the following article. Please log in to the reviewer dashboard to access the full submission.', 'eiu-rp' ) . '</p>';
        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Article Title', 'eiu-rp' ) . '</td><td>' . esc_html( $article->title ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Review ID', 'eiu-rp' ) . '</td><td>#' . $review_id . '</td></tr>';
        $body .= '</table>';
        $body .= '<a href="' . esc_url( $dashboard_url ) . '" class="btn">' . esc_html__( 'Go to Reviewer Dashboard', 'eiu-rp' ) . '</a>';

        return self::render( 'reviewer_assigned', apply_filters( 'eiu_rp_email_body_reviewer_assigned', $body, $review_id, $article, $reviewer ), array(
            'article_title'  => $article->title ?? '',
            'reviewer_name'  => $reviewer->full_name ?? '',
            'login_url'      => get_permalink( get_option( 'eiu_rp_reviewer_page_id' ) ) ?: home_url(),
            'site_name'      => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Reviewer verification email body.
     *
     * @param object $reviewer   Reviewer object.
     * @param string $verify_url Verification URL.
     * @return string
     */
    public static function reviewer_verify( object $reviewer, string $verify_url ): string {
        $body  = '<h2>' . esc_html__( 'Verify Your Reviewer Account', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $reviewer->full_name ) ) . '</p>';
        $body .= '<p>' . esc_html__( 'Thank you for registering as a reviewer. Please click the button below to verify your email address and activate your account.', 'eiu-rp' ) . '</p>';
        $body .= '<a href="' . esc_url( $verify_url ) . '" class="btn">' . esc_html__( 'Verify Account', 'eiu-rp' ) . '</a>';
        $body .= '<p><small>' . esc_html__( 'If the button does not work, copy and paste this link into your browser:', 'eiu-rp' ) . '<br>' . esc_url( $verify_url ) . '</small></p>';

        return apply_filters( 'eiu_rp_email_body_reviewer_verify', $body, $reviewer, $verify_url );
    }

    /**
     * Review submitted — admin notification body.
     *
     * @param object $review Review object.
     * @return string
     */
    public static function review_submitted( object $review ): string {
        $admin_url = admin_url( 'admin.php?page=eiu-rp-reviews&action=view&id=' . $review->id );

        $body  = '<h2>' . esc_html__( 'Review Submitted', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . esc_html__( 'A reviewer has submitted their review.', 'eiu-rp' ) . '</p>';
        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Article', 'eiu-rp' ) . '</td><td>' . esc_html( $review->article_title ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Reviewer', 'eiu-rp' ) . '</td><td>' . esc_html( $review->reviewer_name ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Recommendation', 'eiu-rp' ) . '</td><td>' . esc_html( \EIU_RP\Models\Review::recommendation_label( $review->recommendation ?? '' ) ) . '</td></tr>';
        $body .= '</table>';
        $body .= '<a href="' . esc_url( $admin_url ) . '" class="btn">' . esc_html__( 'View Review', 'eiu-rp' ) . '</a>';

        return self::render( 'review_submitted', apply_filters( 'eiu_rp_email_body_review_submitted', $body, $review ), array(
            'article_title'   => $review->article_title ?? '',
            'reviewer_name'   => $review->reviewer_name ?? '',
            'recommendation'  => \EIU_RP\Models\Review::recommendation_label( $review->recommendation ?? '' ),
            'admin_url'       => admin_url( 'admin.php?page=eiu-rp-reviews&action=view&id=' . $review->id ),
            'site_name'       => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * v1.3: New article submitted — notification to all verified reviewers.
     *
     * @param int   $article_id Article row ID.
     * @param array $data       Submission data.
     * @param array $reviewer   Reviewer row as object.
     * @return string
     */
    public static function new_submission_reviewer_notice( int $article_id, array $data, object $reviewer ): string {
        $dashboard_url = get_permalink( get_option( 'eiu_rp_reviewer_access_page_id' ) )
            ?: ( get_permalink( get_option( 'eiu_rp_reviewer_page_id' ) ) ?: home_url() );

        $body  = '<h2>' . esc_html__( 'New Article Submitted for Review', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $reviewer->full_name ) ) . '</p>';
        $body .= '<p>' . esc_html__( 'A new article has been submitted to EIU Journal System and is available for reviewer consideration.', 'eiu-rp' ) . '</p>';
        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Title', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $data['title'] ?? '' ) . '</strong></td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Author', 'eiu-rp' ) . '</td><td>' . esc_html( $data['author_name'] ?? '' ) . '</td></tr>';
        if ( ! empty( $data['subject'] ) ) {
            $body .= '<tr><td>' . esc_html__( 'Subject', 'eiu-rp' ) . '</td><td>' . esc_html( $data['subject'] ) . '</td></tr>';
        }
        $body .= '<tr><td>' . esc_html__( 'Reference', 'eiu-rp' ) . '</td><td>#' . $article_id . '</td></tr>';
        $body .= '</table>';
        $body .= '<p>' . esc_html__( 'If you are assigned to review this article, you will receive a separate notification with a direct link.', 'eiu-rp' ) . '</p>';
        $body .= '<a href="' . esc_url( $dashboard_url ) . '" class="btn">' . esc_html__( 'Open Reviewer Dashboard', 'eiu-rp' ) . '</a>';

        return self::render( 'reviewer_notice', apply_filters( 'eiu_rp_email_body_new_submission_reviewer', $body, $article_id, $data, $reviewer ), array(
            'article_title'  => $data['title'] ?? '',
            'author_name'    => $data['author_name'] ?? '',
            'subject'        => $data['subject'] ?? '',
            'reviewer_name'  => $reviewer->full_name ?? '',
            'login_url'      => get_permalink( get_option( 'eiu_rp_reviewer_access_page_id' ) ) ?: home_url(),
            'site_name'      => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Revision Required notification — sent to the researcher.
     *
     * @param int    $article_id     Article row ID.
     * @param object $article        Article object.
     * @param string $revision_notes Reviewer feedback.
     * @param string $edit_url       Direct link to resubmit the article.
     * @return string HTML email body.
     */
    public static function revision_required( int $article_id, object $article, string $revision_notes, string $edit_url ): string {
        $body  = '<h2>' . esc_html__( 'Revision Required', 'eiu-rp' ) . '</h2>';
        $body .= '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $article->author_name ) ) . '</p>';
        $body .= '<p>' . esc_html__( 'Your article has been reviewed and requires revisions before it can proceed. Please read the reviewer\'s feedback carefully and resubmit your updated article.', 'eiu-rp' ) . '</p>';

        $body .= '<table class="info-table">';
        $body .= '<tr><td>' . esc_html__( 'Title', 'eiu-rp' ) . '</td><td>' . esc_html( $article->title ?? '' ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'Article ID', 'eiu-rp' ) . '</td><td>#' . esc_html( (string) $article_id ) . '</td></tr>';
        $body .= '<tr><td>' . esc_html__( 'New Status', 'eiu-rp' ) . '</td><td><span class="status-badge status-review">' . esc_html__( 'Revision Required', 'eiu-rp' ) . '</span></td></tr>';
        $body .= '</table>';

        $body .= '<h3 style="margin:28px 0 10px;color:#003087;font-size:16px;">' . esc_html__( 'Reviewer Feedback', 'eiu-rp' ) . '</h3>';
        $body .= '<div style="background:#f8f9ff;border-left:4px solid #003087;padding:16px 20px;border-radius:0 6px 6px 0;font-size:15px;line-height:1.8;color:#333;">';
        $body .= nl2br( esc_html( $revision_notes ) );
        $body .= '</div>';

        $body .= '<div style="margin:28px 0;">';
        $body .= '<a href="' . esc_url( $edit_url ) . '" class="btn" style="display:inline-block;padding:13px 32px;background:#003087;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;font-size:15px;">';
        $body .= esc_html__( 'Revise &amp; Resubmit Article', 'eiu-rp' );
        $body .= '</a>';
        $body .= '</div>';

        $body .= '<p style="font-size:13px;color:#666;">' . esc_html__( 'If the button does not work, copy and paste this link into your browser:', 'eiu-rp' ) . '</p>';
        $body .= '<p style="font-size:13px;"><a href="' . esc_url( $edit_url ) . '" style="color:#003087;word-break:break-all;">' . esc_url( $edit_url ) . '</a></p>';

        $body .= '<p style="margin-top:24px;">' . esc_html__( 'If you have questions about the feedback, please contact the editorial team.', 'eiu-rp' ) . '</p>';

        return self::render( 'revision_required', apply_filters( 'eiu_rp_email_body_revision_required', $body, $article_id, $article, $revision_notes, $edit_url ), array(
            'article_title'  => $article->title ?? '',
            'author_name'    => $article->author_name ?? '',
            'revision_notes' => esc_html( $revision_notes ),
            'login_url'      => $edit_url,
            'article_id'     => $article_id,
            'site_name'      => get_bloginfo( 'name' ),
        ) );
    }
    /**
     * Replace template placeholders with real values.
     *
     * @param string $tpl    Template string with {placeholder} tokens.
     * @param array  $vars   Associative array of key => value.
     * @return string
     */
    public static function replace_vars( string $tpl, array $vars ): string {
        foreach ( $vars as $key => $value ) {
            $tpl = str_replace( '{' . $key . '}', (string) $value, $tpl );
        }
        return $tpl;
    }

    /**
     * Render a custom admin-saved template body, falling back to the provided default body.
     * Applies placeholder replacement on the custom template.
     *
     * @param string $type     Template type slug.
     * @param string $default  Default HTML body if no custom body saved.
     * @param array  $vars     Placeholder values.
     * @return string
     */
    public static function render( string $type, string $default, array $vars = array() ): string {
        $custom = class_exists( '\EIU_RP\Admin\Email_Template_Editor' )
            ? \EIU_RP\Admin\Email_Template_Editor::get_custom_body( $type )
            : '';
        $body   = $custom ? wp_kses_post( $custom ) : $default;
        if ( ! empty( $vars ) ) {
            $body = self::replace_vars( $body, $vars );
        }
        return apply_filters( "eiu_rp_email_body_{$type}", $body, $vars );
    }

    /**
     * Get the subject for a template type.
     * Returns the admin-saved custom subject, falling back to the default.
     *
     * @param string $type    Template type slug.
     * @param array  $vars    Placeholder values for the subject.
     * @return string
     */
    public static function subject( string $type, array $vars = array() ): string {
        if ( ! class_exists( '\EIU_RP\Admin\Email_Template_Editor' ) ) {
            return self::replace_vars(
                sprintf( '[%s] Notification', get_bloginfo( 'name' ) ),
                $vars
            );
        }
        $custom  = \EIU_RP\Admin\Email_Template_Editor::get_custom_subject( $type );
        $subject = $custom ?: \EIU_RP\Admin\Email_Template_Editor::get_default_subject( $type );
        return self::replace_vars( $subject, $vars );
    }

    // ── Ready-made default body templates ────────────────────────────────────

    /**
     * Reviewer OTP login email body.
     *
     * @param object $reviewer Reviewer object.
     * @param string $otp      Plain 6-digit code.
     * @return string
     */
    public static function reviewer_otp_body( object $reviewer, string $otp ): string {
        $default =
            '<h2>' . esc_html__( 'Reviewer Login — One-Time Code', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $reviewer->full_name ) ) . '</p>'
            . '<p>' . esc_html__( 'Use the one-time code below to complete your login.', 'eiu-rp' ) . '</p>'
            . '<div style="font-size:38px;font-weight:900;letter-spacing:.18em;color:#003087;'
            . 'background:#eef4ff;border-radius:10px;padding:18px 24px;text-align:center;margin:16px 0;font-family:monospace;">'
            . esc_html( $otp )
            . '</div>'
            . '<p style="font-size:13px;color:#6b7280;">' . esc_html__( 'This code is valid for 5 minutes and can only be used once.', 'eiu-rp' ) . '</p>'
            . '<p style="font-size:13px;color:#6b7280;">' . esc_html__( 'If you did not request this code, please ignore this email.', 'eiu-rp' ) . '</p>';

        return self::render( 'reviewer_otp', $default, array(
            'reviewer_name' => $reviewer->full_name,
            'otp_code'      => $otp,
            'site_name'     => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Article accepted notification body.
     *
     * @param int    $article_id Article row ID.
     * @param object $article    Article object.
     * @return string
     */
    public static function article_accepted( int $article_id, object $article ): string {
        $default =
            '<h2>' . esc_html__( 'Your Article Has Been Accepted', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $article->author_name ) ) . '</p>'
            . '<p>' . esc_html__( 'We are pleased to inform you that your article has been reviewed and accepted for publication in EIU Journal System.', 'eiu-rp' ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Article Title', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $article->title ?? '' ) . '</strong></td></tr>'
            . '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . $article_id . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Status', 'eiu-rp' ) . '</td><td><span class="status-badge status-approved">' . esc_html__( 'Accepted', 'eiu-rp' ) . '</span></td></tr>'
            . '</table>'
            . '<p>' . esc_html__( 'Our editorial team will be in touch shortly with information about the next steps in the publication process. Congratulations!', 'eiu-rp' ) . '</p>'
            . '<p>' . esc_html__( 'Thank you for contributing to EIU Research.', 'eiu-rp' ) . '</p>';

        return self::render( 'article_accepted', $default, array(
            'article_title'   => $article->title ?? '',
            'author_name'     => $article->author_name ?? '',
            'article_id'      => $article_id,
            'site_name'       => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Article rejected notification body.
     *
     * @param int    $article_id Article row ID.
     * @param object $article    Article object.
     * @return string
     */
    public static function article_rejected( int $article_id, object $article ): string {
        $default =
            '<h2>' . esc_html__( 'Update on Your Article Submission', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $article->author_name ) ) . '</p>'
            . '<p>' . esc_html__( 'Thank you for submitting your work to EIU Journal System. After careful consideration by our editorial review committee, we regret to inform you that your article will not be moving forward for publication at this time.', 'eiu-rp' ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Article Title', 'eiu-rp' ) . '</td><td>' . esc_html( $article->title ?? '' ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . $article_id . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Decision', 'eiu-rp' ) . '</td><td><span class="status-badge status-rejected">' . esc_html__( 'Not Accepted', 'eiu-rp' ) . '</span></td></tr>'
            . '</table>'
            . '<p>' . esc_html__( 'We encourage you to consider the feedback from our reviewers and explore other publication opportunities. We appreciate your interest in EIU Research.', 'eiu-rp' ) . '</p>'
            . '<p>' . esc_html__( 'If you have questions, please contact us at support@eiu.ac.', 'eiu-rp' ) . '</p>';

        return self::render( 'article_rejected', $default, array(
            'article_title'  => $article->title ?? '',
            'author_name'    => $article->author_name ?? '',
            'article_id'     => $article_id,
            'site_name'      => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Co-reviewer assigned notification body.
     *
     * Sent to each newly assigned co-reviewer.
     *
     * @param object $co_reviewer    Reviewer object (the co-reviewer being notified).
     * @param object $lead_reviewer  Reviewer object (the lead who made the assignment).
     * @param object $article        Article object.
     * @param string $login_url      URL to the reviewer dashboard.
     * @return string HTML email body.
     */
    public static function co_reviewer_assigned_body(
        object $co_reviewer,
        object $lead_reviewer,
        object $article,
        string $login_url
    ): string {
        $default =
            '<h2>' . esc_html__( 'Co-Reviewer Assignment', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $co_reviewer->full_name ) ) . '</p>'
            . '<p>' . sprintf(
                    esc_html__( '%s has assigned you as a Co-Reviewer for the following article. Your expertise and collaboration on this review is greatly valued.', 'eiu-rp' ),
                    '<strong>' . esc_html( $lead_reviewer->full_name ) . '</strong>'
              ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Article Title', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $article->title ?? '' ) . '</strong></td></tr>'
            . '<tr><td>' . esc_html__( 'Lead Reviewer', 'eiu-rp' ) . '</td><td>' . esc_html( $lead_reviewer->full_name ) . ' &lt;' . esc_html( $lead_reviewer->email ) . '&gt;</td></tr>'
            . '<tr><td>' . esc_html__( 'Your Role', 'eiu-rp' ) . '</td><td><span style="background:#eef4ff;color:#1a4988;border-radius:20px;padding:3px 12px;font-weight:700;font-size:13px;">' . esc_html__( 'Co-Reviewer', 'eiu-rp' ) . '</span></td></tr>'
            . '</table>'
            . '<p>' . esc_html__( 'Please log in to the Reviewer Dashboard to read the article, collaborate with the lead reviewer, and contribute to the review process.', 'eiu-rp' ) . '</p>'
            . '<a href="' . esc_url( $login_url ) . '" class="btn">' . esc_html__( 'Open Reviewer Dashboard', 'eiu-rp' ) . '</a>';

        return self::render( 'co_reviewer_assigned', $default, array(
            'article_title'      => $article->title ?? '',
            'co_reviewer_name'   => $co_reviewer->full_name,
            'lead_reviewer_name' => $lead_reviewer->full_name,
            'login_url'          => $login_url,
            'site_name'          => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Private notes shared with co-reviewers notification body.
     *
     * Sent to all co-reviewers when the lead reviewer saves/updates notes.
     *
     * @param object $co_reviewer   Reviewer object (recipient).
     * @param object $lead_reviewer Reviewer object (author of notes).
     * @param object $article       Article object.
     * @param string $notes         The private notes content (HTML).
     * @param string $login_url     URL to the reviewer dashboard.
     * @return string HTML email body.
     */
    public static function reviewer_notes_shared_body(
        object $co_reviewer,
        object $lead_reviewer,
        object $article,
        string $notes,
        string $login_url
    ): string {
        $default =
            '<h2>' . esc_html__( 'New Private Review Notes', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $co_reviewer->full_name ) ) . '</p>'
            . '<p>' . sprintf(
                    esc_html__( '%s has added private notes for the article you are co-reviewing.', 'eiu-rp' ),
                    '<strong>' . esc_html( $lead_reviewer->full_name ) . '</strong>'
              ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Article', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $article->title ?? '' ) . '</strong></td></tr>'
            . '</table>'
            . '<div style="background:#f8f9ff;border-left:4px solid #1a4988;padding:16px 20px;border-radius:0 6px 6px 0;margin:16px 0;">'
            . wp_kses_post( $notes )
            . '</div>'
            . '<a href="' . esc_url( $login_url ) . '" class="btn">' . esc_html__( 'Open Reviewer Dashboard', 'eiu-rp' ) . '</a>';

        $safe_notes = wp_strip_all_tags( $notes );
        return self::render( 'reviewer_notes_shared', $default, array(
            'article_title'      => $article->title ?? '',
            'co_reviewer_name'   => $co_reviewer->full_name,
            'lead_reviewer_name' => $lead_reviewer->full_name,
            'notes_content'      => $safe_notes,
            'login_url'          => $login_url,
            'site_name'          => get_bloginfo( 'name' ),
        ) );
    }

    // ── Application email bodies ──────────────────────────────────────────────

    /**
     * Application received — confirmation to applicant.
     *
     * @param int    $app_id Application row ID.
     * @param object $app    Application object.
     * @return string HTML email body.
     */
    public static function application_received_body( int $app_id, object $app ): string {
        $default =
            '<h2>' . esc_html__( 'Your Application Has Been Received', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $app->full_name ) ) . '</p>'
            . '<p>' . esc_html__( 'Thank you for applying to become a Researcher at EIU Journal System. We have received your application and our editorial team will review it shortly.', 'eiu-rp' ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . $app_id . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Name', 'eiu-rp' ) . '</td><td>' . esc_html( $app->full_name ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Email', 'eiu-rp' ) . '</td><td>' . esc_html( $app->email ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Status', 'eiu-rp' ) . '</td><td><span class="status-badge status-pending">' . esc_html__( 'Pending Review', 'eiu-rp' ) . '</span></td></tr>'
            . '</table>'
            . '<p>' . esc_html__( 'We will contact you once a decision has been made. If you have questions, please email support@eiu.ac.', 'eiu-rp' ) . '</p>';

        return self::render( 'application_received', $default, array(
            'full_name' => $app->full_name,
            'app_id'    => $app_id,
            'site_name' => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Application approved — credentials email to new researcher.
     *
     * @param object $app      Application object.
     * @param string $username Generated username.
     * @param string $password Generated password.
     * @param string $login_url Login page URL.
     * @return string HTML email body.
     */
    public static function application_approved_body( object $app, string $username, string $password, string $login_url ): string {
        $default =
            '<h2>' . esc_html__( 'Congratulations — Your Application Has Been Approved!', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $app->full_name ) ) . '</p>'
            . '<p>' . esc_html__( 'We are pleased to inform you that your application to become a Researcher at EIU Journal System has been approved. Your account has been created and you can now log in to submit your research articles.', 'eiu-rp' ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Username', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $username ) . '</strong></td></tr>'
            . '<tr><td>' . esc_html__( 'Temporary Password', 'eiu-rp' ) . '</td><td><code style="background:#f3f4f6;padding:2px 8px;border-radius:4px;font-size:15px;">' . esc_html( $password ) . '</code></td></tr>'
            . '</table>'
            . '<p style="background:#fef9c3;border:1.5px solid #fbbf24;border-radius:8px;padding:12px 16px;font-size:13px;color:#92400e;">' . esc_html__( 'For your security, please log in and change your password immediately after your first login.', 'eiu-rp' ) . '</p>'
            . '<a href="' . esc_url( $login_url ) . '" class="btn">' . esc_html__( 'Log In to Author Portal', 'eiu-rp' ) . '</a>'
            . '<p style="margin-top:16px;font-size:13px;color:#6b7280;">' . esc_html__( 'If the button does not work, copy this link: ', 'eiu-rp' ) . esc_url( $login_url ) . '</p>';

        return self::render( 'application_approved', $default, array(
            'full_name'  => $app->full_name,
            'username'   => $username,
            'password'   => $password,
            'login_url'  => $login_url,
            'site_name'  => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Application rejected — notification to applicant.
     *
     * @param object $app         Application object.
     * @param string $admin_notes Optional feedback from admin.
     * @return string HTML email body.
     */
    public static function application_rejected_body( object $app, string $admin_notes ): string {
        $default =
            '<h2>' . esc_html__( 'Update on Your Author Application', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $app->full_name ) ) . '</p>'
            . '<p>' . esc_html__( 'Thank you for your interest in EIU Journal System. After careful review of your application, we regret to inform you that we are unable to approve your registration at this time.', 'eiu-rp' ) . '</p>'
            . ( $admin_notes ? '<div style="background:#f8f9ff;border-left:4px solid #1a4988;padding:14px 18px;border-radius:0 6px 6px 0;margin:14px 0;">' . nl2br( esc_html( $admin_notes ) ) . '</div>' : '' )
            . '<p>' . esc_html__( 'We appreciate your interest and encourage you to re-apply in the future. If you have questions, please contact support@eiu.ac.', 'eiu-rp' ) . '</p>';

        return self::render( 'application_rejected', $default, array(
            'full_name'   => $app->full_name,
            'admin_notes' => $admin_notes,
            'site_name'   => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * Application more info required — notification to applicant.
     *
     * @param object $app         Application object.
     * @param string $admin_notes What additional info is needed.
     * @param string $apply_url   URL where they can re-apply or contact.
     * @return string HTML email body.
     */
    public static function application_more_info_body( object $app, string $admin_notes, string $apply_url ): string {
        $default =
            '<h2>' . esc_html__( 'More Information Required for Your Application', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $app->full_name ) ) . '</p>'
            . '<p>' . esc_html__( 'We are reviewing your application and need some additional information before we can make a final decision.', 'eiu-rp' ) . '</p>'
            . ( $admin_notes ? '<h3 style="color:#1a4988;font-size:15px;margin:16px 0 8px;">' . esc_html__( 'Required Information', 'eiu-rp' ) . '</h3><div style="background:#f8f9ff;border-left:4px solid #1a4988;padding:14px 18px;border-radius:0 6px 6px 0;">' . nl2br( esc_html( $admin_notes ) ) . '</div>' : '' )
            . '<p style="margin-top:16px;">' . esc_html__( 'Please reply to this email or contact the editorial team at support@eiu.ac with the requested information.', 'eiu-rp' ) . '</p>';

        return self::render( 'application_more_info', $default, array(
            'full_name'   => $app->full_name,
            'admin_notes' => $admin_notes,
            'apply_url'   => $apply_url,
            'site_name'   => get_bloginfo( 'name' ),
        ) );
    }

    /**
     * New application submitted — admin alert.
     *
     * @param int    $app_id Application row ID.
     * @param object $app    Application object.
     * @return string HTML email body.
     */
    public static function application_submitted_admin_body( int $app_id, object $app ): string {
        $admin_url = admin_url( 'admin.php?page=eiu-rp-applications&action=view&id=' . $app_id );

        $default =
            '<h2>' . esc_html__( 'New Author Application Received', 'eiu-rp' ) . '</h2>'
            . '<p>' . esc_html__( 'A new researcher application has been submitted and requires review.', 'eiu-rp' ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . $app_id . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Name', 'eiu-rp' ) . '</td><td>' . esc_html( $app->full_name ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Email', 'eiu-rp' ) . '</td><td>' . esc_html( $app->email ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Expertise', 'eiu-rp' ) . '</td><td>' . esc_html( $app->expertise ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Country', 'eiu-rp' ) . '</td><td>' . esc_html( $app->country ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Submitted', 'eiu-rp' ) . '</td><td>' . esc_html( $app->submitted_at ) . '</td></tr>'
            . '</table>'
            . '<a href="' . esc_url( $admin_url ) . '" class="btn">' . esc_html__( 'Review Application', 'eiu-rp' ) . '</a>';

        return self::render( 'application_submitted', $default, array(
            'full_name'  => $app->full_name,
            'email'      => $app->email,
            'app_id'     => $app_id,
            'admin_url'  => $admin_url,
            'site_name'  => get_bloginfo( 'name' ),
        ) );
    }

    // ── v2.0.1: Application reviewer-assigned notification ────────────────

    /**
     * Email to reviewer when they are assigned to evaluate an application.
     *
     * @param object $app          Application object.
     * @param object $reviewer     Reviewer object.
     * @param string $review_url   Link to view the application.
     * @return string HTML email body.
     */
    public static function application_reviewer_assigned_body( object $app, object $reviewer, string $review_url ): string {
        $default =
            '<h2 style="color:#1a4988;margin-bottom:8px;">' . esc_html__( 'Application Assigned for Your Review', 'eiu-rp' ) . '</h2>'
            . '<p>' . sprintf( esc_html__( 'Dear %s,', 'eiu-rp' ), esc_html( $reviewer->full_name ) ) . '</p>'
            . '<p>' . esc_html__( 'You have been assigned to review the following researcher application. Please log in to your dashboard to view the full application details and provide your evaluation.', 'eiu-rp' ) . '</p>'
            . '<table class="info-table">'
            . '<tr><td>' . esc_html__( 'Applicant Name', 'eiu-rp' ) . '</td><td><strong>' . esc_html( $app->full_name ) . '</strong></td></tr>'
            . '<tr><td>' . esc_html__( 'Email', 'eiu-rp' ) . '</td><td>' . esc_html( $app->email ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Expertise', 'eiu-rp' ) . '</td><td>' . esc_html( $app->expertise ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Country', 'eiu-rp' ) . '</td><td>' . esc_html( $app->country ) . '</td></tr>'
            . '<tr><td>' . esc_html__( 'Reference ID', 'eiu-rp' ) . '</td><td>#' . esc_html( (string) $app->id ) . '</td></tr>'
            . '</table>'
            . '<a href="' . esc_url( $review_url ) . '" class="btn">' . esc_html__( 'Review Application', 'eiu-rp' ) . '</a>'
            . '<p style="font-size:13px;color:#6b7280;margin-top:12px;">' . esc_html__( 'If the button does not work, copy this link: ', 'eiu-rp' ) . esc_url( $review_url ) . '</p>';

        return self::render( 'application_reviewer_assigned', $default, array(
            'full_name'     => $reviewer->full_name,
            'applicant_name'=> $app->full_name,
            'app_id'        => $app->id,
            'expertise'     => $app->expertise,
            'review_url'    => $review_url,
            'site_name'     => get_bloginfo( 'name' ),
        ) );
    }

}
