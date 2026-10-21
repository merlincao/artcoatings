<?php
/**
 * Taxonomy archive (ac_industry / ac_region / ac_news_cat)
 *
 * 以前这些分类页走 index.php 兜底，没有标题、没有说明、也没有导航，
 * 对访问者和抓取方都等于空壳。这里统一补上：术语说明 + 面包屑 + 相关分类 + 编辑政策。
 */
get_header();

$term     = get_queried_object();
$is_news  = is_tax( 'ac_news_cat' );
$is_reg   = is_tax( 'ac_region' );
$pt       = $is_news ? 'ac_news' : 'ac_company';

$siblings = get_terms( array(
	'taxonomy'   => $term->taxonomy,
	'hide_empty' => true,
	'number'     => 20,
) );
?>
<section class="page-hero">
	<div class="container">
		<p class="page-hero__eyebrow"><?php echo $is_news ? 'NEWS CATEGORY' : ( $is_reg ? 'REGION' : 'CATEGORY' ); ?></p>
		<h1 class="page-hero__title"><?php echo esc_html( $term->name ); ?></h1>
		<p class="page-hero__sub"><?php echo esc_html( ac_archive_description() ); ?></p>
	</div>
</section>

<?php if ( $siblings && ! is_wp_error( $siblings ) && count( $siblings ) > 1 ) : ?>
<section class="section section--tight">
	<div class="container">
		<div class="filter-block">
			<div class="filter-row">
				<span class="filter-label"><?php echo $is_reg ? 'Region' : 'Category'; ?></span>
				<div class="pill-row">
					<a class="pill" href="<?php echo esc_url( get_post_type_archive_link( $pt ) ); ?>">All</a>
					<?php foreach ( $siblings as $t ) : ?>
						<a class="pill <?php echo ( $t->term_id === $term->term_id ) ? 'pill--active' : ''; ?>" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?><span class="pill__count"><?php echo (int) $t->count; ?></span></a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<?php if ( $is_news ) : ?>
				<div class="news-list">
					<?php while ( have_posts() ) : the_post(); ?>
						<a class="news-item" href="<?php the_permalink(); ?>">
							<time class="news-item__date"><?php echo get_the_date( 'Y-m-d' ); ?></time>
							<div class="news-item__main">
								<h3 class="news-item__title"><?php the_title(); ?></h3>
								<p class="news-item__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
							</div>
						</a>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<div class="card-grid">
					<?php while ( have_posts() ) : the_post(); ?>
						<article class="card">
							<a class="card__link" href="<?php the_permalink(); ?>">
								<?php ac_company_thumb( 'ac-card' ); ?>
								<div class="card__body">
									<h3 class="card__title"><?php the_title(); ?></h3>
									<p class="card__meta">
										<?php echo esc_html( ac_term_names( 'ac_region' ) ); ?>
										<?php if ( ac_term_names( 'ac_industry' ) ) : ?> · <?php echo esc_html( ac_term_names( 'ac_industry' ) ); ?><?php endif; ?>
									</p>
									<p class="card__desc"><?php echo esc_html( ac_company_summary() ); ?></p>
								</div>
							</a>
						</article>
					<?php endwhile; ?>
				</div>
			<?php endif; ?>

			<div class="pagination"><?php echo paginate_links( array( 'prev_text' => 'Previous', 'next_text' => 'Next' ) ); ?></div>
		<?php else : ?>
			<p class="empty">Nothing published in this <?php echo $is_reg ? 'region' : 'category'; ?> yet.</p>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! $is_news ) { ac_render_directory_policy(); } ?>

<?php get_footer();
