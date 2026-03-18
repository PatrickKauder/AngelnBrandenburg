<?php
/**
 * Angeln Brandenburg 2026 – XML Sitemap Generator
 * PHASE 3: Automatische Sitemap für Google & Bing
 *
 * Generates:
 *  - /sitemap.xml         (index)
 *  - /sitemap-pages.xml
 *  - /sitemap-posts.xml
 *  - /sitemap-gewaesser.xml
 */

defined( 'ABSPATH' ) || exit;

// Note: If Rank Math or Yoast SEO is active, they handle sitemaps.
// This is a lightweight fallback for sites without those plugins.

add_action( 'init', 'abb_add_sitemap_rewrite_rules' );
function abb_add_sitemap_rewrite_rules() {
	if ( abb_seo_plugin_active() ) return;

	add_rewrite_rule( '^sitemap\.xml$',          'index.php?abb_sitemap=index',    'top' );
	add_rewrite_rule( '^sitemap-pages\.xml$',    'index.php?abb_sitemap=pages',    'top' );
	add_rewrite_rule( '^sitemap-posts\.xml$',    'index.php?abb_sitemap=posts',    'top' );
	add_rewrite_rule( '^sitemap-gewaesser\.xml$','index.php?abb_sitemap=gewaesser','top' );
}

add_filter( 'query_vars', 'abb_sitemap_query_vars' );
function abb_sitemap_query_vars( $vars ) {
	$vars[] = 'abb_sitemap';
	return $vars;
}

add_action( 'template_redirect', 'abb_output_sitemap' );
function abb_output_sitemap() {
	if ( abb_seo_plugin_active() ) return;

	$type = get_query_var( 'abb_sitemap' );
	if ( ! $type ) return;

	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, follow' );

	switch ( $type ) {
		case 'index':
			echo abb_generate_sitemap_index();
			break;
		case 'pages':
			echo abb_generate_sitemap_pages();
			break;
		case 'posts':
			echo abb_generate_sitemap_posts();
			break;
		case 'gewaesser':
			echo abb_generate_sitemap_gewaesser();
			break;
	}
	exit;
}

function abb_sitemap_header() {
	return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<?xml-stylesheet type="text/xsl" href="' . home_url( '/sitemap.xsl' ) . '"?>' . "\n";
}

function abb_generate_sitemap_index() {
	$sitemaps = [
		[ 'url' => home_url( '/sitemap-pages.xml' ),     'lastmod' => date( 'c' ) ],
		[ 'url' => home_url( '/sitemap-posts.xml' ),     'lastmod' => date( 'c' ) ],
		[ 'url' => home_url( '/sitemap-gewaesser.xml' ), 'lastmod' => date( 'c' ) ],
	];

	$xml = abb_sitemap_header();
	$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	foreach ( $sitemaps as $s ) {
		$xml .= "  <sitemap>\n";
		$xml .= '    <loc>' . esc_url( $s['url'] ) . "</loc>\n";
		$xml .= '    <lastmod>' . esc_html( $s['lastmod'] ) . "</lastmod>\n";
		$xml .= "  </sitemap>\n";
	}
	$xml .= '</sitemapindex>';
	return $xml;
}

function abb_generate_sitemap_pages() {
	$pages = get_pages( [ 'post_status' => 'publish' ] );
	$xml   = abb_sitemap_header();
	$xml  .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
	$xml  .= abb_sitemap_entry( home_url( '/' ), '1.0', 'daily', date( 'c' ) );

	foreach ( $pages as $page ) {
		$xml .= abb_sitemap_entry(
			get_permalink( $page ),
			'0.8',
			'weekly',
			get_the_modified_date( 'c', $page )
		);
	}
	$xml .= '</urlset>';
	return $xml;
}

function abb_generate_sitemap_posts() {
	$posts = get_posts( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1000,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	] );

	$xml  = abb_sitemap_header();
	$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

	foreach ( $posts as $post ) {
		$xml .= abb_sitemap_entry(
			get_permalink( $post ),
			'0.6',
			'monthly',
			get_the_modified_date( 'c', $post )
		);
	}
	$xml .= '</urlset>';
	return $xml;
}

function abb_generate_sitemap_gewaesser() {
	$gewaesser = get_posts( [
		'post_type'      => 'angelgewaesser',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );

	$xml  = abb_sitemap_header();
	$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
		. ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

	// Archive
	$xml .= abb_sitemap_entry( home_url( '/gewaesser/' ), '0.9', 'daily', date( 'c' ) );

	foreach ( $gewaesser as $gw ) {
		$img_id  = get_post_thumbnail_id( $gw->ID );
		$img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'abb-hero' ) : '';
		$img_alt = $img_id ? get_post_meta( $img_id, '_wp_attachment_image_alt', true ) : '';

		$entry  = "  <url>\n";
		$entry .= '    <loc>' . esc_url( get_permalink( $gw ) ) . "</loc>\n";
		$entry .= '    <lastmod>' . esc_html( get_the_modified_date( 'c', $gw ) ) . "</lastmod>\n";
		$entry .= "    <changefreq>weekly</changefreq>\n";
		$entry .= "    <priority>0.8</priority>\n";

		if ( $img_url ) {
			$entry .= "    <image:image>\n";
			$entry .= '      <image:loc>' . esc_url( $img_url ) . "</image:loc>\n";
			$entry .= '      <image:title>' . esc_html( get_the_title( $gw ) ) . "</image:title>\n";
			if ( $img_alt ) {
				$entry .= '      <image:caption>' . esc_html( $img_alt ) . "</image:caption>\n";
			}
			$entry .= "    </image:image>\n";
		}
		$entry .= "  </url>\n";
		$xml   .= $entry;
	}
	$xml .= '</urlset>';
	return $xml;
}

function abb_sitemap_entry( $url, $priority = '0.5', $changefreq = 'monthly', $lastmod = '' ) {
	$xml  = "  <url>\n";
	$xml .= '    <loc>' . esc_url( $url ) . "</loc>\n";
	if ( $lastmod ) $xml .= '    <lastmod>' . esc_html( $lastmod ) . "</lastmod>\n";
	$xml .= '    <changefreq>' . esc_html( $changefreq ) . "</changefreq>\n";
	$xml .= '    <priority>' . esc_html( $priority ) . "</priority>\n";
	$xml .= "  </url>\n";
	return $xml;
}

// Notify search engines on new/updated Gewässer
add_action( 'save_post_angelgewaesser', 'abb_ping_search_engines', 20 );
function abb_ping_search_engines( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || get_post_status( $post_id ) !== 'publish' ) return;

	$sitemap_url = urlencode( home_url( '/sitemap.xml' ) );
	wp_remote_get( 'https://www.google.com/ping?sitemap=' . $sitemap_url, [ 'blocking' => false ] );
	wp_remote_get( 'https://www.bing.com/indexnow?url=' . urlencode( get_permalink( $post_id ) )
		. '&host=' . urlencode( home_url() ), [ 'blocking' => false ] );
}

function abb_seo_plugin_active() {
	return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEOP_VERSION' );
}
