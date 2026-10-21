<?php
/**
 * Header
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php ac_seo_meta_tags(); ?>
	<?php wp_head(); ?>
	<?php ac_json_ld(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main">Skip to main content</a>

<script>
/* 尽早应用已保存的语言，避免切换后出现英文闪一下才变目标语言（FOUC） */
(function () {
	try {
		var c = localStorage.getItem('ac_lang');
		if (c && c !== 'en') { document.documentElement.lang = c; }
	} catch (e) {}
})();
</script>

<header class="site-header" id="siteHeader">
	<div class="container site-header__inner">
		<a class="brand" href="<?php echo ac_home_url(); ?>" aria-label="Back to home">
			<span class="brand__mark">AC</span>
			<span class="brand__text">
				<strong>ARTCOATINGS</strong>
				<em>Art Coatings Directory · Sicily, Italy</em>
			</span>
		</a>

		<button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="primaryNav" aria-label="Open menu">
			<span></span><span></span><span></span>
		</button>

		<nav class="primary-nav" id="primaryNav" aria-label="Primary navigation">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav-list',
					'depth'          => 1,
				) );
			} else {
				?>
				<ul class="nav-list">
					<li><a href="<?php echo ac_home_url(); ?>">Home</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">Companies</a></li>
					<li><a href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">News</a></li>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a></li>
				</ul>
				<?php
			}
			?>
			<a class="btn btn--gold nav-cta" href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">List your company</a>

			<div class="lang-switch" id="langSwitch">
				<button class="lang-switch__btn" id="langSwitchBtn" type="button"
						aria-haspopup="listbox" aria-expanded="false" aria-label="Language">
					<svg class="lang-switch__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
						<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.6"/>
						<path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18" fill="none" stroke="currentColor" stroke-width="1.6"/>
					</svg>
					<span class="lang-switch__current">English</span>
					<svg class="lang-switch__caret" viewBox="0 0 12 12" width="10" height="10" aria-hidden="true" focusable="false">
						<path d="M2 4.5 6 8.5 10 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
					</svg>
				</button>
				<ul class="lang-switch__menu" role="listbox" aria-label="Language">
					<li><button type="button" role="option" data-lang="en" aria-selected="true">English</button></li>
					<li><button type="button" role="option" data-lang="fr" aria-selected="false">Français</button></li>
					<li><button type="button" role="option" data-lang="it" aria-selected="false">Italiano</button></li>
					<li><button type="button" role="option" data-lang="de" aria-selected="false">Deutsch</button></li>
					<li><button type="button" role="option" data-lang="pt" aria-selected="false">Português</button></li>
					<li><button type="button" role="option" data-lang="el" aria-selected="false">Ελληνικά</button></li>
				</ul>
			</div>
		</nav>
	</div>
</header>

<main id="main" class="site-main">
