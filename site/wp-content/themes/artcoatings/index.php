<?php
/**
 * Generic fallback template
 */
get_header();
?>
<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="card-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article class="card">
						<a class="card__link" href="<?php the_permalink(); ?>">
							<?php ac_company_thumb(); ?>
							<div class="card__body">
								<h3 class="card__title"><?php the_title(); ?></h3>
								<p class="card__desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></p>
							</div>
						</a>
					</article>
				<?php endwhile; ?>
			</div>
			<div class="pagination"><?php echo paginate_links( array( 'prev_text' => 'Previous', 'next_text' => 'Next' ) ); ?></div>
		<?php else : ?>
			<p class="empty">No content yet.</p>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
