<?php
/**
 * Admin: Shortcodes Reference Page.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$shortcodes = array(
    array(
        'tag'   => '[eiu_submission_form]',
        'desc'  => __( 'Displays the multi-step article submission form. Access-controlled: only Researchers, Reviewers, and Admins can submit.', 'eiu-rp' ),
        'atts'  => array( 'redirect="URL"' => __( 'URL to redirect to after successful submission.', 'eiu-rp' ) ),
        'page'  => 'eiu_rp_submission_page_id',
    ),
    array(
        'tag'   => '[eiu_article_list]',
        'desc'  => __( 'Displays the public list of published research articles with search and filtering.', 'eiu-rp' ),
        'atts'  => array(
            'per_page="10"'   => __( 'Number of articles per page.', 'eiu-rp' ),
            'subject=""'      => __( 'Filter by subject slug.', 'eiu-rp' ),
            'status="published"' => __( 'Article status to display.', 'eiu-rp' ),
        ),
        'page'  => 'eiu_rp_listing_page_id',
    ),
    array(
        'tag'   => '[eiu_reviewer_dashboard]',
        'desc'  => __( 'Displays the reviewer portal with login form, assigned articles, review submission, and reviewer directory.', 'eiu-rp' ),
        'atts'  => array(),
        'page'  => 'eiu_rp_reviewer_access_page_id',
    ),
    array(
        'tag'   => '[eiu_researcher_dashboard]',
        'desc'  => __( 'Displays the researcher dashboard with submission management, status tracking, and profile settings.', 'eiu-rp' ),
        'atts'  => array(),
        'page'  => 'eiu_rp_researcher_dashboard_page_id',
    ),
    array(
        'tag'   => '[eiu_researcher_login]',
        'desc'  => __( 'Displays the researcher login and registration form. Recommended page slug: /researcher/', 'eiu-rp' ),
        'atts'  => array(),
        'page'  => 'eiu_rp_researcher_login_page_id',
    ),
    array(
        'tag'   => '[eiu_article_categories]',
        'desc'  => __( 'Displays available research subject categories as a visual filter widget.', 'eiu-rp' ),
        'atts'  => array(
            'style="pills"' => __( 'Display style: pills, cards, or list.', 'eiu-rp' ),
            'columns="3"'   => __( 'Number of columns (cards style only).', 'eiu-rp' ),
        ),
        'page'  => null,
    ),
);
?>
<div class="wrap eiu-rp-admin">
  <h1>
    <span class="dashicons dashicons-shortcode" style="color:#1a4988;margin-right:6px;"></span>
    <?php esc_html_e( 'Shortcodes Reference', 'eiu-rp' ); ?>
  </h1>
  <p style="color:#6b7280;font-size:14px;margin:4px 0 20px;"><?php esc_html_e( 'Copy any shortcode below and paste it into a page or post. Click the tag to copy it to your clipboard.', 'eiu-rp' ); ?></p>
  <hr class="wp-header-end">

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:20px;">
    <?php foreach ( $shortcodes as $sc ): ?>
      <div class="eiu-rp-card" style="margin:0;">
        <div style="padding:16px 20px 0;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <code
              id="sc-<?php echo esc_attr( sanitize_title($sc['tag']) ); ?>"
              onclick="eiu_copy_sc(this)"
              title="<?php esc_attr_e('Click to copy','eiu-rp'); ?>"
              style="background:#f0f6ff;color:#1a4988;border:1px solid #b8d0f0;border-radius:5px;padding:5px 12px;font-size:13px;cursor:pointer;user-select:all;display:inline-block;font-weight:700;">
              <?php echo esc_html( $sc['tag'] ); ?>
            </code>
            <?php if ( $sc['page'] && get_option( $sc['page'] ) ): ?>
              <a href="<?php echo esc_url( get_permalink( get_option( $sc['page'] ) ) ); ?>" target="_blank"
                style="font-size:12px;color:#1a4988;text-decoration:none;">
                <span class="dashicons dashicons-external" style="font-size:14px;vertical-align:middle;"></span>
                <?php esc_html_e( 'View Page', 'eiu-rp' ); ?>
              </a>
            <?php endif; ?>
          </div>
          <p style="font-size:13px;color:#374151;margin:0 0 12px;"><?php echo esc_html( $sc['desc'] ); ?></p>
        </div>
        <?php if ( ! empty( $sc['atts'] ) ): ?>
          <div style="padding:0 20px 16px;">
            <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin:0 0 8px;">
              <?php esc_html_e( 'Attributes', 'eiu-rp' ); ?>
            </p>
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
              <?php foreach ( $sc['atts'] as $att => $attdesc ): ?>
                <tr style="border-top:1px solid #f0f2f5;">
                  <td style="padding:5px 10px 5px 0;"><code style="background:#f8f9fa;padding:2px 6px;border-radius:3px;color:#374151;"><?php echo esc_html( $att ); ?></code></td>
                  <td style="padding:5px 0;color:#6b7280;"><?php echo esc_html( $attdesc ); ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php else: ?>
          <div style="height:16px;"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
function eiu_copy_sc(el){
  var text=el.textContent.trim();
  if(navigator.clipboard){navigator.clipboard.writeText(text);}
  else{var ta=document.createElement('textarea');ta.value=text;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);}
  var orig=el.style.background;
  el.style.background='#d1fae5';el.style.color='#065f46';el.style.borderColor='#6ee7b7';
  setTimeout(function(){el.style.background=orig;el.style.color='#1a4988';el.style.borderColor='#b8d0f0';},1200);
}
</script>
