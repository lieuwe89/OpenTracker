/**
 * OpenTracker — Front-end Tracking Script
 *
 * 1. Records a page hit via REST API.
 * 2. Sends heartbeat pings every 15 seconds for retention tracking.
 * 3. Pauses heartbeat when the tab is hidden.
 */
(function () {
	'use strict';

	function getCurrentScript() {
		if ( document.currentScript ) {
			return document.currentScript;
		}

		var scripts = document.getElementsByTagName( 'script' );
		return scripts.length ? scripts[ scripts.length - 1 ] : null;
	}

	function getTrackerData() {
		var localizedData = window.otTrackerData || {};
		var script = getCurrentScript();
		var restUrl = localizedData.restUrl || '';

		if ( ! restUrl && script && script.getAttribute ) {
			restUrl = script.getAttribute( 'data-ot-rest-url' ) || '';
		}

		return {
			restUrl: restUrl ? restUrl.replace( /\/+$/, '' ) : '',
			nonce: localizedData.nonce || '',
			pageUrl: localizedData.pageUrl || window.location.href,
			referrer: typeof localizedData.referrer === 'string' ? localizedData.referrer : document.referrer
		};
	}

	var data = getTrackerData();
	if ( ! data.restUrl ) {
		return;
	}

	var visitId         = null;
	var heartbeatTimer  = null;
	var HEARTBEAT_INTERVAL = 15000; // 15 seconds

	/**
	 * Get cookie value by name.
	 */
	function getCookie( name ) {
		var match = document.cookie.match( new RegExp( '(^| )' + name + '=([^;]+)' ) );
		if ( match ) {
			return match[2];
		}
		return '';
	}

	/**
	 * Set a cookie.
	 */
	function setCookie( name, value, days ) {
		var expires = '';
		if ( days ) {
			var date = new Date();
			date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
			expires = '; expires=' + date.toUTCString();
		}
		document.cookie = name + '=' + ( value || '' ) + expires + '; path=/; SameSite=Lax';
	}

	/**
	 * Generate a unique UUIDv4.
	 */
	function generateUUID() {
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = Math.random() * 16 | 0, v = c === 'x' ? r : ( r & 0x3 | 0x8 );
			return v.toString( 16 );
		} );
	}

	/**
	 * Send a POST request to the REST API.
	 */
	function post( endpoint, body, callback ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', data.restUrl + '/' + endpoint, true );
		xhr.setRequestHeader( 'Content-Type', 'application/json' );
		if ( data.nonce ) {
			xhr.setRequestHeader( 'X-WP-Nonce', data.nonce );
		}

		xhr.onreadystatechange = function () {
			if ( xhr.readyState === 4 && xhr.status >= 200 && xhr.status < 300 ) {
				if ( callback ) {
					try {
						callback( JSON.parse( xhr.responseText ) );
					} catch ( e ) {
						// Silently ignore parse errors.
					}
				}
			}
		};

		xhr.send( JSON.stringify( body ) );
	}

	/**
	 * Start the heartbeat interval.
	 */
	function startHeartbeat() {
		if ( heartbeatTimer || ! visitId ) {
			return;
		}
		heartbeatTimer = setInterval( function () {
			post( 'heartbeat', { visit_id: visitId } );
		}, HEARTBEAT_INTERVAL );
	}

	/**
	 * Stop the heartbeat interval.
	 */
	function stopHeartbeat() {
		if ( heartbeatTimer ) {
			clearInterval( heartbeatTimer );
			heartbeatTimer = null;
		}
	}

	/**
	 * Handle visibility change — pause/resume heartbeat.
	 */
	function onVisibilityChange() {
		if ( document.hidden ) {
			stopHeartbeat();
		} else {
			startHeartbeat();
		}
	}

	/**
	 * Record the initial page hit, then start heartbeat.
	 */
	function init() {
		var visitorId = getCookie( 'ot_visitor' );
		if ( ! visitorId ) {
			visitorId = generateUUID();
			setCookie( 'ot_visitor', visitorId, 365 );
		}

		var sessionId = getCookie( 'ot_session' );
		if ( ! sessionId ) {
			sessionId = generateUUID();
			setCookie( 'ot_session', sessionId );
		}

		var screenResolution = window.screen ? window.screen.width + 'x' + window.screen.height : '';

		post(
			'hit',
			{
				page_url: data.pageUrl,
				referrer: data.referrer,
				visitor_id: visitorId,
				session_id: sessionId,
				screen_resolution: screenResolution
			},
			function ( response ) {
				if ( response && response.visit_id ) {
					visitId = response.visit_id;
					startHeartbeat();
				}
			}
		);

		// Dynamic event listener to auto-track PDF downloads.
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( 'a' );
			if ( link && link.href ) {
				var url = link.href;
				var cleanUrl = url.split( '?' )[0].split( '#' )[0];
				if ( cleanUrl.toLowerCase().endsWith( '.pdf' ) ) {
					if ( visitId ) {
						post( 'event', {
							visit_id: visitId,
							category: 'download',
							action: 'pdf',
							label: url
						} );
					}
				}
			}
		} );

		// Pause heartbeat when the tab is hidden.
		document.addEventListener( 'visibilitychange', onVisibilityChange );

		// Clean up on page unload.
		window.addEventListener( 'beforeunload', function () {
			stopHeartbeat();
		} );
	}

	// Wait for DOM to be ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

})();
