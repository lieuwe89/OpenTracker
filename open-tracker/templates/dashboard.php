<?php
/**
 * Admin Dashboard Template
 *
 * @package OpenTracker
 *
 * @var array $data Template data passed from OT_Admin::render_dashboard().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_url = admin_url( 'admin.php?page=open-tracker' );
?>
<div class="wrap ot-dashboard">
	<div class="ot-header">
		<h1 class="ot-title">
			<span class="dashicons dashicons-chart-area"></span>
			<?php esc_html_e( 'OpenTracker Dashboard', 'open-tracker' ); ?>
		</h1>
		<div class="ot-date-filter">
			<a href="<?php echo esc_url( add_query_arg( 'days', 7, $current_url ) ); ?>"
			   class="ot-filter-btn <?php echo 7 === $data['days'] ? 'active' : ''; ?>">
				7d
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'days', 30, $current_url ) ); ?>"
			   class="ot-filter-btn <?php echo 30 === $data['days'] ? 'active' : ''; ?>">
				30d
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'days', 90, $current_url ) ); ?>"
			   class="ot-filter-btn <?php echo 90 === $data['days'] ? 'active' : ''; ?>">
				90d
			</a>
		</div>
	</div>

	<!-- Stat Cards -->
	<div class="ot-stat-cards">
		<div class="ot-card">
			<div class="ot-card-icon" style="background: #dbeafe; color: #2563eb;">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<div class="ot-card-content">
				<div class="ot-card-value"><?php echo esc_html( number_format( $data['total_visits'] ) ); ?></div>
				<div class="ot-card-label"><?php esc_html_e( 'Total Visits', 'open-tracker' ); ?></div>
			</div>
		</div>

		<div class="ot-card">
			<div class="ot-card-icon" style="background: #dcfce7; color: #16a34a;">
				<span class="dashicons dashicons-groups"></span>
			</div>
			<div class="ot-card-content">
				<div class="ot-card-value"><?php echo esc_html( number_format( $data['unique_visitors'] ) ); ?></div>
				<div class="ot-card-label"><?php esc_html_e( 'Unique Visitors', 'open-tracker' ); ?></div>
			</div>
		</div>

		<div class="ot-card">
			<div class="ot-card-icon" style="background: #fef3c7; color: #d97706;">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="ot-card-content">
				<div class="ot-card-value"><?php echo esc_html( $data['avg_retention_fmt'] ); ?></div>
				<div class="ot-card-label"><?php esc_html_e( 'Avg. Time on Page', 'open-tracker' ); ?></div>
			</div>
		</div>

		<div class="ot-card">
			<div class="ot-card-icon" style="background: <?php echo $data['uptime_pct'] >= 99 ? '#dcfce7' : '#fef2f2'; ?>; color: <?php echo $data['uptime_pct'] >= 99 ? '#16a34a' : '#dc2626'; ?>;">
				<span class="dashicons dashicons-<?php echo $data['uptime_pct'] >= 99 ? 'yes-alt' : 'warning'; ?>"></span>
			</div>
			<div class="ot-card-content">
				<div class="ot-card-value"><?php echo esc_html( $data['uptime_pct'] ); ?>%</div>
				<div class="ot-card-label"><?php esc_html_e( 'Uptime (7d)', 'open-tracker' ); ?></div>
			</div>
		</div>
	</div>

	<!-- Chart -->
	<div class="ot-section">
		<h2 class="ot-section-title">
			<span class="dashicons dashicons-chart-line"></span>
			<?php esc_html_e( 'Visits Over Time', 'open-tracker' ); ?>
		</h2>
		<div class="ot-chart-container">
			<canvas id="ot-visits-chart"></canvas>
		</div>
	</div>

	<!-- Two-column layout: Top Pages & Top Referrers -->
	<div class="ot-grid-2">
		<div class="ot-section">
			<h2 class="ot-section-title">
				<span class="dashicons dashicons-admin-page"></span>
				<?php esc_html_e( 'Top Pages', 'open-tracker' ); ?>
			</h2>
			<?php if ( ! empty( $data['top_pages'] ) ) : ?>
				<table class="ot-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Page', 'open-tracker' ); ?></th>
							<th><?php esc_html_e( 'Views', 'open-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['top_pages'] as $i => $page ) : ?>
							<tr>
								<td>
									<span class="ot-rank"><?php echo esc_html( $i + 1 ); ?></span>
									<?php
									$parsed = wp_parse_url( $page->page_url );
									if ( isset( $parsed['host'] ) ) {
										$path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
										echo esc_html( $parsed['host'] . $path );
									} else {
										echo esc_html( isset( $parsed['path'] ) ? $parsed['path'] : $page->page_url );
									}
									?>
								</td>
								<td class="ot-num"><?php echo esc_html( number_format( $page->visit_count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="ot-empty"><?php esc_html_e( 'No page views recorded yet.', 'open-tracker' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ot-section">
			<h2 class="ot-section-title">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Top Referrers', 'open-tracker' ); ?>
			</h2>
			<?php if ( ! empty( $data['top_referrers'] ) ) : ?>
				<table class="ot-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Referrer', 'open-tracker' ); ?></th>
							<th><?php esc_html_e( 'Visits', 'open-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['top_referrers'] as $i => $ref ) : ?>
							<tr>
								<td>
									<span class="ot-rank"><?php echo esc_html( $i + 1 ); ?></span>
									<?php
									$parsed = wp_parse_url( $ref->referrer );
									echo esc_html( isset( $parsed['host'] ) ? $parsed['host'] : $ref->referrer );
									?>
								</td>
								<td class="ot-num"><?php echo esc_html( number_format( $ref->ref_count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="ot-empty"><?php esc_html_e( 'No referrer data yet.', 'open-tracker' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Uptime Section -->
	<div class="ot-section">
		<h2 class="ot-section-title">
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'Uptime Status (Last 24 Hours)', 'open-tracker' ); ?>
		</h2>

		<?php if ( ! empty( $data['uptime_timeline'] ) ) : ?>
			<div class="ot-uptime-timeline">
				<?php foreach ( $data['uptime_timeline'] as $check ) : ?>
					<span class="ot-uptime-dot <?php echo 200 === (int) $check->status_code ? 'up' : 'down'; ?>"
					      title="<?php echo esc_attr( $check->checked_at . ' — HTTP ' . $check->status_code ); ?>">
					</span>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="ot-empty"><?php esc_html_e( 'No uptime checks recorded yet.', 'open-tracker' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $data['recent_downtime'] ) ) : ?>
			<h3 style="margin-top: 24px;">
				<?php esc_html_e( 'Recent Downtime Events', 'open-tracker' ); ?>
			</h3>
			<table class="ot-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'open-tracker' ); ?></th>
						<th><?php esc_html_e( 'Status', 'open-tracker' ); ?></th>
						<th><?php esc_html_e( 'Response Time', 'open-tracker' ); ?></th>
						<th><?php esc_html_e( 'Error', 'open-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $data['recent_downtime'] as $event ) : ?>
						<tr>
							<td><?php echo esc_html( $event->checked_at ); ?></td>
							<td>
								<span class="ot-status-badge down">
									<?php echo esc_html( $event->status_code ?: 'Error' ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $event->response_time_ms ); ?>ms</td>
							<td><?php echo esc_html( $event->error_message ?: '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
