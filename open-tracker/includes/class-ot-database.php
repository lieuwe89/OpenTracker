<?php
/**
 * OT_Database
 *
 * Handles custom table creation, schema upgrades, and IP anonymisation.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Database {

	/**
	 * Current database schema version.
	 *
	 * Bump this when changing table definitions so dbDelta() re-runs.
	 */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Create or update all custom tables.
	 *
	 * Called on plugin activation via register_activation_hook().
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// --- ot_visits ---
		$table_visits = $wpdb->prefix . 'ot_visits';
		$sql_visits   = "CREATE TABLE {$table_visits} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			page_url VARCHAR(2048) NOT NULL DEFAULT '',
			referrer VARCHAR(2048) NOT NULL DEFAULT '',
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(512) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_created_at (created_at),
			KEY idx_page_url (page_url(191)),
			KEY idx_ip_hash (ip_hash)
		) {$charset_collate};";

		// --- ot_heartbeats ---
		$table_heartbeats = $wpdb->prefix . 'ot_heartbeats';
		$sql_heartbeats   = "CREATE TABLE {$table_heartbeats} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_visit_id (visit_id)
		) {$charset_collate};";

		// --- ot_uptime_checks ---
		$table_uptime = $wpdb->prefix . 'ot_uptime_checks';
		$sql_uptime   = "CREATE TABLE {$table_uptime} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			status_code SMALLINT(3) NOT NULL DEFAULT 0,
			response_time_ms INT(10) UNSIGNED NOT NULL DEFAULT 0,
			error_message VARCHAR(512) NOT NULL DEFAULT '',
			checked_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_checked_at (checked_at)
		) {$charset_collate};";

		// --- ot_monthly_reports ---
		$table_reports = $wpdb->prefix . 'ot_monthly_reports';
		$sql_reports   = "CREATE TABLE {$table_reports} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			report_month VARCHAR(7) NOT NULL DEFAULT '',
			total_visits BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			avg_retention_sec INT(10) UNSIGNED NOT NULL DEFAULT 0,
			sent_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY idx_report_month (report_month)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_visits );
		dbDelta( $sql_heartbeats );
		dbDelta( $sql_uptime );
		dbDelta( $sql_reports );

		update_option( 'ot_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Anonymise an IP address.
	 *
	 * Zeroes the last octet for IPv4 and the last 80 bits for IPv6,
	 * then returns a SHA-256 hash of the anonymised address.
	 *
	 * @param string $ip Raw IP address.
	 * @return string SHA-256 hash of the anonymised IP.
	 */
	public static function anonymise_ip( $ip ) {
		$ip = trim( $ip );

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			// Zero the last octet: 192.168.1.123 → 192.168.1.0
			$parts    = explode( '.', $ip );
			$parts[3] = '0';
			$ip       = implode( '.', $parts );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			// Expand to full notation, then zero the last 80 bits (last 5 groups).
			$expanded = inet_ntop( inet_pton( $ip ) );
			$groups   = explode( ':', $expanded );
			for ( $i = 3; $i < 8; $i++ ) {
				$groups[ $i ] = '0000';
			}
			$ip = implode( ':', $groups );
		}

		return hash( 'sha256', $ip );
	}

	/**
	 * Drop all custom tables.
	 *
	 * Only called if the user decides to fully uninstall.
	 */
	public static function uninstall() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'ot_visits',
			$wpdb->prefix . 'ot_heartbeats',
			$wpdb->prefix . 'ot_uptime_checks',
			$wpdb->prefix . 'ot_monthly_reports',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'ot_schema_version' );
	}
}
