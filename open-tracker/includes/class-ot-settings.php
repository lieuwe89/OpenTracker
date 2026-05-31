<?php
/**
 * OT_Settings
 *
 * Settings UI for external static/custom site tracking.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_Settings {

	/**
	 * Initialise hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the OpenTracker settings submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			'open-tracker',
			__( 'OpenTracker Settings', 'open-tracker' ),
			__( 'Settings', 'open-tracker' ),
			'manage_options',
			'open-tracker-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings used by the external tracker.
	 */
	public function register_settings() {
		register_setting(
			'ot_settings',
			OT_External_Tracking::OPTION_ALLOWED_ORIGINS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'OT_External_Tracking', 'sanitize_origins' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Render normalized origins as textarea content.
	 *
	 * @return string
	 */
	public static function format_origins_for_textarea() {
		return implode( "\n", OT_External_Tracking::get_allowed_origins() );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OpenTracker Settings', 'open-tracker' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'ot_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ot-external-origins">
								<?php esc_html_e( 'Allowed external sites', 'open-tracker' ); ?>
							</label>
						</th>
						<td>
							<textarea
								id="ot-external-origins"
								name="<?php echo esc_attr( OT_External_Tracking::OPTION_ALLOWED_ORIGINS ); ?>"
								rows="8"
								cols="60"
								class="large-text code"
							><?php echo esc_textarea( self::format_origins_for_textarea() ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Enter one origin per line, for example https://playground.example.com. Only these origins may send external tracking data.', 'open-tracker' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Static site tracker snippet', 'open-tracker' ); ?></h2>
			<p><?php esc_html_e( 'Add this snippet before the closing body tag on each approved static or custom site.', 'open-tracker' ); ?></p>
			<textarea
				id="ot-tracker-snippet"
				rows="3"
				class="large-text code"
				readonly
			><?php echo esc_textarea( OT_External_Tracking::get_tracker_snippet() ); ?></textarea>
			<p>
				<button type="button" class="button button-secondary" id="ot-copy-tracker-snippet">
					<?php esc_html_e( 'Copy snippet', 'open-tracker' ); ?>
				</button>
				<span id="ot-copy-tracker-snippet-status" style="margin-left: 8px;"></span>
			</p>
		</div>

		<script>
		(function () {
			var button = document.getElementById('ot-copy-tracker-snippet');
			var snippet = document.getElementById('ot-tracker-snippet');
			var status = document.getElementById('ot-copy-tracker-snippet-status');

			if (!button || !snippet || !status) {
				return;
			}

			button.addEventListener('click', function () {
				snippet.focus();
				snippet.select();

				var copied = false;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(snippet.value).then(function () {
						status.textContent = '<?php echo esc_js( __( 'Copied.', 'open-tracker' ) ); ?>';
					});
					return;
				}

				try {
					copied = document.execCommand('copy');
				} catch (e) {
					copied = false;
				}

				status.textContent = copied
					? '<?php echo esc_js( __( 'Copied.', 'open-tracker' ) ); ?>'
					: '<?php echo esc_js( __( 'Select and copy the snippet manually.', 'open-tracker' ) ); ?>';
			});
		})();
		</script>
		<?php
	}
}
