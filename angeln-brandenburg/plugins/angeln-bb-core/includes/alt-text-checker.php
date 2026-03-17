<?php
/**
 * Alt-Text Checker – Phase 6
 * Finds images without alt text and flags them for editors.
 */

defined( 'ABSPATH' ) || exit;

// Admin notice for images without alt text
add_action( 'admin_notices', 'abb_alt_text_admin_notice' );
function abb_alt_text_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, [ 'upload', 'post', 'page', 'angelgewaesser' ], true ) ) return;

	$missing = abb_count_images_without_alt();
	if ( ! $missing ) return;

	printf(
		'<div class="notice notice-warning is-dismissible"><p>
			<strong>Angeln Brandenburg:</strong> %d Bild(er) ohne Alt-Text gefunden.
			<a href="%s">→ Jetzt Alt-Texte hinzufügen (SEO + Barrierefreiheit)</a>
		</p></div>',
		$missing,
		esc_url( admin_url( 'upload.php?mode=list&s=&orderby=date&order=desc' ) )
	);
}

function abb_count_images_without_alt(): int {
	global $wpdb;
	$count = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
		 WHERE p.post_type = 'attachment'
		   AND p.post_mime_type LIKE 'image/%'
		   AND p.post_status = 'inherit'
		   AND (pm.meta_value IS NULL OR pm.meta_value = '')",
		'_wp_attachment_image_alt'
	) );
	return (int) $count;
}

/**
 * Get all images missing alt text (for admin panel).
 */
function abb_get_images_without_alt( int $limit = 50 ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID, p.post_title, p.guid
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
		 WHERE p.post_type = 'attachment'
		   AND p.post_mime_type LIKE 'image/%%'
		   AND p.post_status = 'inherit'
		   AND (pm.meta_value IS NULL OR pm.meta_value = '')
		 ORDER BY p.post_date DESC
		 LIMIT %d",
		'_wp_attachment_image_alt',
		$limit
	) );
}

/**
 * Suggest alt text from filename if empty.
 */
add_filter( 'wp_get_attachment_image_attributes', 'abb_ensure_alt_text', 10, 2 );
function abb_ensure_alt_text( array $attr, WP_Post $attachment ): array {
	if ( empty( $attr['alt'] ) ) {
		// Try meta first
		$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		if ( ! $alt ) {
			// Fall back to title or filename
			$alt = $attachment->post_title
				?: pathinfo( wp_get_original_image_path( $attachment->ID ) ?? '', PATHINFO_FILENAME );
			$alt = str_replace( [ '-', '_' ], ' ', $alt );
			$alt = ucfirst( trim( $alt ) );
		}
		$attr['alt'] = esc_attr( $alt );
	}
	return $attr;
}
