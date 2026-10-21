<?php
/**
 * 创建/更新「编辑政策」页面（/editorial-policy/）
 *
 * 第三方平台的信任基础：把「我们是谁 / 信息从哪来 / 什么没被核实 / 怎么更正」
 * 写成一份公开页面，与 JSON-LD 的 FAQPage、llms.txt 保持同一套措辞。
 *
 * 用法： <php> tools/add-editorial-page.php
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

$EMAIL = 'xzmcart@hotmail.com';

$content = <<<'HTML'
<h2>What ArtCoatings is</h2>
<p>ArtCoatings is an independent directory of companies working in art coatings and decorative finishes — decorative paints, mineral and lime plasters, microcement, metallic and textured systems. It is based in Sicily, Italy, and publishes in English.</p>
<p>The directory exists to help specifiers, distributors and contractors find and compare suppliers. It does not sell products, takes no commission on enquiries, and does not endorse or rank any company. Listing order carries no commercial weighting.</p>

<h2>Where the information comes from</h2>
<p>Each entry is compiled from publicly available sources: company websites, trade registers, industry press, product documentation and trade fair listings, together with information submitted by the company itself. Where a field is taken from the company, this is stated on the listing.</p>
<p>We do not publish private contact details, pricing agreed in confidence, or any claim that cannot be traced to a public source or a company submission.</p>

<h2>What is verified and what is not</h2>
<p>Certifications and product claims shown on a listing are reproduced from public sources. They are marked <strong>Unverified</strong> until the issuing body or the company confirms them. We do not independently audit certificates, test products, or inspect factories.</p>
<p>Specifiers should ask the company for current certificates, technical data sheets and references before relying on any claim shown here.</p>

<h2>How the news section works</h2>
<p>News items are second-hand reporting. Each article names the outlet it comes from, links to the original where available, and carries an assessed source reliability (High, Medium-high or Medium). Figures, dates and quotations are reproduced as published by the source and are not independently verified unless the article says otherwise. Where something could not be confirmed, the article says so.</p>
<p>We do not republish press releases as our own reporting. Where an item originates from a company newsroom, the newsroom is named as the source.</p>

<h2>Corrections, updates and removal</h2>
<p>Companies and readers can request a correction, an update or the removal of a listing by emailing {$EMAIL} with the company name and the change requested. Material corrections are applied to the listing and noted on the page.</p>
<p>Listings are reviewed periodically; each entry shows the date it was last reviewed.</p>

<h2>How to cite ArtCoatings</h2>
<p>Please cite the specific listing or article page rather than the home page, and describe the source as “ArtCoatings (independent directory, Sicily, Italy)”.</p>

<h2>Machine-readable versions</h2>
<ul>
<li><a href="/sitemap.xml">sitemap.xml</a> — sitemap index for pages, news and images.</li>
<li><a href="/llms.txt">llms.txt</a> — short summary of the site for AI systems.</li>
<li><a href="/llms-full.txt">llms-full.txt</a> — full list of companies and news items.</li>
</ul>

<h2>Contact</h2>
<p>Editorial desk: <a href="mailto:{$EMAIL}">{$EMAIL}</a></p>
HTML;

$content = str_replace( '{$EMAIL}', $EMAIL, $content );

$slug = 'editorial-policy';
$existing = get_page_by_path( $slug, OBJECT, 'page' );

$args = array(
	'post_type'    => 'page',
	'post_title'   => 'Editorial policy',
	'post_name'    => $slug,
	'post_content' => $content,
	'post_status'  => 'publish',
	'post_author'  => 1,
);

if ( $existing ) {
	$args['ID'] = $existing->ID;
	$id = wp_update_post( $args, true );
	$verb = '更新';
} else {
	$id = wp_insert_post( $args, true );
	$verb = '创建';
}

if ( is_wp_error( $id ) ) {
	fwrite( STDERR, '失败：' . $id->get_error_message() . "\n" );
	exit( 1 );
}

update_post_meta( $id, '_ac_policy_page', '1' );
echo $verb . '页面成功：ID ' . $id . ' -> ' . get_permalink( $id ) . "\n";
