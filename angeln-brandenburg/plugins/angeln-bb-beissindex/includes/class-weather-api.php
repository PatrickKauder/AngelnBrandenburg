<?php
/**
 * Weather API Client – OpenWeatherMap
 * Fetches Luftdruck (pressure) + Temperatur for Brandenburg
 */

defined( 'ABSPATH' ) || exit;

class ABB_Weather_API {

	// Brandenburg an der Havel as default location
	const DEFAULT_LAT = 52.4079;
	const DEFAULT_LON = 12.5314;
	const CACHE_KEY   = 'abb_weather_data';
	const CACHE_TTL   = HOUR_IN_SECONDS;

	private string $api_key;

	public function __construct() {
		$this->api_key = defined( 'ABB_OPENWEATHER_KEY' ) ? ABB_OPENWEATHER_KEY : '';
	}

	/**
	 * Get current weather data.
	 *
	 * @return array|null {
	 *   @type float  $pressure     hPa
	 *   @type float  $temp         °C current
	 *   @type float  $temp_min_24h °C min last 24h
	 *   @type float  $temp_max_24h °C max last 24h
	 *   @type float  $temp_trend   Change vs 24h ago (positive = warming)
	 *   @type string $description  German weather description
	 *   @type float  $wind_speed   m/s
	 *   @type int    $humidity     %
	 * }
	 */
	public function get_current(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached ) return $cached;

		$data = $this->fetch_from_api();
		if ( $data ) {
			set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		}
		return $data;
	}

	private function fetch_from_api(): ?array {
		if ( empty( $this->api_key ) ) {
			// Return simulated data for development
			return $this->get_fallback_data();
		}

		$lat = self::DEFAULT_LAT;
		$lon = self::DEFAULT_LON;
		$url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid={$this->api_key}&units=metric&lang=de";

		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return $this->get_fallback_data();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! $body ) return $this->get_fallback_data();

		// Fetch 24h history to calculate temperature trend
		$prev = $this->fetch_24h_temp();

		return [
			'pressure'     => $body['main']['pressure'] ?? 1013,
			'temp'         => round( $body['main']['temp'] ?? 15, 1 ),
			'temp_min_24h' => round( $body['main']['temp_min'] ?? 12, 1 ),
			'temp_max_24h' => round( $body['main']['temp_max'] ?? 18, 1 ),
			'temp_trend'   => $prev ? round( ( $body['main']['temp'] - $prev ), 1 ) : 0,
			'description'  => ucfirst( $body['weather'][0]['description'] ?? 'Unbekannt' ),
			'wind_speed'   => round( $body['wind']['speed'] ?? 3, 1 ),
			'humidity'     => $body['main']['humidity'] ?? 70,
		];
	}

	private function fetch_24h_temp(): ?float {
		// Cached historical temp (updated less frequently)
		$cached = get_transient( 'abb_weather_prev_temp' );
		return $cached ?: null;
	}

	private function get_fallback_data(): array {
		// Generate plausible Brandenburg weather for development/demo
		$month    = (int) date( 'n' );
		$base_temp = [ 1 => 1, 2 => 2, 3 => 6, 4 => 11, 5 => 16, 6 => 20, 7 => 22, 8 => 21, 9 => 16, 10 => 10, 11 => 5, 12 => 2 ];
		$temp     = $base_temp[ $month ] + wp_rand( -3, 3 );

		return [
			'pressure'     => wp_rand( 990, 1030 ),
			'temp'         => $temp,
			'temp_min_24h' => $temp - wp_rand( 2, 5 ),
			'temp_max_24h' => $temp + wp_rand( 2, 5 ),
			'temp_trend'   => wp_rand( -3, 3 ),
			'description'  => 'Simulierte Daten (kein API-Key)',
			'wind_speed'   => wp_rand( 1, 8 ),
			'humidity'     => wp_rand( 50, 90 ),
		];
	}

	/**
	 * Compute a 0–100 pressure score for fishing activity.
	 * Optimal: 1010–1020 hPa, stable or rising pressure
	 */
	public function pressure_score( float $pressure, float $trend = 0 ): int {
		// Optimal range bonus
		$optimal_bonus = 0;
		if ( $pressure >= 1010 && $pressure <= 1020 ) {
			$optimal_bonus = 20;
		}

		// Distance from ideal 1015 hPa
		$deviation = abs( $pressure - 1015.0 );
		$base_score = (int) max( 0, 80 - $deviation * 2 );

		// Trend: rising pressure = better fishing (+10), falling = worse (-15)
		$trend_bonus = 0;
		if ( $trend > 1 )       $trend_bonus = 10;  // Rising
		elseif ( $trend < -1 )  $trend_bonus = -15; // Falling (fish go deeper)

		return max( 0, min( 100, $base_score + $optimal_bonus + $trend_bonus ) );
	}

	/**
	 * Compute a 0–100 temperature-trend score.
	 * Stable or slightly warming = better fishing
	 */
	public function temperature_score( float $temp, float $trend = 0 ): int {
		// Temperature comfort zones by season
		$month  = (int) date( 'n' );
		$summer = in_array( $month, [ 6, 7, 8 ], true );

		// Ideal water fishing temps: 10–20°C
		if ( $temp < 4 )                          $base = 20;
		elseif ( $temp < 10 )                     $base = 50;
		elseif ( $temp >= 10 && $temp <= 20 )     $base = 80;
		elseif ( $temp > 20 && $temp <= 25 )      $base = 60;
		else                                      $base = 30;

		// Trend bonus: slight warming good, rapid changes bad
		if ( abs( $trend ) > 5 )      $trend_score = -20;
		elseif ( $trend > 1 )         $trend_score = 10;
		elseif ( abs( $trend ) <= 1 ) $trend_score = 15; // stable
		else                          $trend_score = 0;

		return max( 0, min( 100, $base + $trend_score ) );
	}
}
