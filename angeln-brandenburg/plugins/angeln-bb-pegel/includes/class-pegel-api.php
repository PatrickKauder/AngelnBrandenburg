<?php
/**
 * Pegelonline WSV API Client
 * API Docs: https://pegelonline.wsv.de/webservices/rest-api/v2
 *
 * Brandenburg-relevant stations (Havel, Oder, Spree, Elbe tributaries)
 */

defined( 'ABSPATH' ) || exit;

class ABB_Pegel_API {

	const API_BASE = 'https://pegelonline.wsv.de/webservices/rest-api/v2';
	const CACHE_TTL = 30 * MINUTE_IN_SECONDS;

	/**
	 * Pre-configured Brandenburg stations.
	 * UUID from: https://pegelonline.wsv.de/gast/stammdaten
	 */
	private array $stations = [
		[
			'uuid'  => '592F5924-3A16-4892-8B51-E5E5FDB12D2B',
			'name'  => 'Brandenburg/Havel',
			'river' => 'Havel',
			'slug'  => 'havel-brandon',
		],
		[
			'uuid'  => '85D686F2-6C80-4AE3-8FB6-96FAB38D4DF5',
			'name'  => 'Rathenow',
			'river' => 'Havel',
			'slug'  => 'havel-rathenow',
		],
		[
			'uuid'  => '3CF1CB67-8665-4B8B-B4E2-B35A17BB1F60',
			'name'  => 'Magdeburg',
			'river' => 'Elbe',
			'slug'  => 'elbe-magdeburg',
		],
		[
			'uuid'  => 'AA7322CF-42BD-4CC0-8B77-BCC95FD0EEB9',
			'name'  => 'Frankfurt/Oder',
			'river' => 'Oder',
			'slug'  => 'oder-frankfurt',
		],
		[
			'uuid'  => 'PLACEHOLDER-SPREE-UUID-001',
			'name'  => 'Fürstenwalde',
			'river' => 'Spree',
			'slug'  => 'spree-fuerstenwalde',
		],
	];

	public function get_configured_stations(): array {
		// Merge default + custom from DB
		$custom = get_option( 'abb_pegel_custom_stations', [] );
		return array_merge( $this->stations, $custom );
	}

	/**
	 * Get current water level for a station.
	 *
	 * @param string $uuid            Station UUID
	 * @param bool   $force_refresh   Skip cache
	 * @return array|null {
	 *   @type string $uuid
	 *   @type string $name
	 *   @type float  $value    Current level in cm
	 *   @type int    $trend    -1=falling, 0=stable, 1=rising
	 *   @type string $unit
	 *   @type string $timestamp ISO 8601
	 *   @type string $status   'normal'|'warning'|'alarm'
	 *   @type float  $change_24h  cm change in 24 hours
	 * }
	 */
	public function get_current_level( string $uuid, bool $force_refresh = false ): ?array {
		$cache_key = 'abb_pegel_' . md5( $uuid );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached ) return $cached;
		}

		$url = self::API_BASE . "/stations/{$uuid}/W/currentmeasurement.json";

		$response = wp_remote_get( $url, [
			'timeout' => 8,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => 'AngelBrandenburg/1.0 (https://angeln-brandenburg.de)',
			],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return $this->get_mock_data( $uuid );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! $body || ! isset( $body['value'] ) ) {
			return $this->get_mock_data( $uuid );
		}

		$station_info = $this->find_station( $uuid );

		$result = [
			'uuid'       => $uuid,
			'name'       => $station_info['name'] ?? $uuid,
			'river'      => $station_info['river'] ?? '',
			'value'      => round( (float) $body['value'], 1 ),
			'trend'      => (int) ( $body['trend'] ?? 0 ),
			'unit'       => $body['unit'] ?? 'cm',
			'timestamp'  => $body['timestamp'] ?? '',
			'status'     => $this->determine_status( $body['value'] ?? 0 ),
			'change_24h' => 0, // Will be calculated from history
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Get multiple station levels at once.
	 */
	public function get_multiple_levels( array $uuids ): array {
		$results = [];
		foreach ( $uuids as $uuid ) {
			$level = $this->get_current_level( $uuid );
			if ( $level ) $results[] = $level;
		}
		return $results;
	}

	/**
	 * Get available stations near Brandenburg.
	 * Cached for 24h.
	 */
	public function search_stations( string $river = '' ): array {
		$cache_key = 'abb_pegel_stations_' . sanitize_key( $river );
		$cached    = get_transient( $cache_key );
		if ( $cached ) return $cached;

		$url = self::API_BASE . '/stations.json?waters=' . urlencode( strtoupper( $river ) );
		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) ) return [];

		$stations = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $stations ) ) return [];

		// Filter for Brandenburg area (lat 51.8–53.5, lon 11.5–14.7)
		$bb_stations = array_filter( $stations, function( $s ) {
			$lat = $s['latitude'] ?? 0;
			$lon = $s['longitude'] ?? 0;
			return $lat >= 51.8 && $lat <= 53.5 && $lon >= 11.5 && $lon <= 14.7;
		} );

		set_transient( $cache_key, array_values( $bb_stations ), DAY_IN_SECONDS );
		return array_values( $bb_stations );
	}

	private function find_station( string $uuid ): ?array {
		foreach ( $this->get_configured_stations() as $station ) {
			if ( $station['uuid'] === $uuid ) return $station;
		}
		return null;
	}

	private function determine_status( float $level ): string {
		// Generic thresholds – override per station in settings
		if ( $level > 350 ) return 'alarm';
		if ( $level > 280 ) return 'warning';
		return 'normal';
	}

	private function get_mock_data( string $uuid ): array {
		$station = $this->find_station( $uuid );
		return [
			'uuid'      => $uuid,
			'name'      => $station['name'] ?? 'Unbekannte Station',
			'river'     => $station['river'] ?? '',
			'value'     => (float) wp_rand( 120, 250 ),
			'trend'     => wp_rand( -1, 1 ),
			'unit'      => 'cm',
			'timestamp' => current_time( 'c' ),
			'status'    => 'normal',
			'change_24h'=> wp_rand( -10, 10 ),
			'_mock'     => true,
		];
	}

	/**
	 * Get trend arrow HTML.
	 */
	public static function trend_html( int $trend, float $change = 0 ): string {
		if ( $trend > 0 ) {
			return '<span class="abb-trend-up" title="Steigend">↑ +' . abs( $change ) . ' cm</span>';
		} elseif ( $trend < 0 ) {
			return '<span class="abb-trend-down" title="Fallend">↓ -' . abs( $change ) . ' cm</span>';
		}
		return '<span class="abb-trend-flat" title="Stabil">→ stabil</span>';
	}

	/**
	 * Fishing suitability based on level.
	 * High levels = turbid water, low visibility, harder fishing.
	 */
	public static function fishing_suitability( float $level, string $river = '' ): string {
		if ( $level < 80 )  return '⚠️ Sehr niedrig';
		if ( $level < 120 ) return '✅ Optimal';
		if ( $level < 200 ) return '✅ Gut';
		if ( $level < 280 ) return '🟡 Erhöht';
		return '🔴 Hochwasser';
	}
}
