<?php
/**
 * Company detail
 */
get_header();

while ( have_posts() ) : the_post();
	$website  = ac_meta( 'ac_website' );
	$phone    = ac_meta( 'ac_phone' );
	$contact  = ac_meta( 'ac_contact' );
	$address  = ac_meta( 'ac_address' );
	$founded  = ac_meta( 'ac_founded' );
	$products = ac_meta( 'ac_main_products' );
	$summary  = ac_meta( 'ac_summary' );
	$x_link   = ac_meta( 'ac_x_link' );
	$regions  = ac_meta( 'ac_service_regions' );
	$certs    = ac_meta( 'ac_certificates' );
?>

<article class="company">
	<div class="container">
		<nav class="breadcrumb" aria-label="Breadcrumb">
			<a href="<?php echo ac_home_url(); ?>">Home</a>
			<span>/</span>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'ac_company' ) ); ?>">Companies</a>
			<span>/</span>
			<em><?php the_title(); ?></em>
		</nav>

		<header class="company__head">
			<div class="company__logo">
				<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium' ); } else { $initial = mb_strtoupper( mb_substr( get_the_title(), 0, 1 ) ); echo '<span class="monogram monogram--lg">' . esc_html( $initial ) . '</span>'; } ?>
			</div>
			<div class="company__intro">
				<h1 class="company__name"><?php the_title(); ?></h1>
				<div class="company__tags">
					<?php if ( ac_term_names( 'ac_industry' ) ) : ?><span class="tag"><?php echo esc_html( ac_term_names( 'ac_industry' ) ); ?></span><?php endif; ?>
					<?php if ( ac_term_names( 'ac_region' ) ) : ?><span class="tag tag--ghost"><?php echo esc_html( ac_term_names( 'ac_region' ) ); ?></span><?php endif; ?>
				</div>
				<?php if ( $summary ) : ?><p class="company__summary"><?php echo esc_html( $summary ); ?></p><?php endif; ?>
				<div class="company__actions">
					<?php if ( $website ) : ?>
						<a class="btn btn--gold" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="nofollow noopener">Visit website &#8599;</a>
					<?php endif; ?>
					<?php if ( $x_link ) : ?>
						<a class="btn btn--ghost" href="<?php echo esc_url( $x_link ); ?>" target="_blank" rel="nofollow noopener">X (Twitter)</a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<a class="btn btn--ghost-dark" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<div class="company__grid">
			<div class="company__main">
				<?php if ( get_the_content() ) : ?>
					<section class="panel">
						<h2 class="panel__title">About</h2>
						<div class="prose"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( $products ) : ?>
					<section class="panel">
						<h2 class="panel__title">Main products</h2>
						<p class="panel__text"><?php echo nl2br( esc_html( $products ) ); ?></p>
					</section>
				<?php endif; ?>

				<?php if ( $regions ) : ?>
					<section class="panel">
						<h2 class="panel__title">Service regions</h2>
						<p class="panel__text"><?php echo esc_html( $regions ); ?></p>
					</section>
				<?php endif; ?>

				<?php if ( $certs ) : ?>
					<section class="panel">
						<h2 class="panel__title">Certifications<?php if ( ac_meta( 'ac_cert_unverified' ) ) : ?> <span class="badge-unverified">Unverified</span><?php endif; ?></h2>
						<p class="panel__text"><?php echo nl2br( esc_html( $certs ) ); ?></p>
						<?php if ( ac_meta( 'ac_cert_unverified' ) ) : ?><p class="cert-note" data-i18n="cert.note">Based on publicly available sources and not independently confirmed. Contact the company to verify.</p><?php endif; ?>
					</section>
				<?php endif; ?>

				<?php
				$gallery = ac_meta( 'ac_gallery' );
				// ac_gallery 历史上两种写法都出现过：逗号分隔的字符串（导入脚本）和数组（挂图脚本）。
				// 统一在这里归一化，避免 explode() 接到数组直接抛 TypeError 让整页 500。
				$gids = ac_parse_id_list( $gallery );
				if ( $gids ) :
				?>
					<section class="panel">
						<h2 class="panel__title">Gallery</h2>
						<div class="gallery-grid">
							<?php foreach ( $gids as $gid ) : ?>
								<div class="gallery-item"><?php echo wp_get_attachment_image( (int) $gid, 'ac-hero' ); ?></div>
							<?php endforeach; ?>
						</div>
					</section>
				<?php
					endif;
				?>
			</div>

			<aside class="company__side">
				<div class="panel panel--contact">
					<h2 class="panel__title">Contact information</h2>
					<ul class="contact-list">
						<?php if ( $contact ) : ?><li><span>Contact person</span><strong><?php echo esc_html( $contact ); ?></strong></li><?php endif; ?>
						<?php if ( $phone ) : ?><li><span>Phone</span><strong><?php echo esc_html( $phone ); ?></strong></li><?php endif; ?>
						<?php if ( $address ) : ?><li><span>Address</span><strong><?php echo esc_html( $address ); ?></strong></li><?php endif; ?>
						<?php if ( $founded ) : ?><li><span>Founded</span><strong><?php echo esc_html( $founded ); ?></strong></li><?php endif; ?>
						<?php if ( $website ) : ?><li><span>Website</span><strong><a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html( wp_parse_url( $website, PHP_URL_HOST ) ); ?></a></strong></li><?php endif; ?>
						<?php if ( $x_link ) : ?><li><span>X (Twitter)</span><strong><a href="<?php echo esc_url( $x_link ); ?>" target="_blank" rel="nofollow noopener">Profile</a></strong></li><?php endif; ?>
					</ul>
				</div>
			</aside>
		</div>
	</div>
</article>

<?php endwhile; ?>

<?php get_footer(); ?>
