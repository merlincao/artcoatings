<?php
/**
 * 站点内容英文化（一次性，可重复执行）
 *
 * 覆盖三类中文残留：
 *   1. WP 站点语言 -> en_US（决定 <html lang> 与 og:locale）
 *   2. ac_region / ac_industry / ac_news_cat 的中文 term 名称与 slug
 *   3. 企业 meta（主营产品 / 服务地区 / 简介 / 证书…）里的中文词组
 *
 * 替换顺序注意：映射表按「中文词长度降序」应用，
 * 否则 "装饰涂料" 会先把 "高端装饰涂料" 切成 "高端" + 英文，译文破碎。
 *
 * 用法：
 *   php tools/translate-to-en.php           # 预演，只打印将要发生的改动
 *   php tools/translate-to-en.php --apply   # 真正写入
 */
define( 'WP_USE_THEMES', false );
require dirname( __DIR__ ) . '/site/wp-load.php';

$apply = in_array( '--apply', $argv, true );
$zh    = '/[\x{4e00}-\x{9fff}]/u';

// ---------- 1. 地区 / 行业 术语（整词替换，含 slug） ----------
$term_map = array(
	// ac_region
	'圣马力诺'   => 'San Marino',
	'挪威'       => 'Norway',
	'瑞典'       => 'Sweden',
	'芬兰'       => 'Finland',
	'英国/欧洲'  => 'UK / Europe',
	// 兜底：单字国家/地区名
	'意大利'     => 'Italy',
	'法国'       => 'France',
	'德国'       => 'Germany',
	'西班牙'     => 'Spain',
	'荷兰'       => 'Netherlands',
	'比利时'     => 'Belgium',
	'英国'       => 'United Kingdom',
	'欧洲'       => 'Europe',
	// ac_industry 可能用到的中文名
	'装饰涂料'   => 'Decorative Coatings',
	'矿物涂料'   => 'Mineral & Lime Coatings',
	'肌理涂料'   => 'Textured Coatings',
);

// ---------- 2. 企业 meta 词组（按长度降序应用） ----------
$phrase_map = array(
	// —— 复合词组（长的必须先替换）——
	'英国/欧洲'              => 'UK / Europe',
	'装饰涂料及墙纸'         => 'decorative coatings and wallpapers',
	'室内外墙面饰面'         => 'interior and exterior wall finishes',
	'肌理效果及装饰饰面'     => 'texture effects and decorative finishes',
	'石灰基装饰饰面'         => 'lime-based decorative finishes',
	'纹理及金属效果'         => 'textured and metallic effects',
	'矿物及生态涂料'         => 'mineral and ecological coatings',
	'高端室内涂料'           => 'premium interior coatings',
	'高端装饰涂料'           => 'premium decorative coatings',
	'高端色彩涂料'           => 'premium colour coatings',
	'高端艺术涂料'           => 'premium artistic coatings',
	'艺术装饰涂料'           => 'artistic decorative coatings',
	'建筑装饰涂料'           => 'architectural decorative coatings',
	'天然装饰涂料'           => 'natural decorative coatings',
	'装饰效果涂料'           => 'decorative effect coatings',
	'专业建筑涂料'           => 'professional architectural coatings',
	'专业装饰涂料'           => 'professional decorative coatings',
	'室内装饰涂料'           => 'interior decorative coatings',
	'矿物装饰饰面'           => 'mineral decorative finishes',
	'矿物墙面饰面'           => 'mineral wall finishes',
	'艺术墙面饰面'           => 'artistic wall finishes',
	'生态墙面饰面'           => 'ecological wall finishes',
	'装饰墙面涂层'           => 'decorative wall coatings',
	'装饰墙面系统'           => 'decorative wall systems',
	'墙面效果涂料'           => 'wall effect coatings',
	'室内墙面涂料'           => 'interior wall coatings',
	'室内墙面漆'             => 'interior wall paints',
	'环保矿物'               => 'eco-friendly mineral',
	'混凝土效果'             => 'concrete effects',
	'威尼斯灰泥'             => 'Venetian plaster',
	'硅酸盐涂料'             => 'silicate coatings',

	// —— 单词组 ——
	'装饰涂料'   => 'decorative coatings',
	'建筑涂料'   => 'architectural coatings',
	'建筑涂层'   => 'architectural coatings',
	'色彩方案'   => 'colour schemes',
	'色彩系统'   => 'colour systems',
	'色彩装饰'   => 'decorative colours',
	'装饰色彩'   => 'decorative colours',
	'天然色彩'   => 'natural colours',
	'传统色彩'   => 'traditional colours',
	'历史色彩'   => 'historic colours',
	'复古色彩'   => 'heritage colours',
	'高端色彩'   => 'premium colours',
	'墙纸'       => 'wallpapers',
	'装饰饰面'   => 'decorative finishes',
	'效果漆'     => 'effect paints',
	'石灰基'     => 'lime-based',
	'石灰'       => 'lime',
	'灰泥'       => 'plaster',
	'墙面饰面'   => 'wall finishes',
	'墙面涂层'   => 'wall coatings',
	'墙面效果'   => 'wall effects',
	'饰面系统'   => 'finish systems',
	'纹理饰面'   => 'textured finishes',
	'纹理效果'   => 'textured effects',
	'矿物涂料'   => 'mineral coatings',
	'矿物涂层'   => 'mineral coatings',
	'矿物饰面'   => 'mineral finishes',
	'室内涂料'   => 'interior coatings',
	'效果墙面'   => 'effect wall finishes',
	'黏土涂料'   => 'clay coatings',
	'设计师涂料' => 'designer coatings',
	'装饰漆'     => 'decorative paints',
	'装饰肌理'   => 'decorative textures',
	'金属效果'   => 'metallic effects',
	'金属'       => 'metallic',
	'珠光'       => 'pearlescent',

	// —— 通用词（正文里偶尔出现）——
	'品牌'     => 'brand',
	'企业'     => 'company',
	'公司'     => 'company',
	'官网'     => 'official website',

	// —— 地区（meta 里也会出现）——
	'圣马力诺' => 'San Marino',
	'芬兰'     => 'Finland',
	'瑞典'     => 'Sweden',
	'挪威'     => 'Norway',
	'英国'     => 'United Kingdom',
	'欧洲'     => 'Europe',
	'意大利'   => 'Italy',
);

// 长词优先
uksort( $phrase_map, function ( $a, $b ) {
	return mb_strlen( $b ) - mb_strlen( $a );
} );

function ac_tr( $text, $map ) {
	foreach ( $map as $zh => $en ) {
		if ( strpos( $text, $zh ) !== false ) {
			$text = str_replace( $zh, $en, $text );
		}
	}
	// 中文顿号/斜杠统一成英文分隔符
	$text = str_replace( array( '、', '／' ), array( ', ', ' / ' ), $text );
	return $text;
}

echo( $apply ? "=== 应用模式 ===\n" : "=== 预演模式（加 --apply 才会写入）===\n" );

// ---------- 1. 站点语言 ----------
$cur = get_option( 'WPLANG' );
echo "\n[1] 站点语言: " . var_export( $cur, true ) . " -> 'en_US'\n";
if ( $apply ) {
	update_option( 'WPLANG', 'en_US' );
}

// ---------- 2. Term 英文化 ----------
echo "\n[2] 分类术语\n";
foreach ( array( 'ac_region', 'ac_industry', 'ac_news_cat' ) as $tx ) {
	$terms = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		continue;
	}
	foreach ( $terms as $t ) {
		if ( ! preg_match( $zh, $t->name ) ) {
			continue;
		}
		$new_name = $t->name;
		foreach ( $term_map as $z => $e ) {
			$new_name = str_replace( $z, $e, $new_name );
		}
		$new_slug = sanitize_title( $new_name );
		printf( "  [%s] %s (%s) -> %s (%s)\n", $tx, $t->name, $t->slug, $new_name, $new_slug );
		if ( $apply ) {
			wp_update_term( $t->term_id, $tx, array(
				'name' => $new_name,
				'slug' => $new_slug,
			) );
		}
	}
}

// ---------- 3. 企业 meta 英文化 ----------
echo "\n[3] 企业 meta\n";
$keys = array( 'ac_main_products', 'ac_service_regions', 'ac_certificates', 'ac_summary', 'ac_contact', 'ac_address' );
$changed = 0;
foreach ( get_posts( array( 'post_type' => 'ac_company', 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $p ) {
	foreach ( $keys as $k ) {
		$v = get_post_meta( $p->ID, $k, true );
		if ( ! $v || ! preg_match( $zh, $v ) ) {
			continue;
		}
		$nv = ac_tr( $v, $phrase_map );
		$left = preg_match( $zh, $nv );
		printf( "  %-26s %-20s %s -> %s%s\n",
			$p->post_title, $k,
			mb_substr( $v, 0, 34 ), mb_substr( $nv, 0, 42 ),
			$left ? '   <仍有中文!>' : ''
		);
		if ( $apply ) {
			update_post_meta( $p->ID, $k, $nv );
		}
		$changed++;
	}
}
echo "  meta 改动条目: $changed\n";

// ---------- 4. 正文 post_content ----------
// 导入脚本生成的简介把中文 products 直接嵌进了正文，光翻 meta 不够。
echo "\n[4] 正文 post_content\n";
$body_changed = 0;
foreach ( get_posts( array(
	'post_type'      => array( 'ac_company', 'ac_news', 'page', 'post' ),
	'posts_per_page' => -1,
	'post_status'    => 'publish',
) ) as $p ) {
	if ( ! preg_match( $zh, $p->post_content ) ) {
		continue;
	}
	$nv   = ac_tr( $p->post_content, $phrase_map );
	$left = preg_match( $zh, $nv );
	printf( "  %-30s %s%s\n", $p->post_title, mb_substr( strip_tags( $nv ), 0, 60 ), $left ? '   <仍有中文!>' : '' );
	if ( $apply ) {
		wp_update_post( array(
			'ID'           => $p->ID,
			'post_content' => $nv,
		) );
	}
	$body_changed++;
}
echo "  正文改动条目: $body_changed\n";

echo "\n完成。\n";
