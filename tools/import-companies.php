<?php
/**
 * 导入企业数据到 artcoatings.org（ac_company）
 *
 * 数据源（二选一，优先 enriched）：
 *   tools/data/companies_enriched.json  —— 由 scrape_assets.py 产出，含本地图片路径与公开证书
 *   tools/data/companies.json           —— 仅结构化字段，无图片
 *
 * 用法（在 artcoatings.org 根目录）：
 *   <php> tools/import-companies.php
 *
 * 依赖：artcoatings-core 插件已启用（注册 ac_company / ac_industry / ac_region）
 * 图片：若 enriched 提供了本地图片，则作为特色图 + 图集导入（无需联网）。
 * 证书：若 enriched 提供了 certs，则写入 ac_certificates 并标记为未核验。
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
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

if ( ! post_type_exists( 'ac_company' ) ) {
	fwrite( STDERR, "错误：ac_company 文章类型不存在，请先启用 artcoatings-core 插件（运行 tools/cli-theme.php）。\n" );
	exit( 1 );
}

$enriched = dirname( __DIR__ ) . '/tools/data/companies_enriched.json';
$plain    = dirname( __DIR__ ) . '/tools/data/companies.json';
$json     = file_exists( $enriched ) ? $enriched : $plain;

if ( ! file_exists( $json ) ) {
	fwrite( STDERR, "错误：找不到数据文件 $json，请先运行 export_xlsx.py / scrape_assets.py。\n" );
	exit( 1 );
}
$data = json_decode( file_get_contents( $json ), true );
if ( ! is_array( $data ) ) {
	fwrite( STDERR, "错误：JSON 解析失败。\n" );
	exit( 1 );
}

$country_map = array(
	'意大利' => 'Italy', '法国' => 'France', '德国' => 'Germany', '西班牙' => 'Spain',
	'英国'   => 'UK', '葡萄牙' => 'Portugal', '荷兰' => 'Netherlands', '比利时' => 'Belgium',
	'瑞士'   => 'Switzerland', '奥地利' => 'Austria', '波兰' => 'Poland',
);

// 把本地图片文件作为附件导入（无需联网；先复制到临时区，保留源文件）
function ac_import_local_image( $local_path, $post_id, $desc ) {
	if ( ! file_exists( $local_path ) ) {
		return 0;
	}
	$tmp = wp_tempnam( basename( $local_path ) );
	if ( ! $tmp || ! copy( $local_path, $tmp ) ) {
		return 0;
	}
	$file = array(
		'name'     => basename( $local_path ),
		'tmp_name' => $tmp,
	);
	$id = media_handle_sideload( $file, $post_id, $desc );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	return $id;
}

$created = 0;
$skipped = 0;
$updated = 0;

foreach ( $data as $rec ) {
	$brand = trim( $rec['brand'] ?? '' );
	if ( ! $brand ) {
		continue;
	}

	// 分类推导
	$region   = $country_map[ $rec['country'] ?? '' ] ?? ( $rec['country'] ?? 'Italy' );
	$p_blob   = strtolower( ( $rec['products'] ?? '' ) . ' ' . $brand . ' ' . ( $rec['company'] ?? '' ) );
	$industry = 'Decorative Coatings';
	if ( stripos( $p_blob, 'microcement' ) !== false ) {
		$industry = 'Microcement';
	} elseif ( stripos( $p_blob, 'mineral' ) !== false || stripos( $p_blob, 'lime' ) !== false || strpos( $p_blob, '灰泥' ) !== false || strpos( $p_blob, '矿物' ) !== false ) {
		$industry = 'Mineral & Lime Coatings';
	} elseif ( stripos( $p_blob, 'metal' ) !== false ) {
		$industry = 'Metallic Coatings';
	} elseif ( stripos( $p_blob, 'textur' ) !== false || strpos( $p_blob, '纹理' ) !== false || strpos( $p_blob, '肌理' ) !== false ) {
		$industry = 'Textured Coatings';
	}

	$company = $rec['company'] ?: $brand;
	$prod    = $rec['products'] ?: 'decorative coatings';
	$addr    = $rec['address'] ? ' Its publicly listed address is ' . esc_html( $rec['address'] ) . '.' : '';

	// 维基百科风格内容 + References
	$content  = '<p><strong>' . esc_html( $brand ) . '</strong> is a ' . strtolower( $industry ) . ' brand operated by <strong>' . esc_html( $company ) . '</strong>, within the Italian and European art coatings sector. It is listed in the ArtCoatings directory, a Sicily-based reference of art coatings brands.</p>';
	$content .= '<h2>Overview</h2><p>' . esc_html( $brand ) . ' focuses on ' . esc_html( $prod ) . '.' . $addr . ' Listings on ArtCoatings preserve publicly verifiable information only; contact details are included solely where confirmable from open sources.</p>';
	$content .= '<h2>Products and positioning</h2><p>' . esc_html( $prod ) . '</p>';
	$content .= '<h2>References</h2><ul>';
	if ( ! empty( $rec['website'] ) ) {
		$content .= '<li>Official website / public catalogue: <a href="' . esc_url( $rec['website'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $rec['website'] ) . '</a></li>';
	}
	if ( ! empty( $rec['source'] ) ) {
		$content .= '<li>Source / verification: <a href="' . esc_url( $rec['source'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $rec['source'] ) . '</a></li>';
	}
	if ( ! empty( $rec['x_link'] ) ) {
		$content .= '<li>X / Twitter (search entry, not a confirmed official account): <a href="' . esc_url( $rec['x_link'] ) . '" target="_blank" rel="nofollow noopener">X search</a></li>';
	}
	$content .= '</ul>';

	// 去重：同名则跳过
	$exists = get_page_by_title( $brand, OBJECT, 'ac_company' );
	if ( $exists ) {
		$skipped++;
		echo "跳过（已存在）: $brand\n";
		continue;
	}

	$post_id = wp_insert_post( array(
		'post_title'   => $brand,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'ac_company',
		'post_name'    => sanitize_title( $rec['slug'] ?: $brand ),
	), true );

	if ( is_wp_error( $post_id ) ) {
		fwrite( STDERR, "导入失败: $brand -> " . $post_id->get_error_message() . "\n" );
		continue;
	}

	// Meta
	update_post_meta( $post_id, 'ac_website', esc_url_raw( $rec['website'] ?? '' ) );
	update_post_meta( $post_id, 'ac_phone',   sanitize_text_field( $rec['phone'] ?? '' ) );
	update_post_meta( $post_id, 'ac_email',   sanitize_email( $rec['email'] ?? '' ) );
	update_post_meta( $post_id, 'ac_address', sanitize_text_field( $rec['address'] ?? '' ) );
	update_post_meta( $post_id, 'ac_x_link',  esc_url_raw( $rec['x_link'] ?? '' ) );
	update_post_meta( $post_id, 'ac_main_products', sanitize_textarea_field( $prod ) );
	update_post_meta( $post_id, 'ac_service_regions', sanitize_text_field( $region ) );
	update_post_meta( $post_id, 'ac_certificates', '' );
	update_post_meta( $post_id, 'ac_summary', wp_trim_words( $prod, 24, '…' ) );

	// 图片：enriched 提供的本地文件 -> 特色图 + 图集
	$images = $rec['images'] ?? array();
	if ( is_array( $images ) && $images ) {
		$base = dirname( __DIR__ );
		$first = true;
		$gallery_ids = array();
		foreach ( $images as $rel ) {
			$local = $base . '/' . $rel;
			$aid = ac_import_local_image( $local, $post_id, $brand . ' image' );
			if ( ! $aid ) {
				continue;
			}
			if ( $first ) {
				set_post_thumbnail( $post_id, $aid );
				$first = false;
			} else {
				$gallery_ids[] = $aid;
			}
		}
		if ( $gallery_ids ) {
			update_post_meta( $post_id, 'ac_gallery', implode( ',', $gallery_ids ) );
		}
	}

	// 证书：enriched 提供的公开来源证书（标记为未核验）
	if ( ! empty( $rec['certs'] ) && is_array( $rec['certs'] ) ) {
		update_post_meta( $post_id, 'ac_certificates', sanitize_textarea_field( implode( ', ', array_unique( $rec['certs'] ) ) ) );
		update_post_meta( $post_id, 'ac_cert_unverified', '1' );
	}

	// 分类
	wp_set_object_terms( $post_id, array( $industry ), 'ac_industry' );
	wp_set_object_terms( $post_id, array( $region ), 'ac_region' );

	$created++;
	echo "已导入: $brand (#$post_id) [$region / $industry] images=" . count( $images ) . " certs=" . count( $rec['certs'] ?? array() ) . "\n";
}

echo "\n完成：新建 $created，跳过 $skipped，更新 $updated。\n";
