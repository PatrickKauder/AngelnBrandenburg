<?php
/**
 * Angeln Brandenburg 2026 – Schema.org JSON-LD Generator
 * PHASE 3: Structured Data für Gewässer-Profile
 *
 * Outputs:
 *  - Place schema for each Angelgewässer
 *  - FAQPage schema for FAQ sections
 *  - WebSite schema on front page
 *  - BreadcrumbList schema (via functions.php)
 *  - NewsArticle schema on news posts
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// 1. OUTPUT JSON-LD IN <head>
// ============================================================

add_action( 'wp_head', 'abb_output_schema_jsonld', 5 );
function abb_output_schema_jsonld() {
	$schemas = [];

	if ( is_front_page() ) {
		$schemas[] = abb_schema_website();
		$schemas[] = abb_schema_organization();
	}

	if ( is_singular( 'angelgewaesser' ) ) {
		$schemas[] = abb_schema_gewaesser( get_the_ID() );
		$faq = abb_schema_faq_gewaesser( get_the_ID() );
		if ( $faq ) $schemas[] = $faq;
	}

	if ( is_singular( 'post' ) ) {
		$schemas[] = abb_schema_news_article( get_the_ID() );
	}

	if ( is_post_type_archive( 'angelgewaesser' ) ) {
		$schemas[] = abb_schema_gewaesser_archive();
	}

	foreach ( array_filter( $schemas ) as $schema ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
	}
}

// ============================================================
// 2. WEBSITE SCHEMA
// ============================================================

function abb_schema_website() {
	return [
		'@context' => 'https://schema.org',
		'@type'    => 'WebSite',
		'name'     => get_bloginfo( 'name' ),
		'url'      => home_url( '/' ),
		'description' => get_bloginfo( 'description' ),
		'inLanguage'  => 'de-DE',
		'potentialAction' => [
			'@type'       => 'SearchAction',
			'target'      => [
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			],
			'query-input' => 'required name=search_term_string',
		],
	];
}

// ============================================================
// 3. ORGANIZATION SCHEMA
// ============================================================

function abb_schema_organization() {
	return [
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url( '/' ),
		'logo'        => [
			'@type' => 'ImageObject',
			'url'   => abb_get_logo_url(),
		],
		'contactPoint' => [
			'@type'             => 'ContactPoint',
			'contactType'       => 'customer service',
			'availableLanguage' => 'German',
		],
		'sameAs' => [
			// Add social media URLs here
		],
	];
}

function abb_get_logo_url() {
	$logo_id = get_theme_mod( 'custom_logo' );
	return $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
}

// ============================================================
// 4. GEWÄSSER / PLACE SCHEMA
// ============================================================

function abb_schema_gewaesser( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) return null;

	$lat        = get_post_meta( $post_id, 'abb_latitude', true );
	$lon        = get_post_meta( $post_id, 'abb_longitude', true );
	$flaeche    = get_post_meta( $post_id, 'abb_flaeche_ha', true );
	$tiefe      = get_post_meta( $post_id, 'abb_tiefe_m', true );
	$ort        = get_post_meta( $post_id, 'abb_ort', true );
	$bundesland = get_post_meta( $post_id, 'abb_bundesland', true ) ?: 'Brandenburg';
	$erlaubnis  = get_post_meta( $post_id, 'abb_erlaubnis', true );
	$fischarten = abb_get_fischarten_array( $post_id );

	$schema = [
		'@context'    => 'https://schema.org',
		'@type'       => [ 'Place', 'LakeBodyOfWater' ],
		'@id'         => get_permalink( $post_id ) . '#gewässer',
		'name'        => get_the_title( $post_id ),
		'url'         => get_permalink( $post_id ),
		'description' => wp_strip_all_tags( get_the_excerpt( $post ) ) ?: wp_trim_words( get_the_content( null, false, $post ), 40 ),
		'inLanguage'  => 'de-DE',
		'isPartOf'    => [
			'@type' => 'Country',
			'name'  => 'Deutschland',
		],
		'address' => [
			'@type'           => 'PostalAddress',
			'addressLocality' => $ort,
			'addressRegion'   => $bundesland,
			'addressCountry'  => 'DE',
		],
	];

	// Coordinates
	if ( $lat && $lon ) {
		$schema['geo'] = [
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lon,
		];
		$schema['hasMap'] = sprintf(
			'https://www.google.com/maps?q=%s,%s',
			$lat,
			$lon
		);
	}

	// Physical properties
	$additional = [];
	if ( $tiefe ) {
		$additional[] = [
			'@type' => 'PropertyValue',
			'name'  => 'Maximale Wassertiefe',
			'value' => $tiefe . ' Meter',
		];
	}
	if ( $flaeche ) {
		$additional[] = [
			'@type' => 'PropertyValue',
			'name'  => 'Gewässerfläche',
			'value' => $flaeche . ' Hektar',
		];
	}
	if ( $erlaubnis ) {
		$additional[] = [
			'@type' => 'PropertyValue',
			'name'  => 'Angel-Erlaubnis',
			'value' => $erlaubnis,
		];
	}

	// Fischarten as amenityFeature
	if ( ! empty( $fischarten ) ) {
		foreach ( $fischarten as $fischart ) {
			$additional[] = [
				'@type'      => 'LocationFeatureSpecification',
				'name'       => 'Fischart',
				'value'      => trim( $fischart ),
				'valueReference' => 'Angelgewässer Brandenburg',
			];
		}
	}

	if ( ! empty( $additional ) ) {
		$schema['additionalProperty'] = $additional;
	}

	// Featured image
	$img_id = get_post_thumbnail_id( $post_id );
	if ( $img_id ) {
		$img_data = wp_get_attachment_image_src( $img_id, 'abb-hero' );
		if ( $img_data ) {
			$schema['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $img_data[0],
				'width'  => $img_data[1],
				'height' => $img_data[2],
			];
		}
	}

	return $schema;
}

// ============================================================
// 5. FAQ SCHEMA FOR GEWÄSSER
// ============================================================

function abb_schema_faq_gewaesser( $post_id ) {
	$fischarten = abb_get_fischarten_array( $post_id );
	$name       = get_the_title( $post_id );
	$tiefe      = get_post_meta( $post_id, 'abb_tiefe_m', true );
	$flaeche    = get_post_meta( $post_id, 'abb_flaeche_ha', true );
	$erlaubnis  = get_post_meta( $post_id, 'abb_erlaubnis', true );

	$faq_items = [];

	// Q: Welche Fische beißen im [Gewässer]?
	if ( ! empty( $fischarten ) ) {
		$fisch_list = implode( ', ', $fischarten );
		$faq_items[] = [
			'@type'          => 'Question',
			'name'           => "Welche Fische beißen im {$name}?",
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => "Im {$name} sind folgende Fischarten heimisch und können geangelt werden: {$fisch_list}. Die besten Fangchancen bietet unser aktueller Beißindex.",
			],
		];
	}

	// Q: Wie tief ist der [Gewässer]?
	if ( $tiefe ) {
		$faq_items[] = [
			'@type'          => 'Question',
			'name'           => "Wie tief ist der {$name}?",
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => "Der {$name} hat eine maximale Tiefe von {$tiefe} Metern." . ( $flaeche ? " Die Fläche des Gewässers beträgt {$flaeche} Hektar." : '' ),
			],
		];
	}

	// Q: Brauche ich eine Erlaubnis zum Angeln im [Gewässer]?
	if ( $erlaubnis ) {
		$faq_items[] = [
			'@type'          => 'Question',
			'name'           => "Brauche ich eine Angelerlaubnis für den {$name}?",
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => "Ja, zum Angeln im {$name} benötigst du: {$erlaubnis}. Außerdem ist ein gültiger Fischereischein Pflicht.",
			],
		];
	}

	// Q: Wo liegt der [Gewässer]?
	$ort = get_post_meta( $post_id, 'abb_ort', true );
	if ( $ort ) {
		$faq_items[] = [
			'@type'          => 'Question',
			'name'           => "Wo liegt der {$name}?",
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => "Der {$name} liegt in {$ort}, Brandenburg (Deutschland). Die genauen GPS-Koordinaten und eine Karte findest du auf dieser Seite.",
			],
		];
	}

	// Q: Wann ist die beste Angelzeit?
	$faq_items[] = [
		'@type'          => 'Question',
		'name'           => "Wann ist die beste Angelzeit am {$name}?",
		'acceptedAnswer' => [
			'@type' => 'Answer',
			'text'  => "Die Fische beißen am {$name} besonders gut bei ruhigem Wetter, stabilem Luftdruck und in den frühen Morgen- oder Abendstunden. Unser täglicher Beißindex zeigt dir die aktuellen Bedingungen.",
		],
	];

	if ( empty( $faq_items ) ) return null;

	return [
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $faq_items,
	];
}

// ============================================================
// 6. NEWS ARTICLE SCHEMA
// ============================================================

function abb_schema_news_article( $post_id ) {
	$post        = get_post( $post_id );
	$img_id      = get_post_thumbnail_id( $post_id );
	$img_url     = $img_id ? wp_get_attachment_image_url( $img_id, 'abb-hero' ) : '';
	$author_name = get_the_author_meta( 'display_name', $post->post_author );

	return [
		'@context'         => 'https://schema.org',
		'@type'            => 'NewsArticle',
		'headline'         => get_the_title( $post_id ),
		'url'              => get_permalink( $post_id ),
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'author'           => [
			'@type' => 'Person',
			'name'  => $author_name,
		],
		'publisher'        => [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'logo'  => [
				'@type' => 'ImageObject',
				'url'   => abb_get_logo_url(),
			],
		],
		'description'      => wp_strip_all_tags( get_the_excerpt( $post ) ),
		'image'            => $img_url ? [ '@type' => 'ImageObject', 'url' => $img_url ] : null,
		'inLanguage'       => 'de-DE',
		'isAccessibleForFree' => true,
	];
}

// ============================================================
// 7. GEWÄSSER ARCHIVE SCHEMA (ItemList)
// ============================================================

function abb_schema_gewaesser_archive() {
	$query = new WP_Query( [
		'post_type'      => 'angelgewaesser',
		'posts_per_page' => 100,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	] );

	if ( empty( $query->posts ) ) return null;

	$list_items = [];
	foreach ( $query->posts as $position => $post_id ) {
		$list_items[] = [
			'@type'    => 'ListItem',
			'position' => $position + 1,
			'name'     => get_the_title( $post_id ),
			'url'      => get_permalink( $post_id ),
		];
	}

	return [
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => 'Angelgewässer Brandenburg',
		'description'     => 'Alle Angelgewässer in Brandenburg mit Infos zu Fischarten, Tiefe und Angelerlaubnis',
		'numberOfItems'   => count( $list_items ),
		'itemListElement' => $list_items,
	];
}

// ============================================================
// 8. HELPER: Get Fischarten as array
// ============================================================

function abb_get_fischarten_array( $post_id ) {
	// First check raw meta (comma-separated string)
	$raw = get_post_meta( $post_id, 'abb_fischarten_raw', true );
	if ( $raw ) {
		return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	}

	// Fallback: taxonomy terms
	$terms = get_the_terms( $post_id, 'fischarten' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		return wp_list_pluck( $terms, 'name' );
	}

	return [];
}

// ============================================================
// 9. BREADCRUMB JSON-LD for Gewässer
// ============================================================

add_action( 'wp_head', 'abb_output_breadcrumb_schema', 6 );
function abb_output_breadcrumb_schema() {
	if ( ! is_singular( 'angelgewaesser' ) && ! is_singular( 'post' ) && ! is_page() ) return;

	$items = [
		[
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => 'Home',
			'item'     => home_url( '/' ),
		],
	];

	if ( is_singular( 'angelgewaesser' ) ) {
		$items[] = [
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => 'Gewässer',
			'item'     => home_url( '/gewaesser/' ),
		];
		$items[] = [
			'@type'    => 'ListItem',
			'position' => 3,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		];
	} elseif ( is_singular( 'post' ) ) {
		$items[] = [
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => 'News',
			'item'     => home_url( '/news/' ),
		];
		$items[] = [
			'@type'    => 'ListItem',
			'position' => 3,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		];
	}

	$schema = [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	];

	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}
