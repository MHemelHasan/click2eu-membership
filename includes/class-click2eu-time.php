<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** UTC clock with a filterable timestamp for deterministic tests. */
final class Click2EU_Time {
	public static function now() {
		$timestamp = apply_filters( 'click2eu_membership_now', time() );
		return is_numeric( $timestamp ) ? (int) $timestamp : time();
	}

	public static function mysql( $timestamp = null ) {
		return gmdate( 'Y-m-d H:i:s', null === $timestamp ? self::now() : (int) $timestamp );
	}

	public static function timestamp( $mysql_utc ) {
		if ( empty( $mysql_utc ) || '0000-00-00 00:00:00' === $mysql_utc ) {
			return 0;
		}
		$timestamp = strtotime( $mysql_utc . ' UTC' );
		return false === $timestamp ? 0 : $timestamp;
	}
}
