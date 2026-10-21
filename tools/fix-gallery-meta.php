<?php
/**
 * 一次性修复：把 ac_gallery 字段从「数组」归一化成「逗号分隔字符串」。
 *
 * 背景：attach-images.php 早期版本用 update_post_meta(..., array_slice($ids,1))
 * 直接存了 PHP 数组；模板 single-ac_company.php 里 explode(',', $gallery)
 * 收到数组会抛 TypeError，整页直接 500。模板已加 ac_parse_id_list() 做防御，
 * 这里把库里的存量数据一并洗回字符串，保证两种读法都安全。
 *
 * 用法（CLI）：
 *   php tools/fix-gallery-meta.php
 */

define( 'WP_USE_THEMES', false );
require dirname( __DIR__ ) . '/site/wp-load.php';

global $wpdb;

$rows = $wpdb->get_results(
	"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'ac_gallery'"
);

echo "ac_gallery 记录总数: " . count( $rows ) . "\n";

$fixed = 0;
$ok    = 0;
$empty = 0;

foreach ( $rows as $r ) {
	$val = maybe_unserialize( $r->meta_value );

	if ( is_array( $val ) ) {
		$ids = array();
		foreach ( $val as $v ) {
			if ( is_numeric( $v ) ) {
				$ids[] = (int) $v;
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		update_post_meta( (int) $r->post_id, 'ac_gallery', implode( ',', $ids ) );
		$fixed++;
		echo "  [修复] post #{$r->post_id} -> " . ( $ids ? implode( ',', $ids ) : '(空)' ) . "\n";
		continue;
	}

	$s = trim( (string) $val );
	if ( '' === $s ) {
		$empty++;
	} else {
		$ok++;
	}
}

echo "\n完成：修复 $fixed 条，原本已是字符串 $ok 条，空值 $empty 条。\n";

// 复查：确认库里再无数组形态
$still = 0;
foreach ( $wpdb->get_results( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'ac_gallery'" ) as $r ) {
	if ( is_array( maybe_unserialize( $r->meta_value ) ) ) {
		$still++;
	}
}
echo "复查：仍为数组形态的条目 = $still " . ( 0 === $still ? "[OK]\n" : "[MISS]\n" );
