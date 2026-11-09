<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Memberships {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'click2eu_memberships';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			membership_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			candidate_user_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			package_key varchar(32) NOT NULL,
			category_term_id bigint(20) unsigned NOT NULL,
			category_label varchar(200) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			duration_days smallint(5) unsigned NOT NULL DEFAULT 0,
			renewal_of_membership_id bigint(20) unsigned NULL,
			renewal_type varchar(20) NOT NULL DEFAULT '',
			starts_at datetime NULL,
			activated_at datetime NULL,
			expires_at datetime NULL,
			expired_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (membership_id),
			UNIQUE KEY order_item_id (order_item_id),
			KEY candidate_status (candidate_user_id,status),
			KEY renewal_parent (renewal_of_membership_id),
			KEY candidate_starts (candidate_user_id,starts_at),
			KEY status_expires (status,expires_at),
			KEY order_id (order_id)
		) {$charset};";

		dbDelta( $sql );
		$required = array( 'duration_days', 'renewal_of_membership_id', 'renewal_type', 'starts_at', 'expires_at', 'expired_at' );
		$columns  = $wpdb->get_col( 'SHOW COLUMNS FROM ' . self::table_name(), 0 );
		if ( ! array_diff( $required, $columns ) ) {
			update_option( Click2EU_Config::DB_VERSION_OPTION, Click2EU_Config::DB_VERSION );
		}
	}

	public static function maybe_install() {
		if ( Click2EU_Config::DB_VERSION !== get_option( Click2EU_Config::DB_VERSION_OPTION ) ) {
			self::install();
		}
	}

	public static function get_membership( $membership_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE membership_id = %d', absint( $membership_id ) ) );
	}

	public static function get_for_order_item( $order_item_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE order_item_id = %d', absint( $order_item_id ) ) );
	}

	public static function get_memberships_for_user( $user_id, $limit = 0, $offset = 0 ) {
		global $wpdb;
		if ( $limit > 0 ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table_name() . ' WHERE candidate_user_id = %d ORDER BY membership_id DESC LIMIT %d OFFSET %d',
					absint( $user_id ),
					absint( $limit ),
					absint( $offset )
				)
			);
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE candidate_user_id = %d ORDER BY membership_id DESC', absint( $user_id ) ) );
	}

	public static function count_memberships_for_user( $user_id ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE candidate_user_id = %d', absint( $user_id ) ) ) );
	}

	public static function get_membership_for_user( $user_id ) {
		global $wpdb;
		if ( class_exists( 'Click2EU_Expiration' ) ) {
			Click2EU_Expiration::reconcile_user( $user_id );
		}
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table_name() . " WHERE candidate_user_id = %d ORDER BY CASE WHEN status = 'active' THEN 0 WHEN status = 'scheduled' THEN 1 WHEN status = 'pending' THEN 2 ELSE 3 END, membership_id DESC LIMIT 1",
				absint( $user_id )
			)
		);
	}

	public static function get_membership_for_order( $order_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE order_id = %d ORDER BY membership_id DESC LIMIT 1', absint( $order_id ) ) );
	}

	public static function has_active_membership( $user_id ) {
		global $wpdb;
		if ( class_exists( 'Click2EU_Expiration' ) ) {
			Click2EU_Expiration::reconcile_user( $user_id );
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > %s) LIMIT 1", absint( $user_id ), Click2EU_Time::mysql() ) );
	}

	public static function has_pending_membership( $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'pending' LIMIT 1", absint( $user_id ) ) );
	}

	public static function has_scheduled_membership( $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'scheduled' LIMIT 1", absint( $user_id ) ) );
	}

	public static function get_renewal_for_user( $user_id, $statuses = array( 'pending', 'scheduled' ) ) {
		global $wpdb;
		$statuses = array_values( array_intersect( array_map( 'sanitize_key', (array) $statuses ), array( 'pending', 'scheduled', 'active', 'expired', 'cancelled', 'suspended' ) ) );
		if ( empty( $statuses ) ) {
			return null;
		}
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( absint( $user_id ) ), $statuses );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND renewal_of_membership_id IS NOT NULL AND status IN ($placeholders) ORDER BY membership_id DESC LIMIT 1", $params ) );
	}

	public static function create_pending( WC_Order $order, WC_Order_Item_Product $item, $package, $category_id, $category_label, $duration_days = 0, $renewal_of_membership_id = 0, $renewal_type = '' ) {
		global $wpdb;
		$existing = self::get_for_order_item( $item->get_id() );
		if ( $existing ) {
			return absint( $existing->membership_id );
		}

		$now           = Click2EU_Time::mysql();
		$duration_days = absint( $duration_days ) ?: Click2EU_Config::duration_for_package( $package );
		$renewal_of_membership_id = absint( $renewal_of_membership_id );
		$renewal_type = $renewal_of_membership_id ? sanitize_key( $renewal_type ?: 'early' ) : '';
		$ok  = $wpdb->insert(
			self::table_name(),
			array(
				'candidate_user_id' => $order->get_user_id(),
				'order_id'           => $order->get_id(),
				'order_item_id'      => $item->get_id(),
				'product_id'         => $item->get_product_id(),
				'package_key'        => sanitize_key( $package ),
				'category_term_id'   => absint( $category_id ),
				'category_label'     => sanitize_text_field( $category_label ),
				'status'             => 'pending',
				'duration_days'      => $duration_days,
				'renewal_of_membership_id' => $renewal_of_membership_id ?: null,
				'renewal_type'       => $renewal_type,
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			$existing = self::get_for_order_item( $item->get_id() );
			return $existing ? absint( $existing->membership_id ) : 0;
		}

		$membership_id = absint( $wpdb->insert_id );
		do_action( 'click2eu_membership_created', $membership_id );
		return $membership_id;
	}

	public static function activate_membership( $membership_id, &$conflicting_membership_id = 0, $approved_order = null, &$activation_state = '' ) {
		global $wpdb;
		$conflicting_membership_id = 0;
		$activation_state          = '';
		$membership = self::get_membership( $membership_id );
		if ( ! $membership ) {
			return false;
		}
		if ( 'active' === $membership->status ) {
			$activation_state = 'active';
			return true;
		}

		$lock_name = 'click2eu_membership_user_' . absint( $membership->candidate_user_id );
		$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
		if ( 1 !== $locked ) {
			return false;
		}

		try {
			$membership = self::get_membership( $membership_id );
			if ( ! $membership || ! in_array( $membership->status, array( 'pending', 'active' ), true ) ) {
				return false;
			}
			if ( 'active' === $membership->status ) {
				$activation_state = 'active';
				return true;
			}

			$order = $approved_order instanceof WC_Order ? $approved_order : wc_get_order( absint( $membership->order_id ) );
			$item  = $order ? $order->get_item( absint( $membership->order_item_id ) ) : false;
			if (
				! $order || 'completed' !== $order->get_status() ||
				(int) $order->get_id() !== (int) $membership->order_id ||
				(int) $order->get_user_id() !== (int) $membership->candidate_user_id ||
				! $item instanceof WC_Order_Item_Product ||
				(int) $item->get_product_id() !== (int) $membership->product_id ||
				! Click2EU_Config::is_membership_product( $membership->product_id ) ||
				! term_exists( absint( $membership->category_term_id ), 'job_listing_category' ) ||
				absint( $item->get_meta( '_click2eu_category_id', true ) ) !== absint( $membership->category_term_id )
			) {
				return false;
			}

			// Reconcile overdue active rows while holding the same Candidate lock.
			$now_mysql = Click2EU_Time::mysql();
			$due_ids   = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s",
					absint( $membership->candidate_user_id ),
					$now_mysql
				)
			);
			if ( $due_ids ) {
				$expired = $wpdb->query(
					$wpdb->prepare(
						'UPDATE ' . self::table_name() . " SET status = 'expired', expired_at = %s, updated_at = %s WHERE candidate_user_id = %d AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= %s",
						$now_mysql,
						$now_mysql,
						absint( $membership->candidate_user_id ),
						$now_mysql
					)
				);
				foreach ( $due_ids as $due_id ) {
					Click2EU_Expiration::unschedule( $due_id );
					if ( $expired ) { do_action( 'click2eu_membership_transition', absint( $due_id ), 'active', 'expired', 'activation_reconciliation' ); }
				}
			}

			$duration_days = absint( $membership->duration_days );
			if ( ! $duration_days ) {
				$duration_days = absint( $item->get_meta( '_click2eu_duration_days', true ) ) ?: Click2EU_Config::duration_for_package( $membership->package_key );
			}
			if ( ! $duration_days ) {
				return false;
			}

			if ( ! empty( $membership->renewal_of_membership_id ) ) {
				$parent = self::get_membership( $membership->renewal_of_membership_id );
				$other_scheduled = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'scheduled' AND membership_id <> %d LIMIT 1",
						absint( $membership->candidate_user_id ),
						absint( $membership->membership_id )
					)
				);
				$other_active = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND membership_id <> %d AND (expires_at IS NULL OR expires_at > %s) LIMIT 1",
						absint( $membership->candidate_user_id ),
						absint( $membership->renewal_of_membership_id ),
						Click2EU_Time::mysql()
					)
				);
				if (
					! $parent || ! in_array( $parent->status, array( 'active', 'expired' ), true ) || empty( $parent->expires_at ) ||
					(int) $parent->candidate_user_id !== (int) $membership->candidate_user_id ||
					$parent->package_key !== $membership->package_key ||
					(int) $parent->category_term_id !== (int) $membership->category_term_id ||
					$other_scheduled || $other_active
				) {
					$conflicting_membership_id = $parent ? absint( $parent->membership_id ) : 0;
					return false;
				}

				$start_timestamp = max( Click2EU_Time::now(), Click2EU_Time::timestamp( $parent->expires_at ) );
				$starts_at       = Click2EU_Time::mysql( $start_timestamp );
				$expires_at      = Click2EU_Time::mysql( $start_timestamp + ( $duration_days * DAY_IN_SECONDS ) );
				$status          = $start_timestamp <= Click2EU_Time::now() ? 'active' : 'scheduled';
				if ( 'scheduled' === $status ) {
					$updated = $wpdb->query(
						$wpdb->prepare(
							'UPDATE ' . self::table_name() . " SET status = 'scheduled', duration_days = %d, starts_at = %s, activated_at = NULL, expires_at = %s, expired_at = NULL, updated_at = %s WHERE membership_id = %d AND status = 'pending'",
							$duration_days, $starts_at, $expires_at, Click2EU_Time::mysql(), absint( $membership_id )
						)
					);
				} else {
					$updated = $wpdb->query(
						$wpdb->prepare(
							'UPDATE ' . self::table_name() . " SET status = 'active', duration_days = %d, starts_at = %s, activated_at = %s, expires_at = %s, expired_at = NULL, updated_at = %s WHERE membership_id = %d AND status = 'pending'",
							$duration_days, $starts_at, $starts_at, $expires_at, Click2EU_Time::mysql(), absint( $membership_id )
						)
					);
				}
				if ( 1 !== $updated ) {
					return false;
				}
				$activation_state = $status;
				if ( 'scheduled' === $status ) {
					Click2EU_Expiration::schedule_start( $membership_id, $starts_at );
				} else {
					Click2EU_Expiration::schedule( $membership_id, $expires_at );
				}
				do_action( 'click2eu_membership_transition', absint( $membership_id ), 'pending', $status, 'approved_renewal' );
				return true;
			}

			$conflicting_membership_id = absint(
				$wpdb->get_var(
					$wpdb->prepare(
						'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'active' AND membership_id <> %d ORDER BY membership_id DESC LIMIT 1",
						absint( $membership->candidate_user_id ),
						absint( $membership->membership_id )
					)
				)
			);
			if ( $conflicting_membership_id ) {
				return false;
			}

			$activated_timestamp = Click2EU_Time::now();
			$activated_at        = Click2EU_Time::mysql( $activated_timestamp );
			$expires_at          = Click2EU_Time::mysql( $activated_timestamp + ( $duration_days * DAY_IN_SECONDS ) );
			$updated = $wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . self::table_name() . " SET status = 'active', duration_days = %d, activated_at = %s, expires_at = %s, expired_at = NULL, updated_at = %s WHERE membership_id = %d AND status = 'pending'",
					$duration_days,
					$activated_at,
					$expires_at,
					$activated_at,
					absint( $membership_id )
				)
			);
			if ( 1 === $updated ) {
				$activation_state = 'active';
				Click2EU_Expiration::schedule( $membership_id, $expires_at );
				do_action( 'click2eu_membership_transition', absint( $membership_id ), 'pending', 'active', 'approved_order' );
				return true;
			}
			return false;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function suspend_order( $order_id ) {
		global $wpdb;
		$memberships = $wpdb->get_results( $wpdb->prepare( 'SELECT membership_id, candidate_user_id FROM ' . self::table_name() . ' WHERE order_id = %d', absint( $order_id ) ) );
		$result      = true;

		foreach ( $memberships as $membership_ref ) {
			$lock_name = 'click2eu_membership_user_' . absint( $membership_ref->candidate_user_id );
			$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock_name ) );
			if ( 1 !== $locked ) {
				$result = false;
				continue;
			}

			try {
				$membership = self::get_membership( $membership_ref->membership_id );
				if ( ! $membership || ! in_array( $membership->status, array( 'active', 'pending', 'scheduled', 'expired' ), true ) ) {
					continue;
				}
				$new_status = in_array( $membership->status, array( 'pending', 'scheduled' ), true ) ? 'cancelled' : 'suspended';
				$updated    = $wpdb->update(
					self::table_name(),
					array( 'status' => $new_status, 'updated_at' => Click2EU_Time::mysql() ),
					array( 'membership_id' => absint( $membership->membership_id ) ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				$result = false !== $updated && $result;
				Click2EU_Expiration::unschedule( $membership->membership_id );
				Click2EU_Expiration::unschedule_start( $membership->membership_id );
				if ( $updated ) {
					do_action( 'click2eu_membership_transition', absint( $membership->membership_id ), $membership->status, $new_status, 'order_reversal' );
				}
			} finally {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
			}
		}

		return $result;
	}

	public static function get_membership_status( $user_id ) {
		if ( self::has_active_membership( $user_id ) ) {
			return 'active';
		}
		return self::has_pending_membership( $user_id ) ? 'pending' : 'none';
	}
}
