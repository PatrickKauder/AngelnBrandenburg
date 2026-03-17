<?php
/**
 * Go-Live Admin Panel – Phase 6
 * Central checklist and control center
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'abb_golive_admin_menu' );
function abb_golive_admin_menu() {
	add_options_page(
		'Go-Live Checklist',
		'🚀 Go-Live Check',
		'manage_options',
		'abb-golive',
		'abb_golive_panel_page'
	);
}

function abb_golive_panel_page() {
	$checks = abb_run_golive_checks();
	?>
	<div class="wrap">
		<h1>🚀 Angeln Brandenburg – Go-Live Checklist</h1>
		<p>Alle Punkte müssen ✅ sein, bevor die Seite live geht.</p>

		<?php foreach ( $checks as $category => $items ) : ?>
			<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;margin-bottom:1rem;max-width:900px;">
				<h2 style="margin-top:0;"><?php echo esc_html( $category ); ?></h2>
				<table class="wp-list-table widefat fixed" style="font-size:.9rem;">
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td style="width:30px;font-size:1.2rem;">
									<?php echo $item['status'] === 'pass' ? '✅' : ( $item['status'] === 'warn' ? '⚠️' : '❌' ); ?>
								</td>
								<td><strong><?php echo esc_html( $item['label'] ); ?></strong></td>
								<td style="color:#666;"><?php echo esc_html( $item['detail'] ); ?></td>
								<td>
									<?php if ( ! empty( $item['action_url'] ) ) : ?>
										<a href="<?php echo esc_url( $item['action_url'] ); ?>" class="button button-small">
											<?php echo esc_html( $item['action_label'] ?? 'Beheben' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<!-- 404 Log -->
		<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;margin-bottom:1rem;max-width:900px;">
			<h2>Top 404-Fehler</h2>
			<?php
			$log_404 = get_option( ABB_404_LOG_OPTION, [] );
			if ( empty( $log_404 ) ) {
				echo '<p>Keine 404-Fehler protokolliert. 🎉</p>';
			} else {
				echo '<table class="wp-list-table widefat fixed striped" style="font-size:.85rem;">';
				echo '<thead><tr><th>URL</th><th>Häufigkeit</th><th>Zuletzt gesehen</th><th>Referrer</th></tr></thead><tbody>';
				foreach ( array_slice( $log_404, 0, 20 ) as $entry ) {
					printf(
						'<tr><td><code>%s</code></td><td>%d×</td><td>%s</td><td style="word-break:break-all;font-size:.8rem;">%s</td></tr>',
						esc_html( wp_parse_url( $entry['url'], PHP_URL_PATH ) ),
						$entry['count'],
						esc_html( $entry['last_seen'] ),
						esc_html( $entry['referer'] ? wp_parse_url( $entry['referer'], PHP_URL_HOST ) : '–' )
					);
				}
				echo '</tbody></table>';
			}
			?>
			<?php if ( ! empty( $log_404 ) ) : ?>
				<form method="post" style="margin-top:.75rem;">
					<?php wp_nonce_field( 'abb_clear_404_log' ); ?>
					<input type="submit" name="abb_clear_404" class="button" value="404-Log leeren" onclick="return confirm('Wirklich löschen?')">
				</form>
			<?php endif; ?>
		</div>

		<!-- Images without Alt Text -->
		<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:1.5rem;margin-bottom:1rem;max-width:900px;">
			<h2>Bilder ohne Alt-Text</h2>
			<?php
			$missing_alts = abb_get_images_without_alt( 20 );
			if ( empty( $missing_alts ) ) {
				echo '<p>Alle Bilder haben Alt-Texte! ✅</p>';
			} else {
				echo '<p>Folgende Bilder haben keinen Alt-Text:</p>';
				echo '<table class="wp-list-table widefat fixed striped" style="font-size:.85rem;">';
				echo '<thead><tr><th>Dateiname</th><th>Alt-Text hinzufügen</th></tr></thead><tbody>';
				foreach ( $missing_alts as $img ) {
					printf(
						'<tr><td><code>%s</code></td><td><a href="%s" target="_blank">Bearbeiten</a></td></tr>',
						esc_html( basename( $img->guid ) ),
						esc_url( admin_url( 'post.php?post=' . $img->ID . '&action=edit' ) )
					);
				}
				echo '</tbody></table>';
			}
			?>
		</div>
	</div>
	<?php

	// Handle 404 log clear
	if ( isset( $_POST['abb_clear_404'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'abb_clear_404_log' )
		&& current_user_can( 'manage_options' ) ) {
		delete_option( ABB_404_LOG_OPTION );
		echo '<div class="notice notice-success"><p>404-Log geleert.</p></div>';
	}
}

function abb_run_golive_checks(): array {
	$checks = [];

	// ---- Phase 1: Security & Legal ----
	$checks['🔐 Sicherheit & Legal'] = [
		[
			'label'        => 'Impressum vorhanden und veröffentlicht',
			'status'       => get_page_by_path( 'impressum' ) && get_page_by_path( 'impressum' )->post_status === 'publish' ? 'pass' : 'fail',
			'detail'       => get_page_by_path( 'impressum' ) ? ( get_page_by_path( 'impressum' )->post_status === 'publish' ? 'Veröffentlicht' : 'Status: ' . get_page_by_path( 'impressum' )->post_status ) : 'Seite nicht gefunden',
			'action_url'   => admin_url( 'post.php?post=' . ( get_page_by_path( 'impressum' )->ID ?? 0 ) . '&action=edit' ),
			'action_label' => 'Bearbeiten',
		],
		[
			'label'        => 'Datenschutzerklärung vorhanden',
			'status'       => get_page_by_path( 'datenschutz' ) && get_page_by_path( 'datenschutz' )->post_status === 'publish' ? 'pass' : 'fail',
			'detail'       => get_page_by_path( 'datenschutz' ) ? 'Status: ' . get_page_by_path( 'datenschutz' )->post_status : 'Fehlt',
			'action_url'   => admin_url( 'post-new.php?post_type=page' ),
			'action_label' => 'Erstellen',
		],
		[
			'label'  => 'Cookie-Consent aktiv (Complianz)',
			'status' => class_exists( 'COMPLIANZ' ) ? 'pass' : 'warn',
			'detail' => class_exists( 'COMPLIANZ' ) ? 'Aktiv' : 'Complianz nicht installiert',
			'action_url'   => 'https://wordpress.org/plugins/complianz-gdpr/',
			'action_label' => 'Installieren',
		],
		[
			'label'  => 'XML-RPC deaktiviert',
			'status' => ! apply_filters( 'xmlrpc_enabled', true ) ? 'pass' : 'warn',
			'detail' => ! apply_filters( 'xmlrpc_enabled', true ) ? 'Deaktiviert ✅' : 'Aktiv – .htaccess-Regel prüfen',
		],
		[
			'label'  => 'Wartungsmodus deaktiviert',
			'status' => ! file_exists( ABSPATH . '.maintenance' ) ? 'pass' : 'fail',
			'detail' => file_exists( ABSPATH . '.maintenance' ) ? '.maintenance Datei existiert!' : 'Wartungsmodus inaktiv',
		],
	];

	// ---- Phase 2: Performance ----
	$checks['⚡ Performance'] = [
		[
			'label'  => 'Caching-Plugin aktiv',
			'status' => class_exists( 'WP_Rocket' ) || defined( 'W3TC_VERSION' ) || class_exists( 'LiteSpeed_Cache' ) ? 'pass' : 'warn',
			'detail' => 'WP Rocket, W3 Total Cache oder LiteSpeed Cache empfohlen',
			'action_url'   => 'https://wordpress.org/plugins/litespeed-cache/',
			'action_label' => 'Plugin-Vorschlag',
		],
		[
			'label'  => 'Bilder optimiert',
			'status' => class_exists( 'Smush\Core\Core' ) || defined( 'EWWW_IMAGE_OPTIMIZER_VERSION' ) ? 'pass' : 'warn',
			'detail' => 'Smush oder EWWW Image Optimizer empfohlen',
		],
		[
			'label'  => 'CDN konfiguriert',
			'status' => defined( 'CDN_URL' ) ? 'pass' : 'warn',
			'detail' => 'Cloudflare oder BunnyCDN empfohlen für unter 1,5s Ladezeit',
		],
	];

	// ---- Phase 3: SEO ----
	$checks['🔍 SEO'] = [
		[
			'label'  => 'SEO-Plugin aktiv',
			'status' => defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ? 'pass' : 'fail',
			'detail' => defined( 'RANK_MATH_VERSION' ) ? 'Rank Math aktiv' : ( defined( 'WPSEO_VERSION' ) ? 'Yoast SEO aktiv' : 'Kein SEO-Plugin!'),
			'action_url'   => 'https://wordpress.org/plugins/seo-by-rank-math/',
			'action_label' => 'Rank Math installieren',
		],
		[
			'label'  => 'Sitemap aktiv',
			'status' => 'pass', // Our plugin handles this
			'detail' => home_url( '/sitemap.xml' ),
		],
		[
			'label'  => 'robots.txt freigegeben (kein noindex)',
			'status' => get_option( 'blog_public' ) ? 'pass' : 'fail',
			'detail' => get_option( 'blog_public' ) ? 'Indexierung erlaubt' : 'WordPress "Suchmaschinen davon abhalten" ist aktiviert!',
			'action_url'   => admin_url( 'options-reading.php' ),
			'action_label' => 'Einstellung ändern',
		],
		[
			'label'  => 'Permalink-Struktur konfiguriert',
			'status' => get_option( 'permalink_structure' ) !== '' ? 'pass' : 'fail',
			'detail' => 'Empfohlen: /%category%/%postname%/',
			'action_url'   => admin_url( 'options-permalink.php' ),
			'action_label' => 'Konfigurieren',
		],
	];

	// ---- Phase 4: Features ----
	$checks['🎣 Features'] = [
		[
			'label'  => 'Beißindex-Plugin aktiv',
			'status' => function_exists( 'abb_get_beissindex' ) ? 'pass' : 'fail',
			'detail' => function_exists( 'abb_get_beissindex' ) ? 'Aktiv' : 'Plugin nicht aktiv',
		],
		[
			'label'  => 'Pegel-Plugin aktiv',
			'status' => class_exists( 'ABB_Pegel_API' ) ? 'pass' : 'fail',
			'detail' => class_exists( 'ABB_Pegel_API' ) ? 'Aktiv' : 'Plugin nicht aktiv',
		],
		[
			'label'  => 'Angelgewässer CPT registriert',
			'status' => post_type_exists( 'angelgewaesser' ) ? 'pass' : 'fail',
			'detail' => post_type_exists( 'angelgewaesser' ) ? 'CPT aktiv' : 'CPT fehlt',
		],
		[
			'label'  => 'Mindestens 1 Gewässer veröffentlicht',
			'status' => wp_count_posts( 'angelgewaesser' )->publish > 0 ? 'pass' : 'warn',
			'detail' => wp_count_posts( 'angelgewaesser' )->publish . ' Gewässer veröffentlicht',
			'action_url'   => admin_url( 'post-new.php?post_type=angelgewaesser' ),
			'action_label' => 'Gewässer erstellen',
		],
	];

	// ---- Phase 5: Content ----
	$checks['📝 Content & KI'] = [
		[
			'label'  => 'Keine Entwürfe ohne Review veröffentlicht',
			'status' => 'pass',
			'detail' => 'KI-Inhalte immer als Entwurf – manuell prüfen!',
		],
		[
			'label'  => 'Bilder ohne Alt-Text',
			'status' => abb_count_images_without_alt() === 0 ? 'pass' : 'warn',
			'detail' => abb_count_images_without_alt() . ' Bilder ohne Alt-Text',
			'action_url'   => admin_url( 'options-general.php?page=abb-golive#alt-texts' ),
			'action_label' => 'Anzeigen',
		],
	];

	return $checks;
}
