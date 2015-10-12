<?php
/**
 * Main WordPress Formatting API.
 *
 * Handles many functions for formatting output.
 *
 * @package WordPress
 */

/**
 * Checks to see if a string is utf8 encoded.
 *
 * NOTE: This function checks for 5-Byte sequences, UTF8
 *       has Bytes Sequences with a maximum length of 4.
 *
 * @author bmorel at ssi dot fr (modified)
 * @since 1.2.1
 *
 * @param string $str The string to be checked
 * @return bool True if $str fits a UTF-8 model, false otherwise.
 */
function seems_utf8($str) {
	mbstring_binary_safe_encoding();
	$length = strlen($str);
	reset_mbstring_encoding();
	for ($i=0; $i < $length; $i++) {
		$c = ord($str[$i]);
		if ($c < 0x80) $n = 0; # 0bbbbbbb
		elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
		elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
		elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
		elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
		elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
		else return false; # Does not match any model
		for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
			if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
				return false;
		}
	}
	return true;
}

/**
 * Sanitizes a title, replacing whitespace and a few other characters with dashes.
 *
 * Limits the output to alphanumeric characters, underscore (_) and dash (-).
 * Whitespace becomes a dash.
 *
 * @since 1.2.0
 *
 * @param string $title The title to be sanitized.
 * @param string $raw_title Optional. Not used.
 * @param string $context Optional. The operation for which the string is sanitized.
 * @return string The sanitized title.
 */
function sanitize_title_with_dashes( $title, $raw_title = '', $context = 'display' ) {
	$title = strip_tags($title);
	// Preserve escaped octets.
	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
	// Remove percent signs that are not part of an octet.
	$title = str_replace('%', '', $title);
	// Restore octets.
	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

	if (seems_utf8($title)) {
		if (function_exists('mb_strtolower')) {
			$title = mb_strtolower($title, 'UTF-8');
		}
		$title = utf8_uri_encode($title, 200);
	}

	$title = strtolower($title);
	$title = preg_replace('/&.+?;/', '', $title); // kill entities
	$title = str_replace('.', '-', $title);

	if ( 'save' == $context ) {
		// Convert nbsp, ndash and mdash to hyphens
		$title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );

		// Strip these characters entirely
		$title = str_replace( array(
			// iexcl and iquest
			'%c2%a1', '%c2%bf',
			// angle quotes
			'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
			// curly quotes
			'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
			'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
			// copy, reg, deg, hellip and trade
			'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
			// acute accents
			'%c2%b4', '%cb%8a', '%cc%81', '%cd%81',
			// grave accent, macron, caron
			'%cc%80', '%cc%84', '%cc%8c',
		), '', $title );

		// Convert times to x
		$title = str_replace( '%c3%97', 'x', $title );
	}

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
	$title = preg_replace('/\s+/', '-', $title);
	$title = preg_replace('|-+|', '-', $title);
	$title = trim($title, '-');

	return $title;
}

/**
 * Sanitizes a string key.
 *
 * Keys are used as internal identifiers. Lowercase alphanumeric characters, dashes and underscores are allowed.
 *
 * @since 3.0.0
 *
 * @param string $key String key
 * @return string Sanitized key
 */
function sanitize_key( $key ) {
	$raw_key = $key;
	$key = strtolower( $key );
	$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

	/**
	 * Filter a sanitized key string.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key     Sanitized key.
	 * @param string $raw_key The key prior to sanitization.
	 */
	return apply_filters( 'sanitize_key', $key, $raw_key );
}
