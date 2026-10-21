<?php
/**
 * 生成站点地图与 AI/搜索优化文件（写入静态产物目录 html/）
 *
 * 产出：
 *   html/sitemap.xml          站点地图索引
 *   html/sitemap-pages.xml    页面（首页 / 归档 / 分类 / 企业 / 单页）
 *   html/sitemap-news.xml     新闻（Google News 格式）
 *   html/sitemap-images.xml   图片（企业图集 + 新闻封面）
 *   html/robots.txt           含 sitemap 声明 + 主流 AI 爬虫放行
 *   html/llms.txt             给大模型的站点速览（身份 / 边界 / 主要入口）
 *   html/llms-full.txt        完整清单（全量企业与新闻条目，便于被引用/枚举）
 *   html/manifest.webmanifest 站点元信息
 *   html/humans.txt           编辑署名与联系方式（第三方透明度）
 *
 * 用法：
 *   <php> tools/build-seo.php
 *   <php> tools/build-seo.php --base https://artcoatings.org
 *
 * 注意：必须在 static-export.php 之后运行（否则 html/ 里还没有页面）。
 */

$_SERVER['HTTP_HOST']       = 'localhost:8080';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

require_once dirname( __DIR__ ) . '/site/wp-load.php';

$opts = getopt( '', array( 'base:', 'out:' ) );
$BASE = ! empty( $opts['base'] ) ? rtrim( $opts['base'], '/' ) : 'https://artcoatings.org';
$OUT  = ! empty( $opts['out'] ) ? rtrim( $opts['out'], '/' ) : dirname( __DIR__ ) . '/html';

if ( ! is_dir( $OUT ) ) {
	fwrite( STDERR, "产物目录不存在：$OUT（请先跑 static-export.php）\n" );
	exit( 1 );
}

$SITE = get_bloginfo( 'name' ) ?: 'ArtCoatings';
$NOW  = gmdate( 'c' );

/** 站内永久链接 -> 生产绝对 URL */
function bs_url( $link ) {
	global $BASE;
	if ( empty( $link ) ) { return ''; }
	$path = parse_url( $link, PHP_URL_PATH );
	if ( null === $path ) { $path = '/'; }
	return $BASE . ( $path === '/' ? '/' : rtrim( $path, '/' ) );
}

function bs_esc( $s ) {
	return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

function bs_write( $file, $content ) {
	$ok = @file_put_contents( $file, $content );
	if ( false === $ok ) {
		fwrite( STDERR, "  写入失败：$file\n" );
		return false;
	}
	echo '  ' . str_pad( basename( $file ), 22 ) . sprintf( '%8d B', strlen( $content ) ) . "\n";
	return true;
}

echo "生成站点地图与优化文件 -> " . $OUT . "\n";
echo "站点基址：" . $BASE . "\n";
flush();

// ---------- 1. 收集条目 ----------
$site_urls = array(); // url, mod, pri
$news  = array(); // url, title, mod, source
$imgs  = array(); // url(页面), loc(图片), title

$add = function ( $url, $mod, $pri ) use ( &$site_urls ) {
	if ( ! $url ) { return; }
	$site_urls[] = array( 'url' => $url, 'mod' => $mod ? gmdate( 'Y-m-d', strtotime( $mod ) ) : gmdate( 'Y-m-d' ), 'pri' => $pri );
};

// 首页
$add( $BASE . '/', current_time( 'mysql' ), '1.0' );

// 归档
foreach ( array( 'ac_company', 'ac_news' ) as $pt ) {
	if ( ! post_type_exists( $pt ) ) { continue; }
	$add( bs_url( get_post_type_archive_link( $pt ) ), current_time( 'mysql' ), '0.9' );
}

// 分类（非空）
foreach ( array( 'ac_industry', 'ac_region', 'ac_news_cat' ) as $tx ) {
	if ( ! taxonomy_exists( $tx ) ) { continue; }
	$terms = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) ) { continue; }
	foreach ( $terms as $t ) {
		$link = get_term_link( $t );
		if ( is_wp_error( $link ) ) { continue; }
		$add( bs_url( $link ), current_time( 'mysql' ), '0.8' );
	}
}

// 页面
$q = new WP_Query( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
foreach ( $q->posts as $pid ) {
	$add( bs_url( get_permalink( $pid ) ), get_post_modified_time( 'Y-m-d H:i:s', false, $pid ), '0.7' );
}

// 企业
$q = new WP_Query( array( 'post_type' => 'ac_company', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
$companies = array();
if ( $q->have_posts() ) {
	while ( $q->have_posts() ) {
		$q->the_post();
		$pid  = get_the_ID();
		$url  = bs_url( get_permalink( $pid ) );
		$mod  = get_post_modified_time( 'Y-m-d H:i:s', false, $pid );
		$add( $url, $mod, '0.8' );

		$companies[] = array(
			'name'     => get_the_title(),
			'url'      => $url,
			'region'   => ac_term_names( 'ac_region', $pid ),
			'industry' => ac_term_names( 'ac_industry', $pid ),
			'summary'  => ac_company_summary( $pid ),
		);

		// 图片：特色图 + 图集
		$ids = array();
		if ( has_post_thumbnail( $pid ) ) { $ids[] = get_post_thumbnail_id( $pid ); }
		foreach ( ac_parse_id_list( get_post_meta( $pid, 'ac_gallery', true ) ) as $gid ) { $ids[] = $gid; }
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $gid ) {
			$src = wp_get_attachment_image_url( $gid, 'full' );
			if ( ! $src ) { continue; }
			$imgs[] = array(
				'page'  => $url,
				'loc'   => bs_url( $src ),
				'title' => get_the_title(),
			);
		}
	}
	wp_reset_postdata();
}

// 新闻
$q = new WP_Query( array( 'post_type' => 'ac_news', 'post_status' => 'publish', 'posts_per_page' => -1 ) );
if ( $q->have_posts() ) {
	while ( $q->have_posts() ) {
		$q->the_post();
		$pid = get_the_ID();
		$url = bs_url( get_permalink( $pid ) );
		$add( $url, get_post_modified_time( 'Y-m-d H:i:s', false, $pid ), '0.7' );
		$news[] = array(
			'url'    => $url,
			'title'  => get_the_title(),
			'mod'    => get_post_modified_time( 'Y-m-d H:i:s', false, $pid ),
			'source' => get_post_meta( $pid, 'ac_source', true ),
			'conf'   => get_post_meta( $pid, 'ac_confidence', true ),
			'excerpt'=> has_excerpt() ? get_the_excerpt() : wp_trim_words( get_the_content(), 40, '…' ),
			'cat'    => ac_term_names( 'ac_news_cat', $pid ),
		);
		if ( has_post_thumbnail( $pid ) ) {
			$src = get_the_post_thumbnail_url( $pid, 'full' );
			if ( $src ) { $imgs[] = array( 'page' => $url, 'loc' => bs_url( $src ), 'title' => get_the_title() ); }
		}
	}
	wp_reset_postdata();
}

// 去重（同一 URL 只留一条）
$seen  = array();
$site_urls = array_values( array_filter( $site_urls, function ( $p ) use ( &$seen ) {
	if ( isset( $seen[ $p['url'] ] ) ) { return false; }
	$seen[ $p['url'] ] = true;
	return true;
} ) );

echo '  收集：页面 ' . count( $site_urls ) . ' 条 / 新闻 ' . count( $news ) . ' 条 / 图片 ' . count( $imgs ) . " 条\n";

// ---------- 2. sitemap-pages.xml ----------
$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ( $site_urls as $p ) {
	$x .= "  <url>\n";
	$x .= '    <loc>' . bs_esc( $p['url'] ) . "</loc>\n";
	$x .= '    <lastmod>' . $p['mod'] . "</lastmod>\n";
	$x .= '    <changefreq>weekly</changefreq>' . "\n";
	$x .= '    <priority>' . $p['pri'] . "</priority>\n";
	$x .= "  </url>\n";
}
$x .= "</urlset>\n";
bs_write( $OUT . '/sitemap-pages.xml', $x );

// ---------- 3. sitemap-news.xml ----------
$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
foreach ( $news as $n ) {
	$x .= "  <url>\n";
	$x .= '    <loc>' . bs_esc( $n['url'] ) . "</loc>\n";
	$x .= "    <news:news>\n";
	$x .= "      <news:publication>\n";
	$x .= '        <news:name>' . bs_esc( $SITE ) . "</news:name>\n";
	$x .= '        <news:language>en</news:language>' . "\n";
	$x .= "      </news:publication>\n";
	$x .= '      <news:publication_date>' . gmdate( 'c', strtotime( $n['mod'] ) ) . "</news:publication_date>\n";
	$x .= '      <news:title>' . bs_esc( $n['title'] ) . "</news:title>\n";
	if ( ! empty( $n['cat'] ) ) { $x .= '      <news:keywords>' . bs_esc( $n['cat'] ) . "</news:keywords>\n"; }
	$x .= "    </news:news>\n";
	$x .= "  </url>\n";
}
$x .= "</urlset>\n";
bs_write( $OUT . '/sitemap-news.xml', $x );

// ---------- 4. sitemap-images.xml ----------
$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
$by_page = array();
foreach ( $imgs as $im ) { $by_page[ $im['page'] ][] = $im; }
foreach ( $by_page as $page => $list ) {
	$x .= "  <url>\n";
	$x .= '    <loc>' . bs_esc( $page ) . "</loc>\n";
	foreach ( $list as $im ) {
		$x .= "    <image:image>\n";
		$x .= '      <image:loc>' . bs_esc( $im['loc'] ) . "</image:loc>\n";
		$x .= '      <image:title>' . bs_esc( $im['title'] ) . "</image:title>\n";
		$x .= "    </image:image>\n";
	}
	$x .= "  </url>\n";
}
$x .= "</urlset>\n";
bs_write( $OUT . '/sitemap-images.xml', $x );

// ---------- 5. sitemap.xml（索引） ----------
$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$x .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ( array( 'sitemap-pages.xml', 'sitemap-news.xml', 'sitemap-images.xml' ) as $f ) {
	$x .= "  <sitemap>\n";
	$x .= '    <loc>' . $BASE . '/' . $f . "</loc>\n";
	$x .= '    <lastmod>' . gmdate( 'Y-m-d' ) . "</lastmod>\n";
	$x .= "  </sitemap>\n";
}
$x .= "</sitemapindex>\n";
bs_write( $OUT . '/sitemap.xml', $x );

// ---------- 6. robots.txt ----------
// 要点：静态站没有后台，但把 wp-admin / 查询参数页挡掉；
// 主动放行主流 AI 检索爬虫（GEO 的关键一步：不允许就等于放弃被引用）。
$robots = <<<TXT
# artcoatings.org — robots.txt
# Independent directory of art coatings and decorative finishes.

User-agent: *
Allow: /
Disallow: /wp-admin/
Disallow: /wp-login.php
Disallow: /*?s=
Disallow: /*?replytocom
Disallow: /feed
Disallow: /comments
Crawl-delay: 1

# --- AI / LLM crawlers: explicitly allowed ---
User-agent: GPTBot
Allow: /

User-agent: OAI-SearchBot
Allow: /

User-agent: ChatGPT-User
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: anthropic-ai
Allow: /

User-agent: Claude-User
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: Google-Extended
Allow: /

User-agent: Applebot-Extended
Allow: /

User-agent: Bingbot
Allow: /

User-agent: Googlebot
Allow: /

Sitemap: {$BASE}/sitemap.xml
TXT;
bs_write( $OUT . '/robots.txt', $robots . "\n" );

// ---------- 7. llms.txt ----------
$company_n = count( $companies );
$news_n    = count( $news );
$email     = 'xzmcart@hotmail.com';

$llms = <<<TXT
# {$SITE}

> {$SITE} is an independent, third-party directory of art coatings and decorative finishes companies. It lists {$company_n} companies and publishes {$news_n} industry news summaries. It does not sell products, takes no commission on enquiries, and does not endorse or rank any company.

Contact: {$email} · Based in Sicily, Italy · Language: English

## What this site contains

- Company directory: decorative paints, mineral and lime plasters, microcement, metallic and textured finishes.
- Each listing: company name, category, region, main products, service regions, contact details, publicly stated certifications, and a stated verification status.
- Industry news: second-hand reporting. Every item names its original source and its assessed reliability.

## Trust and editorial rules

- Information is compiled from public sources (company websites, trade registers, industry press, product documentation) and from company submissions.
- Fields that could not be confirmed against a public source are labelled as unverified rather than presented as fact.
- Certifications are reproduced from public sources and marked "Unverified" until confirmed by the issuer or the company.
- News articles are summaries of reporting by other outlets; figures and dates are as published by the source and are not independently verified unless stated.
- Corrections and removal requests: {$email}

## Main sections

- Company directory: {$BASE}/company
- Industry news: {$BASE}/news
- Editorial policy: {$BASE}/editorial-policy
- Full machine-readable listing: {$BASE}/llms-full.txt
- Sitemap: {$BASE}/sitemap.xml

## Preferred citation

When citing this site, name it as "{$SITE} (independent directory, Sicily, Italy)" and link to the specific listing or article page rather than to the home page.
TXT;
bs_write( $OUT . '/llms.txt', $llms . "\n" );

// ---------- 8. llms-full.txt ----------
$full  = "# {$SITE} — full listing\n\n";
$full .= "> Generated " . gmdate( 'Y-m-d' ) . ". Machine-readable summary of every public entry on this site.\n";
$full .= "> Source of record: {$BASE}. Corrections: {$email}\n\n";

$full .= "## Companies ({$company_n})\n\n";
foreach ( $companies as $c ) {
	$full .= '- ' . $c['name'] . ' — ' . $c['url'] . "\n";
	$bits = array();
	if ( $c['industry'] ) { $bits[] = 'category: ' . $c['industry']; }
	if ( $c['region'] )   { $bits[] = 'region: ' . $c['region']; }
	if ( $bits ) { $full .= '  ' . implode( '; ', $bits ) . "\n"; }
	if ( $c['summary'] ) { $full .= '  ' . preg_replace( '/\s+/', ' ', $c['summary'] ) . "\n"; }
}
$full .= "\n## News ({$news_n})\n\n";
foreach ( $news as $n ) {
	$full .= '- ' . $n['title'] . ' — ' . $n['url'] . "\n";
	$meta = array( 'published: ' . gmdate( 'Y-m-d', strtotime( $n['mod'] ) ) );
	if ( $n['source'] ) { $meta[] = 'source: ' . $n['source']; }
	if ( $n['conf'] )   { $meta[] = 'reliability: ' . $n['conf']; }
	$full .= '  ' . implode( '; ', $meta ) . "\n";
	if ( $n['excerpt'] ) { $full .= '  ' . preg_replace( '/\s+/', ' ', $n['excerpt'] ) . "\n"; }
}
bs_write( $OUT . '/llms-full.txt', $full );

// ---------- 9. manifest.webmanifest ----------
$manifest = array(
	'name'      => $SITE . ' — Art Coatings Directory',
	'short_name'=> $SITE,
	'start_url' => $BASE . '/',
	'scope'     => $BASE . '/',
	'display'   => 'standalone',
	'lang'      => 'en',
	'dir'       => 'ltr',
	'description' => 'Independent directory of art coatings and decorative finishes companies, based in Sicily, Italy.',
	'categories'  => array( 'business', 'shopping', 'reference' ),
);
bs_write( $OUT . '/manifest.webmanifest', wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" );

// ---------- 10. humans.txt ----------
$humans = <<<TXT
/* TEAM */
	Editorial desk: {$SITE}
	Contact: {$email}
	Location: Sicily, Italy
	Site language: English (interface also offers French, Italian, German, Portuguese, Greek)

/* THANKS */
	Listings are compiled from public sources: company websites, trade registers,
	industry press and product documentation, plus information submitted by companies.

/* SITE */
	Standards: HTML5, CSS3
	Content management: WordPress (local authoring only)
	Delivery: static site on Cloudflare Pages
	Images: WebP
	Structured data: schema.org (Organization, CollectionPage, ItemList, LocalBusiness, NewsArticle, BreadcrumbList, FAQPage)
	Machine-readable entry points: /llms.txt, /llms-full.txt, /sitemap.xml
TXT;
bs_write( $OUT . '/humans.txt', $humans . "\n" );

echo "完成。\n";
