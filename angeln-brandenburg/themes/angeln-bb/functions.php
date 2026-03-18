<?php
/**
 * Angeln Brandenburg 2026 – Child Theme functions.php
 * Parent theme: Astra
 *
 * PHASE 2: Performance, mobile enhancements, asset loading
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. ENQUEUE STYLES & SCRIPTS (Performance-optimized)
// ============================================================

add_action( 'wp_enqueue_scripts', 'abb_enqueue_assets' );
function abb_enqueue_assets() {
	// Parent Astra theme
	wp_enqueue_style(
		'astra-theme-css',
		get_template_directory_uri() . '/style.css',
		[],
		wp_get_theme( 'astra' )->get( 'Version' )
	);

	// Child theme CSS
	wp_enqueue_style(
		'angeln-bb-style',
		get_stylesheet_directory_uri() . '/style.css',
		[ 'astra-theme-css' ],
		filemtime( get_stylesheet_directory() . '/style.css' )
	);

	// Google Fonts – Montserrat (preconnect first, then font)
	wp_enqueue_style(
		'abb-google-fonts',
		'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
		[],
		null
	);

	// Main JS – deferred
	wp_enqueue_script(
		'angeln-bb-main',
		get_stylesheet_directory_uri() . '/js/main.js',
		[],
		filemtime( get_stylesheet_directory() . '/js/main.js' ),
		true  // footer
	);

	// Pass PHP data to JS (nonce, API URLs)
	wp_localize_script( 'angeln-bb-main', 'abbData', [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'abb_nonce' ),
		'pegelApiBase' => ABB_PEGELONLINE_API,
		'siteUrl'      => home_url(),
	] );
}

// Preconnect to Google Fonts for performance
add_action( 'wp_head', 'abb_preconnect_hints', 1 );
function abb_preconnect_hints() {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	echo '<link rel="dns-prefetch" href="https://pegelonline.wsv.de">' . "\n";
}

// ============================================================
// 2. THEME SUPPORTS
// ============================================================

add_action( 'after_setup_theme', 'abb_theme_setup' );
function abb_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' ] );
	add_theme_support( 'custom-logo', [
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	] );

	// Image sizes optimized for mobile
	add_image_size( 'abb-card',   640, 360, true );   // Card 16:9
	add_image_size( 'abb-hero',  1200, 600, true );   // Hero banner
	add_image_size( 'abb-thumb',  400, 300, true );   // Small thumbnail
}

// ============================================================
// 3. ASTRA CUSTOMIZER DEFAULTS (Deep Water Blue & Reed Green)
// ============================================================

add_filter( 'astra_theme_defaults', 'abb_astra_defaults' );
function abb_astra_defaults( $defaults ) {
	// Header
	$defaults['header-bg-color']               = '#1a3a5c';
	$defaults['header-color']                  = '#e8f4f8';
	$defaults['header-link-color']             = '#e8f4f8';
	$defaults['header-link-h-color']           = '#ffffff';

	// Menu
	$defaults['menu-bg-color']                 = '#1a3a5c';
	$defaults['menu-color']                    = '#e8f4f8';
	$defaults['menu-h-bg-color']               = 'rgba(255,255,255,0.12)';

	// Footer
	$defaults['footer-bg-color']               = '#0d2137';
	$defaults['footer-color']                  = '#b0bec5';
	$defaults['footer-link-color']             = '#5b9bd5';

	// Content
	$defaults['link-color']                    = '#2d5a8e';
	$defaults['link-h-color']                  = '#3d6b4f';
	$defaults['content-bg-color']              = '#ffffff';

	// Typography
	$defaults['body-font-family']              = 'inherit';
	$defaults['headings-font-family']          = 'Montserrat, sans-serif';
	$defaults['body-font-size']                = '16';

	// Buttons
	$defaults['button-bg-color']               = '#3d6b4f';
	$defaults['button-bg-h-color']             = '#5a9470';
	$defaults['button-color']                  = '#ffffff';
	$defaults['button-border-radius']          = '9999';

	return $defaults;
}

// ============================================================
// 4. PERFORMANCE: Remove unnecessary WordPress features
// ============================================================

// Remove emoji scripts (saves ~15kB)
remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles',     'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles',  'print_emoji_styles' );

// Remove oEmbed discovery links
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

// Remove RSD link
remove_action( 'wp_head', 'rsd_link' );

// Remove wlwmanifest
remove_action( 'wp_head', 'wlwmanifest_link' );

// Remove shortlink
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

// Remove REST API link from head
remove_action( 'wp_head', 'rest_output_link_wp_head' );

// Disable XML-RPC
add_filter( 'xmlrpc_enabled', '__return_false' );

// Remove query strings from static assets (cache-busting)
add_filter( 'script_loader_src', 'abb_remove_query_strings', 15 );
add_filter( 'style_loader_src',  'abb_remove_query_strings', 15 );
function abb_remove_query_strings( $src ) {
	if ( strpos( $src, '?ver=' ) || strpos( $src, '&ver=' ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}

// ============================================================
// 5. LAZY LOADING IMAGES (native + JS fallback)
// ============================================================

add_filter( 'the_content',   'abb_add_lazy_loading' );
add_filter( 'post_thumbnail_html', 'abb_add_lazy_loading' );
function abb_add_lazy_loading( $content ) {
	return str_replace( '<img ', '<img loading="lazy" decoding="async" ', $content );
}

// ============================================================
// 6. CUSTOM EXCERPT LENGTH
// ============================================================

add_filter( 'excerpt_length', fn() => 30 );
add_filter( 'excerpt_more',   fn() => '…' );

// ============================================================
// 7. SECURITY: Login URL customization helper (WPS Hide Login)
// ============================================================

// If WPS Hide Login plugin is not used, this adds a basic check
add_action( 'init', 'abb_redirect_default_login' );
function abb_redirect_default_login() {
	if ( defined( 'WPS_HIDE_LOGIN' ) ) {
		return; // Plugin handles it
	}
	// Warn admin to install WPS Hide Login
	if ( is_admin() && current_user_can( 'manage_options' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-warning"><p><strong>Angeln Brandenburg:</strong> Bitte installiere <a href="https://wordpress.org/plugins/wps-hide-login/">WPS Hide Login</a> um die Login-URL zu verschleiern.</p></div>';
		} );
	}
}

// ============================================================
// 8. BREADCRUMBS (SEO + UX)
// ============================================================

function abb_breadcrumbs() {
	if ( is_front_page() ) return;

	$separator = '<span class="abb-breadcrumb-sep" aria-hidden="true"> › </span>';
	$output    = '<nav class="abb-breadcrumbs" aria-label="Brotkrumen-Navigation"><ol itemscope itemtype="https://schema.org/BreadcrumbList">';
	$position  = 1;

	// Home
	$output .= sprintf(
		'<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><a itemprop="item" href="%s"><span itemprop="name">Home</span></a><meta itemprop="position" content="%d"></li>',
		esc_url( home_url( '/' ) ),
		$position
	);
	$position++;

	if ( is_singular( 'angelgewaesser' ) ) {
		$output .= $separator;
		$output .= sprintf(
			'<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><a itemprop="item" href="%s"><span itemprop="name">Gewässer</span></a><meta itemprop="position" content="%d"></li>',
			esc_url( home_url( '/gewaesser/' ) ),
			$position
		);
		$position++;
		$output .= $separator;
		$output .= sprintf(
			'<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><span itemprop="name">%s</span><meta itemprop="position" content="%d"></li>',
			esc_html( get_the_title() ),
			$position
		);
	} elseif ( is_single() ) {
		$output .= $separator;
		$cats = get_the_category();
		if ( $cats ) {
			$output .= sprintf(
				'<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><a itemprop="item" href="%s"><span itemprop="name">%s</span></a><meta itemprop="position" content="%d"></li>',
				esc_url( get_category_link( $cats[0]->term_id ) ),
				esc_html( $cats[0]->name ),
				$position
			);
			$position++;
			$output .= $separator;
		}
		$output .= sprintf(
			'<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><span itemprop="name">%s</span><meta itemprop="position" content="%d"></li>',
			esc_html( get_the_title() ),
			$position
		);
	} elseif ( is_page() ) {
		$output .= $separator;
		$output .= sprintf(
			'<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><span itemprop="name">%s</span><meta itemprop="position" content="%d"></li>',
			esc_html( get_the_title() ),
			$position
		);
	}

	$output .= '</ol></nav>';
	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// ============================================================
// 9. INCLUDE MODULAR FILES
// ============================================================

$includes = [
	'/inc/cpt-angelgewaesser.php',
	'/inc/schema-org.php',
	'/inc/sitemap.php',
];

foreach ( $includes as $file ) {
	$path = get_stylesheet_directory() . $file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
