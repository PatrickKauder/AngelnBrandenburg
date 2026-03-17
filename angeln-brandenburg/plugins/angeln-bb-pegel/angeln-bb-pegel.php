<?php
/**
 * Plugin Name:       Angeln Brandenburg – Pegel-Ticker
 * Plugin URI:        https://angeln-brandenburg.de
 * Description:       Echtzeit-Wasserstand-Anbindung an Pegelonline WSV API für Brandenburg-Gewässer.
 * Version:           1.0.0
 * Author:            Angeln Brandenburg
 * License:           GPL-2.0-or-later
 * Text Domain:       angeln-bb-pegel
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ABB_PEGEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABB_PEGEL_VER', '1.0.0' );

require_once ABB_PEGEL_DIR . 'includes/class-pegel-api.php';
require_once ABB_PEGEL_DIR . 'includes/shortcode-pegel.php';
require_once ABB_PEGEL_DIR . 'includes/ajax-pegel.php';

// ============================================================
// CRON: Update pegel data every 30 minutes
// ============================================================

register_activation_hook( __FILE__, 'abb_pegel_activate' );
function abb_pegel_activate() {
	if ( ! wp_next_scheduled( 'abb_pegel_cron' ) ) {
		wp_schedule_event( time(), 'thirty_minutes', 'abb_pegel_cron' );
	}
}

register_deactivation_hook( __FILE__, 'abb_pegel_deactivate' );
function abb_pegel_deactivate() {
	wp_clear_scheduled_hook( 'abb_pegel_cron' );
}

add_filter( 'cron_schedules', 'abb_pegel_cron_intervals' );
function abb_pegel_cron_intervals( $schedules ) {
	if ( ! isset( $schedules['thirty_minutes'] ) ) {
		$schedules['thirty_minutes'] = [
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => 'Alle 30 Minuten',
		];
	}
	return $schedules;
}

add_action( 'abb_pegel_cron', 'abb_update_all_pegel_caches' );
function abb_update_all_pegel_caches() {
	$api      = new ABB_Pegel_API();
	$stations = $api->get_configured_stations();
	foreach ( $stations as $station ) {
		$api->get_current_level( $station['uuid'], true ); // force refresh
	}
}
