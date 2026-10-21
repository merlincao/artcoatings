<?php
/**
 * 普通页面
 */
get_header();

while ( have_posts() ) : the_post();
?>
<article class="page">
	<div class="container container--narrow">
		<header class="page__head">
			<h1 class="page__title"><?php the_title(); ?></h1>
		</header>
		<div class="prose"><?php the_content(); ?></div>
	</div>
</article>
<?php endwhile; ?>

<?php get_footer(); ?>
