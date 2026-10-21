<?php
/**
 * News archive
 */
get_header();

$cats = get_terms( array( 'taxonomy' => 'ac_news_cat', 'hide_empty' => true, 'number' => 20 ) );
?>
<section class="page-hero">
	<div class="container">
		<p class="page-hero__eyebrow">NEWS</p>
		<h1 class="page-hero__title">Industry News</h1>
		<p class="page-hero__sub">Reporting on the decorative and art coatings industry — company moves, colour and material trends, standards and regulation. Every item names its original source.</p>
	</div>
</section>

<?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
<section class="section section--tight">
	<div class="container">
		<div class="filter-block">
			<div class="filter-row">
				<span class="filter-label">Category</span>
				<div class="pill-row">
					<a class="pill pill--active" href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">All</a>
					<?php foreach ( $cats as $t ) : ?>
						<a class="pill" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?><span class="pill__count"><?php echo (int) $t->count; ?></span></a>
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
			<div class="news-list">
				<?php while ( have_posts() ) : the_post();
					$src  = ac_meta( 'ac_source' );
					$conf = ac_news_confidence();
				?>
					<a class="news-item" href="<?php the_permalink(); ?>">
						<time class="news-item__date"><?php echo get_the_date( 'Y-m-d' ); ?></time>
						<div class="news-item__main">
							<h3 class="news-item__title"><?php the_title(); ?></h3>
							<p class="news-item__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
							<?php if ( $src || $conf ) : ?>
								<p class="news-item__byline">
									<?php if ( $src ) : ?>Source: <?php echo esc_html( $src ); ?><?php endif; ?>
									<?php if ( $conf ) : ?> · Reliability: <?php echo esc_html( $conf ); ?><?php endif; ?>
								</p>
							<?php endif; ?>
						</div>
					</a>
				<?php endwhile; ?>
			</div>
			<div class="pagination"><?php echo paginate_links( array( 'prev_text' => 'Previous', 'next_text' => 'Next' ) ); ?></div>
		<?php else : ?>
			<p class="empty">No articles yet. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ac_news' ) ); ?>">Publish the first article</a></p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer();
