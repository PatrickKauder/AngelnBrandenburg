<?php
/**
 * Beißindex Calculator – Core Algorithm
 *
 * PHASE 4: Luftdruck + Mondphase + Temperatur-Trend
 *
 * Score breakdown:
 *   Luftdruck (35%):    Pressure level + trend
 *   Mondphase (30%):    Distance from new/full moon
 *   Temperatur (25%):   Comfort zone + trend
 *   Tageszeit (10%):    Dawn/dusk bonus
 *
 * Final score 0–100:
 *   0–25   → Sehr schlecht 🔴
 *   26–45  → Schlecht 🟠
 *   46–65  → Mäßig 🟡
 *   66–80  → Gut 🟢
 *   81–100 → Sehr gut 💚
 */

defined( 'ABSPATH' ) || exit;

class ABB_Beissindex_Calculator {

	private ABB_Weather_API $weather;
	private ABB_Moon_Phase  $moon;

	// Algorithm weights (must sum to 1.0)
	const WEIGHT_PRESSURE    = 0.35;
	const WEIGHT_MOON        = 0.30;
	const WEIGHT_TEMPERATURE = 0.25;
	const WEIGHT_DAYTIME     = 0.10;

	public function __construct() {
		$this->weather = new ABB_Weather_API();
		$this->moon    = new ABB_Moon_Phase();
	}

	/**
	 * Calculate Beißindex.
	 *
	 * @return array Complete Beißindex data for display and caching
	 */
	public function calculate(): array {
		$weather    = $this->weather->get_current();
		$moon_data  = $this->moon->get_phase();
		$next_phase = $this->moon->days_to_next_major_phase();

		// Component scores (0–100 each)
		$pressure_score  = $this->weather->pressure_score(
			$weather['pressure'],
			$weather['temp_trend']
		);
		$moon_score      = $moon_data['score'];
		$temp_score      = $this->weather->temperature_score(
			$weather['temp'],
			$weather['temp_trend']
		);
		$daytime_score   = $this->daytime_score();

		// Weighted total
		$total = (int) round(
			$pressure_score  * self::WEIGHT_PRESSURE +
			$moon_score      * self::WEIGHT_MOON +
			$temp_score      * self::WEIGHT_TEMPERATURE +
			$daytime_score   * self::WEIGHT_DAYTIME
		);
		$total = max( 0, min( 100, $total ) );

		// Special penalties
		$total = $this->apply_penalties( $total, $weather );

		$label = $this->score_to_label( $total );

		return [
			'score'        => $total,
			'label'        => $label['text'],
			'emoji'        => $label['emoji'],
			'color'        => $label['color'],
			'description'  => $this->generate_description( $total, $weather, $moon_data ),
			'updated_at'   => current_time( 'mysql' ),
			'factors'      => [
				'luftdruck'   => [
					'value'  => $weather['pressure'] . ' hPa',
					'score'  => $pressure_score,
					'label'  => 'Luftdruck',
					'icon'   => '🌡️',
					'trend'  => $weather['temp_trend'] > 1 ? 'steigend' : ( $weather['temp_trend'] < -1 ? 'fallend' : 'stabil' ),
				],
				'mondphase'   => [
					'value'  => $moon_data['phase_name'] . ' ' . $moon_data['emoji'],
					'score'  => $moon_score,
					'label'  => 'Mondphase',
					'icon'   => $moon_data['emoji'],
					'illumination' => $moon_data['illumination'] . '%',
					'next_phase'   => $next_phase,
				],
				'temperatur'  => [
					'value'  => $weather['temp'] . '°C',
					'score'  => $temp_score,
					'label'  => 'Temperatur',
					'icon'   => '🌡',
					'trend'  => $weather['temp_trend'] > 0 ? '+' . $weather['temp_trend'] . '°C' : $weather['temp_trend'] . '°C',
				],
				'tageszeit'   => [
					'value'  => $this->current_period_name(),
					'score'  => $daytime_score,
					'label'  => 'Tageszeit',
					'icon'   => $this->current_period_icon(),
				],
			],
			'weather'      => [
				'temp'        => $weather['temp'],
				'pressure'    => $weather['pressure'],
				'wind'        => $weather['wind_speed'],
				'humidity'    => $weather['humidity'],
				'description' => $weather['description'],
			],
			'best_times'   => $this->get_best_times(),
			'tip'          => $this->get_fishing_tip( $total, $moon_data, $weather ),
		];
	}

	/**
	 * Time-of-day score – fish most active at dawn/dusk
	 */
	private function daytime_score(): int {
		$hour = (int) current_time( 'G' );

		// Dawn (4:00–7:00): 90
		if ( $hour >= 4 && $hour <= 7 )  return 90;
		// Morning (7:00–10:00): 75
		if ( $hour >= 7 && $hour <= 10 ) return 75;
		// Midday (10:00–16:00): 50 in summer, 65 in winter
		if ( $hour >= 10 && $hour <= 16 ) {
			$month = (int) current_time( 'n' );
			return in_array( $month, [ 6, 7, 8 ], true ) ? 40 : 65;
		}
		// Evening (16:00–20:00): 85
		if ( $hour >= 16 && $hour <= 20 ) return 85;
		// Dusk (20:00–22:00): 70
		if ( $hour >= 20 && $hour <= 22 ) return 70;
		// Night: 35
		return 35;
	}

	private function current_period_name(): string {
		$hour = (int) current_time( 'G' );
		if ( $hour >= 4 && $hour < 7 )   return 'Morgendämmerung';
		if ( $hour >= 7 && $hour < 10 )  return 'Morgen';
		if ( $hour >= 10 && $hour < 16 ) return 'Mittag';
		if ( $hour >= 16 && $hour < 20 ) return 'Nachmittag';
		if ( $hour >= 20 && $hour < 22 ) return 'Abend';
		return 'Nacht';
	}

	private function current_period_icon(): string {
		$hour = (int) current_time( 'G' );
		if ( $hour >= 4 && $hour < 7 )   return '🌅';
		if ( $hour >= 7 && $hour < 10 )  return '☀️';
		if ( $hour >= 10 && $hour < 16 ) return '🌤️';
		if ( $hour >= 16 && $hour < 20 ) return '🌇';
		if ( $hour >= 20 && $hour < 22 ) return '🌆';
		return '🌙';
	}

	/**
	 * Apply special environmental penalties
	 */
	private function apply_penalties( int $score, array $weather ): int {
		// Strong wind penalty
		if ( $weather['wind_speed'] > 8 ) {
			$score -= 15;
		} elseif ( $weather['wind_speed'] > 5 ) {
			$score -= 5;
		}

		// Extreme cold penalty (winter)
		if ( $weather['temp'] < 2 ) {
			$score -= 10;
		}

		// Extreme heat penalty
		if ( $weather['temp'] > 28 ) {
			$score -= 10;
		}

		// Very low pressure (incoming storm)
		if ( $weather['pressure'] < 990 ) {
			$score -= 20;
		}

		return max( 0, min( 100, $score ) );
	}

	private function score_to_label( int $score ): array {
		if ( $score >= 81 ) return [ 'text' => 'Sehr gut',     'emoji' => '🟢', 'color' => '#27ae60' ];
		if ( $score >= 66 ) return [ 'text' => 'Gut',          'emoji' => '🟢', 'color' => '#2ecc71' ];
		if ( $score >= 46 ) return [ 'text' => 'Mäßig',        'emoji' => '🟡', 'color' => '#f39c12' ];
		if ( $score >= 26 ) return [ 'text' => 'Schlecht',     'emoji' => '🟠', 'color' => '#e67e22' ];
		return                     [ 'text' => 'Sehr schlecht','emoji' => '🔴', 'color' => '#e74c3c' ];
	}

	private function generate_description( int $score, array $weather, array $moon ): string {
		$parts = [];

		if ( $score >= 66 ) {
			$parts[] = 'Heute sind die Bedingungen für das Angeln in Brandenburg sehr vielversprechend.';
		} elseif ( $score >= 46 ) {
			$parts[] = 'Die Angelbedingungen sind heute durchschnittlich.';
		} else {
			$parts[] = 'Heute sind die Angelbedingungen schwieriger als üblich.';
		}

		$parts[] = sprintf(
			'Luftdruck: %s hPa. Mondphase: %s (%s%% beleuchtet).',
			$weather['pressure'],
			$moon['phase_name'],
			$moon['illumination']
		);

		return implode( ' ', $parts );
	}

	private function get_best_times(): array {
		return [
			[ 'time' => '04:30–07:00', 'quality' => 'Sehr gut',  'icon' => '🌅' ],
			[ 'time' => '16:00–19:30', 'quality' => 'Gut',       'icon' => '🌇' ],
			[ 'time' => '08:00–10:00', 'quality' => 'Mäßig',     'icon' => '☀️' ],
		];
	}

	private function get_fishing_tip( int $score, array $moon, array $weather ): string {
		$tips = [];

		if ( $moon['score'] > 70 ) {
			$tips[] = "Vollmond-Bonus: Fische sind bei " . $moon['phase_name'] . " besonders aktiv – gute Zeit für Raubfische!";
		}
		if ( $weather['pressure'] > 1015 && $weather['temp_trend'] > 0 ) {
			$tips[] = "Steigender Luftdruck zeigt an: Ideale Bedingungen für Friedfische.";
		}
		if ( $score >= 70 ) {
			$tips[] = "Empfohlene Köder: Würmer, Maiskörner und Forellenhaken für Barsch und Zander.";
		} elseif ( $score < 40 ) {
			$tips[] = "Bei diesen Bedingungen: Tiefer angeln (Thermokline beachten) und geduldig sein.";
		}

		return ! empty( $tips ) ? $tips[ array_rand( $tips ) ] : 'Viel Erfolg beim Angeln in Brandenburg!';
	}
}
