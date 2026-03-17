<?php
/**
 * AJAX Handlers – Pegel live data
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_abb_get_pegel',        'abb_ajax_get_pegel' );
add_action( 'wp_ajax_nopriv_abb_get_pegel', 'abb_ajax_get_pegel' );

function abb_ajax_get_pegel() {
	check_ajax_referer( 'abb_nonce', 'nonce' );

	$uuid = sanitize_text_field( wp_unslash( $_GET['uuid'] ?? '' ) );
	if ( empty( $uuid ) ) {
		wp_send_json_error( 'Missing UUID' );
	}

	$api   = new ABB_Pegel_API();
	$level = $api->get_current_level( $uuid );

	if ( ! $level ) {
		wp_send_json_error( 'Station not found' );
	}

	wp_send_json_success( $level );
}

// Bulk update for ticker widget
add_action( 'wp_ajax_abb_get_pegel_bulk',        'abb_ajax_get_pegel_bulk' );
add_action( 'wp_ajax_nopriv_abb_get_pegel_bulk', 'abb_ajax_get_pegel_bulk' );

function abb_ajax_get_pegel_bulk() {
	check_ajax_referer( 'abb_nonce', 'nonce' );

	$uuids = array_map(
		'sanitize_text_field',
		json_decode( wp_unslash( $_POST['uuids'] ?? '[]' ), true ) ?? []
	);

	if ( empty( $uuids ) || count( $uuids ) > 20 ) {
		wp_send_json_error( 'Invalid request' );
	}

	$api    = new ABB_Pegel_API();
	$levels = $api->get_multiple_levels( $uuids );

	wp_send_json_success( $levels );
}
