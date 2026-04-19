<?php
/**
 * OT_Plugin
 *
 * Main controller — wires all plugin components together.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Plugin {

	/**
	 * Boot all components.
	 */
	public function __construct() {
		// Front-end tracking (public pages only).
		if ( ! is_admin() ) {
			new OT_Tracker();
		}

		// REST API (always available — both front-end and admin use it).
		new OT_REST_API();

		// Admin dashboard.
		if ( is_admin() ) {
			new OT_Admin();
		}

		// Cron-based components (always need hooks registered).
		new OT_Uptime();
		new OT_Reports();
	}
}
