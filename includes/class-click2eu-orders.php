<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Orders {
	const ACTIVATION_CONFLICT_META    = '_click2eu_membership_activation_conflict';
	const ACTIVATION_CONFLICT_AT_META = '_click2eu_membership_activation_conflict_at';

	public function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart' ) );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'record_pending_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
	}

	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		$checked_id = $variation_id ? $variation_id : $product_id;
		if ( ! Click2EU_Config::is_membership_product( $checked_id ) ) {
			return $passed;
		}
		if ( ! is_user_logged_in() ) {
			wc_add_notice( __( 'Please register or sign in as a Candidate before choosing a membership.', 'click2eu-membership' ), 'error' );
			return false;
		}
		if ( ! Click2EU_Auth::is_candidate() ) {
			wc_add_notice( __( 'Candidate memberships can only be purchased by Candidate accounts.', 'click2eu-membership' ), 'error' );
			return false;
		}
		if ( Click2EU_Memberships::has_active_membership( get_current_user_id() ) ) {
			wc_add_notice( __( 'Your Candidate membership is already active.', 'click2eu-membership' ), 'error' );
			return false;
		}
		if ( empty( $cart_item_data['click2eu_category_id'] ) || empty( $cart_item_data['click2eu_package'] ) ) {
			wc_add_notice( __( 'Please choose a package and job category from the Join Us page.', 'click2eu-membership' ), 'error' );
			return false;
		}
		return $passed;
	}

	public function validate_cart() {
		if ( ! WC()->cart ) {
			return;
		}
		$membership_items = array_filter(
			WC()->cart->get_cart(),
			static function ( $item ) {
				return Click2EU_Config::is_membership_product( $item['product_id'] ?? 0 );
			}
		);
		if ( count( $membership_items ) > 1 ) {
			wc_add_notice( __( 'Only one Candidate membership package can be purchased per order.', 'click2eu-membership' ), 'error' );
		}
		if ( $membership_items && Click2EU_Memberships::has_active_membership( get_current_user_id() ) ) {
			wc_add_notice( __( 'Your Candidate membership is already active.', 'click2eu-membership' ), 'error' );
		}
		if ( $membership_items && Click2EU_Memberships::has_pending_membership( get_current_user_id() ) ) {
			wc_add_notice( __( 'You already have a membership order awaiting payment approval.', 'click2eu-membership' ), 'error' );
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! Click2EU_Config::is_membership_product( $item['product_id'] ?? 0 ) ) {
				continue;
			}
			if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
				wc_add_notice( __( 'A signed-in Candidate account is required for Candidate membership checkout.', 'click2eu-membership' ), 'error' );
			}
			$category_id = absint( $item['click2eu_category_id'] ?? 0 );
			$package     = sanitize_key( $item['click2eu_package'] ?? '' );
			$term        = $category_id ? get_term( $category_id, 'job_listing_category' ) : null;
			if ( Click2EU_Config::package_for_product( $item['product_id'] ?? 0 ) !== $package || ! $term || is_wp_error( $term ) ) {
				wc_add_notice( __( 'The membership package or job category is invalid. Please choose it again.', 'click2eu-membership' ), 'error' );
			}
		}
	}

	public function record_pending_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( ! $order || ! $order->get_user_id() || ! Click2EU_Auth::is_candidate( $order->get_user() ) ) {
			return;
		}

		$membership_items = array_filter(
			$order->get_items( 'line_item' ),
			static function ( $item ) {
				return $item instanceof WC_Order_Item_Product && Click2EU_Config::is_membership_product( $item->get_product_id() );
			}
		);
		if ( 1 !== count( $membership_items ) ) {
			return;
		}

		foreach ( $membership_items as $item ) {
			$package = Click2EU_Config::package_for_product( $item->get_product_id() );
			if ( false === $package ) {
				continue;
			}
			$category_id = absint( $item->get_meta( '_click2eu_category_id', true ) );
			$label       = sanitize_text_field( $item->get_meta( 'Job Category', true ) );
			$term        = $category_id ? get_term( $category_id, 'job_listing_category' ) : null;
			if ( ! $term || is_wp_error( $term ) || '' === $label ) {
				continue;
			}
			$membership_id = Click2EU_Memberships::create_pending( $order, $item, $package, $category_id, $label );
			if ( $membership_id ) {
				$order->update_meta_data( '_click2eu_membership_id', $membership_id );
				$order->save();
			}
		}
	}

	public function handle_status_change( $order_id, $from, $to, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$this->record_pending_order( $order );

		if ( in_array( $to, array( 'failed', 'cancelled', 'refunded' ), true ) ) {
			Click2EU_Memberships::suspend_order( $order_id );
			$order->update_meta_data( '_click2eu_membership_suspended', current_time( 'mysql', true ) );
			$order->add_order_note( __( 'Click2EU Candidate membership suspended after order reversal.', 'click2eu-membership' ) );
			$order->save();
			return;
		}
		if ( 'completed' !== $to || $order->get_meta( '_click2eu_membership_activated', true ) || $order->get_meta( '_click2eu_membership_suspended', true ) || self::has_activation_conflict( $order ) ) {
			return;
		}
		$user = $order->get_user();
		if ( ! $user || ! Click2EU_Auth::is_candidate( $user ) ) {
			return;
		}

		$membership = Click2EU_Memberships::get_membership_for_order( $order_id );
		if ( ! $membership || ! Click2EU_Config::is_membership_product( $membership->product_id ) || ! term_exists( absint( $membership->category_term_id ), 'job_listing_category' ) ) {
			return;
		}
		$conflicting_membership_id = 0;
		if ( Click2EU_Memberships::activate_membership( $membership->membership_id, $conflicting_membership_id ) ) {
			$order->update_meta_data( '_click2eu_membership_activated', absint( $membership->membership_id ) );
			$order->add_order_note( __( 'Click2EU Candidate membership activated.', 'click2eu-membership' ) );
			$order->save();
		} elseif ( $conflicting_membership_id ) {
			$order->update_meta_data( self::ACTIVATION_CONFLICT_META, $conflicting_membership_id );
			$order->update_meta_data( self::ACTIVATION_CONFLICT_AT_META, current_time( 'mysql', true ) );
			$order->add_order_note( __( 'Click2EU membership activation blocked: this Candidate already has a different active membership. Manual administrator review is required.', 'click2eu-membership' ) );
			$order->save();
		}
	}

	public static function has_activation_conflict( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		return $order && absint( $order->get_meta( self::ACTIVATION_CONFLICT_META, true ) ) > 0;
	}
}
