<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Persistent, deduplicated membership notification queue and diagnostics. */
final class Click2EU_Notifications {
	const ACTION_HOOK       = 'click2eu_deliver_notification';
	const RUNNER_HOOK       = 'click2eu_run_notification_queue';
	const CLEANUP_HOOK      = 'click2eu_cleanup_notifications';
	const ACTION_GROUP      = 'click2eu-membership';
	const PAGE_SLUG         = 'click2eu-notifications';
	const MAX_ATTEMPTS      = 3;
	const LEASE_SECONDS     = 300;
	const CUTOVER_OPTION    = 'click2eu_notifications_cutover_at';

	public function __construct() {
		add_action( 'click2eu_membership_created', array( __CLASS__, 'capture_created' ), 10, 1 );
		add_action( 'click2eu_membership_transition', array( __CLASS__, 'capture_transition' ), 10, 4 );
		add_action( 'click2eu_membership_admin_event', array( __CLASS__, 'capture_admin_event' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'capture_order_markers' ), 30, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'capture_order_markers' ), 30, 1 );
		add_action( self::ACTION_HOOK, array( __CLASS__, 'deliver' ), 10, 1 );
		add_action( self::RUNNER_HOOK, array( __CLASS__, 'run_queue' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cleanup_schedule' ), 45 );
		add_action( 'init', array( __CLASS__, 'ensure_runner_schedule' ), 46 );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_click2eu_retry_notification', array( $this, 'handle_admin_retry' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'click2eu_notifications';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			notification_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(191) NOT NULL,
			event_type varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_type varchar(32) NOT NULL,
			source_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			scheduled_at datetime NOT NULL,
			event_due_at datetime NULL,
			attempt_count smallint(5) unsigned NOT NULL DEFAULT 0,
			lease_until datetime NULL,
			last_attempt_at datetime NULL,
			sent_at datetime NULL,
			last_error varchar(500) NOT NULL DEFAULT '',
			review_note varchar(500) NOT NULL DEFAULT '',
			admin_retry_by bigint(20) unsigned NOT NULL DEFAULT 0,
			admin_retry_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (notification_id),
			UNIQUE KEY event_key (event_key),
			KEY queue_due (status,scheduled_at),
			KEY lease_recovery (status,lease_until),
			KEY retention (status,updated_at),
			KEY source (source_type,source_id),
			KEY user_event (user_id,event_type)
		) {$charset};";
		dbDelta( $sql );
		self::backfill_event_due_at();
		update_option( Click2EU_Config::NOTIFICATION_DB_VERSION_OPTION, Click2EU_Config::NOTIFICATION_DB_VERSION, false );
		if ( ! get_option( self::CUTOVER_OPTION ) ) {
			add_option( self::CUTOVER_OPTION, Click2EU_Time::mysql(), '', false );
		}
		self::ensure_cleanup_schedule();
		self::ensure_runner_schedule();
	}

	public static function maybe_install() {
		global $wpdb;
		$table_exists = self::table_name() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table_name() ) );
		if ( ! $table_exists || Click2EU_Config::NOTIFICATION_DB_VERSION !== get_option( Click2EU_Config::NOTIFICATION_DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	/** Backfill v0.7.1 rows without changing status, attempts or accepted history. */
	private static function backfill_event_due_at() {
		global $wpdb;
		$wpdb->query( 'UPDATE ' . self::table_name() . " SET event_due_at=scheduled_at WHERE event_due_at IS NULL AND event_type NOT LIKE 'membership_expiring_%'" );
		do {
			$rows = $wpdb->get_results( 'SELECT notification_id,event_type,source_id,scheduled_at FROM ' . self::table_name() . " WHERE event_due_at IS NULL AND event_type LIKE 'membership_expiring_%' ORDER BY notification_id ASC LIMIT 200" );
			foreach ( $rows as $row ) {
				$membership = Click2EU_Memberships::get_membership( $row->source_id );
				$days       = absint( substr( $row->event_type, strlen( 'membership_expiring_' ) ) );
				$due        = $membership && $days && ! empty( $membership->expires_at )
					? Click2EU_Time::mysql( Click2EU_Time::timestamp( $membership->expires_at ) - ( $days * DAY_IN_SECONDS ) )
					: $row->scheduled_at;
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . ' SET event_due_at=%s WHERE notification_id=%d AND event_due_at IS NULL', $due, $row->notification_id ) );
			}
		} while ( 200 === count( $rows ) );
	}

	public static function deactivate() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK, null, self::ACTION_GROUP );
			as_unschedule_all_actions( self::RUNNER_HOOK, array(), self::ACTION_GROUP );
			as_unschedule_all_actions( self::CLEANUP_HOOK, array(), self::ACTION_GROUP );
		}
		wp_unschedule_hook( self::ACTION_HOOK );
		wp_unschedule_hook( self::RUNNER_HOOK );
		wp_unschedule_hook( self::CLEANUP_HOOK );
	}

	public static function event_registry() {
		return array(
			'payment_pending'               => 'candidate',
			'membership_activated'          => 'candidate',
			'renewal_scheduled'             => 'candidate',
			'renewal_started'               => 'candidate',
			'upgrade_completed'             => 'candidate',
			'membership_expiring_7'         => 'candidate',
			'membership_expiring_3'         => 'candidate',
			'membership_expiring_1'         => 'candidate',
			'membership_expired'            => 'candidate',
			'membership_suspended'          => 'candidate',
			'membership_cancelled'          => 'candidate',
			'activation_conflict'           => 'admin',
			'upgrade_review_required'       => 'admin',
			'invalid_payment_lineage'       => 'admin',
			'notification_delivery_failure' => 'admin',
			'notification_delivery_uncertain' => 'admin',
		);
	}

	public static function capture_created( $membership_id ) {
		$membership = Click2EU_Memberships::get_membership( $membership_id );
		if ( $membership && 'pending' === $membership->status ) {
			self::queue( 'membership:' . absint( $membership_id ) . ':payment-pending', 'payment_pending', $membership->candidate_user_id, 'membership', $membership_id );
		}
	}

	public static function capture_transition( $membership_id, $from, $to, $context = '' ) {
		$membership = Click2EU_Memberships::get_membership( $membership_id );
		if ( ! $membership || $from === $to ) { return; }
		self::cancel_reminders( $membership_id );
		if ( 'active' === $to ) {
			if ( 'upgrade' === $membership->renewal_type ) {
				$type = 'upgrade_completed'; $suffix = 'upgrade-completed';
			} elseif ( 'scheduled' === $from || ! empty( $membership->renewal_of_membership_id ) ) {
				$type = 'renewal_started'; $suffix = 'renewal-started';
			} else {
				$type = 'membership_activated'; $suffix = 'activated';
			}
			self::queue( 'membership:' . absint( $membership_id ) . ':' . $suffix, $type, $membership->candidate_user_id, 'membership', $membership_id );
			self::schedule_reminders( $membership_id );
		} elseif ( 'scheduled' === $to ) {
			if ( $membership->renewal_of_membership_id ) { self::cancel_reminders( $membership->renewal_of_membership_id ); }
			self::queue( 'membership:' . absint( $membership_id ) . ':renewal-scheduled', 'renewal_scheduled', $membership->candidate_user_id, 'membership', $membership_id );
		} elseif ( 'expired' === $to ) {
			self::queue( 'membership:' . absint( $membership_id ) . ':expired', 'membership_expired', $membership->candidate_user_id, 'membership', $membership_id );
		} elseif ( 'suspended' === $to ) {
			self::queue( 'membership:' . absint( $membership_id ) . ':suspended', 'membership_suspended', $membership->candidate_user_id, 'membership', $membership_id );
		} elseif ( 'cancelled' === $to ) {
			self::queue( 'membership:' . absint( $membership_id ) . ':cancelled', 'membership_cancelled', $membership->candidate_user_id, 'membership', $membership_id );
		}
		if ( in_array( $to, array( 'suspended', 'cancelled' ), true ) && $membership->renewal_of_membership_id ) {
			self::schedule_reminders( $membership->renewal_of_membership_id );
		}
	}

	public static function capture_admin_event( $event_type, $order_id, $membership_id = 0, $fingerprint = '' ) {
		$event_type = sanitize_key( $event_type );
		if ( ! isset( self::event_registry()[ $event_type ] ) || 'admin' !== self::event_registry()[ $event_type ] ) { return; }
		$suffix = $fingerprint ? ':' . substr( hash( 'sha256', (string) $fingerprint ), 0, 16 ) : '';
		$key    = 'order:' . absint( $order_id ) . ':' . str_replace( '_', '-', $event_type ) . $suffix;
		self::queue( $key, $event_type, 0, 'order', $order_id );
	}

	public static function capture_order_markers( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) { return; }
		$fingerprints = array_filter( (array) $order->get_meta( Click2EU_Upgrades::REVERSAL_FINGERPRINTS_META, true ) );
		foreach ( $fingerprints as $fingerprint ) {
			self::capture_admin_event( 'invalid_payment_lineage', $order->get_id(), 0, $fingerprint );
		}
		if ( $fingerprints ) { return; }
		if ( $order->get_meta( Click2EU_Upgrades::REVIEW_META, true ) ) {
			self::capture_admin_event( 'upgrade_review_required', $order->get_id(), absint( $order->get_meta( '_click2eu_membership_id', true ) ) );
		} elseif ( Click2EU_Orders::has_activation_conflict( $order ) ) {
			self::capture_admin_event( 'activation_conflict', $order->get_id(), absint( $order->get_meta( '_click2eu_membership_id', true ) ) );
		}
	}

	public static function queue( $event_key, $event_type, $user_id, $source_type, $source_id, $scheduled_timestamp = null ) {
		global $wpdb;
		$event_key   = sanitize_text_field( $event_key );
		$event_type  = sanitize_key( $event_type );
		$source_type = sanitize_key( $source_type );
		$registry    = self::event_registry();
		if ( ! $event_key || strlen( $event_key ) > 191 || ! isset( $registry[ $event_type ] ) || ! in_array( $source_type, array( 'membership', 'order', 'notification' ), true ) || ! absint( $source_id ) ) { return 0; }
		$scheduled_timestamp = null === $scheduled_timestamp ? Click2EU_Time::now() : (int) $scheduled_timestamp;
		$now = Click2EU_Time::mysql();
		$event_due_at = Click2EU_Time::mysql( $scheduled_timestamp );
		$ok = $wpdb->query( $wpdb->prepare(
			'INSERT IGNORE INTO ' . self::table_name() . ' (event_key,event_type,user_id,source_type,source_id,status,scheduled_at,event_due_at,created_at,updated_at) VALUES (%s,%s,%d,%s,%d,%s,%s,%s,%s,%s)',
			$event_key, $event_type, absint( $user_id ), $source_type, absint( $source_id ), 'queued',
			Click2EU_Time::mysql( max( Click2EU_Time::now(), $scheduled_timestamp ) ), $event_due_at, $now, $now
		) );
		if ( 1 !== $ok ) {
			$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE event_key = %s', $event_key ) );
			if ( $existing && 'cancelled' === $existing->status && 0 === strpos( $event_type, 'membership_expiring_' ) && $scheduled_timestamp > Click2EU_Time::now() ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . " SET status='queued',scheduled_at=%s,event_due_at=COALESCE(event_due_at,%s),attempt_count=0,lease_until=NULL,last_error='',updated_at=%s WHERE notification_id=%d AND status='cancelled'", Click2EU_Time::mysql( $scheduled_timestamp ), $event_due_at, $now, $existing->notification_id ) );
				self::schedule_delivery( $existing->notification_id, $scheduled_timestamp );
			}
			return $existing ? absint( $existing->notification_id ) : 0;
		}
		$notification_id = absint( $wpdb->insert_id );
		self::schedule_delivery( $notification_id, max( Click2EU_Time::now() + 1, $scheduled_timestamp ) );
		return $notification_id;
	}

	public static function schedule_reminders( $membership_id ) {
		$membership = Click2EU_Memberships::get_membership( $membership_id );
		if ( ! $membership || 'active' !== $membership->status || empty( $membership->expires_at ) ) { return; }
		$expiry = Click2EU_Time::timestamp( $membership->expires_at );
		foreach ( array( 7, 3, 1 ) as $days ) {
			$when = $expiry - ( $days * DAY_IN_SECONDS );
			if ( $when <= Click2EU_Time::now() ) { continue; }
			self::queue( 'membership:' . absint( $membership_id ) . ':expiry-warning:' . $days . 'd', 'membership_expiring_' . $days, $membership->candidate_user_id, 'membership', $membership_id, $when );
		}
	}

	public static function cancel_reminders( $membership_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE " . self::table_name() . " SET status='cancelled', updated_at=%s WHERE source_type='membership' AND source_id=%d AND event_type LIKE 'membership_expiring_%%' AND status='queued'", Click2EU_Time::mysql(), absint( $membership_id ) ) );
	}

	private static function schedule_delivery( $notification_id, $timestamp ) {
		$args = array( 'notification_id' => absint( $notification_id ) );
		if ( self::use_action_scheduler() ) {
			as_schedule_single_action( max( Click2EU_Time::now() + 1, (int) $timestamp ), self::ACTION_HOOK, $args, self::ACTION_GROUP, true );
			return;
		}
		if ( ! wp_next_scheduled( self::ACTION_HOOK, $args ) ) {
			wp_schedule_single_event( max( Click2EU_Time::now() + 1, (int) $timestamp ), self::ACTION_HOOK, $args );
		}
	}

	public static function deliver( $notification_id ) {
		global $wpdb;
		$notification_id = absint( $notification_id );
		$now = Click2EU_Time::mysql();
		$lease = Click2EU_Time::mysql( Click2EU_Time::now() + self::LEASE_SECONDS );
		$claimed = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table_name() . " SET status='processing', lease_until=%s, attempt_count=attempt_count+1, last_attempt_at=%s, updated_at=%s WHERE notification_id=%d AND attempt_count<%d AND scheduled_at<=%s AND (status='queued' OR (status='processing' AND lease_until<%s))",
			$lease, $now, $now, $notification_id, self::MAX_ATTEMPTS, $now, $now
		) );
		if ( 1 !== $claimed ) { return false; }
		$notification = self::get( $notification_id );
		if ( ! $notification ) { return false; }
		$valid = self::validate( $notification );
		if ( is_wp_error( $valid ) ) {
			$wpdb->update( self::table_name(), array( 'status' => 'cancelled', 'lease_until' => null, 'last_error' => sanitize_text_field( $valid->get_error_message() ), 'updated_at' => $now ), array( 'notification_id' => $notification_id ), array( '%s', null, '%s', '%s' ), array( '%d' ) );
			return false;
		}
		$recipient = self::recipient( $notification );
		if ( ! is_email( $recipient ) ) {
			return self::delivery_failed( $notification, __( 'Recipient address is unavailable or invalid.', 'click2eu-membership' ) );
		}
		$subject = wp_strip_all_tags( '[' . get_bloginfo( 'name' ) . '] ' . Click2EU_Notification_Templates::event_title( $notification->event_type ) );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$accepted = wp_mail( $recipient, $subject, Click2EU_Notification_Templates::render_html( $notification ), $headers );
		if ( $accepted ) {
			$wpdb->update( self::table_name(), array( 'status' => 'accepted', 'lease_until' => null, 'sent_at' => Click2EU_Time::mysql(), 'last_error' => '', 'updated_at' => Click2EU_Time::mysql() ), array( 'notification_id' => $notification_id ), array( '%s', null, '%s', '%s', '%s' ), array( '%d' ) );
			return true;
		}
		return self::delivery_failed( $notification, __( 'WordPress mail transport did not accept the message.', 'click2eu-membership' ) );
	}

	private static function delivery_failed( $notification, $message ) {
		global $wpdb;
		$terminal = absint( $notification->attempt_count ) >= self::MAX_ATTEMPTS;
		if ( $terminal ) {
			$wpdb->update( self::table_name(), array( 'status' => 'failed', 'lease_until' => null, 'last_error' => sanitize_text_field( $message ), 'updated_at' => Click2EU_Time::mysql() ), array( 'notification_id' => $notification->notification_id ), array( '%s', null, '%s', '%s' ), array( '%d' ) );
			if ( 'notification_delivery_failure' !== $notification->event_type ) {
				self::queue( 'notification:' . absint( $notification->notification_id ) . ':delivery-failure', 'notification_delivery_failure', 0, 'notification', $notification->notification_id );
			}
			return false;
		}
		$delays = array( 1 => 5 * MINUTE_IN_SECONDS, 2 => 30 * MINUTE_IN_SECONDS, 3 => 2 * HOUR_IN_SECONDS );
		$next   = Click2EU_Time::now() + ( $delays[ absint( $notification->attempt_count ) ] ?? 2 * HOUR_IN_SECONDS );
		$wpdb->update( self::table_name(), array( 'status' => 'queued', 'lease_until' => null, 'scheduled_at' => Click2EU_Time::mysql( $next ), 'last_error' => sanitize_text_field( $message ), 'updated_at' => Click2EU_Time::mysql() ), array( 'notification_id' => $notification->notification_id ), array( '%s', null, '%s', '%s', '%s' ), array( '%d' ) );
		self::schedule_delivery( $notification->notification_id, $next );
		return false;
	}

	public static function validate( $notification ) {
		$type = $notification->event_type;
		if ( 'membership' === $notification->source_type ) {
			$membership = Click2EU_Memberships::get_membership( $notification->source_id );
			if ( ! $membership || (int) $membership->candidate_user_id !== (int) $notification->user_id ) { return new WP_Error( 'invalid_source', __( 'Membership source is unavailable.', 'click2eu-membership' ) ); }
			$order = wc_get_order( $membership->order_id );
			if ( ! $order || (int) $order->get_user_id() !== (int) $membership->candidate_user_id ) { return new WP_Error( 'invalid_order', __( 'Membership order ownership is unavailable.', 'click2eu-membership' ) ); }
			if ( 'payment_pending' === $type && 'pending' !== $membership->status ) { return new WP_Error( 'stale_event', __( 'Membership is no longer pending.', 'click2eu-membership' ) ); }
			if ( 'payment_pending' === $type ) {
				if ( in_array( $order->get_status(), array( 'completed', 'failed', 'cancelled', 'refunded' ), true ) ) { return new WP_Error( 'invalid_order', __( 'Pending payment order state is no longer applicable.', 'click2eu-membership' ) ); }
			}
			if ( 'membership_activated' === $type && ( 'active' !== $membership->status || ! Click2EU_Expiration::is_effectively_active( $membership ) || $membership->renewal_of_membership_id ) ) { return new WP_Error( 'stale_event', __( 'Membership activation is not effective.', 'click2eu-membership' ) ); }
			if ( 'renewal_scheduled' === $type && 'scheduled' !== $membership->status ) { return new WP_Error( 'stale_event', __( 'Renewal is no longer scheduled.', 'click2eu-membership' ) ); }
			if ( 'renewal_started' === $type && ( 'active' !== $membership->status || ! $membership->renewal_of_membership_id || 'upgrade' === $membership->renewal_type ) ) { return new WP_Error( 'stale_event', __( 'Renewal is not active.', 'click2eu-membership' ) ); }
			if ( 'upgrade_completed' === $type ) {
				$source = Click2EU_Memberships::get_membership( $membership->renewal_of_membership_id );
				if ( 'active' !== $membership->status || 'upgrade' !== $membership->renewal_type || ! $source || 'upgraded' !== $source->status || ! $order || ! $order->get_meta( Click2EU_Upgrades::FINALIZED_META, true ) ) { return new WP_Error( 'stale_event', __( 'Upgrade is not fully finalized.', 'click2eu-membership' ) ); }
			}
			if ( 0 === strpos( $type, 'membership_expiring_' ) ) {
				if ( 'active' !== $membership->status || ! Click2EU_Expiration::is_effectively_active( $membership ) || empty( $membership->expires_at ) || self::has_scheduled_renewal( $membership ) ) { return new WP_Error( 'stale_event', __( 'Expiry reminder is no longer applicable.', 'click2eu-membership' ) ); }
				$days = absint( substr( $type, strlen( 'membership_expiring_' ) ) );
				$target = Click2EU_Time::timestamp( $membership->expires_at ) - ( $days * DAY_IN_SECONDS );
				$expected_key = 'membership:' . absint( $membership->membership_id ) . ':expiry-warning:' . $days . 'd';
				$event_due = Click2EU_Time::timestamp( $notification->event_due_at ?? null );
				if ( ! $days || ! hash_equals( $expected_key, $notification->event_key ) || ! $event_due || abs( $event_due - $target ) > 60 ) { return new WP_Error( 'stale_event', __( 'Expiry reminder identity or original due time changed.', 'click2eu-membership' ) ); }
				if ( Click2EU_Time::now() + 60 < $target ) { return new WP_Error( 'stale_event', __( 'Expiry reminder is not due yet.', 'click2eu-membership' ) ); }
			}
			if ( 'membership_expired' === $type && ( 'expired' !== $membership->status || self::has_active_successor( $membership ) ) ) { return new WP_Error( 'stale_event', __( 'Membership expiration notice is no longer applicable.', 'click2eu-membership' ) ); }
			if ( 'membership_suspended' === $type && 'suspended' !== $membership->status ) { return new WP_Error( 'stale_event', __( 'Membership is not suspended.', 'click2eu-membership' ) ); }
			if ( 'membership_cancelled' === $type && 'cancelled' !== $membership->status ) { return new WP_Error( 'stale_event', __( 'Membership is not cancelled.', 'click2eu-membership' ) ); }
			if ( in_array( $type, array( 'membership_activated', 'renewal_scheduled', 'renewal_started', 'upgrade_completed' ), true ) && ( ! $order || 'completed' !== $order->get_status() || (int) $order->get_user_id() !== (int) $membership->candidate_user_id ) ) { return new WP_Error( 'invalid_order', __( 'Approved order state is unavailable.', 'click2eu-membership' ) ); }
		} elseif ( 'order' === $notification->source_type ) {
			$order = wc_get_order( $notification->source_id );
			if ( ! $order ) { return new WP_Error( 'invalid_source', __( 'Order source is unavailable.', 'click2eu-membership' ) ); }
			if ( 'activation_conflict' === $type && ! Click2EU_Orders::has_activation_conflict( $order ) ) { return new WP_Error( 'stale_event', __( 'Activation conflict marker is unavailable.', 'click2eu-membership' ) ); }
			if ( 'upgrade_review_required' === $type && ! $order->get_meta( Click2EU_Upgrades::REVIEW_META, true ) ) { return new WP_Error( 'stale_event', __( 'Upgrade review marker is unavailable.', 'click2eu-membership' ) ); }
			if ( 'invalid_payment_lineage' === $type && ! $order->get_meta( Click2EU_Upgrades::REVIEW_META, true ) ) { return new WP_Error( 'stale_event', __( 'Payment-lineage review marker is unavailable.', 'click2eu-membership' ) ); }
		} elseif ( 'notification' === $notification->source_type ) {
			$source = self::get( $notification->source_id );
			$valid_failure = 'notification_delivery_failure' === $type && $source && 'failed' === $source->status;
			$valid_uncertain = 'notification_delivery_uncertain' === $type && $source && 'uncertain' === $source->status;
			if ( ! $valid_failure && ! $valid_uncertain ) { return new WP_Error( 'stale_event', __( 'Delivery review state is no longer current.', 'click2eu-membership' ) ); }
		}
		return true;
	}

	private static function has_scheduled_renewal( $membership ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE renewal_of_membership_id=%d AND status='scheduled' LIMIT 1", absint( $membership->membership_id ) ) );
	}

	private static function has_active_successor( $membership ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . Click2EU_Memberships::table_name() . " WHERE renewal_of_membership_id=%d AND status='active' LIMIT 1", absint( $membership->membership_id ) ) );
	}

	private static function recipient( $notification ) {
		if ( 'admin' === ( self::event_registry()[ $notification->event_type ] ?? '' ) ) {
			return sanitize_email( get_option( 'admin_email' ) );
		}
		$user = get_userdata( $notification->user_id );
		return $user && (int) $user->ID === (int) $notification->user_id ? sanitize_email( $user->user_email ) : '';
	}

	public static function get( $notification_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE notification_id=%d', absint( $notification_id ) ) );
	}

	public static function cron_schedules( $schedules ) {
		if ( empty( $schedules['click2eu_notification_five_minutes'] ) ) {
			$schedules['click2eu_notification_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => __( 'Every five minutes (Click2EU notifications)', 'click2eu-membership' ) );
		}
		return $schedules;
	}

	public static function ensure_runner_schedule() {
		if ( self::use_action_scheduler() && function_exists( 'as_schedule_recurring_action' ) ) {
			wp_unschedule_hook( self::RUNNER_HOOK );
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::RUNNER_HOOK, array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action( Click2EU_Time::now() + 5 * MINUTE_IN_SECONDS, 5 * MINUTE_IN_SECONDS, self::RUNNER_HOOK, array(), self::ACTION_GROUP, true );
			}
		} elseif ( ! wp_next_scheduled( self::RUNNER_HOOK ) ) {
			wp_schedule_event( Click2EU_Time::now() + 5 * MINUTE_IN_SECONDS, 'click2eu_notification_five_minutes', self::RUNNER_HOOK );
		}
	}

	public static function run_queue() {
		global $wpdb;
		$now = Click2EU_Time::mysql();
		$recovered = self::recover_uncertain_final_attempts( $now );
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT notification_id FROM ' . self::table_name() . " WHERE attempt_count<%d AND scheduled_at<=%s AND (status='queued' OR (status='processing' AND lease_until<%s)) ORDER BY scheduled_at ASC,notification_id ASC LIMIT 25", self::MAX_ATTEMPTS, $now, $now ) );
		foreach ( $ids as $notification_id ) { self::deliver( $notification_id ); }
		return $recovered + count( $ids );
	}

	private static function recover_uncertain_final_attempts( $now ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT notification_id FROM ' . self::table_name() . " WHERE status='processing' AND attempt_count>=%d AND lease_until<%s ORDER BY lease_until ASC,notification_id ASC LIMIT 25", self::MAX_ATTEMPTS, $now ) );
		$recovered = 0;
		foreach ( $ids as $notification_id ) {
			$message = __( 'Final transport outcome is unknown after the worker lease expired. Automatic resend is suppressed pending administrator review.', 'click2eu-membership' );
			$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . " SET status='uncertain',lease_until=NULL,review_note=%s,updated_at=%s WHERE notification_id=%d AND status='processing' AND attempt_count>=%d AND lease_until<%s", $message, $now, $notification_id, self::MAX_ATTEMPTS, $now ) );
			if ( 1 !== $updated ) { continue; }
			++$recovered;
			self::queue( 'notification:' . absint( $notification_id ) . ':delivery-uncertain', 'notification_delivery_uncertain', 0, 'notification', $notification_id );
		}
		return $recovered;
	}

	public static function ensure_cleanup_schedule() {
		if ( self::use_action_scheduler() && function_exists( 'as_schedule_recurring_action' ) ) {
			wp_unschedule_hook( self::CLEANUP_HOOK );
			if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( self::CLEANUP_HOOK, array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action( Click2EU_Time::now() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::CLEANUP_HOOK, array(), self::ACTION_GROUP, true );
			}
		} elseif ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( Click2EU_Time::now() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	private static function use_action_scheduler() {
		$available = function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' );
		return (bool) apply_filters( 'click2eu_notifications_use_action_scheduler', $available );
	}

	public static function cleanup() {
		global $wpdb;
		$accepted_days = max( 30, absint( apply_filters( 'click2eu_notification_accepted_retention_days', 180 ) ) );
		$failed_days   = max( $accepted_days, absint( apply_filters( 'click2eu_notification_failed_retention_days', 365 ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . " WHERE status IN ('accepted','cancelled') AND updated_at<%s", Click2EU_Time::mysql( Click2EU_Time::now() - $accepted_days * DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . " WHERE status='failed' AND updated_at<%s", Click2EU_Time::mysql( Click2EU_Time::now() - $failed_days * DAY_IN_SECONDS ) ) );
	}

	public function register_admin_page() {
		add_submenu_page( Click2EU_Admin::PAGE_SLUG, __( 'Membership Notifications', 'click2eu-membership' ), __( 'Notifications', 'click2eu-membership' ), Click2EU_Admin::CAPABILITY, self::PAGE_SLUG, array( $this, 'render_admin_page' ) );
	}

	public function render_admin_page() {
		if ( ! current_user_can( Click2EU_Admin::CAPABILITY ) ) { wp_die( esc_html__( 'You are not allowed to view notification diagnostics.', 'click2eu-membership' ), '', array( 'response' => 403 ) ); }
		global $wpdb;
		$allowed = array( 'all', 'queued', 'processing', 'accepted', 'failed', 'uncertain', 'cancelled' );
		$filter = sanitize_key( wp_unslash( $_GET['notification_status'] ?? 'all' ) );
		$filter = in_array( $filter, $allowed, true ) ? $filter : 'all';
		$where = 'all' === $filter ? '' : $wpdb->prepare( ' WHERE status=%s', $filter );
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_name() . $where . ' ORDER BY notification_id DESC LIMIT 200' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Membership Notifications', 'click2eu-membership' ) . '</h1><p>' . esc_html__( 'Transport diagnostics only. Accepted means WordPress handed the message to its configured mail transport; it does not confirm inbox delivery.', 'click2eu-membership' ) . '</p><nav class="c2eu-admin-filters">';
		foreach ( $allowed as $status ) {
			$url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'notification_status' => $status ), admin_url( 'admin.php' ) );
			echo '<a class="' . esc_attr( $filter === $status ? 'current' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( ucfirst( $status ) ) . '</a> ';
		}
		echo '</nav><table class="widefat striped"><thead><tr><th>ID / Event</th><th>Source</th><th>Candidate</th><th>Status</th><th>Event due / Next attempt</th><th>Attempts</th><th>Last attempt</th><th>Accepted</th><th>Error / Review / Action</th></tr></thead><tbody>';
		if ( ! $rows ) { echo '<tr><td colspan="9">' . esc_html__( 'No notification records found.', 'click2eu-membership' ) . '</td></tr>'; }
		foreach ( $rows as $row ) {
			echo '<tr><td>#' . esc_html( $row->notification_id ) . '<br><strong>' . esc_html( Click2EU_Notification_Templates::event_title( $row->event_type ) ) . '</strong></td><td>' . esc_html( $row->source_type . ' #' . $row->source_id ) . '</td><td>' . esc_html( $row->user_id ?: '—' ) . '</td><td>' . esc_html( ucfirst( $row->status ) ) . '</td><td>' . esc_html( self::admin_date( $row->event_due_at ) ) . '<br><small>' . esc_html__( 'Next:', 'click2eu-membership' ) . ' ' . esc_html( self::admin_date( $row->scheduled_at ) ) . '</small></td><td>' . esc_html( $row->attempt_count . '/' . self::MAX_ATTEMPTS ) . '</td><td>' . esc_html( self::admin_date( $row->last_attempt_at ) ) . '</td><td>' . esc_html( self::admin_date( $row->sent_at ) ) . '</td><td>' . esc_html( $row->last_error );
			if ( ! empty( $row->review_note ) ) { echo '<br><strong>' . esc_html__( 'Review:', 'click2eu-membership' ) . '</strong> ' . esc_html( $row->review_note ); }
			if ( 'failed' === $row->status ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=click2eu_retry_notification&notification_id=' . absint( $row->notification_id ) ), 'click2eu_retry_notification_' . absint( $row->notification_id ) );
				echo '<br><a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Retry', 'click2eu-membership' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function handle_admin_retry() {
		if ( ! current_user_can( Click2EU_Admin::CAPABILITY ) ) { wp_die( esc_html__( 'You are not allowed to retry notifications.', 'click2eu-membership' ), '', array( 'response' => 403 ) ); }
		$id = absint( $_GET['notification_id'] ?? 0 );
		check_admin_referer( 'click2eu_retry_notification_' . $id );
		global $wpdb;
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . " SET status='queued', attempt_count=0, lease_until=NULL, scheduled_at=%s, last_error='', admin_retry_by=%d, admin_retry_at=%s, updated_at=%s WHERE notification_id=%d AND status='failed'", Click2EU_Time::mysql(), get_current_user_id(), Click2EU_Time::mysql(), Click2EU_Time::mysql(), $id ) );
		if ( 1 === $updated ) { self::schedule_delivery( $id, Click2EU_Time::now() + 1 ); }
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'notification_status' => 'failed' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function admin_date( $value ) {
		return empty( $value ) ? '—' : get_date_from_gmt( $value, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
