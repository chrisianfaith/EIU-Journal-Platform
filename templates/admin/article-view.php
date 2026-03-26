<?php
/**
 * Admin Article View + Edit Template (v1.2).
 * Tabbed: View (read-only) | Edit (all fields — Admin/Reviewer only)
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Article;
use EIU_RP\Models\Review;
use EIU_RP\Utils\Helpers;

if ( ! $article ) {
    echo '<div class="wrap"><p>' . esc_html__( 'Article not found.', 'eiu-rp' ) . '</p></div>';
    return;
}

$can_edit      = current_user_can( 'eiu_manage_articles' ) || current_user_can( 'eiu_review_articles' );
$subjects      = (array) get_option( 'eiu_rp_subjects', array() );
$terms         = get_the_terms( $article->post_id, 'eiu_subject' );
$cur_subj      = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
$abstract_meta = get_post_meta( (int) $article->post_id, '_eiu_abstract', true ) ?: $article->abstract;
$art_content   = get_post_meta( (int) $article->post_id, '_eiu_article_content', true );
$references_v  = get_post_meta( (int) $article->post_id, '_eiu_references', true ) ?: ( $article->references ?? '' );
$author_photo  = (int) get_post_meta( (int) $article->post_id, '_eiu_author_photo_id', true );
$coauth_photo  = (int) get_post_meta( (int) $article->post_id, '_eiu_coauthor_photo_id', true );

if ( $can_edit ) {
    wp_enqueue_editor();
    wp_enqueue_media();
}

$statuses = array(
    Article::STATUS_PENDING         => __( 'Pending', 'eiu-rp' ),
    Article::STATUS_UNDER_REVIEW    => __( 'Under Review', 'eiu-rp' ),
    Article::STATUS_APPROVED        => __( 'Approved', 'eiu-rp' ),
    Article::STATUS_REJECTED        => __( 'Rejected', 'eiu-rp' ),
    Article::STATUS_PUBLISHED       => __( 'Published', 'eiu-rp' ),
    Article::STATUS_REVISION        => __( 'Revision Required', 'eiu-rp' ),
);
?>
<style>
.eiu-edit-tabs{display:flex;gap:0;border-bottom:2px solid #e0e0e0;margin-bottom:20px;}
.eiu-edit-tab{padding:10px 22px;font-size:14px;font-weight:600;cursor:pointer;border:none;background:none;color:#555;border-bottom:3px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;}
.eiu-edit-tab.active{color:#1a4988;border-bottom-color:#1a4988;}
.eiu-edit-panel{display:none;}
.eiu-edit-panel.active{display:block;animation:eiu-panel-in .2s ease both;}
@keyframes eiu-panel-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
/* Cards */
.eiu-edit-card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:22px 26px;margin-bottom:18px;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.eiu-edit-card-title{
  font-size:12px;font-weight:800;color:#1a4988;text-transform:uppercase;
  letter-spacing:.06em;margin:0 0 16px;padding-bottom:10px;
  border-bottom:2px solid #eef4ff;
  display:flex;align-items:center;gap:8px;
}
.eiu-edit-card-title .dashicons{font-size:16px;width:16px;height:16px;color:#1a4988;opacity:.7;}
/* Field groups */
.eiu-fg{margin-bottom:16px;}
.eiu-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;letter-spacing:.02em;}
.eiu-fg input,.eiu-fg select,.eiu-fg textarea{
  width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:7px;
  font-size:14px;background:#f9fafb;color:#1f2937;
  transition:border-color .15s,box-shadow .15s;
}
.eiu-fg input:focus,.eiu-fg select:focus,.eiu-fg textarea:focus{
  border-color:#1a4988;background:#fff;outline:none;
  box-shadow:0 0 0 3px rgba(26,73,136,.1);
}
.eiu-fg textarea{resize:vertical;min-height:80px;line-height:1.6;}
.eiu-fg-hint{font-size:11px;color:#9ca3af;margin-top:4px;}
/* Grid */
.eiu-row2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.eiu-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
/* Photo */
.eiu-photo-wrap{display:flex;align-items:center;gap:14px;margin-bottom:14px;}
.eiu-photo-av{width:64px;height:64px;border-radius:50%;border:2px solid #e5e7eb;object-fit:cover;background:#e8eef8;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#1a4988;overflow:hidden;flex-shrink:0;}
.eiu-photo-av img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
/* Save bar */
.eiu-save-bar{
  position:sticky;bottom:0;background:#fff;
  border-top:2px solid #1a4988;padding:14px 24px;
  display:flex;align-items:center;gap:14px;z-index:10;
  border-radius:0 0 10px 10px;margin-top:4px;
  box-shadow:0 -2px 12px rgba(0,0,0,.06);
}
.eiu-save-ok{color:#166534;font-size:13px;font-weight:600;}
.eiu-save-err{color:#9a0805;font-size:13px;font-weight:600;}
/* Change indicator: red dot when unsaved */
.eiu-unsaved-dot{
  display:none;width:8px;height:8px;border-radius:50%;
  background:#ef4444;flex-shrink:0;
  animation:eiu-pulse 1.5s ease infinite;
}
.eiu-unsaved-dot.visible{display:inline-block;}
@keyframes eiu-pulse{0%,100%{opacity:1}50%{opacity:.4}}
@media(max-width:780px){.eiu-row2,.eiu-row3{grid-template-columns:1fr;}}
</style>

<div class="wrap eiu-rp-admin">
  <h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=eiu-rp-articles')); ?>" class="eiu-back-link">
      <span class="dashicons dashicons-arrow-left-alt2"></span>
    </a>
    <?php esc_html_e('Article','eiu-rp'); ?>: <em><?php echo esc_html( wp_trim_words($article->title,8) ); ?></em>
    <?php echo Helpers::status_badge($article->status); // phpcs:ignore ?>
  </h1>
  <hr class="wp-header-end">
  <div id="eiu-global-msg" style="display:none;" class="notice notice-success is-dismissible"><p></p></div>

  <!-- Tabs -->
  <div class="eiu-edit-tabs">
    <button class="eiu-edit-tab active" data-panel="pv">
      <span class="dashicons dashicons-visibility" style="vertical-align:middle;margin-right:4px;"></span><?php esc_html_e('View','eiu-rp'); ?>
    </button>
    <?php if ($can_edit): ?>
      <button class="eiu-edit-tab" data-panel="pe">
        <span class="dashicons dashicons-edit" style="vertical-align:middle;margin-right:4px;"></span><?php esc_html_e('Edit Article','eiu-rp'); ?>
      </button>
    <?php endif; ?>
  </div>

  <!-- ============ VIEW PANEL ============ -->
  <div class="eiu-edit-panel active" id="pv">
    <div class="eiu-rp-article-layout">
      <div class="eiu-rp-article-main">

        <div class="eiu-rp-card">
          <h2 class="eiu-rp-card-title"><?php esc_html_e('Article Information','eiu-rp'); ?></h2>
          <table class="eiu-detail-table">
            <tr><th>Ref#</th><td>#<?php echo esc_html($article->id); ?></td></tr>
            <tr><th><?php esc_html_e('Title','eiu-rp'); ?></th><td><strong><?php echo esc_html($article->title); ?></strong></td></tr>
            <tr><th><?php esc_html_e('Subject','eiu-rp'); ?></th><td><?php echo $cur_subj?esc_html($cur_subj):'&mdash;'; ?></td></tr>
            <tr><th><?php esc_html_e('Abstract','eiu-rp'); ?></th><td style="max-width:480px;"><?php echo wp_kses_post(nl2br($abstract_meta)); ?></td></tr>
            <tr><th>DOI</th><td><?php echo $article->doi?esc_html($article->doi):'&mdash;'; ?></td></tr>
            <tr><th>ISSN</th><td><?php echo $article->issn ? esc_html($article->issn) : '<em style="color:#9ca3af;">' . esc_html__('Not yet assigned — editable in Edit tab','eiu-rp') . '</em>'; ?></td></tr>
            <tr><th><?php esc_html_e('Keywords','eiu-rp'); ?></th><td><?php echo !empty($article->keywords)?esc_html($article->keywords):'&mdash;'; ?></td></tr>
            <tr><th><?php esc_html_e('Submitted','eiu-rp'); ?></th><td><?php echo esc_html($article->submitted_at); ?></td></tr>
          </table>
        </div>

        <div class="eiu-rp-card">
          <h2 class="eiu-rp-card-title"><?php esc_html_e('Authors','eiu-rp'); ?></h2>
          <div class="eiu-author-cols">
            <div>
              <h4><?php esc_html_e('Primary Author','eiu-rp'); ?></h4>
              <?php if ($author_photo): ?>
                <img src="<?php echo esc_url(wp_get_attachment_image_url($author_photo,'thumbnail')); ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:2px solid #e5e7eb;" alt="">
              <?php endif; ?>
              <table class="eiu-detail-table">
                <tr><th><?php esc_html_e('Name','eiu-rp'); ?></th><td><?php echo esc_html($article->author_name); ?></td></tr>
                <tr><th><?php esc_html_e('Email','eiu-rp'); ?></th><td><a href="mailto:<?php echo esc_attr($article->author_email); ?>"><?php echo esc_html($article->author_email); ?></a></td></tr>
                <tr><th><?php esc_html_e('Org','eiu-rp'); ?></th><td><?php echo esc_html($article->author_org); ?></td></tr>
                <?php if (!empty($article->author_affiliation)): ?>
                <tr><th><?php esc_html_e('Affiliation','eiu-rp'); ?></th><td><?php echo wp_kses_post($article->author_affiliation); ?></td></tr>
                <?php endif; ?>
              </table>
            </div>
            <?php if ($article->coauthor_name): ?>
              <div>
                <h4><?php esc_html_e('Co-Author','eiu-rp'); ?></h4>
                <?php if ($coauth_photo): ?>
                  <img src="<?php echo esc_url(wp_get_attachment_image_url($coauth_photo,'thumbnail')); ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:2px solid #e5e7eb;" alt="">
                <?php endif; ?>
                <table class="eiu-detail-table">
                  <tr><th><?php esc_html_e('Name','eiu-rp'); ?></th><td><?php echo esc_html($article->coauthor_name); ?></td></tr>
                  <tr><th><?php esc_html_e('Email','eiu-rp'); ?></th><td><?php echo esc_html($article->coauthor_email); ?></td></tr>
                  <tr><th><?php esc_html_e('Org','eiu-rp'); ?></th><td><?php echo esc_html($article->coauthor_org); ?></td></tr>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($references_v)): ?>
          <div class="eiu-rp-card">
            <h2 class="eiu-rp-card-title"><?php esc_html_e('References','eiu-rp'); ?></h2>
            <div style="font-size:14px;line-height:1.8;color:#374151;"><?php echo wp_kses_post(nl2br($references_v)); ?></div>
          </div>
        <?php endif; ?>

        <div class="eiu-rp-card">
          <h2 class="eiu-rp-card-title"><?php esc_html_e('Reviews','eiu-rp'); ?></h2>
          <?php if (empty($reviews)): ?>
            <p class="eiu-rp-empty"><?php esc_html_e('No reviewers assigned yet.','eiu-rp'); ?></p>
          <?php else: ?>
            <table class="wp-list-table widefat fixed eiu-rp-table">
              <thead><tr>
                <th><?php esc_html_e('Reviewer','eiu-rp'); ?></th><th><?php esc_html_e('Assigned','eiu-rp'); ?></th>
                <th><?php esc_html_e('Due','eiu-rp'); ?></th><th><?php esc_html_e('Status','eiu-rp'); ?></th>
                <th><?php esc_html_e('Recommendation','eiu-rp'); ?></th><th><?php esc_html_e('Actions','eiu-rp'); ?></th>
              </tr></thead>
              <tbody>
                <?php foreach ($reviews as $rev): ?>
                  <tr>
                    <td><?php echo esc_html($rev['reviewer_name']); ?><br><small><?php echo esc_html($rev['reviewer_email']); ?></small></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'),strtotime($rev['assigned_at']))); ?></td>
                    <td><?php echo $rev['due_date']?esc_html(date_i18n(get_option('date_format'),strtotime($rev['due_date']))):'&mdash;'; ?></td>
                    <td><span class="eiu-rp-badge status-<?php echo esc_attr($rev['status']); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$rev['status']))); ?></span></td>
                    <td><?php echo $rev['recommendation']?esc_html(Review::recommendation_label($rev['recommendation'])):'&mdash;'; ?></td>
                    <td>
                      <?php if ($rev['status']==='submitted'): ?>
                        <button class="button button-small eiu-btn-approve-review" data-id="<?php echo esc_attr($rev['id']); ?>"><?php esc_html_e('Approve','eiu-rp'); ?></button>
                        <button class="button button-small eiu-btn-reject-review"  data-id="<?php echo esc_attr($rev['id']); ?>"><?php esc_html_e('Reject','eiu-rp'); ?></button>
                      <?php endif; ?>
                      <button class="button button-small button-link-delete eiu-btn-delete-review" data-id="<?php echo esc_attr($rev['id']); ?>"><?php esc_html_e('Delete','eiu-rp'); ?></button>
                    </td>
                  </tr>
                  <?php if (!empty($rev['comments'])): ?>
                    <tr><td colspan="6"><div class="eiu-review-comments"><strong><?php esc_html_e('Comments:','eiu-rp'); ?></strong> <?php echo nl2br(esc_html($rev['comments'])); ?></div></td></tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div><!-- .main -->

      <!-- Sidebar -->
      <div class="eiu-rp-article-sidebar">
        <div class="eiu-rp-card">
          <h3 class="eiu-rp-card-title"><?php esc_html_e('Update Status','eiu-rp'); ?></h3>
          <select id="eiu-article-status-select" class="eiu-full-select">
            <?php foreach ($statuses as $v=>$l): ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected($article->status,$v); ?>><?php echo esc_html($l); ?></option>
            <?php endforeach; ?>
          </select>
          <!-- Publish date for backdating — shown when Published is selected -->
          <div id="eiu-publish-date-wrap" style="display:none;margin:10px 0 6px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">
              <span class="dashicons dashicons-calendar-alt" style="vertical-align:middle;margin-right:4px;"></span>
              <?php esc_html_e('Publish Date (optional)','eiu-rp'); ?>
            </label>
            <input type="date" id="eiu-publish-date" class="eiu-full-input"
              max="<?php echo esc_attr(date('Y-m-d')); ?>"
              placeholder="<?php esc_attr_e('Leave blank for today','eiu-rp'); ?>">
            <p style="font-size:11px;color:#9ca3af;margin:3px 0 0;"><?php esc_html_e('Choose a past date to backdate.','eiu-rp'); ?></p>
          </div>
          <button class="button button-primary eiu-full-btn" id="eiu-update-status-btn" data-article-id="<?php echo esc_attr($article->id); ?>">
            <?php esc_html_e('Save Status','eiu-rp'); ?>
          </button>
          <div id="eiu-status-msg" class="eiu-inline-msg"></div>
        </div>

        <?php if ( current_user_can( 'manage_options' ) ): ?>
        <!-- v2.0.1: Delete Article — Main Admin only -->
        <div class="eiu-rp-card" style="border:1px solid #fca5a5;">
          <h3 class="eiu-rp-card-title" style="color:#dc2626;">
            <span class="dashicons dashicons-warning" style="vertical-align:middle;margin-right:5px;"></span>
            <?php esc_html_e('Danger Zone','eiu-rp'); ?>
          </h3>
          <p style="font-size:12px;color:#6b7280;margin:0 0 10px;line-height:1.5;">
            <?php esc_html_e('Permanently delete this article including its uploaded file and all associated data. This action cannot be undone.','eiu-rp'); ?>
          </p>
          <button class="button eiu-full-btn" id="eiu-delete-article-btn"
            data-article-id="<?php echo esc_attr($article->id); ?>"
            data-article-title="<?php echo esc_attr($article->title ?? ''); ?>"
            data-nonce="<?php echo esc_attr(wp_create_nonce('eiu_rp_admin_delete_article')); ?>"
            style="background:#dc2626;color:#fff;border-color:#dc2626;font-weight:700;">
            <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-right:5px;"></span>
            <?php esc_html_e('Delete Article Permanently','eiu-rp'); ?>
          </button>
          <div id="eiu-delete-msg" class="eiu-inline-msg"></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($article->file_name)): ?>
          <div class="eiu-rp-card">
            <h3 class="eiu-rp-card-title"><?php esc_html_e('Submitted File','eiu-rp'); ?></h3>
            <div class="eiu-file-info">
              <span class="dashicons dashicons-media-document eiu-file-icon"></span>
              <div><p class="eiu-file-name"><?php echo esc_html($article->file_name); ?></p><p class="eiu-file-type"><?php echo esc_html(strtoupper($article->file_type)); ?></p></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="eiu-rp-card">
          <h3 class="eiu-rp-card-title"><?php esc_html_e('Assign Reviewer','eiu-rp'); ?></h3>
          <?php if (empty($reviewers_all['items'])): ?>
            <p class="eiu-rp-empty"><?php esc_html_e('No verified reviewers.','eiu-rp'); ?></p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=eiu-rp-reviewers')); ?>" class="button button-small"><?php esc_html_e('Manage','eiu-rp'); ?></a>
          <?php else: ?>
            <select id="eiu-assign-reviewer-select" class="eiu-full-select">
              <option value=""><?php esc_html_e('— Select Reviewer —','eiu-rp'); ?></option>
              <?php foreach ($reviewers_all['items'] as $rv): ?>
                <option value="<?php echo esc_attr($rv['id']); ?>"><?php echo esc_html($rv['full_name'].' ('.$rv['email'].')'); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="date" id="eiu-review-due-date" class="eiu-full-input" placeholder="<?php esc_attr_e('Due date (optional)','eiu-rp'); ?>">
            <button class="button button-primary eiu-full-btn" id="eiu-assign-reviewer-btn" data-article-id="<?php echo esc_attr($article->id); ?>">
              <?php esc_html_e('Assign','eiu-rp'); ?>
            </button>
            <div id="eiu-assign-msg" class="eiu-inline-msg"></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div><!-- #pv -->

  <?php if ($can_edit): ?>
  <!-- ============ EDIT PANEL ============ -->
  <div class="eiu-edit-panel" id="pe">

    <!-- 1. Publication details -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">1. <?php esc_html_e('Publication Details','eiu-rp'); ?></p>
      <div class="eiu-fg"><label><?php esc_html_e('Article Title','eiu-rp'); ?> <span style="color:#9a0805;">*</span></label><input type="text" name="title" value="<?php echo esc_attr($article->title); ?>"></div>
      <div class="eiu-row2">
        <div class="eiu-fg"><label><?php esc_html_e('DOI','eiu-rp'); ?></label><input type="text" name="doi" value="<?php echo esc_attr($article->doi); ?>" placeholder="10.xxxx/xxxxx"></div>
        <div class="eiu-fg">
          <label>
            <?php esc_html_e('ISSN','eiu-rp'); ?>
            <em style="font-size:11px;color:#6b7280;font-weight:400;"> — <?php esc_html_e('optional, editable by Admin/Reviewer','eiu-rp'); ?></em>
          </label>
          <input type="text" name="issn" value="<?php echo esc_attr($article->issn); ?>" placeholder="XXXX-XXXX">
        </div>
      </div>
      <div class="eiu-row2">
        <div class="eiu-fg">
          <label><?php esc_html_e('Subject','eiu-rp'); ?></label>
          <select name="subject">
            <option value=""><?php esc_html_e('— Select —','eiu-rp'); ?></option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?php echo esc_attr($s); ?>" <?php selected($cur_subj,$s); ?>><?php echo esc_html($s); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="eiu-fg"><label><?php esc_html_e('Keywords','eiu-rp'); ?></label><input type="text" name="keywords" value="<?php echo esc_attr($article->keywords ?? ''); ?>"></div>
      </div>
    </div>

    <!-- 2. Abstract -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">2. <?php esc_html_e('Abstract','eiu-rp'); ?></p>
      <?php wp_editor( wp_kses_post($abstract_meta), 'edit_abstract_wp', array(
        'textarea_name'=>'abstract','textarea_rows'=>8,'media_buttons'=>true,
        'tinymce'=>array('toolbar1'=>'bold,italic,underline,|,bullist,numlist,|,link,image,|,removeformat'),
      ) ); ?>
    </div>

    <!-- 3. Article content -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">3. <?php esc_html_e('Article Content (Full Body)','eiu-rp'); ?></p>
      <p style="font-size:12px;color:#6b7280;margin:0 0 10px;"><?php esc_html_e('Full article body shown after the abstract on the single page.','eiu-rp'); ?></p>
      <?php wp_editor( wp_kses_post($art_content), 'edit_content_wp', array(
        'textarea_name'=>'article_content','textarea_rows'=>14,'media_buttons'=>true,
      ) ); ?>
    </div>

    <!-- 4. References -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">4. <?php esc_html_e('References','eiu-rp'); ?></p>
      <?php wp_editor( wp_kses_post($references_v), 'edit_references_wp', array(
        'textarea_name'=>'references','textarea_rows'=>6,'media_buttons'=>false,'teeny'=>true,
      ) ); ?>
    </div>

    <!-- 5. Author details + photo -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">5. <?php esc_html_e('Author Details','eiu-rp'); ?></p>
      <div class="eiu-photo-wrap">
        <div class="eiu-photo-av" id="av-author">
          <?php if ($author_photo): ?>
            <img src="<?php echo esc_url(wp_get_attachment_image_url($author_photo,'thumbnail')); ?>" alt="">
          <?php else: ?><?php echo esc_html(strtoupper(substr($article->author_name,0,1))); ?><?php endif; ?>
        </div>
        <div>
          <input type="hidden" name="author_photo_id" id="author-photo-id" value="<?php echo esc_attr($author_photo); ?>">
          <input type="file" id="author-photo-file" accept="image/*" style="display:none;">
          <button type="button" class="button button-small" onclick="document.getElementById('author-photo-file').click();">
            <span class="dashicons dashicons-upload" style="vertical-align:middle;font-size:14px;"></span> <?php esc_html_e('Upload Photo','eiu-rp'); ?>
          </button>
          <p style="font-size:11px;color:#9ca3af;margin:4px 0 0;"><?php esc_html_e('JPG/PNG/WebP · circular avatar · max 2 MB','eiu-rp'); ?></p>
        </div>
      </div>
      <div class="eiu-row3">
        <div class="eiu-fg"><label><?php esc_html_e('Full Name','eiu-rp'); ?></label><input type="text" name="author_name" value="<?php echo esc_attr($article->author_name); ?>"></div>
        <div class="eiu-fg"><label><?php esc_html_e('Organization','eiu-rp'); ?></label><input type="text" name="author_org" value="<?php echo esc_attr($article->author_org); ?>"></div>
        <div class="eiu-fg"><label><?php esc_html_e('Email','eiu-rp'); ?></label><input type="email" name="author_email" value="<?php echo esc_attr($article->author_email); ?>"></div>
      </div>
      <!-- v2.2: Affiliation — rich text, editable by admin -->
      <div class="eiu-fg" style="margin-top:14px;">
        <label style="font-weight:600;font-size:12px;color:#374151;display:block;margin-bottom:6px;">
          <?php esc_html_e('Affiliation','eiu-rp'); ?>
          <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:6px;">(<?php esc_html_e('optional — supports bold, italic, links','eiu-rp'); ?>)</span>
        </label>
        <?php
        $affil_editor_id = 'admin_author_affil_' . absint( $article->id );
        wp_editor(
            wp_kses_post( $article->author_affiliation ?? '' ),
            $affil_editor_id,
            array(
                'textarea_name' => 'author_affiliation',
                'media_buttons' => false,
                'teeny'         => true,
                'quicktags'     => true,
                'editor_height' => 100,
                'tinymce'       => array(
                    'toolbar1' => 'bold,italic,underline,link,unlink',
                    'toolbar2' => '',
                ),
            )
        );
        ?>
      </div>
    </div>

    <!-- 6. Co-Author + photo -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">6. <?php esc_html_e('Co-Author Details','eiu-rp'); ?> <em style="font-size:11px;color:#9ca3af;text-transform:none;font-weight:400;">(<?php esc_html_e('optional','eiu-rp'); ?>)</em></p>
      <div class="eiu-photo-wrap">
        <div class="eiu-photo-av" id="av-coauthor">
          <?php if ($coauth_photo): ?>
            <img src="<?php echo esc_url(wp_get_attachment_image_url($coauth_photo,'thumbnail')); ?>" alt="">
          <?php else: ?><?php echo $article->coauthor_name ? esc_html(strtoupper(substr($article->coauthor_name,0,1))) : '?'; ?><?php endif; ?>
        </div>
        <div>
          <input type="hidden" name="coauthor_photo_id" id="coauthor-photo-id" value="<?php echo esc_attr($coauth_photo); ?>">
          <input type="file" id="coauthor-photo-file" accept="image/*" style="display:none;">
          <button type="button" class="button button-small" onclick="document.getElementById('coauthor-photo-file').click();">
            <span class="dashicons dashicons-upload" style="vertical-align:middle;font-size:14px;"></span> <?php esc_html_e('Upload Photo','eiu-rp'); ?>
          </button>
        </div>
      </div>
      <div class="eiu-row3">
        <div class="eiu-fg"><label><?php esc_html_e('Full Name','eiu-rp'); ?></label><input type="text" name="coauthor_name" value="<?php echo esc_attr($article->coauthor_name); ?>"></div>
        <div class="eiu-fg"><label><?php esc_html_e('Organization','eiu-rp'); ?></label><input type="text" name="coauthor_org" value="<?php echo esc_attr($article->coauthor_org); ?>"></div>
        <div class="eiu-fg"><label><?php esc_html_e('Email','eiu-rp'); ?></label><input type="email" name="coauthor_email" value="<?php echo esc_attr($article->coauthor_email); ?>"></div>
      </div>
    </div>

    <!-- 7. Additional -->
    <div class="eiu-edit-card">
      <p class="eiu-edit-card-title">7. <?php esc_html_e('Additional Fields','eiu-rp'); ?></p>
      <div class="eiu-row3">
        <div class="eiu-fg"><label><?php esc_html_e('Contact Number','eiu-rp'); ?></label><input type="tel" name="contact_number" value="<?php echo esc_attr($article->contact_number); ?>"></div>
        <div class="eiu-fg"><label><?php esc_html_e('Country','eiu-rp'); ?></label><input type="text" name="country" value="<?php echo esc_attr($article->country); ?>"></div>
        <div class="eiu-fg"><label><?php esc_html_e('Disclosures','eiu-rp'); ?></label><input type="text" name="disclosures" value="<?php echo esc_attr($article->disclosures ?? ''); ?>"></div>
      </div>
    </div>

    <!-- Save bar -->
    <div class="eiu-save-bar">
      <button type="button" id="eiu-save-btn" class="button button-primary button-large"
        data-article-id="<?php echo esc_attr($article->id); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('eiu_rp_article_edit')); ?>">
        <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:5px;"></span><?php esc_html_e('Save All Changes','eiu-rp'); ?>
      </button>
      <span id="edit-save-msg"></span>
      <span style="color:#9ca3af;font-size:12px;margin-left:auto;"><?php esc_html_e('Changes go live on the frontend immediately.','eiu-rp'); ?></span>
    </div>
  </div><!-- #pe -->
  <?php endif; ?>
</div>

<script>
(function($){
'use strict';

/* Tabs */
$('.eiu-edit-tab').on('click',function(){
  var p=$(this).data('panel');
  $('.eiu-edit-tab').removeClass('active');
  $(this).addClass('active');
  $('.eiu-edit-panel').removeClass('active');
  $('#'+p).addClass('active');
});

/* Author photo upload helper */
function uploadPhoto(fileInput, previewId, hiddenId){
  var file = fileInput.files[0];
  if(!file) return;
  if(file.size > 2*1024*1024){ alert('<?php echo esc_js(__('Photo must be under 2 MB.','eiu-rp')); ?>'); return; }
  var fd=new FormData();
  fd.append('action','eiu_rp_upload_author_photo');
  fd.append('nonce','<?php echo esc_js(wp_create_nonce('eiu_rp_article_edit')); ?>');
  fd.append('photo',file);
  $.ajax({url:ajaxurl,type:'POST',data:fd,processData:false,contentType:false,success:function(res){
    if(res.success){
      $('#'+previewId).html('<img src="'+res.data.url+'" alt="">');
      $('#'+hiddenId).val(res.data.attachment_id);
    }
  }});
}
$('#author-photo-file').on('change',function(){ uploadPhoto(this,'av-author','author-photo-id'); });
$('#coauthor-photo-file').on('change',function(){ uploadPhoto(this,'av-coauthor','coauthor-photo-id'); });

/* Save article */
$('#eiu-save-btn').on('click',function(){
  var $btn=$(this), nonce=$btn.data('nonce'), articleId=$btn.data('article-id');
  var msgEl=$('#edit-save-msg');
  if(typeof tinymce!=='undefined') tinymce.triggerSave();
  var fd=new FormData();
  fd.append('action','eiu_rp_save_article_edit');
  fd.append('nonce',nonce);
  fd.append('article_id',articleId);
  var fields=['title','doi','issn','subject','keywords','author_name','author_org','author_email',
    'coauthor_name','coauthor_org','coauthor_email','contact_number','country','disclosures',
    'author_photo_id','coauthor_photo_id'];
  fields.forEach(function(f){ fd.append(f,$('#pe [name="'+f+'"]').val()||''); });
  ['abstract','article_content','references'].forEach(function(f){
    fd.append(f,$('#pe textarea[name="'+f+'"]').val()||'');
  });
  // v2.2: affiliation is a wp_editor textarea — already synced by tinymce.triggerSave()
  fd.append('author_affiliation',$('#pe textarea[name="author_affiliation"]').val()||'');
  $btn.prop('disabled',true).text('<?php echo esc_js(__('Saving…','eiu-rp')); ?>');
  msgEl.text('').removeClass('eiu-save-ok eiu-save-err');
  $.ajax({url:ajaxurl,type:'POST',data:fd,processData:false,contentType:false,
    success:function(res){
      if(res.success){
        msgEl.text('<?php echo esc_js(__('Saved.','eiu-rp')); ?>').addClass('eiu-save-ok');
        $('#eiu-global-msg').show().find('p').text('<?php echo esc_js(__('Article updated — changes are live on the frontend.','eiu-rp')); ?>');
      } else {
        msgEl.text((res.data&&res.data.message)||'<?php echo esc_js(__('Error.','eiu-rp')); ?>').addClass('eiu-save-err');
      }
    },
    error:function(){ msgEl.text('<?php echo esc_js(__('Network error.','eiu-rp')); ?>').addClass('eiu-save-err'); },
    complete:function(){
      $btn.prop('disabled',false).html('<span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:5px;"></span><?php echo esc_js(__('Save All Changes','eiu-rp')); ?>');
      setTimeout(function(){ msgEl.text('').removeClass('eiu-save-ok eiu-save-err'); },4000);
    }
  });
});
})(jQuery);
</script>
