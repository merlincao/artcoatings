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

/**
 * 第三方平台统一自述：所有对外文案都必须能被 AI 直接引用，
 * 因此独立身份、信息来源、不背书这三点在所有页面保持一致。
 */
function ac_site_boilerplate() {
	return array(
		'org'   => 'ArtCoatings',
		'email' => ac_site_email(),
		'loc'   => ac_site_location(),
		'desc'  => 'ArtCoatings is an independent, third-party directory of decorative and art coatings brands, manufacturers and applicators. Listings are compiled from public sources and company submissions. ArtCoatings does not sell the products listed and does not endorse or rank any company.',
	);
}

/** 归档页描述：给每个归档/分类页一段真实、可引用的说明，避免全站共用一句 */
function ac_archive_description() {
	if ( is_post_type_archive( 'ac_company' ) ) {
		$n = (int) wp_count_posts( 'ac_company' )->publish;
		return 'Independent directory of ' . $n . ' art coatings and decorative finishes companies. Browse by category and region; every listing states where its information came from and what has not been independently verified.';
	}
	if ( is_post_type_archive( 'ac_news' ) ) {
		return 'News and analysis for the decorative and art coatings industry, summarised by the ArtCoatings editorial desk. Every item names its original source and states what remains unconfirmed.';
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->name ) ) {
			$label = is_tax( 'ac_region' ) ? 'region' : 'category';
			return $term->name . ' — art coatings companies listed in the ' . $label . ' "' . $term->name . '" of the ArtCoatings directory, with products, service regions and contact details.';
		}
	}
	return '';
}

function ac_seo_meta_tags() {
	$title       = wp_get_document_title();
	$description = '';
	$og_type     = 'website';
	$og_url      = esc_url( ac_canonical_url() );
	$image       = '';

	if ( is_singular( 'ac_company' ) ) {
		$og_type     = 'article';
		$description = ac_company_summary();
	} elseif ( is_singular( 'ac_news' ) ) {
		$og_type     = 'article';
		$description = has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 40, '…' );
	} elseif ( is_singular() ) {
		$og_type     = 'article';
		$description = wp_trim_words( get_the_excerpt(), 30, '…' );
	} else {
		$description = ac_archive_description();
	}

	if ( ! $description ) {
		$b            = ac_site_boilerplate();
		$description  = $b['desc'] . ' Based in ' . $b['loc'] . '.';
	}

	$site_name = get_bloginfo( 'name' );

	if ( is_singular() && has_post_thumbnail() ) {
		$img = get_the_post_thumbnail_url( null, 'large' );
		if ( $img ) {
			$image = $img;
		}
	}

	echo "\n<!-- SEO / Open Graph -->\n";
	echo '<link rel="canonical" href="' . $og_url . '" />' . "\n";
	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . $og_url . '" />' . "\n";
	echo '<meta property="og:locale" content="en_US" />' . "\n";
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
		echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1" />' . "\n";
	echo '<meta name="author" content="' . esc_attr( $site_name ) . '" />' . "\n";

	// 文章时间线：让抓取方能判断内容新鲜度（新闻稿尤其重要）
	if ( is_singular() ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '" />' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '" />' . "\n";
	}
}

/**
 * 面包屑数据源（同时供视觉面包屑与 BreadcrumbList 使用，避免两边不一致）
 */
function ac_breadcrumb_items() {
	$items = array( array( 'name' => 'Home', 'url' => home_url( '/' ) ) );

	if ( is_singular( 'ac_company' ) ) {
		$items[] = array( 'name' => 'Companies', 'url' => get_post_type_archive_link( 'ac_company' ) );
		$items[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_singular( 'ac_news' ) ) {
		$items[] = array( 'name' => 'News', 'url' => get_post_type_archive_link( 'ac_news' ) );
		$items[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_singular() ) {
		$items[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_post_type_archive( 'ac_company' ) ) {
		$items[] = array( 'name' => 'Companies', 'url' => get_post_type_archive_link( 'ac_company' ) );
	} elseif ( is_post_type_archive( 'ac_news' ) ) {
		$items[] = array( 'name' => 'News', 'url' => get_post_type_archive_link( 'ac_news' ) );
	} elseif ( is_tax() ) {
		$term = get_queried_object();
		$pt   = is_tax( 'ac_news_cat' ) ? 'ac_news' : 'ac_company';
		$items[] = array( 'name' => ( 'ac_news' === $pt ? 'News' : 'Companies' ), 'url' => get_post_type_archive_link( $pt ) );
		if ( $term && ! empty( $term->name ) ) {
			$link = get_term_link( $term );
			$items[] = array( 'name' => $term->name, 'url' => is_wp_error( $link ) ? '' : $link );
		}
	}
	return $items;
}

/** 归档页上参与 ItemList 的条目（最多 30 条，控制体积） */
function ac_list_items( $max = 30 ) {
	$out = array();
	$i   = 0;
	if ( have_posts() ) {
		while ( have_posts() ) {
			the_post();
			if ( ++$i > $max ) { break; }
			$out[] = array(
				'@type'    => 'ListItem',
				'position' => $i,
				'name'     => get_the_title(),
				'url'      => get_permalink(),
			);
		}
		rewind_posts();
	}
	return $out;
}

/**
 * 输出结构化数据 JSON-LD（@graph 形式）
 * 第三方平台视角：所有页面都声明「谁在发布」（Organization/Publisher），
 * 新闻页额外声明「依据哪条原始来源」（isBasedOn）与核验状态，
 * 归档页用 CollectionPage + ItemList 让抓取方能直接拿到列表。
 */
function ac_json_ld() {
	$b        = ac_site_boilerplate();
	$graph    = array();
	$home     = home_url( '/' );
	$canon    = ac_canonical_url();

	$publisher = array(
		'@type'       => 'Organization',
		'@id'         => $home . '#organization',
		'name'        => $b['org'],
		'url'         => $home,
		'email'       => $b['email'],
		'description' => $b['desc'],
		'address'     => array(
			'@type'           => 'PostalAddress',
			'addressLocality' => 'Sicily',
			'addressRegion'   => 'Sicily',
			'addressCountry'  => 'IT',
		),
		'contactPoint' => array(
			'@type'             => 'ContactPoint',
			'contactType'       => 'editorial',
			'email'             => $b['email'],
			'availableLanguage' => array( 'English', 'Italian', 'French', 'German', 'Portuguese', 'Greek' ),
		),
	);

	if ( is_front_page() ) {
		$graph[] = $publisher;
		$graph[] = array(
			'@type'        => 'WebSite',
			'@id'          => $home . '#website',
			'url'          => $home,
			'name'         => get_bloginfo( 'name' ),
			'description'  => $b['desc'],
			'publisher'    => array( '@id' => $home . '#organization' ),
			'inLanguage'   => 'en',
		);
	} else {
		// 内页用引用形式带上发布者，避免重复描述整段 Organization
		$graph[] = array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => $b['org'], 'url' => $home );
	}

	// 面包屑（全站）
	$bc = array_filter( ac_breadcrumb_items(), function ( $it ) { return ! empty( $it['url'] ); } );
	if ( count( $bc ) > 1 ) {
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $canon . '#breadcrumb',
			'itemListElement' => array_values( array_map( function ( $it, $i ) {
				return array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $it['name'],
					'item'     => $it['url'],
				);
			}, $bc, array_keys( $bc ) ) ),
		);
	}

	if ( is_singular( 'ac_company' ) ) {
		$website = ac_meta( 'ac_website' );
		$phone   = ac_meta( 'ac_phone' );
		$address = ac_meta( 'ac_address' );
		$x_link  = ac_meta( 'ac_x_link' );
		$certs   = ac_meta( 'ac_certificates' );

		$entry = array(
			'@type'       => 'LocalBusiness',
			'@id'         => $canon . '#company',
			'name'        => get_the_title(),
			'url'         => $website ? esc_url( $website ) : $canon,
			'description' => ac_company_summary(),
			'address'     => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => 'Sicily',
				'addressCountry'  => 'IT',
				'streetAddress'   => $address ? $address : '',
			),
			'areaServed'  => 'Italy',
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $canon ),
		);
		if ( $phone )   { $entry['telephone'] = $phone; }
		if ( $x_link )  { $entry['sameAs'] = array( esc_url( $x_link ) ); }
		if ( $certs ) {
			// 核验状态必须跟着数据一起给出去，不能被当成已确认事实
			$entry['hasCredential'] = array(
				'@type'              => 'EducationalOccupationalCredential',
				'credentialCategory' => 'certification',
				'recognizedBy'       => array( '@id' => $home . '#organization' ),
				'name'               => $certs,
				'credentialStatus'   => ac_meta( 'ac_cert_unverified' ) ? 'Unverified — reported from public sources, not independently confirmed' : 'Self-declared by the company',
			);
		}
		$graph[] = $entry;
	} elseif ( is_singular( 'ac_news' ) ) {
		$src_url  = ac_meta( 'ac_source_url' );
		$src_name = ac_meta( 'ac_source' );
		$conf     = ac_meta( 'ac_confidence' );

		$art = array(
			'@type'            => 'NewsArticle',
			'@id'              => $canon . '#article',
			'headline'         => get_the_title(),
			'description'      => has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 40, '…' ),
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $canon ),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'inLanguage'       => 'en',
			'author'           => array( '@id' => $home . '#organization' ),
			'publisher'        => array( '@id' => $home . '#organization' ),
			'isAccessibleForFree' => true,
		);
		if ( has_post_thumbnail() ) {
			$img = get_the_post_thumbnail_url( null, 'full' );
			if ( $img ) {
				$art['image'] = array( '@type' => 'ImageObject', 'url' => $img );
			}
		}
		if ( $src_url ) {
			// 明确声明「这是二手报道，原始来源在这里」——第三方平台最重要的可核验信号
			$art['isBasedOn'] = array( '@type' => 'CreativeWork', 'url' => esc_url( $src_url ), 'name' => get_the_title() );
		}
		if ( $src_name ) {
			$art['sourceOrganization'] = array( '@type' => 'Organization', 'name' => $src_name );
			$art['creditText']         = 'Reported by ' . $src_name;
		}
		if ( $conf ) {
			$art['abstract'] = 'Source reliability as assessed by ArtCoatings: ' . $conf . '.';
		}
		$graph[] = $art;
	} elseif ( is_singular() ) {
		$graph[] = array(
			'@type'            => 'WebPage',
			'@id'              => $canon . '#webpage',
			'url'              => $canon,
			'name'             => get_the_title(),
			'isPartOf'         => array( '@id' => $home . '#website' ),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'inLanguage'       => 'en',
		);
	} elseif ( is_post_type_archive() || is_tax() ) {
		$name   = is_tax() && ( $t = get_queried_object() ) ? $t->name : post_type_archive_title( '', false );
		$desc   = ac_archive_description();
		$items  = ac_list_items( 30 );
		$page   = array(
			'@type'      => 'CollectionPage',
			'@id'        => $canon . '#collection',
			'url'        => $canon,
			'name'       => $name . ' — ' . get_bloginfo( 'name' ) . ' Directory',
			'isPartOf'   => array( '@id' => $home . '#website' ),
			'inLanguage' => 'en',
			'about'      => array( '@type' => 'Thing', 'name' => 'Art coatings and decorative finishes' ),
			'publisher'  => array( '@id' => $home . '#organization' ),
		);
		if ( $desc ) { $page['description'] = $desc; }
		if ( $items ) { $page['mainEntity'] = array( '@type' => 'ItemList', 'numberOfItems' => count( $items ), 'itemListElement' => $items ); }
		$graph[] = $page;

		// 目录页的编辑政策 FAQ：直接回答「你们是谁/信息来源/是否核验」，
		// 这是 AI 引用第三方目录时最常见的三个疑问。
		if ( is_post_type_archive( 'ac_company' ) || is_tax( 'ac_industry' ) || is_tax( 'ac_region' ) ) {
			$graph[] = array(
				'@type'      => 'FAQPage',
				'@id'        => $canon . '#faq',
				'mainEntity' => ac_directory_faq( true ),
			);
		}
	}

	$data = array( '@context' => 'https://schema.org', '@graph' => $graph );
	echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n</script>\n";
}

/**
 * 目录页的三问三答（编辑政策）。同一份内容既渲染成可见 FAQ，又输出成 FAQPage JSON-LD，
 * 保证机器可读与用户可读完全一致。
 */
function ac_directory_faq( $for_json = false ) {
	$email = ac_site_email();
	$faq   = array(
		array(
			'q' => 'Is ArtCoatings affiliated with the companies it lists?',
			'a' => 'No. ArtCoatings is an independent directory. It does not sell the products listed, takes no commission on enquiries, and does not endorse or rank any company. Listings are published for reference only.',
		),
		array(
			'q' => 'Where does the information in a listing come from?',
			'a' => 'Each entry is compiled from publicly available sources — company websites, trade registers, industry press and product documentation — together with information submitted by the company itself. Where a field could not be confirmed against a public source, it is labelled as unverified.',
		),
		array(
			'q' => 'Are certifications and product claims verified?',
			'a' => 'Not independently. Certifications shown on a listing are reproduced from public sources and marked "Unverified" until the issuing body or the company confirms them. Specifiers should ask the company for current certificates before relying on them.',
		),
		array(
			'q' => 'How can a company update or remove its listing?',
			'a' => 'Email ' . $email . ' with the company name and the change requested. Corrections and removal requests are handled by the editorial desk, and material corrections are noted on the listing.',
		),
	);
	if ( $for_json ) {
		return array_map( function ( $row ) {
			return array(
				'@type'          => 'Question',
				'name'           => $row['q'],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $row['a'] ),
			);
		}, $faq );
	}
	return $faq;
}

/**
 * 目录页的「编辑政策 + 常见问题」区块。
 * 可见文案与 ac_directory_faq() 的 JSON-LD 同源，保证机器读到的和用户看到的一致。
 */
function ac_render_directory_policy() {
	$b   = ac_site_boilerplate();
	$faq = ac_directory_faq( false );
	?>
	<section class="section section--muted">
		<div class="container">
			<div class="policy">
				<div class="policy__block">
					<h2 class="policy__title" data-i18n="How this directory is compiled">How this directory is compiled</h2>
					<p class="policy__text"><?php echo esc_html( $b['desc'] ); ?> It is based in <?php echo esc_html( $b['loc'] ); ?> and covers decorative coatings, mineral and lime plasters, microcement and metallic finishes.</p>
					<ul class="policy__list">
						<li><strong>What we publish:</strong> company name, products, service regions, contact details and publicly stated certifications.</li>
						<li><strong>What we do not do:</strong> sell products, take commission on enquiries, or rank and endorse companies.</li>
						<li><strong>What is unverified:</strong> any field that could not be checked against a public source is labelled, rather than presented as confirmed fact.</li>
						<li><strong>Corrections:</strong> material corrections are applied to the listing and can be requested at <?php echo esc_html( $b['email'] ); ?>.</li>
					</ul>
				</div>
				<div class="policy__block">
					<h2 class="policy__title" data-i18n="Editorial policy">Editorial policy</h2>
					<dl class="faq">
						<?php foreach ( $faq as $row ) : ?>
							<dt><?php echo esc_html( $row['q'] ); ?></dt>
							<dd><?php echo esc_html( $row['a'] ); ?></dd>
						<?php endforeach; ?>
					</dl>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/** 新闻页的来源/核验声明（第三方报道必备） */
function ac_news_source_url() {
	return ac_meta( 'ac_source_url' );
}

function ac_news_confidence() {
	return ac_meta( 'ac_confidence' );
}
