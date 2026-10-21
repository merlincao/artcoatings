<?php
/**
 * Front page
 */
get_header();

$industries = get_terms( array( 'taxonomy' => 'ac_industry', 'hide_empty' => false, 'number' => 10 ) );
$companies  = new WP_Query( array( 'post_type' => 'ac_company', 'posts_per_page' => 8, 'orderby' => 'modified', 'order' => 'DESC' ) );
$news       = new WP_Query( array( 'post_type' => 'ac_news', 'posts_per_page' => 6 ) );
?>

<section class="hero">
	<div class="container hero__inner">
		<p class="hero__eyebrow">ART COATINGS DIRECTORY</p>
		<h1 class="hero__title" data-i18n="hero.title">Discover trusted<br>art coatings companies</h1>
		<p class="hero__sub" data-i18n="hero.sub">A Sicily-based, independent directory of decorative coatings, mineral &amp; lime plasters, microcement and metallic systems — connecting brands, manufacturers and specifiers across Italy and Europe.</p>
		<div class="hero__actions">
			<a class="btn btn--gold" href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">Browse the directory</a>
			<a class="btn btn--ghost" href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">Industry news</a>
		</div>
		<div class="hero__stats">
			<div><strong><?php echo (int) wp_count_posts( 'ac_company' )->publish; ?></strong><span>Companies</span></div>
			<div><strong><?php echo (int) wp_count_posts( 'ac_news' )->publish; ?></strong><span>News</span></div>
			<div><strong><?php echo $industries && ! is_wp_error( $industries ) ? count( $industries ) : 0; ?></strong><span>Categories</span></div>
		</div>
	</div>
</section>

<?php if ( $industries && ! is_wp_error( $industries ) ) : ?>
<section class="section section--tight">
	<div class="container">
		<div class="section__head">
			<h2 class="section__title">Browse by category</h2>
			<a class="section__more" href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">All companies &rarr;</a>
		</div>
		<div class="pill-row">
			<?php foreach ( $industries as $term ) : ?>
				<a class="pill" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?><span class="pill__count"><?php echo (int) $term->count; ?></span></a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section">
	<div class="container">
		<div class="section__head">
			<h2 class="section__title">Featured companies</h2>
			<a class="section__more" href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">View all &rarr;</a>
		</div>
		<div class="card-grid">
			<?php if ( $companies->have_posts() ) : while ( $companies->have_posts() ) : $companies->the_post(); ?>
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
			<?php endwhile; wp_reset_postdata(); else : ?>
				<p class="empty">No companies yet. Add your first entry from the dashboard.</p>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section section--muted">
	<div class="container">
		<div class="section__head">
			<h2 class="section__title">Industry news</h2>
			<a class="section__more" href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">More news &rarr;</a>
		</div>
		<div class="news-list">
			<?php if ( $news->have_posts() ) : while ( $news->have_posts() ) : $news->the_post(); ?>
				<a class="news-item" href="<?php the_permalink(); ?>">
					<time class="news-item__date"><?php echo get_the_date( 'm.d' ); ?></time>
					<div class="news-item__main">
						<h3 class="news-item__title"><?php the_title(); ?></h3>
						<p class="news-item__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
					</div>
				</a>
			<?php endwhile; wp_reset_postdata(); else : ?>
				<p class="empty">No news yet. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ac_news' ) ); ?>">Publish the first article</a></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php get_footer(); ?>
