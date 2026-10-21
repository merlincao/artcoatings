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
		</nav>
	</div>
</header>

<main id="main" class="site-main">
