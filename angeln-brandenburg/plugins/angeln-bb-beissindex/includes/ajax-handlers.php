<?php
/**
 * AJAX Handlers – Beißindex live updates
 */

defined( 'ABSPATH' ) || exit;

// Public AJAX (logged-in and non-logged-in users)
add_action( 'wp_ajax_abb_get_beissindex',        'abb_ajax_get_beissindex' );
add_action( 'wp_ajax_nopriv_abb_get_beissindex', 'abb_ajax_get_beissindex' );

function abb_ajax_get_beissindex() {
	check_ajax_referer( 'abb_nonce', 'nonce' );

	$data = abb_get_beissindex();

	wp_send_json_success( [
		'score'   => $data['score'],
		'label'   => $data['label'],
		'emoji'   => $data['emoji'],
		'factors' => [
			'luftdruck'  => $data['factors']['luftdruck']['value'],
			'mondphase'  => $data['factors']['mondphase']['value'],
			'temperatur' => $data['factors']['temperatur']['value'],
		],
		'tip'     => $data['tip'],
	] );
}

// Admin: force cache refresh
add_action( 'wp_ajax_abb_refresh_beissindex', 'abb_ajax_refresh_beissindex' );
function abb_ajax_refresh_beissindex() {
	check_ajax_referer( 'abb_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

	delete_transient( 'abb_beissindex_cache' );
	delete_transient( 'abb_weather_data' );

	$calculator = new ABB_Beissindex_Calculator();
	$result     = $calculator->calculate();
	set_transient( 'abb_beissindex_cache', $result, 3 * HOUR_IN_SECONDS );

	wp_send_json_success( [ 'score' => $result['score'], 'label' => $result['label'] ] );
}
