<?php
/**
 * OT_External_Tracking
 *
 * Helpers for tracking static/custom sites from approved external origins.
 *
 * @package OpenTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OT_External_Tracking {

	/**
	 * Option name for approved external origins.
	 */
	const OPTION_ALLOWED_ORIGINS = 'ot_external_origins';

	/**
	 * Normalize a URL/origin to scheme://host[:port].
	 *
	 * @param string $origin Origin or host entered by an admin.
	 * @return string
	 */
	public static function normalize_origin( $origin ) {
		$origin = trim( (string) $origin );
		if ( '' === $origin ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $origin ) ) {
			$origin = 'https://' . ltrim( $origin, '/' );
		}

		$parts = wp_parse_url( $origin );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		$host = strtolower( $parts['host'] );
		$port = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';

		return $scheme . '://' . $host . $port;
	}

	/**
	 * Sanitize an array or newline-separated list of allowed origins.
	 *
	 * @param array|string $origins Raw origins.
	 * @return array
	 */
	public static function sanitize_origins( $origins ) {
		if ( is_string( $origins ) ) {
			$origins = preg_split( '/[\r\n,]+/', $origins );
		}

		if ( ! is_array( $origins ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $origins as $origin ) {
			$origin = self::normalize_origin( $origin );
			if ( '' !== $origin ) {
				$normalized[] = $origin;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Get approved external tracking origins.
	 *
	 * @return array
	 */
	public static function get_allowed_origins() {
		$origins = self::sanitize_origins(
			get_option( self::OPTION_ALLOWED_ORIGINS, array() )
		);

		return self::sanitize_origins(
			apply_filters( 'ot_external_tracking_allowed_origins', $origins )
		);
	}

	/**
	 * Determine whether an origin is approved for external tracking.
	 *
	 * @param string $origin Request origin.
	 * @return bool
	 */
	public static function is_allowed_origin( $origin ) {
		$origin = self::normalize_origin( $origin );
		if ( '' === $origin ) {
			return false;
		}

		return in_array( $origin, self::get_allowed_origins(), true );
	}

	/**
	 * Determine whether a page URL is allowed for the supplied request origin.
	 *
	 * @param string $page_url Page URL sent by the tracker.
	 * @param string $origin   Request origin.
	 * @return bool
	 */
	public static function is_allowed_page_url( $page_url, $origin = '' ) {
		$page_origin = self::normalize_origin( $page_url );
		if ( '' === $page_origin ) {
			return false;
		}

		if ( '' !== $origin ) {
			$origin = self::normalize_origin( $origin );

			return '' !== $origin
				&& $page_origin === $origin
				&& self::is_allowed_origin( $origin );
		}

		return in_array( $page_origin, self::get_allowed_origins(), true );
	}

	/**
	 * Get the browser origin for the current request.
	 *
	 * @return string
	 */
	public static function get_request_origin() {
		return isset( $_SERVER['HTTP_ORIGIN'] )
			? self::normalize_origin( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) )
			: '';
	}

	/**
	 * Generate the copyable static-site tracker snippet.
	 *
	 * @return string
	 */
	public static function get_tracker_snippet() {
		$script_url = OT_PLUGIN_URL . 'assets/js/ot-tracker.js';
		$rest_url   = rtrim( rest_url( 'open-tracker/v1' ), '/' );

		return sprintf(
			'<script async src="%1$s" data-ot-rest-url="%2$s"></script>',
			esc_url( $script_url ),
			esc_attr( $rest_url )
		);
	}
}
