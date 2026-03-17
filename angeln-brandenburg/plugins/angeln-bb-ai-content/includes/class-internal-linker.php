<?php
/**
 * Internal Linker – Phase 5
 *
 * Automatically injects internal links into AI-generated content.
 * Maps Gewässer names and fish species to their WordPress pages.
 */

defined( 'ABSPATH' ) || exit;

class ABB_Internal_Linker {

	/**
	 * Inject internal links into content.
	 * Safe: Only links first occurrence of each keyword.
	 */
	public function inject_internal_links( string $content ): string {
		$link_map = $this->build_link_map();
		if ( empty( $link_map ) ) return $content;

		// Sort by length (longer terms first) to avoid partial matches
		uksort( $link_map, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );

		foreach ( $link_map as $term => $url ) {
			// Skip if already a link or inside a heading
			$already_linked = strpos( $content, ">{$term}</a>" ) !== false
				|| strpos( $content, ">{$term} " ) !== false && strpos( $content, 'href=' ) !== false;

			if ( $already_linked ) continue;

			// Only link first occurrence, skip if inside HTML tag
			$pattern     = '/(?<!["\'>\/])(\b' . preg_quote( $term, '/' ) . '\b)/';
			$replacement = '<a href="' . esc_url( $url ) . '" class="abb-internal-link">' . '$1' . '</a>';

			$new_content = preg_replace( $pattern, $replacement, $content, 1 );
			if ( $new_content && $new_content !== $content ) {
				$content = $new_content;
			}
		}

		return $content;
	}

	/**
	 * Build a map of [ term => URL ] from published Gewässer.
	 * Cached per request.
	 */
	private function build_link_map(): array {
		static $cache = null;
		if ( $cache !== null ) return $cache;

		$cache_key = 'abb_internal_link_map';
		$map       = get_transient( $cache_key );
		if ( $map ) {
			$cache = $map;
			return $cache;
		}

		$map   = [];
		$posts = get_posts( [
			'post_type'      => 'angelgewaesser',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		] );

		foreach ( $posts as $post ) {
			$name = get_the_title( $post );
			$url  = get_permalink( $post );
			$map[ $name ] = $url;

			// Also link common abbreviations/alternate names
			$alt = get_post_meta( $post->ID, 'abb_alternative_names', true );
			if ( $alt ) {
				foreach ( explode( ',', $alt ) as $alt_name ) {
					$alt_name = trim( $alt_name );
					if ( $alt_name ) $map[ $alt_name ] = $url;
				}
			}
		}

		// Add static links
		$map['Beißindex']          = home_url( '/beissindex/' );
		$map['Angelgewässer Brandenburg'] = home_url( '/gewaesser/' );
		$map['Havel']              = home_url( '/gewaesser/?river=havel' );
		$map['Oder']               = home_url( '/gewaesser/?river=oder' );
		$map['Spree']              = home_url( '/gewaesser/?river=spree' );

		set_transient( $cache_key, $map, HOUR_IN_SECONDS );
		$cache = $map;
		return $cache;
	}

	/**
	 * Invalidate cache when Gewässer is updated.
	 */
	public static function invalidate_cache(): void {
		delete_transient( 'abb_internal_link_map' );
	}
}

// Invalidate on Gewässer save
add_action( 'save_post_angelgewaesser', [ 'ABB_Internal_Linker', 'invalidate_cache' ] );
