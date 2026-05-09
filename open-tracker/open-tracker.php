<?php
/**
 * Plugin Name: OpenTracker
 * Plugin URI:  https://github.com/lieuwejongsma/OpenTracker
 * Description: Local analytics & uptime monitor. Tracks visits, page views, user retention, and website uptime — all data stored locally.
 * Version:     1.0.1
 * Author:      Lieuwe Jongsma
 * License:     GPL-2.0-or-later
 * Text Domain: open-tracker
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Constants ---
define( 'OT_VERSION', '1.0.1' );
define( 'OT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// --- Load classes ---
require_once OT_PLUGIN_DIR . 'includes/class-ot-database.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-rest-api.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-tracker.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-stats.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-admin.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-uptime.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-reports.php';
require_once OT_PLUGIN_DIR . 'includes/class-ot-plugin.php';

// --- Activation ---
register_activation_hook( __FILE__, function () {
	OT_Database::install();
	OT_Uptime::schedule();
	OT_Reports::schedule();
} );

// --- Deactivation ---
register_deactivation_hook( __FILE__, function () {
	OT_Uptime::unschedule();
	OT_Reports::unschedule();
} );

// --- Register custom cron schedules at top level so they exist during activation ---
add_filter( 'cron_schedules', array( 'OT_Reports', 'add_cron_schedule' ) );
add_filter( 'cron_schedules', array( 'OT_Uptime', 'add_cron_schedule' ) );

// --- Boot ---
add_action( 'plugins_loaded', function () {
	new OT_Plugin();
} );

// --- GitHub auto-updates via plugin-update-checker ---
require_once OT_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

$ot_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/lieuwe89/OpenTracker/',
	__FILE__,
	'open-tracker'
);
$ot_update_checker->setBranch( 'main' );
$ot_update_checker->getVcsApi()->enableReleaseAssets();
