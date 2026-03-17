<?php
/**
 * Plugin Name:       Angeln Brandenburg – Beißindex
 * Plugin URI:        https://angeln-brandenburg.de
 * Description:       Täglicher Beißindex-Algorithmus basierend auf Luftdruck, Mondphase und Temperatur-Trend.
 * Version:           1.0.0
 * Author:            Angeln Brandenburg
 * License:           GPL-2.0-or-later
 * Text Domain:       angeln-bb-beissindex
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ABB_BEISSINDEX_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABB_BEISSINDEX_VER', '1.0.0' );

require_once ABB_BEISSINDEX_DIR . 'includes/class-beissindex-calculator.php';
require_once ABB_BEISSINDEX_DIR . 'includes/class-moon-phase.php';
require_once ABB_BEISSINDEX_DIR . 'includes/class-weather-api.php';
require_once ABB_BEISSINDEX_DIR . 'includes/shortcode-beissindex.php';
require_once ABB_BEISSINDEX_DIR . 'includes/widget-beissindex.php';
require_once ABB_BEISSINDEX_DIR . 'includes/ajax-handlers.php';
require_once ABB_BEISSINDEX_DIR . 'includes/admin-settings.php';

// ============================================================
// CRON: Update Beißindex every 3 hours
// ============================================================

register_activation_hook( __FILE__, 'abb_beissindex_activate' );
function abb_beissindex_activate() {
	if ( ! wp_next_scheduled( 'abb_beissindex_cron' ) ) {
		wp_schedule_event( time(), 'three_hours', 'abb_beissindex_cron' );
	}
}

register_deactivation_hook( __FILE__, 'abb_beissindex_deactivate' );
function abb_beissindex_deactivate() {
	wp_clear_scheduled_hook( 'abb_beissindex_cron' );
}

add_filter( 'cron_schedules', 'abb_add_cron_intervals' );
function abb_add_cron_intervals( $schedules ) {
	$schedules['three_hours'] = [
		'interval' => 3 * HOUR_IN_SECONDS,
		'display'  => 'Alle 3 Stunden',
	];
	return $schedules;
}

add_action( 'abb_beissindex_cron', 'abb_update_beissindex_cache' );
function abb_update_beissindex_cache() {
	$calculator = new ABB_Beissindex_Calculator();
	$result     = $calculator->calculate();
	set_transient( 'abb_beissindex_cache', $result, 3 * HOUR_IN_SECONDS );
}

// ============================================================
// PUBLIC API: Get current Beißindex data
// ============================================================

function abb_get_beissindex() {
	$cached = get_transient( 'abb_beissindex_cache' );
	if ( $cached ) return $cached;

	$calculator = new ABB_Beissindex_Calculator();
	$result     = $calculator->calculate();
	set_transient( 'abb_beissindex_cache', $result, 3 * HOUR_IN_SECONDS );
	return $result;
}
