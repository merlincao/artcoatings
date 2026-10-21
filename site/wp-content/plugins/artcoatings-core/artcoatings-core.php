<?php
/**
 * Plugin Name: ArtCoatings Core
 * Description: artcoatings.org business core: company directory (ac_company), news (ac_news), industry/region/news taxonomies, and company fields.
 * Version: 0.1.0
 * Author: artcoatings
 * Text Domain: artcoatings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Register custom post types
 */
function ac_register_post_types() {

	// ---- Company directory ----
	register_post_type( 'ac_company', array(
		'labels' => array(
			'name'          => 'Companies',
			'singular_name' => 'Company',
			'menu_name'     => 'Company Directory',
			'add_new'       => 'Add Company',
			'add_new_item'  => 'Add New Company',
			'edit_item'     => 'Edit Company',
			'all_items'     => 'All Companies',
			'search_items'  => 'Search Companies',
		),
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => array( 'slug' => 'company', 'with_front' => false ),
		'menu_icon'     => 'dashicons-building',
		'menu_position' => 20,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest'  => true,
	) );

	// ---- News ----
	register_post_type( 'ac_news', array(
		'labels' => array(
			'name'          => 'News',
			'singular_name' => 'News',
			'menu_name'     => 'News',
			'add_new'       => 'Add News',
			'add_new_item'  => 'Add New Article',
			'edit_item'     => 'Edit Article',
			'all_items'     => 'All News',
			'search_items'  => 'Search News',
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
 * 2. Register taxonomies
 */
function ac_register_taxonomies() {

	// Industry (companies)
	register_taxonomy( 'ac_industry', array( 'ac_company' ), array(
		'labels' => array(
			'name'          => 'Industries',
			'singular_name' => 'Industry',
			'menu_name'     => 'Industries',
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'industry', 'with_front' => false ),
		'show_in_rest'      => true,
	) );

	// Region (companies)
	register_taxonomy( 'ac_region', array( 'ac_company' ), array(
		'labels' => array(
			'name'          => 'Regions',
			'singular_name' => 'Region',
			'menu_name'     => 'Regions',
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'region', 'with_front' => false ),
		'show_in_rest'      => true,
	) );

	// News categories
	register_taxonomy( 'ac_news_cat', array( 'ac_news' ), array(
		'labels' => array(
			'name'          => 'News Categories',
			'singular_name' => 'News Category',
			'menu_name'     => 'News Categories',
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
 * 3. Company fields (native meta — export friendly, no ACF dependency)
 */
function ac_company_fields() {
	return array(
		'ac_website'       => array( 'label' => 'Official website', 'type' => 'url',      'ph' => 'https://' ),
		'ac_phone'         => array( 'label' => 'Phone', 'type' => 'text',     'ph' => '+39 0000 000000' ),
		'ac_contact'       => array( 'label' => 'Contact person',   'type' => 'text',     'ph' => '' ),
		'ac_address'       => array( 'label' => 'Address', 'type' => 'text',     'ph' => 'Street / City / Country' ),
		'ac_founded'       => array( 'label' => 'Founded', 'type' => 'text',     'ph' => '2010' ),
		'ac_main_products' => array( 'label' => 'Main products', 'type' => 'textarea', 'ph' => 'Decorative paints / Mineral plaster / Venetian finishes…' ),
		'ac_service_regions' => array( 'label' => 'Service regions', 'type' => 'textarea', 'ph' => 'Italy / Europe / specific countries' ),
		'ac_certificates'  => array( 'label' => 'Certifications', 'type' => 'textarea', 'ph' => 'ISO 9001 / environmental certifications (publicly verifiable only)' ),
		'ac_summary'       => array( 'label' => 'Short summary', 'type' => 'textarea', 'ph' => 'One or two sentences shown on listing cards' ),
	);
}

function ac_news_fields() {
	return array(
		'ac_source' => array( 'label' => 'Source', 'type' => 'text', 'ph' => 'Trade media / company submission' ),
	);
}

/**
 * 4. Register meta boxes
 */
function ac_add_meta_boxes() {
	add_meta_box( 'ac_company_meta', 'Company information', 'ac_render_company_meta', 'ac_company', 'normal', 'high' );
	add_meta_box( 'ac_news_meta', 'Article information', 'ac_render_news_meta', 'ac_news', 'side', 'default' );
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
	echo '<p class="description">Use the "Featured image" panel for the cover photo; it also serves as the company logo.</p>';
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
 * 5. Save meta
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
 * 6. Admin list columns
 */
function ac_company_columns( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['ac_region']  = 'Region';
			$new['ac_industry'] = 'Industry';
			$new['ac_phone']   = 'Phone';
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
			echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
		}
	}
}
add_action( 'manage_ac_company_posts_custom_column', 'ac_company_column_content', 10, 2 );

/**
 * 7. Directory archive: newest updated first
 */
function ac_company_archive_order( $q ) {
	if ( ! is_admin() && $q->is_main_query() && ( $q->is_post_type_archive( 'ac_company' ) || $q->is_tax( array( 'ac_industry', 'ac_region' ) ) ) ) {
		$q->set( 'orderby', 'modified' );
		$q->set( 'order', 'DESC' );
		$q->set( 'posts_per_page', 24 );
	}
}
add_action( 'pre_get_posts', 'ac_company_archive_order' );
