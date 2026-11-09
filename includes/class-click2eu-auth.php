<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Auth {
	public static function is_candidate( $user = null ) {
		$user = $user instanceof WP_User ? $user : wp_get_current_user();
		return $user->exists() && in_array( 'candidate', (array) $user->roles, true );
	}

	public static function is_employer( $user = null ) {
		$user = $user instanceof WP_User ? $user : wp_get_current_user();
		return $user->exists() && in_array( 'employer', (array) $user->roles, true );
	}

	public static function cariera_auth_url() {
		$mapped = class_exists( 'Click2EU_Settings' ) ? Click2EU_Settings::page_id( 'login' ) : 0;
		$page   = absint( apply_filters( 'cariera_login_register_page', $mapped ?: get_option( 'cariera_login_register_page' ) ) );

		if ( $page && $page !== Click2EU_Config::join_page_id() && 'publish' === get_post_status( $page ) ) {
			return get_permalink( $page );
		}

		// Never inject authentication forms into Join Us. A missing mapping falls
		// back to WordPress authentication with a validated, local continuation.
		$join = wp_validate_redirect( Click2EU_Config::join_url(), home_url( '/' ) );
		return wp_login_url( $join );
	}

	public static function my_orders_url() {
		return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : home_url( '/my-account/orders/' );
	}
}
