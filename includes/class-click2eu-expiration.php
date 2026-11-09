<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Expiration {
	const ACTION_HOOK    = 'click2eu_expire_membership';
	const START_HOOK     = 'click2eu_start_membership';
	const RECONCILE_HOOK = 'click2eu_reconcile_memberships';
	const ACTION_GROUP   = 'click2eu-membership';

	public function __construct() {
		add_action( self::ACTION_HOOK, array( __CLASS__, 'expire_membership' ) );
		add_action( self::START_HOOK, array( __CLASS__, 'start_scheduled_membership' ) );
		add_action( self::RECONCILE_HOOK, array( __CLASS__, 'reconcile_all' ) );
		add_action( 'init', array( __CLASS__, 'ensure_reconciliation_schedule' ), 30 );
	}

	public static function activate() {
		self::ensure_reconciliation_schedule();
		self::reconcile_all();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::RECONCILE_HOOK );
	}

	public static function ensure_reconciliation_schedule() {
		if ( ! wp_next_scheduled( self::RECONCILE_HOOK ) ) {
			wp_schedule_event( Click2EU_Time::now() + HOUR_IN_SECONDS, 'hourly', self::RECONCILE_HOOK );
		}
	}

	public static function schedule( $membership_id, $expires_at ) {
		$membership_id = absint( $membership_id );
		$timestamp     = Click2EU_Time::timestamp( $expires_at );
		if ( ! $membership_id || ! $timestamp ) {
			return false;
		}

		$args = array( 'membership_id' => $membership_id );
		if ( function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' ) ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::ACTION_HOOK, $args, self::ACTION_GROUP ) ) {
				return true;
			}
			return (bool) as_schedule_single_action( $timestamp, self::ACTION_HOOK, $args, self::ACTION_GROUP, true );
		}

		if ( ! wp_next_scheduled( self::ACTION_HOOK, $args ) ) {
			return wp_schedule_single_event( $timestamp, self::ACTION_HOOK, $args );
		}
		return true;
	}

	public static function unschedule( $membership_id ) {
		$args = array( 'membership_id' => absint( $membership_id ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, $args, self::ACTION_GROUP );
		}
		wp_clear_scheduled_hook( self::ACTION_HOOK, $args );
	}

	public static function schedule_start( $membership_id, $starts_at ) {
		$membership_id = absint( $membership_id );
		$timestamp     = Click2EU_Time::timestamp( $starts_at );
		$args          = array( 'membership_id' => $membership_id );
		if ( ! $membership_id || ! $timestamp ) {
			return false;
		}
		if ( function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' ) ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::START_HOOK, $args, self::ACTION_GROUP ) ) {
				return true;
			}
			return (bool) as_schedule_single_action( $timestamp, self::START_HOOK, $args, self::ACTION_GROUP, true );
		}
		return wp_next_scheduled( self::START_HOOK, $args ) ? true : wp_schedule_single_event( $timestamp, self::START_HOOK, $args );
	}

	public static function unschedule_start( $membership_id ) {
		$args = array( 'membership_id' => absint( $membership_id ) );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::START_HOOK, $args, self::ACTION_GROUP );
		}
		wp_clear_scheduled_hook( self::START_HOOK, $args );
	}

	public static function is_effectively_active( $membership, $timestamp = null ) {
		if ( ! $membership || 'active' !== $membership->status ) {
			return false;
		}
		$expires = Click2EU_Time::timestamp( $membership->expires_at ?? null );
		if ( ! $expires ) {
			return true;
		}
		$timestamp = null === $timestamp ? Click2EU_Time::now() : (int) $timestamp;
		return $timestamp < $expires;
	}

	public static function remaining_days( $membership, $timestamp = null ) {
		if ( ! self::is_effectively_active( $membership, $timestamp ) ) {
			return 0;
		}
		$expires = Click2EU_Time::timestamp( $membership->expires_at ?? null );
		if ( ! $expires ) {
			return null;
		}
		$timestamp = null === $timestamp ? Click2EU_Time::now() : (int) $timestamp;
		return max( 0, (int) ceil( ( $expires - $timestamp ) / DAY_IN_SECONDS ) );
	}

	public static function reconcile_user( $user_id ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s",
				absint( $user_id ),
				Click2EU_Time::mysql()
			)
		);
		foreach ( $ids as $membership_id ) {
			self::expire_membership( $membership_id );
		}
		self::start_due_for_user( $user_id );
	}

	public static function reconcile_all() {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s ORDER BY membership_id ASC LIMIT 200",
				Click2EU_Time::mysql()
			)
		);
		foreach ( $ids as $membership_id ) {
			self::expire_membership( $membership_id );
		}

		$due_starts = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE status = 'scheduled' AND starts_at IS NOT NULL AND starts_at <= %s ORDER BY starts_at ASC LIMIT 200",
				Click2EU_Time::mysql()
			)
		);
		foreach ( $due_starts as $membership_id ) {
			self::start_scheduled_membership( $membership_id );
		}

		$future = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT membership_id, expires_at FROM ' . Click2EU_Memberships::table_name() . " WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at > %s ORDER BY expires_at ASC LIMIT 200",
				Click2EU_Time::mysql()
			)
		);
		foreach ( $future as $membership ) {
			self::schedule( $membership->membership_id, $membership->expires_at );
		}
		$future_starts = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT membership_id, starts_at FROM ' . Click2EU_Memberships::table_name() . " WHERE status = 'scheduled' AND starts_at > %s ORDER BY starts_at ASC LIMIT 200",
				Click2EU_Time::mysql()
			)
		);
		foreach ( $future_starts as $membership ) {
			self::schedule_start( $membership->membership_id, $membership->starts_at );
		}
	}

	public static function expire_membership( $membership_id ) {
		global $wpdb;
		$membership = Click2EU_Memberships::get_membership( $membership_id );
		if ( ! $membership || 'active' !== $membership->status || empty( $membership->expires_at ) ) {
			return false;
		}

		$lock_name = 'click2eu_membership_user_' . absint( $membership->candidate_user_id );
		$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
		if ( 1 !== $locked ) {
			return false;
		}

		try {
			$membership = Click2EU_Memberships::get_membership( $membership_id );
			if ( ! $membership || 'active' !== $membership->status || empty( $membership->expires_at ) || Click2EU_Time::now() < Click2EU_Time::timestamp( $membership->expires_at ) ) {
				return false;
			}
			$now = Click2EU_Time::mysql();
			$updated = $wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . Click2EU_Memberships::table_name() . " SET status = 'expired', expired_at = %s, updated_at = %s WHERE membership_id = %d AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s",
					$now,
					$now,
					absint( $membership_id ),
					$now
				)
			);
			if ( 1 === $updated ) {
				self::unschedule( $membership_id );
				self::start_due_locked( $membership->candidate_user_id );
				return true;
			}
			return false;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function start_due_for_user( $user_id ) {
		global $wpdb;
		$lock_name = 'click2eu_membership_user_' . absint( $user_id );
		$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
		if ( 1 !== $locked ) {
			return false;
		}
		try {
			return self::start_due_locked( $user_id );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function start_scheduled_membership( $membership_id ) {
		$membership = Click2EU_Memberships::get_membership( $membership_id );
		return $membership ? self::start_due_for_user( $membership->candidate_user_id ) : false;
	}

	private static function start_due_locked( $user_id ) {
		global $wpdb;
		$membership = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Click2EU_Memberships::table_name() . " WHERE candidate_user_id = %d AND status = 'scheduled' AND starts_at IS NOT NULL AND starts_at <= %s ORDER BY starts_at ASC, membership_id ASC LIMIT 1",
				absint( $user_id ), Click2EU_Time::mysql()
			)
		);
		if ( ! $membership ) {
			return false;
		}
		$due_active_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s",
				absint( $user_id ), Click2EU_Time::mysql()
			)
		);
		if ( $due_active_ids ) {
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . Click2EU_Memberships::table_name() . " SET status = 'expired', expired_at = %s, updated_at = %s WHERE candidate_user_id = %d AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s",
					Click2EU_Time::mysql(), Click2EU_Time::mysql(), absint( $user_id ), Click2EU_Time::mysql()
				)
			);
			foreach ( $due_active_ids as $due_id ) {
				self::unschedule( $due_id );
			}
		}
		$effective_active = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
				absint( $user_id ), Click2EU_Time::mysql()
			)
		);
		if ( $effective_active ) {
			return false;
		}
		$order = wc_get_order( absint( $membership->order_id ) );
		$item  = $order ? $order->get_item( absint( $membership->order_item_id ) ) : false;
		if ( ! $order || 'completed' !== $order->get_status() || (int) $order->get_user_id() !== (int) $user_id || ! $item instanceof WC_Order_Item_Product || (int) $item->get_product_id() !== (int) $membership->product_id ) {
			return false;
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . Click2EU_Memberships::table_name() . " SET status = 'active', activated_at = starts_at, updated_at = %s WHERE membership_id = %d AND status = 'scheduled'",
				Click2EU_Time::mysql(), absint( $membership->membership_id )
			)
		);
		if ( 1 === $updated ) {
			self::unschedule_start( $membership->membership_id );
			self::schedule( $membership->membership_id, $membership->expires_at );
			return true;
		}
		return false;
	}
}
