<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Candidate-only, ownership-validated frontend payment status notice. */
final class Click2EU_Payment_Notice {
	const SHORTCODE = 'click2eu_payment_status_notice';

	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
	}

	public function shortcode() {
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return '';
		}

		// This shortcode contains account-specific data and must never be page-cached.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		$user_id = get_current_user_id();
		$active  = Click2EU_Category_Access::effective_membership( $user_id );
		if ( ! is_wp_error( $active ) ) {
			$order = $this->owned_membership_order( $active, $user_id );
			if ( ! $order || 'completed' !== $order->get_status() || Click2EU_Orders::has_activation_conflict( $order ) ) {
				return '';
			}

			$secondary = '';
			$renewal   = Click2EU_Memberships::get_renewal_for_user( $user_id, array( 'pending' ) );
			if ( $renewal ) {
				$renewal_order = $this->owned_membership_order( $renewal, $user_id );
				if ( $renewal_order && in_array( $renewal_order->get_status(), array( 'pending', 'on-hold', 'processing' ), true ) ) {
					$secondary = __( 'Your renewal or upgrade payment is awaiting verification. Your current membership remains active.', 'click2eu-membership' );
				}
			}

			return $this->render(
				'active',
				__( 'Your Membership Is Active', 'click2eu-membership' ),
				__( 'Your payment has been verified and your membership is now active. You can create and manage your resume from your Candidate Dashboard.', 'click2eu-membership' ),
				0,
				$secondary
			);
		}

		foreach ( Click2EU_Memberships::get_pending_initial_memberships( $user_id ) as $membership ) {
			$order = $this->owned_membership_order( $membership, $user_id );
			if ( ! $order ) {
				continue;
			}

			if ( 'on-hold' === $order->get_status() ) {
				return $this->render(
					'on-hold',
					__( 'Payment Verification in Progress', 'click2eu-membership' ),
					__( "Your membership order has been received and is awaiting payment verification. Once our team confirms your bank transfer, your membership will be activated and you'll be able to create your resume.", 'click2eu-membership' ),
					$order->get_order_number()
				);
			}

			if ( 'pending' === $order->get_status() ) {
				return $this->render(
					'pending',
					__( 'Payment Still Pending', 'click2eu-membership' ),
					__( "We haven't received your membership payment yet. Please complete your bank transfer using the payment instructions and send your payment confirmation via WhatsApp. Your membership will remain inactive until payment is verified.", 'click2eu-membership' )
				);
			}
		}

		return '';
	}

	private function owned_membership_order( $membership, $user_id ) {
		if ( ! $membership || (int) $membership->candidate_user_id !== (int) $user_id || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		$order = wc_get_order( absint( $membership->order_id ) );
		if ( ! $order || (int) $order->get_user_id() !== (int) $user_id ) {
			return false;
		}
		$item = $order->get_item( absint( $membership->order_item_id ) );
		if ( ! $item instanceof WC_Order_Item_Product || (int) $item->get_product_id() !== (int) $membership->product_id || ! Click2EU_Config::is_membership_product( $membership->product_id ) ) {
			return false;
		}
		return $order;
	}

	private function render( $state, $title, $message, $order_number = 0, $secondary = '' ) {
		$heading_id = wp_unique_id( 'c2eu-payment-notice-title-' );
		$url        = wc_get_account_endpoint_url( Click2EU_Dashboard::ENDPOINT );
		$html       = '<section class="c2eu-payment-notice c2eu-payment-notice--' . esc_attr( $state ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html      .= '<span class="c2eu-payment-notice__marker" aria-hidden="true"></span><div class="c2eu-payment-notice__body">';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '" class="c2eu-payment-notice__title">' . esc_html( $title ) . '</h2>';
		$html      .= '<p class="c2eu-payment-notice__message">' . esc_html( $message ) . '</p>';
		if ( $secondary ) {
			$html .= '<p class="c2eu-payment-notice__secondary">' . esc_html( $secondary ) . '</p>';
		}
		if ( $order_number ) {
			$html .= '<p class="c2eu-payment-notice__order">' . esc_html( sprintf( __( 'Order #%s', 'click2eu-membership' ), $order_number ) ) . '</p>';
		}
		$html .= '<a class="c2eu-payment-notice__link" href="' . esc_url( $url ) . '">' . esc_html__( 'View My Membership', 'click2eu-membership' ) . '</a>';
		$html .= '</div></section>';
		return $html;
	}
}
