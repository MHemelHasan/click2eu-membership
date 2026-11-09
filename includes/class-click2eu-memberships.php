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
			activated_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (membership_id),
			UNIQUE KEY order_item_id (order_item_id),
			KEY candidate_status (candidate_user_id,status),
			KEY order_id (order_id)
		) {$charset};";

		dbDelta( $sql );
		update_option( Click2EU_Config::DB_VERSION_OPTION, Click2EU_Config::DB_VERSION );
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
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::table_name() . " WHERE candidate_user_id = %d ORDER BY CASE WHEN status = 'active' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END, membership_id DESC LIMIT 1",
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
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'active' LIMIT 1", absint( $user_id ) ) );
	}

	public static function has_pending_membership( $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT membership_id FROM ' . self::table_name() . " WHERE candidate_user_id = %d AND status = 'pending' LIMIT 1", absint( $user_id ) ) );
	}

	public static function create_pending( WC_Order $order, WC_Order_Item_Product $item, $package, $category_id, $category_label ) {
		global $wpdb;
		$existing = self::get_for_order_item( $item->get_id() );
		if ( $existing ) {
			return absint( $existing->membership_id );
		}

		$now = current_time( 'mysql', true );
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
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			$existing = self::get_for_order_item( $item->get_id() );
			return $existing ? absint( $existing->membership_id ) : 0;
		}

		return absint( $wpdb->insert_id );
	}

	public static function activate_membership( $membership_id, &$conflicting_membership_id = 0 ) {
		global $wpdb;
		$conflicting_membership_id = 0;
		$membership = self::get_membership( $membership_id );
		if ( ! $membership ) {
			return false;
		}
		if ( 'active' === $membership->status ) {
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

			$now = current_time( 'mysql', true );
			return false !== $wpdb->update(
				self::table_name(),
				array( 'status' => 'active', 'activated_at' => $now, 'updated_at' => $now ),
				array( 'membership_id' => absint( $membership_id ) ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	public static function suspend_order( $order_id ) {
		global $wpdb;
		return false !== $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table_name() . " SET status = CASE WHEN status = 'active' THEN 'suspended' ELSE 'cancelled' END, updated_at = %s WHERE order_id = %d AND status IN ('active','pending')",
				current_time( 'mysql', true ),
				absint( $order_id )
			)
		);
	}

	public static function get_membership_status( $user_id ) {
		if ( self::has_active_membership( $user_id ) ) {
			return 'active';
		}
		return self::has_pending_membership( $user_id ) ? 'pending' : 'none';
	}
}
