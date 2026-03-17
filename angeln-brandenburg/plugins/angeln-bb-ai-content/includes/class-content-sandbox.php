<?php
/**
 * Content Sandbox – Phase 5
 *
 * All AI-generated content is stored as DRAFT.
 * Editors must review and publish manually.
 *
 * Supported AI providers:
 *   - OpenAI (GPT-4o)
 *   - Anthropic Claude (via API)
 *   - Local fallback (template-based)
 */

defined( 'ABSPATH' ) || exit;

class ABB_Content_Sandbox {

	private ABB_SEO_Prompt_Builder $prompt_builder;
	private ABB_Internal_Linker    $linker;

	const LOG_OPTION = 'abb_ai_generation_log';
	const MAX_LOG    = 50;

	public function __construct() {
		$this->prompt_builder = new ABB_SEO_Prompt_Builder();
		$this->linker         = new ABB_Internal_Linker();
	}

	/**
	 * Generate a fishing news draft article.
	 *
	 * @param array $params Content generation parameters
	 * @return int|WP_Error  Post ID on success
	 */
	public function generate_article_draft( array $params ): int|WP_Error {
		$prompts  = $this->prompt_builder->build_article_prompt( $params );
		$ai_text  = $this->call_ai_api( $prompts['system_prompt'], $prompts['user_prompt'] );

		if ( is_wp_error( $ai_text ) ) {
			$this->log_generation( $params, 'error', $ai_text->get_error_message() );
			return $ai_text;
		}

		$parsed   = $this->prompt_builder->parse_ai_output( $ai_text );
		$content  = $this->linker->inject_internal_links( $parsed['content'] );

		// Build WordPress post
		$post_data = [
			'post_title'   => $parsed['h1'] ?: $params['focus_keyword'],
			'post_content' => $this->wrap_faq_schema( $content, $parsed['faq'] ),
			'post_status'  => apply_filters( 'abb_ai_post_status', 'draft' ), // ALWAYS draft
			'post_type'    => 'post',
			'post_author'  => get_option( 'abb_ai_author_id', 1 ),
			'meta_input'   => [
				'_abb_ai_generated'        => '1',
				'_abb_ai_generated_at'     => current_time( 'mysql' ),
				'_yoast_wpseo_metadesc'    => $parsed['meta'],
				'rank_math_description'    => $parsed['meta'],
				'_aioseop_description'     => $parsed['meta'],
				'_abb_focus_keyword'       => $params['focus_keyword'] ?? '',
				'_abb_ai_raw_response'     => $ai_text, // Store for review
			],
		];

		// Link to Gewässer if specified
		if ( ! empty( $params['gewaesser_post_id'] ) ) {
			$post_data['meta_input']['_abb_related_gewaesser'] = $params['gewaesser_post_id'];
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Auto-categorize
		$this->auto_categorize( $post_id, $params );

		// Add featured image placeholder notice
		update_post_meta( $post_id, '_abb_needs_featured_image', '1' );

		$this->log_generation( $params, 'success', "Post ID: {$post_id}" );

		return $post_id;
	}

	/**
	 * Generate a Gewässer profile description draft.
	 */
	public function generate_gewaesser_description( int $gewaesser_post_id ): int|WP_Error {
		$prompts = $this->prompt_builder->build_gewaesser_prompt( $gewaesser_post_id );
		$ai_text = $this->call_ai_api( $prompts['system_prompt'], $prompts['user_prompt'] );

		if ( is_wp_error( $ai_text ) ) return $ai_text;

		$parsed  = $this->prompt_builder->parse_ai_output( $ai_text );
		$content = $this->linker->inject_internal_links( $parsed['content'] );

		// Update the Gewässer post (as pending review, not published)
		$result = wp_update_post( [
			'ID'           => $gewaesser_post_id,
			'post_content' => $this->wrap_faq_schema( $content, $parsed['faq'] ),
			'post_status'  => 'pending', // Pending review
			'meta_input'   => [
				'_abb_ai_generated'     => '1',
				'_abb_ai_generated_at'  => current_time( 'mysql' ),
				'_yoast_wpseo_metadesc' => $parsed['meta'],
			],
		], true );

		return $result;
	}

	/**
	 * Weekly batch: Generate news drafts for top Gewässer.
	 */
	public function generate_weekly_fishing_news(): void {
		$season     = $this->get_current_season();
		$month      = date_i18n( 'F' );
		$year       = date( 'Y' );

		// Get top Gewässer (most viewed or manually marked)
		$gewaesser_posts = get_posts( [
			'post_type'      => 'angelgewaesser',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'meta_key'       => '_abb_ai_news_priority',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		] );

		foreach ( $gewaesser_posts as $gw ) {
			$fischarten = abb_get_fischarten_array( $gw->ID );
			$fischart   = ! empty( $fischarten ) ? $fischarten[0] : 'Karpfen';

			$this->generate_article_draft( [
				'focus_keyword'     => "{$fischart} angeln " . get_the_title( $gw ),
				'gewaesser'         => get_the_title( $gw ),
				'fischart'          => $fischart,
				'season'            => $season,
				'gewaesser_post_id' => $gw->ID,
				'topic_description' => "Aktueller Angelbericht {$month} {$year} für " . get_the_title( $gw ),
				'word_count'        => 700,
			] );

			// Rate limiting: pause between requests
			sleep( 2 );
		}
	}

	/**
	 * Call AI API (OpenAI, Anthropic, or local fallback).
	 */
	private function call_ai_api( string $system_prompt, string $user_prompt ): string|WP_Error {
		$provider = get_option( 'abb_ai_provider', 'openai' );

		switch ( $provider ) {
			case 'anthropic':
				return $this->call_anthropic( $system_prompt, $user_prompt );
			case 'openai':
				return $this->call_openai( $system_prompt, $user_prompt );
			default:
				return $this->generate_template_fallback( $user_prompt );
		}
	}

	private function call_anthropic( string $system_prompt, string $user_prompt ): string|WP_Error {
		$api_key = get_option( 'abb_anthropic_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', 'Anthropic API-Key nicht konfiguriert.' );
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'timeout' => 60,
			'headers' => [
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'      => 'claude-3-5-sonnet-20241022',
				'max_tokens' => 2000,
				'system'     => $system_prompt,
				'messages'   => [
					[ 'role' => 'user', 'content' => $user_prompt ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? "HTTP {$code}" );
		}

		return $body['content'][0]['text'] ?? new WP_Error( 'empty_response', 'Leere API-Antwort.' );
	}

	private function call_openai( string $system_prompt, string $user_prompt ): string|WP_Error {
		$api_key = get_option( 'abb_openai_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', 'OpenAI API-Key nicht konfiguriert.' );
		}

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'timeout' => 60,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'       => 'gpt-4o',
				'max_tokens'  => 2000,
				'temperature' => 0.7,
				'messages'    => [
					[ 'role' => 'system', 'content' => $system_prompt ],
					[ 'role' => 'user',   'content' => $user_prompt ],
				],
			] ),
		] );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? "HTTP {$code}" );
		}

		return $body['choices'][0]['message']['content'] ?? new WP_Error( 'empty_response', 'Leere API-Antwort.' );
	}

	/**
	 * Template-based fallback when no AI API is configured.
	 * Generates a placeholder draft for editors to complete.
	 */
	private function generate_template_fallback( string $user_prompt ): string {
		// Extract keyword from prompt
		preg_match( '/FOKUS-KEYWORD:\s*(.+)/m', $user_prompt, $m );
		$keyword = trim( $m[1] ?? 'Angeln Brandenburg' );

		$month = date_i18n( 'F Y' );

		return <<<CONTENT
H1: {$keyword} – Tipps und Infos {$month}
META: {$keyword}: Alle wichtigen Informationen, Tipps und Spots für Brandenburg. Jetzt lesen!

Das Angeln in Brandenburg bietet einzigartige Möglichkeiten für Freizeitangler. Mit über 3.000 Seen und zahlreichen Flusskilometern zählt Brandenburg zu den besten Angelregionen Deutschlands.

## Warum Brandenburg für Angler?

[⚠️ KI-Inhalt-Platzhalter: Bitte diesen Abschnitt mit echtem Inhalt füllen oder KI-Provider in den Einstellungen konfigurieren]

**Fokus-Keyword:** {$keyword}
**Zu erwähnen:** Pegelstände, Beißindex, Angelerlaubnis

## Beste Angelzeiten

[⚠️ Platzhalter]

## FAQ: Häufige Fragen

### Welche Fische gibt es in Brandenburgs Gewässern?
Brandenburg ist bekannt für Hecht, Zander, Barsch, Karpfen, Aal und Wels. Jedes Gewässer hat seinen eigenen Fischbestand – sieh dir unsere Gewässerprofile an.

### Brauche ich eine Angelerlaubnis in Brandenburg?
Ja. Neben dem Fischereischein benötigst du eine Erlaubnis des zuständigen Angelvereins oder des Gewässereigentümers.

### Wann beißen die Fische am besten?
Laut unserem [LINK: Beißindex → /beissindex/] sind Morgen- und Abenddämmerung die aktivsten Zeiten.
CONTENT;
	}

	/**
	 * Wrap FAQ items in schema-ready HTML.
	 */
	private function wrap_faq_schema( string $content, array $faq ): string {
		if ( empty( $faq ) ) return $content;

		$faq_html = "\n\n<!-- wp:group {\"className\":\"abb-faq\",\"metadata\":{\"name\":\"FAQ\"}} -->\n";
		$faq_html .= '<div class="abb-faq">';
		$faq_html .= '<h2 class="abb-faq__title">Häufig gestellte Fragen</h2>';

		foreach ( $faq as $item ) {
			$faq_html .= sprintf(
				'<div class="abb-faq__item" itemscope itemtype="https://schema.org/Question">
					<button class="abb-faq__question" itemprop="name">%s</button>
					<div class="abb-faq__answer" itemscope itemtype="https://schema.org/Answer" itemprop="acceptedAnswer">
						<span itemprop="text">%s</span>
					</div>
				</div>',
				esc_html( $item['question'] ),
				wp_kses_post( $item['answer'] )
			);
		}
		$faq_html .= '</div>';
		$faq_html .= "\n<!-- /wp:group -->";

		return $content . $faq_html;
	}

	private function auto_categorize( int $post_id, array $params ): void {
		// Map topic to category
		$category_map = [
			'season_report'  => 'Angelberichte',
			'fish_report'    => 'Fischarten',
			'water_report'   => 'Gewässerinfos',
			'regulation_news'=> 'Regelungen',
			'species_guide'  => 'Angelratgeber',
		];

		$topic = $params['topic'] ?? '';
		$cat   = $category_map[ $topic ] ?? 'Allgemein';

		$cat_id = get_cat_ID( $cat );
		if ( ! $cat_id ) {
			$cat_id = wp_create_category( $cat );
		}
		if ( $cat_id ) {
			wp_set_post_categories( $post_id, [ $cat_id ] );
		}

		// Auto-tag with Gewässer name
		if ( ! empty( $params['gewaesser'] ) ) {
			wp_set_post_tags( $post_id, [ $params['gewaesser'] ], true );
		}
		if ( ! empty( $params['fischart'] ) ) {
			wp_set_post_tags( $post_id, [ $params['fischart'] ], true );
		}
	}

	private function log_generation( array $params, string $status, string $message ): void {
		$log   = get_option( self::LOG_OPTION, [] );
		$log[] = [
			'time'    => current_time( 'mysql' ),
			'keyword' => $params['focus_keyword'] ?? '',
			'status'  => $status,
			'message' => $message,
		];

		// Keep last N entries
		if ( count( $log ) > self::MAX_LOG ) {
			$log = array_slice( $log, -self::MAX_LOG );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	private function get_current_season(): string {
		$month = (int) date( 'n' );
		if ( in_array( $month, [ 12, 1, 2 ], true ) )   return 'Winter';
		if ( in_array( $month, [ 3, 4, 5 ], true ) )    return 'Frühling';
		if ( in_array( $month, [ 6, 7, 8 ], true ) )    return 'Sommer';
		return 'Herbst';
	}
}
