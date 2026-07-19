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

		$visits_table     = $wpdb->prefix . 'ot_visits';
		$heartbeats_table = $wpdb->prefix . 'ot_heartbeats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					v.page_url, 
					COUNT(v.id) AS visit_count,
					AVG(IFNULL(sub.beat_count, 0)) AS avg_beats,
					SUM(CASE WHEN IFNULL(sub.beat_count, 0) = 0 THEN 1 ELSE 0 END) AS bounce_count
				FROM {$visits_table} v
				LEFT JOIN (
					SELECT visit_id, COUNT(id) AS beat_count
					FROM {$heartbeats_table}
					GROUP BY visit_id
				) AS sub ON sub.visit_id = v.id
				WHERE v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY v.page_url
				ORDER BY visit_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);

		foreach ( $results as $row ) {
			$row->avg_time    = (int) round( floatval( $row->avg_beats ) * 15 );
			$row->bounce_rate = $row->visit_count > 0 ? round( ( $row->bounce_count / $row->visit_count ) * 100, 1 ) : 0;
		}

		return $results;
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- No user-supplied parameters; table name is hardcoded with the WP prefix.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status_code, checked_at
				FROM {$table}
				WHERE checked_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d HOUR)
				ORDER BY checked_at ASC",
				24
			)
		);
	}

	/**
	 * Get total visits within an absolute date range.
	 *
	 * @param string $start Start datetime (UTC, MySQL format).
	 * @param string $end   End datetime (UTC, MySQL format).
	 * @return int
	 */
	public function get_total_visits_range( $start, $end ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);
	}

	/**
	 * Get unique visitors within an absolute date range.
	 *
	 * @param string $start Start datetime (UTC, MySQL format).
	 * @param string $end   End datetime (UTC, MySQL format).
	 * @return int
	 */
	public function get_unique_visitors_range( $start, $end ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_hash) FROM {$table} WHERE created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);
	}

	/**
	 * Get average retention (seconds) within an absolute date range.
	 *
	 * @param string $start Start datetime (UTC, MySQL format).
	 * @param string $end   End datetime (UTC, MySQL format).
	 * @return int
	 */
	public function get_avg_retention_range( $start, $end ) {
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
					WHERE v.created_at >= %s AND v.created_at <= %s
					GROUP BY v.id
				) AS sub",
				$start,
				$end
			)
		);

		return (int) round( floatval( $avg_beats ) * 15 );
	}

	/**
	 * Get uptime percentage within an absolute date range.
	 *
	 * @param string $start Start datetime (UTC, MySQL format).
	 * @param string $end   End datetime (UTC, MySQL format).
	 * @return float Percentage (0–100).
	 */
	public function get_uptime_percentage_range( $start, $end ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_uptime_checks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE checked_at >= %s AND checked_at <= %s",
				$start,
				$end
			)
		);

		if ( 0 === $total ) {
			return 100.0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE checked_at >= %s AND checked_at <= %s AND status_code = 200",
				$start,
				$end
			)
		);

		return round( ( $ok / $total ) * 100, 1 );
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

	/**
	 * Get top channels (referrer types) with visitor counts and percentages.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array of channel objects containing count and pct.
	 */
	public function get_top_channels( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referrer_type, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY referrer_type
				ORDER BY visit_count DESC",
				$days
			)
		);

		$total = 0;
		foreach ( $results as $row ) {
			$total += (int) $row->visit_count;
		}

		$channels = array();
		foreach ( $results as $row ) {
			$type = $row->referrer_type;
			if ( empty( $type ) ) {
				$type = 'direct'; // fallback for historical data
			}

			if ( isset( $channels[ $type ] ) ) {
				$channels[ $type ]['count'] += (int) $row->visit_count;
			} else {
				$channels[ $type ] = array(
					'count' => (int) $row->visit_count,
					'pct'   => 0,
				);
			}
		}

		foreach ( $channels as $type => $data ) {
			$channels[ $type ]['pct'] = $total > 0 ? round( ( $data['count'] / $total ) * 100, 1 ) : 0;
		}

		// Sort by count descending.
		uasort( $channels, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		return $channels;
	}

	/**
	 * Get top countries by visit count.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Max results.
	 * @return array Array of country objects.
	 */
	public function get_top_countries( $days = 30, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT country_code, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY country_code
				ORDER BY visit_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);
	}

	/**
	 * Get top UTM campaigns by visit count.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Max results.
	 * @return array Array of campaign objects.
	 */
	public function get_top_campaigns( $days = 30, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT utm_campaign, utm_source, utm_medium, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				  AND utm_campaign != ''
				GROUP BY utm_campaign, utm_source, utm_medium
				ORDER BY visit_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);
	}

	/**
	 * Get human-readable label for referrer/channel type.
	 *
	 * @param string $type Channel type.
	 * @return string
	 */
	public static function get_channel_label( $type ) {
		switch ( $type ) {
			case 'direct':
				return '✉️ ' . __( 'Direct Traffic', 'open-tracker' );
			case 'search':
				return '🔍 ' . __( 'Organic Search', 'open-tracker' );
			case 'social':
				return '📱 ' . __( 'Social Media', 'open-tracker' );
			case 'referral':
				return '🔗 ' . __( 'Referrals', 'open-tracker' );
			default:
				return '🌐 ' . ucfirst( $type );
		}
	}

	/**
	 * Get CSS color for progress bar representing a channel type.
	 *
	 * @param string $type Channel type.
	 * @return string CSS color hex.
	 */
	public static function get_channel_color( $type ) {
		switch ( $type ) {
			case 'direct':
				return '#64748b'; // Slate
			case 'search':
				return '#10b981'; // Emerald
			case 'social':
				return '#8b5cf6'; // Purple
			case 'referral':
				return '#f59e0b'; // Amber
			default:
				return '#3b82f6'; // Blue
		}
	}

	/**
	 * Get full country name from 2-letter country code.
	 *
	 * @param string $code 2-letter country code.
	 * @return string Country name or code if not found.
	 */
	public static function get_country_name( $code ) {
		$code = strtoupper( trim( $code ) );
		if ( 'LOCAL' === $code ) {
			return __( 'Local / Intranet', 'open-tracker' );
		}
		if ( 'UNKNOWN' === $code || empty( $code ) ) {
			return __( 'Unknown', 'open-tracker' );
		}

		$countries = array(
			'NL' => 'Netherlands',
			'BE' => 'Belgium',
			'DE' => 'Germany',
			'FR' => 'France',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'CA' => 'Canada',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'CH' => 'Switzerland',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'PT' => 'Portugal',
			'IE' => 'Ireland',
			'DK' => 'Denmark',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'FI' => 'Finland',
			'PL' => 'Poland',
			'TR' => 'Turkey',
			'IN' => 'India',
			'CN' => 'China',
			'JP' => 'Japan',
			'BR' => 'Brazil',
			'ZA' => 'South Africa',
			'NZ' => 'New Zealand',
			'SG' => 'Singapore',
			'HK' => 'Hong Kong',
			'MY' => 'Malaysia',
			'ID' => 'Indonesia',
			'TH' => 'Thailand',
			'MX' => 'Mexico',
			'AR' => 'Argentina',
			'CO' => 'Colombia',
			'CL' => 'Chile',
			'PE' => 'Peru',
			'VE' => 'Venezuela',
			'UA' => 'Ukraine',
			'RU' => 'Russia',
			'KR' => 'South Korea',
		);

		return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
	}

	/**
	 * Convert 2-letter country code to flag emoji.
	 *
	 * @param string $code 2-letter country code.
	 * @return string Flag emoji or globe if invalid.
	 */
	public static function get_country_flag( $code ) {
		$code = strtoupper( trim( $code ) );
		if ( 'LOCAL' === $code ) {
			return '🏠';
		}
		if ( 'UNKNOWN' === $code || empty( $code ) || 2 !== strlen( $code ) ) {
			return '🌐';
		}

		$c1 = ord( $code[0] ) + 127397;
		$c2 = ord( $code[1] ) + 127397;

		if ( function_exists( 'mb_chr' ) ) {
			return mb_chr( $c1, 'UTF-8' ) . mb_chr( $c2, 'UTF-8' );
		}

		return '🌐';
	}

	/**
	 * Get the global bounce rate for the entire site.
	 *
	 * @param int $days Number of days to look back.
	 * @return float Bounce rate percentage.
	 */
	public function get_global_bounce_rate( $days = 30 ) {
		global $wpdb;

		$visits_table     = $wpdb->prefix . 'ot_visits';
		$heartbeats_table = $wpdb->prefix . 'ot_heartbeats';

		// Count total visits.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$visits_table} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);

		if ( 0 === $total ) {
			return 0.0;
		}

		// Count visits that have at least 1 heartbeat.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$with_heartbeats = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT v.id)
				FROM {$visits_table} v
				INNER JOIN {$heartbeats_table} h ON h.visit_id = v.id
				WHERE v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);

		$bounces = $total - $with_heartbeats;

		return round( ( $bounces / $total ) * 100, 1 );
	}

	/**
	 * Get session statistics (total sessions, pages per session, and average session duration).
	 *
	 * @param int $days Number of days to look back.
	 * @return array Session stats array.
	 */
	public function get_session_stats( $days = 30 ) {
		global $wpdb;

		$visits_table     = $wpdb->prefix . 'ot_visits';
		$heartbeats_table = $wpdb->prefix . 'ot_heartbeats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					COUNT(v.id) AS total_pageviews,
					COUNT(DISTINCT CASE WHEN v.session_id != '' THEN v.session_id ELSE v.id END) AS total_sessions,
					COUNT(h.id) AS total_heartbeats
				FROM {$visits_table} v
				LEFT JOIN {$heartbeats_table} h ON h.visit_id = v.id
				WHERE v.created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)",
				$days
			)
		);

		$total_sessions   = $row && $row->total_sessions ? (int) $row->total_sessions : 0;
		$total_pageviews  = $row && $row->total_pageviews ? (int) $row->total_pageviews : 0;
		$total_heartbeats = $row && $row->total_heartbeats ? (int) $row->total_heartbeats : 0;

		$pages_per_session    = $total_sessions > 0 ? round( $total_pageviews / $total_sessions, 1 ) : 0.0;
		$avg_session_duration = $total_sessions > 0 ? (int) round( ( $total_heartbeats * 15 ) / $total_sessions ) : 0;

		return array(
			'total_sessions'       => $total_sessions,
			'pages_per_session'    => $pages_per_session,
			'avg_session_duration' => $avg_session_duration,
			'avg_session_dur_fmt'  => self::format_duration( $avg_session_duration ),
		);
	}

	/**
	 * Get new vs returning visitors count and percentage.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Multi-dimensional array with new and returning count/pct.
	 */
	public function get_new_vs_returning( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		$start_date = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					v.visitor_id,
					MIN(v.created_at) AS first_visit
				FROM {$table} v
				WHERE v.visitor_id != ''
				  AND v.visitor_id IN (
					  SELECT DISTINCT visitor_id 
					  FROM {$table} 
					  WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				  )
				GROUP BY v.visitor_id",
				$days
			)
		);

		$new       = 0;
		$returning = 0;

		foreach ( $results as $row ) {
			if ( $row->first_visit >= $start_date ) {
				$new++;
			} else {
				$returning++;
			}
		}

		$total = $new + $returning;

		return array(
			'new'       => array(
				'count' => $new,
				'pct'   => $total > 0 ? round( ( $new / $total ) * 100, 1 ) : 0,
			),
			'returning' => array(
				'count' => $returning,
				'pct'   => $total > 0 ? round( ( $returning / $total ) * 100, 1 ) : 0,
			),
		);
	}

	/**
	 * Get top devices grouped by Mobile, Desktop, Tablet.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Top devices counts/percentages.
	 */
	public function get_top_devices( $days = 30 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_agent, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY user_agent",
				$days
			)
		);

		$devices = array(
			'Desktop' => 0,
			'Mobile'  => 0,
			'Tablet'  => 0,
		);
		$total   = 0;

		foreach ( $results as $row ) {
			$type              = self::get_device_type( $row->user_agent );
			$devices[ $type ] += (int) $row->visit_count;
			$total            += (int) $row->visit_count;
		}

		$formatted = array();
		foreach ( $devices as $type => $count ) {
			$formatted[ $type ] = array(
				'count' => $count,
				'pct'   => $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0,
			);
		}

		// Sort by count descending.
		uasort( $formatted, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		return $formatted;
	}

	/**
	 * Determine device type from User Agent string.
	 *
	 * @param string $user_agent Raw User Agent.
	 * @return string Desktop, Mobile, or Tablet.
	 */
	public static function get_device_type( $user_agent ) {
		$ua = strtolower( $user_agent );

		if ( false !== strpos( $ua, 'ipad' ) || ( false !== strpos( $ua, 'android' ) && false === strpos( $ua, 'mobile' ) ) || false !== strpos( $ua, 'tablet' ) ) {
			return 'Tablet';
		}

		if ( false !== strpos( $ua, 'mobile' ) || false !== strpos( $ua, 'iphone' ) || false !== strpos( $ua, 'ipod' ) || false !== strpos( $ua, 'android' ) || false !== strpos( $ua, 'opera mini' ) || false !== strpos( $ua, 'blackberry' ) ) {
			return 'Mobile';
		}

		return 'Desktop';
	}

	/**
	 * Get top screen resolutions by visit count.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Max results.
	 * @return array Array of screen resolutions.
	 */
	public function get_top_screen_resolutions( $days = 30, $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT screen_resolution, COUNT(*) AS visit_count
				FROM {$table}
				WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				  AND screen_resolution != ''
				GROUP BY screen_resolution
				ORDER BY visit_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);
	}

	/**
	 * Get top PDF downloads by download count.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Max results.
	 * @return array Array of PDF downloads.
	 */
	public function get_top_pdf_downloads( $days = 30, $limit = 10 ) {
		global $wpdb;

		$events_table = $wpdb->prefix . 'ot_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_label AS pdf_url, COUNT(*) AS download_count
				FROM {$events_table}
				WHERE event_category = 'download'
				  AND event_action = 'pdf'
				  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
				GROUP BY event_label
				ORDER BY download_count DESC
				LIMIT %d",
				$days,
				$limit
			)
		);
	}
}
