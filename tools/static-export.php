<?php
/**
 * artcoatings.org 静态导出器（生成自包含的 html/）
 * 抓取本地 http://localhost:8080 的渲染结果，改写为根相对路径后落盘到 html/。
 * 资源（CSS/JS/图片/字体）一并下载到 html/ 内，Cloudflare Pages 可直接托管。
 * 自动发现分页（page-numbers 链接）一并导出。
 *
 * 用法：
 *   php tools/static-export.php                 # 根相对路径（默认，适合任意域名）
 *   php tools/static-export.php --base https://artcoatings.pages.dev  # 改写为绝对生产域名
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

// ---------- 配置 ----------
$BASE = 'http://localhost:8080';
$ROOT = dirname( __DIR__ );
$OUT  = $ROOT . '/html';

$opts = getopt( '', array( 'base:' ) );
$PROD = ! empty( $opts['base'] ) ? rtrim( $opts['base'], '/' ) : '';

// ---------- 收集种子 URL ----------
$urls = array( '/' );
foreach ( array( 'ac_company', 'ac_news' ) as $tax_pt ) {
	if ( $link = get_post_type_archive_link( $tax_pt ) ) {
		$urls[] = parse_url( $link, PHP_URL_PATH );
	}
}
foreach ( array( 'ac_company', 'ac_news', 'page' ) as $pt ) {
	$ps = get_posts( array( 'post_type' => $pt, 'posts_per_page' => -1, 'post_status' => 'publish' ) );
	foreach ( $ps as $p ) {
		$path = parse_url( get_permalink( $p ), PHP_URL_PATH );
		if ( $path ) {
			$urls[] = $path;
		}
	}
}
$urls = array_values( array_unique( $urls ) );

// ---------- 工具函数 ----------
function ss_fetch( $url ) {
	$ctx = stream_context_create( array(
		'http' => array(
			'timeout'        => 25,
			'follow_location' => true,
			'max_redirects'  => 5,
			'header'         => "User-Agent: Mozilla/5.0 (compatible; ArtCoatingsStatic/1.0)\r\n",
		),
	) );
	$c = @file_get_contents( $url, false, $ctx );
	return $c;
}

function ss_disk_path( $root, $rel ) {
	$rel = ltrim( $rel, '/' );
	if ( $rel === '' ) {
		$rel = 'index.html';
	} elseif ( substr( $rel, -1 ) === '/' ) {
		$rel .= 'index.html';
	} elseif ( ! preg_match( '/\.[a-z0-9]{2,5}$/i', $rel ) ) {
		$rel .= '/index.html';
	}
	return $root . '/' . $rel;
}

function ss_mkdir_for( $file ) {
	$dir = dirname( $file );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}
}

function collect_assets( $html ) {
	$pat = '#(?:https?://(?:localhost|127\.0\.0\.1):8080)?(/wp-content/[^"\')\s]+|/wp-includes/[^"\')\s]+)#i';
	$out = array();
	if ( preg_match_all( $pat, $html, $m ) ) {
		foreach ( $m[1] as $p ) {
			// 跳过 speculationrules 等注入 JSON 里的通配/正则片段（非真实资源，
			// 例如 /wp-content/uploads/* ），否则每个会白白消耗抓取超时
			if ( preg_match( '#[*?()+\[\]{}<>|\\^$]#', $p ) ) {
				continue;
			}
			$out[] = $p;
		}
	}
	return array_values( array_unique( $out ) );
}

function rewrite_base( $html, $base, $prod ) {
	$search  = array( $base, 'https://localhost:8080', 'http://127.0.0.1:8080', 'https://127.0.0.1:8080' );
	$replace = $prod !== '' ? $prod : '';
	return str_replace( $search, $replace, $html );
}

function download_asset( $base, $out, $asset_rel ) {
	$url  = $base . $asset_rel;
	$file = $out . '/' . ltrim( $asset_rel, '/' );
	ss_mkdir_for( $file );
	if ( file_exists( $file ) && filesize( $file ) > 0 ) {
		return true;
	}
	$data = ss_fetch( $url );
	if ( $data === false || strlen( $data ) < 1 ) {
		return false;
	}
	file_put_contents( $file, $data );
	return true;
}

// CSS 内部 url() 引用的本地资源（相对路径）一并下载
function fix_css_urls( $base, $out, $css_url_path, $css_file ) {
	$css = file_get_contents( $css_file );
	if ( $css === false ) {
		return;
	}
	if ( ! preg_match_all( '#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', $css, $m ) ) {
		return;
	}
	$dir = dirname( $css_url_path );
	foreach ( $m[1] as $u ) {
		$u = trim( $u );
		if ( preg_match( '#^(https?:|data:|//)#i', $u ) ) {
			continue;
		}
		$asset_rel = ( $u[0] === '/' ) ? $u : $dir . '/' . $u;
		while ( preg_match( '#/[^/]+/\.\./#', $asset_rel ) ) {
			$asset_rel = preg_replace( '#/[^/]+/\.\./#', '/', $asset_rel, 1 );
		}
		download_asset( $base, $out, $asset_rel );
	}
}

// 解析分页链接（page-numbers），返回站内 path
function collect_pagination( $html, $base ) {
	$out = array();
	if ( ! preg_match_all( '#<a\b[^>]*>#i', $html, $atags ) ) {
		return $out;
	}
	foreach ( $atags[0] as $tag ) {
		if ( stripos( $tag, 'page-numbers' ) === false ) {
			continue;
		}
		if ( ! preg_match( '#href="([^"]+)"#i', $tag, $hm ) ) {
			continue;
		}
		$href = $hm[1];
		if ( strpos( $href, $base ) === 0 ) {
			$path = substr( $href, strlen( $base ) );
		} elseif ( preg_match( '#^https?://(?:localhost|127\.0\.0\.1):8080(/.*)$#i', $href, $pm ) ) {
			$path = $pm[1];
		} elseif ( $href !== '' && $href[0] === '/' ) {
			$path = $href;
		} else {
			continue; // 外链或锚点
		}
		$out[] = $path;
	}
	return array_values( array_unique( $out ) );
}

// ---------- 执行（队列 + 分页发现） ----------
echo "种子 " . count( $urls ) . " 个页面 -> $OUT\n";
$queue = $urls;
$seen  = array();
$saved = 0;

while ( $queue ) {
	$u = array_shift( $queue );
	if ( isset( $seen[ $u ] ) ) {
		continue;
	}
	$seen[ $u ] = true;

	$html = ss_fetch( $BASE . $u );
	if ( $html === false ) {
		fwrite( STDERR, "抓取失败: $u\n" );
		continue;
	}

	$assets = collect_assets( $html );
	foreach ( $assets as $a ) {
		if ( ! download_asset( $BASE, $OUT, $a ) ) {
			fwrite( STDERR, "  资源下载失败: $a\n" );
		}
	}
	foreach ( $assets as $a ) {
		if ( preg_match( '/\.css$/i', $a ) ) {
			$f = $OUT . '/' . ltrim( $a, '/' );
			if ( file_exists( $f ) ) {
				fix_css_urls( $BASE, $OUT, $a, $f );
			}
		}
	}

	// 发现分页，加入队列
	foreach ( collect_pagination( $html, $BASE ) as $p ) {
		if ( ! isset( $seen[ $p ] ) ) {
			$queue[] = $p;
		}
	}

	$html = rewrite_base( $html, $BASE, $PROD );
	$file = ss_disk_path( $OUT, $u );
	ss_mkdir_for( $file );
	file_put_contents( $file, $html );
	$saved++;
	echo "  saved $u\n";
}

echo "导出完成：共生成 $saved 个页面。\n";
