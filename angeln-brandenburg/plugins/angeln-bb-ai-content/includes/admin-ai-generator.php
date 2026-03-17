<?php
/**
 * Admin UI – KI-Content Generator
 * Phase 5: SEO-Inhalte erstellen
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'abb_ai_admin_menu' );
function abb_ai_admin_menu() {
	add_menu_page(
		'KI-Content Generator',
		'🤖 KI-Content',
		'edit_posts',
		'abb-ai-generator',
		'abb_ai_generator_page',
		'dashicons-edit',
		6
	);
	add_submenu_page(
		'abb-ai-generator',
		'Einstellungen',
		'Einstellungen',
		'manage_options',
		'abb-ai-settings',
		'abb_ai_settings_page'
	);
}

function abb_ai_generator_page() {
	$sandbox = new ABB_Content_Sandbox();
	$links   = ( new ABB_SEO_Prompt_Builder() )->get_available_internal_links();

	// Get Gewässer for dropdown
	$gewaesser_list = get_posts( [
		'post_type'      => 'angelgewaesser',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	] );
	?>
	<div class="wrap">
		<h1>🤖 KI-Content Generator</h1>
		<p class="description">Alle generierten Inhalte werden als <strong>Entwurf</strong> gespeichert und müssen manuell freigegeben werden.</p>

		<?php
		// Handle form submission
		if ( isset( $_POST['abb_generate'] ) && check_admin_referer( 'abb_generate_content' ) ) {
			$params = [
				'focus_keyword'     => sanitize_text_field( $_POST['focus_keyword'] ?? '' ),
				'gewaesser'         => sanitize_text_field( $_POST['gewaesser'] ?? '' ),
				'fischart'          => sanitize_text_field( $_POST['fischart'] ?? '' ),
				'season'            => sanitize_text_field( $_POST['season'] ?? '' ),
				'word_count'        => (int) ( $_POST['word_count'] ?? 700 ),
				'topic'             => sanitize_text_field( $_POST['topic'] ?? '' ),
				'topic_description' => sanitize_textarea_field( $_POST['topic_description'] ?? '' ),
				'gewaesser_post_id' => (int) ( $_POST['gewaesser_post_id'] ?? 0 ),
			];

			$post_id = $sandbox->generate_article_draft( $params );
			if ( is_wp_error( $post_id ) ) {
				echo '<div class="notice notice-error"><p>Fehler: ' . esc_html( $post_id->get_error_message() ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success"><p>✅ Entwurf erstellt: <a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">Entwurf bearbeiten (ID: ' . $post_id . ')</a></p></div>';
			}
		}

		// Handle Gewässer description generation
		if ( isset( $_POST['abb_generate_gewaesser'] ) && check_admin_referer( 'abb_generate_gewaesser' ) ) {
			$gw_id = (int) ( $_POST['gewaesser_desc_id'] ?? 0 );
			if ( $gw_id ) {
				$result = $sandbox->generate_gewaesser_description( $gw_id );
				if ( is_wp_error( $result ) ) {
					echo '<div class="notice notice-error"><p>Fehler: ' . esc_html( $result->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success"><p>✅ Gewässer-Beschreibung generiert: <a href="' . esc_url( get_edit_post_link( $gw_id ) ) . '">Zur Überprüfung</a></p></div>';
				}
			}
		}
		?>

		<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-top:1.5rem;max-width:1100px;">

			<!-- Article Generator -->
			<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;">
				<h2>Neuen News-Artikel generieren</h2>
				<form method="post">
					<?php wp_nonce_field( 'abb_generate_content' ); ?>

					<table class="form-table">
						<tr>
							<th><label for="focus_keyword">Fokus-Keyword *</label></th>
							<td>
								<input type="text" id="focus_keyword" name="focus_keyword" class="regular-text" required
									   placeholder="z.B. Hecht angeln Havel Brandenburg" value="<?php echo esc_attr( $_POST['focus_keyword'] ?? '' ); ?>">
								<p class="description">Primäres SEO-Keyword – erscheint im H1 und Meta-Description</p>
							</td>
						</tr>
						<tr>
							<th><label for="gewaesser_post_id">Gewässer (optional)</label></th>
							<td>
								<select id="gewaesser_post_id" name="gewaesser_post_id" style="min-width:250px;">
									<option value="">– Kein spezifisches Gewässer –</option>
									<?php foreach ( $gewaesser_list as $gw ) : ?>
										<option value="<?php echo esc_attr( $gw->ID ); ?>" <?php selected( (int)($_POST['gewaesser_post_id']??0), $gw->ID ); ?>>
											<?php echo esc_html( get_the_title( $gw ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="fischart">Fischarten</label></th>
							<td>
								<input type="text" id="fischart" name="fischart" class="regular-text"
									   placeholder="Hecht, Zander, Barsch" value="<?php echo esc_attr( $_POST['fischart'] ?? '' ); ?>">
							</td>
						</tr>
						<tr>
							<th><label for="topic">Thema/Typ</label></th>
							<td>
								<select id="topic" name="topic">
									<option value="season_report">Angelbericht (saisonal)</option>
									<option value="fish_report">Fischarten-Report</option>
									<option value="regulation_news">Regelungen/Neuigkeiten</option>
									<option value="weather_fishing">Angeln & Wetter</option>
									<option value="water_report">Pegelstand-Bericht</option>
									<option value="species_guide">Angelratgeber</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="season">Kontext/Jahreszeit</label></th>
							<td>
								<input type="text" id="season" name="season" class="regular-text"
									   value="<?php echo esc_attr( date_i18n( 'F Y' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th><label for="word_count">Wortanzahl</label></th>
							<td>
								<select id="word_count" name="word_count">
									<option value="500">~500 Wörter (kurz)</option>
									<option value="700" selected>~700 Wörter (standard)</option>
									<option value="1000">~1000 Wörter (lang)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="topic_description">Thema-Details (optional)</label></th>
							<td>
								<textarea id="topic_description" name="topic_description" class="large-text" rows="3"
										  placeholder="Zusätzliche Infos für die KI..."><?php echo esc_textarea( $_POST['topic_description'] ?? '' ); ?></textarea>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="abb_generate" class="button button-primary" value="🤖 Artikel als Entwurf generieren">
					</p>
				</form>
			</div>

			<!-- Gewässer Description Generator + Log -->
			<div>
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;margin-bottom:1rem;">
					<h2>Gewässer-Beschreibung</h2>
					<form method="post">
						<?php wp_nonce_field( 'abb_generate_gewaesser' ); ?>
						<select name="gewaesser_desc_id" style="width:100%;margin-bottom:.75rem;">
							<?php foreach ( $gewaesser_list as $gw ) : ?>
								<option value="<?php echo esc_attr( $gw->ID ); ?>">
									<?php echo esc_html( get_the_title( $gw ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description" style="margin-bottom:.75rem;">Generiert eine SEO-optimierte Beschreibung für das Gewässer-Profil (Status: Ausstehend).</p>
						<input type="submit" name="abb_generate_gewaesser" class="button" value="Beschreibung generieren">
					</form>
				</div>

				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;">
					<h3>Verfügbare interne Links</h3>
					<ul style="max-height:200px;overflow-y:auto;font-size:.8rem;">
						<?php foreach ( array_slice( $links, 0, 15 ) as $link ) : ?>
							<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank"><?php echo esc_html( $link['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>

		<!-- Generation Log -->
		<?php
		$log = array_reverse( get_option( ABB_Content_Sandbox::LOG_OPTION, [] ) );
		if ( ! empty( $log ) ) :
		?>
		<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;margin-top:1.5rem;max-width:1100px;">
			<h2>Generierungs-Log</h2>
			<table class="wp-list-table widefat fixed striped" style="font-size:.85rem;">
				<thead><tr><th>Zeit</th><th>Keyword</th><th>Status</th><th>Meldung</th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( $log, 0, 20 ) as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ); ?></td>
							<td><?php echo esc_html( $entry['keyword'] ); ?></td>
							<td><?php echo $entry['status'] === 'success' ? '✅' : '❌'; ?> <?php echo esc_html( $entry['status'] ); ?></td>
							<td><?php echo esc_html( $entry['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</div>
	<?php
}

function abb_ai_settings_page() {
	if ( isset( $_POST['abb_ai_save_settings'] ) && check_admin_referer( 'abb_ai_settings' ) ) {
		update_option( 'abb_ai_provider',       sanitize_text_field( $_POST['abb_ai_provider'] ?? 'openai' ) );
		update_option( 'abb_openai_api_key',    sanitize_text_field( $_POST['abb_openai_api_key'] ?? '' ) );
		update_option( 'abb_anthropic_api_key', sanitize_text_field( $_POST['abb_anthropic_api_key'] ?? '' ) );
		update_option( 'abb_ai_author_id',      (int) ( $_POST['abb_ai_author_id'] ?? 1 ) );
		update_option( 'abb_ai_auto_generate',  isset( $_POST['abb_ai_auto_generate'] ) ? 1 : 0 );
		echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
	}
	?>
	<div class="wrap">
		<h1>KI-Content – Einstellungen</h1>
		<form method="post" style="max-width:600px;">
			<?php wp_nonce_field( 'abb_ai_settings' ); ?>
			<table class="form-table">
				<tr>
					<th>KI-Provider</th>
					<td>
						<select name="abb_ai_provider">
							<option value="openai" <?php selected( get_option('abb_ai_provider'), 'openai' ); ?>>OpenAI GPT-4o</option>
							<option value="anthropic" <?php selected( get_option('abb_ai_provider'), 'anthropic' ); ?>>Anthropic Claude</option>
							<option value="none" <?php selected( get_option('abb_ai_provider'), 'none' ); ?>>Kein API (Template-Fallback)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="abb_openai_api_key">OpenAI API-Key</label></th>
					<td><input type="password" id="abb_openai_api_key" name="abb_openai_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'abb_openai_api_key' ) ); ?>"></td>
				</tr>
				<tr>
					<th><label for="abb_anthropic_api_key">Anthropic API-Key</label></th>
					<td><input type="password" id="abb_anthropic_api_key" name="abb_anthropic_api_key" class="regular-text" value="<?php echo esc_attr( get_option( 'abb_anthropic_api_key' ) ); ?>"></td>
				</tr>
				<tr>
					<th>Auto-Generierung</th>
					<td>
						<label>
							<input type="checkbox" name="abb_ai_auto_generate" value="1" <?php checked( get_option('abb_ai_auto_generate') ); ?>>
							Wöchentlich automatisch Entwürfe generieren
						</label>
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" name="abb_ai_save_settings" class="button button-primary" value="Speichern"></p>
		</form>
	</div>
	<?php
}
