</main><!-- #main -->

<footer class="site-footer">
	<div class="container site-footer__inner">
		<div class="footer-col footer-brand">
			<span class="brand__text">
				<strong>ARTCOATINGS</strong>
				<em>Art Coatings Directory · Sicily, Italy</em>
			</span>
			<p data-i18n="footer.about">An independent directory of art coatings brands, manufacturers and suppliers — covering decorative, mineral & lime, microcement and metallic systems across Italy and Europe.</p>
		</div>
		<div class="footer-col">
			<h4>Quick links</h4>
			<ul>
				<li><a href="<?php echo ac_home_url(); ?>">Home</a></li>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">Companies</a></li>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">News</a></li>
				<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
			</ul>
		</div>
		<div class="footer-col">
			<h4>Contact</h4>
			<ul>
				<li>Email: <a href="mailto:<?php echo esc_attr( ac_site_email() ); ?>"><?php echo esc_html( ac_site_email() ); ?></a></li>
				<li>Based in Sicily, Italy</li>
			</ul>
		</div>
	</div>
	<div class="footer-bottom">
		<div class="container">
			<span>© <?php echo date( 'Y' ); ?> artcoatings.org · Art Coatings Directory</span>
			<p class="footer-disclaimer" data-i18n="footer.disclaimer">ArtCoatings is an independent reference directory. Listings are informational and include only publicly verifiable details; we are not affiliated with any listed brand unless explicitly stated.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
