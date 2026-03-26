<?php
/**
 * Article Categories Shortcode Template.
 * Shortcode: [eiu_article_categories style="pills|cards|list" columns="3"]
 *
 * @package EIU_Research_Publication
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$style   = in_array( $style, array('pills','cards','list'), true ) ? $style : 'pills';
$columns = max(1, min(6, absint($columns)));

$listing_url = get_option('eiu_rp_listing_page_id') ? get_permalink(get_option('eiu_rp_listing_page_id')) : home_url();

// Get subjects with counts
$terms = get_terms( array( 'taxonomy' => 'eiu_subject', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
if ( is_wp_error( $terms ) || empty( $terms ) ) { return; }
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.eiu-cat-wrap { --brand-red:#990000; --brand-blue:#1a4988; --brand-blue-l:#e8eef8; }

/* Pills style */
.eiu-cat-pills { display: flex; flex-wrap: wrap; gap: 10px; }
.eiu-cat-pill {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--brand-blue-l); color: var(--brand-blue);
  padding: 7px 16px; border-radius: 24px; font-size: 13px; font-weight: 600;
  text-decoration: none; border: 1.5px solid transparent; transition: all .15s;
}
.eiu-cat-pill:hover { background: var(--brand-blue); color: #fff; text-decoration: none; border-color: var(--brand-blue); }
.eiu-cat-pill .count { background: rgba(26,73,136,.12); color: var(--brand-blue); padding: 1px 7px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.eiu-cat-pill:hover .count { background: rgba(255,255,255,.25); color: #fff; }

/* Cards style */
.eiu-cat-grid { display: grid; grid-template-columns: repeat(<?php echo $columns; ?>, 1fr); gap: 14px; }
.eiu-cat-card {
  background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
  padding: 20px; text-align: center; text-decoration: none;
  transition: all .2s; box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.eiu-cat-card:hover { background: var(--brand-blue); border-color: var(--brand-blue); box-shadow: 0 6px 20px rgba(26,73,136,.2); transform: translateY(-2px); text-decoration: none; }
.eiu-cat-card .icon { font-size: 1.8rem; color: var(--brand-blue); margin-bottom: 8px; display: block; transition: color .2s; }
.eiu-cat-card:hover .icon { color: rgba(255,255,255,.85); }
.eiu-cat-card .name { font-size: 13px; font-weight: 700; color: #111827; transition: color .2s; display: block; }
.eiu-cat-card:hover .name { color: #fff; }
.eiu-cat-card .count { font-size: 11px; color: #6b7280; margin-top: 4px; display: block; transition: color .2s; }
.eiu-cat-card:hover .count { color: rgba(255,255,255,.75); }

/* List style */
.eiu-cat-list { list-style: none; margin: 0; padding: 0; }
.eiu-cat-list li { border-bottom: 1px solid #f3f4f6; }
.eiu-cat-list li:last-child { border-bottom: none; }
.eiu-cat-list a { display: flex; align-items: center; justify-content: space-between; padding: 11px 4px; text-decoration: none; color: #374151; font-size: 14px; font-weight: 500; transition: color .13s; }
.eiu-cat-list a:hover { color: var(--brand-blue); text-decoration: none; }
.eiu-cat-list a span.badge-count { background: var(--brand-blue-l); color: var(--brand-blue); font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 20px; }

@media (max-width: 640px) { .eiu-cat-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="eiu-cat-wrap">
  <?php if ( $style === 'pills' ): ?>
    <div class="eiu-cat-pills">
      <?php foreach ( $terms as $term ):
        $url = add_query_arg( 'eiu_subject', urlencode( $term->slug ), $listing_url );
      ?>
        <a href="<?php echo esc_url( $url ); ?>" class="eiu-cat-pill">
          <i class="bi bi-folder2"></i>
          <?php echo esc_html( $term->name ); ?>
          <span class="count"><?php echo esc_html( $term->count ); ?></span>
        </a>
      <?php endforeach; ?>
    </div>

  <?php elseif ( $style === 'cards' ): ?>
    <div class="eiu-cat-grid">
      <?php foreach ( $terms as $term ):
        $url = add_query_arg( 'eiu_subject', urlencode( $term->slug ), $listing_url );
      ?>
        <a href="<?php echo esc_url( $url ); ?>" class="eiu-cat-card">
          <i class="bi bi-journal-bookmark-fill icon"></i>
          <span class="name"><?php echo esc_html( $term->name ); ?></span>
          <span class="count"><?php echo esc_html( number_format_i18n( $term->count ) ); ?> <?php esc_html_e( 'articles', 'eiu-rp' ); ?></span>
        </a>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <ul class="eiu-cat-list">
      <?php foreach ( $terms as $term ):
        $url = add_query_arg( 'eiu_subject', urlencode( $term->slug ), $listing_url );
      ?>
        <li>
          <a href="<?php echo esc_url( $url ); ?>">
            <span><i class="bi bi-chevron-right me-2" style="font-size:11px;color:#9ca3af;"></i><?php echo esc_html( $term->name ); ?></span>
            <span class="badge-count"><?php echo esc_html( $term->count ); ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
