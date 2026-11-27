<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Auth {
	public static function is_candidate( $user = null ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', absint( $user ) );
		} elseif ( ! ( $user instanceof WP_User ) ) {
			$user = wp_get_current_user();
		}
		return $user && $user->exists() && in_array( 'candidate', (array) $user->roles, true );
	}

	public static function is_employer( $user = null ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', absint( $user ) );
		} elseif ( ! ( $user instanceof WP_User ) ) {
			$user = wp_get_current_user();
		}
		return $user && $user->exists() && in_array( 'employer', (array) $user->roles, true );
	}

	/**
	 * Check if a user is an approved Employer.
	 *
	 * Fail-closed: returns true ONLY when the user exists, has the 'employer'
	 * role, and has explicitly verified/approved status.
	 *
	 * @param WP_User|int|null $user User object, ID, or null for current user.
	 * @return bool
	 */
	public static function is_approved_employer( $user = null ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', absint( $user ) );
		} elseif ( ! ( $user instanceof WP_User ) ) {
			$user = wp_get_current_user();
		}

		if ( ! $user || ! $user->exists() || ! in_array( 'employer', (array) $user->roles, true ) ) {
			return false;
		}

		// Stage 2 integration point: delegate to Employer Verification class if available.
		if ( class_exists( 'Click2EU_Employer_Verification' ) && method_exists( 'Click2EU_Employer_Verification', 'is_approved' ) ) {
			return (bool) Click2EU_Employer_Verification::is_approved( $user->ID );
		}

		$status = get_user_meta( $user->ID, '_click2eu_employer_verification_status', true );
		return 'approved' === $status;
	}

	public static function cariera_auth_url( $redirect_to = '' ) {
		$mapped = class_exists( 'Click2EU_Settings' ) ? Click2EU_Settings::page_id( 'login' ) : 0;
		$page   = absint( apply_filters( 'cariera_login_register_page', $mapped ?: get_option( 'cariera_login_register_page' ) ) );

		if ( $page && $page !== Click2EU_Config::join_page_id() && 'publish' === get_post_status( $page ) ) {
			$url = get_permalink( $page );
			if ( ! empty( $redirect_to ) ) {
				$url = add_query_arg( 'redirect_to', urlencode( (string) $redirect_to ), $url );
			}
			return $url;
		}

		// Never inject authentication forms into Join Us. A missing mapping falls
		// back to WordPress authentication with a validated, local continuation.
		$join = wp_validate_redirect( Click2EU_Config::join_url(), home_url( '/' ) );
		return wp_login_url( $redirect_to ?: $join );
	}

	public static function my_orders_url() {
		return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'orders' ) : home_url( '/my-account/orders/' );
	}
}
