<?php
/**
 * Frontend Article Listing — #990000 + #1a4988 brand.
 * Layout: left thumbnail | right = title, abstract, author badge, published, ISSN, Read More.
 * No category filter shown (use [eiu_article_categories] shortcode separately).
 * Shortcode: [eiu_article_list per_page="10" status="published"]
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

use EIU_RP\Models\Article;

$per_page_n   = absint( $per_page );
$current_page = max( 1, absint( isset( $_GET['eiu_page'] ) ? $_GET['eiu_page'] : 1 ) );
$args = array( 'per_page' => $per_page_n, 'page' => $current_page, 'status' => Article::STATUS_PUBLISHED );
if ( ! empty( $subject ) ) $args['subject'] = sanitize_text_field( $subject );

$result      = Article::query( $args );
$items       = $result['items'];
$total       = $result['total'];
$total_pages = (int) ceil( $total / $per_page_n );
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.eiu-al-wrap {
  --brand-red:  #990000;
  --brand-blue: #1a4988;
  --brand-blue-l: #e8eef8;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

/* Section header pill */
.eiu-al-header-pill {
  display: inline-flex; align-items: center; gap: 10px;
  background: var(--brand-blue); color: #fff;
  padding: 10px 28px; border-radius: 8px; font-size: 17px; font-weight: 700; letter-spacing: .2px;
  margin-bottom: 28px;
}

/* Article card */
.eiu-al-card {
  background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
  overflow: hidden; transition: box-shadow .2s, transform .15s;
  animation: eiu-fadein .35s ease both;
}
.eiu-al-card:hover { box-shadow: 0 8px 28px rgba(26,73,136,.12); transform: translateY(-2px); }
@keyframes eiu-fadein { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }

/* Left thumbnail — 150×150 standardized */
.eiu-al-thumb-col { width: 150px; min-width: 150px; flex-shrink: 0; }
.eiu-al-thumb {
  width: 150px; height: 150px; object-fit: cover; display: block;
  border-right: 1px solid #e5e7eb;
}
.eiu-al-thumb-placeholder {
  width: 150px; height: 150px; background: var(--brand-blue-l);
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 8px; border-right: 1px solid #e5e7eb;
}
.eiu-al-thumb-placeholder i { font-size: 2rem; color: var(--brand-blue); opacity: .4; }
.eiu-al-thumb-placeholder span { font-size: 11px; color: var(--brand-blue); font-weight: 600; letter-spacing: .5px; text-transform: uppercase; opacity: .5; }

/* Right content */
.eiu-al-body { padding: 22px 24px; flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 10px; }

/* Title */
.eiu-al-title { font-size: 17px; font-weight: 700; color: #111827; line-height: 1.4; margin: 0; font-family: 'Georgia', serif; }
.eiu-al-title a { color: inherit; text-decoration: none; transition: color .15s; }
.eiu-al-title a:hover { color: var(--brand-blue); }

/* Abstract */
.eiu-al-abstract { font-size: 14px; color: #4b5563; line-height: 1.7; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

/* Meta row */
.eiu-al-meta { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }

/* Author badge */
.eiu-al-author-badge {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--brand-blue); color: #fff;
  padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;
}
.eiu-al-author-badge .avatar {
  width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,.25);
  display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 800;
  flex-shrink: 0;
}

/* Published, ISSN */
.eiu-al-pub { font-size: 12px; color: #6b7280; display: flex; align-items: center; gap: 5px; }
.eiu-al-issn {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 600; color: var(--brand-red);
  background: #fff0f0; padding: 3px 10px; border-radius: 4px;
  border: 1px solid rgba(153,0,0,.15);
}

/* Read More button */
.eiu-al-footer { margin-top: auto; padding-top: 10px; border-top: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.eiu-al-read-more {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--brand-blue); color: #fff; padding: 9px 22px;
  border-radius: 7px; font-size: 14px; font-weight: 700; text-decoration: none;
  transition: background .15s;
}
.eiu-al-read-more:hover { background: #123266; color: #fff; text-decoration: none; }
.eiu-al-read-more:hover i { transform: translateX(3px); }
.eiu-al-read-more i { transition: transform .15s; }

/* Search */
.eiu-al-search-wrap { position: relative; }
.eiu-al-search-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; z-index: 5; }
.eiu-al-search { padding-left: 38px !important; border-radius: 8px; border-color: #e5e7eb; }
.eiu-al-search:focus { border-color: var(--brand-blue); box-shadow: 0 0 0 .2rem rgba(26,73,136,.15); outline: none; }

/* Pagination */
.eiu-al-page-btn { border-radius: 7px; border-color: #e5e7eb; font-weight: 600; }
.eiu-al-page-btn:hover { background: var(--brand-blue); color: #fff; border-color: var(--brand-blue); }
.eiu-al-page-num { width: 38px; height: 38px; border-radius: 7px; border-color: #e5e7eb; font-weight: 600; display: flex; align-items: center; justify-content: center; }
.eiu-al-page-num.active, .eiu-al-page-num:hover { background: var(--brand-blue) !important; color: #fff !important; border-color: var(--brand-blue) !important; }

@media (max-width: 640px) {
  .eiu-al-card { flex-direction: column !important; }
  .eiu-al-thumb-col { width: 100%; min-width: 0; }
  .eiu-al-thumb { width: 100%; height: 150px; border-right: none; border-bottom: 1px solid #e5e7eb; }
  .eiu-al-thumb-placeholder { width: 100%; height: 120px; border-right: none; border-bottom: 1px solid #e5e7eb; }
}
</style>

<div class="eiu-al-wrap">

  <?php if ( ! isset( $show_header ) || $show_header !== 'false' ): ?>
  <div class="eiu-al-header-pill">
    <i class="bi bi-journals"></i>
    <?php esc_html_e( 'Articles', 'eiu-rp' ); ?>
    <?php if ( $total > 0 ): ?>
      <span style="background:rgba(255,255,255,.18);padding:2px 10px;border-radius:20px;font-size:13px;">
        <?php echo esc_html( number_format_i18n( $total ) ); ?>
      </span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Search -->
  <?php if ( ! isset( $show_search ) || $show_search !== 'false' ): ?>
  <div class="mb-4">
    <div class="eiu-al-search-wrap" style="max-width:440px;">
      <i class="bi bi-search"></i>
      <input type="text" id="eiu-al-search" class="form-control eiu-al-search"
        placeholder="<?php esc_attr_e( 'Search articles, authors…', 'eiu-rp' ); ?>">
    </div>
  </div>
  <?php endif; ?>

  <!-- Articles -->
  <?php if ( empty( $items ) ): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-journal-x" style="font-size:3rem;opacity:.3;display:block;margin-bottom:12px;"></i>
      <p class="mb-3"><?php esc_html_e( 'No published articles yet.', 'eiu-rp' ); ?></p>
      <?php if ( get_option( 'eiu_rp_submission_page_id' ) ): ?>
        <a href="<?php echo esc_url( get_permalink( get_option( 'eiu_rp_submission_page_id' ) ) ); ?>"
          class="btn fw-bold" style="background:var(--brand-blue);color:#fff;border-radius:8px;">
          <i class="bi bi-upload me-1"></i><?php esc_html_e( 'Submit Your Manuscript', 'eiu-rp' ); ?>
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div class="d-flex flex-column gap-3" id="eiu-al-grid">
      <?php foreach ( $items as $idx => $row ):
        $terms        = ! empty( $row['post_id'] ) ? get_the_terms( (int) $row['post_id'], 'eiu_subject' ) : array();
        $subject_name = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
        $article_url  = ! empty( $row['post_id'] ) ? get_permalink( (int) $row['post_id'] ) : '#';
        $thumb_url    = ! empty( $row['post_id'] ) ? get_the_post_thumbnail_url( (int) $row['post_id'], 'eiu-thumb-150' ) : '';
        // Use published_at if set; fall back to post_date, then submitted_at.
        $_pl_ts = ! empty( $row['published_at'] ) && $row['published_at'] !== '0000-00-00 00:00:00'
                    ? strtotime( $row['published_at'] )
                    : ( ! empty( $row['post_id'] ) ? strtotime( get_post_field( 'post_date', (int) $row['post_id'] ) ) : 0 );
        if ( ! $_pl_ts || $_pl_ts <= 0 ) { $_pl_ts = strtotime( $row['submitted_at'] ); }
        $pub_date     = date_i18n( 'd/m/Y', $_pl_ts );
        $abstract     = wp_trim_words( wp_strip_all_tags( $row['abstract'] ?? '' ), 35, '…' );
        $author_init  = strtoupper( substr( $row['author_name'] ?? 'A', 0, 1 ) );
        $has_coauthor = ! empty( $row['coauthor_name'] );
      ?>
        <div class="eiu-al-card d-flex" data-title="<?php echo esc_attr( strtolower( $row['title'] ?? '' ) ); ?>"
             data-author="<?php echo esc_attr( strtolower( $row['author_name'] ?? '' ) ); ?>"
             style="animation-delay: <?php echo $idx * 0.05; ?>s">

          <!-- Left: Thumbnail — only shown when image exists -->
          <?php if ( $thumb_url ): ?>
          <div class="eiu-al-thumb-col">
            <img src="<?php echo esc_url( $thumb_url ); ?>"
                 alt="<?php echo esc_attr( $row['title'] ); ?>"
                 class="eiu-al-thumb">
          </div>
          <?php endif; ?>

          <!-- Right: Content -->
          <div class="eiu-al-body">

            <!-- Title -->
            <h2 class="eiu-al-title">
              <a href="<?php echo esc_url( $article_url ); ?>">
                <?php echo esc_html( $row['title'] ); ?>
              </a>
            </h2>

            <!-- Abstract -->
            <?php if ( $abstract ): ?>
              <p class="eiu-al-abstract"><?php echo esc_html( $abstract ); ?></p>
            <?php endif; ?>

            <!-- Meta -->
            <div class="eiu-al-meta">

              <!-- Author badge -->
              <span class="eiu-al-author-badge">
                <span class="avatar"><?php echo esc_html( $author_init ); ?></span>
                <?php echo esc_html( $row['author_name'] ); ?>
                <?php if ( $has_coauthor ): ?>
                  <span style="opacity:.7;">+1</span>
                <?php endif; ?>
              </span>

              <!-- Published -->
              <span class="eiu-al-pub">
                <i class="bi bi-calendar3"></i>
                <?php esc_html_e( 'Published:', 'eiu-rp' ); ?>
                <strong><?php echo esc_html( $pub_date ); ?></strong>
              </span>

              <!-- ISSN -->
              <?php if ( ! empty( $row['issn'] ) ): ?>
                <span class="eiu-al-issn">
                  <i class="bi bi-upc-scan"></i>
                  ISSN: <?php echo esc_html( $row['issn'] ); ?>
                </span>
              <?php endif; ?>

            </div>

            <!-- Footer: Read More -->
            <div class="eiu-al-footer">
              <?php if ( $subject_name ): ?>
                <span style="font-size:12px;color:#6b7280;">
                  <i class="bi bi-folder2 me-1"></i><?php echo esc_html( $subject_name ); ?>
                </span>
              <?php else: ?>
                <span></span>
              <?php endif; ?>
              <a href="<?php echo esc_url( $article_url ); ?>" class="eiu-al-read-more">
                <?php esc_html_e( 'Read More', 'eiu-rp' ); ?>
                <i class="bi bi-arrow-right"></i>
              </a>
            </div>

          </div><!-- .eiu-al-body -->
        </div><!-- .eiu-al-card -->
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ): ?>
      <div class="d-flex align-items-center justify-content-center gap-2 mt-4 flex-wrap">
        <?php if ( $current_page > 1 ): ?>
          <a href="<?php echo esc_url( add_query_arg( 'eiu_page', $current_page - 1 ) ); ?>"
            class="btn eiu-al-page-btn d-flex align-items-center gap-1">
            <i class="bi bi-chevron-left"></i><?php esc_html_e( 'Previous', 'eiu-rp' ); ?>
          </a>
        <?php endif; ?>
        <div class="d-flex gap-1">
          <?php for ( $p = max(1,$current_page-2); $p <= min($total_pages,$current_page+2); $p++ ): ?>
            <a href="<?php echo esc_url( add_query_arg( 'eiu_page', $p ) ); ?>"
               class="btn eiu-al-page-num <?php echo $p===$current_page?'active':''; ?>">
              <?php echo $p; ?>
            </a>
          <?php endfor; ?>
        </div>
        <?php if ( $current_page < $total_pages ): ?>
          <a href="<?php echo esc_url( add_query_arg( 'eiu_page', $current_page + 1 ) ); ?>"
            class="btn eiu-al-page-btn d-flex align-items-center gap-1">
            <?php esc_html_e( 'Next', 'eiu-rp' ); ?><i class="bi bi-chevron-right"></i>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<script>
(function(){
  var search = document.getElementById('eiu-al-search');
  var cards  = document.querySelectorAll('.eiu-al-card');
  if(!search) return;
  search.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    cards.forEach(function(c){
      var t = (c.getAttribute('data-title')||'') + (c.getAttribute('data-author')||'');
      c.style.display = !q || t.indexOf(q)>-1 ? '' : 'none';
    });
  });
}());
</script>
