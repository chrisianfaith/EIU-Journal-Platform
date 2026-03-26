<?php
/**
 * Admin: Email Template Editor.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Admin\Email_Template_Editor;

$types   = Email_Template_Editor::get_template_types();
$current = isset( $_GET['tpl'] ) ? sanitize_key( $_GET['tpl'] ) : array_key_first( $types );
if ( ! isset( $types[ $current ] ) ) { $current = array_key_first( $types ); }
?>
<div class="wrap eiu-rp-admin">
  <h1>
    <span class="dashicons dashicons-email-alt" style="color:#1a4988;margin-right:6px;"></span>
    <?php esc_html_e( 'Email Template Editor', 'eiu-rp' ); ?>
  </h1>
  <p style="color:#6b7280;font-size:14px;margin:4px 0 20px;"><?php esc_html_e( 'Customise the subject lines and HTML body of each notification email. Leave blank to use the default template.', 'eiu-rp' ); ?></p>
  <hr class="wp-header-end">

  <?php if ( isset( $_GET['tpl-saved'] ) ): ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template saved.', 'eiu-rp' ); ?></p></div>
  <?php endif; ?>

  <div style="display:flex;gap:24px;align-items:flex-start;">

    <!-- Sidebar: template list -->
    <div style="min-width:220px;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
      <div style="padding:12px 16px;background:#1a4988;color:#fff;font-weight:700;font-size:13px;">
        <?php esc_html_e( 'Email Types', 'eiu-rp' ); ?>
      </div>
      <?php foreach ( $types as $key => $label ):
        $active = $key === $current;
        $url    = add_query_arg( array( 'page' => 'eiu-rp-email-templates', 'tpl' => $key ), admin_url( 'admin.php' ) );
      ?>
        <a href="<?php echo esc_url( $url ); ?>"
          style="display:block;padding:10px 16px;font-size:13px;border-bottom:1px solid #f0f2f5;text-decoration:none;
            <?php echo $active ? 'background:#e8eef8;color:#1a4988;font-weight:700;' : 'color:#374151;'; ?>">
          <?php echo esc_html( $label ); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Main: editor -->
    <div style="flex:1;min-width:0;">
      <div class="eiu-rp-card">
        <h2 class="eiu-rp-card-title"><?php echo esc_html( $types[ $current ] ); ?></h2>
        <p style="font-size:12px;color:#6b7280;margin:0 0 16px;"><?php echo esc_html( Email_Template_Editor::get_default_hint( $current ) ); ?></p>

        <form method="post" action="">
          <?php wp_nonce_field( 'eiu_rp_email_tpl' ); ?>
          <input type="hidden" name="eiu_rp_email_tpl_save" value="1">

          <table class="form-table">
            <tr>
              <th><label for="subj-<?php echo esc_attr($current); ?>"><?php esc_html_e( 'Subject Line', 'eiu-rp' ); ?></label></th>
              <td>
                <input type="text" id="subj-<?php echo esc_attr($current); ?>"
                  name="subject_<?php echo esc_attr($current); ?>"
                  class="large-text"
                  value="<?php echo esc_attr( Email_Template_Editor::get_custom_subject( $current ) ); ?>"
                  placeholder="<?php esc_attr_e( 'Leave blank to use default', 'eiu-rp' ); ?>">
              </td>
            </tr>
            <tr>
              <th style="vertical-align:top;padding-top:12px;"><label><?php esc_html_e( 'Email Body (HTML)', 'eiu-rp' ); ?></label></th>
              <td>
                <?php
                $body_content = Email_Template_Editor::get_custom_body( $current );
                // Show default template preview if no custom body has been saved.
                $default_preview = array(
                    'article_received'  => '<p>Dear {author_name},</p><p>Thank you for submitting your article <strong>{article_title}</strong> (Reference: #{article_id}) to {site_name}. We have received it on {submission_date} and our editorial team will be in touch soon.</p>',
                    'status_changed'    => '<p>Dear {author_name},</p><p>The status of your article <strong>{article_title}</strong> has been updated to: <strong>{status}</strong>.</p><p>You will receive further communications as the review process progresses.</p>',
                    'article_accepted'  => '<p>Dear {author_name},</p><p>Congratulations! We are pleased to inform you that your article <strong>{article_title}</strong> has been accepted for publication in {site_name}.</p><p>Our editorial team will contact you shortly with information on the next steps.</p>',
                    'revision_required' => '<p>Dear {author_name},</p><p>Your article <strong>{article_title}</strong> has been reviewed and requires revisions. Please read the feedback carefully and resubmit via: <a href="{login_url}">Resubmit Article</a></p><p>Reviewer notes:<br>{revision_notes}</p>',
                    'article_rejected'  => '<p>Dear {author_name},</p><p>Thank you for submitting <strong>{article_title}</strong> to {site_name}. After careful consideration, we regret to inform you that your article will not be moving forward for publication at this time. We appreciate your interest and encourage you to consider revising and resubmitting in the future.</p>',
                    'reviewer_otp'      => '<p>Dear {reviewer_name},</p><p>Your one-time login code for {site_name} is:</p><p style="font-size:32px;font-weight:bold;letter-spacing:0.2em;">{otp_code}</p><p>This code is valid for 5 minutes and can only be used once. If you did not request this, please ignore this email.</p>',
                    'reviewer_assigned' => '<p>Dear {reviewer_name},</p><p>You have been assigned to review the article: <strong>{article_title}</strong>.</p><p>Please log in to the Reviewer Dashboard to access the full submission and submit your review: <a href="{login_url}">Open Reviewer Dashboard</a></p>',
                    'reviewer_notice'   => '<p>Dear {reviewer_name},</p><p>A new article titled <strong>{article_title}</strong> by {author_name} has been submitted to {site_name} and is available for review consideration.</p><p>If you are assigned to this article you will receive a separate notification. <a href="{login_url}">Open Reviewer Dashboard</a></p>',
                    'article_submitted' => '<p>A new article has been submitted to {site_name} and requires editorial attention.</p><p><strong>Title:</strong> {article_title}<br><strong>Author:</strong> {author_name} ({author_email})<br><strong>Subject:</strong> {subject}<br><strong>Reference:</strong> #{article_id}</p><p><a href="{admin_url}">View Submission in Admin</a></p>',
                    'review_submitted'        => '<p>A reviewer has submitted their review for article <strong>{article_title}</strong>.</p><p><strong>Reviewer:</strong> {reviewer_name}<br><strong>Recommendation:</strong> {recommendation}</p><p><a href="{admin_url}">View Review in Admin</a></p>',
                    'co_reviewer_assigned'    => '<p>Dear {co_reviewer_name},</p><p>You have been assigned as a <strong>Co-Reviewer</strong> for the following article by {lead_reviewer_name}.</p><p><strong>Article:</strong> {article_title}</p><p>Please log in to the Reviewer Dashboard to collaborate on this review: <a href="{login_url}">Open Reviewer Dashboard</a></p><p>Your review contribution is greatly appreciated.</p>',
                    'reviewer_notes_shared'   => '<p>Dear {co_reviewer_name},</p><p>New private review notes have been added for the article: <strong>{article_title}</strong>.</p><p><strong>Notes:</strong></p><blockquote style="border-left:4px solid #1a4988;padding:12px 16px;background:#f8f9ff;margin:12px 0;">{notes_content}</blockquote><p>Log in to view and collaborate: <a href="{login_url}">Open Reviewer Dashboard</a></p>',
                    'application_received'    => '<p>Dear {full_name},</p><p>Thank you for applying to become a Researcher at EIU Journal System. We have received your application (Reference: #{app_id}) and our editorial team will review it shortly.</p><p>We will contact you once a decision has been made.</p>',
                    'application_approved'    => '<p>Dear {full_name},</p><p>Congratulations! Your application has been approved. Your researcher account has been created.</p><p><strong>Username:</strong> {username}<br><strong>Temporary Password:</strong> {password}</p><p><a href="{login_url}">Log In Now</a></p><p>Please change your password after your first login.</p>',
                    'application_rejected'    => '<p>Dear {full_name},</p><p>Thank you for your interest in EIU Journal System. After careful review, we regret to inform you that we are unable to approve your application at this time.</p><p>We appreciate your interest and encourage you to re-apply in the future.</p>',
                    'application_more_info'   => '<p>Dear {full_name},</p><p>We are reviewing your application and need some additional information before we can make a final decision.</p><p>{admin_notes}</p><p>Please reply to this email with the requested information.</p>',
                    'application_submitted'   => '<p>A new researcher application has been submitted and requires your review.</p><p><strong>Name:</strong> {full_name}<br><strong>Email:</strong> {email}<br><strong>Reference:</strong> #{app_id}</p><p><a href="{admin_url}">Review Application</a></p>',
                );
                if ( ! $body_content && isset( $default_preview[ $current ] ) ) {
                    $body_content = $default_preview[ $current ];
                }
                wp_editor( $body_content, "body_{$current}", array(
                    'textarea_name' => "body_{$current}",
                    'media_buttons' => false,
                    'textarea_rows' => 18,
                    'teeny'         => false,
                    'tinymce'       => array( 'toolbar1' => 'formatselect bold italic underline | bullist numlist | link unlink | code' ),
                ) ); ?>
                <p class="description" style="margin-top:8px;"><?php esc_html_e( 'Leave blank to use the built-in default template. HTML is allowed.', 'eiu-rp' ); ?></p>
              </td>
            </tr>
          </table>

          <p class="submit">
            <button type="submit" class="button button-primary button-large">
              <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
              <?php esc_html_e( 'Save Template', 'eiu-rp' ); ?>
            </button>
            <?php if ( Email_Template_Editor::get_custom_body( $current ) || Email_Template_Editor::get_custom_subject( $current ) ): ?>
              <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'eiu-rp-email-templates', 'tpl' => $current, 'reset' => '1' ), admin_url( 'admin.php' ) ), 'eiu_reset_tpl_' . $current ) ); ?>"
                class="button button-secondary" style="margin-left:10px;">
                <?php esc_html_e( 'Reset to Default', 'eiu-rp' ); ?>
              </a>
            <?php endif; ?>
          </p>
        </form>
      </div>
    </div>
  </div>
</div>
