<?php
/**
 * Company directory (archive)
 */
get_header();

$industries = get_terms( array( 'taxonomy' => 'ac_industry', 'hide_empty' => true ) );
$regions    = get_terms( array( 'taxonomy' => 'ac_region', 'hide_empty' => true ) );
?>

<section class="page-hero">
	<div class="container">
		<p class="page-hero__eyebrow">DIRECTORY</p>
		<h1 class="page-hero__title">Company Directory</h1>
		<p class="page-hero__sub" data-i18n="dir.sub">Search art coatings companies by category and region — find the right brands, materials and contractors quickly.</p>
	</div>
</section>

<section class="section section--tight">
	<div class="container">
		<div class="filter-block">
			<?php if ( $industries && ! is_wp_error( $industries ) ) : ?>
				<div class="filter-row">
					<span class="filter-label">Category</span>
					<div class="pill-row">
						<a class="pill pill--active" href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">All</a>
						<?php foreach ( $industries as $term ) : ?>
							<a class="pill" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?><span class="pill__count"><?php echo (int) $term->count; ?></span></a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $regions && ! is_wp_error( $regions ) ) : ?>
				<div class="filter-row">
					<span class="filter-label">Region</span>
					<div class="pill-row">
						<?php foreach ( $regions as $term ) : ?>
							<a class="pill" href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?><span class="pill__count"><?php echo (int) $term->count; ?></span></a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
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

			<div class="pagination">
				<?php echo paginate_links( array( 'prev_text' => 'Previous', 'next_text' => 'Next' ) ); ?>
			</div>
		<?php else : ?>
			<p class="empty">No companies in this category yet.</p>
		<?php endif; ?>
	</div>
</section>

<?php ac_render_directory_policy(); ?>

<?php get_footer(); ?>
