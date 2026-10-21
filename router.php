<?php
/**
 * artcoatings.org 本地开发 PHP 内置服务器路由
 * 启动：php -S localhost:8080 -t site router.php
 *
 * 职责：
 *   1. 静态文件直接返回（补 WebP 等 MIME）
 *   2. 静态文件 no-cache，方便调试
 *   3. 其余请求转 index.php，交给 WordPress
 */

$docroot = __DIR__ . '/site';
$path    = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file    = realpath( $docroot . '/' . urldecode( ltrim( $path, '/' ) ) );

// 目录穿越防护
if ( $file && strpos( $file, realpath( $docroot ) ) !== 0 ) {
	$file = false;
}

// ---- 静态文件 ----
if ( $file && is_file( $file ) ) {
	$mimes = array(
		'webp'  => 'image/webp',
		'css'   => 'text/css; charset=UTF-8',
		'js'    => 'application/javascript; charset=UTF-8',
		'json'  => 'application/json; charset=UTF-8',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'jpg'   => 'image/jpeg',
		'jpeg'  => 'image/jpeg',
		'gif'   => 'image/gif',
		'ico'   => 'image/x-icon',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf'   => 'font/ttf',
		'mp4'   => 'video/mp4',
		'html'  => 'text/html; charset=UTF-8',
		'txt'   => 'text/plain; charset=UTF-8',
		'map'   => 'application/json; charset=UTF-8',
	);
	$ext  = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	$mime = $mimes[ $ext ] ?? 'application/octet-stream';

	header( 'Content-Type: ' . $mime );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cache-Control: no-cache, must-revalidate' );

	if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] )
		&& strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) >= filemtime( $file ) ) {
		http_response_code( 304 );
		exit;
	}
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $file ) ) . ' GMT' );

	readfile( $file );
	return true;
}

// ---- 动态请求：交给 WordPress ----
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docroot . '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';

require $docroot . '/index.php';
