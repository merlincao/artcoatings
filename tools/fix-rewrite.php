<?php
/**
 * 修复 /news-cat/* 分类页 404
 *
 * 根因：站点把 WordPress 内置「分类目录」的 base（category_base 选项）设成了 news-cat，
 * 而自定义分类 ac_news_cat 的 rewrite slug 也是 news-cat。两者同前缀时，
 * WP 内置分类规则先注册先匹配（news-cat/(.+?) => index.php?category_name=...），
 * 请求被吃掉后查不到任何内置分类文章 -> 404。
 *
 * 修复：把 category_base 改回默认的 category（本站点不使用内置文章分类），刷新规则。
 * 注意 update_option 与 flush 必须分两次请求执行：分类法在 init 阶段就已按当时的
 * category_base 注册完毕，同一进程内先改后刷，刷出来的仍是旧规则。
 *
 * 用法：php tools/fix-rewrite.php   （连跑两次即可，脚本幂等）
 */
define( 'WP_USE_THEMES', false );
require dirname( __DIR__ ) . '/site/wp-load.php';

if ( 'category' !== get_option( 'category_base' ) ) {
	update_option( 'category_base', 'category' );
	echo "已将内置分类 base 改回 category，请再执行一次本脚本刷新规则\n";
	exit;
}

flush_rewrite_rules( true );

$rules = get_option( 'rewrite_rules' );
$stale = 0;
$ok    = 0;
foreach ( $rules as $k => $v ) {
	if ( strpos( $k, 'news-cat' ) === 0 ) {
		if ( strpos( $v, 'category_name' ) !== false ) {
			$stale++;
		} else {
			$ok++;
		}
	}
}
echo 'news-cat 规则：ac_news_cat ' . $ok . ' 条 / 残留内置 category_name ' . $stale . ' 条' . PHP_EOL;
echo $stale === 0 ? "[OK] /news-cat/<slug> 已指向新闻分类归档\n" : "[MISS] 仍有冲突规则\n";
