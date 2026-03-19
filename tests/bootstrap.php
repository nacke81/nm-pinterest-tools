<?php
/**
 * PHPUnit bootstrap file.
 *
 * Provides lightweight WordPress function stubs so the plugin's pure-logic
 * methods can be tested without loading a full WordPress environment.
 *
 * @package NM_Pinterest_Tools
 */

// Composer autoloader (PHPUnit, polyfills, etc.).
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define ABSPATH so the plugin files don't call exit().
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

/*
 * -------------------------------------------------------------------------
 * WordPress function stubs
 *
 * Only the functions that the *tested* code paths actually call are stubbed
 * here.  These are intentionally minimal — they just need to return sensible
 * values so the unit-testable logic can run.
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Stub: merge user args with defaults (mirrors WordPress behaviour).
	 *
	 * @param array|string $args     User arguments.
	 * @param array        $defaults Default values.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub: always returns the provided default.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub: lowercase, strip non-alphanumeric (except dashes/underscores).
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Stub: absolute integer.
	 *
	 * @param mixed $maybeint Value to convert.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Stub: format a timestamp using PHP's date().
	 *
	 * @param string   $format    Date format.
	 * @param int|null $timestamp Unix timestamp.
	 * @return string
	 */
	function wp_date( $format, $timestamp = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}
		return gmdate( $format, $timestamp );
	}
}

/*
 * -------------------------------------------------------------------------
 * Load plugin files (only the classes we test).
 * -------------------------------------------------------------------------
 */
require_once dirname( __DIR__ ) . '/includes/class-nm-pinterest-tools.php';
require_once dirname( __DIR__ ) . '/includes/class-nm-pinterest-tools-settings.php';
