<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only Candidate membership dashboard for WooCommerce My Account and shortcodes.
 */
final class Click2EU_Dashboard {
	const ENDPOINT               = 'my-membership';
	const ENDPOINT_VERSION       = '1.0.0';
	const ENDPOINT_VERSION_OPTION = 'click2eu_membership_endpoint_version';
	const HISTORY_PER_PAGE       = 10;

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'account_content' ) );
		add_shortcode( 'click2eu_membership_dashboard', array( $this, 'shortcode' ) );
	}

	public static function activate() {
		self::register_endpoint();
		flush_rewrite_rules();
		update_option( self::ENDPOINT_VERSION_OPTION, self::ENDPOINT_VERSION );
	}

	public static function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function maybe_flush_rewrite_rules() {
		if ( self::ENDPOINT_VERSION === get_option( self::ENDPOINT_VERSION_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::ENDPOINT_VERSION_OPTION, self::ENDPOINT_VERSION );
	}

	public function account_menu_items( $items ) {
		if ( ! Click2EU_Auth::is_candidate() ) {
			return $items;
		}

		$updated = array();
		foreach ( $items as $key => $label ) {
			$updated[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				$updated[ self::ENDPOINT ] = __( 'My Membership', 'click2eu-membership' );
			}
		}

		if ( ! isset( $updated[ self::ENDPOINT ] ) ) {
			$updated[ self::ENDPOINT ] = __( 'My Membership', 'click2eu-membership' );
		}

		return $updated;
	}

	public function account_content() {
		echo wp_kses_post( $this->render_for_current_user() );
	}

	public function shortcode() {
		return $this->render_for_current_user();
	}

	private function render_for_current_user() {
		if ( ! is_user_logged_in() ) {
			return '<div class="c2eu-notice">' . esc_html__( 'Please sign in to view your membership.', 'click2eu-membership' ) . '</div>';
		}

		if ( ! Click2EU_Auth::is_candidate() ) {
			return '<div class="c2eu-notice">' . esc_html__( 'Candidate membership is available only to Candidate accounts.', 'click2eu-membership' ) . '</div>';
		}

		$user_id    = get_current_user_id();
		$current    = Click2EU_Memberships::get_membership_for_user( $user_id );
		$page       = isset( $_GET['c2eu_membership_page'] ) ? max( 1, absint( wp_unslash( $_GET['c2eu_membership_page'] ) ) ) : 1;
		$total      = Click2EU_Memberships::count_memberships_for_user( $user_id );
		$total_pages = max( 1, (int) ceil( $total / self::HISTORY_PER_PAGE ) );
		$page       = min( $page, $total_pages );
		$history    = Click2EU_Memberships::get_memberships_for_user(
			$user_id,
			self::HISTORY_PER_PAGE,
			( $page - 1 ) * self::HISTORY_PER_PAGE
		);

		$html  = '<section class="c2eu-membership c2eu-membership-dashboard" aria-labelledby="c2eu-membership-heading">';
		$html .= '<header class="c2eu-dashboard__header"><p class="c2eu-dashboard__eyebrow">' . esc_html__( 'Candidate account', 'click2eu-membership' ) . '</p>';
		$html .= '<h2 id="c2eu-membership-heading">' . esc_html__( 'My Membership', 'click2eu-membership' ) . '</h2></header>';
		$html .= $this->render_current_membership( $current, $user_id );
		$html .= $this->render_history( $history, $user_id, $page, $total_pages );
		$html .= '</section>';

		return $html;
	}

	private function render_current_membership( $membership, $user_id ) {
		$html  = '<div class="c2eu-dashboard__section">';
		$html .= '<h3>' . esc_html__( 'Current Membership', 'click2eu-membership' ) . '</h3>';

		if ( ! $membership || (int) $membership->candidate_user_id !== (int) $user_id ) {
			$html .= '<div class="c2eu-dashboard__empty"><p>' . esc_html__( 'No membership found.', 'click2eu-membership' ) . '</p>';
			$html .= '<a class="c2eu-dashboard__button" href="' . esc_url( Click2EU_Config::join_url() ) . '">' . esc_html__( 'Join Us', 'click2eu-membership' ) . '</a></div></div>';
			return $html;
		}

		$order       = $this->owned_order( $membership, $user_id );
		$status      = sanitize_key( $membership->status );
		$status_copy = $this->status_message( $status );

		$html .= '<article class="c2eu-dashboard__card">';
		$html .= '<div class="c2eu-dashboard__card-heading"><div><p class="c2eu-dashboard__message">' . esc_html( $status_copy ) . '</p>';
		$html .= '<h4>' . esc_html( $this->package_name( $membership ) ) . '</h4></div>';
		$html .= $this->status_badge( $status ) . '</div>';
		$html .= '<dl class="c2eu-dashboard__details">';
		$html .= $this->detail_row( __( 'Job Category', 'click2eu-membership' ), $membership->category_label );
		$html .= $this->detail_row( __( 'Order', 'click2eu-membership' ), $order ? '#' . $order->get_order_number() : __( 'Order unavailable', 'click2eu-membership' ) );
		$html .= $this->detail_row( __( 'Payment / Order Status', 'click2eu-membership' ), $order ? wc_get_order_status_name( $order->get_status() ) : __( 'Unavailable', 'click2eu-membership' ) );
		$html .= $this->detail_row( __( 'Activation Date', 'click2eu-membership' ), $this->activation_date( $membership ) );
		$html .= '</dl><div class="c2eu-dashboard__actions">';

		if ( $order ) {
			$html .= '<a class="c2eu-dashboard__button" href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html__( 'View Order', 'click2eu-membership' ) . '</a>';
		}
		if ( 'pending' === $status ) {
			$html .= '<a class="c2eu-dashboard__link" href="' . esc_url( Click2EU_Auth::my_orders_url() ) . '">' . esc_html__( 'View My Orders', 'click2eu-membership' ) . '</a>';
		} elseif ( 'suspended' === $status && ! $order ) {
			$html .= '<span class="c2eu-dashboard__support">' . esc_html__( 'Please contact customer support for assistance.', 'click2eu-membership' ) . '</span>';
		}

		$html .= '</div></article></div>';
		return $html;
	}

	private function render_history( $memberships, $user_id, $page, $total_pages ) {
		$html  = '<div class="c2eu-dashboard__section c2eu-dashboard__history">';
		$html .= '<h3>' . esc_html__( 'Membership History', 'click2eu-membership' ) . '</h3>';

		if ( empty( $memberships ) ) {
			return $html . '<div class="c2eu-dashboard__empty"><p>' . esc_html__( 'No membership history found.', 'click2eu-membership' ) . '</p></div></div>';
		}

		$html .= '<div class="c2eu-dashboard__table-wrap"><table><thead><tr>';
		$html .= '<th scope="col">' . esc_html__( 'Package', 'click2eu-membership' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Job Category', 'click2eu-membership' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Order', 'click2eu-membership' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Status', 'click2eu-membership' ) . '</th>';
		$html .= '<th scope="col">' . esc_html__( 'Activated', 'click2eu-membership' ) . '</th>';
		$html .= '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Action', 'click2eu-membership' ) . '</span></th></tr></thead><tbody>';

		foreach ( $memberships as $membership ) {
			if ( (int) $membership->candidate_user_id !== (int) $user_id ) {
				continue;
			}
			$order = $this->owned_order( $membership, $user_id );
			$html .= '<tr><td data-label="' . esc_attr__( 'Package', 'click2eu-membership' ) . '">' . esc_html( $this->package_name( $membership ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'Job Category', 'click2eu-membership' ) . '">' . esc_html( $membership->category_label ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'Order', 'click2eu-membership' ) . '">' . esc_html( $order ? '#' . $order->get_order_number() : __( 'Unavailable', 'click2eu-membership' ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'Status', 'click2eu-membership' ) . '">' . $this->status_badge( sanitize_key( $membership->status ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'Activated', 'click2eu-membership' ) . '">' . esc_html( $this->activation_date( $membership ) ) . '</td>';
			$html .= '<td data-label="' . esc_attr__( 'Action', 'click2eu-membership' ) . '">';
			if ( $order ) {
				$html .= '<a class="c2eu-dashboard__table-link" href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html__( 'View Order', 'click2eu-membership' ) . '</a>';
			} else {
				$html .= '<span aria-label="' . esc_attr__( 'Order unavailable', 'click2eu-membership' ) . '">—</span>';
			}
			$html .= '</td></tr>';
		}

		$html .= '</tbody></table></div>';
		if ( $total_pages > 1 ) {
			$html .= $this->pagination( $page, $total_pages );
		}
		return $html . '</div>';
	}

	private function pagination( $page, $total_pages ) {
		$base = function_exists( 'wc_get_account_endpoint_url' ) && is_account_page()
			? wc_get_account_endpoint_url( self::ENDPOINT )
			: remove_query_arg( 'c2eu_membership_page' );
		$html = '<nav class="c2eu-dashboard__pagination" aria-label="' . esc_attr__( 'Membership history pages', 'click2eu-membership' ) . '">';

		if ( $page > 1 ) {
			$html .= '<a href="' . esc_url( add_query_arg( 'c2eu_membership_page', $page - 1, $base ) ) . '">' . esc_html__( 'Previous', 'click2eu-membership' ) . '</a>';
		}
		$html .= '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'click2eu-membership' ), $page, $total_pages ) ) . '</span>';
		if ( $page < $total_pages ) {
			$html .= '<a href="' . esc_url( add_query_arg( 'c2eu_membership_page', $page + 1, $base ) ) . '">' . esc_html__( 'Next', 'click2eu-membership' ) . '</a>';
		}

		return $html . '</nav>';
	}

	private function owned_order( $membership, $user_id ) {
		if ( ! function_exists( 'wc_get_order' ) || (int) $membership->candidate_user_id !== (int) $user_id ) {
			return false;
		}
		$order = wc_get_order( absint( $membership->order_id ) );
		return $order && (int) $order->get_user_id() === (int) $user_id ? $order : false;
	}

	private function package_name( $membership ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $membership->product_id ) ) : false;
		return $product ? $product->get_name() : ucfirst( sanitize_key( $membership->package_key ) );
	}

	private function activation_date( $membership ) {
		if ( empty( $membership->activated_at ) || '0000-00-00 00:00:00' === $membership->activated_at ) {
			return __( 'Not available', 'click2eu-membership' );
		}
		return get_date_from_gmt( $membership->activated_at, get_option( 'date_format' ) );
	}

	private function status_message( $status ) {
		$messages = array(
			'pending'   => __( 'Awaiting payment verification.', 'click2eu-membership' ),
			'active'    => __( 'Your membership is active.', 'click2eu-membership' ),
			'suspended' => __( 'Your membership is suspended.', 'click2eu-membership' ),
			'cancelled' => __( 'This membership was cancelled.', 'click2eu-membership' ),
			'expired'   => __( 'This membership is no longer active.', 'click2eu-membership' ),
		);
		return isset( $messages[ $status ] ) ? $messages[ $status ] : __( 'Membership status is unavailable.', 'click2eu-membership' );
	}

	private function status_badge( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'click2eu-membership' ),
			'active'    => __( 'Active', 'click2eu-membership' ),
			'suspended' => __( 'Suspended', 'click2eu-membership' ),
			'cancelled' => __( 'Cancelled', 'click2eu-membership' ),
			'expired'   => __( 'Expired', 'click2eu-membership' ),
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'click2eu-membership' );
		return '<span class="c2eu-dashboard__badge c2eu-dashboard__badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	private function detail_row( $label, $value ) {
		return '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
	}
}
