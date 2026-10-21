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

		<?php
		$src_url  = ac_news_source_url();
		$conf     = ac_news_confidence();
		?>
		<section class="panel panel--note">
			<h2 class="panel__title">Source &amp; editorial note</h2>
			<dl class="note-list">
				<div class="note-list__row">
					<dt data-i18n="Source">Source</dt>
					<dd><?php echo $source ? esc_html( $source ) : 'Not stated'; ?></dd>
				</div>
				<div class="note-list__row">
					<dt data-i18n="Source reliability">Source reliability</dt>
					<dd><?php echo $conf ? esc_html( $conf ) : 'Not assessed'; ?></dd>
				</div>
				<div class="note-list__row">
					<dt data-i18n="Last reviewed">Last reviewed</dt>
					<dd><time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'Y-m-d' ) ); ?></time></dd>
				</div>
			</dl>
			<?php if ( $src_url ) : ?>
				<p class="note-text">
					<a class="note-link" href="<?php echo esc_url( $src_url ); ?>" target="_blank" rel="nofollow noopener" data-i18n="Read the original report">Read the original report</a>
					<span class="note-text__muted"> — external site, in the source publisher's own words.</span>
				</p>
			<?php endif; ?>
			<p class="note-text note-text__muted">
				This article is a summary prepared by the ArtCoatings editorial desk from the source named above. It is second-hand reporting: figures, dates and quotations are reproduced as published by the source and have not been independently verified unless stated in the text.
			</p>
		</section>

		<footer class="article__foot">
			<a class="btn btn--ghost-dark" href="<?php echo esc_url( get_post_type_archive_link( 'ac_news' ) ); ?>">&larr; Back to news</a>
		</footer>
	</div>
</article>
<?php endwhile; ?>

<?php get_footer(); ?>
