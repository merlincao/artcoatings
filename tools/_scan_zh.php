<?php
/**
 * 扫描站点里残留的中文：分类名称/slug + 企业 meta 内容。
 * 只读，不改数据。
 */
define( 'WP_USE_THEMES', false );
require dirname( __DIR__ ) . '/site/wp-load.php';

$zh = '/[\x{4e00}-\x{9fff}]/u';

echo "=== 分类法 term ===\n";
foreach ( array( 'ac_industry', 'ac_region', 'ac_news_cat' ) as $tx ) {
	$terms = get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) ) {
		continue;
	}
	echo "-- $tx (" . count( $terms ) . ") --\n";
	foreach ( $terms as $t ) {
		$flag = preg_match( $zh, $t->name ) ? '  <含中文>' : '';
		printf( "  %-38s | %-42s | %d%s\n", $t->name, $t->slug, $t->count, $flag );
	}
}

echo "\n=== 企业 meta 含中文的字段 ===\n";
$keys = array( 'ac_main_products', 'ac_service_regions', 'ac_certificates', 'ac_summary', 'ac_contact', 'ac_address' );
$posts = get_posts( array( 'post_type' => 'ac_company', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
foreach ( $posts as $p ) {
	foreach ( $keys as $k ) {
		$v = get_post_meta( $p->ID, $k, true );
		if ( $v && preg_match( $zh, $v ) ) {
			printf( "  %-28s %-22s %s\n", $p->post_title, $k, mb_substr( preg_replace( '/\s+/', ' ', $v ), 0, 60 ) );
		}
	}
}

echo "\n=== 正文内容含中文 ===\n";
foreach ( get_posts( array(
	'post_type'      => array( 'ac_company', 'ac_news', 'page', 'post' ),
	'posts_per_page' => -1,
	'post_status'    => 'publish',
) ) as $p ) {
	$blob = $p->post_title . ' ' . $p->post_content . ' ' . $p->post_excerpt;
	if ( preg_match( $zh, $blob ) ) {
		preg_match_all( '/[\x{4e00}-\x{9fff}]+/u', $blob, $m );
		printf( "  [%s] %-28s %s\n", $p->post_type, $p->post_title, implode( ' / ', array_slice( array_unique( $m[0] ), 0, 8 ) ) );
	}
}

echo "\n=== 站点选项 ===\n";
foreach ( array( 'blogname', 'blogdescription', 'WPLANG', 'timezone_string' ) as $o ) {
	printf( "  %-18s %s\n", $o, var_export( get_option( $o ), true ) );
}
