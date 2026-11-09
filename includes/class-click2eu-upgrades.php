<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Secure, tax-disabled mid-term upgrade quotations and activation. */
final class Click2EU_Upgrades {
	const CALCULATION_VERSION = '1';
	const REVIEW_META = '_click2eu_upgrade_review_required';
	const CONSUMED_META = '_click2eu_upgrade_credit_consumed_by';
	const FINALIZED_META = '_click2eu_upgrade_finalized_at';
	const ZERO_APPROVED_META = '_click2eu_zero_upgrade_approved_at';
	const REVERSAL_FINGERPRINTS_META = '_click2eu_upgrade_reversal_fingerprints';
	const MAX_LINEAGE_NODES = 100;
	const RECOVERY_HOOK = 'click2eu_reconcile_incomplete_upgrades';
	const RECOVERY_GROUP = 'click2eu-membership';
	const RECOVERY_CURSOR_OPTION = 'click2eu_upgrade_recovery_cursor';
	const RECOVERY_BATCH_SIZE = 25;

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'process_request' ), 4 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_price' ), 20 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'reject_upgrade_coupon' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'copy_item_metadata' ), 20, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'copy_order_metadata' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'copy_order_metadata' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'copy_order_metadata' ), 20, 1 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'handle_refund_event' ), 10, 2 );
		add_action( 'woocommerce_order_partially_refunded', array( __CLASS__, 'handle_refund_event' ), 10, 2 );
		add_filter( 'woocommerce_order_actions', array( $this, 'order_actions' ), 20, 2 );
		add_action( 'woocommerce_order_action_click2eu_approve_zero_upgrade', array( $this, 'approve_zero_upgrade' ) );
		add_action( self::RECOVERY_HOOK, array( __CLASS__, 'reconcile_incomplete' ) );
		add_action( 'init', array( __CLASS__, 'ensure_recovery_schedule' ), 40 );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_quote' ), 20, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart_quote' ), 20 );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['click2eu_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => __( 'Every five minutes', 'click2eu-membership' ) );
		return $schedules;
	}

	public static function ensure_recovery_schedule() {
		if ( function_exists( 'as_schedule_recurring_action' ) && did_action( 'action_scheduler_init' ) ) {
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::RECOVERY_HOOK, array(), self::RECOVERY_GROUP ) ) {
				as_schedule_recurring_action( Click2EU_Time::now() + MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, self::RECOVERY_HOOK, array(), self::RECOVERY_GROUP, true );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::RECOVERY_HOOK ) ) {
			wp_schedule_event( Click2EU_Time::now() + MINUTE_IN_SECONDS, 'click2eu_five_minutes', self::RECOVERY_HOOK );
		}
	}

	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( self::RECOVERY_HOOK, array(), self::RECOVERY_GROUP ); }
		wp_clear_scheduled_hook( self::RECOVERY_HOOK );
	}

	public static function allowed_targets( $package ) {
		$tiers = array( 'basic', 'pro', 'premium', 'elite' );
		$index = array_search( sanitize_key( $package ), $tiers, true );
		return false === $index ? array() : array_slice( $tiers, $index + 1 );
	}

	public static function quote_ttl() {
		return max( MINUTE_IN_SECONDS, absint( apply_filters( 'click2eu_upgrade_quote_ttl', 15 * MINUTE_IN_SECONDS ) ) );
	}

	private static function quote_timestamp( $quote ) {
		return Click2EU_Time::timestamp( $quote['quoted_at'] ?? '' );
	}

	private static function quote_expiry_timestamp( $quote ) {
		$expiry = Click2EU_Time::timestamp( $quote['quote_expires_at'] ?? '' );
		return $expiry ?: self::quote_timestamp( $quote ) + self::quote_ttl();
	}

	private static function current_quote_inputs( $source, $target ) {
		if ( wc_tax_enabled() ) { return new WP_Error( 'tax_ambiguous', __( 'Automated upgrades are paused while WooCommerce taxes are enabled.', 'click2eu-membership' ) ); }
		$order = wc_get_order( absint( $source->order_id ) );
		$item  = $order ? $order->get_item( absint( $source->order_item_id ) ) : false;
		if ( ! $order || 'completed' !== $order->get_status() || $order->get_currency() !== get_woocommerce_currency() || (int) $order->get_user_id() !== (int) $source->candidate_user_id || ! $item instanceof WC_Order_Item_Product || (int) $item->get_product_id() !== (int) $source->product_id ) { return new WP_Error( 'source_payment', __( 'The original payment cannot be verified automatically.', 'click2eu-membership' ) ); }
		$item_refund = abs( (float) $order->get_total_refunded_for_item( $item->get_id() ) );
		if ( abs( (float) $order->get_total_refunded() ) > $item_refund + 0.000001 || (float) $item->get_total_tax() !== 0.0 || (float) $item->get_subtotal_tax() !== 0.0 ) { return new WP_Error( 'source_changed', __( 'The source payment or tax basis changed. Please request a new quote.', 'click2eu-membership' ) ); }
		$product_id = absint( Click2EU_Config::products()[ $target ] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product || ! is_numeric( $product->get_price() ) ) { return new WP_Error( 'target', __( 'The target package is unavailable.', 'click2eu-membership' ) ); }
		return array(
			'paid_basis' => wc_format_decimal( max( 0, min( (float) $item->get_total(), (float) $order->get_total() ) - $item_refund ), wc_get_price_decimals() ),
			'target_price' => wc_format_decimal( $product->get_price(), wc_get_price_decimals() ), 'currency' => $order->get_currency(),
			'target_category_id' => absint( $source->category_term_id ), 'target_product_id' => $product_id,
		);
	}

	private static function validate_quote_snapshot( $quote, $check_freshness = true ) {
		if ( ! is_array( $quote ) || empty( $quote['source_membership_id'] ) || empty( $quote['target_package'] ) ) {
			return new WP_Error( 'missing_quote', __( 'The upgrade quote is unavailable. Please request a new quote.', 'click2eu-membership' ) );
		}
		$quoted = self::quote_timestamp( $quote );
		if ( ! $quoted || ( $check_freshness && Click2EU_Time::now() > self::quote_expiry_timestamp( $quote ) ) ) {
			return new WP_Error( 'expired_quote', __( 'This upgrade quote expired. Please return to My Membership and request a new quote.', 'click2eu-membership' ) );
		}
		$source = Click2EU_Memberships::get_membership( absint( $quote['source_membership_id'] ) );
		if ( ! $source || 'active' !== $source->status || empty( $source->expires_at ) || Click2EU_Time::now() >= Click2EU_Time::timestamp( $source->expires_at ) ) {
			return new WP_Error( 'source_changed', __( 'The source membership changed or expired. Please request a new quote.', 'click2eu-membership' ) );
		}
		$fresh = self::current_quote_inputs( $source, sanitize_key( $quote['target_package'] ) );
		if ( is_wp_error( $fresh ) ) { return $fresh; }
		foreach ( array( 'paid_basis', 'target_price', 'currency', 'target_category_id', 'target_product_id' ) as $key ) {
			if ( (string) ( $fresh[ $key ] ?? '' ) !== (string) ( $quote[ $key ] ?? '' ) ) {
				return new WP_Error( 'quote_changed', __( 'Upgrade pricing or payment details changed. Please request a new quote.', 'click2eu-membership' ) );
			}
		}
		return true;
	}

	public static function quote( $source, $target, $timestamp = null ) {
		if ( ! $source || 'active' !== $source->status || empty( $source->expires_at ) || ! in_array( $target, self::allowed_targets( $source->package_key ), true ) ) {
			return new WP_Error( 'ineligible', __( 'This membership is not eligible for an upgrade.', 'click2eu-membership' ) );
		}
		if ( wc_tax_enabled() ) {
			return new WP_Error( 'tax_ambiguous', __( 'Automated upgrades are paused while WooCommerce taxes are enabled.', 'click2eu-membership' ) );
		}
		if ( Click2EU_Memberships::has_pending_membership( $source->candidate_user_id ) || Click2EU_Memberships::has_scheduled_membership( $source->candidate_user_id ) ) {
			return new WP_Error( 'conflict', __( 'Resolve the existing membership order before upgrading.', 'click2eu-membership' ) );
		}
		$order = wc_get_order( absint( $source->order_id ) );
		$item  = $order ? $order->get_item( absint( $source->order_item_id ) ) : false;
		if ( ! $order || 'completed' !== $order->get_status() || (int) $order->get_user_id() !== (int) $source->candidate_user_id || ! $item instanceof WC_Order_Item_Product || (int) $item->get_product_id() !== (int) $source->product_id ) {
			return new WP_Error( 'source_payment', __( 'The original payment cannot be verified automatically.', 'click2eu-membership' ) );
		}
		$currency = $order->get_currency();
		if ( $currency !== get_woocommerce_currency() || (float) $item->get_total_tax() !== 0.0 || (float) $item->get_subtotal_tax() !== 0.0 ) {
			return new WP_Error( 'source_currency_tax', __( 'The original currency or tax basis is not eligible for automatic proration.', 'click2eu-membership' ) );
		}
		$item_refund  = abs( (float) $order->get_total_refunded_for_item( $item->get_id() ) );
		$total_refund = abs( (float) $order->get_total_refunded() );
		if ( $total_refund > $item_refund + 0.000001 ) {
			return new WP_Error( 'ambiguous_refund', __( 'An order-level refund requires administrator review before upgrade.', 'click2eu-membership' ) );
		}
		$paid_basis = max( 0, min( (float) $item->get_total(), (float) $order->get_total() ) - $item_refund );
		$product_id = absint( Click2EU_Config::products()[ $target ] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : false;
		$duration   = Click2EU_Config::duration_for_package( $target );
		if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $duration || ! is_numeric( $product->get_price() ) ) {
			return new WP_Error( 'target', __( 'The target package is unavailable.', 'click2eu-membership' ) );
		}
		$timestamp = null === $timestamp ? Click2EU_Time::now() : (int) $timestamp;
		$expires   = Click2EU_Time::timestamp( $source->expires_at );
		$original  = absint( $source->duration_days ) * DAY_IN_SECONDS;
		if ( ! $expires || ! $original || $timestamp >= $expires ) {
			return new WP_Error( 'expired', __( 'This membership has expired; use the normal purchase flow.', 'click2eu-membership' ) );
		}
		$remaining = max( 0, $expires - $timestamp );
		$credit    = min( $paid_basis, $paid_basis * $remaining / $original );
		$target_price = (float) $product->get_price();
		$credit    = (float) wc_format_decimal( $credit, wc_get_price_decimals() );
		$charge    = (float) wc_format_decimal( max( 0, $target_price - $credit ), wc_get_price_decimals() );
		return array(
			'source_membership_id' => absint( $source->membership_id ), 'source_order_id' => absint( $source->order_id ),
			'target_package' => sanitize_key( $target ), 'target_product_id' => $product_id,
			'target_category_id' => absint( $source->category_term_id ), 'target_category_label' => sanitize_text_field( $source->category_label ),
			'target_duration_days' => $duration, 'quoted_at' => Click2EU_Time::mysql( $timestamp ), 'quote_expires_at' => Click2EU_Time::mysql( $timestamp + self::quote_ttl() ),
			'paid_basis' => wc_format_decimal( $paid_basis, wc_get_price_decimals() ), 'credit_amount' => wc_format_decimal( $credit, wc_get_price_decimals() ),
			'target_price' => wc_format_decimal( $target_price, wc_get_price_decimals() ), 'final_charge' => wc_format_decimal( $charge, wc_get_price_decimals() ),
			'currency' => $currency, 'calculation_version' => self::CALCULATION_VERSION,
		);
	}

	public function process_request() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['click2eu_upgrade_submit'] ) || ! is_user_logged_in() ) {
			return;
		}
		$source_id = absint( wp_unslash( $_POST['source_membership_id'] ?? 0 ) );
		$target    = sanitize_key( wp_unslash( $_POST['target_package'] ?? '' ) );
		$nonce     = sanitize_text_field( wp_unslash( $_POST['click2eu_upgrade_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'click2eu_upgrade_' . $source_id . '_' . $target ) ) {
			wp_die( esc_html__( 'Your upgrade session expired.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}
		$source = Click2EU_Memberships::get_membership( $source_id );
		if ( ! $source || (int) $source->candidate_user_id !== get_current_user_id() || ! Click2EU_Auth::is_candidate() ) {
			wp_die( esc_html__( 'This upgrade is not available to your account.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}
		$quote = self::quote( $source, $target );
		if ( is_wp_error( $quote ) ) {
			wc_add_notice( $quote->get_error_message(), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( Click2EU_Dashboard::ENDPOINT ) ); exit;
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) { wc_load_cart(); }
		WC()->cart->empty_cart();
		$key = WC()->cart->add_to_cart( $quote['target_product_id'], 1, 0, array(), array(
			'click2eu_package' => $target, 'click2eu_category_id' => $quote['target_category_id'], 'click2eu_category_name' => $quote['target_category_label'],
			'click2eu_renewal_of_membership_id' => $source_id, 'click2eu_renewal_type' => 'upgrade', 'click2eu_upgrade_quote' => $quote,
		) );
		if ( ! $key ) { wc_add_notice( __( 'Could not start upgrade checkout.', 'click2eu-membership' ), 'error' ); wp_safe_redirect( wc_get_account_endpoint_url( Click2EU_Dashboard::ENDPOINT ) ); exit; }
		WC()->cart->calculate_totals(); WC()->cart->set_session();
		wp_safe_redirect( wc_get_checkout_url() ); exit;
	}

	public function apply_cart_price( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['click2eu_upgrade_quote']['final_charge'] ) || isset( $item['click2eu_upgrade_quote']['final_charge'] ) ) {
				$item['data']->set_price( (string) $item['click2eu_upgrade_quote']['final_charge'] );
			}
		}
	}

	public function reject_upgrade_coupon( $valid, $coupon ) {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) { if ( ! empty( $item['click2eu_upgrade_quote'] ) ) { return false; } }
		}
		return $valid;
	}

	public function validate_checkout_quote( $data, $errors ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! $errors instanceof WP_Error ) { return; }
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['click2eu_upgrade_quote'] ) ) { continue; }
			$result = self::validate_quote_snapshot( $item['click2eu_upgrade_quote'], true );
			if ( is_wp_error( $result ) ) { $errors->add( $result->get_error_code(), $result->get_error_message() ); }
		}
	}

	public function validate_cart_quote() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) { return; }
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['click2eu_upgrade_quote'] ) ) { continue; }
			$result = self::validate_quote_snapshot( $item['click2eu_upgrade_quote'], true );
			if ( is_wp_error( $result ) && ! wc_has_notice( $result->get_error_message(), 'error' ) ) { wc_add_notice( $result->get_error_message(), 'error' ); }
		}
	}

	private static function metadata_map( $quote ) {
		return array(
			'_click2eu_upgrade_from_membership_id' => $quote['source_membership_id'], '_click2eu_upgrade_target_package' => $quote['target_package'],
			'_click2eu_upgrade_target_category_id' => $quote['target_category_id'], '_click2eu_upgrade_original_order_id' => $quote['source_order_id'],
			'_click2eu_upgrade_quoted_at' => $quote['quoted_at'], '_click2eu_upgrade_quote_expires_at' => $quote['quote_expires_at'] ?? '', '_click2eu_upgrade_paid_basis' => $quote['paid_basis'],
			'_click2eu_upgrade_credit_amount' => $quote['credit_amount'], '_click2eu_upgrade_target_price' => $quote['target_price'],
			'_click2eu_upgrade_final_charge' => $quote['final_charge'], '_click2eu_upgrade_currency' => $quote['currency'],
			'_click2eu_upgrade_calculation_version' => $quote['calculation_version'],
		);
	}

	public function copy_item_metadata( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['click2eu_upgrade_quote'] ) ) { return; }
		foreach ( self::metadata_map( $values['click2eu_upgrade_quote'] ) as $key => $value ) { $item->update_meta_data( $key, $value ); }
	}

	public function copy_order_metadata( $order, $data = array() ) {
		if ( ! WC()->cart ) { return; }
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['click2eu_upgrade_quote'] ) ) { continue; }
			$quote_check = self::validate_quote_snapshot( $item['click2eu_upgrade_quote'], true );
			foreach ( self::metadata_map( $item['click2eu_upgrade_quote'] ) as $key => $value ) { $order->update_meta_data( $key, $value ); }
			foreach ( $order->get_items( 'line_item' ) as $order_item ) {
				if ( (int) $order_item->get_product_id() !== (int) $item['product_id'] ) { continue; }
				foreach ( self::metadata_map( $item['click2eu_upgrade_quote'] ) as $key => $value ) { $order_item->update_meta_data( $key, $value ); }
				$order_item->save();
			}
			if ( is_wp_error( $quote_check ) ) {
				$order->update_meta_data( '_click2eu_upgrade_quote_stale_at', Click2EU_Time::mysql() );
				$order->update_meta_data( self::REVIEW_META, Click2EU_Time::mysql() );
				$order->update_meta_data( '_click2eu_upgrade_state', 'review' );
				if ( ! $order->get_meta( '_click2eu_upgrade_stale_note', true ) ) {
					$order->update_meta_data( '_click2eu_upgrade_stale_note', Click2EU_Time::mysql() );
					$order->add_order_note( $quote_check->get_error_message() );
				}
			} else {
				$order->update_meta_data( '_click2eu_upgrade_state', 'pending' );
			}
			break;
		}
	}

	public static function is_upgrade_order( $order ) { return $order && 'upgrade' === $order->get_meta( '_click2eu_renewal_type', true ); }

	public function order_actions( $actions, $order ) {
		if ( $order instanceof WC_Order && self::is_upgrade_order( $order ) && (float) $order->get_total() <= 0 && ! $order->get_meta( self::ZERO_APPROVED_META, true ) ) {
			$actions['click2eu_approve_zero_upgrade'] = __( 'Approve zero-total Click2EU upgrade', 'click2eu-membership' );
		}
		return $actions;
	}

	public function approve_zero_upgrade( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'manage_woocommerce' ) || ! self::is_upgrade_order( $order ) || (float) $order->get_total() > 0 ) {
			return;
		}
		$order->update_meta_data( self::ZERO_APPROVED_META, Click2EU_Time::mysql() );
		$order->update_meta_data( '_click2eu_zero_upgrade_approved_by', get_current_user_id() );
		$order->add_order_note( __( 'Zero-total Click2EU upgrade explicitly approved by an administrator.', 'click2eu-membership' ) );
		$order->save();
		if ( 'completed' === $order->get_status() ) {
			$membership = Click2EU_Memberships::get_membership_for_order( $order->get_id() );
			$conflict   = 0;
			if ( $membership && self::activate( $membership, $order, $conflict ) ) {
				$order = wc_get_order( $order->get_id() );
				$order->delete_meta_data( Click2EU_Orders::ACTIVATION_CONFLICT_META );
				$order->delete_meta_data( Click2EU_Orders::ACTIVATION_CONFLICT_AT_META );
				$order->delete_meta_data( self::REVIEW_META );
				$order->save();
			}
		}
	}

	public static function handle_refund_event( $order_id, $refund_id = 0 ) {
		self::handle_source_reversal( $order_id, 'refund', $refund_id );
	}

	private static function source_basis_is_unchanged( $source, $order ) {
		$source_order = wc_get_order( absint( $source->order_id ) );
		$source_item  = $source_order ? $source_order->get_item( absint( $source->order_item_id ) ) : false;
		if ( ! $source_order || 'completed' !== $source_order->get_status() || ! $source_item instanceof WC_Order_Item_Product ) { return false; }
		$item_refund = abs( (float) $source_order->get_total_refunded_for_item( $source_item->get_id() ) );
		if ( abs( (float) $source_order->get_total_refunded() ) > $item_refund + 0.000001 ) { return false; }
		$current_basis = max( 0, min( (float) $source_item->get_total(), (float) $source_order->get_total() ) - $item_refund );
		return $source_order->get_currency() === $order->get_currency() && abs( $current_basis - (float) $order->get_meta( '_click2eu_upgrade_paid_basis', true ) ) < 0.000001;
	}

	private static function approval_snapshot_is_valid( $membership, $source, $order ) {
		if ( $order->get_meta( '_click2eu_upgrade_quote_stale_at', true ) ) { return false; }
		$quoted = Click2EU_Time::timestamp( $order->get_meta( '_click2eu_upgrade_quoted_at', true ) );
		$created = $order->get_date_created();
		$expires = Click2EU_Time::timestamp( $order->get_meta( '_click2eu_upgrade_quote_expires_at', true ) );
		if ( ! $expires ) { $expires = $quoted + self::quote_ttl(); }
		if ( ! $quoted || ! $created || $created->getTimestamp() > $expires || $order->get_currency() !== get_woocommerce_currency() ) { return false; }
		$product = wc_get_product( absint( $membership->product_id ) );
		if ( ! $product || ! is_numeric( $product->get_price() ) || abs( (float) $product->get_price() - (float) $order->get_meta( '_click2eu_upgrade_target_price', true ) ) > 0.000001 ) { return false; }
		$expires  = Click2EU_Time::timestamp( $source->expires_at );
		$original = absint( $source->duration_days ) * DAY_IN_SECONDS;
		if ( ! $expires || ! $original || Click2EU_Time::now() >= $expires ) { return false; }
		$current_credit = (float) wc_format_decimal( (float) $order->get_meta( '_click2eu_upgrade_paid_basis', true ) * max( 0, $expires - Click2EU_Time::now() ) / $original, wc_get_price_decimals() );
		$frozen_credit  = (float) $order->get_meta( '_click2eu_upgrade_credit_amount', true );
		$tolerance      = max( 0, (float) apply_filters( 'click2eu_upgrade_approval_credit_tolerance', 0.01, $order, $source ) );
		return $frozen_credit <= $current_credit + $tolerance;
	}

	public static function handle_source_reversal( $source_order_id, $event = 'status', $refund_id = 0 ) {
		global $wpdb;
		$source_order = wc_get_order( absint( $source_order_id ) );
		$refund_total = $source_order ? wc_format_decimal( abs( (float) $source_order->get_total_refunded() ), wc_get_price_decimals() ) : 'missing';
		$fingerprint  = (float) $refund_total > 0 ? 'refund-total:' . $refund_total : sanitize_key( $event ) . ':' . absint( $refund_id );
		$roots = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . Click2EU_Memberships::table_name() . ' WHERE order_id=%d ORDER BY membership_id ASC', absint( $source_order_id ) ) );
		foreach ( $roots as $root ) {
			$queue   = array( absint( $root->membership_id ) );
			$visited = array();
			$count   = 0;
			while ( $queue && $count < self::MAX_LINEAGE_NODES ) {
				$current_id = array_shift( $queue );
				if ( isset( $visited[ $current_id ] ) ) { continue; }
				$visited[ $current_id ] = true;
				++$count;
				$current = Click2EU_Memberships::get_membership( $current_id );
				if ( ! $current || (int) $current->candidate_user_id !== (int) $root->candidate_user_id ) { continue; }
				self::invalidate_lineage_member( $current, $root->membership_id, $fingerprint );
				$children = $wpdb->get_results( $wpdb->prepare( "SELECT membership_id,candidate_user_id FROM " . Click2EU_Memberships::table_name() . " WHERE renewal_of_membership_id=%d AND renewal_type='upgrade' ORDER BY membership_id ASC", $current_id ) );
				foreach ( $children as $child ) {
					if ( (int) $child->candidate_user_id !== (int) $root->candidate_user_id ) {
						self::note_lineage_problem( $source_order, $fingerprint, __( 'Cross-user upgrade lineage detected; foreign membership was not modified.', 'click2eu-membership' ) );
						continue;
					}
					if ( isset( $visited[ absint( $child->membership_id ) ] ) ) {
						self::note_lineage_problem( $source_order, $fingerprint, __( 'Cyclic upgrade lineage detected and contained.', 'click2eu-membership' ) );
						continue;
					}
					$queue[] = absint( $child->membership_id );
				}
			}
			if ( $queue ) {
				self::note_lineage_problem( $source_order, $fingerprint, __( 'Upgrade lineage safety limit reached; administrator review is required.', 'click2eu-membership' ) );
			}
		}
	}

	private static function invalidate_lineage_member( $membership, $root_id, $fingerprint ) {
		global $wpdb;
		$order = wc_get_order( absint( $membership->order_id ) );
		if ( $order ) {
			$fingerprints = array_filter( (array) $order->get_meta( self::REVERSAL_FINGERPRINTS_META, true ) );
			if ( ! in_array( $fingerprint, $fingerprints, true ) ) {
				$fingerprints[] = $fingerprint;
				$order->update_meta_data( self::REVERSAL_FINGERPRINTS_META, array_slice( $fingerprints, -20 ) );
				$order->update_meta_data( self::REVIEW_META, Click2EU_Time::mysql() );
				$order->add_order_note( sprintf( __( 'Click2EU payment-lineage reversal from membership #%1$d invalidated this upgrade chain. Previous memberships were not restored. Event: %2$s', 'click2eu-membership' ), absint( $root_id ), $fingerprint ) );
				$order->save();
				do_action( 'click2eu_membership_admin_event', 'invalid_payment_lineage', $order->get_id(), $membership->membership_id, $fingerprint );
			}
		}
		if ( ! in_array( $membership->status, array( 'active', 'pending', 'scheduled' ), true ) ) { return; }
		$lock_name = 'click2eu_membership_user_' . absint( $membership->candidate_user_id );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) ) ) { return; }
		try {
			$new_status = 'active' === $membership->status ? 'suspended' : 'cancelled';
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . Click2EU_Memberships::table_name() . " SET status=%s, updated_at=%s WHERE membership_id=%d AND status IN ('active','pending','scheduled')", $new_status, Click2EU_Time::mysql(), $membership->membership_id ) );
			Click2EU_Expiration::unschedule( $membership->membership_id );
			Click2EU_Expiration::unschedule_start( $membership->membership_id );
			if ( 1 === $updated ) { do_action( 'click2eu_membership_transition', absint( $membership->membership_id ), $membership->status, $new_status, 'payment_lineage_reversal' ); }
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	private static function note_lineage_problem( $order, $fingerprint, $message ) {
		if ( ! $order ) { return; }
		$key = '_click2eu_lineage_problem_' . md5( $fingerprint . $message );
		if ( $order->get_meta( $key, true ) ) { return; }
		$order->update_meta_data( $key, Click2EU_Time::mysql() );
		$order->update_meta_data( self::REVIEW_META, Click2EU_Time::mysql() );
		$order->add_order_note( $message );
		$order->save();
		do_action( 'click2eu_membership_admin_event', 'invalid_payment_lineage', $order->get_id(), 0, $fingerprint );
	}

	public static function activate( $membership, $order, &$conflict_id = 0 ) {
		global $wpdb;
		$conflict_id = 0;
		if ( ! $membership || ! $order || 'completed' !== $order->get_status() || 'upgrade' !== $membership->renewal_type ) { return false; }
		$source_id = absint( $order->get_meta( '_click2eu_upgrade_from_membership_id', true ) );
		$lock_name = 'click2eu_membership_user_' . absint( $membership->candidate_user_id );
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) ) ) { return false; }
		try {
			$membership = Click2EU_Memberships::get_membership( $membership->membership_id ); $source = Click2EU_Memberships::get_membership( $source_id );
			if ( $membership && 'active' === $membership->status && absint( $source->membership_id ?? 0 ) === $source_id && 'upgraded' === $source->status ) {
				return self::finalize_activation( $membership, $source, $order );
			}
			$source_order = $source ? wc_get_order( $source->order_id ) : false;
			$consumed = $source_order ? absint( $source_order->get_meta( self::CONSUMED_META, true ) ) : 0;
			$item = $membership ? $order->get_item( $membership->order_item_id ) : false;
			$zero_approved = (float) $order->get_total() > 0 || (bool) $order->get_meta( self::ZERO_APPROVED_META, true );
			$valid = $membership && $source && 'pending' === $membership->status && 'active' === $source->status && ! empty( $source->expires_at ) && Click2EU_Time::now() < Click2EU_Time::timestamp( $source->expires_at )
				&& (int) $source->candidate_user_id === (int) $membership->candidate_user_id && (int) $source->category_term_id === (int) $membership->category_term_id
				&& in_array( $membership->package_key, self::allowed_targets( $source->package_key ), true ) && (int) $order->get_user_id() === (int) $membership->candidate_user_id
				&& $item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === (int) $membership->product_id && $order->get_currency() === $order->get_meta( '_click2eu_upgrade_currency', true )
				&& self::CALCULATION_VERSION === $order->get_meta( '_click2eu_upgrade_calculation_version', true ) && ! wc_tax_enabled() && self::source_basis_is_unchanged( $source, $order ) && self::approval_snapshot_is_valid( $membership, $source, $order ) && ( ! $consumed || $consumed === (int) $order->get_id() )
				&& abs( (float) $order->get_total() - (float) $order->get_meta( '_click2eu_upgrade_final_charge', true ) ) < 0.000001 && $zero_approved;
			$other = $membership ? $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE candidate_user_id=%d AND status IN ('active','pending','scheduled') AND membership_id NOT IN (%d,%d) LIMIT 1", $membership->candidate_user_id, $membership->membership_id, $source_id ) ) : 0;
			if ( ! $valid || $other ) { $conflict_id = $source_id; return false; }
			$now = Click2EU_Time::mysql(); $expires = Click2EU_Time::mysql( Click2EU_Time::now() + absint( $membership->duration_days ) * DAY_IN_SECONDS );
			$wpdb->query( 'START TRANSACTION' );
			$old = $wpdb->query( $wpdb->prepare( 'UPDATE ' . Click2EU_Memberships::table_name() . " SET status='upgraded', updated_at=%s WHERE membership_id=%d AND status='active'", $now, $source_id ) );
			$new = $wpdb->query( $wpdb->prepare( 'UPDATE ' . Click2EU_Memberships::table_name() . " SET status='active', starts_at=%s, activated_at=%s, expires_at=%s, expired_at=NULL, updated_at=%s WHERE membership_id=%d AND status='pending'", $now, $now, $expires, $now, $membership->membership_id ) );
			if ( 1 !== $old || 1 !== $new ) { $wpdb->query( 'ROLLBACK' ); return false; }
			$wpdb->query( 'COMMIT' );
			$membership = Click2EU_Memberships::get_membership( $membership->membership_id );
			$source     = Click2EU_Memberships::get_membership( $source_id );
			return self::finalize_activation( $membership, $source, $order );
		} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); }
	}

	private static function finalize_activation( $membership, $source, $order ) {
		if ( ! $membership || ! $source || ! $order || 'active' !== $membership->status || 'upgraded' !== $source->status || (int) $membership->renewal_of_membership_id !== (int) $source->membership_id || (int) $membership->candidate_user_id !== (int) $source->candidate_user_id ) { return false; }
		if ( (float) $order->get_total() <= 0 && ! $order->get_meta( self::ZERO_APPROVED_META, true ) ) { return false; }
		if ( ! self::source_basis_is_unchanged( $source, $order ) ) {
			self::handle_source_reversal( $source->order_id, 'reconcile' );
			return false;
		}
		$source_order = wc_get_order( absint( $source->order_id ) );
		if ( ! $source_order ) { return false; }
		$consumed = absint( $source_order->get_meta( self::CONSUMED_META, true ) );
		if ( $consumed && $consumed !== (int) $order->get_id() ) { return false; }
		if ( apply_filters( 'click2eu_upgrade_fail_before_source_marker', false, $membership, $order ) ) { return false; }
		$source_order->update_meta_data( self::CONSUMED_META, $order->get_id() );
		$source_order->save();
		$source_order = wc_get_order( $source_order->get_id() );
		if ( (int) $source_order->get_meta( self::CONSUMED_META, true ) !== (int) $order->get_id() ) { return false; }
		if ( apply_filters( 'click2eu_upgrade_fail_before_schedule', false, $membership, $order ) ) { return false; }
		Click2EU_Expiration::unschedule( $source->membership_id );
		if ( ! Click2EU_Expiration::schedule( $membership->membership_id, $membership->expires_at ) ) { return false; }
		if ( apply_filters( 'click2eu_upgrade_fail_before_target_marker', false, $membership, $order ) ) { return false; }
		$order = wc_get_order( $order->get_id() );
		$already_finalized = (bool) $order->get_meta( self::FINALIZED_META, true );
		$order->update_meta_data( '_click2eu_upgrade_state', 'activated' );
		$order->update_meta_data( '_click2eu_membership_activated', $membership->membership_id );
		$order->update_meta_data( self::FINALIZED_META, Click2EU_Time::mysql() );
		$order->delete_meta_data( self::REVIEW_META );
		$order->delete_meta_data( Click2EU_Orders::ACTIVATION_CONFLICT_META );
		$order->delete_meta_data( Click2EU_Orders::ACTIVATION_CONFLICT_AT_META );
		if ( ! $already_finalized ) { $order->add_order_note( __( 'Click2EU mid-term upgrade activated; source membership retained as Upgraded history.', 'click2eu-membership' ) ); }
		$order->save();
		$order = wc_get_order( $order->get_id() );
		$finalized = (int) $order->get_meta( '_click2eu_membership_activated', true ) === (int) $membership->membership_id && (bool) $order->get_meta( self::FINALIZED_META, true );
		if ( $finalized && ! $already_finalized ) {
			do_action( 'click2eu_membership_transition', absint( $source->membership_id ), 'active', 'upgraded', 'upgrade_finalized' );
			do_action( 'click2eu_membership_transition', absint( $membership->membership_id ), 'pending', 'active', 'upgrade_finalized' );
		}
		return $finalized;
	}

	public static function reconcile_incomplete() {
		global $wpdb;
		$cursor = absint( get_option( self::RECOVERY_CURSOR_OPTION, 0 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . Click2EU_Memberships::table_name() . " WHERE renewal_type='upgrade' AND status='active' AND membership_id>%d ORDER BY membership_id ASC LIMIT %d", $cursor, self::RECOVERY_BATCH_SIZE ) );
		if ( ! $rows ) {
			if ( $cursor ) { update_option( self::RECOVERY_CURSOR_OPTION, 0, false ); }
			return 0;
		}
		$processed = 0;
		foreach ( $rows as $membership ) {
			$order = wc_get_order( absint( $membership->order_id ) );
			if ( $order && 'completed' === $order->get_status() && (float) $order->get_total() <= 0 && ! $order->get_meta( self::ZERO_APPROVED_META, true ) ) {
				$order->update_meta_data( self::REVIEW_META, Click2EU_Time::mysql() );
				$order->add_order_note( __( 'Zero-total upgrade lacked explicit administrator approval and was suspended for review.', 'click2eu-membership' ) );
				$order->save();
				do_action( 'click2eu_membership_admin_event', 'upgrade_review_required', $order->get_id(), $membership->membership_id, 'zero-total-unapproved' );
				Click2EU_Memberships::suspend_order( $order->get_id() );
			} elseif ( $order && 'completed' === $order->get_status() && ( ! $order->get_meta( self::FINALIZED_META, true ) || (int) $order->get_meta( '_click2eu_membership_activated', true ) !== (int) $membership->membership_id ) ) {
				$conflict = 0;
				self::activate( $membership, $order, $conflict );
			}
			update_option( self::RECOVERY_CURSOR_OPTION, absint( $membership->membership_id ), false );
			++$processed;
		}
		return $processed;
	}

	public static function render_for( $source ) {
		if ( ! $source || 'active' !== $source->status || empty( $source->expires_at ) ) { return ''; }
		$html = '<div class="c2eu-dashboard__section c2eu-dashboard__upgrades"><h3>' . esc_html__( 'Upgrade Membership', 'click2eu-membership' ) . '</h3>';
		foreach ( self::allowed_targets( $source->package_key ) as $target ) {
			$quote = self::quote( $source, $target ); if ( is_wp_error( $quote ) ) { continue; }
			$html .= '<article class="c2eu-dashboard__card"><h4>' . esc_html( ucfirst( $source->package_key ) . ' → ' . ucfirst( $target ) ) . '</h4><dl class="c2eu-dashboard__details">';
			$html .= '<div><dt>' . esc_html__( 'Verified payment basis', 'click2eu-membership' ) . '</dt><dd>' . wp_kses_post( wc_price( $quote['paid_basis'], array( 'currency' => $quote['currency'] ) ) ) . '</dd></div>';
			$html .= '<div><dt>' . esc_html__( 'Unused-time credit', 'click2eu-membership' ) . '</dt><dd>' . wp_kses_post( wc_price( $quote['credit_amount'], array( 'currency' => $quote['currency'] ) ) ) . '</dd></div>';
			$html .= '<div><dt>' . esc_html__( 'Target full duration', 'click2eu-membership' ) . '</dt><dd>' . esc_html( $quote['target_duration_days'] . ' days' ) . '</dd></div>';
			$html .= '<div><dt>' . esc_html__( 'Tax', 'click2eu-membership' ) . '</dt><dd>' . esc_html__( 'Not applicable', 'click2eu-membership' ) . '</dd></div>';
			$html .= '<div><dt>' . esc_html__( 'Final amount due', 'click2eu-membership' ) . '</dt><dd>' . wp_kses_post( wc_price( $quote['final_charge'], array( 'currency' => $quote['currency'] ) ) ) . '</dd></div></dl>';
			$html .= '<form method="post"><input type="hidden" name="click2eu_upgrade_submit" value="1"><input type="hidden" name="source_membership_id" value="' . esc_attr( $source->membership_id ) . '"><input type="hidden" name="target_package" value="' . esc_attr( $target ) . '">' . wp_nonce_field( 'click2eu_upgrade_' . $source->membership_id . '_' . $target, 'click2eu_upgrade_nonce', true, false ) . '<button class="c2eu-dashboard__button" type="submit">' . esc_html__( 'Upgrade', 'click2eu-membership' ) . '</button></form></article>';
		}
		return $html . '</div>';
	}
}
