<?php
/**
 * Admin Settings Page – Beißindex
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'abb_beissindex_admin_menu' );
function abb_beissindex_admin_menu() {
	add_options_page(
		'Beißindex Einstellungen',
		'Beißindex',
		'manage_options',
		'abb-beissindex',
		'abb_beissindex_settings_page'
	);
}

function abb_beissindex_settings_page() {
	$data = abb_get_beissindex();
	?>
	<div class="wrap">
		<h1>🎣 Beißindex Einstellungen</h1>

		<div style="background:#fff;border:1px solid #ccd0d4;padding:1.5rem;border-radius:4px;max-width:700px;margin-top:1rem;">
			<h2>Aktueller Beißindex</h2>
			<p>Score: <strong><?php echo esc_html( $data['score'] ); ?>/100</strong> – <?php echo esc_html( $data['emoji'] . ' ' . $data['label'] ); ?></p>
			<p>Aktualisiert: <?php echo esc_html( $data['updated_at'] ); ?></p>

			<form method="post" style="margin-top:1rem;">
				<?php wp_nonce_field( 'abb_refresh_action', 'abb_refresh_nonce' ); ?>
				<input type="submit" name="abb_refresh" class="button button-primary" value="Cache leeren & neu berechnen">
			</form>
		</div>

		<div style="background:#fff;border:1px solid #ccd0d4;padding:1.5rem;border-radius:4px;max-width:700px;margin-top:1rem;">
			<h2>OpenWeatherMap API-Key</h2>
			<p>Füge deinen API-Key in <code>wp-config.php</code> ein:</p>
			<pre style="background:#f0f0f0;padding:1rem;border-radius:4px;">define( 'ABB_OPENWEATHER_KEY', 'dein-api-key-hier' );</pre>
			<p><a href="https://openweathermap.org/api" target="_blank">→ Kostenloser API-Key (bis 1000 Anfragen/Tag)</a></p>
			<p><?php echo defined( 'ABB_OPENWEATHER_KEY' ) ? '✅ API-Key konfiguriert' : '⚠️ Kein API-Key – Simulierte Daten werden verwendet'; ?></p>
		</div>

		<div style="background:#fff;border:1px solid #ccd0d4;padding:1.5rem;border-radius:4px;max-width:700px;margin-top:1rem;">
			<h2>Faktoren-Details</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Faktor</th><th>Wert</th><th>Score (0-100)</th><th>Gewichtung</th></tr></thead>
				<tbody>
					<tr><td>🌡️ Luftdruck</td><td><?php echo esc_html( $data['factors']['luftdruck']['value'] ); ?></td><td><?php echo esc_html( $data['factors']['luftdruck']['score'] ); ?></td><td>35%</td></tr>
					<tr><td><?php echo esc_html( $data['factors']['mondphase']['icon'] ); ?> Mondphase</td><td><?php echo esc_html( $data['factors']['mondphase']['value'] ); ?></td><td><?php echo esc_html( $data['factors']['mondphase']['score'] ); ?></td><td>30%</td></tr>
					<tr><td>🌡 Temperatur</td><td><?php echo esc_html( $data['factors']['temperatur']['value'] ); ?></td><td><?php echo esc_html( $data['factors']['temperatur']['score'] ); ?></td><td>25%</td></tr>
					<tr><td><?php echo esc_html( $data['factors']['tageszeit']['icon'] ); ?> Tageszeit</td><td><?php echo esc_html( $data['factors']['tageszeit']['value'] ); ?></td><td><?php echo esc_html( $data['factors']['tageszeit']['score'] ); ?></td><td>10%</td></tr>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

add_action( 'admin_init', 'abb_handle_beissindex_refresh' );
function abb_handle_beissindex_refresh() {
	if ( ! isset( $_POST['abb_refresh'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['abb_refresh_nonce'] ) ), 'abb_refresh_action' ) ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;

	delete_transient( 'abb_beissindex_cache' );
	delete_transient( 'abb_weather_data' );
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-success is-dismissible"><p>Beißindex-Cache geleert und neu berechnet!</p></div>';
	} );
}
