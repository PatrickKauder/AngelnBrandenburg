<?php
/**
 * 404 Monitor – Phase 6
 * Logs 404 errors and alerts admin for broken links.
 */

defined( 'ABSPATH' ) || exit;

const ABB_404_LOG_OPTION = 'abb_404_log';
const ABB_404_MAX_LOG    = 100;

add_action( 'template_redirect', 'abb_log_404_errors' );
function abb_log_404_errors() {
	if ( ! is_404() ) return;

	$url     = esc_url_raw( ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http' )
		. '://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) )
		. sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) );

	$log     = get_option( ABB_404_LOG_OPTION, [] );
	$key     = md5( $url );

	if ( isset( $log[ $key ] ) ) {
		$log[ $key ]['count']++;
		$log[ $key ]['last_seen'] = current_time( 'mysql' );
	} else {
		$log[ $key ] = [
			'url'        => $url,
			'referer'    => $referer,
			'count'      => 1,
			'first_seen' => current_time( 'mysql' ),
			'last_seen'  => current_time( 'mysql' ),
		];
	}

	// Keep most frequent
	usort( $log, fn( $a, $b ) => $b['count'] - $a['count'] );
	$log = array_slice( $log, 0, ABB_404_MAX_LOG );

	update_option( ABB_404_LOG_OPTION, $log, false );
}

// Admin dashboard widget
add_action( 'wp_dashboard_setup', 'abb_register_404_dashboard_widget' );
function abb_register_404_dashboard_widget() {
	wp_add_dashboard_widget( 'abb_404_widget', '🔍 404-Fehler Monitor', 'abb_render_404_widget' );
}

function abb_render_404_widget() {
	$log = array_slice( get_option( ABB_404_LOG_OPTION, [] ), 0, 10 );

	if ( empty( $log ) ) {
		echo '<p>Keine 404-Fehler bisher. 🎉</p>';
		return;
	}

	echo '<table style="width:100%;font-size:.85rem;border-collapse:collapse;">';
	echo '<tr><th style="text-align:left;border-bottom:1px solid #eee;padding-bottom:.5rem;">URL</th><th style="text-align:right;">Häufigkeit</th></tr>';
	foreach ( $log as $entry ) {
		printf(
			'<tr style="border-bottom:1px solid #f5f5f5;"><td style="padding:.3rem 0;word-break:break-all;"><code>%s</code>%s</td><td style="text-align:right;font-weight:bold;">%d×</td></tr>',
			esc_html( wp_parse_url( $entry['url'], PHP_URL_PATH ) ),
			$entry['referer'] ? '<br><small style="color:#888;">von: ' . esc_html( $entry['referer'] ) . '</small>' : '',
			$entry['count']
		);
	}
	echo '</table>';

	$manage_url = admin_url( 'options-general.php?page=abb-golive' );
	echo '<p style="margin-top:.75rem;"><a href="' . esc_url( $manage_url ) . '">→ Alle 404-Fehler anzeigen & bereinigen</a></p>';
}
