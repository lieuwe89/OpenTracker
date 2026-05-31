<?php
/**
 * OT_REST_API
 *
 * Registers and handles REST routes for visitor tracking.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_REST_API {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'open-tracker/v1';

	/**
	 * Initialise hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'send_cors_headers' ), 10, 4 );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_hit' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'page_url' => array(
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'referrer' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/heartbeat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_heartbeat' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'visit_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback for tracking endpoints.
	 *
	 * Validates the REST nonce sent by the front-end tracker script and
	 * applies a per-IP rate limit to prevent flooding.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_permission( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $this->check_rate_limit();
		}

		if ( ! $this->is_hit_request( $request ) && ! $this->is_heartbeat_request( $request ) ) {
			return new WP_Error(
				'ot_invalid_nonce',
				__( 'Invalid or missing nonce.', 'open-tracker' ),
				array( 'status' => 403 )
			);
		}

		$external_permission = $this->check_external_permission( $request );
		if ( $external_permission instanceof WP_Error ) {
			return $external_permission;
		}

		return $this->check_rate_limit();
	}

	/**
	 * Apply rate limiting for accepted tracking requests.
	 *
	 * @return bool|WP_Error
	 */
	private function check_rate_limit() {
		// Rate limit: max 60 requests per minute per anonymised IP.
		$ip_hash   = OT_Database::anonymise_ip( $this->get_client_ip() );
		$key       = 'ot_rl_' . $ip_hash;
		$count     = (int) get_transient( $key );
		if ( $count >= 60 ) {
			return new WP_Error(
				'ot_rate_limited',
				__( 'Rate limit exceeded.', 'open-tracker' ),
				array( 'status' => 429 )
			);
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Check whether a request is allowed through external static-site tracking.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	private function check_external_permission( $request ) {
		$origin = OT_External_Tracking::get_request_origin();
		if ( ! OT_External_Tracking::is_allowed_origin( $origin ) ) {
			return new WP_Error(
				'ot_invalid_origin',
				__( 'External tracking origin is not allowed.', 'open-tracker' ),
				array( 'status' => 403 )
			);
		}

		if ( $this->is_options_request( $request ) ) {
			return true;
		}

		if ( $this->is_hit_request( $request ) ) {
			$page_url = (string) $request->get_param( 'page_url' );
			if ( ! OT_External_Tracking::is_allowed_page_url( $page_url, $origin ) ) {
				return new WP_Error(
					'ot_invalid_page_url',
					__( 'Tracked page URL is not allowed for this origin.', 'open-tracker' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Check whether the request targets the page hit endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool
	 */
	private function is_hit_request( $request ) {
		return method_exists( $request, 'get_route' )
			&& '/' . self::NAMESPACE . '/hit' === $request->get_route();
	}

	/**
	 * Check whether the request targets the heartbeat endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool
	 */
	private function is_heartbeat_request( $request ) {
		return method_exists( $request, 'get_route' )
			&& '/' . self::NAMESPACE . '/heartbeat' === $request->get_route();
	}

	/**
	 * Check whether the request is a CORS preflight request.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool
	 */
	private function is_options_request( $request ) {
		return method_exists( $request, 'get_method' )
			&& 'OPTIONS' === strtoupper( $request->get_method() );
	}

	/**
	 * Add CORS headers for approved external tracker origins.
	 *
	 * @param bool            $served  Whether the request has already been served.
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Request $request The request object.
	 * @param WP_REST_Server  $server  Server instance.
	 * @return bool
	 */
	public function send_cors_headers( $served, $result, $request, $server ) {
		if ( ! method_exists( $request, 'get_route' ) || 0 !== strpos( $request->get_route(), '/' . self::NAMESPACE . '/' ) ) {
			return $served;
		}

		$origin = OT_External_Tracking::get_request_origin();
		if ( ! OT_External_Tracking::is_allowed_origin( $origin ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin, true );
		header( 'Access-Control-Allow-Methods: POST, OPTIONS', true );
		header( 'Access-Control-Allow-Headers: Content-Type, X-WP-Nonce', true );
		header( 'Vary: Origin', false );

		return $served;
	}

	/**
	 * Handle a page hit.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_hit( $request ) {
		global $wpdb;

		$page_url   = $request->get_param( 'page_url' );
		$referrer   = $request->get_param( 'referrer' );
		$ip_hash    = OT_Database::anonymise_ip( $this->get_client_ip() );
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		// Truncate user agent to 512 chars.
		$user_agent = mb_substr( $user_agent, 0, 512 );

		$table = $wpdb->prefix . 'ot_visits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'page_url'   => mb_substr( $page_url, 0, 2048 ),
				'referrer'   => mb_substr( $referrer, 0, 2048 ),
				'ip_hash'    => $ip_hash,
				'user_agent' => $user_agent,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		$visit_id = $wpdb->insert_id;

		if ( ! $visit_id ) {
			return new WP_REST_Response(
				array( 'error' => 'Failed to record visit.' ),
				500
			);
		}

		return new WP_REST_Response(
			array( 'visit_id' => $visit_id ),
			201
		);
	}

	/**
	 * Handle a heartbeat ping.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_heartbeat( $request ) {
		global $wpdb;

		$visit_id = $request->get_param( 'visit_id' );

		// Verify the visit exists AND was created recently. The recency
		// window prevents enumeration attacks against historical visit IDs.
		$table_visits = $wpdb->prefix . 'ot_visits';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_visits}
				WHERE id = %d
				  AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR)",
				$visit_id
			)
		);

		if ( ! $exists ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid or expired visit ID.' ),
				404
			);
		}

		$table_heartbeats = $wpdb->prefix . 'ot_heartbeats';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table_heartbeats,
			array(
				'visit_id'   => $visit_id,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s' )
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Get the client IP address, accounting for proxies.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// X-Forwarded-For can contain multiple IPs; take the first.
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip = trim( $ip[0] );

				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
