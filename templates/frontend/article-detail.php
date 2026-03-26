<?php
/**
 * Frontend Full Article View (v1.2).
 *
 * Sections:
 *  1. Header card — thumbnail | title, flat author chips (with photos), published, ISSN/DOI
 *  2. Decorative divider
 *  3. Abstract
 *  4. Article Content (if set by admin)
 *  5. References (clearly visible, formatted)
 *  6. PDF Preview (first 2 pages, embedded) + email-gate Download modal
 *  7. Comments
 *
 * Brand: #9a0805 (red) / #003399 (blue) — flat, no heavy gradients.
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$post_id   = get_the_ID();
$article   = \EIU_RP\Models\Article::get_by_post( $post_id );
if ( ! $article ) { return; }

// -- Meta values --
$terms         = get_the_terms( $post_id, 'eiu_subject' );
$subject_name  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
$thumb_url     = get_the_post_thumbnail_url( $post_id, 'large' );
// Published date: use reviewer-set published_at, then WP post_date, then submitted_at.
$_pub_ts   = ! empty( $article->published_at ) && $article->published_at !== '0000-00-00 00:00:00'
               ? strtotime( $article->published_at )
               : ( $post_id ? strtotime( get_post_field( 'post_date', $post_id ) ) : 0 );
if ( ! $_pub_ts || $_pub_ts <= 0 ) {
    $_pub_ts = strtotime( $article->submitted_at );
}
$pub_date = date_i18n( 'd/m/Y', $_pub_ts );
$has_coauthor  = ! empty( $article->coauthor_name );
$listing_url   = get_option( 'eiu_rp_listing_page_id' ) ? get_permalink( get_option( 'eiu_rp_listing_page_id' ) ) : home_url();
$dl_nonce      = wp_create_nonce( 'eiu_rp_download_' . $post_id );
$has_file      = ! empty( $article->file_path ) && file_exists( $article->file_path );

// Abstract: prefer post meta (set by admin edit), fall back to DB field
$abstract_content = get_post_meta( $post_id, '_eiu_abstract', true );
if ( empty( $abstract_content ) ) { $abstract_content = $article->abstract ?? ''; }

// Full article body (set by admin)
$art_content   = get_post_meta( $post_id, '_eiu_article_content', true );

// References
$references    = get_post_meta( $post_id, '_eiu_references', true ) ?: ( $article->references ?? '' );

// Author photos
$author_photo_id  = (int) get_post_meta( $post_id, '_eiu_author_photo_id', true );
$coauth_photo_id  = (int) get_post_meta( $post_id, '_eiu_coauthor_photo_id', true );
$author_photo_url = $author_photo_id ? wp_get_attachment_image_url( $author_photo_id, 'eiu-thumb-150' ) : '';
$coauth_photo_url = $coauth_photo_id ? wp_get_attachment_image_url( $coauth_photo_id, 'eiu-thumb-150' ) : '';
$author_init      = strtoupper( substr( $article->author_name, 0, 1 ) );
$coauth_init      = $has_coauthor ? strtoupper( substr( $article->coauthor_name, 0, 1 ) ) : '';

// PDF URL for preview — always refresh transient so it works after cache flush.
$pdf_preview_url = '';
if ( $has_file && strtolower( $article->file_type ) === 'pdf' ) {
    $preview_token = get_post_meta( $post_id, '_eiu_pdf_preview_token', true );
    $preview_expiry= (int) get_post_meta( $post_id, '_eiu_pdf_preview_expiry', true );
    $transient_ok  = $preview_token && get_transient( 'eiu_pdf_preview_' . $preview_token );

    // Regenerate if: token missing, expired, OR transient gone (e.g. cache flush, server restart).
    if ( ! $transient_ok || $preview_expiry < time() ) {
        // Only generate a fresh token when the old one is truly expired; reuse otherwise.
        if ( ! $preview_token || $preview_expiry < time() ) {
            $preview_token  = wp_generate_password( 32, false );
            $preview_expiry = time() + 3600; // 1 hour
            update_post_meta( $post_id, '_eiu_pdf_preview_token', $preview_token );
            update_post_meta( $post_id, '_eiu_pdf_preview_expiry', $preview_expiry );
        }
        // Always ensure the transient exists for the current token.
        set_transient( 'eiu_pdf_preview_' . $preview_token, array(
            'post_id' => $post_id,
            'file'    => $article->file_path,
        ), 3600 );
    }
    $pdf_preview_url = add_query_arg( array( 'eiu_pdf_preview' => $preview_token ), home_url() );
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
/* ══════════════════════════════════════════════════
   EIU Article Detail v1.2 — flat, institutional
   Brand: #9a0805 (red) · #003399 (blue)
   All selectors scoped to .eiu-av-wrap
══════════════════════════════════════════════════ */
.eiu-av-wrap {
  --c-red:    #9a0805;
  --c-red-l:  #fdf0f0;
  --c-blue:   #003399;
  --c-blue-d: #001f7a;
  --c-blue-l: #e8eef8;
  --c-border: #e5e7eb;
  --c-text:   #111827;
  --c-text-2: #374151;
  --c-muted:  #6b7280;
  max-width: 960px; margin: 0 auto; padding-bottom: 48px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
  color: var(--c-text);
}
.eiu-av-wrap * { box-sizing: border-box; }

/* Breadcrumb */
.eiu-av-bc { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--c-muted); margin-bottom: 18px; flex-wrap: wrap; }
.eiu-av-bc a { color: var(--c-blue); text-decoration: none; }
.eiu-av-bc a:hover { text-decoration: underline; }
.eiu-av-bc-current { color: var(--c-text); font-weight: 600; }

/* ── Header card ─────────────────────────────── */
.eiu-av-hcard { background: #fff; border: 1px solid var(--c-border); border-radius: 10px; overflow: hidden; display: flex; }
.eiu-av-thumb-col { width: 260px; min-width: 260px; flex-shrink: 0; position: relative; }
.eiu-av-thumb-img { width: 100%; height: 100%; min-height: 260px; object-fit: cover; display: block; }
.eiu-av-thumb-ph  { width: 100%; min-height: 260px; background: var(--c-blue-l); display: flex; align-items: center; justify-content: center; }
.eiu-av-thumb-ph i { font-size: 3rem; color: var(--c-blue); opacity: .3; }
.eiu-av-subj-chip {
  position: absolute; top: 12px; left: 12px;
  background: var(--c-blue); color: #fff;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
  padding: 3px 9px; border-radius: 3px;
}
.eiu-av-meta-col { flex: 1; padding: 26px 28px; display: flex; flex-direction: column; gap: 12px; }
.eiu-av-title { font-size: clamp(18px, 2.5vw, 24px); font-weight: 700; color: var(--c-text); line-height: 1.3; margin: 0; font-family: Georgia, serif; }
.eiu-av-divider { border: none; border-top: 1px solid #f0f2f5; margin: 0; }

/* ── Author chips — flat with circular photo ── */
.eiu-av-authors { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
.eiu-av-author {
  display: flex; align-items: center; gap: 10px;
  background: var(--c-blue-l); border: 1px solid #c7d5f5;
  border-radius: 8px; padding: 8px 14px 8px 8px; min-width: 0;
}
.eiu-av-author.co-author { background: #f5f0ff; border-color: #d3c5f5; }
.eiu-av-av {
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  background: var(--c-blue); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; font-weight: 800; overflow: hidden;
  border: 2px solid #fff; box-shadow: 0 0 0 1px var(--c-blue);
}
.eiu-av-av img { width: 100%; height: 100%; object-fit: cover; display: block; }
.eiu-av-author-info strong { display: block; font-size: 14px; font-weight: 700; color: var(--c-text); }
.eiu-av-author-info span   { display: block; font-size: 12px; color: var(--c-muted); }
.eiu-av-author-info .role  { font-size: 11px; font-weight: 600; color: var(--c-blue); text-transform: uppercase; letter-spacing: .4px; }

/* Meta rows */
.eiu-av-mrow { display: flex; align-items: center; gap: 7px; font-size: 13px; color: var(--c-text-2); }
.eiu-av-mrow i { color: var(--c-blue); font-size: 14px; flex-shrink: 0; }
.eiu-av-issn {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--c-red-l); color: var(--c-red);
  border: 1px solid rgba(154,8,5,.12);
  padding: 4px 12px; border-radius: 4px; font-size: 13px; font-weight: 700;
}

/* ── Section divider ──────────────────────────── */
.eiu-av-sep { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
.eiu-av-sep::before, .eiu-av-sep::after { content: ''; flex: 1; height: 1px; background: var(--c-border); }
.eiu-av-sep-dots { display: flex; gap: 5px; }
.eiu-av-sep-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--c-blue); }
.eiu-av-sep-dot:nth-child(2) { background: var(--c-red); }
.eiu-av-sep-dot:nth-child(3) { background: #e5e7eb; }

/* ── Content cards ────────────────────────────── */
.eiu-av-card { background: #fff; border: 1px solid var(--c-border); border-radius: 10px; overflow: hidden; margin-bottom: 18px; }
.eiu-av-card-head { padding: 13px 22px; border-bottom: 1px solid #f0f2f5; display: flex; align-items: center; gap: 10px; }
.eiu-av-card-head .accent { width: 4px; height: 18px; border-radius: 2px; flex-shrink: 0; }
.eiu-av-card-head h3 { font-size: 15px; font-weight: 700; margin: 0; color: var(--c-text); }
.eiu-av-card-body { padding: 22px; }

/* Abstract */
.eiu-av-abstract {
  font-family: Georgia, serif; font-style: italic; font-size: 15.5px;
  color: var(--c-text-2, #374151); line-height: 1.9;
  background: transparent; border-left: none;
  padding: 0; border-radius: 0; margin: 0;
}
.eiu-av-abstract p:first-child { margin-top: 0; }
.eiu-av-abstract p:last-child  { margin-bottom: 0; }
.eiu-av-abstract p:empty       { display: none; }

/* References — clear typography */
.eiu-av-references { font-size: 14px; color: var(--c-text-2); line-height: 1.9; }
.eiu-av-references ol { padding-left: 20px; margin: 0; }
.eiu-av-references li { margin-bottom: 8px; }

/* ── PDF preview ──────────────────────────────── */
.eiu-av-pdf-wrap { position: relative; }
.eiu-av-pdf-embed {
  width: 100%; height: 520px; border: none; border-radius: 6px;
  background: #f8f9fa; display: block;
  overflow: auto; /* allow scrolling within the iframe container */
}

/* Download trigger button */
.eiu-av-dl-trigger {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--c-red); color: #fff;
  border: none; border-radius: 7px; padding: 12px 28px;
  font-size: 15px; font-weight: 700; cursor: pointer;
  transition: background .15s, box-shadow .15s;
  box-shadow: 0 4px 14px rgba(154,8,5,.3);
}
.eiu-av-dl-trigger:hover { background: #720000; color: #fff; }

/* Non-PDF fallback */
.eiu-av-pdf-na { background: var(--c-blue-l); border-radius: 8px; padding: 32px; text-align: center; }
.eiu-av-pdf-na .eiu-av-dl-trigger { margin-top: 8px; }

/* Comments */
.eiu-av-comment { display: flex; gap: 12px; }
.eiu-av-cav { width: 38px; height: 38px; border-radius: 50%; background: var(--c-blue); color: #fff; font-size: 15px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.eiu-av-cname { font-size: 14px; font-weight: 700; color: var(--c-text); }
.eiu-av-cdate { font-size: 12px; color: var(--c-muted); }
.eiu-av-ctext { font-size: 14px; color: var(--c-text-2); line-height: 1.7; margin: 5px 0 0; }
.eiu-av-ci { width: 100%; padding: 10px 13px; border: 1.5px solid var(--c-border); border-radius: 7px; font-size: 14px; font-family: inherit; transition: border-color .15s; }
.eiu-av-ci:focus { outline: none; border-color: var(--c-blue); box-shadow: 0 0 0 3px rgba(0,51,153,.1); }
.eiu-av-post-btn { background: var(--c-blue); color: #fff; border: none; border-radius: 7px; padding: 10px 28px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .15s; }
.eiu-av-post-btn:hover { background: var(--c-blue-d); }
.eiu-av-post-btn:disabled { opacity: .6; cursor: not-allowed; }

/* Back */
.eiu-av-back { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: var(--c-blue); text-decoration: none; transition: gap .15s; }
.eiu-av-back:hover { gap: 10px; text-decoration: none; color: var(--c-blue-d); }

/* Responsive */
@media(max-width:680px) {
  .eiu-av-hcard { flex-direction: column; }
  .eiu-av-thumb-col { width: 100%; min-width: unset; }
  .eiu-av-thumb-img { height: 200px; }
  .eiu-av-thumb-ph  { min-height: 160px; }
  .eiu-av-pdf-embed { height: 340px; }
  .eiu-av-authors { flex-direction: column; }
  /* Download button full-width on mobile */
  .eiu-av-dl-trigger { width: 100%; justify-content: center; }
  /* Breadcrumb wraps */
  .eiu-av-bc { flex-wrap: wrap; row-gap: 4px; }
  /* Meta rows stack */
  .eiu-av-meta-col { padding: 18px 16px; }
  .eiu-av-mrow { flex-direction: column; align-items: flex-start; gap: 3px; }
}
@media(max-width:480px) {
  .eiu-av-wrap { padding: 0 4px; }
  .eiu-av-card-head h3 { font-size: 14px; }
  .eiu-av-pdf-embed { height: 280px; }
}
</style>

<div class="eiu-av-wrap">

  <!-- Breadcrumb: Category > Article Title (v1.3) -->
  <nav class="eiu-av-bc" aria-label="Breadcrumb">
    <?php
    // Build: Home > Publications > [Category] > Article Title
    $bc_items = array();

    // Home
    $bc_items[] = '<a href="' . esc_url( home_url() ) . '"><i class="bi bi-house-fill" style="font-size:12px;" aria-hidden="true"></i><span class="visually-hidden">Home</span></a>';

    // Publications listing
    if ( $listing_url ) {
        $bc_items[] = '<a href="' . esc_url( $listing_url ) . '">' . esc_html__( 'Publications', 'eiu-rp' ) . '</a>';
    }

    // Category (subject taxonomy)
    if ( $subject_name ) {
        $subject_term = get_term_by( 'name', $subject_name, 'eiu_subject' );
        if ( $subject_term && $listing_url ) {
            $cat_url = add_query_arg( 'eiu_subject', urlencode( $subject_term->slug ), $listing_url );
            $bc_items[] = '<a href="' . esc_url( $cat_url ) . '">' . esc_html( $subject_name ) . '</a>';
        } else {
            $bc_items[] = '<span>' . esc_html( $subject_name ) . '</span>';
        }
    }

    // Article title (current page — not linked)
    $bc_items[] = '<span class="eiu-av-bc-current" aria-current="page">' . esc_html( wp_trim_words( $article->title, 10 ) ) . '</span>';

    $sep = '<i class="bi bi-chevron-right" style="font-size:10px;" aria-hidden="true"></i>';
    echo implode( ' ' . $sep . ' ', $bc_items ); // phpcs:ignore WordPress.Security.EscapeOutput
    ?>
  </nav>

  <!-- ── 1. Header card ──────────────────────────────────── -->
  <div class="eiu-av-hcard">

    <!-- Left: thumbnail — only rendered when an image exists -->
    <?php if ($thumb_url): ?>
    <div class="eiu-av-thumb-col">
      <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($article->title); ?>" class="eiu-av-thumb-img">
      <?php if ($subject_name): ?>
        <span class="eiu-av-subj-chip"><?php echo esc_html($subject_name); ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Right: meta -->
    <div class="eiu-av-meta-col">

      <h1 class="eiu-av-title"><?php echo esc_html($article->title); ?></h1>
      <hr class="eiu-av-divider">

      <!-- ── Author chips (with circular photos) ── -->
      <div class="eiu-av-authors">
        <!-- Primary Author -->
        <div class="eiu-av-author">
          <div class="eiu-av-av">
            <?php if ($author_photo_url): ?>
              <img src="<?php echo esc_url($author_photo_url); ?>" alt="<?php echo esc_attr($article->author_name); ?>">
            <?php else: ?>
              <?php echo esc_html($author_init); ?>
            <?php endif; ?>
          </div>
          <div class="eiu-av-author-info">
            <strong><?php echo esc_html($article->author_name); ?></strong>
            <span class="role"><?php esc_html_e('Corresponding Author','eiu-rp'); ?></span>
          </div>
        </div>
        <!-- Co-Author -->
        <?php if ($has_coauthor): ?>
          <div class="eiu-av-author co-author">
            <div class="eiu-av-av" style="background:#5c6bc0;box-shadow:0 0 0 1px #5c6bc0;">
              <?php if ($coauth_photo_url): ?>
                <img src="<?php echo esc_url($coauth_photo_url); ?>" alt="<?php echo esc_attr($article->coauthor_name); ?>">
              <?php else: ?>
                <?php echo esc_html($coauth_init); ?>
              <?php endif; ?>
            </div>
            <div class="eiu-av-author-info">
              <strong><?php echo esc_html($article->coauthor_name); ?></strong>
              <span class="role" style="color:#5c6bc0;"><?php esc_html_e('Co-Author','eiu-rp'); ?></span>
            </div>
          </div>
        <?php endif; ?>
      </div>


      <!-- Published -->
      <div class="eiu-av-mrow">
        <i class="bi bi-calendar3-event"></i>
        <?php esc_html_e('Published:','eiu-rp'); ?> <strong><?php echo esc_html($pub_date); ?></strong>
      </div>

      <!-- Mini social share circles (v2.0.1 — Web Share API + HTTPS fix) -->
      <?php
      $abs_url       = preg_replace( '#^http://#', 'https://', get_the_permalink( $post_id ) );
      $_ms_url       = rawurlencode( $abs_url );
      $_ms_title_raw = wp_strip_all_tags( $article->title ?? get_the_title( $post_id ) );
      $_ms_title     = rawurlencode( $_ms_title_raw );
      $_ms_abs       = wp_strip_all_tags( get_post_meta( $post_id, '_eiu_abstract', true ) ?: ( $article->abstract ?? '' ) );
      $_ms_words     = preg_split( '/\s+/', trim( $_ms_abs ), -1, PREG_SPLIT_NO_EMPTY );
      $_ms_desc_raw  = implode( ' ', array_slice( $_ms_words, 0, 60 ) ) . ( count( $_ms_words ) > 60 ? '...' : '' );
      ?>
      <div class="eiu-mini-share" style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:2px;">
        <span style="font-size:11px;color:var(--c-muted);font-weight:600;letter-spacing:.02em;margin-right:2px;"><?php esc_html_e('Share:','eiu-rp'); ?></span>

        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $_ms_url; ?>"
          class="eiu-msc" style="background:#1877f2;" title="Facebook"
          target="_blank" rel="noopener noreferrer">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073c0 6.027 4.388 11.024 10.125 11.927v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953h-1.514c-1.491 0-1.956.927-1.956 1.874v2.25h3.328l-.532 3.49h-2.796v8.437C19.612 23.097 24 18.1 24 12.073z"/></svg>
        </a>
        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $_ms_url; ?>"
          class="eiu-msc" style="background:#0a66c2;" title="LinkedIn"
          target="_blank" rel="noopener noreferrer">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        </a>
        <a href="https://twitter.com/intent/tweet?url=<?php echo $_ms_url; ?>&text=<?php echo $_ms_title; ?>"
          class="eiu-msc" style="background:#000;" title="X (Twitter)"
          target="_blank" rel="noopener noreferrer">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.63 5.905-5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
        <a href="https://wa.me/?text=<?php echo $_ms_title; ?>%20<?php echo $_ms_url; ?>"
          class="eiu-msc" style="background:#25d366;" title="WhatsApp"
          target="_blank" rel="noopener noreferrer">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        </a>
        <button type="button" class="eiu-msc eiu-msc-copy"
          style="background:#6b7280;border:none;cursor:pointer;width:28px;height:28px;padding:0;"
          data-url="<?php echo esc_attr($abs_url); ?>"
          data-title="<?php echo esc_attr($_ms_title_raw); ?>"
          data-text="<?php echo esc_attr($_ms_desc_raw); ?>"
          title="<?php esc_attr_e('Copy link','eiu-rp'); ?>">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
        </button>
      </div>
      <style>
      .eiu-msc{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;min-width:28px;min-height:28px;border-radius:50%;text-decoration:none;transition:opacity .15s,transform .12s;flex-shrink:0;box-sizing:content-box;padding:0;}
      .eiu-msc:hover{opacity:.82;transform:scale(1.12);text-decoration:none;}
      .eiu-msc:active{transform:scale(.96);}
      .eiu-msc-copy.copied{background:#10b981!important;}
      </style>
      <script>
      (function(){
        var cb = document.querySelector('.eiu-msc-copy');
        if (!cb || cb.dataset.mscBound) return;
        cb.dataset.mscBound = '1';
        var shareUrl   = cb.dataset.url   || window.location.href;
        var shareTitle = cb.dataset.title || document.title;
        var shareText  = cb.dataset.text  || '';

        function markCopied(){
          cb.classList.add('copied');
          setTimeout(function(){ cb.classList.remove('copied'); }, 1800);
        }

        /* On mobile (Web Share API available): intercept ALL share buttons */
        if (navigator.share) {
          /* Circle link buttons */
          document.querySelectorAll('.eiu-mini-share a.eiu-msc').forEach(function(link){
            link.addEventListener('click', function(e){
              e.preventDefault();
              navigator.share({ title: shareTitle, text: shareText, url: shareUrl })
                .catch(function(err){
                  /* User cancelled or share failed — silently ignore */
                });
            });
          });
          /* Copy button also triggers share sheet on mobile */
          cb.addEventListener('click', function(){
            navigator.share({ title: shareTitle, text: shareText, url: shareUrl })
              .catch(function(){ markCopied(); });
          });
        } else {
          /* Desktop — copy to clipboard */
          cb.addEventListener('click', function(){
            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(shareUrl).then(markCopied).catch(function(){});
            } else {
              var t = document.createElement('textarea');
              t.value = shareUrl; t.style.cssText = 'position:fixed;opacity:0;';
              document.body.appendChild(t); t.select();
              try { document.execCommand('copy'); } catch(e) {}
              document.body.removeChild(t); markCopied();
            }
          });
        }
      }());
      </script>

      <!-- ISSN -->
      <?php if (!empty($article->issn)): ?>
        <div><span class="eiu-av-issn"><i class="bi bi-upc-scan"></i>ISSN: <?php echo esc_html($article->issn); ?></span></div>
      <?php endif; ?>

      <!-- DOI -->
      <?php if (!empty($article->doi)): ?>
        <div class="eiu-av-mrow">
          <i class="bi bi-link-45deg"></i>
          DOI: <a href="https://doi.org/<?php echo esc_attr($article->doi); ?>" target="_blank" rel="noopener" style="color:var(--c-blue);"><?php echo esc_html($article->doi); ?></a>
        </div>
      <?php endif; ?>

    </div><!-- .eiu-av-meta-col -->
  </div><!-- .eiu-av-hcard -->

  <!-- ── Decorative divider ──────────────────────────────── -->
  <div class="eiu-av-sep">
    <div class="eiu-av-sep-dots">
      <div class="eiu-av-sep-dot"></div>
      <div class="eiu-av-sep-dot"></div>
      <div class="eiu-av-sep-dot"></div>
    </div>
  </div>

  <!-- ── Tab Navigation ──────────────────────────────────── -->
  <?php
  // Increment read count via post meta on each page view (throttled per session by JS)
  $read_count     = (int) get_post_meta( $post_id, '_eiu_read_count', true );
  $download_count = (int) get_option( 'eiu_rp_dl_count_' . $post_id, 0 );
  // Accurate download count from leads table
  global $wpdb;
  $download_count = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->prefix}eiu_download_leads WHERE article_id = %d",
      $article->id
  ) );
  $track_nonce    = wp_create_nonce( 'eiu_rp_track_read_' . $post_id );
  ?>

  <style>
  /* Tab layout */
  .eiu-av-tabs {
    border-bottom: 1.5px solid #e0e6ef;
    margin-bottom: 0; display: flex; gap: 0;
    overflow-x: auto; scrollbar-width: none;
    background: transparent; padding: 0 6px;
    -webkit-overflow-scrolling: touch;
  }
  .eiu-av-tabs::-webkit-scrollbar { display: none; }
  .eiu-av-tab-btn {
    padding: 12px 20px 10px; font-size: 13px; font-weight: 500;
    color: #8494a9;
    /* Explicit overrides — prevent Bootstrap/theme from adding fills */
    background: transparent !important;
    border: none !important;
    border-bottom: 2px solid transparent !important;
    outline: none;
    cursor: pointer;
    white-space: nowrap; display: inline-flex; align-items: center; gap: 7px;
    margin-bottom: -2px;
    /* Smooth, professional transitions */
    transition: color .18s ease, border-color .18s ease;
    letter-spacing: .01em; flex-shrink: 0;
  }
  /* Hover: text darkens, no fill */
  .eiu-av-tab-btn:hover {
    color: #2d4a6e;
    background: transparent !important;
    border-bottom-color: transparent !important;
  }
  /* Active: dark text + single blue underline — zero fill */
  .eiu-av-tab-btn.active {
    color: #1a3558 !important;
    font-weight: 600;
    background: transparent !important;
    border-bottom-color: #1a4988 !important;
    box-shadow: none !important;
  }
  .eiu-av-tab-btn.active i { color: #1a4988; }
  /* Focus ring for keyboard users */
  .eiu-av-tab-btn:focus-visible {
    outline: 2px solid #1a4988 !important;
    outline-offset: -2px;
    border-radius: 4px 4px 0 0;
  }
  .eiu-av-tab-pane { display: none; }

  /* ── Mobile tab dropdown (replaces tab strip on small screens) ── */
  @media (max-width: 600px) {
    .eiu-av-tabs { display: none !important; }
    #eiu-tab-dropdown-wrap { display: block !important; }
  }
  .eiu-av-tab-dropdown {
    width: 100%; appearance: none; -webkit-appearance: none;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%231a4988' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 14px center;
    background-size: 18px;
    border: 1.5px solid #c7d9f8; border-radius: 10px;
    padding: 11px 42px 11px 14px; font-size: 14px; font-weight: 600;
    color: #1a3558; cursor: pointer; outline: none;
    box-shadow: 0 1px 4px rgba(26,73,136,.08); transition: border-color .15s;
  }
  .eiu-av-tab-dropdown:focus { border-color: #1a4988; box-shadow: 0 0 0 3px rgba(26,73,136,.12); }
  .eiu-av-tab-pane.active {
    display: block;
    animation: eiuAvFade .16s ease both;
  }
  @keyframes eiuAvFade {
    from { opacity: 0; transform: translateY(3px); }
    to   { opacity: 1; transform: none; }
  }
  /* Metrics */
  .eiu-av-metric-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(180px,1fr)); gap: 16px; margin-bottom: 18px; }
  .eiu-av-metric-card { background: #fff; border: 1px solid var(--c-border,#e5e7eb); border-radius: 10px; padding: 22px 20px; display: flex; align-items: center; gap: 16px; }
  .eiu-av-metric-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; flex-shrink: 0; }
  .eiu-av-metric-num { font-size: 30px; font-weight: 800; line-height: 1; margin-bottom: 2px; }
  .eiu-av-metric-lbl { font-size: 11px; color: var(--c-muted,#6b7280); font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
  </style>

  <div class="eiu-av-card" style="overflow:visible;">
    <!-- Tab buttons -->
    <!-- Desktop: horizontal tab buttons | Mobile: replaced by dropdown via CSS/JS -->
    <div class="eiu-av-tabs" role="tablist" id="eiu-av-tablist">
      <button class="eiu-av-tab-btn active" data-tab="abstract" role="tab">
        <i class="bi bi-file-text"></i><?php esc_html_e('Abstract','eiu-rp'); ?>
      </button>
      <button class="eiu-av-tab-btn" data-tab="document" role="tab">
        <i class="bi bi-file-earmark-pdf"></i><?php esc_html_e('Manuscript','eiu-rp'); ?>
      </button>
      <?php if (!empty($references)): ?>
      <button class="eiu-av-tab-btn" data-tab="references" role="tab">
        <i class="bi bi-journals"></i><?php esc_html_e('References','eiu-rp'); ?>
      </button>
      <?php endif; ?>
      <button class="eiu-av-tab-btn" data-tab="authors" role="tab">
        <i class="bi bi-people"></i><?php esc_html_e('Author Information','eiu-rp'); ?>
      </button>
      <button class="eiu-av-tab-btn" data-tab="metrics" role="tab">
        <i class="bi bi-bar-chart-line"></i><?php esc_html_e('Metrics','eiu-rp'); ?>
      </button>
    </div>

    <!-- Mobile: dropdown tab switcher (shown only on small screens via CSS) -->
    <div class="eiu-av-tab-dropdown-wrap" id="eiu-tab-dropdown-wrap" style="display:none;padding:10px 14px 0;">
      <select id="eiu-tab-dropdown" class="eiu-av-tab-dropdown" aria-label="<?php esc_attr_e('Switch section','eiu-rp'); ?>">
        <option value="abstract"><?php esc_html_e('Abstract','eiu-rp'); ?></option>
        <option value="document"><?php esc_html_e('Manuscript','eiu-rp'); ?></option>
        <?php if (!empty($references)): ?>
        <option value="references"><?php esc_html_e('References','eiu-rp'); ?></option>
        <?php endif; ?>
        <option value="authors"><?php esc_html_e('Author Information','eiu-rp'); ?></option>
        <option value="metrics"><?php esc_html_e('Metrics','eiu-rp'); ?></option>
      </select>
    </div>

    <!-- ── Tab: Abstract ─────────────────────── -->
    <div class="eiu-av-tab-pane active eiu-av-card-body" id="tab-abstract" role="tabpanel">
      <?php if (!empty($abstract_content)): ?>
        <p class="eiu-av-abstract"><?php echo wp_kses_post(nl2br($abstract_content)); ?></p>
      <?php else: ?>
        <p style="color:var(--c-muted,#6b7280);font-style:italic;"><?php esc_html_e('No abstract available.','eiu-rp'); ?></p>
      <?php endif; ?>
      <?php if (!empty($art_content)): ?>
        <hr style="border-color:#f0f2f5;margin:20px 0;">
        <div style="font-size:15px;color:#374151;line-height:1.8;"><?php echo wp_kses_post($art_content); ?></div>
      <?php endif; ?>
    </div>

    <!-- ── Tab: Research Document ────────────── -->
    <div class="eiu-av-tab-pane eiu-av-card-body" id="tab-document" role="tabpanel">
      <?php if ($has_file): ?>
        <?php if (!empty($pdf_preview_url)): ?>
          <?php
          /* Build the correct viewer URL for each device type:
           * — Desktop/tablet-large: native browser PDF iframe (fastest, no external dep)
           * — All touch devices (phones + tablets): Google Docs PDF viewer
           *   (https://docs.google.com/viewer?url=...&embedded=true)
           *   This renders PDFs on iOS Safari, Android Chrome, and any mobile browser
           *   without requiring app redirect or leaving the page.
           *
           * The PDF URL must be publicly accessible for Google Docs viewer to fetch it.
           * The JS below swaps the iframe src after detecting the device type.
           */
          $pdf_native_url = esc_url( $pdf_preview_url ) . '#toolbar=0&navpanes=0&scrollbar=1&view=FitH&zoom=page-fit';
          $pdf_gdocs_url  = 'https://docs.google.com/viewer?url=' . rawurlencode( $pdf_preview_url ) . '&embedded=true';
          ?>
          <div class="eiu-av-pdf-wrap" id="eiu-pdf-wrap">
            <!-- Unified iframe — src is swapped by JS based on device type -->
            <iframe
              id="eiu-pdf-iframe"
              src="<?php echo $pdf_native_url; ?>"
              class="eiu-av-pdf-embed"
              title="<?php echo esc_attr($article->title); ?>"
              loading="lazy"
              allowfullscreen>
              <p><?php esc_html_e('Your browser does not support PDF preview.','eiu-rp'); ?></p>
            </iframe>

            <!-- Loading overlay — shown while Google Docs viewer initialises on mobile -->
            <div id="eiu-pdf-loading" style="display:none;position:absolute;inset:0;background:#f8f9fa;border-radius:6px;align-items:center;justify-content:center;flex-direction:column;gap:12px;z-index:3;">
              <div style="width:36px;height:36px;border:3px solid #e5e7eb;border-top-color:#1a4988;border-radius:50%;animation:eiu-spin .7s linear infinite;"></div>
              <p style="font-size:13px;color:#6b7280;margin:0;"><?php esc_html_e('Loading PDF…','eiu-rp'); ?></p>
            </div>
          </div>
          <p class="text-center mt-3 small text-muted" id="eiu-pdf-caption">
            <?php esc_html_e('Scroll to read the preview. Download for the complete document.','eiu-rp'); ?>
          </p>
          <style>
          @keyframes eiu-spin { to { transform: rotate(360deg); } }
          /* Mobile: taller iframe so more content is visible without scrolling */
          @media (max-width: 768px) {
            .eiu-av-pdf-embed { height: 75vh !important; min-height: 400px; }
          }
          </style>
          <script>
          (function(){
            var ua  = navigator.userAgent;
            var isTouch = ('ontouchstart' in window)
                       || (navigator.maxTouchPoints > 0)
                       || (/Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(ua));
            /* Modern iPad reports as Macintosh — catch it via touch points */
            var isIPad  = /iPad/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);

            var iframe   = document.getElementById('eiu-pdf-iframe');
            var loading  = document.getElementById('eiu-pdf-loading');
            var wrap     = document.getElementById('eiu-pdf-wrap');
            var nativeUrl = <?php echo wp_json_encode( $pdf_native_url ); ?>;
            var gdocsUrl  = <?php echo wp_json_encode( $pdf_gdocs_url ); ?>;

            if (!iframe) return;

            if (isTouch) {
              /* Touch device: switch to Google Docs viewer for universal PDF support.
                 Show loading spinner while Google Docs initialises (can take 2-4s). */
              if (loading) { loading.style.display = 'flex'; wrap.style.position = 'relative'; }

              iframe.src = gdocsUrl;

              /* Set taller height for comfortable reading on tablet */
              if (isIPad || window.screen.width > 600) {
                iframe.style.height = '75vh';
              } else {
                iframe.style.height = '75vh';
              }

              /* Hide loading spinner once iframe loads */
              iframe.addEventListener('load', function(){
                if (loading) loading.style.display = 'none';
              });

              /* Fallback: hide spinner after 8s even if load never fires (cross-origin) */
              setTimeout(function(){
                if (loading) loading.style.display = 'none';
              }, 8000);

            } else {
              /* Desktop: keep native PDF viewer, right-click protection */
              if (wrap) {
                wrap.addEventListener('contextmenu', function(e){
                  e.preventDefault(); return false;
                });
              }
            }
          }());
          </script>
        <?php else: ?>
          <div class="eiu-av-pdf-na">
            <i class="bi bi-file-earmark-text" style="font-size:2.5rem;color:var(--c-blue,#1a4988);opacity:.4;display:block;margin-bottom:12px;"></i>
            <p class="fw-semibold mb-2" style="color:#374151;">
              <?php echo esc_html(strtoupper($article->file_type)); ?> &mdash; <?php echo esc_html($article->file_name); ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- Inline Download Form -->
        <div class="eiu-dl-inline" id="eiu-dl-inline-wrap" style="margin-top:18px;text-align:center;">
          <button type="button" class="eiu-av-dl-trigger" id="eiu-dl-toggle">
            <i class="bi bi-cloud-download"></i>
            <?php esc_html_e('Download Article','eiu-rp'); ?>
          </button>
          <div class="eiu-dl-form-panel" id="eiu-dl-panel" style="display:none;margin-top:12px;">
            <div class="eiu-dl-form-inner">
              <i class="bi bi-cloud-arrow-down eiu-dl-form-icon"></i>
              <p class="eiu-dl-form-title"><?php esc_html_e('Enter your email to download','eiu-rp'); ?></p>
              <p class="eiu-dl-form-sub"><?php esc_html_e('We use this only to track research access. Your email stays private.','eiu-rp'); ?></p>
              <div class="eiu-dl-input-row">
                <input type="email" id="eiu-dl-email" class="eiu-dl-email-input"
                  placeholder="<?php esc_attr_e('your@email.com','eiu-rp'); ?>"
                  autocomplete="email">
                <button type="button" class="eiu-dl-submit" id="eiu-dl-btn"
                  data-post="<?php echo esc_attr($post_id); ?>"
                  data-nonce="<?php echo esc_attr($dl_nonce); ?>">
                  <i class="bi bi-download me-1"></i><?php esc_html_e('Download','eiu-rp'); ?>
                </button>
              </div>
              <div class="eiu-dl-msg" id="eiu-dl-msg"></div>
              <button type="button" class="eiu-dl-cancel" id="eiu-dl-cancel">
                <i class="bi bi-x me-1"></i><?php esc_html_e('Cancel','eiu-rp'); ?>
              </button>
            </div>
          </div>
        </div>
      <?php else: ?>
        <p style="color:var(--c-muted,#6b7280);font-style:italic;"><?php esc_html_e('No document available for this article.','eiu-rp'); ?></p>
      <?php endif; ?>
    </div>

    <!-- ── Tab: References ───────────────────── -->
    <div class="eiu-av-tab-pane eiu-av-card-body" id="tab-references" role="tabpanel">
      <?php if (!empty($references)): ?>
        <div class="eiu-av-references"><?php echo wp_kses_post(nl2br($references)); ?></div>
      <?php endif; ?>
    </div>

    <!-- ── Tab: Author Information ───────────── -->
    <div class="eiu-av-tab-pane eiu-av-card-body" id="tab-authors" role="tabpanel">

      <!-- Author Information tab (v2.0.1) -->
      <?php
      // Collect all available detail fields for the primary author
      $affil_val   = $article->author_affiliation ?? '';
      $org_val     = $article->author_org         ?? '';
      $country_val = $article->country            ?? '';
      $adviser_val = $article->advisers           ?? '';
      $phone_val   = $article->contact_number     ?? '';
      $has_details = $affil_val || $org_val || $country_val || $adviser_val || $phone_val;
      ?>

      <!-- ── Author + Co-Author chips row ─────────────────── -->
      <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-start;margin-bottom:<?php echo $has_details ? '24px' : '0'; ?>;">

        <!-- Primary Author chip -->
        <div style="display:flex;align-items:center;gap:14px;background:#eef4ff;border:1px solid #c7d9f8;border-radius:12px;padding:14px 18px;min-width:240px;flex:1;max-width:420px;">
          <div class="eiu-av-av">
            <?php if ($author_photo_url): ?>
              <img src="<?php echo esc_url($author_photo_url); ?>" alt="<?php echo esc_attr($article->author_name); ?>">
            <?php else: ?>
              <?php echo esc_html($author_init); ?>
            <?php endif; ?>
          </div>
          <div>
            <strong style="font-size:15px;color:var(--c-text,#1a2535);display:block;line-height:1.3;"><?php echo esc_html($article->author_name); ?></strong>
            <span class="role" style="display:block;margin-top:3px;"><?php esc_html_e('Corresponding Author','eiu-rp'); ?></span>
          </div>
        </div>

        <!-- Co-Author chip -->
        <?php if ($has_coauthor): ?>
          <div style="display:flex;align-items:center;gap:14px;background:#f5f0ff;border:1px solid #d3c5f5;border-radius:12px;padding:14px 18px;min-width:240px;flex:1;max-width:420px;">
            <div class="eiu-av-av" style="background:#5c6bc0;box-shadow:0 0 0 1px #5c6bc0;">
              <?php if ($coauth_photo_url): ?>
                <img src="<?php echo esc_url($coauth_photo_url); ?>" alt="<?php echo esc_attr($article->coauthor_name); ?>">
              <?php else: ?>
                <?php echo esc_html($coauth_init); ?>
              <?php endif; ?>
            </div>
            <div>
              <strong style="font-size:15px;color:var(--c-text,#1a2535);display:block;line-height:1.3;"><?php echo esc_html($article->coauthor_name); ?></strong>
              <?php if (!empty($article->coauthor_org)): ?>
                <span style="font-size:12px;color:var(--c-muted,#6b7280);display:block;margin-top:2px;"><?php echo esc_html($article->coauthor_org); ?></span>
              <?php endif; ?>
              <span class="role" style="color:#5c6bc0;display:block;margin-top:3px;"><?php esc_html_e('Co-Author','eiu-rp'); ?></span>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <!-- ── Author details section — only rendered if at least one field has data ── -->
      <?php if ($has_details): ?>
      <div style="border:1px solid #e8eef8;border-radius:12px;overflow:hidden;">

        <?php if ($affil_val): ?>
        <!-- Affiliation — full-width prominent block -->
        <div style="padding:20px 24px;border-bottom:1px solid #f0f4fc;background:#fff;">
          <p style="margin:0 0 8px;font-size:11px;font-weight:800;color:var(--c-blue,#1a4988);text-transform:uppercase;letter-spacing:.6px;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-award-fill" style="font-size:13px;"></i><?php esc_html_e('Affiliation','eiu-rp'); ?>
          </p>
          <div style="font-size:14px;color:var(--c-text,#1a2535);line-height:1.7;font-weight:400;">
            <?php echo wp_kses_post( $affil_val ); ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Other details in a 2-col grid -->
        <?php $other = array_filter(array(
          array('bi-building',   'Organization',          $org_val,     false),
          array('bi-geo-alt',    'Country',               $country_val, false),
          array('bi-person-badge','Advisers / Supervisors',$adviser_val, false),
          array('bi-telephone',  'Contact',               $phone_val,   false),
        ), function($r){ return !empty($r[2]); }); ?>
        <?php if (!empty($other)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0;">
          <?php foreach (array_values($other) as $i => $row): ?>
          <div style="padding:14px 24px;<?php echo $i < count($other)-1 ? 'border-bottom:1px solid #f0f4fc;border-right:1px solid #f0f4fc;' : ''; ?>background:#fafbff;">
            <p style="margin:0 0 4px;font-size:10px;font-weight:700;color:var(--c-muted,#6b7280);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:5px;">
              <i class="bi <?php echo esc_attr($row[0]); ?>" style="color:var(--c-blue,#1a4988);font-size:12px;"></i><?php echo esc_html(__($row[1],'eiu-rp')); ?>
            </p>
            <p style="margin:0;font-size:14px;color:var(--c-text,#1a2535);line-height:1.5;"><?php echo esc_html($row[2]); ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div>
      <?php endif; ?>

      <!-- Additional Co-Authors from co_authors_json (v2.0) -->
      <?php
      $extra_coauthors = array();
      if ( ! empty( $article->co_authors_json ) ) {
          $decoded = json_decode( $article->co_authors_json, true );
          if ( is_array( $decoded ) && count( $decoded ) > 1 ) {
              $extra_coauthors = array_slice( $decoded, 1 );
          }
      }
      ?>
      <?php if ( ! empty( $extra_coauthors ) ): ?>
      <div style="margin-top:20px;padding-top:18px;border-top:1px solid #f0f2f5;">
        <p style="font-size:11px;font-weight:700;color:var(--c-muted,#6b7280);text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px;">
          <i class="bi bi-people me-1" style="color:#5c6bc0;"></i><?php esc_html_e('Additional Co-Authors','eiu-rp'); ?>
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
          <?php foreach ($extra_coauthors as $ca): ?>
            <?php if (empty($ca['name']) && empty($ca['email'])) continue; ?>
            <div style="background:#f8f5ff;border:1px solid #e9e0fb;border-radius:8px;padding:10px 14px;min-width:180px;">
              <p style="margin:0;font-size:14px;font-weight:700;color:#374151;"><?php echo esc_html($ca['name'] ?? ''); ?></p>
              <?php if (!empty($ca['org'])): ?>
                <p style="margin:2px 0 0;font-size:12px;color:#6b7280;"><?php echo esc_html($ca['org']); ?></p>
              <?php endif; ?>
              <?php if (!empty($ca['contribution'])): ?>
                <p style="margin:3px 0 0;font-size:11px;color:#5c6bc0;font-weight:600;"><?php echo esc_html($ca['contribution']); ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
    <!-- ── Tab: Metrics ──────────────────────── -->
    <div class="eiu-av-tab-pane eiu-av-card-body" id="tab-metrics" role="tabpanel">
      <div class="eiu-av-metric-grid">
        <div class="eiu-av-metric-card">
          <div class="eiu-av-metric-icon" style="background:var(--c-blue,#1a4988);">
            <i class="bi bi-download"></i>
          </div>
          <div>
            <div class="eiu-av-metric-num" style="color:var(--c-blue,#1a4988);" id="eiu-dl-count">
              <?php echo esc_html( number_format_i18n( $download_count ) ); ?>
            </div>
            <div class="eiu-av-metric-lbl"><?php esc_html_e('Downloads','eiu-rp'); ?></div>
          </div>
        </div>
        <div class="eiu-av-metric-card">
          <div class="eiu-av-metric-icon" style="background:#7c3aed;">
            <i class="bi bi-eye"></i>
          </div>
          <div>
            <div class="eiu-av-metric-num" style="color:#7c3aed;" id="eiu-read-count">
              <?php echo esc_html( number_format_i18n( $read_count ) ); ?>
            </div>
            <div class="eiu-av-metric-lbl"><?php esc_html_e('Views','eiu-rp'); ?></div>
          </div>
        </div>
      </div>
      <p style="font-size:12px;color:var(--c-muted,#6b7280);margin:0;">
        <i class="bi bi-info-circle me-1"></i>
        <?php esc_html_e('Metrics are updated in real time as the article is accessed and downloaded.','eiu-rp'); ?>
      </p>
    </div>

  </div><!-- .eiu-av-card (tabs) -->

  <!-- ── Comments ────────────────────────────────────────── -->
  <div class="eiu-av-card" id="eiu-comments-section">
    <div class="eiu-av-card-head">
      <span class="accent" style="background:var(--c-red);"></span>
      <h3><?php esc_html_e('Comments','eiu-rp'); ?></h3>
      <?php $cc=(int)get_comments_number($post_id); if($cc>0): ?>
        <span class="ms-2 badge" style="background:var(--c-blue);color:#fff;border-radius:20px;padding:3px 10px;font-size:11px;"><?php echo esc_html($cc); ?></span>
      <?php endif; ?>
    </div>
    <div class="eiu-av-card-body">

      <?php
      $comments = get_comments(array('post_id'=>$post_id,'status'=>'approve','order'=>'ASC','number'=>50));
      if (!empty($comments)):
      ?>
        <div class="d-flex flex-column gap-3 mb-4">
          <?php foreach ($comments as $c):
            $ci = strtoupper(substr($c->comment_author,0,1));
          ?>
            <div class="eiu-av-comment">
              <div class="eiu-av-cav"><?php echo esc_html($ci); ?></div>
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2">
                  <span class="eiu-av-cname"><?php echo esc_html($c->comment_author); ?></span>
                  <span class="eiu-av-cdate"><i class="bi bi-clock me-1"></i><?php echo esc_html(date_i18n('d/m/Y',strtotime($c->comment_date))); ?></span>
                </div>
                <p class="eiu-av-ctext"><?php echo nl2br(esc_html($c->comment_content)); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <hr style="border-color:#f0f2f5;">
      <?php endif; ?>

      <p class="fw-semibold mb-3" style="font-size:14px;color:var(--c-text-2);">
        <i class="bi bi-chat-dots me-1" style="color:var(--c-blue);"></i>
        <?php esc_html_e('Leave a Comment','eiu-rp'); ?>
      </p>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-person" style="color:#6c757d;"></i></span>
            <input type="text" id="eiu-c-name" class="eiu-av-ci" placeholder="<?php esc_attr_e('Your Name *','eiu-rp'); ?>" style="border-radius:0 7px 7px 0;">
          </div>
        </div>
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-envelope" style="color:#6c757d;"></i></span>
            <input type="email" id="eiu-c-email" class="eiu-av-ci" placeholder="<?php esc_attr_e('Your Email *','eiu-rp'); ?>" style="border-radius:0 7px 7px 0;">
          </div>
        </div>
      </div>
      <textarea id="eiu-c-text" class="eiu-av-ci mb-3" rows="5" placeholder="<?php esc_attr_e('Write your comment here…','eiu-rp'); ?>" style="resize:vertical;"></textarea>
      <div class="text-end">
        <button type="button" class="eiu-av-post-btn" id="eiu-post-btn"
          data-post="<?php echo esc_attr($post_id); ?>"
          data-nonce="<?php echo esc_attr(wp_create_nonce('eiu_rp_comment_'.$post_id)); ?>">
          <i class="bi bi-send me-1"></i><?php esc_html_e('Post Comment','eiu-rp'); ?>
        </button>
      </div>
      <div id="eiu-comment-resp" class="mt-3 small" style="display:none;"></div>

    </div>
  </div>

  <?php
  /* ── Social Share Bar (v2.0.1) ─────────────────────────── */
  $share_url   = rawurlencode( get_the_permalink( $post_id ) );
  $share_title = rawurlencode( wp_strip_all_tags( $article->title ?? get_the_title( $post_id ) ) );
  // Build 60-word excerpt for the share description
  $_share_abs   = wp_strip_all_tags( get_post_meta( $post_id, '_eiu_abstract', true ) ?: ( $article->abstract ?? '' ) );
  $_share_words = preg_split( '/\s+/', trim( $_share_abs ), -1, PREG_SPLIT_NO_EMPTY );
  $share_desc   = rawurlencode( implode( ' ', array_slice( $_share_words, 0, 60 ) ) . ( count( $_share_words ) > 60 ? '…' : '' ) );
  $share_author = rawurlencode( wp_strip_all_tags( $article->author_name ?? '' ) );

  // Share URLs
  $share_fb  = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url;
  $share_li  = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url;
  $share_x   = 'https://twitter.com/intent/tweet?url=' . $share_url . '&text=' . $share_title . '&via=';
  $share_wa  = 'https://wa.me/?text=' . $share_title . '%20' . $share_url;
  ?>
  <div class="eiu-share-bar" style="margin:32px 0 24px;padding:20px 24px;background:#f8faff;border:1px solid #e8eef8;border-radius:12px;">
    <p style="margin:0 0 14px;font-size:13px;font-weight:700;color:#1a4988;letter-spacing:.03em;text-transform:uppercase;">
      <i class="bi bi-share-fill me-2"></i><?php esc_html_e('Share this Article','eiu-rp'); ?>
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">

      <!-- Facebook -->
      <a href="<?php echo esc_url($share_fb); ?>" target="_blank" rel="noopener noreferrer"
        class="eiu-share-btn"
        style="background:#1877f2;"
        aria-label="<?php esc_attr_e('Share on Facebook','eiu-rp'); ?>"
>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073c0 6.027 4.388 11.024 10.125 11.927v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953h-1.514c-1.491 0-1.956.927-1.956 1.874v2.25h3.328l-.532 3.49h-2.796v8.437C19.612 23.097 24 18.1 24 12.073z"/></svg>
        <span>Facebook</span>
      </a>

      <!-- LinkedIn -->
      <a href="<?php echo esc_url($share_li); ?>" target="_blank" rel="noopener noreferrer"
        class="eiu-share-btn"
        style="background:#0a66c2;"
        aria-label="<?php esc_attr_e('Share on LinkedIn','eiu-rp'); ?>"
>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
        <span>LinkedIn</span>
      </a>

      <!-- X (Twitter) -->
      <a href="<?php echo esc_url($share_x); ?>" target="_blank" rel="noopener noreferrer"
        class="eiu-share-btn"
        style="background:#000;"
        aria-label="<?php esc_attr_e('Share on X','eiu-rp'); ?>"
>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.63 5.905-5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        <span>X</span>
      </a>

      <!-- WhatsApp -->
      <a href="<?php echo esc_url($share_wa); ?>" target="_blank" rel="noopener noreferrer"
        class="eiu-share-btn"
        style="background:#25d366;"
        aria-label="<?php esc_attr_e('Share on WhatsApp','eiu-rp'); ?>"
>
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span>WhatsApp</span>
      </a>

      <!-- Copy Link -->
      <button type="button" class="eiu-share-btn eiu-share-copy"
        style="background:#6b7280;"
        data-url="<?php echo esc_attr(get_the_permalink($post_id)); ?>"
        aria-label="<?php esc_attr_e('Copy link','eiu-rp'); ?>">
        <i class="bi bi-link-45deg" style="font-size:18px;"></i>
        <span id="eiu-copy-lbl"><?php esc_html_e('Copy Link','eiu-rp'); ?></span>
      </button>

    </div><!-- /.flex -->
  </div><!-- .eiu-share-bar -->

  <style>
  .eiu-share-btn{
    display:inline-flex;align-items:center;gap:8px;
    color:#fff;text-decoration:none;border:none;cursor:pointer;
    padding:9px 18px;border-radius:8px;
    font-size:13px;font-weight:600;font-family:inherit;
    transition:opacity .15s,transform .1s;
    white-space:nowrap;
  }
  .eiu-share-btn:hover{opacity:.88;transform:translateY(-1px);color:#fff;text-decoration:none;}
  .eiu-share-btn:active{transform:translateY(0);}
  @media(max-width:540px){
    .eiu-share-btn{padding:8px 14px;font-size:12px;}
    .eiu-share-btn span{display:none;}
    .eiu-share-btn svg,.eiu-share-btn i{margin:0;}
  }
  </style>
  <script>
  (function(){
    var copyBtn=document.querySelector('.eiu-share-copy');
    if(!copyBtn) return;
    copyBtn.addEventListener('click',function(){
      var url=this.dataset.url;
      var lbl=document.getElementById('eiu-copy-lbl');
      if(navigator.clipboard&&navigator.clipboard.writeText){
        navigator.clipboard.writeText(url).then(function(){
          if(lbl){lbl.textContent='<?php echo esc_js(__('Copied!','eiu-rp')); ?>';}
          setTimeout(function(){if(lbl)lbl.textContent='<?php echo esc_js(__('Copy Link','eiu-rp')); ?>';},2000);
        });
      } else {
        var ta=document.createElement('textarea');
        ta.value=url; ta.style.position='fixed'; ta.style.opacity='0';
        document.body.appendChild(ta); ta.select();
        try{document.execCommand('copy');}catch(e){}
        document.body.removeChild(ta);
        if(lbl){lbl.textContent='<?php echo esc_js(__('Copied!','eiu-rp')); ?>';}
        setTimeout(function(){if(lbl)lbl.textContent='<?php echo esc_js(__('Copy Link','eiu-rp')); ?>';},2000);
      }
    });
  }());
  </script>

  <a href="<?php echo esc_url($listing_url); ?>" class="eiu-av-back">
    <i class="bi bi-arrow-left"></i><?php esc_html_e('Back to Publications','eiu-rp'); ?>
  </a>

</div><!-- .eiu-av-wrap -->

<!-- ══ Download inline form styles (no popup) ══════════════════ -->
<style>
/* Inline download panel — replaces popup modal */
.eiu-dl-inline{margin-top:18px;text-align:center;}
.eiu-dl-form-panel{
  background:linear-gradient(135deg,#f0f6ff 0%,#eef2ff 100%);
  border:1.5px solid var(--c-blue-l,#b8d0f0);
  border-radius:10px;padding:22px 20px 18px;margin-top:12px;
  animation:eiu-dl-fadein .2s ease;
}
@keyframes eiu-dl-fadein{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:none;}}
.eiu-dl-form-inner{max-width:480px;margin:0 auto;}
.eiu-dl-form-icon{font-size:2rem;color:var(--c-blue,#1a4988);display:block;margin-bottom:8px;}
.eiu-dl-form-title{font-size:15px;font-weight:700;color:var(--c-text,#111827);margin:0 0 4px;}
.eiu-dl-form-sub{font-size:12px;color:var(--c-muted,#6b7280);margin:0 0 14px;}
.eiu-dl-input-row{display:flex;gap:8px;align-items:stretch;}
.eiu-dl-email-input{
  flex:1;padding:11px 14px;border:1.5px solid var(--c-border,#d1d5db);
  border-radius:7px;font-size:14px;font-family:inherit;
  transition:border-color .15s;min-width:0;
}
.eiu-dl-email-input:focus{outline:none;border-color:var(--c-blue,#1a4988);box-shadow:0 0 0 3px rgba(26,73,136,.1);}
.eiu-dl-submit{
  padding:11px 20px;background:var(--c-red,#9a0805);color:#fff;
  border:none;border-radius:7px;font-size:14px;font-weight:700;
  cursor:pointer;white-space:nowrap;flex-shrink:0;
  transition:background .15s;display:inline-flex;align-items:center;gap:6px;
}
.eiu-dl-submit:hover{background:#720000;}
.eiu-dl-submit:disabled{opacity:.65;cursor:not-allowed;}
.eiu-dl-msg{font-size:13px;margin-top:10px;min-height:18px;text-align:center;}
.eiu-dl-msg.ok{color:#166534;}.eiu-dl-msg.err{color:var(--c-red,#9a0805);}
.eiu-dl-cancel{
  background:none;border:none;color:var(--c-muted,#6b7280);
  font-size:12px;cursor:pointer;margin-top:10px;padding:4px 8px;
  border-radius:5px;transition:color .12s;display:inline-flex;align-items:center;gap:4px;
}
.eiu-dl-cancel:hover{color:var(--c-red,#9a0805);}
@media(max-width:540px){
  .eiu-dl-input-row{flex-direction:column;}
  .eiu-dl-submit{justify-content:center;}
}
</style>

<script>
(function(){
'use strict';
var ajaxUrl = typeof eiuRP!=='undefined' ? eiuRP.ajaxUrl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

/* ── Tab switching ────────────────────────────── */
var tabList = document.getElementById('eiu-av-tablist');
if(tabList){
  tabList.addEventListener('click', function(e){
    var btn = e.target.closest('.eiu-av-tab-btn');
    if(!btn) return;
    var tabId = btn.getAttribute('data-tab');
    // Deactivate all
    tabList.querySelectorAll('.eiu-av-tab-btn').forEach(function(b){ b.classList.remove('active'); });
    document.querySelectorAll('.eiu-av-tab-pane').forEach(function(p){ p.classList.remove('active'); });
    // Activate clicked
    btn.classList.add('active');
    var pane = document.getElementById('tab-' + tabId);
    if(pane) pane.classList.add('active');
    // Sync mobile dropdown
    var dd = document.getElementById('eiu-tab-dropdown');
    if(dd) dd.value = tabId;
    // Persist tab in URL hash without scrolling
    if(history.replaceState){
      history.replaceState(null, '', '#tab-' + tabId);
    }
  });
  // Restore tab from URL hash on load
  var hash = window.location.hash.replace('#tab-','');
  if(hash){
    var target = tabList.querySelector('[data-tab="' + hash + '"]');
    if(target) target.click();
  }
}

/* Mobile dropdown tab switcher */
(function(){
  var dd = document.getElementById('eiu-tab-dropdown');
  if(!dd) return;
  dd.addEventListener('change', function(){
    var tabId = this.value;
    var tl = document.getElementById('eiu-av-tablist');
    if(tl){
      tl.querySelectorAll('.eiu-av-tab-btn').forEach(function(b){ b.classList.remove('active'); });
      var targetBtn = tl.querySelector('[data-tab="'+tabId+'"]');
      if(targetBtn) targetBtn.classList.add('active');
    }
    document.querySelectorAll('.eiu-av-tab-pane').forEach(function(p){ p.classList.remove('active'); });
    var pane = document.getElementById('tab-'+tabId);
    if(pane) pane.classList.add('active');
    if(history.replaceState) history.replaceState(null,'','#tab-'+tabId);
  });
}());

/* ── Read count tracking (once per session) ───── */
(function(){
  var key = 'eiu_read_' + <?php echo (int) $post_id; ?>;
  if(sessionStorage.getItem(key)) return; // already tracked this session
  sessionStorage.setItem(key, '1');
  var fd = new FormData();
  fd.append('action',  'eiu_rp_track_read');
  fd.append('nonce',   '<?php echo esc_js($track_nonce); ?>');
  fd.append('post_id', '<?php echo esc_js((string)$post_id); ?>');
  fetch(ajaxUrl, { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if(res.success && res.data && res.data.count){
        var el = document.getElementById('eiu-read-count');
        if(el) el.textContent = Number(res.data.count).toLocaleString();
      }
    })
    .catch(function(){});
}());

/* ── Inline Download Form ─────────────────────── */
var dlToggle  = document.getElementById('eiu-dl-toggle');
var dlPanel   = document.getElementById('eiu-dl-panel');
var dlCancel  = document.getElementById('eiu-dl-cancel');
var dlEmailIn = document.getElementById('eiu-dl-email');

if(dlToggle && dlPanel){
  dlToggle.addEventListener('click',function(){
    dlPanel.style.display = dlPanel.style.display==='none' ? 'block' : 'none';
    if(dlPanel.style.display==='block' && dlEmailIn){
      setTimeout(function(){ dlEmailIn.focus(); }, 80);
    }
  });
}
if(dlCancel && dlPanel){
  dlCancel.addEventListener('click',function(){
    dlPanel.style.display='none';
    var msgEl=document.getElementById('eiu-dl-msg');
    if(msgEl){msgEl.className='eiu-dl-msg';msgEl.textContent='';}
    var btn=document.getElementById('eiu-dl-btn');
    if(btn){btn.disabled=false;btn.innerHTML='<i class="bi bi-download me-1"></i><?php echo esc_js(__('Download','eiu-rp')); ?>';}
  });
}

var dlBtn = document.getElementById('eiu-dl-btn');
if(dlBtn){
  dlBtn.addEventListener('click',function(){
    var email = dlEmailIn ? dlEmailIn.value.trim() : '';
    var msgEl = document.getElementById('eiu-dl-msg');
    msgEl.className='eiu-dl-msg'; msgEl.textContent='';
    if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
      msgEl.textContent='<?php echo esc_js(__('Please enter a valid email address.','eiu-rp')); ?>';
      msgEl.classList.add('err'); return;
    }
    dlBtn.disabled=true;
    dlBtn.innerHTML='<span class="spinner-border spinner-border-sm" style="width:13px;height:13px;border-width:2px;"></span>';
    var fd=new FormData();
    fd.append('action','eiu_rp_download_request');
    fd.append('email',email);
    fd.append('post_id',dlBtn.dataset.post);
    fd.append('nonce',dlBtn.dataset.nonce);
    fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
      if(res.success){
        msgEl.textContent='<?php echo esc_js(__('Preparing your download…','eiu-rp')); ?>';
        msgEl.classList.add('ok');
        if(res.data && res.data.url){
          var safeUrl=res.data.url;
          try{
            var parsed=new URL(safeUrl,window.location.origin);
            if(parsed.origin!==window.location.origin) safeUrl=null;
          }catch(e){safeUrl=null;}
          if(safeUrl){
            /* Use a hidden <a download> so the browser shows a proper download
               indicator and we can update the message once the link is triggered */
            var a=document.createElement('a');
            a.href=safeUrl; a.download=''; a.style.display='none';
            document.body.appendChild(a);
            setTimeout(function(){
              a.click();
              document.body.removeChild(a);
              msgEl.textContent='<?php echo esc_js(__('Download complete ✓','eiu-rp')); ?>';
              msgEl.className='eiu-dl-msg ok';
            },400);
          }
        }
        // Re-enable button
        dlBtn.disabled=false;
        dlBtn.innerHTML='<i class="bi bi-download me-1"></i><?php echo esc_js(__('Download','eiu-rp')); ?>';
      } else {
        msgEl.textContent=(res.data&&res.data.message)||'<?php echo esc_js(__('Error. Please try again.','eiu-rp')); ?>';
        msgEl.classList.add('err');
        dlBtn.disabled=false;
        dlBtn.innerHTML='<i class="bi bi-download me-1"></i><?php echo esc_js(__('Download','eiu-rp')); ?>';
      }
    }).catch(function(){
      msgEl.textContent='<?php echo esc_js(__('Network error. Please try again.','eiu-rp')); ?>';
      msgEl.classList.add('err');
      dlBtn.disabled=false;
      dlBtn.innerHTML='<i class="bi bi-download me-1"></i><?php echo esc_js(__('Download','eiu-rp')); ?>';
    });
  });
  // Allow Enter key in email input to trigger download
  if(dlEmailIn){
    dlEmailIn.addEventListener('keydown',function(e){
      if(e.key==='Enter') dlBtn.click();
    });
  }
}

/* ── Post comment ────────────────────────────────────── */
var postBtn = document.getElementById('eiu-post-btn');
if(postBtn){
  postBtn.addEventListener('click',function(){
    var name  = (document.getElementById('eiu-c-name')||{}).value||'';
    var email = (document.getElementById('eiu-c-email')||{}).value||'';
    var text  = (document.getElementById('eiu-c-text')||{}).value||'';
    var resp  = document.getElementById('eiu-comment-resp');
    if(!name||!email||!text){
      resp.style.display='block'; resp.style.color='var(--c-red)';
      resp.innerHTML='<i class="bi bi-exclamation-circle me-1"></i><?php echo esc_js(__('Please fill in all fields.','eiu-rp')); ?>';
      return;
    }
    postBtn.disabled=true;
    var fd=new FormData();
    fd.append('action','eiu_rp_post_comment');
    fd.append('post_id',postBtn.dataset.post);
    fd.append('nonce',postBtn.dataset.nonce);
    fd.append('comment_author',name);
    fd.append('comment_author_email',email);
    fd.append('comment_content',text);
    fetch(ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
      resp.style.display='block';
      if(res.success){
        resp.style.color='#166534';
        resp.innerHTML='<i class="bi bi-check-circle-fill me-1"></i>'+res.data.message;
        document.getElementById('eiu-c-name').value='';
        document.getElementById('eiu-c-email').value='';
        document.getElementById('eiu-c-text').value='';
        setTimeout(function(){location.reload();},1500);
      } else {
        resp.style.color='var(--c-red)';
        resp.innerHTML='<i class="bi bi-exclamation-circle me-1"></i>'+((res.data&&res.data.message)?res.data.message:'<?php echo esc_js(__('Could not post comment.','eiu-rp')); ?>');
        postBtn.disabled=false;
      }
    }).catch(function(){postBtn.disabled=false;});
  });
}
}());
</script>
