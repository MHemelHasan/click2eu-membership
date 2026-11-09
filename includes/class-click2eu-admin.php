<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Admin {
	const CAPABILITY = 'manage_options';
	const PAGE_SLUG  = 'click2eu-memberships';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_page() {
		add_menu_page(
			__( 'Click2EU Memberships', 'click2eu-membership' ),
			__( 'Click2EU Memberships', 'click2eu-membership' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-groups',
			56
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'click2eu-membership-admin', plugin_dir_url( dirname( __DIR__ ) . '/click2eu-membership.php' ) . 'assets/admin-memberships.css', array(), Click2EU_Config::VERSION );
	}

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view membership administration.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}
		global $wpdb;
		$allowed = array( 'all', 'active', 'pending', 'scheduled', 'expired', 'suspended', 'upgraded', 'review' );
		$filter  = isset( $_GET['membership_status'] ) ? sanitize_key( wp_unslash( $_GET['membership_status'] ) ) : 'all';
		$filter  = in_array( $filter, $allowed, true ) ? $filter : 'all';
		$where   = '';
		if ( ! in_array( $filter, array( 'all', 'review' ), true ) ) {
			$where = $wpdb->prepare( ' WHERE status = %s', $filter );
		}
		$memberships = $wpdb->get_results( 'SELECT * FROM ' . Click2EU_Memberships::table_name() . $where . ' ORDER BY membership_id DESC LIMIT 200' );
		if ( 'review' === $filter ) {
			$memberships = array_values( array_filter( $memberships, static function ( $membership ) {
				$order = wc_get_order( $membership->order_id );
				return $order && ( Click2EU_Orders::has_activation_conflict( $order ) || $order->get_meta( Click2EU_Upgrades::REVIEW_META, true ) );
			} ) );
		}

		echo '<div class="wrap c2eu-admin-memberships"><h1>' . esc_html__( 'Click2EU Memberships', 'click2eu-membership' ) . '</h1>';
		echo '<p>' . esc_html__( 'Read-only lifecycle review. Payment approval remains in WooCommerce Orders.', 'click2eu-membership' ) . '</p><nav class="c2eu-admin-filters">';
		foreach ( array( 'all' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'suspended' => 'Suspended', 'upgraded' => 'Upgraded', 'review' => 'Review Required' ) as $key => $label ) {
			$url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'membership_status' => $key ), admin_url( 'admin.php' ) );
			echo '<a class="' . esc_attr( $filter === $key ? 'current' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav><div class="c2eu-admin-table-wrap"><table class="widefat striped"><thead><tr><th>ID</th><th>Candidate</th><th>Package / Category</th><th>Category Authorization</th><th>Order</th><th>Status</th><th>Activated</th><th>Starts</th><th>Expires</th><th>Renewal</th><th>Review</th></tr></thead><tbody>';
		if ( ! $memberships ) {
			echo '<tr><td colspan="11">' . esc_html__( 'No memberships found.', 'click2eu-membership' ) . '</td></tr>';
		}
		foreach ( $memberships as $membership ) {
			$user     = get_userdata( $membership->candidate_user_id );
			$order    = wc_get_order( $membership->order_id );
			$conflict = $order && ( Click2EU_Orders::has_activation_conflict( $order ) || $order->get_meta( Click2EU_Upgrades::REVIEW_META, true ) );
			$children = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Click2EU_Memberships::table_name() . ' WHERE renewal_of_membership_id = %d', $membership->membership_id ) );
			echo '<tr><td>#' . esc_html( $membership->membership_id ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : __( 'Deleted user', 'click2eu-membership' ) ) . '<br><small>ID ' . esc_html( $membership->candidate_user_id ) . '</small></td>';
			$category = get_term( absint( $membership->category_term_id ), Click2EU_Category_Access::JOB_TAXONOMY );
			$category_status = $category && ! is_wp_error( $category ) ? Click2EU_Category_Access::mapping_status( $membership->category_term_id ) : __( 'Purchased job category missing or deleted', 'click2eu-membership' );
			echo '<td><strong>' . esc_html( ucfirst( $membership->package_key ) ) . '</strong><br>' . esc_html( $membership->category_label ) . '</td>';
			echo '<td>' . esc_html( $category_status ) . '</td><td>';
			if ( $order ) {
				echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a><br><small>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</small>';
			} else {
				echo esc_html__( 'Unavailable', 'click2eu-membership' );
			}
			echo '</td><td><strong>' . esc_html( ucfirst( $membership->status ) ) . '</strong></td>';
			echo '<td>' . esc_html( $this->date( $membership->activated_at ) ) . '</td><td>' . esc_html( $this->date( $membership->starts_at ) ) . '</td><td>' . esc_html( $this->date( $membership->expires_at ) ) . '</td>';
			if ( 'upgrade' === $membership->renewal_type && $order ) {
				echo '<td>Upgrade from #' . esc_html( $membership->renewal_of_membership_id ) . '<br><small>' . esc_html( $order->get_meta( '_click2eu_upgrade_credit_amount', true ) . ' ' . $order->get_currency() . ' credit / ' . $order->get_meta( '_click2eu_upgrade_final_charge', true ) . ' charged' ) . '</small></td>';
			} else {
				echo '<td>' . ( $membership->renewal_of_membership_id ? 'Renews #' . esc_html( $membership->renewal_of_membership_id ) : '—' ) . ( $children ? '<br><small>' . esc_html( $children ) . ' renewal(s)</small>' : '' ) . '</td>';
			}
			echo '<td>' . esc_html( $conflict ? __( 'Activation conflict', 'click2eu-membership' ) : '—' ) . '</td></tr>';
		}
		echo '</tbody></table></div></div>';
	}

	private function date( $value ) {
		return empty( $value ) ? '—' : get_date_from_gmt( $value, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
