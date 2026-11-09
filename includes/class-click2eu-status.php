<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Status {
	public function __construct() {
		add_shortcode( 'click2eu_membership_status', array( $this, 'shortcode' ) );
	}

	public function shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="c2eu-notice">' . esc_html__( 'Please sign in to view your membership status.', 'click2eu-membership' ) . '</div>';
		}
		if ( ! Click2EU_Auth::is_candidate() ) {
			return '<div class="c2eu-notice">' . esc_html__( 'Candidate membership status is available to Candidate accounts.', 'click2eu-membership' ) . '</div>';
		}
		$membership = Click2EU_Memberships::get_membership_for_user( get_current_user_id() );
		if ( ! $membership ) {
			return '<div class="c2eu-membership c2eu-membership-status"><p>' . esc_html__( 'No Candidate membership order was found.', 'click2eu-membership' ) . '</p><a class="c2eu-join-button" href="' . esc_url( Click2EU_Config::join_url() ) . '">' . esc_html__( 'View memberships', 'click2eu-membership' ) . '</a></div>';
		}
		$order = wc_get_order( $membership->order_id );
		if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
			return '<div class="c2eu-notice">' . esc_html__( 'Membership details are unavailable.', 'click2eu-membership' ) . '</div>';
		}
		$product = wc_get_product( $membership->product_id );
		$conflict = 'pending' === $membership->status && Click2EU_Orders::has_activation_conflict( $order );
		$status  = $conflict ? __( 'Activation review required', 'click2eu-membership' ) : ( 'active' === $membership->status ? __( 'Active', 'click2eu-membership' ) : ( 'pending' === $membership->status ? __( 'Pending payment approval', 'click2eu-membership' ) : ucfirst( $membership->status ) ) );
		$html    = '<div class="c2eu-membership c2eu-membership-status">';
		$html   .= '<h3>' . esc_html__( 'Candidate Membership', 'click2eu-membership' ) . '</h3><dl>';
		$html   .= '<dt>' . esc_html__( 'Status', 'click2eu-membership' ) . '</dt><dd>' . esc_html( $status ) . '</dd>';
		$html   .= '<dt>' . esc_html__( 'Package', 'click2eu-membership' ) . '</dt><dd>' . esc_html( $product ? $product->get_name() : ucfirst( $membership->package_key ) ) . '</dd>';
		$html   .= '<dt>' . esc_html__( 'Job Category', 'click2eu-membership' ) . '</dt><dd>' . esc_html( $membership->category_label ) . '</dd>';
		$html   .= '<dt>' . esc_html__( 'Order', 'click2eu-membership' ) . '</dt><dd>#' . esc_html( $order->get_order_number() ) . '</dd></dl>';
		if ( $conflict ) {
			$html .= '<p>' . esc_html__( 'Payment is complete. Membership activation requires administrator review.', 'click2eu-membership' ) . '</p>';
		} elseif ( 'pending' === $membership->status ) {
			$html .= '<p>' . esc_html__( 'Your order is awaiting administrator payment verification. Membership features remain locked until the order is completed.', 'click2eu-membership' ) . '</p>';
		}
		$html .= '<a class="c2eu-join-button" href="' . esc_url( Click2EU_Auth::my_orders_url() ) . '">' . esc_html__( 'View My Orders', 'click2eu-membership' ) . '</a></div>';
		return $html;
	}
}
