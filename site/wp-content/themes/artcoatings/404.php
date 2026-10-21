<?php
/**
 * 404
 */
get_header();
?>
<section class="section section--error">
	<div class="container">
		<p class="error-code">404</p>
		<h1 class="error-title">Page not found</h1>
		<p class="error-sub">The page you are looking for may have been moved or removed.</p>
		<a class="btn btn--gold" href="<?php echo ac_home_url(); ?>">Back to home</a>
	</div>
</section>
<?php get_footer(); ?>
