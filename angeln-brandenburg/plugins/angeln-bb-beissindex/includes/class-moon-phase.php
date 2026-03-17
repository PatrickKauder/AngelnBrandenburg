<?php
/**
 * Moon Phase Calculator
 * Based on the algorithm by Keith Burnett
 * Optimized for fishing activity prediction
 */

defined( 'ABSPATH' ) || exit;

class ABB_Moon_Phase {

	/**
	 * Calculate moon phase for a given Unix timestamp.
	 *
	 * @param int $timestamp Unix timestamp (default: now)
	 * @return array {
	 *   @type float  $phase       Phase as 0.0–1.0 (0=New Moon, 0.5=Full Moon)
	 *   @type string $phase_name  German phase name
	 *   @type string $emoji       Moon emoji
	 *   @type float  $illumination Illumination percentage 0–100
	 *   @type int    $score       Activity score 0–100 (higher = better fishing)
	 * }
	 */
	public function get_phase( int $timestamp = 0 ): array {
		$timestamp = $timestamp ?: time();
		$jd        = $this->unix_to_julian( $timestamp );
		$phase     = $this->julian_to_phase( $jd );

		return [
			'phase'        => $phase,
			'phase_name'   => $this->get_phase_name( $phase ),
			'emoji'        => $this->get_phase_emoji( $phase ),
			'illumination' => round( $this->get_illumination( $phase ), 1 ),
			'score'        => $this->get_activity_score( $phase ),
		];
	}

	private function unix_to_julian( int $timestamp ): float {
		return ( $timestamp / 86400.0 ) + 2440587.5;
	}

	private function julian_to_phase( float $jd ): float {
		// Known new moon: January 6, 2000 at 18:14 UTC → JD 2451550.1
		$known_new_moon = 2451550.1;
		$synodic_month  = 29.530588853; // Days
		$phase = fmod( ( $jd - $known_new_moon ) / $synodic_month, 1.0 );
		if ( $phase < 0 ) $phase += 1.0;
		return $phase;
	}

	private function get_illumination( float $phase ): float {
		// Illumination follows a cosine curve: 0 at new moon, 100 at full moon
		return ( 1.0 - cos( $phase * 2 * M_PI ) ) / 2.0 * 100;
	}

	private function get_phase_name( float $phase ): string {
		if ( $phase < 0.03 || $phase > 0.97 ) return 'Neumond';
		if ( $phase < 0.22 ) return 'Zunehmende Sichel';
		if ( $phase < 0.28 ) return 'Erstes Viertel';
		if ( $phase < 0.47 ) return 'Zunehmender Mond';
		if ( $phase < 0.53 ) return 'Vollmond';
		if ( $phase < 0.72 ) return 'Abnehmender Mond';
		if ( $phase < 0.78 ) return 'Letztes Viertel';
		return 'Abnehmende Sichel';
	}

	private function get_phase_emoji( float $phase ): string {
		$emojis = [ '🌑', '🌒', '🌓', '🌔', '🌕', '🌖', '🌗', '🌘' ];
		$index  = (int) round( $phase * 8 ) % 8;
		return $emojis[ $index ];
	}

	/**
	 * Fishing activity score based on moon phase.
	 *
	 * Research shows fish are most active during:
	 * - New Moon (phase ~0): Feeding frenzy
	 * - Full Moon (phase ~0.5): High activity, especially nocturnal
	 * - Quarter moons: Moderate activity
	 */
	private function get_activity_score( float $phase ): int {
		// Distance from nearest new or full moon
		$dist_new  = min( $phase, 1.0 - $phase );          // 0 = new moon
		$dist_full = abs( $phase - 0.5 );                   // 0 = full moon
		$dist      = min( $dist_new, $dist_full );          // closer to either

		// Gaussian score: max at phase=0 or 0.5
		$score = (int) round( 100 * exp( -( $dist * $dist ) / ( 2 * 0.03 ) ) );

		// Quarter moons get a bonus (30–50)
		if ( $dist > 0.1 && $dist < 0.15 ) {
			$score = max( $score, 45 );
		}

		// Ensure range 10–100
		return max( 10, min( 100, $score ) );
	}

	/**
	 * Get days until next major phase.
	 */
	public function days_to_next_major_phase(): array {
		$now  = time();
		$jd   = $this->unix_to_julian( $now );
		$phase = $this->julian_to_phase( $jd );
		$synodic = 29.530588853;

		$phases = [
			0.0  => 'Neumond',
			0.25 => 'Erstes Viertel',
			0.5  => 'Vollmond',
			0.75 => 'Letztes Viertel',
		];

		$closest     = null;
		$min_days    = PHP_INT_MAX;
		$closest_name = '';

		foreach ( $phases as $target => $name ) {
			$diff = $target - $phase;
			if ( $diff < 0 ) $diff += 1.0;
			$days = $diff * $synodic;
			if ( $days < $min_days ) {
				$min_days     = $days;
				$closest_name = $name;
			}
		}

		return [
			'name' => $closest_name,
			'days' => round( $min_days, 1 ),
		];
	}
}
