<?php
/**
 * Retrieves and creates the wp-config.php file.
 *
 * The permissions for the base directory must allow for writing files in order
 * for the wp-config.php to be created using this page.
 *
 * @internal This file must be parsable by PHP4.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * We are installing.
 */
define('WP_INSTALLING', true);

/**
 * We are blissfully unaware of anything.
 */
define('WP_SETUP_CONFIG', true);

/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging
 */
error_reporting(0);

define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/' );

require( ABSPATH . 'wp-settings.php' );

/** Load WordPress Administration Upgrade API */
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

/** Load WordPress Translation Install API */
require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );

nocache_headers();

// Support wp-config-sample.php one level up, for the develop repo.
if ( file_exists( ABSPATH . 'wp-config-sample.php' ) )
	$config_file = file( ABSPATH . 'wp-config-sample.php' );
elseif ( file_exists( dirname( ABSPATH ) . '/wp-config-sample.php' ) )
	$config_file = file( dirname( ABSPATH ) . '/wp-config-sample.php' );
else
	wp_die( __( 'Sorry, I need a wp-config-sample.php file to work from. Please re-upload this file from your WordPress installation.' ) );

var_dump( __FILE__ );
