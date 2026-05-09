<?php
/**
 * OT_Tracker
 *
 * Handles front-end script enqueue for visitor tracking.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Tracker {

	/**
	 * Initialise hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );
	}

	/**
	 * Enqueue the lightweight tracking script on the front-end.
	 */
	public function enqueue_tracking_script() {
		// Don't track any logged-in user (admin, editor, author, etc.) so
		// internal staff traffic doesn't pollute analytics. Sites that want
		// the previous behaviour can short-circuit this filter.
		if ( apply_filters( 'ot_skip_tracking_for_user', is_user_logged_in() ) ) {
			return;
		}

		wp_enqueue_script(
			'ot-tracker',
			OT_PLUGIN_URL . 'assets/js/ot-tracker.js',
			array(),
			OT_VERSION,
			array(
				'strategy' => 'async',
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'ot-tracker',
			'otTrackerData',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'open-tracker/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'pageUrl'  => esc_url_raw( $this->get_current_url() ),
				'referrer' => isset( $_SERVER['HTTP_REFERER'] )
					? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
					: '',
			)
		);
	}

	/**
	 * Build the current page URL from server vars.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: '';
		$uri    = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		return $scheme . '://' . $host . $uri;
	}
}
