<?php
/**
 * Simple and uniform HTTP request API.
 *
 * Standardizes the HTTP requests for WordPress. Handles cookies, gzip encoding and decoding, chunk
 * decoding, if HTTP 1.1 and various other difficult HTTP protocol implementations.
 *
 * @link http://trac.wordpress.org/ticket/4799 HTTP API Proposal
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 2.7.0
 */

/**
 * WordPress HTTP Class for managing HTTP Transports and making HTTP requests.
 *
 * This class is used to consistently make outgoing HTTP requests easy for developers
 * while still being compatible with the many PHP configurations under which
 * WordPress runs.
 *
 * Debugging includes several actions, which pass different variables for debugging the HTTP API.
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 2.7.0
 */
class WP_Http {

	/**
	 * Tests which transports are capable of supporting the request.
	 *
	 * @since 3.2.0
	 * @access private
	 *
	 * @param array $args Request arguments
	 * @param string $url URL to Request
	 *
	 * @return string|bool Class name for the first transport that claims to support the request. False if no transport claims to support the request.
	 */
	public function _get_first_available_transport( $args, $url = null ) {
		/**
		 * Filter which HTTP transports are available and in what order.
		 *
		 * @since 3.7.0
		 *
		 * @param array  $value Array of HTTP transports to check. Default array contains
		 *                      'curl', and 'streams', in that order.
		 * @param array  $args  HTTP request arguments.
		 * @param string $url   The URL to request.
		 */
		$request_order = apply_filters( 'http_api_transports', array( 'curl', 'streams' ), $args, $url );

		// Loop over each transport on each HTTP request looking for one which will serve this request's needs.
		foreach ( $request_order as $transport ) {
			$class = 'WP_HTTP_' . $transport;

			// Check to see if this transport is a possibility, calls the transport statically.
			if ( !call_user_func( array( $class, 'test' ), $args, $url ) )
				continue;

			return $class;
		}

		return false;
	}

	/**
	 * Uses the POST HTTP method.
	 *
	 * Used for sending data that is expected to be in the body.
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string       $url  The request URL.
	 * @param string|array $args Optional. Override the defaults.
	 * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A WP_Error instance upon error
	 */
	public function post($url, $args = array()) {
		$defaults = array('method' => 'POST');
		$r = wp_parse_args( $args, $defaults );
		return $this->request($url, $r);
	}

}

/**
 * HTTP request method uses PHP Streams to retrieve the url.
 *
 * @since 2.7.0
 * @since 3.7.0 Combined with the fsockopen transport and switched to stream_socket_client().
 */
class WP_Http_Streams {

	/**
	 * Whether this class can be used for retrieving a URL.
	 *
	 * @static
	 * @access public
	 * @since 2.7.0
	 * @since 3.7.0 Combined with the fsockopen transport and switched to stream_socket_client().
	 *
	 * @return boolean False means this class can not be used, true means it can.
	 */
	public static function test( $args = array() ) {
		if ( ! function_exists( 'stream_socket_client' ) )
			return false;

		$is_ssl = isset( $args['ssl'] ) && $args['ssl'];

		if ( $is_ssl ) {
			if ( ! extension_loaded( 'openssl' ) )
				return false;
			if ( ! function_exists( 'openssl_x509_parse' ) )
				return false;
		}

		/**
		 * Filter whether streams can be used as a transport for retrieving a URL.
		 *
		 * @since 2.7.0
		 *
		 * @param bool  $use_class Whether the class can be used. Default true.
		 * @param array $args      Request arguments.
		 */
		return apply_filters( 'use_streams_transport', true, $args );
	}
}

/**
 * HTTP request method uses Curl extension to retrieve the url.
 *
 * Requires the Curl extension to be installed.
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 2.7.0
 */
class WP_Http_Curl {

	/**
	 * Whether this class can be used for retrieving an URL.
	 *
	 * @static
	 * @since 2.7.0
	 *
	 * @return boolean False means this class can not be used, true means it can.
	 */
	public static function test( $args = array() ) {
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_exec' ) )
			return false;

		$is_ssl = isset( $args['ssl'] ) && $args['ssl'];

		if ( $is_ssl ) {
			$curl_version = curl_version();
			// Check whether this cURL version support SSL requests.
			if ( ! (CURL_VERSION_SSL & $curl_version['features']) )
				return false;
		}

		/**
		 * Filter whether cURL can be used as a transport for retrieving a URL.
		 *
		 * @since 2.7.0
		 *
		 * @param bool  $use_class Whether the class can be used. Default true.
		 * @param array $args      An array of request arguments.
		 */
		return apply_filters( 'use_curl_transport', true, $args );
	}
}
