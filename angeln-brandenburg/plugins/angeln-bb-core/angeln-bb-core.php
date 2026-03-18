<?php
/**
 * Plugin Name:       Angeln Brandenburg – Core
 * Plugin URI:        https://angeln-brandenburg.de
 * Description:       Kern-Plugin: Impressum, Datenschutz, Cookie-Consent-Integration, 404-Monitor, Alt-Text-Checker.
 * Version:           1.0.0
 * Author:            Angeln Brandenburg
 * License:           GPL-2.0-or-later
 * Text Domain:       angeln-bb-core
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ABB_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABB_CORE_VER', '1.0.0' );

require_once ABB_CORE_DIR . 'includes/legal-pages.php';
require_once ABB_CORE_DIR . 'includes/404-monitor.php';
require_once ABB_CORE_DIR . 'includes/alt-text-checker.php';
require_once ABB_CORE_DIR . 'includes/admin-golive-panel.php';
require_once ABB_CORE_DIR . 'includes/under-construction.php';

