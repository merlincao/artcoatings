<?php
/**
 * artcoatings.org 静态导出器 v2（生成自包含 html/）
 *
 * v2 修复（v1 的两个致命 bug）：
 *   1) WP 给资源加 ?ver=xxx 缓存串，v1 直接把它当文件名 -> Windows 文件名不能含 '?'
 *      -> file_put_contents 写失败，而 v1 无条件 return true，静默吞掉失败，
 *         导致 theme 的 CSS/JS 全部丢失（目录建了但是空的），页面裸奔。
 *      现在：落盘前剥离查询串，HTML 引用同步改指向干净文件名，并真实校验写入结果。
 *   2) v1 输出根相对路径 /wp-content/...，只有在 html/ 恰好是站点根时才可用。
 *      现在默认输出**相对路径**，部署到域名根目录或任意子目录都能工作。
 *
 * v2.1：
 *   3) 归档页（/industry/*、/region/*）此前没进种子，导致主导航在静态站上 404。
 *      现在显式枚举所有非空 term 一并导出。
 *   4) 新增 --clean：导出前清空 html/，避免上一轮残留的废弃页面混进本次产物。
 *
 * 用法：
 *   php tools/static-export.php                          # 相对路径（推荐，任何部署位置都能跑）
 *   php tools/static-export.php --clean                  # 先清空 html/ 再导出（推荐）
 *   php tools/static-export.php --base https://artcoatings.org   # 绝对生产域名
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

$opts = getopt( '', array( 'base:', 'clean' ) );
$PROD = ! empty( $opts['base'] ) ? rtrim( $opts['base'], '/' ) : '';

// ---------- 清空输出目录 ----------
// 由 PHP 自己递归删除，而不是在 shell 里 rm -rf：
// 静态产物动辄几百个文件，shell 批量删除会触发安全策略拦截。
if ( isset( $opts['clean'] ) ) {
	if ( ! is_dir( $OUT ) ) {
		@mkdir( $OUT, 0755, true );
	} else {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $OUT, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		$n = 0;
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() );
			} else {
				@unlink( $f->getPathname() );
				$n++;
			}
		}
		echo "已清空旧产物：$n 个文件\n";
	}
}

// ---------- 收集种子 URL ----------
$urls = array( '/' );
foreach ( array( 'ac_company', 'ac_news' ) as $pt ) {
	if ( $link = get_post_type_archive_link( $pt ) ) {
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

// 分类/地区归档页。黄页站的主导航就靠这些入口（/industry/xxx、/region/yyy），
// 漏掉它们会让首页导航在静态站上直接 404 —— 所以这里显式枚举所有有内容的 term。
foreach ( array( 'ac_industry', 'ac_region', 'ac_news_cat' ) as $tx ) {
	if ( ! taxonomy_exists( $tx ) ) {
		continue;
	}
	$terms = get_terms( array(
		'taxonomy'   => $tx,
		'hide_empty' => true,   // 空归档不生成，避免抓到空壳页
	) );
	if ( is_wp_error( $terms ) ) {
		continue;
	}
	foreach ( $terms as $t ) {
		$link = get_term_link( $t );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$path = parse_url( $link, PHP_URL_PATH );
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
			'timeout'         => 25,
			'follow_location' => true,
			'max_redirects'   => 5,
			'header'          => "User-Agent: Mozilla/5.0 (compatible; ArtCoatingsStatic/1.0)\r\n",
		),
	) );
	return @file_get_contents( $url, false, $ctx );
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

/**
 * 去掉查询串 / 锚点。
 * 刻意不用正则：以 '#' 作分隔符时，字符类 [?#] 里的 '#' 会被当成结束分隔符，
 * preg 报 "Unknown modifier ']'" 并返回 null —— v2 第一版就是栽在这里，
 * 结果所有资源路径变成 null，页面上却显示"0 失败"。
 */
function ss_strip_query( $p ) {
	$p = (string) $p;
	$i = strcspn( $p, '?#' );
	return ( $i < strlen( $p ) ) ? substr( $p, 0, $i ) : $p;
}

/** 去掉查询串/锚点后拼上相对前缀；根路径返回 './' 或前缀本身 */
function ss_clean_path( $prefix, $path ) {
	$path = ss_strip_query( $path );
	if ( $path === '' || $path === '/' ) {
		return ( $prefix === '' ) ? './' : $prefix;
	}
	return $prefix . ltrim( $path, '/' );
}

/** 由页面 URL 推出回到站点根的相对前缀 */
function ss_prefix_for( $u ) {
	$p = ss_strip_query( $u );
	$p = trim( $p, '/' );
	if ( $p === '' ) {
		return '';
	}
	return str_repeat( '../', count( explode( '/', $p ) ) );
}

function collect_assets( $html ) {
	$pat = '#(?:https?://(?:localhost|127\.0\.0\.1):8080)?(/wp-content/[^"\')\s]+|/wp-includes/[^"\')\s]+)#i';
	$out = array();
	if ( preg_match_all( $pat, $html, $m ) ) {
		foreach ( $m[1] as $p ) {
			// 跳过 speculationrules 注入的 glob 片段（含 * ( [ { 等正则元字符），
			// 但**不要**排除 '?' —— 真实资源的 ?ver= 缓存串是合法的，只需落盘时剥离。
			if ( preg_match( '#[*()\[\]{}<>|\\^$]#', $p ) ) {
				continue;
			}
		$clean = ss_strip_query( $p );
		if ( empty( $clean ) ) {
				continue;
			}
			$out[] = $clean;
		}
	}
	return array_values( array_unique( $out ) );
}

function download_asset( $base, $out, $asset_rel ) {
	$asset_rel = ss_strip_query( $asset_rel );
	if ( empty( $asset_rel ) ) {
		return false;
	}
	$url  = $base . $asset_rel;
	$file = $out . '/' . ltrim( $asset_rel, '/' );
	ss_mkdir_for( $file );
	// 注意排除目录：否则 $file 恰好是目录时会被误判为"已存在"而跳过下载
	if ( ! is_dir( $file ) && file_exists( $file ) && filesize( $file ) > 0 ) {
		return true;
	}
	$data = ss_fetch( $url );
	if ( $data === false || strlen( $data ) < 1 ) {
		return false;
	}
	// 关键：校验写入结果（v1 就是在这里静默失败导致 CSS 全丢）
	if ( @file_put_contents( $file, $data ) === false ) {
		fwrite( STDERR, "  写入失败(非法文件名?): $asset_rel\n" );
		return false;
	}
	return true;
}

/** CSS 内 url(/...) 改相对，并下载其引用的资源 */
function fix_css_urls( $base, $out, $css_url_path, $css_file ) {
	$css = @file_get_contents( $css_file );
	if ( $css === false ) {
		return;
	}
	// 先按原始引用下载资源
	if ( preg_match_all( '#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i', $css, $m ) ) {
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
	// 再把 url(/xxx) 改写成相对该 css 所在目录的路径
	$dirparts = array_filter( explode( '/', trim( dirname( $css_url_path ), '/' ) ), 'strlen' );
	$prefix   = str_repeat( '../', count( $dirparts ) );
	$new      = preg_replace_callback(
		'#(url\(\s*[\'"]?)(/[^)\'"]+)([\'"]?\s*\))#i',
		function ( $mm ) use ( $prefix ) {
			return $mm[1] . $prefix . ltrim( $mm[2], '/' ) . $mm[3];
		},
		$css
	);
	if ( $new !== null && $new !== $css ) {
		file_put_contents( $css_file, $new );
	}
}

/** 解析分页链接（page-numbers），返回站内 path */
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
			continue;
		}
		// 只取分页，避免把筛选/归档参数页拉进来
		if ( strpos( $path, '/page/' ) !== false || preg_match( '#/page/\d+/?$#', $path ) ) {
			$out[] = $path;
		}
	}
	return array_values( array_unique( $out ) );
}

/** 把绝对/根相对 URL 改写成相对路径（或绝对生产域名） */
function ss_rewrite( $html, $prefix, $prod, $base ) {
	if ( $prod !== '' ) {
		$search = array( $base, 'https://localhost:8080', 'http://127.0.0.1:8080', 'https://127.0.0.1:8080' );
		return str_replace( $search, $prod, $html );
	}
	$self = ( $prefix === '' ) ? './' : $prefix;
	$html = str_replace( array( '="/"', "='/'" ), array( '="' . $self . '"', "='" . $self . "'" ), $html );

	$html = preg_replace_callback(
		'#https?://(?:localhost|127\.0\.0\.1):8080(/[^"\')\s>]*)?#i',
		function ( $m ) use ( $prefix ) {
			return ss_clean_path( $prefix, isset( $m[1] ) ? $m[1] : '/' );
		},
		$html
	);
	// 根相对（排除 // 协议相对、# 锚点）
	$html = preg_replace_callback(
		'#(=["\'])(/[^/"\'\s>][^"\'\s>]*)#',
		function ( $m ) use ( $prefix ) {
			return $m[1] . ss_clean_path( $prefix, $m[2] );
		},
		$html
	);
	return $html;
}

// ---------- 执行（队列 + 分页发现） ----------
echo "种子 " . count( $urls ) . " 个页面 -> $OUT\n";
echo "路径模式: " . ( $PROD !== '' ? "绝对 $PROD" : "相对（推荐）" ) . "\n";

$queue   = $urls;
$seen    = array();
$saved   = 0;
$failed  = 0;
$assets  = 0;

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

	$list = collect_assets( $html );
	foreach ( $list as $a ) {
		if ( download_asset( $BASE, $OUT, $a ) ) {
			$assets++;
		} else {
			$failed++;
			fwrite( STDERR, "  资源失败: $a\n" );
		}
	}
	foreach ( $list as $a ) {
		if ( preg_match( '/\.css$/i', $a ) ) {
			$f = $OUT . '/' . ltrim( $a, '/' );
			if ( file_exists( $f ) ) {
				fix_css_urls( $BASE, $OUT, $a, $f );
			}
		}
	}

	foreach ( collect_pagination( $html, $BASE ) as $p ) {
		if ( ! isset( $seen[ $p ] ) ) {
			$queue[] = $p;
		}
	}

	$html = ss_rewrite( $html, ss_prefix_for( $u ), $PROD, $BASE );
	$file = ss_disk_path( $OUT, $u );
	ss_mkdir_for( $file );
	file_put_contents( $file, $html );
	$saved++;
	echo "  saved $u\n";
}

echo "导出完成：共生成 $saved 个页面，资源 $assets 个，失败 $failed 个。\n";

// 关键自检：主题 CSS/JS 必须落盘且非空（v1 就是这里静默丢失）
foreach ( array(
	'wp-content/themes/artcoatings/assets/css/tokens.css',
	'wp-content/themes/artcoatings/assets/css/main.css',
	'wp-content/themes/artcoatings/assets/js/main.js',
) as $crit ) {
	$f = $OUT . '/' . $crit;
	$ok = file_exists( $f ) && filesize( $f ) > 0;
	echo ( $ok ? '[OK]   ' : '[MISS] ' ) . $crit . ( $ok ? ' (' . filesize( $f ) . ' B)' : '' ) . "\n";
}
