<?php
/**
 * 新闻配图统一转 webp（长期约束：所有图片一律 webp）
 *
 *   tools/data/news_images_raw/*  ->  tools/data/news_images/<basename>.webp
 *   转换后删除原图，并把 news_images.json 里的路径改写为 webp 路径。
 *
 * 用法： <php> tools/convert-news-webp.php
 */

$HERE = __DIR__;
$RAW  = $HERE . '/data/news_images_raw';
$OUT  = $HERE . '/data/news_images';

if ( ! is_dir( $RAW ) ) {
	fwrite( STDERR, "没有 $RAW，先跑 scrape_news_images.py\n" );
	exit( 1 );
}
if ( ! is_dir( $OUT ) ) { @mkdir( $OUT, 0755, true ); }

/**
 * 按扩展名加载，失败时按文件内容探测真实格式兜底。
 * （有些站点给的 .png/.jpg 实际是 AVIF，imagecreatefrompng/jpeg 会失败。）
 */
function cnw_load( $path ) {
	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	$im  = false;
	switch ( $ext ) {
		case 'jpg':
		case 'jpeg':
			$im = @imagecreatefromjpeg( $path );
			break;
		case 'png':
			$im = @imagecreatefrompng( $path );
			break;
		case 'gif':
			$im = @imagecreatefromgif( $path );
			break;
		case 'webp':
			$im = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false;
			break;
	}
	if ( $im ) { return $im; }
	$data = @file_get_contents( $path );
	if ( false === $data || '' === $data ) { return false; }
	return @imagecreatefromstring( $data );
}

$files = glob( $RAW . '/*.*' );
$done = 0; $skip = 0; $bad = 0; $saved = 0;

foreach ( $files as $f ) {
	$base = pathinfo( $f, PATHINFO_FILENAME );
	$dest = $OUT . '/' . $base . '.webp';

	if ( strtolower( pathinfo( $f, PATHINFO_EXTENSION ) ) === 'webp' ) {
		copy( $f, $dest );
		$done++;
		continue;
	}

	$im = cnw_load( $f );
	if ( ! $im ) {
		fwrite( STDERR, "  无法解码：" . basename( $f ) . "\n" );
		$bad++;
		continue;
	}

	// 长边压到 1600px 以内，新闻封面足够用，体积也可控
	$w = imagesx( $im );
	$h = imagesy( $im );
	$max = 1600;
	if ( $w > $max || $h > $max ) {
		$ratio = $max / max( $w, $h );
		$nw = (int) round( $w * $ratio );
		$nh = (int) round( $h * $ratio );
		$tmp = imagecreatetruecolor( $nw, $nh );
		imagealphablending( $tmp, false );
		imagesavealpha( $tmp, true );
		imagecopyresampled( $tmp, $im, 0, 0, 0, 0, $nw, $nh, $w, $h );
		imagedestroy( $im );
		$im = $tmp;
	}

	$before = filesize( $f );
	if ( @imagewebp( $im, $dest, 82 ) ) {
		$done++;
		$saved += max( 0, $before - filesize( $dest ) );
		@unlink( $f );
	} else {
		fwrite( STDERR, "  转换失败：" . basename( $f ) . "\n" );
		$bad++;
	}
	imagedestroy( $im );
}

// 改写 news_images.json 里的路径
$json = $HERE . '/data/news_images.json';
if ( file_exists( $json ) ) {
	$recs = json_decode( file_get_contents( $json ), true );
	if ( is_array( $recs ) ) {
		foreach ( $recs as $i => $r ) {
			if ( ! empty( $r['file'] ) ) {
				$b = pathinfo( $r['file'], PATHINFO_FILENAME );
				$recs[ $i ]['file'] = 'data/news_images/' . $b . '.webp';
			}
		}
		file_put_contents( $json, json_encode( $recs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		echo "已改写 news_images.json 路径\n";
	}
}

// 注意：中文标点属于 PHP 标识符允许的字符范围（\x80-\xff），
// 直接写 "$bad，跳过" 会被当成一个变量名。这里一律用 {$var} 显式定界。
echo '转换完成：' . $done . ' 张 webp，失败 ' . $bad . '，跳过 ' . $skip . '；节省约 ' . round( $saved / 1048576, 2 ) . " MB\n";

// 复查：源目录不该再留下非 webp
$left = 0;
foreach ( glob( $RAW . '/*.*' ) as $f ) {
	if ( ! in_array( strtolower( pathinfo( $f, PATHINFO_EXTENSION ) ), array( 'webp' ), true ) ) { $left++; }
}
echo '复查：raw 目录残留非 webp = ' . $left . ( $left === 0 ? ' [OK]' : '' ) . "\n";
