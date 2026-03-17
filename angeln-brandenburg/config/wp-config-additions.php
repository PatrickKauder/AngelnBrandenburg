<?php
/**
 * wp-config.php Additions – Angeln Brandenburg 2026
 * Add these constants to your wp-config.php (before "/* That's all, stop editing! */")
 *
 * PHASE 1: Security hardening constants
 */

// --- Disable file editing from WP Admin ---
define( 'DISALLOW_FILE_EDIT', true );

// --- Disable automatic theme/plugin file modification ---
define( 'DISALLOW_FILE_MODS', false ); // Set true after initial setup

// --- Force SSL for admin ---
define( 'FORCE_SSL_ADMIN', true );

// --- Disable WP Cron (use server cron instead for performance) ---
// define( 'DISABLE_WP_CRON', true );
// Add to crontab: */5 * * * * wget -q -O - https://angeln-brandenburg.de/wp-cron.php?doing_wp_cron >/dev/null 2>&1

// --- Limit post revisions (saves DB space) ---
define( 'WP_POST_REVISIONS', 5 );

// --- Trash auto-empty after 14 days ---
define( 'EMPTY_TRASH_DAYS', 14 );

// --- Memory limit ---
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );

// --- Debug (disable in production) ---
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );

// --- Custom table prefix (change in your actual wp-config.php!) ---
// $table_prefix = 'abb_';  // Example: 'abb_' instead of default 'wp_'

// --- Cookie path (if in subdirectory, adjust accordingly) ---
define( 'COOKIE_DOMAIN', 'angeln-brandenburg.de' );

// --- Authentication keys – GENERATE NEW ONES at https://api.wordpress.org/secret-key/1.1/salt/ ---
// (Replace with generated values in actual wp-config.php)

// --- Pegelonline API Key (store here, not in plugin files) ---
define( 'ABB_PEGELONLINE_API', 'https://pegelonline.wsv.de/webservices/rest-api/v2' );

// --- OpenWeatherMap API Key (for Beißindex weather data) ---
// define( 'ABB_OPENWEATHER_KEY', 'YOUR_API_KEY_HERE' );
