<?php
/**
 * CLI：自动完成 WordPress 安装
 * 用法（在 artcoatings.org 根目录）：php tools/cli-install.php
 */

$_SERVER['HTTP_HOST']       = 'localhost:8080';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['SERVER_PORT']     = '8080';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTPS']           = '';

define( 'WP_INSTALLING', true );

require_once dirname( __DIR__ ) . '/site/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( is_blog_installed() ) {
	fwrite( STDERR, "WordPress 已安装，跳过。\n" );
	exit( 0 );
}

$result = wp_install(
	'ArtCoatings — Art Coatings Directory', // 站点标题
	'admin',                            // 管理员账号
	'admin@artcoatings.local',          // 管理员邮箱
	true,                               // 允许搜索引擎索引
	'',
	'Admin@2026!ac',                    // 管理员密码
	'en_US'                             // 语言
);

echo "安装完成\n";
echo "管理员ID: " . $result['user_id'] . "\n";
echo "账号: admin\n";
echo "密码: Admin@2026!ac\n";
