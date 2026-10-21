<?php
/**
 * 给已存在的 ac_company 补挂图片（特色图 + 图集）。
 *
 * 为什么需要单独一个脚本：import-companies.php 遇到同名企业会直接 skip，
 * 所以后补抓到 / AI 生成的图片没法通过重跑导入写进去。本脚本只挂图，不动正文。
 *
 * 用法（在 artcoatings.org 根目录）：
 *   <php> tools/attach-images.php            # 只给「尚无特色图」的企业挂
 *   <php> tools/attach-images.php --force    # 强制重挂（覆盖现有特色图与图集）
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

$ROOT = dirname( __DIR__ );

if ( ! post_type_exists( 'ac_company' ) ) {
	fwrite( STDERR, "错误：ac_company 不存在，请先启用 artcoatings-core 插件。\n" );
	exit( 1 );
}

/** 把本地图片作为附件导入（复制到临时区，不动源文件） */
function ac_attach_local_image( $local_path, $post_id, $desc ) {
	if ( ! file_exists( $local_path ) || filesize( $local_path ) < 100 ) {
		return 0;
	}
	$tmp = wp_tempnam( basename( $local_path ) );
	if ( ! $tmp || ! copy( $local_path, $tmp ) ) {
		return 0;
	}
	$id = media_handle_sideload( array(
		'name'     => basename( $local_path ),
		'tmp_name' => $tmp,
	), $post_id, $desc );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	return $id;
}

$opts  = getopt( '', array( 'force' ) );
$FORCE = isset( $opts['force'] );

$json = $ROOT . '/tools/data/companies_enriched.json';
if ( ! file_exists( $json ) ) {
	fwrite( STDERR, "错误：找不到 $json\n" );
	exit( 1 );
}
$data = json_decode( file_get_contents( $json ), true );
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "错误：JSON 解析失败。\n" );
	exit( 1 );
}

$attached = 0;
$skipped  = 0;
$nopost   = 0;

foreach ( $data as $rec ) {
	$brand = trim( $rec['brand'] ?? '' );
	$slug  = $rec['slug'] ?: sanitize_title( $brand );
	$imgs  = $rec['images'] ?? array();
	if ( ! $brand || empty( $imgs ) ) {
		continue;
	}

	// 先按 slug(post_name) 找，再按标题找
	$q = new WP_Query( array(
		'post_type'      => 'ac_company',
		'name'           => $slug,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	) );
	$post_id = $q->have_posts() ? (int) $q->posts[0] : 0;
	if ( ! $post_id ) {
		$p = get_page_by_title( $brand, OBJECT, 'ac_company' );
		$post_id = $p ? (int) $p->ID : 0;
	}
	if ( ! $post_id ) {
		$nopost++;
		echo "找不到企业: $brand ($slug)\n";
		continue;
	}

	if ( ! $FORCE && has_post_thumbnail( $post_id ) ) {
		$skipped++;
		continue;
	}

	$ids = array();
	foreach ( array_slice( $imgs, 0, 3 ) as $rel ) {
		$path = $ROOT . '/' . ltrim( str_replace( '\\', '/', $rel ), '/' );
		$id   = ac_attach_local_image( $path, $post_id, $brand );
		if ( $id ) {
			$ids[] = $id;
		}
	}

	if ( empty( $ids ) ) {
		continue;
	}
	set_post_thumbnail( $post_id, $ids[0] );
	if ( count( $ids ) > 1 ) {
		// 必须和 import-companies.php 保持一致：存逗号分隔字符串，不能存数组，
		// 否则模板 explode() 收到数组会抛 TypeError 让整页 500。
		update_post_meta( $post_id, 'ac_gallery', implode( ',', array_slice( $ids, 1 ) ) );
	}
	$attached++;
	echo "挂图: $brand -> 特色图 #{$ids[0]}" . ( count( $ids ) > 1 ? ' + 图集 ' . ( count( $ids ) - 1 ) . ' 张' : '' ) . "\n";
}

echo "\n完成：挂图 $attached 家，已有图跳过 $skipped 家，找不到企业 $nopost 家。\n";
