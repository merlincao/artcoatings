<?php
/**
 * 导入改写后的新闻到 ac_news
 *
 * 数据源：
 *   tools/data/news_bbc.json     由 parse_news.py 产出（BBC 规范改写稿）
 *   tools/data/news_images.json  由 scrape_news_images.py + convert-news-webp.php 产出
 *
 * 用法：
 *   <php> tools/import-news.php           幂等：按标题去重，已存在则更新
 *   <php> tools/import-news.php --dry     只报告不写库
 *
 * 依赖：artcoatings-core 插件启用（注册 ac_news / ac_news_cat）
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
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$opts = getopt( '', array( 'dry' ) );
$DRY  = isset( $opts['dry'] );

$ROOT = dirname( __DIR__ );
$json_file = $ROOT . '/tools/data/news_bbc.json';
$img_file  = $ROOT . '/tools/data/news_images.json';

if ( ! file_exists( $json_file ) ) {
	fwrite( STDERR, "缺少 $json_file\n" );
	exit( 1 );
}
$items = json_decode( file_get_contents( $json_file ), true );
if ( ! is_array( $items ) ) {
	fwrite( STDERR, "news_bbc.json 解析失败\n" );
	exit( 1 );
}

$imgmap = array();
if ( file_exists( $img_file ) ) {
	$recs = json_decode( file_get_contents( $img_file ), true );
	if ( is_array( $recs ) ) {
		foreach ( $recs as $r ) {
			if ( ! empty( $r['file'] ) ) { $imgmap[ (int) $r['no'] ] = $r['file']; }
		}
	}
}

/** 取（或建）新闻分类术语 */
function insist_term( $name, $tax = 'ac_news_cat' ) {
	$t = get_term_by( 'name', $name, $tax );
	if ( $t && ! is_wp_error( $t ) ) { return (int) $t->term_id; }
	$r = wp_insert_term( $name, $tax );
	if ( is_wp_error( $r ) ) {
		$s = sanitize_title( $name );
		$t = get_term_by( 'slug', $s, $tax );
		if ( $t && ! is_wp_error( $t ) ) { return (int) $t->term_id; }
		fwrite( STDERR, "  建分类失败：$name —— " . $r->get_error_message() . "\n" );
		return 0;
	}
	return (int) $r['term_id'];
}

/** 图片入库（webp），返回 attachment id */
function attach_webp( $rel_path, $title ) {
	global $ROOT;
	$abs = $ROOT . '/tools/' . ltrim( $rel_path, '/' );
	if ( ! file_exists( $abs ) ) {
		fwrite( STDERR, "  图片不存在：$rel_path\n" );
		return 0;
	}
	$name = basename( $abs );

	// 已上传过就复用，避免重复附件
	$q = new WP_Query( array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'meta_key'       => '_ac_src_name',
		'meta_value'     => $name,
		'fields'         => 'ids',
	) );
	if ( $q->have_posts() ) { return (int) $q->posts[0]; }

	$tmp = get_temp_dir() . $name;
	@copy( $abs, $tmp );

	$file = array(
		'name'     => $name,
		'tmp_name' => $tmp,
	);
	$id = media_handle_sideload( $file, 0, $title );
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, "  上传失败：$name —— " . $id->get_error_message() . "\n" );
		return 0;
	}
	update_post_meta( $id, '_ac_src_name', $name );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Editorial image — ' . $title );
	return (int) $id;
}

$created = 0; $updated = 0; $imged = 0; $fail = 0;

foreach ( $items as $it ) {
	$title = trim( $it['title'] );
	if ( ! $title ) { continue; }

	$existing = get_page_by_path( sanitize_title( $title ), OBJECT, 'ac_news' );
	$pid = $existing ? $existing->ID : 0;

	$post = array(
		'post_type'    => 'ac_news',
		'post_title'   => $title,
		'post_name'    => sanitize_title( $title ),
		'post_content' => $it['body'],
		'post_excerpt' => $it['standfirst'],
		'post_status'  => 'publish',
		'post_author'  => 1,
	);

	if ( $DRY ) {
		echo ( $pid ? '更新' : '新建' ) . "：$title\n";
		$pid ? $updated++ : $created++;
		continue;
	}

	if ( $pid ) {
		$post['ID'] = $pid;
		$pid = wp_update_post( $post, true );
		if ( is_wp_error( $pid ) ) { fwrite( STDERR, '  更新失败：' . $title . "\n" ); $fail++; continue; }
		$updated++;
	} else {
		$pid = wp_insert_post( $post, true );
		if ( is_wp_error( $pid ) ) { fwrite( STDERR, '  插入失败：' . $title . "\n" ); $fail++; continue; }
		$created++;
	}

	// 分类
	$tid = insist_term( $it['category'] );
	if ( $tid ) { wp_set_object_terms( $pid, array( $tid ), 'ac_news_cat', false ); }

	// 来源与可靠性（第三方报道的核心字段）
	update_post_meta( $pid, 'ac_source', $it['source'] );
	update_post_meta( $pid, 'ac_source_url', $it['source_url'] );
	update_post_meta( $pid, 'ac_confidence', $it['confidence'] );

	// 配图
	if ( isset( $imgmap[ (int) $it['no'] ] ) ) {
		$aid = attach_webp( $imgmap[ (int) $it['no'] ], $title );
		if ( $aid ) {
			set_post_thumbnail( $pid, $aid );
			$imged++;
		}
	}

	echo sprintf( "  [%02d] %s (%s)\n", $it['no'], $title, $it['confidence'] );
}

echo "\n新建 $created / 更新 $updated / 配图 $imged / 失败 $fail\n";
