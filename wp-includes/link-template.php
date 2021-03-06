<?php
/**
 * WordPress Link Template Functions
 *
 * @package WordPress
 * @subpackage Template
 */






























































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































































/**
 * Retrieve the home url for the current site.
 *
 * Returns the 'home' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @uses get_home_url()
 *
 * @param  string $path   (optional) Path relative to the home url.
 * @param  string $scheme (optional) Scheme to give the home url context. Currently 'http', 'https', or 'relative'.
 * @return string Home url link with optional path appended.
*/
function home_url( $path = '', $scheme = null ) {
	return get_home_url( null, $path, $scheme );
}

/**
 * Retrieve the home url for a given site.
 *
 * Returns the 'home' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @param  int $blog_id   (optional) Blog ID. Defaults to current blog.
 * @param  string $path   (optional) Path relative to the home url.
 * @param  string $scheme (optional) Scheme to give the home url context. Currently 'http', 'https', or 'relative'.
 * @return string Home url link with optional path appended.
*/
function get_home_url( $blog_id = null, $path = '', $scheme = null ) {
	$orig_scheme = $scheme;

	if ( empty( $blog_id ) || !is_multisite() ) {
		$url = get_option( 'home' );
	} else {
		switch_to_blog( $blog_id );
		$url = get_option( 'home' );
		restore_current_blog();
	}

	if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) ) {
		if ( is_ssl() && ! is_admin() && 'wp-login.php' !== $GLOBALS['pagenow'] )
			$scheme = 'https';
		else
			$scheme = parse_url( $url, PHP_URL_SCHEME );
	}

	$url = set_url_scheme( $url, $scheme );

	if ( $path && is_string( $path ) )
		$url .= '/' . ltrim( $path, '/' );

	/**
	 * Filter the home URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $url         The complete home URL including scheme and path.
	 * @param string      $path        Path relative to the home URL. Blank string if no path is specified.
	 * @param string|null $orig_scheme Scheme to give the home URL context. Accepts 'http', 'https', 'relative' or null.
	 * @param int|null    $blog_id     Blog ID, or null for the current blog.
	 */
	return apply_filters( 'home_url', $url, $path, $orig_scheme, $blog_id );
}

/**
 * Retrieve the site url for the current site.
 *
 * Returns the 'site_url' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @uses get_site_url()
 *
 * @param string $path Optional. Path relative to the site url.
 * @param string $scheme Optional. Scheme to give the site url context. See set_url_scheme().
 * @return string Site url link with optional path appended.
*/
function site_url( $path = '', $scheme = null ) {
	return get_site_url( null, $path, $scheme );
}

/**
 * Retrieve the site url for a given site.
 *
 * Returns the 'site_url' option with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @param int $blog_id (optional) Blog ID. Defaults to current blog.
 * @param string $path Optional. Path relative to the site url.
 * @param string $scheme Optional. Scheme to give the site url context. Currently 'http', 'https', 'login', 'login_post', 'admin', or 'relative'.
 * @return string Site url link with optional path appended.
*/
function get_site_url( $blog_id = null, $path = '', $scheme = null ) {
	if ( empty( $blog_id ) || !is_multisite() ) {
		$url = get_option( 'siteurl' );
	} else {
		switch_to_blog( $blog_id );
		$url = get_option( 'siteurl' );
		restore_current_blog();
	}

	$url = set_url_scheme( $url, $scheme );

	if ( $path && is_string( $path ) )
		$url .= '/' . ltrim( $path, '/' );

	/**
	 * Filter the site URL.
	 *
	 * @since 2.7.0
	 *
	 * @param string      $url     The complete site URL including scheme and path.
	 * @param string      $path    Path relative to the site URL. Blank string if no path is specified.
	 * @param string|null $scheme  Scheme to give the site URL context. Accepts 'http', 'https', 'login',
	 *                             'login_post', 'admin', 'relative' or null.
	 * @param int|null    $blog_id Blog ID, or null for the current blog.
	 */
	return apply_filters( 'site_url', $url, $path, $scheme, $blog_id );
}

/**
 * Retrieve the url to the admin area for the current site.
 *
 * @since 2.6.0
 *
 * @param string $path Optional path relative to the admin url.
 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Admin url link with optional path appended.
*/
function admin_url( $path = '', $scheme = 'admin' ) {
	return get_admin_url( null, $path, $scheme );
}

/**
 * Retrieve the url to the admin area for a given site.
 *
 * @since 3.0.0
 *
 * @param int $blog_id (optional) Blog ID. Defaults to current blog.
 * @param string $path Optional path relative to the admin url.
 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl(). 'http' or 'https' can be passed to force those schemes.
 * @return string Admin url link with optional path appended.
*/
function get_admin_url( $blog_id = null, $path = '', $scheme = 'admin' ) {
	$url = get_site_url($blog_id, 'wp-admin/', $scheme);

	if ( $path && is_string( $path ) )
		$url .= ltrim( $path, '/' );

	/**
	 * Filter the admin area URL.
	 *
	 * @since 2.8.0
	 *
	 * @param string   $url     The complete admin area URL including scheme and path.
	 * @param string   $path    Path relative to the admin area URL. Blank string if no path is specified.
	 * @param int|null $blog_id Blog ID, or null for the current blog.
	 */
	return apply_filters( 'admin_url', $url, $path, $blog_id );
}

/**
 * Retrieve the url to the includes directory.
 *
 * @since 2.6.0
 *
 * @param string $path Optional. Path relative to the includes url.
 * @param string $scheme Optional. Scheme to give the includes url context.
 * @return string Includes url link with optional path appended.
*/
function includes_url( $path = '', $scheme = null ) {
	$url = site_url( '/' . WPINC . '/', $scheme );

	if ( $path && is_string( $path ) )
		$url .= ltrim($path, '/');

	/**
	 * Filter the URL to the includes directory.
	 *
	 * @since 2.8.0
	 *
	 * @param string $url  The complete URL to the includes directory including scheme and path.
	 * @param string $path Path relative to the URL to the wp-includes directory. Blank string
	 *                     if no path is specified.
	 */
	return apply_filters( 'includes_url', $url, $path );
}























































































































/**
 * Retrieve the home url for the current network.
 *
 * Returns the home url with the appropriate protocol, 'https' if
 * is_ssl() and 'http' otherwise. If $scheme is 'http' or 'https', is_ssl() is
 * overridden.
 *
 * @since 3.0.0
 *
 * @param  string $path   (optional) Path relative to the home url.
 * @param  string $scheme (optional) Scheme to give the home url context. Currently 'http', 'https', or 'relative'.
 * @return string Home url link with optional path appended.
*/
function network_home_url( $path = '', $scheme = null ) {
	if ( ! is_multisite() )
		return home_url($path, $scheme);

	$current_site = get_current_site();
	$orig_scheme = $scheme;

	if ( ! in_array( $scheme, array( 'http', 'https', 'relative' ) ) )
		$scheme = is_ssl() && ! is_admin() ? 'https' : 'http';

	if ( 'relative' == $scheme )
		$url = $current_site->path;
	else
		$url = set_url_scheme( 'http://' . $current_site->domain . $current_site->path, $scheme );

	if ( $path && is_string( $path ) )
		$url .= ltrim( $path, '/' );

	/**
	 * Filter the network home URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $url         The complete network home URL including scheme and path.
	 * @param string      $path        Path relative to the network home URL. Blank string
	 *                                 if no path is specified.
	 * @param string|null $orig_scheme Scheme to give the URL context. Accepts 'http', 'https',
	 *                                 'relative' or null.
	 */
	return apply_filters( 'network_home_url', $url, $path, $orig_scheme);
}












































































/**
 * Set the scheme for a URL
 *
 * @since 3.4.0
 *
 * @param string $url Absolute url that includes a scheme
 * @param string $scheme Optional. Scheme to give $url. Currently 'http', 'https', 'login', 'login_post', 'admin', or 'relative'.
 * @return string $url URL with chosen scheme.
 */
function set_url_scheme( $url, $scheme = null ) {
	$orig_scheme = $scheme;

	if ( ! $scheme ) {
		$scheme = is_ssl() ? 'https' : 'http';
	} elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
		$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
	} elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
		$scheme = is_ssl() ? 'https' : 'http';
	}

	$url = trim( $url );
	if ( substr( $url, 0, 2 ) === '//' )
		$url = 'http:' . $url;

	if ( 'relative' == $scheme ) {
		$url = ltrim( preg_replace( '#^\w+://[^/]*#', '', $url ) );
		if ( $url !== '' && $url[0] === '/' )
			$url = '/' . ltrim($url , "/ \t\n\r\0\x0B" );
	} else {
		$url = preg_replace( '#^\w+://#', $scheme . '://', $url );
	}

	/**
	 * Filter the resulting URL after setting the scheme.
	 *
	 * @since 3.4.0
	 *
	 * @param string $url         The complete URL including scheme and path.
	 * @param string $scheme      Scheme applied to the URL. One of 'http', 'https', or 'relative'.
	 * @param string $orig_scheme Scheme requested for the URL. One of 'http', 'https', 'login',
	 *                            'login_post', 'admin', 'rpc', or 'relative'.
	 */
	return apply_filters( 'set_url_scheme', $url, $scheme, $orig_scheme );
}
