<?php
/**
 * OT_Reports
 *
 * Monthly automated report generation, email delivery, and data cleanup.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Reports {

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'ot_monthly_report';

	/**
	 * Initialise hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'generate_report' ) );
	}

	/**
	 * Schedule the monthly report event.
	 *
	 * Called on plugin activation.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Schedule to run monthly. Use the first day of next month at midnight.
			$next_month = strtotime( 'first day of next month midnight' );
			wp_schedule_event( $next_month, 'monthly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the monthly report event.
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
	 * Add the monthly cron schedule (WordPress doesn't have one by default).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) {
		$schedules['monthly'] = array(
			'interval' => MONTH_IN_SECONDS,
			'display'  => __( 'Once a Month', 'open-tracker' ),
		);
		return $schedules;
	}

	/**
	 * Generate the monthly report, email it, then clean up old data.
	 */
	public function generate_report() {
		global $wpdb;

		$stats = new OT_Stats();

		// Report for the previous month.
		$report_month  = gmdate( 'Y-m', strtotime( 'first day of last month' ) );
		$month_start   = $report_month . '-01 00:00:00';
		$month_end     = gmdate( 'Y-m-t 23:59:59', strtotime( $month_start ) );

		// --- Summary stats for the previous calendar month ---
		$total_visits      = $stats->get_total_visits_range( $month_start, $month_end );
		$unique_visitors   = $stats->get_unique_visitors_range( $month_start, $month_end );
		$avg_retention     = $stats->get_avg_retention_range( $month_start, $month_end );
		$avg_retention_fmt = OT_Stats::format_duration( $avg_retention );
		$uptime_pct        = $stats->get_uptime_percentage_range( $month_start, $month_end );

		// --- Generate CSV ---
		$csv_path = $this->generate_csv( $month_start, $month_end );

		// --- Build email ---
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: site name, 2: report month */
			__( '[%1$s] OpenTracker Monthly Report — %2$s', 'open-tracker' ),
			$site_name,
			$report_month
		);

		$body = $this->build_email_body( array(
			'report_month'      => $report_month,
			'total_visits'      => $total_visits,
			'unique_visitors'   => $unique_visitors,
			'avg_retention_fmt' => $avg_retention_fmt,
			'uptime_pct'        => $uptime_pct,
			'site_name'         => $site_name,
		) );

		$headers     = array( 'Content-Type: text/html; charset=UTF-8' );
		$attachments = $csv_path ? array( $csv_path ) : array();

		$sent = wp_mail( $admin_email, $subject, $body, $headers, $attachments );

		// Always clean up old data — retention policy must hold even if mail
		// delivery fails (e.g., misconfigured SMTP), or the tables grow forever.
		$this->cleanup_old_data();

		if ( $sent ) {
			// Record in reports table.
			$wpdb->insert(
				$wpdb->prefix . 'ot_monthly_reports',
				array(
					'report_month'      => $report_month,
					'total_visits'      => $total_visits,
					'avg_retention_sec' => $avg_retention,
					'sent_at'           => current_time( 'mysql', true ),
				),
				array( '%s', '%d', '%d', '%s' )
			);
		}

		// Remove the temporary CSV file.
		if ( $csv_path && file_exists( $csv_path ) ) {
			wp_delete_file( $csv_path );
		}
	}

	/**
	 * Generate a CSV file with raw visit data for the given period.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return string|false File path or false on failure.
	 */
	private function generate_csv( $start, $end ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, referrer, ip_hash, user_agent, created_at
				FROM {$table}
				WHERE created_at >= %s AND created_at <= %s
				ORDER BY created_at ASC",
				$start,
				$end
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return false;
		}

		// Write the CSV to the system temp directory (typically outside the
		// webroot). Use a random filename so the URL cannot be guessed even
		// if the location is ever exposed.
		$dir = trailingslashit( get_temp_dir() );

		$filename = sprintf(
			'ot-report-%s-%s.csv',
			gmdate( 'Y-m', strtotime( $start ) ),
			wp_generate_password( 16, false )
		);
		$filepath = $dir . $filename;

		$fp = fopen( $filepath, 'w' );
		if ( ! $fp ) {
			return false;
		}

		// Header row.
		fputcsv( $fp, array( 'Page URL', 'Referrer', 'IP Hash', 'User Agent', 'Date' ) );

		foreach ( $rows as $row ) {
			fputcsv( $fp, array_values( $row ) );
		}

		fclose( $fp );

		return $filepath;
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array $data Report data.
	 * @return string
	 */
	private function build_email_body( $data ) {
		ob_start();
		?>
		<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 0 auto;">
			<h1 style="color: #1e293b; border-bottom: 2px solid #3b82f6; padding-bottom: 12px;">
				📊 OpenTracker Monthly Report
			</h1>
			<p style="color: #64748b;">
				Report for <strong><?php echo esc_html( $data['report_month'] ); ?></strong>
				on <strong><?php echo esc_html( $data['site_name'] ); ?></strong>
			</p>

			<table style="width: 100%; border-collapse: collapse; margin: 24px 0;">
				<tr>
					<td style="padding: 16px; background: #f1f5f9; border-radius: 8px; text-align: center; width: 25%;">
						<div style="font-size: 24px; font-weight: 700; color: #1e293b;">
							<?php echo esc_html( number_format( $data['total_visits'] ) ); ?>
						</div>
						<div style="font-size: 12px; color: #64748b; margin-top: 4px;">Total Visits</div>
					</td>
					<td style="padding: 16px; background: #f1f5f9; border-radius: 8px; text-align: center; width: 25%;">
						<div style="font-size: 24px; font-weight: 700; color: #1e293b;">
							<?php echo esc_html( number_format( $data['unique_visitors'] ) ); ?>
						</div>
						<div style="font-size: 12px; color: #64748b; margin-top: 4px;">Unique Visitors</div>
					</td>
					<td style="padding: 16px; background: #f1f5f9; border-radius: 8px; text-align: center; width: 25%;">
						<div style="font-size: 24px; font-weight: 700; color: #1e293b;">
							<?php echo esc_html( $data['avg_retention_fmt'] ); ?>
						</div>
						<div style="font-size: 12px; color: #64748b; margin-top: 4px;">Avg. Retention</div>
					</td>
					<td style="padding: 16px; background: #f1f5f9; border-radius: 8px; text-align: center; width: 25%;">
						<div style="font-size: 24px; font-weight: 700; color: #1e293b;">
							<?php echo esc_html( $data['uptime_pct'] ); ?>%
						</div>
						<div style="font-size: 12px; color: #64748b; margin-top: 4px;">Uptime</div>
					</td>
				</tr>
			</table>

			<p style="color: #64748b; font-size: 13px;">
				A CSV file with the raw visit data is attached to this email.
			</p>
			<hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
			<p style="color: #94a3b8; font-size: 11px;">
				This report was generated automatically by the OpenTracker plugin.
				Raw analytics data older than 30 days has been cleaned up.
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Delete raw analytics data older than 30 days.
	 */
	private function cleanup_old_data() {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		// Delete heartbeats for old visits.
		$visits_table     = $wpdb->prefix . 'ot_visits';
		$heartbeats_table = $wpdb->prefix . 'ot_heartbeats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE h FROM {$heartbeats_table} h
				INNER JOIN {$visits_table} v ON h.visit_id = v.id
				WHERE v.created_at < %s",
				$cutoff
			)
		);

		// Delete old visits.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$visits_table} WHERE created_at < %s",
				$cutoff
			)
		);
	}
}
