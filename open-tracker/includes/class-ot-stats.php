<?php
/**
 * OT_Stats
 *
 * Query helper class for aggregating analytics data.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Stats {

	/**
	 * Get total number of visits within a date range.
	 *
	 * @param int $days Number of days to look back.
	 * @return int
	 */
	public function get_total_visits( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Get number of unique visitors (by IP hash).
	 *
	 * @param int $days Number of days to look back.
	 * @return int
	 */
	public function get_unique_visitors( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_hash) FROM {$table} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Get top pages by visit count.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Max results.
	 * @return array Array of objects with page_url and visit_count.
	 */
	public function get_top_pages( $days = 30, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY page_url
				ORDER BY visit_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);
	}

	/**
	 * Get top referrers by count.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Max results.
	 * @return array
	 */
	public function get_top_referrers( $days = 30, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referrer, COUNT(*) AS ref_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				  AND referrer != ''
				GROUP BY referrer
				ORDER BY ref_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);
	}

	/**
	 * Get average retention in seconds.
	 *
	 * Calculated as average heartbeat count per visit × 15 seconds.
	 *
	 * @param int $days Number of days to look back.
	 * @return int Average retention in seconds.
	 */
	public function get_avg_retention( $days = 30 ) {
		global $wpdb;

		$visits_table     = $wpdb->prefix . 'ot_visits';
		$heartbeats_table = $wpdb->prefix . 'ot_heartbeats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$avg_beats = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(beat_count) FROM (
					SELECT v.id, COUNT(h.id) AS beat_count
					FROM {$visits_table} v
					LEFT JOIN {$heartbeats_table} h ON h.visit_id = v.id
					WHERE v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
					GROUP BY v.id
				) AS sub",
				$days
			)
		);

		// Each heartbeat = 15 seconds of engagement.
		return (int) round( floatval( $avg_beats ) * 15 );
	}

	/**
	 * Get daily visit counts for charting.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array of objects with visit_date and visit_count.
	 */
	public function get_visits_per_day( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS visit_date, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY visit_date
				ORDER BY visit_date ASC",
				$days
			)
		);
	}

	/**
	 * Get uptime percentage.
	 *
	 * @param int $days Number of days to look back.
	 * @return float Percentage (0–100).
	 */
	public function get_uptime_percentage( $days = 7 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_uptime_checks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE checked_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);

		if ( 0 === $total ) {
			return 100.0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE checked_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) AND status_code = 200",
				$days
			)
		);

		return round( ( $ok / $total ) * 100, 1 );
	}

	/**
	 * Get recent downtime events.
	 *
	 * @param int $limit Max results.
	 * @return array
	 */
	public function get_recent_downtime( $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_uptime_checks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status_code, response_time_ms, error_message, checked_at
				FROM {$table}
				WHERE status_code != 200
				ORDER BY checked_at DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get recent uptime check statuses for the last 24 h.
	 *
	 * @return array Array of objects with status_code and checked_at.
	 */
	public function get_uptime_timeline() {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_uptime_checks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			"SELECT status_code, checked_at
			FROM {$table}
			WHERE checked_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
			ORDER BY checked_at ASC"
		);
	}

	/**
	 * Format seconds into a human-readable string.
	 *
	 * @param int $seconds Total seconds.
	 * @return string e.g. "2m 34s".
	 */
	public static function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );

		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		$minutes = floor( $seconds / 60 );
		$secs    = $seconds % 60;

		return $minutes . 'm ' . $secs . 's';
	}
}
