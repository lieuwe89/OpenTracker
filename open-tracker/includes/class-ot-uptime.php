<?php
/**
 * OT_Uptime
 *
 * Uptime monitor using WP-Cron.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Uptime {

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'ot_uptime_check';

	/**
	 * Initialise hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_check' ) );
	}

	/**
	 * Register a custom 5-minute cron schedule.
	 *
	 * Registered at top level of open-tracker.php so the schedule exists
	 * during the activation hook.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every Five Minutes', 'open-tracker' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the uptime check event.
	 *
	 * Called on plugin activation.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'every_five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the uptime check event.
	 *
	 * Called on plugin deactivation.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Run the uptime check.
	 *
	 * Sends a GET request to the homepage and logs the result.
	 */
	public function run_check() {
		global $wpdb;

		$start_time = microtime( true );

		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 30,
				'sslverify' => false,
			)
		);

		$elapsed_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		$status_code   = 0;
		$error_message = '';

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );
		}

		$table = $wpdb->prefix . 'ot_uptime_checks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'status_code'     => $status_code,
				'response_time_ms' => $elapsed_ms,
				'error_message'   => mb_substr( sanitize_text_field( $error_message ), 0, 512 ),
				'checked_at'      => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}
}
