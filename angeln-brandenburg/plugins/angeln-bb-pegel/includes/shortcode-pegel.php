<?php
/**
 * Shortcode: [pegel_ticker] and [pegel_station uuid="..."]
 *
 * Usage:
 *   [pegel_ticker rivers="havel,oder"]
 *   [pegel_station uuid="592F5924-..." name="Havel Brandenburg"]
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'pegel_ticker',  'abb_shortcode_pegel_ticker' );
add_shortcode( 'pegel_station', 'abb_shortcode_pegel_single' );

function abb_shortcode_pegel_ticker( $atts ) {
	$atts = shortcode_atts( [
		'rivers' => '',
		'limit'  => 5,
		'title'  => 'Pegel-Ticker Brandenburg',
	], $atts );

	$api      = new ABB_Pegel_API();
	$stations = $api->get_configured_stations();

	// Filter by river if specified
	if ( ! empty( $atts['rivers'] ) ) {
		$filter   = array_map( 'strtolower', array_map( 'trim', explode( ',', $atts['rivers'] ) ) );
		$stations = array_filter( $stations, function( $s ) use ( $filter ) {
			return in_array( strtolower( $s['river'] ), $filter, true );
		} );
	}

	$stations = array_slice( array_values( $stations ), 0, (int) $atts['limit'] );
	$levels   = [];
	foreach ( $stations as $station ) {
		$level = $api->get_current_level( $station['uuid'] );
		if ( $level ) $levels[] = $level;
	}

	if ( empty( $levels ) ) {
		return '<p class="abb-pegel-no-data">Keine Pegeldaten verfügbar.</p>';
	}

	ob_start();
	?>
	<div class="abb-pegel-ticker"
		 data-pegel-ticker
		 data-stations="<?php echo esc_attr( wp_json_encode( array_column( $stations, 'uuid' ) ) ); ?>">

		<div class="abb-pegel-ticker__header">
			<?php echo esc_html( $atts['title'] ); ?>
			<span style="font-size:.75rem;font-weight:400;opacity:.7;margin-left:auto;" class="pegel-timestamp"></span>
		</div>

		<table class="abb-pegel-table" aria-label="<?php echo esc_attr( $atts['title'] ); ?>">
			<thead>
				<tr>
					<th>Station</th>
					<th>Fluss</th>
					<th>Pegelstand</th>
					<th>Trend</th>
					<th>Angeln</th>
				</tr>
			</thead>
			<tbody class="abb-pegel-tbody">
				<?php foreach ( $levels as $level ) : ?>
					<tr data-uuid="<?php echo esc_attr( $level['uuid'] ); ?>">
						<td><strong><?php echo esc_html( $level['name'] ); ?></strong></td>
						<td><?php echo esc_html( $level['river'] ); ?></td>
						<td class="pegel-value"><?php echo esc_html( $level['value'] . ' ' . $level['unit'] ); ?></td>
						<td class="pegel-trend <?php echo 'abb-trend-' . ( $level['trend'] > 0 ? 'up' : ( $level['trend'] < 0 ? 'down' : 'flat' ) ); ?>">
							<?php echo esc_html( $level['trend'] > 0 ? '↑' : ( $level['trend'] < 0 ? '↓' : '→' ) ); ?>
						</td>
						<td><?php echo ABB_Pegel_API::fishing_suitability( $level['value'], $level['river'] ); // phpcs:ignore ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p style="font-size:.75rem;color:#888;margin-top:.5rem;margin-bottom:0;">
			Quelle: <a href="https://pegelonline.wsv.de" target="_blank" rel="noopener">Pegelonline WSV</a> |
			Auto-Update alle 10 Minuten
			<?php if ( isset( $levels[0]['_mock'] ) ) echo ' | ⚠️ Demo-Daten'; ?>
		</p>
	</div>
	<?php
	return ob_get_clean();
}

function abb_shortcode_pegel_single( $atts ) {
	$atts  = shortcode_atts( [ 'uuid' => '', 'name' => '' ], $atts );
	if ( empty( $atts['uuid'] ) ) return '';

	$api   = new ABB_Pegel_API();
	$level = $api->get_current_level( $atts['uuid'] );
	if ( ! $level ) return '<p>Keine Pegeldaten verfügbar.</p>';

	$name  = $atts['name'] ?: $level['name'];

	return sprintf(
		'<div class="abb-pegel-inline" style="display:inline-flex;align-items:center;gap:.5rem;background:var(--color-gray-1,#f4f7fa);border:1px solid var(--color-gray-2,#e2eaf1);border-radius:.5rem;padding:.4rem .8rem;font-size:.9rem;">
			📊 <strong>%s:</strong>
			<span class="pegel-value">%s %s</span>
			<span class="pegel-trend">%s</span>
			<span style="color:#888;font-size:.8rem;">(%s)</span>
		</div>',
		esc_html( $name ),
		esc_html( $level['value'] ),
		esc_html( $level['unit'] ),
		ABB_Pegel_API::trend_html( $level['trend'], $level['change_24h'] ), // phpcs:ignore
		esc_html( ABB_Pegel_API::fishing_suitability( $level['value'] ) )
	);
}
