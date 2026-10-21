<?php
/**
 * Plugin Name: ArtCoatings Core
 * Description: artcoatings.org 业务核心：企业黄页(ac_company)、新闻(ac_news)、行业/地区/新闻分类，以及企业字段。
 * Version: 0.1.0
 * Author: artcoatings
 * Text Domain: artcoatings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. 注册自定义文章类型
 */
function ac_register_post_types() {

	// ---- 企业黄页 ----
	register_post_type( 'ac_company', array(
		'labels' => array(
			'name'          => '企业黄页',
			'singular_name' => '企业',
			'menu_name'     => '企业黄页',
			'add_new'       => '新增企业',
			'add_new_item'  => '新增企业',
			'edit_item'     => '编辑企业',
			'all_items'     => '全部企业',
			'search_items'  => '搜索企业',
		),
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => array( 'slug' => 'company', 'with_front' => false ),
		'menu_icon'     => 'dashicons-building',
		'menu_position' => 20,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );

	// ---- 新闻 ----
	register_post_type( 'ac_news', array(
		'labels' => array(
			'name'          => '新闻资讯',
			'singular_name' => '新闻',
			'menu_name'     => '新闻资讯',
			'add_new'       => '新增新闻',
			'add_new_item'  => '新增新闻',
			'edit_item'     => '编辑新闻',
			'all_items'     => '全部新闻',
			'search_items'  => '搜索新闻',
		),
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => array( 'slug' => 'news', 'with_front' => false ),
		'menu_icon'     => 'dashicons-megaphone',
		'menu_position' => 21,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );
}
add_action( 'init', 'ac_register_post_types' );

/**
 * 2. 注册分类法
 */
function ac_register_taxonomies() {

	// 行业（企业）
	register_taxonomy( 'ac_industry', array( 'ac_company' ), array(
		'labels' => array(
			'name'          => '行业分类',
			'singular_name' => '行业',
			'menu_name'     => '行业分类',
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'industry', 'with_front' => false ),
		'show_in_rest'      => true,
	) );

	// 地区（企业）
	register_taxonomy( 'ac_region', array( 'ac_company' ), array(
		'labels' => array(
			'name'          => '地区分类',
			'singular_name' => '地区',
			'menu_name'     => '地区分类',
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'region', 'with_front' => false ),
		'show_in_rest'      => true,
	) );

	// 新闻分类
	register_taxonomy( 'ac_news_cat', array( 'ac_news' ), array(
		'labels' => array(
			'name'          => '新闻分类',
			'singular_name' => '新闻分类',
			'menu_name'     => '新闻分类',
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'news-cat', 'with_front' => false ),
		'show_in_rest'      => true,
	) );
}
add_action( 'init', 'ac_register_taxonomies' );

/**
 * 3. 企业字段（原生 meta，导出友好，不依赖 ACF）
 */
function ac_company_fields() {
	return array(
		'ac_website'       => array( 'label' => '官方网站', 'type' => 'url',      'ph' => 'https://' ),
		'ac_phone'         => array( 'label' => '联系电话', 'type' => 'text',     'ph' => '0571-xxxxxxx' ),
		'ac_contact'       => array( 'label' => '联系人',   'type' => 'text',     'ph' => '' ),
		'ac_address'       => array( 'label' => '公司地址', 'type' => 'text',     'ph' => '省 / 市 / 详细地址' ),
		'ac_founded'       => array( 'label' => '成立年份', 'type' => 'text',     'ph' => '2010' ),
		'ac_main_products' => array( 'label' => '主营产品', 'type' => 'textarea', 'ph' => '艺术漆 / 无机灰泥 / 小羊皮…' ),
		'ac_service_regions' => array( 'label' => '服务地区', 'type' => 'textarea', 'ph' => 'Italy / Europe / 具体国家与地区' ),
		'ac_certificates'  => array( 'label' => '资质证书', 'type' => 'textarea', 'ph' => 'ISO 9001 / 环保认证等（仅填可公开核验的）' ),
		'ac_summary'       => array( 'label' => '一句话简介', 'type' => 'textarea', 'ph' => '用于列表卡片展示的 1-2 句话' ),
	);
}

function ac_news_fields() {
	return array(
		'ac_source' => array( 'label' => '来源', 'type' => 'text', 'ph' => '行业媒体 / 企业投稿' ),
	);
}

/**
 * 4. 注册 meta box
 */
function ac_add_meta_boxes() {
	add_meta_box( 'ac_company_meta', '企业信息', 'ac_render_company_meta', 'ac_company', 'normal', 'high' );
	add_meta_box( 'ac_news_meta', '新闻信息', 'ac_render_news_meta', 'ac_news', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'ac_add_meta_boxes' );

function ac_render_company_meta( $post ) {
	wp_nonce_field( 'ac_save_meta', 'ac_meta_nonce' );
	echo '<table class="form-table">';
	foreach ( ac_company_fields() as $key => $cfg ) {
		$val = get_post_meta( $post->ID, $key, true );
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label></th><td>';
		if ( 'textarea' === $cfg['type'] ) {
			echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" rows="3" style="width:100%" placeholder="' . esc_attr( $cfg['ph'] ) . '">' . esc_textarea( $val ) . '</textarea>';
		} else {
			echo '<input type="' . esc_attr( $cfg['type'] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" style="width:100%" placeholder="' . esc_attr( $cfg['ph'] ) . '" />';
		}
		echo '</td></tr>';
	}
	echo '</table>';
	echo '<p class="description">封面图请使用右侧「特色图片」；企业 Logo 也可用特色图片承担。</p>';
}

function ac_render_news_meta( $post ) {
	wp_nonce_field( 'ac_save_meta', 'ac_meta_nonce' );
	foreach ( ac_news_fields() as $key => $cfg ) {
		$val = get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $cfg['label'] ) . '</strong></label><br>';
		echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" style="width:100%" placeholder="' . esc_attr( $cfg['ph'] ) . '" /></p>';
	}
}

/**
 * 5. 保存 meta
 */
function ac_save_meta( $post_id ) {
	if ( ! isset( $_POST['ac_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ac_meta_nonce'], 'ac_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$fields = array_merge( ac_company_fields(), ac_news_fields() );
	foreach ( $fields as $key => $cfg ) {
		if ( isset( $_POST[ $key ] ) ) {
			$raw = wp_unslash( $_POST[ $key ] );
			$val = ( 'url' === $cfg['type'] ) ? esc_url_raw( $raw ) : sanitize_textarea_field( $raw );
			update_post_meta( $post_id, $key, $val );
		}
	}
}
add_action( 'save_post', 'ac_save_meta' );

/**
 * 6. 后台列表列
 */
function ac_company_columns( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['ac_region']  = '地区';
			$new['ac_industry'] = '行业';
			$new['ac_phone']   = '电话';
		}
	}
	return $new;
}
add_filter( 'manage_ac_company_posts_columns', 'ac_company_columns' );

function ac_company_column_content( $col, $post_id ) {
	if ( 'ac_phone' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'ac_phone', true ) );
	} elseif ( 'ac_region' === $col || 'ac_industry' === $col ) {
		$terms = get_the_terms( $post_id, $col );
		if ( $terms && ! is_wp_error( $terms ) ) {
			echo esc_html( implode( '、', wp_list_pluck( $terms, 'name' ) ) );
		}
	}
}
add_action( 'manage_ac_company_posts_custom_column', 'ac_company_column_content', 10, 2 );

/**
 * 7. 黄页归档按更新时间倒序
 */
function ac_company_archive_order( $q ) {
	if ( ! is_admin() && $q->is_main_query() && ( $q->is_post_type_archive( 'ac_company' ) || $q->is_tax( array( 'ac_industry', 'ac_region' ) ) ) ) {
		$q->set( 'orderby', 'modified' );
		$q->set( 'order', 'DESC' );
		$q->set( 'posts_per_page', 24 );
	}
}
add_action( 'pre_get_posts', 'ac_company_archive_order' );
