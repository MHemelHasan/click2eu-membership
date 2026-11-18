<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared Candidate-only, ownership-validated frontend membership notice service. */
final class Click2EU_Payment_Notice {
	const SHORTCODE             = 'click2eu_payment_status_notice';
	const DISMISS_ACTION        = 'click2eu_dismiss_active_notice';
	const DISMISSED_META        = 'click2eu_dismissed_activation_notice';
	const ACTIVE_WINDOW_SECONDS = DAY_IN_SECONDS;

	private static $rendered = false;

	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		add_action( 'template_redirect', array( $this, 'prevent_candidate_page_cache' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_dismissal' ), 4 );
	}

	public function prevent_candidate_page_cache() {
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return;
		}
		$page_ids = array_filter( array(
			Click2EU_Settings::page_id( 'join' ),
			Click2EU_Settings::page_id( 'my_profile' ),
			Click2EU_Settings::page_id( 'candidate_dashboard' ),
			Click2EU_Settings::page_id( 'my_account' ),
		) );
		if ( ( $page_ids && is_page( $page_ids ) ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			self::prevent_private_caching();
		}
	}

	public function shortcode() {
		return self::render_for_current_user();
	}

	public static function render_for_current_user() {
		if ( self::$rendered || ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return '';
		}
		self::$rendered = true;
		self::prevent_private_caching();

		$user_id = get_current_user_id();
		$state   = self::current_state( $user_id );
		if ( ! $state ) {
			return '';
		}

		if ( 'active' === $state['type'] ) {
			if ( self::active_success_is_visible( $state['membership'], $user_id ) ) {
				return self::render(
					'active',
					__( 'Your Membership Is Active', 'click2eu-membership' ),
					__( 'Your payment has been verified and your membership is now active. You can create and manage your resume from your Candidate Dashboard.', 'click2eu-membership' ),
					0,
					$state['continuation'] ? __( 'Your renewal or upgrade order is awaiting payment verification. Your current membership remains active.', 'click2eu-membership' ) : '',
					$state['membership']
				);
			}
			return $state['continuation'] ? self::render_continuation() : '';
		}

		if ( 'on-hold' === $state['type'] ) {
			return self::render(
				'on-hold',
				__( 'Payment Verification in Progress', 'click2eu-membership' ),
				__( "Your membership order has been received and is awaiting payment verification. Once our team confirms your bank transfer, your membership will be activated and you'll be able to create your resume.", 'click2eu-membership' ),
				$state['order']->get_order_number()
			);
		}

		if ( 'pending' === $state['type'] ) {
			return self::render(
				'pending',
				__( 'Payment Still Pending', 'click2eu-membership' ),
				__( "We haven't received your membership payment yet. Please complete your bank transfer using the payment instructions and send your payment confirmation via WhatsApp. Your membership will remain inactive until payment is verified.", 'click2eu-membership' )
			);
		}

		if ( 'expired' === $state['type'] ) {
			return self::render(
				'expired',
				__( 'Your Membership Has Expired', 'click2eu-membership' ),
				__( 'Your membership has expired. Please renew your membership to restore access to protected Candidate features, including resume creation and management.', 'click2eu-membership' )
			);
		}

		return '';
	}

	/** Trusted current state; active entitlement always wins over historical rows. */
	public static function current_state( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || $user_id !== get_current_user_id() || ! Click2EU_Auth::is_candidate() ) {
			return null;
		}

		$active = Click2EU_Category_Access::effective_membership( $user_id );
		if ( ! is_wp_error( $active ) ) {
			$order = self::owned_membership_order( $active, $user_id );
			if ( $order && 'completed' === $order->get_status() && ! Click2EU_Orders::has_activation_conflict( $order ) ) {
				return array(
					'type'         => 'active',
					'membership'   => $active,
					'order'        => $order,
					'continuation' => self::pending_continuation( $user_id ),
				);
			}
		}

		foreach ( Click2EU_Memberships::get_pending_initial_memberships( $user_id ) as $membership ) {
			$order = self::owned_membership_order( $membership, $user_id );
			if ( $order && in_array( $order->get_status(), array( 'on-hold', 'pending' ), true ) ) {
				return array( 'type' => $order->get_status(), 'membership' => $membership, 'order' => $order, 'continuation' => null );
			}
		}

		$expired = Click2EU_Memberships::get_latest_expired_membership_for_user( $user_id );
		$expires = $expired ? Click2EU_Time::timestamp( $expired->expires_at ?? null ) : 0;
		if ( $expired && $expires && $expires <= Click2EU_Time::now() ) {
			$order = self::owned_membership_order( $expired, $user_id );
			if ( $order && 'completed' === $order->get_status() ) {
				return array( 'type' => 'expired', 'membership' => $expired, 'order' => $order, 'continuation' => null );
			}
		}

		return null;
	}

	public static function handle_join_context_state() {
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return null;
		}
		self::prevent_private_caching();
		return self::current_state( get_current_user_id() );
	}

	public function handle_dismissal() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['click2eu_dismiss_active_notice'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return;
		}

		$user_id       = get_current_user_id();
		$membership_id = absint( wp_unslash( $_POST['click2eu_membership_id'] ?? 0 ) );
		$identity      = sanitize_text_field( wp_unslash( $_POST['click2eu_activation_identity'] ?? '' ) );
		$nonce         = sanitize_text_field( wp_unslash( $_POST['click2eu_dismiss_nonce'] ?? '' ) );
		$state         = self::current_state( $user_id );

		if ( $state && 'active' === $state['type'] && $membership_id === absint( $state['membership']->membership_id ) && self::active_success_is_visible( $state['membership'], $user_id ) ) {
			$expected = self::activation_identity( $state['membership'] );
			if ( $expected && hash_equals( $expected, $identity ) && wp_verify_nonce( $nonce, self::DISMISS_ACTION . '|' . $expected ) ) {
				update_user_meta( $user_id, self::DISMISSED_META, $expected );
			}
		}

		$redirect = wp_get_referer();
		wp_safe_redirect( $redirect ? $redirect : wc_get_account_endpoint_url( Click2EU_Dashboard::ENDPOINT ) );
		exit;
	}

	private static function pending_continuation( $user_id ) {
		$renewal = Click2EU_Memberships::get_renewal_for_user( $user_id, array( 'pending' ) );
		if ( ! $renewal ) {
			return null;
		}
		$order = self::owned_membership_order( $renewal, $user_id );
		return $order && in_array( $order->get_status(), array( 'pending', 'on-hold', 'processing' ), true )
			? array( 'membership' => $renewal, 'order' => $order )
			: null;
	}

	private static function owned_membership_order( $membership, $user_id ) {
		if ( ! $membership || (int) $membership->candidate_user_id !== (int) $user_id || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		$order = wc_get_order( absint( $membership->order_id ) );
		if ( ! $order || (int) $order->get_user_id() !== (int) $user_id ) {
			return false;
		}
		$item = $order->get_item( absint( $membership->order_item_id ) );
		return $item instanceof WC_Order_Item_Product
			&& (int) $item->get_product_id() === (int) $membership->product_id
			&& Click2EU_Config::is_membership_product( $membership->product_id ) ? $order : false;
	}

	private static function active_success_is_visible( $membership, $user_id ) {
		$activated = Click2EU_Time::timestamp( $membership->activated_at ?? null );
		$now       = Click2EU_Time::now();
		$identity  = self::activation_identity( $membership );
		if ( ! $activated || ! $identity || $activated > $now || ( $now - $activated ) >= self::ACTIVE_WINDOW_SECONDS ) {
			return false;
		}
		$dismissed = (string) get_user_meta( $user_id, self::DISMISSED_META, true );
		return ! $dismissed || ! hash_equals( $dismissed, $identity );
	}

	private static function activation_identity( $membership ) {
		$membership_id = absint( $membership->membership_id ?? 0 );
		$activated_at  = (string) ( $membership->activated_at ?? '' );
		return $membership_id && Click2EU_Time::timestamp( $activated_at )
			? hash( 'sha256', $membership_id . '|' . $activated_at )
			: '';
	}

	private static function render_continuation() {
		return self::render(
			'pending',
			__( 'Renewal or Upgrade Payment Pending', 'click2eu-membership' ),
			__( 'Your renewal or upgrade order is awaiting payment verification. Your current membership remains active.', 'click2eu-membership' )
		);
	}

	private static function render( $state, $title, $message, $order_number = 0, $secondary = '', $dismissible_membership = null ) {
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
		$html .= '<a class="c2eu-payment-notice__link" href="' . esc_url( $url ) . '">' . esc_html__( 'View My Membership', 'click2eu-membership' ) . '</a></div>';

		if ( $dismissible_membership ) {
			$identity = self::activation_identity( $dismissible_membership );
			$html .= '<form class="c2eu-payment-notice__dismiss" method="post" action="">';
			$html .= '<input type="hidden" name="click2eu_dismiss_active_notice" value="1">';
			$html .= '<input type="hidden" name="click2eu_membership_id" value="' . esc_attr( absint( $dismissible_membership->membership_id ) ) . '">';
			$html .= '<input type="hidden" name="click2eu_activation_identity" value="' . esc_attr( $identity ) . '">';
			$html .= wp_nonce_field( self::DISMISS_ACTION . '|' . $identity, 'click2eu_dismiss_nonce', true, false );
			$html .= '<button type="submit" class="c2eu-payment-notice__dismiss-button" aria-label="' . esc_attr__( 'Dismiss active membership notice', 'click2eu-membership' ) . '">&times;</button></form>';
		}

		return $html . '</section>';
	}

	private static function prevent_private_caching() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}
}
