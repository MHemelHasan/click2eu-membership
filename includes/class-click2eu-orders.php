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
		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'enable_checkout_registration' ) );
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'require_checkout_registration' ) );
		add_filter( 'option_woocommerce_enable_checkout_login_reminder', array( $this, 'enable_checkout_login' ) );
		add_filter( 'option_woocommerce_registration_generate_password', array( $this, 'generate_checkout_password' ) );
		add_filter( 'woocommerce_new_customer_data', array( $this, 'candidate_customer_data' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_account' ), 10, 2 );
		add_filter( 'woocommerce_cancel_unpaid_order', array( $this, 'preserve_pending_bank_transfer_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'record_pending_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_status_change' ), 10, 4 );
	}

	/**
	 * A validated membership cart is the server-side boundary for checkout
	 * registration. Never infer Candidate intent from posted account fields.
	 */
	private function is_membership_checkout_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$membership_items = array_filter(
			WC()->cart->get_cart(),
			static function ( $cart_item ) {
				$product_id = absint( $cart_item['variation_id'] ?? 0 ) ?: absint( $cart_item['product_id'] ?? 0 );
				return Click2EU_Config::is_membership_product( $product_id );
			}
		);
		if ( 1 !== count( $membership_items ) ) {
			return false;
		}

		$item        = reset( $membership_items );
		$product_id  = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );
		$package     = sanitize_key( $item['click2eu_package'] ?? '' );
		$category_id = absint( $item['click2eu_category_id'] ?? 0 );
		$term        = $category_id ? get_term( $category_id, 'job_listing_category' ) : null;

		return Click2EU_Config::is_membership_product( $product_id )
			&& 1 === absint( $item['quantity'] ?? 0 )
			&& Click2EU_Config::package_for_product( $product_id ) === $package
			&& $term
			&& ! is_wp_error( $term )
			&& 'job_listing_category' === $term->taxonomy;
	}

	public function enable_checkout_registration( $enabled ) {
		return $this->is_membership_checkout_cart() ? true : $enabled;
	}

	public function require_checkout_registration( $required ) {
		return $this->is_membership_checkout_cart() ? true : $required;
	}

	public function enable_checkout_login( $value ) {
		return $this->is_membership_checkout_cart() ? 'yes' : $value;
	}

	public function generate_checkout_password( $value ) {
		return $this->is_membership_checkout_cart() ? 'yes' : $value;
	}

	public function candidate_customer_data( $customer_data ) {
		if ( ! is_user_logged_in() && $this->is_membership_checkout_cart() && get_role( 'candidate' ) ) {
			$customer_data['role'] = 'candidate';
		}
		return $customer_data;
	}

	public function validate_checkout_account( $data, $errors ) {
		if ( ! $this->is_membership_checkout_cart() || ! $errors instanceof WP_Error ) {
			return;
		}
		if ( is_user_logged_in() && ! Click2EU_Auth::is_candidate() ) {
			$errors->add( 'click2eu_candidate_required', __( 'Please sign in with a Candidate account to purchase a Candidate membership. This account has a different role.', 'click2eu-membership' ) );
			return;
		}
		if ( ! is_user_logged_in() && ! get_role( 'candidate' ) ) {
			$errors->add( 'click2eu_candidate_role_missing', __( 'Candidate registration is temporarily unavailable. Please contact support.', 'click2eu-membership' ) );
		}
	}

	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		$checked_id = $variation_id ? $variation_id : $product_id;
		if ( ! Click2EU_Config::is_membership_product( $checked_id ) ) {
			return $passed;
		}
		if ( is_user_logged_in() && ! Click2EU_Auth::is_candidate() ) {
			wc_add_notice( __( 'Candidate memberships can only be purchased by Candidate accounts.', 'click2eu-membership' ), 'error' );
			return false;
		}
		if ( is_user_logged_in() && empty( $cart_item_data['click2eu_renewal_of_membership_id'] ) ) {
			$existing_order = self::unresolved_initial_order_for_user( get_current_user_id() );
			if ( $existing_order ) {
				wc_add_notice( self::existing_order_message( $existing_order ), 'error' );
				return false;
			}
		}
		if ( is_user_logged_in() && Click2EU_Memberships::has_active_membership( get_current_user_id() ) ) {
			$active = Click2EU_Memberships::get_membership_for_user( get_current_user_id() );
			$is_upgrade = 'upgrade' === sanitize_key( $cart_item_data['click2eu_renewal_type'] ?? '' );
			$valid_renewal = $active && ! empty( $active->expires_at ) && absint( $cart_item_data['click2eu_renewal_of_membership_id'] ?? 0 ) === absint( $active->membership_id ) && absint( $active->category_term_id ) === absint( $cart_item_data['click2eu_category_id'] ?? 0 ) && ( absint( $active->product_id ) === absint( $checked_id ) || ( $is_upgrade && Click2EU_Config::package_for_product( $checked_id ) && in_array( Click2EU_Config::package_for_product( $checked_id ), Click2EU_Upgrades::allowed_targets( $active->package_key ), true ) ) );
			if ( ! $valid_renewal ) {
				wc_add_notice( __( 'Mid-term upgrades are unavailable. Renew using the same package and job category.', 'click2eu-membership' ), 'error' );
				return false;
			}
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
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! Click2EU_Config::is_membership_product( $item['product_id'] ?? 0 ) ) {
				continue;
			}
			if ( is_user_logged_in() && ! Click2EU_Auth::is_candidate() ) {
				wc_add_notice( __( 'A signed-in Candidate account is required for Candidate membership checkout.', 'click2eu-membership' ), 'error' );
			}
			$category_id = absint( $item['click2eu_category_id'] ?? 0 );
			$package     = sanitize_key( $item['click2eu_package'] ?? '' );
			$renewal_of  = absint( $item['click2eu_renewal_of_membership_id'] ?? 0 );
			$term        = $category_id ? get_term( $category_id, 'job_listing_category' ) : null;
			if ( Click2EU_Config::package_for_product( $item['product_id'] ?? 0 ) !== $package || ! $term || is_wp_error( $term ) ) {
				wc_add_notice( __( 'The membership package or job category is invalid. Please choose it again.', 'click2eu-membership' ), 'error' );
			}
			if ( is_user_logged_in() && ! $renewal_of ) {
				$existing_order = self::unresolved_initial_order_for_user( get_current_user_id() );
				if ( $existing_order && ! wc_has_notice( self::existing_order_message( $existing_order ), 'error' ) ) {
					wc_add_notice( self::existing_order_message( $existing_order ), 'error' );
				}
			} elseif ( is_user_logged_in() && $renewal_of && ( Click2EU_Memberships::has_pending_membership( get_current_user_id() ) || Click2EU_Memberships::has_scheduled_membership( get_current_user_id() ) ) ) {
				wc_add_notice( __( 'Resolve the existing membership order before starting another renewal or upgrade.', 'click2eu-membership' ), 'error' );
			}
			if ( is_user_logged_in() && Click2EU_Memberships::has_active_membership( get_current_user_id() ) ) {
				$active = Click2EU_Memberships::get_membership_for_user( get_current_user_id() );
				$is_upgrade = 'upgrade' === sanitize_key( $item['click2eu_renewal_type'] ?? '' );
				$valid_package = $package === $active->package_key || ( $is_upgrade && in_array( $package, Click2EU_Upgrades::allowed_targets( $active->package_key ), true ) );
				if ( ! $active || empty( $active->expires_at ) || absint( $item['click2eu_renewal_of_membership_id'] ?? 0 ) !== absint( $active->membership_id ) || ! $valid_package || $category_id !== absint( $active->category_term_id ) ) {
					wc_add_notice( __( 'Active memberships may renew only the same package and job category.', 'click2eu-membership' ), 'error' );
				}
			}
		}
	}

	/** Return an ownership-validated unresolved initial order, never a renewal or upgrade. */
	public static function unresolved_initial_order_for_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}
		foreach ( Click2EU_Memberships::get_pending_initial_memberships( $user_id ) as $membership ) {
			$order = wc_get_order( absint( $membership->order_id ) );
			if ( ! $order || (int) $order->get_user_id() !== $user_id || ! in_array( $order->get_status(), array( 'pending', 'on-hold', 'processing' ), true ) ) {
				continue;
			}
			$item = $order->get_item( absint( $membership->order_item_id ) );
			if ( $item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === (int) $membership->product_id && Click2EU_Config::is_membership_product( $membership->product_id ) ) {
				return $order;
			}
		}
		return false;
	}

	private static function existing_order_message( $order ) {
		$url = $order->get_view_order_url();
		if ( 'on-hold' === $order->get_status() ) {
			$message = __( 'Your membership order has already been placed. We are currently verifying your bank transfer. You do not need to place another order.', 'click2eu-membership' );
		} else {
			$message = __( 'You already have a membership order awaiting payment. Please complete your bank transfer using the payment instructions provided with your order.', 'click2eu-membership' );
		}
		return $url ? $message . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'View order', 'click2eu-membership' ) . '</a>' : $message;
	}

	/** Keep only validated Click2EU BACS orders out of WooCommerce's global pending-order timeout. */
	public function preserve_pending_bank_transfer_order( $should_cancel, $order ) {
		if ( ! $should_cancel || ! $order instanceof WC_Order || 'pending' !== $order->get_status() || 'bacs' !== $order->get_payment_method() ) {
			return $should_cancel;
		}
		$membership = Click2EU_Memberships::get_membership_for_order( $order->get_id() );
		if ( ! $membership || 'pending' !== $membership->status || (int) $membership->candidate_user_id !== (int) $order->get_user_id() ) {
			return $should_cancel;
		}
		$item = $order->get_item( absint( $membership->order_item_id ) );
		return $item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === (int) $membership->product_id && Click2EU_Config::is_membership_product( $membership->product_id ) ? false : $should_cancel;
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
			$duration    = absint( $item->get_meta( '_click2eu_duration_days', true ) );
			$renewal_of  = absint( $item->get_meta( '_click2eu_renewal_of_membership_id', true ) );
			$renewal_type = sanitize_key( $item->get_meta( '_click2eu_renewal_type', true ) );
			$term        = $category_id ? get_term( $category_id, 'job_listing_category' ) : null;
			if ( ! $term || is_wp_error( $term ) || '' === $label ) {
				continue;
			}
			$membership_id = Click2EU_Memberships::create_pending( $order, $item, $package, $category_id, $label, $duration, $renewal_of, $renewal_type );
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
			Click2EU_Upgrades::handle_source_reversal( $order_id );
			Click2EU_Memberships::suspend_order( $order_id );
			$order->update_meta_data( '_click2eu_membership_suspended', Click2EU_Time::mysql() );
			$order->add_order_note( __( 'Click2EU Candidate membership suspended after order reversal.', 'click2eu-membership' ) );
			$order->save();
			return;
		}
		if ( 'completed' !== $to || $order->get_meta( '_click2eu_membership_activated', true ) || $order->get_meta( '_click2eu_membership_scheduled', true ) || $order->get_meta( '_click2eu_membership_suspended', true ) || self::has_activation_conflict( $order ) ) {
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
		$activation_state = '';
		if ( 'upgrade' === $membership->renewal_type ) {
			if ( Click2EU_Upgrades::activate( $membership, $order, $conflicting_membership_id ) ) {
				return;
			}
			$order->update_meta_data( self::ACTIVATION_CONFLICT_META, $conflicting_membership_id ?: absint( $membership->renewal_of_membership_id ) );
			$order->update_meta_data( self::ACTIVATION_CONFLICT_AT_META, Click2EU_Time::mysql() );
			$order->update_meta_data( Click2EU_Upgrades::REVIEW_META, Click2EU_Time::mysql() );
			$order->add_order_note( __( 'Click2EU upgrade activation blocked because its frozen source, payment, ownership, or lifecycle assumptions changed. Manual administrator review is required.', 'click2eu-membership' ) );
			$order->save();
			do_action( 'click2eu_membership_admin_event', 'upgrade_review_required', $order->get_id(), $membership->membership_id, '' );
			return;
		}
		if ( Click2EU_Memberships::activate_membership( $membership->membership_id, $conflicting_membership_id, $order, $activation_state ) ) {
			if ( 'scheduled' === $activation_state ) {
				$order->update_meta_data( '_click2eu_membership_scheduled', absint( $membership->membership_id ) );
				$order->add_order_note( __( 'Click2EU renewal approved and scheduled to begin when the current membership expires.', 'click2eu-membership' ) );
			} else {
				$order->update_meta_data( '_click2eu_membership_activated', absint( $membership->membership_id ) );
				$order->add_order_note( __( 'Click2EU Candidate membership activated.', 'click2eu-membership' ) );
			}
			$order->save();
		} elseif ( $conflicting_membership_id ) {
			$order->update_meta_data( self::ACTIVATION_CONFLICT_META, $conflicting_membership_id );
			$order->update_meta_data( self::ACTIVATION_CONFLICT_AT_META, Click2EU_Time::mysql() );
			$order->add_order_note( __( 'Click2EU membership activation blocked: this Candidate already has a different active membership. Manual administrator review is required.', 'click2eu-membership' ) );
			$order->save();
			do_action( 'click2eu_membership_admin_event', 'activation_conflict', $order->get_id(), $membership->membership_id, '' );
		}
	}

	public static function has_activation_conflict( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		return $order && absint( $order->get_meta( self::ACTIVATION_CONFLICT_META, true ) ) > 0;
	}
}
