<?php
/**
 * Simple and uniform HTTP request API.
 *
 * Standardizes the HTTP requests for WordPress. Handles cookies, gzip encoding and decoding, chunk
 * decoding, if HTTP 1.1 and various other difficult HTTP protocol implementations.
 *
 * @link http://trac.wordpress.org/ticket/4779 HTTP API Proposal
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
	 * Send an HTTP request to a URI.
	 *
	 * Please note: The only URI that are supported in the HTTP Transport implementation
	 * are the HTTP and HTTPS protocols.
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string       $url  The request URL.
	 * @param string|array $args {
	 *     Optional. Array or string of HTTP request arguments.
	 *
	 *     @type string       $method              Request method. Accepts 'GET', 'POST', 'HEAD', or 'PUT'.
	 *                                             Some transports technically allow others, but should not be
	 *                                             assumed. Default 'GET'.
	 *     @type int          $timeout             How long the connection should stay open in seconds. Default 5.
	 *     @type int          $redirection         Number of allowed redirects. Not supported by all transports
	 *                                             Default 5.
	 *     @type string       $httpversion         Version of the HTTP protocol to use. Accepts '1.0' and '1.1'.
	 *                                             Default '1.0'.
	 *     @type string       $user-agent          User-agent value sent.
	 *                                             Default WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ).
	 *     @type bool         $reject_unsafe_urls  Whether to pass URLs through {@see wp_http_validate_url()}.
	 *                                             Default false.
	 *     @type bool         $blocking            Whether the calling code requires the result of the request.
	 *                                             If set to false, the request will be sent to the remote server,
	 *                                             and processing returned to the calling code immediately, the caller
	 *                                             will know if the request succeeded or failed, but will not receive
	 *                                             any response from the remote server. Default true.
	 *     @type string|array $headers             Array or string of headers to send with the request.
	 *                                             Default empty array.
	 *     @type array        $cookies             List of cookies to send with the request. Default empty array.
	 *     @type string|array $body                Body to send with the request. Default null.
	 *     @type bool         $compress            Whether to compress the $body when sending the request.
	 *                                             Default false.
	 *     @type bool         $decompress          Whether to decompress a compressed response. If set to false and
	 *                                             compressed content is returned in the response anyway, it will
	 *                                             need to be separately decompressed. Default true.
	 *     @type bool         $sslverify           Whether to verify SSL for the request. Default true.
	 *     @type string       sslcertificates      Absolute path to an SSL certificate .crt file.
	 *                                             Default ABSPATH . WPINC . '/certificates/ca-bundle.crt'.
	 *     @type bool         $stream              Whether to stream to a file. If set to true and no filename was
	 *                                             given, it will be droped it in the WP temp dir and its name will
	 *                                             be set using the basename of the URL. Default false.
	 *     @type string       $filename            Filename of the file to write to when streaming. $stream must be
	 *                                             set to true. Default null.
	 *     @type int          $limit_response_size Size in bytes to limit the response to. Default null.
	 *
	 * }
	 * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'.
	 *                        A WP_Error instance upon error.
	 */
	public function request( $url, $args = array() ) {
		global $wp_version;

		$defaults = array(
			'method' => 'GET',
			/**
			 * Filter the timeout value for an HTTP request.
			 *
			 * @since 2.7.0
			 *
			 * @param int $timeout_value Time in seconds until a request times out.
			 *                           Default 5.
			 */
			'timeout' => apply_filters( 'http_request_timeout', 5 ),
			/**
			 * Filter the number of redirects allowed during an HTTP request.
			 *
			 * @since 2.7.0
			 *
			 * @param int $redirect_count Number of redirects allowed. Default 5.
			 */
			'redirection' => apply_filters( 'http_request_redirection_count', 5 ),
			/**
			 * Filter the version of the HTTP protocol used in a request.
			 *
			 * @since 2.7.0
			 *
			 * @param string $version Version of HTTP used. Accepts '1.0' and '1.1'.
			 *                        Default '1.0'.
			 */
			'httpversion' => apply_filters( 'http_request_version', '1.0' ),
			/**
			 * Filter the user agent value sent with an HTTP request.
			 *
			 * @since 2.7.0
			 *
			 * @param string $user_agent WordPress user agent string.
			 */
			'user-agent' => apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) ),
			/**
			 * Filter whether to pass URLs through wp_http_validate_url() in an HTTP request.
			 *
			 * @since 3.6.0
			 *
			 * @param bool $pass_url Whether to pass URLs through wp_http_validate_url().
			 *                       Default false.
			 */
			'reject_unsafe_urls' => apply_filters( 'http_request_reject_unsafe_urls', false ),
			'blocking' => true,
			'headers' => array(),
			'cookies' => array(),
			'body' => null,
			'compress' => false,
			'decompress' => true,
			'sslverify' => true,
			'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
			'stream' => false,
			'filename' => null,
			'limit_response_size' => null,
		);

		// Pre-parse for the HEAD checks.
		$args = wp_parse_args( $args );

		// By default, Head requests do not cause redirections.
		if ( isset($args['method']) && 'HEAD' == $args['method'] )
			$defaults['redirection'] = 0;

		$r = wp_parse_args( $args, $defaults );
		/**
		 * Filter the arguments used in an HTTP request.
		 *
		 * @since 2.7.0
		 *
		 * @param array  $r   An array of HTTP request arguments.
		 * @param string $url The request URL.
		 */
		$r = apply_filters( 'http_request_args', $r, $url );

		// The transports decrement this, store a copy of the original value for loop purposes.
		if ( ! isset( $r['_redirection'] ) )
			$r['_redirection'] = $r['redirection'];

		/**
		 * Filter whether to preempt an HTTP request's return.
		 *
		 * Returning a truthy value to the filter will short-circuit
		 * the HTTP request and return early with that value.
		 *
		 * @since 2.9.0
		 *
		 * @param bool   $preempt Whether to preempt an HTTP request return. Default false.
		 * @param array  $r       HTTP request arguments.
		 * @param string $url     The request URL.
		 */
		$pre = apply_filters( 'pre_http_request', false, $r, $url );
		if ( false !== $pre )
			return $pre;

		if ( function_exists( 'wp_kses_bad_protocol' ) ) {
			if ( $r['reject_unsafe_urls'] )
				$url = wp_http_validate_url( $url );
			$url = wp_kses_bad_protocol( $url, array( 'http', 'https', 'ssl' ) );
		}

		$arrURL = @parse_url( $url );

		if ( empty( $url ) || empty( $arrURL['scheme'] ) )
			return new WP_Error('http_request_failed', __('A valid URL was not provided.'));

		if ( $this->block_request( $url ) )
			return new WP_Error( 'http_request_failed', __( 'User has blocked requests through HTTP.' ) );

		/*
		 * Determine if this is a https call and pass that on to the transport functions
		 * so that we can blacklist the transports that do not support ssl verification
		 */
		$r['ssl'] = $arrURL['scheme'] == 'https' || $arrURL['scheme'] == 'ssl';

		// Determine if this request is to OUR install of WordPress.
		$homeURL = parse_url( get_bloginfo( 'url' ) );
		$r['local'] = 'localhost' == $arrURL['host'] || ( isset( $homeURL['host'] ) && $homeURL['host'] == $arrURL['host'] );
		unset( $homeURL );

		/*
		 * If we are streaming to a file but no filename was given drop it in the WP temp dir
		 * and pick its name using the basename of the $url.
		 */
		if ( $r['stream']  && empty( $r['filename'] ) )
			$r['filename'] = get_temp_dir() . basename( $url );

		/*
		 * Force some settings if we are streaming to a file and check for existence and perms
		 * of destination directory.
		 */
		if ( $r['stream'] ) {
			$r['blocking'] = true;
			if ( ! wp_is_writable( dirname( $r['filename'] ) ) )
				return new WP_Error( 'http_request_failed', __( 'Destination directory for file streaming does not exist or is not writable.' ) );
		}

		if ( is_null( $r['headers'] ) )
			$r['headers'] = array();

		if ( ! is_array( $r['headers'] ) ) {
			$processedHeaders = WP_Http::processHeaders( $r['headers'], $url );
			$r['headers'] = $processedHeaders['headers'];
		}

		if ( isset( $r['headers']['User-Agent'] ) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset( $r['headers']['User-Agent'] );
		}

		if ( isset( $r['headers']['user-agent'] ) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset( $r['headers']['user-agent'] );
		}

		if ( '1.1' == $r['httpversion'] && !isset( $r['headers']['connection'] ) ) {
			$r['headers']['connection'] = 'close';
		}

		// Construct Cookie: header if any cookies are set.
		WP_Http::buildCookieHeader( $r );

		// Avoid issues where mbstring.func_overload is enabled.
		mbstring_binary_safe_encoding();

		if ( ! isset( $r['headers']['Accept-Encoding'] ) ) {
			if ( $encoding = WP_Http_Encoding::accept_encoding( $url, $r ) )
				$r['headers']['Accept-Encoding'] = $encoding;
		}

		if ( ( ! is_null( $r['body'] ) && '' != $r['body'] ) || 'POST' == $r['method'] || 'PUT' == $r['method'] ) {
			if ( is_array( $r['body'] ) || is_object( $r['body'] ) ) {
				$r['body'] = http_build_query( $r['body'], null, '&' );

				if ( ! isset( $r['headers']['Content-Type'] ) )
					$r['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' );
			}

			if ( '' === $r['body'] )
				$r['body'] = null;

			if ( ! isset( $r['headers']['Content-Length'] ) && ! isset( $r['headers']['content-length'] ) )
				$r['headers']['Content-Length'] = strlen( $r['body'] );
		}

		$response = $this->_dispatch_request( $url, $r );

		reset_mbstring_encoding();

		if ( is_wp_error( $response ) )
			return $response;

		// Append cookies that were used in this request to the response
		if ( ! empty( $r['cookies'] ) ) {
			$cookies_set = wp_list_pluck( $response['cookies'], 'name' );
			foreach ( $r['cookies'] as $cookie ) {
				if ( ! in_array( $cookie->name, $cookies_set ) && $cookie->test( $url ) ) {
					$response['cookies'][] = $cookie;
				}
			}
		}

		return $response;
	}

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
	 * Dispatches a HTTP request to a supporting transport.
	 *
	 * Tests each transport in order to find a transport which matches the request arguments.
	 * Also caches the transport instance to be used later.
	 *
	 * The order for requests is cURL, and then PHP Streams.
	 *
	 * @since 3.2.0
	 * @access private
	 *
	 * @param string $url URL to Request
	 * @param array $args Request arguments
	 * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A WP_Error instance upon error
	 */
	private function _dispatch_request( $url, $args ) {
		static $transports = array();

		$class = $this->_get_first_available_transport( $args, $url );
		if ( !$class )
			return new WP_Error( 'http_failure', __( 'There are no HTTP transports available which can complete the requested request.' ) );

		// Transport claims to support request, instantiate it and give it a whirl.
		if ( empty( $transports[$class] ) )
			$transports[$class] = new $class;

		$response = $transports[$class]->request( $url, $args );

		/**
		 * Fires after an HTTP API response is received and before the response is returned.
		 *
		 * @since 2.8.0
		 *
		 * @param array|WP_Error $response HTTP response or WP_Error object.
		 * @param string         $context  Context under which the hook is fired.
		 * @param string         $class    HTTP transport used.
		 * @param array          $args     HTTP request arguments.
		 * @param string         $url      The request URL.
		 */
		do_action( 'http_api_debug', $response, 'response', $class, $args, $url );

		if ( is_wp_error( $response ) )
			return $response;

		/**
		 * Filter the HTTP API response immediately before the response is returned.
		 *
		 * @since 2.9.0
		 *
		 * @param array  $response HTTP response.
		 * @param array  $args     HTTP request arguments.
		 * @param string $url      The request URL.
		 */
		return apply_filters( 'http_response', $response, $args, $url );
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





















































	/**
	 * Transform header string into an array.
	 *
	 * If an array is given then it is assumed to be raw header data with numeric keys with the
	 * headers as the values. No headers must be passed that were already processed.
	 *
	 * @access public
	 * @static
	 * @since 2.7.0
	 *
	 * @param string|array $headers
	 * @param string $url The URL that was requested
	 * @return array Processed string headers. If duplicate headers are encountered,
	 * 					Then a numbered array is returned as the value of that header-key.
	 */
	public static function processHeaders( $headers, $url = '' ) {
		// Split headers, one per array element.
		if ( is_string($headers) ) {
			// Tolerate line terminator: CRLF = LF (RFC 2616 19.3).
			$headers = str_replace("\r\n", "\n", $headers);
			/*
			 * Unfold folded header fields. LWS = [CRLF] 1*( SP | HT ) <US-ASCII SP, space (32)>,
			 * <US-ASCII HT, horizontal-tab (9)> (RFC 2616 2.2).
			 */
			$headers = preg_replace('/\n[ \t]/', ' ', $headers);
			// Create the headers array.
			$headers = explode("\n", $headers);
		}

		$response = array('code' => 0, 'message' => '');

		/*
		 * If a redirection has taken place, The headers for each page request may have been passed.
		 * In this case, determine the final HTTP header and parse from there.
		 */
		for ( $i = count($headers)-1; $i >= 0; $i-- ) {
			if ( !empty($headers[$i]) && false === strpos($headers[$i], ':') ) {
				$headers = array_splice($headers, $i);
				break;
			}
		}

		$cookies = array();
		$newheaders = array();
		foreach ( (array) $headers as $tempheader ) {
			if ( empty($tempheader) )
				continue;

			if ( false === strpos($tempheader, ':') ) {
				$stack = explode(' ', $tempheader, 3);
				$stack[] = '';
				list( , $response['code'], $response['message']) = $stack;
				continue;
			}

			list($key, $value) = explode(':', $tempheader, 2);

			$key = strtolower( $key );
			$value = trim( $value );

			if ( isset( $newheaders[ $key ] ) ) {
				if ( ! is_array( $newheaders[ $key ] ) )
					$newheaders[$key] = array( $newheaders[ $key ] );
				$newheaders[ $key ][] = $value;
			} else {
				$newheaders[ $key ] = $value;
			}
			if ( 'set-cookie' == $key )
				$cookies[] = new WP_Http_Cookie( $value, $url );
		}

		return array('response' => $response, 'headers' => $newheaders, 'cookies' => $cookies);
	}

	/**
	 * Takes the arguments for a ::request() and checks for the cookie array.
	 *
	 * If it's found, then it upgrades any basic name => value pairs to WP_Http_Cookie instances,
	 * which are each parsed into strings and added to the Cookie: header (within the arguments array).
	 * Edits the array by reference.
	 *
	 * @access public
	 * @version 2.8.0
	 * @static
	 *
	 * @param array $r Full array of args passed into ::request()
	 */
	public static function buildCookieHeader( &$r ) {
		if ( ! empty($r['cookies']) ) {
			// Upgrade any name => value cookie pairs to WP_HTTP_Cookie instances.
			foreach ( $r['cookies'] as $name => $value ) {
				if ( ! is_object( $value ) )
					$r['cookies'][ $name ] = new WP_HTTP_Cookie( array( 'name' => $name, 'value' => $value ) );
			}

			$cookies_header = '';
			foreach ( (array) $r['cookies'] as $cookie ) {
				$cookies_header .= $cookie->getHeaderValue() . '; ';
			}

			$cookies_header = substr( $cookies_header, 0, -2 );
			$r['headers']['cookie'] = $cookies_header;
		}
	}













































	/**
	 * Block requests through the proxy.
	 *
	 * Those who are behind a proxy and want to prevent access to certain hosts may do so. This will
	 * prevent plugins from working and core functionality, if you don't include api.wordpress.org.
	 *
	 * You block external URL requests by defining WP_HTTP_BLOCK_EXTERNAL as true in your wp-config.php
	 * file and this will only allow localhost and your blog to make requests. The constant
	 * WP_ACCESSIBLE_HOSTS will allow additional hosts to go through for requests. The format of the
	 * WP_ACCESSIBLE_HOSTS constant is a comma separated list of hostnames to allow, wildcard domains
	 * are supported, eg *.wordpress.org will allow for all subdomains of wordpress.org to be contacted.
	 *
	 * @since 2.8.0
	 * @link http://core.trac.wordpress.org/ticket/8927 Allow preventing external requests.
	 * @link http://core.trac.wordpress.org/ticket/14636 Allow wildcard domains in WP_ACCESSIBLE_HOSTS
	 *
	 * @param string $uri URI of url.
	 * @return bool True to block, false to allow.
	 */
	public function block_request($uri) {
		// We don't need to block requests, because nothing is blocked.
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL )
			return false;

		$check = parse_url($uri);
		if ( ! $check )
			return true;

		$home = parse_url( get_option('siteurl') );

		// Don't block requests back to ourselves by default.
		if ( 'localhost' == $check['host'] || ( isset( $home['host'] ) && $home['host'] == $check['host'] ) ) {
			/**
			 * Filter whether to block local requests through the proxy.
			 *
			 * @since 2.8.0
			 *
			 * @param bool $block Whether to block local requests through proxy.
			 *                    Default false.
			 */
			return apply_filters( 'block_local_requests', false );
		}

		if ( !defined('WP_ACCESSIBLE_HOSTS') )
			return true;

		static $accessible_hosts;
		static $wildcard_regex = false;
		if ( null == $accessible_hosts ) {
			$accessible_hosts = preg_split('|,\s*|', WP_ACCESSIBLE_HOSTS);

			if ( false !== strpos(WP_ACCESSIBLE_HOSTS, '*') ) {
				$wildcard_regex = array();
				foreach ( $accessible_hosts as $host )
					$wildcard_regex[] = str_replace( '\*', '.+', preg_quote( $host, '/' ) );
				$wildcard_regex = '/^(' . implode('|', $wildcard_regex) . ')$/i';
			}
		}

		if ( !empty($wildcard_regex) )
			return !preg_match($wildcard_regex, $check['host']);
		else
			return !in_array( $check['host'], $accessible_hosts ); //Inverse logic, If it's in the array, then we can't access it.

	}


















































	/**
	 * Handles HTTP Redirects and follows them if appropriate.
	 *
	 * @since 3.7.0
	 *
	 * @param string $url The URL which was requested.
	 * @param array $args The Arguments which were used to make the request.
	 * @param array $response The Response of the HTTP request.
	 * @return false|object False if no redirect is present, a WP_HTTP or WP_Error result otherwise.
	 */
	public static function handle_redirects( $url, $args, $response ) {
		// If no redirects are present, or, redirects were not requested, perform no action.
		if ( ! isset( $response['headers']['location'] ) || 0 === $args['_redirection'] )
			return false;

		// Only perform redirections on redirection http codes.
		if ( $response['response']['code'] > 399 || $response['response']['code'] < 300 )
			return false;

		// Don't redirect if we've run out of redirects.
		if ( $args['redirection']-- <= 0 )
			return new WP_Error( 'http_request_failed', __('Too many redirects.') );

		$redirect_location = $response['headers']['location'];

		// If there were multiple Location headers, use the last header specified.
		if ( is_array( $redirect_location ) )
			$redirect_location = array_pop( $redirect_location );

		$redirect_location = WP_HTTP::make_absolute_url( $redirect_location, $url );

		// POST requests should not POST to a redirected location.
		if ( 'POST' == $args['method'] ) {
			if ( in_array( $response['response']['code'], array( 302, 303 ) ) )
				$args['method'] = 'GET';
		}

		// Include valid cookies in the redirect process.
		if ( ! empty( $response['cookies'] ) ) {
			foreach ( $response['cookies'] as $cookie ) {
				if ( $cookie->test( $redirect_location ) )
					$args['cookies'][] = $cookie;
			}
		}

		return wp_remote_request( $redirect_location, $args );
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
	 * Send a HTTP request to a URI using cURL extension.
	 *
	 * @access public
	 * @since 2.7.0
	 *
	 * @param string $url The request URL.
	 * @param string|array $args Optional. Override the defaults.
	 * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A WP_Error instance upon error
	 */
	public function request($url, $args = array()) {
		$defaults = array(
			'method' => 'GET', 'timeout' => 5,
			'redirection' => 5, 'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(), 'body' => null, 'cookies' => array()
		);

		$r = wp_parse_args( $args, $defaults );

		if ( isset($r['headers']['User-Agent']) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset($r['headers']['User-Agent']);
		} else if ( isset($r['headers']['user-agent']) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset($r['headers']['user-agent']);
		}

		// Construct Cookie: header if any cookies are set.
		WP_Http::buildCookieHeader( $r );

		$handle = curl_init();

		// cURL offers really easy proxy support.
		$proxy = new WP_HTTP_Proxy();

		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) {

			curl_setopt( $handle, CURLOPT_PROXYTYPE, CURLPROXY_HTTP );
			curl_setopt( $handle, CURLOPT_PROXY, $proxy->host() );
			curl_setopt( $handle, CURLOPT_PROXYPORT, $proxy->port() );

			if ( $proxy->use_authentication() ) {
				curl_setopt( $handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY );
				curl_setopt( $handle, CURLOPT_PROXYUSERPWD, $proxy->authentication() );
			}
		}

		$is_local = isset($r['local']) && $r['local'];
		$ssl_verify = isset($r['sslverify']) && $r['sslverify'];
		if ( $is_local ) {
			/** This filter is documented in wp-includes/class-http.php */
			$ssl_verify = apply_filters( 'https_local_ssl_verify', $ssl_verify );
		} elseif ( ! $is_local ) {
			/** This filter is documented in wp-includes/class-http.php */
			$ssl_verify = apply_filters( 'https_ssl_verify', $ssl_verify );
		}

		/*
		 * CURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT expect integers. Have to use ceil since.
		 * a value of 0 will allow an unlimited timeout.
		 */
		$timeout = (int) ceil( $r['timeout'] );
		curl_setopt( $handle, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $handle, CURLOPT_TIMEOUT, $timeout );

		curl_setopt( $handle, CURLOPT_URL, $url);
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, ( $ssl_verify === true ) ? 2 : false );
		curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, $ssl_verify );
		curl_setopt( $handle, CURLOPT_CAINFO, $r['sslcertificates'] );
		curl_setopt( $handle, CURLOPT_USERAGENT, $r['user-agent'] );

		/*
		 * The option doesn't work with safe mode or when open_basedir is set, and there's
		 * a bug #17490 with redirected POST requests, so handle redirections outside Curl.
		 */
		curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, false );
		if ( defined( 'CURLOPT_PROTOCOLS' ) ) // PHP 5.2.10 / cURL 7.19.4
			curl_setopt( $handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );

		switch ( $r['method'] ) {
			case 'HEAD':
				curl_setopt( $handle, CURLOPT_NOBODY, true );
				break;
			case 'POST':
				curl_setopt( $handle, CURLOPT_POST, true );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			case 'PUT':
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'PUT' );
				curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
			default:
				curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, $r['method'] );
				if ( ! is_null( $r['body'] ) )
					curl_setopt( $handle, CURLOPT_POSTFIELDS, $r['body'] );
				break;
		}

		if ( true === $r['blocking'] ) {
			curl_setopt( $handle, CURLOPT_HEADERFUNCTION, array( $this, 'stream_headers' ) );
			curl_setopt( $handle, CURLOPT_WRITEFUNCTION, array( $this, 'stream_body' ) );
		}

		curl_setopt( $handle, CURLOPT_HEADER, false );

		if ( isset( $r['limit_response_size'] ) )
			$this->max_body_length = intval( $r['limit_response_size'] );
		else
			$this->max_body_length = false;

		// If streaming to a file open a file handle, and setup our curl streaming handler.
		if ( $r['stream'] ) {
			if ( ! WP_DEBUG )
				$this->stream_handle = @fopen( $r['filename'], 'w+' );
			else
				$this->stream_handle = fopen( $r['filename'], 'w+' );
			if ( ! $this->stream_handle )
				return new WP_Error( 'http_request_failed', sprintf( __( 'Could not open handle for fopen() to %s' ), $r['filename'] ) );
		} else {
			$this->stream_handle = false;
		}

		if ( !empty( $r['headers'] ) ) {
			// cURL expects full header strings in each element.
			$headers = array();
			foreach ( $r['headers'] as $name => $value ) {
				$headers[] = "{$name}: $value";
			}
			curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		}

		if ( $r['httpversion'] == '1.0' )
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0 );
		else
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		/**
		 * Fires before the cURL request is executed.
		 *
		 * Cookies are not currently handled by the HTTP API. This action allows
		 * plugins to handle cookies themselves.
		 *
		 * @since 2.8.0
		 *
		 * @param resource &$handle The cURL handle returned by curl_init().
		 * @param array    $r       The HTTP request arguments.
		 * @param string   $url     The request URL.
		 */
		do_action_ref_array( 'http_api_curl', array( &$handle, $r, $url ) );

		// We don't need to return the body, so don't. Just execute request and return.
		if ( ! $r['blocking'] ) {
			curl_exec( $handle );

			if ( $curl_error = curl_error( $handle ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', $curl_error );
			}
			if ( in_array( curl_getinfo( $handle, CURLINFO_HTTP_CODE ), array( 301, 302 ) ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', __( 'Too many redirects.' ) );
			}

			curl_close( $handle );
			return array( 'headers' => array(), 'body' => '', 'response' => array('code' => false, 'message' => false), 'cookies' => array() );
		}

		curl_exec( $handle );
		$theHeaders = WP_Http::processHeaders( $this->headers, $url );
		$theBody = $this->body;

		$this->headers = '';
		$this->body = '';

		$curl_error = curl_errno( $handle );

		// If an error occurred, or, no response.
		if ( $curl_error || ( 0 == strlen( $theBody ) && empty( $theHeaders['headers'] ) ) ) {
			if ( CURLE_WRITE_ERROR /* 23 */ == $curl_error &&  $r['stream'] ) {
				fclose( $this->stream_handle );
				return new WP_Error( 'http_request_failed', __( 'Failed to write request to temporary file.' ) );
			}
			if ( $curl_error = curl_error( $handle ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', $curl_error );
			}
			if ( in_array( curl_getinfo( $handle, CURLINFO_HTTP_CODE ), array( 301, 302 ) ) ) {
				curl_close( $handle );
				return new WP_Error( 'http_request_failed', __( 'Too many redirects.' ) );
			}
		}

		$response = array();
		$response['code'] = curl_getinfo( $handle, CURLINFO_HTTP_CODE );
		$response['message'] = get_status_header_desc($response['code']);

		curl_close( $handle );

		if ( $r['stream'] )
			fclose( $this->stream_handle );

		$response = array(
			'headers' => $theHeaders['headers'],
			'body' => null,
			'response' => $response,
			'cookies' => $theHeaders['cookies'],
			'filename' => $r['filename']
		);

		// Handle redirects.
		if ( false !== ( $redirect_response = WP_HTTP::handle_redirects( $url, $r, $response ) ) )
			return $redirect_response;

		if ( true === $r['decompress'] && true === WP_Http_Encoding::should_decode($theHeaders['headers']) )
			$theBody = WP_Http_Encoding::decompress( $theBody );

		$response['body'] = $theBody;

		return $response;
	}

	/**
	 * Grab the headers of the cURL request
	 *
	 * Each header is sent individually to this callback, so we append to the $header property for temporary storage
	 *
	 * @since 3.2.0
	 * @access private
	 * @return int
	 */
	private function stream_headers( $handle, $headers ) {
		$this->headers .= $headers;
		return strlen( $headers );
	}

	/**
	 * Grab the body of the cURL request
	 *
	 * The contents of the document are passed in chunks, so we append to the $body property for temporary storage.
	 * Returning a length shorter than the length of $data passed in will cause cURL to abort the request as "completed"
	 *
	 * @since 3.6.0
	 * @access private
	 * @return int
	 */
	private function stream_body( $handle, $data ) {
		$data_length = strlen( $data );

		if ( $this->max_body_length && ( strlen( $this->body ) + $data_length ) > $this->max_body_length )
			$data = substr( $data, 0, ( $this->max_body_length - $data_length ) );

		if ( $this->stream_handle ) {
			$bytes_written = fwrite( $this->stream_handle, $data );
		} else {
			$this->body .= $data;
			$bytes_written = $data_length;
		}

		// Upon event of this function returning less than strlen( $data ) curl will error with CURLE_WRITE_ERROR.
		return $bytes_written;
	}

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

/**
 * Adds Proxy support to the WordPress HTTP API.
 *
 * There are caveats to proxy support. It requires that defines be made in the wp-config.php file to
 * enable proxy support. There are also a few filters that plugins can hook into for some of the
 * constants.
 *
 * Please note that only BASIC authentication is supported by most transports.
 * cURL MAY support more methods (such as NTLM authentication) depending on your environment.
 *
 * The constants are as follows:
 * <ol>
 * <li>WP_PROXY_HOST - Enable proxy support and host for connecting.</li>
 * <li>WP_PROXY_PORT - Proxy port for connection. No default, must be defined.</li>
 * <li>WP_PROXY_USERNAME - Proxy username, if it requires authentication.</li>
 * <li>WP_PROXY_PASSWORD - Proxy password, if it requires authentication.</li>
 * <li>WP_PROXY_BYPASS_HOSTS - Will prevent the hosts in this list from going through the proxy.
 * You do not need to have localhost and the blog host in this list, because they will not be passed
 * through the proxy. The list should be presented in a comma separated list, wildcards using * are supported, eg. *.wordpress.org</li>
 * </ol>
 *
 * An example can be as seen below.
 * <code>
 * define('WP_PROXY_HOST', '192.168.84.101');
 * define('WP_PROXY_PORT', '8080');
 * define('WP_PROXY_BYPASS_HOSTS', 'localhost, www.example.com, *.wordpress.org');
 * </code>
 *
 * @link http://core.trac.wordpress.org/ticket/4011 Proxy support ticket in WordPress.
 * @link http://core.trac.wordpress.org/ticket/14636 Allow wildcard domains in WP_PROXY_BYPASS_HOSTS
 * @since 2.8.0
 */
class WP_HTTP_Proxy {

	/**
	 * Whether proxy connection should be used.
	 *
	 * @since 2.8.0
	 *
	 * @use WP_PROXY_HOST
	 * @use WP_PROXY_PORT
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return defined('WP_PROXY_HOST') && defined('WP_PROXY_PORT');
	}





























































































	/**
	 * Whether URL should be sent through the proxy server.
	 *
	 * We want to keep localhost and the blog URL from being sent through the proxy server, because
	 * some proxies can not handle this. We also have the constant available for defining other
	 * hosts that won't be sent through the proxy.
	 *
	 * @uses WP_PROXY_BYPASS_HOSTS
	 * @since 2.8.0
	 *
	 * @param string $uri URI to check.
	 * @return bool True, to send through the proxy and false if, the proxy should not be used.
	 */
	public function send_through_proxy( $uri ) {
		/*
		 * parse_url() only handles http, https type URLs, and will emit E_WARNING on failure.
		 * This will be displayed on blogs, which is not reasonable.
		 */
		$check = @parse_url($uri);

		// Malformed URL, can not process, but this could mean ssl, so let through anyway.
		if ( $check === false )
			return true;

		$home = parse_url( get_option('siteurl') );

		/**
		 * Filter whether to preempt sending the request through the proxy server.
		 *
		 * Returning false will bypass the proxy; returning true will send
		 * the request through the proxy. Returning null bypasses the filter.
		 *
		 * @since 3.5.0
		 *
		 * @param null   $override Whether to override the request result. Default null.
		 * @param string $uri      URL to check.
		 * @param array  $check    Associative array result of parsing the URI.
		 * @param array  $home     Associative array result of parsing the site URL.
		 */
		$result = apply_filters( 'pre_http_send_through_proxy', null, $uri, $check, $home );
		if ( ! is_null( $result ) )
			return $result;

		if ( 'localhost' == $check['host'] || ( isset( $home['host'] ) && $home['host'] == $check['host'] ) )
			return false;

		if ( !defined('WP_PROXY_BYPASS_HOSTS') )
			return true;

		static $bypass_hosts;
		static $wildcard_regex = false;
		if ( null == $bypass_hosts ) {
			$bypass_hosts = preg_split('|,\s*|', WP_PROXY_BYPASS_HOSTS);

			if ( false !== strpos(WP_PROXY_BYPASS_HOSTS, '*') ) {
				$wildcard_regex = array();
				foreach ( $bypass_hosts as $host )
					$wildcard_regex[] = str_replace( '\*', '.+', preg_quote( $host, '/' ) );
				$wildcard_regex = '/^(' . implode('|', $wildcard_regex) . ')$/i';
			}
		}

		if ( !empty($wildcard_regex) )
			return !preg_match($wildcard_regex, $check['host']);
		else
			return !in_array( $check['host'], $bypass_hosts );
	}
}
/**
 * Internal representation of a single cookie.
 *
 * Returned cookies are represented using this class, and when cookies are set, if they are not
 * already a WP_Http_Cookie() object, then they are turned into one.
 *
 * @todo The WordPress convention is to use underscores instead of camelCase for function and method
 * names. Need to switch to use underscores instead for the methods.
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 2.8.0
 */
class WP_Http_Cookie {

	/**
	 * Cookie name.
	 *
	 * @since 2.8.0
	 * @var string
	 */
	public $name;

	/**
	 * Cookie value.
	 *
	 * @since 2.8.0
	 * @var string
	 */
	public $value;

	/**
	 * When the cookie expires.
	 *
	 * @since 2.8.0
	 * @var string
	 */
	public $expires;

	/**
	 * Cookie URL path.
	 *
	 * @since 2.8.0
	 * @var string
	 */
	public $path;

	/**
	 * Cookie Domain.
	 *
	 * @since 2.8.0
	 * @var string
	 */
	public $domain;

	/**
	 * Sets up this cookie object.
	 *
	 * The parameter $data should be either an associative array containing the indices names below
	 * or a header string detailing it.
	 *
	 * @since 2.8.0
	 * @access public
	 *
	 * @param string|array $data {
	 *     Raw cookie data as header string or data array.
	 *
	 *     @type string     $name    Cookie name.
	 *     @type mixed      $value   Value. Should NOT already be urlencoded.
	 *     @type string|int $expires Optional. Unix timestamp or formatted date. Default null.
	 *     @type string     $path    Optional. Path. Default '/'.
	 *     @type string     $domain  Optional. Domain. Default host of parsed $requested_url.
	 *     @type int        $port    Optional. Port. Default null.
	 * }
	 * @param string       $requested_url The URL which the cookie was set on, used for default $domain
	 *                                    and $port values.
	 */
	public function __construct( $data, $requested_url = '' ) {
		if ( $requested_url )
			$arrURL = @parse_url( $requested_url );
		if ( isset( $arrURL['host'] ) )
			$this->domain = $arrURL['host'];
		$this->path = isset( $arrURL['path'] ) ? $arrURL['path'] : '/';
		if (  '/' != substr( $this->path, -1 ) )
			$this->path = dirname( $this->path ) . '/';

		if ( is_string( $data ) ) {
			// Assume it's a header string direct from a previous request.
			$pairs = explode( ';', $data );

			// Special handling for first pair; name=value. Also be careful of "=" in value.
			$name  = trim( substr( $pairs[0], 0, strpos( $pairs[0], '=' ) ) );
			$value = substr( $pairs[0], strpos( $pairs[0], '=' ) + 1 );
			$this->name  = $name;
			$this->value = urldecode( $value );

			// Removes name=value from items.
			array_shift( $pairs );

			// Set everything else as a property.
			foreach ( $pairs as $pair ) {
				$pair = rtrim($pair);

				// Handle the cookie ending in ; which results in a empty final pair.
				if ( empty($pair) )
					continue;

				list( $key, $val ) = strpos( $pair, '=' ) ? explode( '=', $pair ) : array( $pair, '' );
				$key = strtolower( trim( $key ) );
				if ( 'expires' == $key )
					$val = strtotime( $val );
				$this->$key = $val;
			}
		} else {
			if ( !isset( $data['name'] ) )
				return false;

			// Set properties based directly on parameters.
			foreach ( array( 'name', 'value', 'path', 'domain', 'port' ) as $field ) {
				if ( isset( $data[ $field ] ) )
					$this->$field = $data[ $field ];
			}

			if ( isset( $data['expires'] ) )
				$this->expires = is_int( $data['expires'] ) ? $data['expires'] : strtotime( $data['expires'] );
			else
				$this->expires = null;
		}
	}
}



















































































/**
 * Implementation for deflate and gzip transfer encodings.
 *
 * Includes RFC 1950, RFC 1951, and RFC 1952.
 *
 * @since 2.8.0
 * @package WordPress
 * @subpackage HTTP
 */
class WP_Http_Encoding {










































































































	/**
	 * What encoding types to accept and their priority values.
	 *
	 * @since 2.8.0
	 *
	 * @return string Types of encoding to accept.
	 */
	public static function accept_encoding( $url, $args ) {
		$type = array();
		$compression_enabled = WP_Http_Encoding::is_available();

		if ( ! $args['decompress'] ) // Decompression specifically disabled.
			$compression_enabled = false;
		elseif ( $args['stream'] ) // Disable when streaming to file.
			$compression_enabled = false;
		elseif ( isset( $args['limit_response_size'] ) ) // If only partial content is being requested, we won't be able to decompress it.
			$compression_enabled = false;

		if ( $compression_enabled ) {
			if ( function_exists( 'gzinflate' ) )
				$type[] = 'deflate;q=1.0';

			if ( function_exists( 'gzuncompress' ) )
				$type[] = 'compress;q=0.5';

			if ( function_exists( 'gzdecode' ) )
				$type[] = 'gzip;q=0.5';
		}

		/**
		 * Filter the allowed encoding types.
		 *
		 * @since 3.6.0
		 *
		 * @param array  $type Encoding types allowed. Accepts 'gzinflate',
		 *                     'gzuncompress', 'gzdecode'.
		 * @param string $url  URL of the HTTP request.
		 * @param array  $args HTTP request arguments.
		 */
		$type = apply_filters( 'wp_http_accept_encoding', $type, $url, $args );

		return implode(', ', $type);
	}












	/**
	 * Whether the content be decoded based on the headers.
	 *
	 * @since 2.8.0
	 *
	 * @param array|string $headers All of the available headers.
	 * @return bool
	 */
	public static function should_decode($headers) {
		if ( is_array( $headers ) ) {
			if ( array_key_exists('content-encoding', $headers) && ! empty( $headers['content-encoding'] ) )
				return true;
		} else if ( is_string( $headers ) ) {
			return ( stripos($headers, 'content-encoding:') !== false );
		}

		return false;
	}

	/**
	 * Whether decompression and compression are supported by the PHP version.
	 *
	 * Each function is tested instead of checking for the zlib extension, to
	 * ensure that the functions all exist in the PHP version and aren't
	 * disabled.
	 *
	 * @since 2.8.0
	 *
	 * @return bool
	 */
	public static function is_available() {
		return ( function_exists('gzuncompress') || function_exists('gzdeflate') || function_exists('gzinflate') );
	}
}
