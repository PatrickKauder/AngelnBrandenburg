<?php
/**
 * Shortcode: [beissindex] and [beissindex_mini]
 *
 * Usage:
 *   [beissindex]
 *   [beissindex_mini label="Heute angeln?"]
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'beissindex',      'abb_shortcode_beissindex_full' );
add_shortcode( 'beissindex_mini', 'abb_shortcode_beissindex_mini' );

function abb_shortcode_beissindex_full( $atts ) {
	$data = abb_get_beissindex();
	ob_start();
	?>
	<div class="abb-beissindex" data-beissindex-widget aria-label="Beißindex Brandenburg">
		<h2 class="abb-beissindex__title">🎣 Beißindex Brandenburg – <?php echo esc_html( date_i18n( 'd. F Y' ) ); ?></h2>

		<div class="abb-beissindex__grid">
			<!-- Luftdruck -->
			<div class="abb-beissindex__factor">
				<div class="abb-beissindex__factor-icon"><?php echo esc_html( $data['factors']['luftdruck']['icon'] ); ?></div>
				<div class="abb-beissindex__factor-label">Luftdruck</div>
				<div class="abb-beissindex__factor-value" data-factor="luftdruck">
					<?php echo esc_html( $data['factors']['luftdruck']['value'] ); ?>
				</div>
				<div style="font-size:.75rem;opacity:.6;margin-top:.25rem;">
					<?php echo esc_html( $data['factors']['luftdruck']['trend'] ); ?>
				</div>
			</div>

			<!-- Mondphase -->
			<div class="abb-beissindex__factor">
				<div class="abb-beissindex__factor-icon"><?php echo esc_html( $data['factors']['mondphase']['icon'] ); ?></div>
				<div class="abb-beissindex__factor-label">Mondphase</div>
				<div class="abb-beissindex__factor-value" data-factor="mondphase">
					<?php echo esc_html( $data['factors']['mondphase']['value'] ); ?>
				</div>
				<div style="font-size:.75rem;opacity:.6;margin-top:.25rem;">
					<?php echo esc_html( $data['factors']['mondphase']['illumination'] ); ?> beleuchtet
				</div>
			</div>

			<!-- Temperatur -->
			<div class="abb-beissindex__factor">
				<div class="abb-beissindex__factor-icon">🌡</div>
				<div class="abb-beissindex__factor-label">Temperatur</div>
				<div class="abb-beissindex__factor-value" data-factor="temperatur">
					<?php echo esc_html( $data['factors']['temperatur']['value'] ); ?>
				</div>
				<div style="font-size:.75rem;opacity:.6;margin-top:.25rem;">
					Trend: <?php echo esc_html( $data['factors']['temperatur']['trend'] ); ?>
				</div>
			</div>
		</div>

		<!-- Gauge -->
		<div class="abb-beissindex__gauge">
			<div style="font-size:.85rem;opacity:.7;margin-bottom:.5rem;">Gesamtbewertung</div>
			<div class="abb-gauge-track" role="progressbar" aria-valuenow="<?php echo esc_attr( $data['score'] ); ?>" aria-valuemin="0" aria-valuemax="100">
				<div class="abb-gauge-fill" data-score="<?php echo esc_attr( $data['score'] ); ?>" style="width:0%"></div>
			</div>
			<div class="abb-gauge-label">
				<?php echo esc_html( $data['score'] ); ?>/100 –
				<?php echo esc_html( $data['emoji'] ); ?> <?php echo esc_html( $data['label'] ); ?>
			</div>
		</div>

		<!-- Best Times -->
		<div style="margin-top:1.5rem;">
			<div style="font-size:.85rem;opacity:.7;font-weight:600;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.75rem;">Beste Angelzeiten heute</div>
			<div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;">
				<?php foreach ( $data['best_times'] as $bt ) : ?>
					<div style="background:rgba(255,255,255,.08);border-radius:.5rem;padding:.5rem 1rem;font-size:.85rem;">
						<?php echo esc_html( $bt['icon'] . ' ' . $bt['time'] ); ?> – <strong><?php echo esc_html( $bt['quality'] ); ?></strong>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Tip -->
		<?php if ( $data['tip'] ) : ?>
			<div style="margin-top:1.25rem;background:rgba(255,255,255,.06);border-radius:.75rem;padding:1rem 1.25rem;font-size:.9rem;border-left:3px solid #5b9bd5;">
				💡 <?php echo esc_html( $data['tip'] ); ?>
			</div>
		<?php endif; ?>

		<div style="margin-top:1rem;font-size:.75rem;opacity:.5;text-align:center;">
			Aktualisiert: <?php echo esc_html( $data['updated_at'] ); ?> | Nächste Aktualisierung in 3h
		</div>
	</div>
	<?php
	return ob_get_clean();
}

function abb_shortcode_beissindex_mini( $atts ) {
	$atts = shortcode_atts( [ 'label' => 'Beißindex' ], $atts );
	$data = abb_get_beissindex();

	return sprintf(
		'<span class="abb-beissindex-mini" title="%s: %d/100 – %s" style="display:inline-flex;align-items:center;gap:.4rem;background:%s;color:#fff;padding:.3rem .8rem;border-radius:9999px;font-weight:700;font-size:.9rem;">%s %s %s/100</span>',
		esc_attr( $atts['label'] ),
		$data['score'],
		esc_attr( $data['label'] ),
		esc_attr( $data['color'] ),
		esc_html( $data['emoji'] ),
		esc_html( $atts['label'] ),
		$data['score']
	);
}
