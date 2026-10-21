<?php
/**
 * ArtCoatings 主题函数
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 主题基础能力
 */
function ac_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary' => '主导航',
		'footer'  => '页脚导航',
	) );

	// 企业封面尺寸
	add_image_size( 'ac-card', 640, 400, true );
	add_image_size( 'ac-hero', 1600, 700, true );
}
add_action( 'after_setup_theme', 'ac_theme_setup' );

/**
 * 资源入队
 */
function ac_enqueue_assets() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'ac-tokens', $uri . '/assets/css/tokens.css', array(), filemtime( $dir . '/assets/css/tokens.css' ) );
	wp_enqueue_style( 'ac-main', $uri . '/assets/css/main.css', array( 'ac-tokens' ), filemtime( $dir . '/assets/css/main.css' ) );
	wp_enqueue_script( 'ac-main', $uri . '/assets/js/main.js', array(), filemtime( $dir . '/assets/js/main.js' ), true );

	// 语言切换（客户端 i18n，静态站无后端）
	wp_enqueue_script( 'ac-i18n', $uri . '/assets/js/i18n.js', array(), filemtime( $dir . '/assets/js/i18n.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'ac_enqueue_assets' );

/**
 * 读取企业字段
 */
function ac_meta( $key, $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	return get_post_meta( $post_id, $key, true );
}

/**
 * 把 meta 值归一化成「正整数 ID 数组」。
 *
 * 同一个字段在库里可能有两种形态：
 *   - 逗号/空格分隔的字符串（导入脚本写入）
 *   - PHP 数组（挂图脚本早期版本写入）
 * 调用方一律走这里，杜绝 explode() 收到数组导致的致命错误（整页 500）。
 *
 * @param mixed $value
 * @return int[]
 */
function ac_parse_id_list( $value ) {
	if ( empty( $value ) ) {
		return array();
	}
	if ( is_array( $value ) ) {
		$raw = $value;
	} else {
		$raw = preg_split( '/[,\s|]+/', (string) $value );
	}
	$ids = array();
	foreach ( $raw as $v ) {
		if ( is_numeric( $v ) ) {
			$ids[] = (int) $v;
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * 企业 Logo / 封面（优先特色图片）
 */
function ac_company_thumb( $size = 'ac-card', $class = 'ac-card__media' ) {
	if ( has_post_thumbnail() ) {
		echo '<div class="' . esc_attr( $class ) . '">';
		the_post_thumbnail( $size, array( 'loading' => 'lazy' ) );
		echo '</div>';
	} else {
		$initial = mb_strtoupper( mb_substr( get_the_title(), 0, 1 ) );
		echo '<div class="' . esc_attr( $class ) . ' ac-card__media--empty"><span class="monogram">' . esc_html( $initial ) . '</span></div>';
	}
}

/**
 * 摘要：优先自定义一句话简介，其次 excerpt
 */
function ac_company_summary( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$s = ac_meta( 'ac_summary', $post_id );
	if ( ! $s ) {
		$s = get_the_excerpt( $post_id );
	}
	return wp_trim_words( wp_strip_all_tags( $s ), 40, '…' );
}

/**
 * 企业所属行业/地区名称
 */
function ac_term_names( $tax, $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$terms   = get_the_terms( $post_id, $tax );
	if ( $terms && ! is_wp_error( $terms ) ) {
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
	return '';
}

/**
 * 首页跳转锚点用：首页 URL
 */
function ac_home_url() {
	return esc_url( home_url( '/' ) );
}

/**
 * 站点统一对外信息（国际化 / 西西里定位）
 */
function ac_site_email() {
	return 'xzmcart@hotmail.com';
}

function ac_site_location() {
	return 'Sicily, Italy';
}

/**
 * 输出 SEO / Open Graph / Twitter Card 标签 + 规范链接
 * 放在 header 的 <head> 内调用。
 */
/**
 * 当前页面的规范 URL。
 *
 * 不要用 $_SERVER['REQUEST_URI'] 拼：中文/百分号 slug 经 esc_url() 会被剥成
 * "region/-" 之类的残串，canonical 与 og:url 同时坏掉。这里一律走 WordPress
 * 自己的规范 URL API，它们返回的已经是正确编码的绝对地址。
 */
function ac_canonical_url() {
	if ( is_singular() ) {
		return get_permalink();
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
	}
	if ( is_post_type_archive() ) {
		return get_post_type_archive_link( get_post_type() );
	}
	if ( is_front_page() || is_home() ) {
		return home_url( '/' );
	}
	// 兜底：用 WP 解析出的请求路径（不含查询串），比 REQUEST_URI 干净
	global $wp;
	$req = ( isset( $wp->request ) && $wp->request !== '' ) ? '/' . $wp->request . '/' : '/';
	return home_url( $req );
}

function ac_seo_meta_tags() {
	$title       = wp_get_document_title();
	$description = '';
	$og_type     = 'website';
	$og_url      = esc_url( ac_canonical_url() );

	if ( is_singular( 'ac_company' ) ) {
		$og_type     = 'article';
		$description = ac_company_summary();
	} elseif ( is_singular() ) {
		$og_type     = 'article';
		$description = wp_trim_words( get_the_excerpt(), 30, '…' );
	}

	if ( ! $description ) {
		$description = 'ArtCoatings — an independent directory of art coatings brands and manufacturers, based in Sicily, Italy. Browse verified companies by category and region.';
	}

	$site_name = get_bloginfo( 'name' );

	echo "\n<!-- SEO / Open Graph -->\n";
	echo '<link rel="canonical" href="' . $og_url . '" />' . "\n";
	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . $og_url . '" />' . "\n";
	echo '<meta property="og:locale" content="en_US" />' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta name="robots" content="index, follow, max-image-preview:large" />' . "\n";
}

/**
 * 输出结构化数据 JSON-LD（权威度 / 可信任度）
 * 首页：Organization；企业页：LocalBusiness（+ sameAs X 链接）
 */
function ac_json_ld() {
	$data = array();

	if ( is_singular( 'ac_company' ) ) {
		$website = ac_meta( 'ac_website' );
		$phone   = ac_meta( 'ac_phone' );
		$address = ac_meta( 'ac_address' );
		$x_link  = ac_meta( 'ac_x_link' );

		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'LocalBusiness',
			'name'        => get_the_title(),
			'url'         => $website ? esc_url( $website ) : get_permalink(),
			'description' => ac_company_summary(),
			'address'     => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => 'Sicily',
				'addressCountry'  => 'IT',
				'streetAddress'   => $address ? $address : '',
			),
			'areaServed'   => 'Italy',
		);
		if ( $phone ) { $data['telephone'] = $phone; }
		if ( $x_link ) { $data['sameAs'] = array( esc_url( $x_link ) ); }
	} else {
		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Organization',
			'name'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'email'       => ac_site_email(),
			'address'     => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => 'Sicily',
				'addressCountry'  => 'IT',
			),
			'description' => 'ArtCoatings is an independent directory of art coatings brands and manufacturers, based in Sicily, Italy.',
		);
	}

	echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n</script>\n";
}
