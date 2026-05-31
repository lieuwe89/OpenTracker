<?php

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'OT_PLUGIN_URL', 'https://www.lieuwejongsma.nl/wp-content/plugins/open-tracker/' );
define( 'MINUTE_IN_SECONDS', 60 );

$ot_test_options = array(
	'ot_external_origins' => array(
		'https://playground.lieuwejongsma.nl',
		'palimpsest.lieuwejongsma.nl',
		'https://groningen-1926.lieuwejongsma.nl/path?ignored=1',
		'https://playground.lieuwejongsma.nl/',
		'javascript:alert(1)',
	),
);

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $ot_test_options;

		return array_key_exists( $name, $ot_test_options ) ? $ot_test_options[ $name ] : $default;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		if ( -1 === $component ) {
			return parse_url( $url );
		}

		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://www.lieuwejongsma.nl/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return 'valid' === $nonce && 'wp_rest' === $action;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient() {
		return 0;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient() {
		return true;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public $data;

		public function __construct( $code, $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}
	}
}

class OT_Test_REST_Request {
	private $headers;
	private $params;
	private $route;
	private $method;

	public function __construct( $headers, $params, $route, $method = 'POST' ) {
		$this->headers = $headers;
		$this->params  = $params;
		$this->route   = $route;
		$this->method  = $method;
	}

	public function get_header( $name ) {
		foreach ( $this->headers as $header_name => $value ) {
			if ( strtolower( $header_name ) === strtolower( $name ) ) {
				return $value;
			}
		}

		return '';
	}

	public function get_param( $name ) {
		return array_key_exists( $name, $this->params ) ? $this->params[ $name ] : null;
	}

	public function get_route() {
		return $this->route;
	}

	public function get_method() {
		return $this->method;
	}
}

require_once dirname( __DIR__ ) . '/open-tracker/includes/class-ot-database.php';
require_once dirname( __DIR__ ) . '/open-tracker/includes/class-ot-external-tracking.php';
require_once dirname( __DIR__ ) . '/open-tracker/includes/class-ot-settings.php';
require_once dirname( __DIR__ ) . '/open-tracker/includes/class-ot-rest-api.php';

function ot_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
		fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

function ot_assert_true( $actual, $message ) {
	ot_assert_same( true, $actual, $message );
}

function ot_assert_false( $actual, $message ) {
	ot_assert_same( false, $actual, $message );
}

ot_assert_same(
	'https://playground.lieuwejongsma.nl',
	OT_External_Tracking::normalize_origin( 'HTTPS://Playground.LieuweJongsma.nl/some/page?x=1' ),
	'Origins are normalized to scheme and host.'
);

ot_assert_same(
	array(
		'https://playground.lieuwejongsma.nl',
		'https://palimpsest.lieuwejongsma.nl',
		'https://groningen-1926.lieuwejongsma.nl',
	),
	OT_External_Tracking::get_allowed_origins(),
	'Allowed origins are sanitized, normalized, and de-duplicated.'
);

ot_assert_true(
	OT_External_Tracking::is_allowed_origin( 'https://palimpsest.lieuwejongsma.nl/' ),
	'Allowed origin with trailing slash is accepted.'
);

ot_assert_false(
	OT_External_Tracking::is_allowed_origin( 'https://unknown.lieuwejongsma.nl' ),
	'Unknown origin is rejected.'
);

ot_assert_true(
	OT_External_Tracking::is_allowed_page_url(
		'https://playground.lieuwejongsma.nl/demo/index.html?x=1',
		'https://playground.lieuwejongsma.nl'
	),
	'Page URL may be tracked when it belongs to the request origin.'
);

ot_assert_false(
	OT_External_Tracking::is_allowed_page_url(
		'https://palimpsest.lieuwejongsma.nl/essay',
		'https://playground.lieuwejongsma.nl'
	),
	'Page URL from another allowed origin is rejected for this request origin.'
);

ot_assert_false(
	OT_External_Tracking::is_allowed_page_url(
		'https://example.com/borrowed',
		'https://playground.lieuwejongsma.nl'
	),
	'Page URL from an unapproved host is rejected.'
);

$snippet = OT_External_Tracking::get_tracker_snippet();

ot_assert_true(
	false !== strpos( $snippet, 'src="https://www.lieuwejongsma.nl/wp-content/plugins/open-tracker/assets/js/ot-tracker.js"' ),
	'Snippet includes the plugin tracker script URL.'
);

ot_assert_true(
	false !== strpos( $snippet, 'data-ot-rest-url="https://www.lieuwejongsma.nl/wp-json/open-tracker/v1"' ),
	'Snippet includes the OpenTracker REST API base URL.'
);

ot_assert_same(
	"https://playground.lieuwejongsma.nl\nhttps://palimpsest.lieuwejongsma.nl\nhttps://groningen-1926.lieuwejongsma.nl",
	OT_Settings::format_origins_for_textarea(),
	'Settings textarea renders one normalized origin per line.'
);

$_SERVER['REMOTE_ADDR'] = '203.0.113.123';
$rest_api = new OT_REST_API();

ot_assert_true(
	$rest_api->check_permission(
		new OT_Test_REST_Request(
			array( 'X-WP-Nonce' => 'valid' ),
			array( 'page_url' => 'https://www.lieuwejongsma.nl/current-page/' ),
			'/open-tracker/v1/hit'
		)
	),
	'Nonce-authenticated WordPress tracking still works.'
);

$_SERVER['HTTP_ORIGIN'] = 'https://playground.lieuwejongsma.nl';

ot_assert_true(
	$rest_api->check_permission(
		new OT_Test_REST_Request(
			array(),
			array( 'page_url' => 'https://playground.lieuwejongsma.nl/demo/' ),
			'/open-tracker/v1/hit'
		)
	),
	'Approved external origin may send a hit without a nonce.'
);

$wrong_host_result = $rest_api->check_permission(
	new OT_Test_REST_Request(
		array(),
		array( 'page_url' => 'https://palimpsest.lieuwejongsma.nl/essay/' ),
		'/open-tracker/v1/hit'
	)
);

ot_assert_same(
	'ot_invalid_page_url',
	$wrong_host_result instanceof WP_Error ? $wrong_host_result->get_error_code() : '',
	'External hits cannot send page URLs from a different origin.'
);

ot_assert_true(
	$rest_api->check_permission(
		new OT_Test_REST_Request(
			array(),
			array( 'visit_id' => 42 ),
			'/open-tracker/v1/heartbeat'
		)
	),
	'Approved external origin may send heartbeat requests without a nonce.'
);

ot_assert_true(
	$rest_api->check_permission(
		new OT_Test_REST_Request(
			array(),
			array(),
			'/open-tracker/v1/hit',
			'OPTIONS'
		)
	),
	'Approved external origin may send preflight requests without a page URL.'
);

$future_route_result = $rest_api->check_permission(
	new OT_Test_REST_Request(
		array(),
		array(),
		'/open-tracker/v1/future-admin-route'
	)
);

ot_assert_same(
	'ot_invalid_nonce',
	$future_route_result instanceof WP_Error ? $future_route_result->get_error_code() : '',
	'External-origin fallback does not authorize unrelated future routes.'
);

echo 'External tracking tests passed.' . PHP_EOL;
