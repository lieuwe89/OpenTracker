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

		<div class="ot-card">
			<div class="ot-card-icon" style="background: #fef2f2; color: #dc2626;">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="ot-card-content">
				<div class="ot-card-value"><?php echo esc_html( $data['bounce_rate'] ); ?>%</div>
				<div class="ot-card-label"><?php esc_html_e( 'Bounce Rate', 'open-tracker' ); ?></div>
			</div>
		</div>

		<div class="ot-card">
			<div class="ot-card-icon" style="background: #f3e8ff; color: #7c3aed;">
				<span class="dashicons dashicons-excerpt-view"></span>
			</div>
			<div class="ot-card-content">
				<div class="ot-card-value"><?php echo esc_html( $data['session_stats']['pages_per_session'] ); ?></div>
				<div class="ot-card-label"><?php esc_html_e( 'Pages / Session', 'open-tracker' ); ?></div>
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
							<th class="ot-num"><?php esc_html_e( 'Views', 'open-tracker' ); ?></th>
							<th class="ot-num"><?php esc_html_e( 'Avg. Time', 'open-tracker' ); ?></th>
							<th class="ot-num"><?php esc_html_e( 'Bounce Rate', 'open-tracker' ); ?></th>
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
								<td class="ot-num"><?php echo esc_html( OT_Stats::format_duration( $page->avg_time ) ); ?></td>
								<td class="ot-num"><?php echo esc_html( $page->bounce_rate ); ?>%</td>
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

	<!-- Two-column layout: Traffic Sources (Channels & Countries) & UTM Campaigns -->
	<div class="ot-grid-2" style="margin-bottom: 20px;">
		<!-- Left column: Channels & Countries -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Traffic Channels Section -->
			<div class="ot-section" style="margin-bottom: 0;">
				<h2 class="ot-section-title">
					<span class="dashicons dashicons-category"></span>
					<?php esc_html_e( 'Traffic Channels', 'open-tracker' ); ?>
				</h2>
				<?php if ( ! empty( $data['top_channels'] ) ) : ?>
					<div class="ot-channels-list">
						<?php foreach ( $data['top_channels'] as $type => $channel ) : ?>
							<div class="ot-channel-row">
								<div class="ot-channel-meta">
									<span class="ot-channel-label"><?php echo esc_html( OT_Stats::get_channel_label( $type ) ); ?></span>
									<span class="ot-channel-value"><?php echo esc_html( $channel['pct'] ); ?>% (<?php echo esc_html( number_format( $channel['count'] ) ); ?>)</span>
								</div>
								<div class="ot-channel-bar-bg">
									<div class="ot-channel-bar-fill" style="width: <?php echo esc_attr( $channel['pct'] ); ?>%; background: <?php echo esc_attr( OT_Stats::get_channel_color( $type ) ); ?>;"></div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="ot-empty"><?php esc_html_e( 'No channel data recorded yet.', 'open-tracker' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Top Countries Section -->
			<div class="ot-section" style="margin-bottom: 0;">
				<h2 class="ot-section-title">
					<span class="dashicons dashicons-admin-site-alt3"></span>
					<?php esc_html_e( 'Top Countries', 'open-tracker' ); ?>
				</h2>
				<?php if ( ! empty( $data['top_countries'] ) ) : ?>
					<table class="ot-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Country', 'open-tracker' ); ?></th>
								<th><?php esc_html_e( 'Visits', 'open-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['top_countries'] as $i => $country ) : ?>
								<tr>
									<td>
										<span class="ot-rank"><?php echo esc_html( $i + 1 ); ?></span>
										<span class="ot-flag" style="font-size: 16px; margin-right: 6px; display: inline-block; vertical-align: middle;">
											<?php echo esc_html( OT_Stats::get_country_flag( $country->country_code ) ); ?>
										</span>
										<span style="vertical-align: middle;">
											<?php echo esc_html( OT_Stats::get_country_name( $country->country_code ) ); ?>
										</span>
									</td>
									<td class="ot-num"><?php echo esc_html( number_format( $country->visit_count ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ot-empty"><?php esc_html_e( 'No country data recorded yet.', 'open-tracker' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right column: UTM Campaigns -->
		<div class="ot-section" style="margin-bottom: 0;">
			<h2 class="ot-section-title">
				<span class="dashicons dashicons-products"></span>
				<?php esc_html_e( 'Top UTM Campaigns', 'open-tracker' ); ?>
			</h2>
			<?php if ( ! empty( $data['top_campaigns'] ) ) : ?>
				<table class="ot-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Campaign', 'open-tracker' ); ?></th>
							<th><?php esc_html_e( 'Source / Medium', 'open-tracker' ); ?></th>
							<th><?php esc_html_e( 'Views', 'open-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['top_campaigns'] as $i => $campaign ) : ?>
							<tr>
								<td>
									<span class="ot-rank"><?php echo esc_html( $i + 1 ); ?></span>
									<span style="font-weight: 500;"><?php echo esc_html( $campaign->utm_campaign ); ?></span>
								</td>
								<td>
									<code style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569;">
										<?php echo esc_html( ($campaign->utm_source ?: '—') . ' / ' . ($campaign->utm_medium ?: '—') ); ?>
									</code>
								</td>
								<td class="ot-num"><?php echo esc_html( number_format( $campaign->visit_count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="ot-empty"><?php esc_html_e( 'No campaign data recorded yet.', 'open-tracker' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Two-column layout: Visitor Retention (New vs. Returning, Screen size) & Technology/Events (Devices, PDFs) -->
	<div class="ot-grid-2" style="margin-bottom: 20px;">
		<!-- Left column: New vs. Returning & Screen Resolutions -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- New vs. Returning Visitors Section -->
			<div class="ot-section" style="margin-bottom: 0;">
				<h2 class="ot-section-title">
					<span class="dashicons dashicons-admin-users"></span>
					<?php esc_html_e( 'New vs. Returning Visitors', 'open-tracker' ); ?>
				</h2>
				<?php if ( ! empty( $data['new_vs_returning'] ) ) : ?>
					<div class="ot-channels-list">
						<!-- New Visitors -->
						<div class="ot-channel-row">
							<div class="ot-channel-meta">
								<span class="ot-channel-label">🆕 <?php esc_html_e( 'New Visitors', 'open-tracker' ); ?></span>
								<span class="ot-channel-value"><?php echo esc_html( $data['new_vs_returning']['new']['pct'] ); ?>% (<?php echo esc_html( number_format( $data['new_vs_returning']['new']['count'] ) ); ?>)</span>
							</div>
							<div class="ot-channel-bar-bg">
								<div class="ot-channel-bar-fill" style="width: <?php echo esc_attr( $data['new_vs_returning']['new']['pct'] ); ?>%; background: #3b82f6;"></div>
							</div>
						</div>
						<!-- Returning Visitors -->
						<div class="ot-channel-row">
							<div class="ot-channel-meta">
								<span class="ot-channel-label">🔄 <?php esc_html_e( 'Returning Visitors', 'open-tracker' ); ?></span>
								<span class="ot-channel-value"><?php echo esc_html( $data['new_vs_returning']['returning']['pct'] ); ?>% (<?php echo esc_html( number_format( $data['new_vs_returning']['returning']['count'] ) ); ?>)</span>
							</div>
							<div class="ot-channel-bar-bg">
								<div class="ot-channel-bar-fill" style="width: <?php echo esc_attr( $data['new_vs_returning']['returning']['pct'] ); ?>%; background: #10b981;"></div>
							</div>
						</div>
					</div>
				<?php else : ?>
					<p class="ot-empty"><?php esc_html_e( 'No visitor retention data recorded yet.', 'open-tracker' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Top Screen Resolutions Section -->
			<div class="ot-section" style="margin-bottom: 0;">
				<h2 class="ot-section-title">
					<span class="dashicons dashicons-desktop"></span>
					<?php esc_html_e( 'Top Screen Sizes', 'open-tracker' ); ?>
				</h2>
				<?php if ( ! empty( $data['top_resolutions'] ) ) : ?>
					<table class="ot-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Screen Size', 'open-tracker' ); ?></th>
								<th><?php esc_html_e( 'Visits', 'open-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['top_resolutions'] as $i => $res ) : ?>
								<tr>
									<td>
										<span class="ot-rank"><?php echo esc_html( $i + 1 ); ?></span>
										<code style="font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; color: #475569;">
											<?php echo esc_html( $res->screen_resolution ); ?>
										</code>
									</td>
									<td class="ot-num"><?php echo esc_html( number_format( $res->visit_count ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ot-empty"><?php esc_html_e( 'No screen size data recorded yet.', 'open-tracker' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right column: Devices & PDF Downloads -->
		<div style="display: flex; flex-direction: column; gap: 20px;">
			<!-- Top Devices Section -->
			<div class="ot-section" style="margin-bottom: 0;">
				<h2 class="ot-section-title">
					<span class="dashicons dashicons-smartphone"></span>
					<?php esc_html_e( 'Visitor Devices', 'open-tracker' ); ?>
				</h2>
				<?php if ( ! empty( $data['top_devices'] ) ) : ?>
					<div class="ot-channels-list">
						<?php foreach ( $data['top_devices'] as $type => $device ) : ?>
							<?php
							$icon = '💻';
							$color = '#3b82f6';
							if ( 'Mobile' === $type ) {
								$icon = '📱';
								$color = '#8b5cf6';
							} elseif ( 'Tablet' === $type ) {
								$icon = '📟';
								$color = '#f59e0b';
							}
							?>
							<div class="ot-channel-row">
								<div class="ot-channel-meta">
									<span class="ot-channel-label"><?php echo esc_html( $icon . ' ' . $type ); ?></span>
									<span class="ot-channel-value"><?php echo esc_html( $device['pct'] ); ?>% (<?php echo esc_html( number_format( $device['count'] ) ); ?>)</span>
								</div>
								<div class="ot-channel-bar-bg">
									<div class="ot-channel-bar-fill" style="width: <?php echo esc_attr( $device['pct'] ); ?>%; background: <?php echo esc_attr( $color ); ?>;"></div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="ot-empty"><?php esc_html_e( 'No device data recorded yet.', 'open-tracker' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- PDF Downloads Section -->
			<div class="ot-section" style="margin-bottom: 0;">
				<h2 class="ot-section-title">
					<span class="dashicons dashicons-pdf"></span>
					<?php esc_html_e( 'Top PDF Downloads', 'open-tracker' ); ?>
				</h2>
				<?php if ( ! empty( $data['top_pdf_downloads'] ) ) : ?>
					<table class="ot-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'PDF File', 'open-tracker' ); ?></th>
								<th><?php esc_html_e( 'Downloads', 'open-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['top_pdf_downloads'] as $i => $pdf ) : ?>
								<tr>
									<td>
										<span class="ot-rank"><?php echo esc_html( $i + 1 ); ?></span>
										<?php
										$filename = basename( wp_parse_url( $pdf->pdf_url, PHP_URL_PATH ) );
										?>
										<a href="<?php echo esc_url( $pdf->pdf_url ); ?>" target="_blank" title="<?php echo esc_attr( $pdf->pdf_url ); ?>" style="text-decoration: none; color: #2563eb;">
											📄 <?php echo esc_html( urldecode( $filename ) ); ?>
										</a>
									</td>
									<td class="ot-num"><?php echo esc_html( number_format( $pdf->download_count ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="ot-empty"><?php esc_html_e( 'No PDF downloads recorded yet.', 'open-tracker' ); ?></p>
				<?php endif; ?>
			</div>
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
