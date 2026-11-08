<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Config {
	const VERSION           = '0.4.0';
	const DB_VERSION        = '1.0.0';
	const JOIN_PAGE_OPTION  = 'click2eu_join_page_id';
	const DB_VERSION_OPTION = 'click2eu_membership_db_version';

	public static function products() {
		$products = apply_filters(
			'click2eu_membership_products',
			array(
				'basic'   => 11868,
				'pro'     => 11869,
				'premium' => 11870,
				'elite'   => 11871,
			)
		);

		return is_array( $products ) ? array_map( 'absint', $products ) : array();
	}

	public static function package_for_product( $product_id ) {
		return array_search( absint( $product_id ), self::products(), true );
	}

	public static function is_membership_product( $product_id ) {
		return false !== self::package_for_product( $product_id );
	}

	public static function join_page_id() {
		return absint( apply_filters( 'click2eu_join_page_id', get_option( self::JOIN_PAGE_OPTION ) ) );
	}

	public static function join_url() {
		$page_id = self::join_page_id();
		return $page_id ? get_permalink( $page_id ) : home_url( '/join-us/' );
	}

	public static function candidate_dashboard_id() {
		return absint( get_option( 'resume_manager_candidate_dashboard_page_id' ) );
	}

	public static function submit_resume_id() {
		return absint( get_option( 'resume_manager_submit_resume_form_page_id' ) );
	}
}
