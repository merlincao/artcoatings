<?php
/**
 * CLI：启用 artcoatings 主题 + 初始化站点基础配置
 * 用法（在 artcoatings.org 根目录）：php tools/cli-theme.php
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
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// 0. 激活核心插件（注册 ac_company / ac_news / 分类）
if ( ! is_plugin_active( 'artcoatings-core/artcoatings-core.php' ) ) {
	$act = activate_plugin( 'artcoatings-core/artcoatings-core.php' );
	if ( is_wp_error( $act ) ) {
		fwrite( STDERR, "插件激活失败: " . $act->get_error_message() . "\n" );
	} else {
		echo "已激活插件: artcoatings-core\n";
	}
}

// 1. 启用主题
switch_theme( 'artcoatings' );
echo "主题已启用: " . wp_get_theme()->get( 'Name' ) . "\n";

// 2. 固定链接：黄页用 /company/{slug}.html，新闻用 /news/{slug}.html
update_option( 'permalink_structure', '/%postname%.html' );
update_option( 'category_base', 'news-cat' );
update_option( 'tag_base', 'tag' );

// 3. 站点基础信息（国际化 / 西西里定位）
update_option( 'blogname', 'ArtCoatings — Art Coatings Directory' );
update_option( 'blogdescription', 'An independent directory of art coatings brands and manufacturers, based in Sicily, Italy.' );
update_option( 'timezone_string', 'Europe/Rome' );
update_option( 'date_format', 'Y-m-d' );
update_option( 'time_format', 'H:i' );
update_option( 'posts_per_page', 12 );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );

// 4. 刷新重写规则
flush_rewrite_rules( true );
echo "固定链接已刷新\n";

// 5. 站点页面（About / 关于我们）
$about = get_page_by_path( 'about' );
if ( ! $about ) {
	wp_insert_post( array(
		'post_title'   => 'About ArtCoatings',
		'post_name'    => 'about',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' =>
			"ArtCoatings is an independent, Sicily-based directory of art coatings brands, manufacturers and suppliers operating across Italy and wider Europe.\n\n" .
			"<h2>Our mission</h2>\n<p>We help specifiers, contractors, architects and distributors discover and compare decorative coatings, mineral and lime plasters, microcement and metallic systems through a single, clearly structured reference.</p>\n\n" .
			"<h2>Editorial principles</h2>\n<ul>\n<li><strong>Verifiable by default.</strong> Every listing is built only from publicly available information — official websites, open catalogues and verifiable sources. We do not assert details we cannot confirm.</li>\n<li><strong>Independent.</strong> ArtCoatings is not affiliated with any listed brand unless explicitly stated.</li>\n<li><strong>Transparent.</strong> Where a contact or social entry cannot be confirmed, we label it as a search entry rather than a verified account.</li>\n</ul>\n\n" .
			"<h2>Coverage</h2>\n<p>Our directory spans established Italian and European coating houses and emerging specialists, organised by product category and region so you can shortlist quickly.</p>\n\n" .
			"<h2>Contact</h2>\n<p>For listing requests, corrections or partnership enquiries, reach us at xzmcart@hotmail.com. ArtCoatings is operated from Sicily, Italy.</p>",
	) );
	echo "已创建关于页 / About\n";
} else {
	echo "关于页已存在，跳过\n";
}
