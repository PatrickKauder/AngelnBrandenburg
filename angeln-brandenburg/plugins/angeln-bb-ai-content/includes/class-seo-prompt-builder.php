<?php
/**
 * SEO Prompt Builder – Phase 5
 *
 * Generates structured prompts for AI that enforce:
 *  1. H1 title with focus keyword
 *  2. Meta description (max 155 chars, CTR-optimized)
 *  3. Internal links to Gewässer profiles
 *  4. FAQ schema entries
 *  5. Proper SEO content structure
 */

defined( 'ABSPATH' ) || exit;

class ABB_SEO_Prompt_Builder {

	// Topic templates for Brandenburg fishing news
	const TOPIC_TEMPLATES = [
		'season_report'   => 'Angelbericht {saison} {year} – {gewaesser}',
		'fish_report'     => '{fischart}-Angeln im {gewaesser}: Tipps und Tricks',
		'regulation_news' => 'Neue Angelregelungen {year} in Brandenburg: Was ändert sich?',
		'weather_fishing' => 'Angeln bei {wetter} in Brandenburg – So geht es richtig',
		'water_report'    => 'Wasserstände {monat} {year}: Was bedeutet das für Angler?',
		'species_guide'   => '{fischart} angeln in Brandenburg: Vollständiger Leitfaden',
	];

	/**
	 * Build a full SEO content prompt for a fishing news article.
	 *
	 * @param array $params {
	 *   @type string $topic          Content topic/type
	 *   @type string $focus_keyword  Primary SEO keyword
	 *   @type string $gewaesser      Related body of water
	 *   @type string $fischart       Fish species
	 *   @type string $season         Season/month context
	 *   @type array  $internal_links Available internal links
	 * }
	 * @return array { 'system_prompt', 'user_prompt', 'meta_prompt' }
	 */
	public function build_article_prompt( array $params ): array {
		$keyword       = $params['focus_keyword'] ?? 'Angeln Brandenburg';
		$gewaesser     = $params['gewaesser'] ?? '';
		$fischart      = $params['fischart'] ?? '';
		$word_count    = $params['word_count'] ?? 800;
		$internal_links = $params['internal_links'] ?? $this->get_available_internal_links();

		$system_prompt = $this->build_system_prompt( $keyword );
		$user_prompt   = $this->build_user_prompt( $params, $internal_links, $word_count );
		$meta_prompt   = $this->build_meta_description_prompt( $keyword, $gewaesser, $fischart );

		return [
			'system_prompt' => $system_prompt,
			'user_prompt'   => $user_prompt,
			'meta_prompt'   => $meta_prompt,
		];
	}

	private function build_system_prompt( string $keyword ): string {
		return <<<PROMPT
Du bist ein erfahrener SEO-Texter und Angelexperte, spezialisiert auf Brandenburg und Nordostdeutschland.

DEINE AUFGABE:
Schreibe deutschsprachige, SEO-optimierte Angelartikel für angeln-brandenburg.de

PFLICHTREGELN:
1. Das Fokus-Keyword "{$keyword}" MUSS im H1-Titel vorkommen (exakt oder als Variante)
2. Das Fokus-Keyword muss in den ersten 100 Wörtern erscheinen
3. Keyword-Dichte: 1-2% (nicht überoptimieren!)
4. Schreibe für Menschen, nicht für Suchmaschinen
5. Verwende natürliche, umgangssprachliche Sprache (Angler sprechen miteinander)
6. Alle Links MÜSSEN als [LINK: anchor-text → URL] Platzhalter geschrieben werden

STRUKTUR (ZWINGEND):
---
H1: [Titel mit Fokus-Keyword]
META: [Meta-Description, max 155 Zeichen, endet mit Handlungsaufforderung]
---
[Einleitung: 80-100 Wörter, spannend, Fokus-Keyword in ersten 2 Sätzen]

## [H2 Zwischenüberschrift]
[Inhalt...]

## [H2 Zwischenüberschrift]
[Inhalt mit internem Link: [LINK: Gewässername → /gewaesser/slug/]]

## FAQ: Häufige Fragen
### [Frage 1 als vollständigen Satz?]
[Antwort in 2-4 Sätzen]

### [Frage 2?]
[Antwort]
---

VERBOTE:
- Keine generischen Phrasen wie "In diesem Artikel erfahren Sie..."
- Keine Wiederholungen des Fokus-Keywords mehr als 2x pro Abschnitt
- Keine nicht verifizierbaren Fakten über Fangrekorde oder Gewässer
- Kein Clickbait in der Meta-Description
PROMPT;
	}

	private function build_user_prompt( array $params, array $internal_links, int $word_count ): string {
		$keyword   = $params['focus_keyword'] ?? 'Angeln Brandenburg';
		$gewaesser = $params['gewaesser'] ?? 'Brandenburgische Gewässer';
		$fischart  = $params['fischart'] ?? '';
		$season    = $params['season'] ?? date_i18n( 'F Y' );
		$topic     = $params['topic_description'] ?? '';

		$links_context = '';
		if ( ! empty( $internal_links ) ) {
			$links_context = "\n\nVERFÜGBARE INTERNE LINKS (mindestens 2 verwenden):\n";
			foreach ( array_slice( $internal_links, 0, 10 ) as $link ) {
				$links_context .= "- {$link['name']} → {$link['url']}\n";
			}
		}

		return <<<PROMPT
Schreibe einen SEO-Artikel mit ca. {$word_count} Wörtern.

FOKUS-KEYWORD: {$keyword}
GEWÄSSER-BEZUG: {$gewaesser}
FISCHARTEN: {$fischart}
JAHRESZEIT/KONTEXT: {$season}
THEMA: {$topic}
{$links_context}

WICHTIG:
- Mindestens eine Erwähnung des Beißindex (verlinke auf /beissindex/)
- Falls der Artikel über {$gewaesser} handelt, verlinke auf /gewaesser/[slug]/
- 3-5 FAQ-Fragen am Ende (werden als FAQPage-Schema eingebettet)
- Schreib die Meta-Description in eckigen Klammern nach "META:"
PROMPT;
	}

	private function build_meta_description_prompt( string $keyword, string $gewaesser, string $fischart ): string {
		return <<<PROMPT
Schreibe eine Meta-Description für das Fokus-Keyword "{$keyword}".

REGELN:
- Exakt 130-155 Zeichen (ZÄHLE GENAU!)
- Enthält das Fokus-Keyword am Anfang
- Endet mit einer klaren Handlungsaufforderung (z.B. "Jetzt lesen!", "Tipps entdecken!")
- Macht neugierig und verspricht konkreten Nutzen
- Nennt wenn möglich: {$gewaesser}, {$fischart}, oder Brandenburg

SCHLECHTES BEISPIEL (zu generisch):
"Erfahren Sie alles über das Angeln in Brandenburg. Informationen zu Gewässern und Fischen."

GUTES BEISPIEL:
"Hecht-Angeln am Beetzsee: Die besten Köder, Spots und Jahreszeiten für Brandenburgs Top-Raubfischgewässer. Jetzt lesen!"
PROMPT;
	}

	/**
	 * Build a Gewässer profile description prompt.
	 */
	public function build_gewaesser_prompt( int $post_id ): array {
		$name      = get_the_title( $post_id );
		$fischarten = implode( ', ', abb_get_fischarten_array( $post_id ) );
		$tiefe     = get_post_meta( $post_id, 'abb_tiefe_m', true );
		$flaeche   = get_post_meta( $post_id, 'abb_flaeche_ha', true );
		$ort       = get_post_meta( $post_id, 'abb_ort', true );

		$user_prompt = <<<PROMPT
Schreibe einen vollständigen Steckbrief für das Angelgewässer "{$name}" in Brandenburg.

DATEN:
- Name: {$name}
- Ort/Region: {$ort}
- Tiefe: {$tiefe} Meter
- Fläche: {$flaeche} Hektar
- Fischarten: {$fischarten}

STRUKTUR:
1. Einleitung (50-80 Wörter): Warum ist dieses Gewässer besonders?
2. Fischarten & Angeltipps (200 Wörter): Pro Fischart 1-2 Sätze Tipps
3. Beste Jahreszeiten (100 Wörter)
4. Anreise & Parken (50 Wörter, generisch falls unbekannt)
5. FAQ (3 Fragen)

FOKUS-KEYWORD: {$name} angeln
META: [max 155 Zeichen Meta-Description]

Schreibe konkret und praxisnah für erfahrene Angler.
PROMPT;

		return [
			'system_prompt' => $this->build_system_prompt( $name . ' angeln' ),
			'user_prompt'   => $user_prompt,
		];
	}

	/**
	 * Get all published Gewässer as internal link candidates.
	 */
	public function get_available_internal_links(): array {
		$posts = get_posts( [
			'post_type'      => 'angelgewaesser',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$links = [
			[ 'name' => 'Beißindex Brandenburg', 'url' => home_url( '/beissindex/' ) ],
			[ 'name' => 'Alle Angelgewässer Brandenburg', 'url' => home_url( '/gewaesser/' ) ],
		];

		foreach ( $posts as $post ) {
			$fischarten = implode( '/', array_slice( abb_get_fischarten_array( $post->ID ), 0, 3 ) );
			$links[] = [
				'name' => get_the_title( $post ) . ( $fischarten ? " ({$fischarten})" : '' ),
				'url'  => get_permalink( $post ),
			];
		}

		return $links;
	}

	/**
	 * Parse AI output and extract H1, META, FAQ, content body.
	 */
	public function parse_ai_output( string $ai_response ): array {
		$result = [
			'h1'           => '',
			'meta'         => '',
			'content'      => '',
			'faq'          => [],
			'internal_links_found' => [],
		];

		// Extract H1
		if ( preg_match( '/^H1:\s*(.+)$/m', $ai_response, $m ) ) {
			$result['h1'] = trim( $m[1] );
		}

		// Extract META
		if ( preg_match( '/^META:\s*(.+)$/m', $ai_response, $m ) ) {
			$meta = trim( trim( $m[1] ), '[]' );
			if ( mb_strlen( $meta ) > 155 ) {
				$meta = mb_substr( $meta, 0, 152 ) . '...';
			}
			$result['meta'] = $meta;
		}

		// Extract FAQ items
		preg_match_all( '/###\s*(.+\?)\n(.+(?:\n(?!###).+)*)/U', $ai_response, $faq_matches );
		if ( ! empty( $faq_matches[1] ) ) {
			foreach ( $faq_matches[1] as $i => $question ) {
				$result['faq'][] = [
					'question' => trim( $question ),
					'answer'   => trim( $faq_matches[2][ $i ] ),
				];
			}
		}

		// Process [LINK: anchor → URL] placeholders
		$content = $ai_response;
		$content = preg_replace_callback(
			'/\[LINK:\s*([^\]→]+)\s*→\s*([^\]]+)\]/',
			function( $m ) use ( &$result ) {
				$anchor = trim( $m[1] );
				$url    = trim( $m[2] );
				// Make sure URL is absolute
				if ( strpos( $url, 'http' ) !== 0 ) {
					$url = home_url( $url );
				}
				$result['internal_links_found'][] = [ 'anchor' => $anchor, 'url' => $url ];
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $anchor ) . '</a>';
			},
			$content
		);

		// Remove H1/META lines from content body
		$content = preg_replace( '/^(H1|META):\s*.+\n?/m', '', $content );

		// Convert Markdown headings to HTML
		$content = preg_replace( '/^## (.+)$/m', '<h2>$1</h2>', $content );
		$content = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $content );

		// Convert **bold** to <strong>
		$content = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content );

		$result['content'] = trim( $content );

		return $result;
	}
}
