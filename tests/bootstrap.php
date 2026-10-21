<?php
/**
 * PHPUnit bootstrap for Oli Cron Jobs.
 *
 * Loads a real WordPress install when WP_ROOT (or a local wordpress/ tree)
 * is available. Tests skip themselves when WordPress is not loaded.
 *
 * @package Oli_Cron_Jobs
 */

$oli_cj_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $oli_cj_autoload ) ) {
	require_once $oli_cj_autoload;
}

$oli_cj_wp_root = getenv( 'WP_ROOT' );
if ( ! $oli_cj_wp_root ) {
	$oli_cj_candidates = array(
		dirname( __DIR__ ) . '/wordpress',
		'/tmp/wordpress',
		getenv( 'WP_TESTS_DIR' ) ? dirname( getenv( 'WP_TESTS_DIR' ), 2 ) : '',
	);

	foreach ( $oli_cj_candidates as $oli_cj_candidate ) {
		if ( $oli_cj_candidate && file_exists( $oli_cj_candidate . '/wp-load.php' ) ) {
			$oli_cj_wp_root = $oli_cj_candidate;
			break;
		}
	}
}

if ( $oli_cj_wp_root && file_exists( $oli_cj_wp_root . '/wp-load.php' ) ) {
	define( 'WP_USE_THEMES', false );
	require_once $oli_cj_wp_root . '/wp-load.php';
}
