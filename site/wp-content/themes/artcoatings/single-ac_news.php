<?php
/**
 * News detail
 */
get_header();

while ( have_posts() ) : the_post();
	$source = ac_meta( 'ac_source' );
?>
<article class="article">
	<div class="container container--narrow">
		<nav class="breadcrumb" aria-label="Breadcrumb">
			<a href="<?php echo ac_home_url(); ?>">Home</a>
			<span>/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">News</a>
			<span>/</span>
			<em><?php the_title(); ?></em>
		</nav>

		<header class="article__head">
			<div class="article__meta">
				<time><?php echo get_the_date( 'Y-m-d' ); ?></time>
				<?php if ( ac_term_names( 'ac_news_cat' ) ) : ?><span>· <?php echo esc_html( ac_term_names( 'ac_news_cat' ) ); ?></span><?php endif; ?>
				<?php if ( $source ) : ?><span>· Source: <?php echo esc_html( $source ); ?></span><?php endif; ?>
			</div>
			<h1 class="article__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="article__lead"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="article__cover"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>

		<div class="prose"><?php the_content(); ?></div>

		<footer class="article__foot">
			<a class="btn btn--ghost-dark" href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">&larr; Back to news</a>
		</footer>
	</div>
</article>
<?php endwhile; ?>

<?php get_footer(); ?>
