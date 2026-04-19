<?php
/**
 * OT_Admin
 *
 * Admin dashboard page and menu registration.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Admin {

	/**
	 * Initialise hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the admin menu page.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'OpenTracker', 'open-tracker' ),
			__( 'OpenTracker', 'open-tracker' ),
			'manage_options',
			'open-tracker',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			30
		);
	}

	/**
	 * Enqueue admin CSS and JS on the dashboard page only.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_open-tracker' !== $hook ) {
			return;
		}

		// Chart.js from CDN.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
			array(),
			'4.4.7',
			true
		);

		// Admin JS.
		wp_enqueue_script(
			'ot-admin-js',
			OT_PLUGIN_URL . 'assets/js/ot-admin.js',
			array( 'chartjs' ),
			OT_VERSION,
			true
		);

		// Admin CSS.
		wp_enqueue_style(
			'ot-admin-css',
			OT_PLUGIN_URL . 'assets/css/ot-admin.css',
			array(),
			OT_VERSION
		);

		// Prepare chart data.
		$stats         = new OT_Stats();
		$days          = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$days          = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		$visits_data   = $stats->get_visits_per_day( $days );

		$labels = array();
		$values = array();
		foreach ( $visits_data as $row ) {
			$labels[] = $row->visit_date;
			$values[] = (int) $row->visit_count;
		}

		wp_localize_script(
			'ot-admin-js',
			'otAdminData',
			array(
				'chartLabels' => $labels,
				'chartValues' => $values,
			)
		);
	}

	/**
	 * Render the dashboard page.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$stats = new OT_Stats();
		$days  = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$days  = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;

		// Gather all data for the template.
		$data = array(
			'days'              => $days,
			'total_visits'      => $stats->get_total_visits( $days ),
			'unique_visitors'   => $stats->get_unique_visitors( $days ),
			'avg_retention'     => $stats->get_avg_retention( $days ),
			'avg_retention_fmt' => OT_Stats::format_duration( $stats->get_avg_retention( $days ) ),
			'uptime_pct'        => $stats->get_uptime_percentage( 7 ),
			'top_pages'         => $stats->get_top_pages( $days ),
			'top_referrers'     => $stats->get_top_referrers( $days ),
			'uptime_timeline'   => $stats->get_uptime_timeline(),
			'recent_downtime'   => $stats->get_recent_downtime(),
		);

		include OT_PLUGIN_DIR . 'templates/dashboard.php';
	}
}
