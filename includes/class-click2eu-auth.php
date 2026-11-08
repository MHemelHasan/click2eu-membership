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
		$layout = get_option( 'cariera_login_register_layout' );
		$page   = absint( apply_filters( 'cariera_login_register_page', get_option( 'cariera_login_register_page' ) ) );

		if ( 'popup' !== $layout && $page ) {
			return get_permalink( $page );
		}

		// The popup is available on public theme pages; Join Us is a stable landing page for it.
		return add_query_arg( 'click2eu_auth', '1', Click2EU_Config::join_url() ) . '#login-register-popup';
	}

	public static function my_orders_url() {
		return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : home_url( '/my-account/orders/' );
	}
}
