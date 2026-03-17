<?php
/**
 * Plugin Name:       Angeln Brandenburg – KI-Content
 * Plugin URI:        https://angeln-brandenburg.de
 * Description:       KI-gestützte SEO-Content-Erstellung für Gewässer-News und Artikel. Alle Inhalte gehen als Draft in WordPress.
 * Version:           1.0.0
 * Author:            Angeln Brandenburg
 * License:           GPL-2.0-or-later
 * Text Domain:       angeln-bb-ai
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ABB_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABB_AI_VER', '1.0.0' );

require_once ABB_AI_DIR . 'includes/class-seo-prompt-builder.php';
require_once ABB_AI_DIR . 'includes/class-content-sandbox.php';
require_once ABB_AI_DIR . 'includes/class-internal-linker.php';
require_once ABB_AI_DIR . 'includes/admin-ai-generator.php';

// ============================================================
// SECURITY: All AI-generated content → Draft status ONLY
// ============================================================

add_filter( 'abb_ai_post_status', fn() => 'draft' );

// ============================================================
// CRON: Daily content generation trigger (optional)
// ============================================================

register_activation_hook( __FILE__, 'abb_ai_activate' );
function abb_ai_activate() {
	// Weekly news draft generation (disabled by default, enable in settings)
	if ( get_option( 'abb_ai_auto_generate', false ) ) {
		if ( ! wp_next_scheduled( 'abb_ai_weekly_cron' ) ) {
			wp_schedule_event( time(), 'weekly', 'abb_ai_weekly_cron' );
		}
	}
}

register_deactivation_hook( __FILE__, 'abb_ai_deactivate' );
function abb_ai_deactivate() {
	wp_clear_scheduled_hook( 'abb_ai_weekly_cron' );
}

add_action( 'abb_ai_weekly_cron', 'abb_ai_generate_weekly_drafts' );
function abb_ai_generate_weekly_drafts() {
	$sandbox = new ABB_Content_Sandbox();
	$sandbox->generate_weekly_fishing_news();
}
