<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Cariera {
	public function __construct() {
		add_filter( 'pre_option_cariera_login_redirection_candidate', array( $this, 'force_candidate_redirect_mode' ) );
		add_filter( 'cariera_login_candi_redirection_page', array( $this, 'candidate_landing_page' ) );
		add_filter( 'login_redirect', array( $this, 'standard_login_redirect' ), 20, 3 );
		add_filter( 'the_content', array( $this, 'show_authentication_forms' ), 20 );
		add_action( 'init', array( $this, 'validate_cariera_registration_role' ), 0 );
		add_action( 'cariera_dashboard_main_nav_end', array( $this, 'membership_dashboard_link' ) );
	}

	public function force_candidate_redirect_mode() {
		return 'custom_page';
	}

	public function candidate_landing_page( $page_id ) {
		$membership = is_user_logged_in() ? Click2EU_Category_Access::effective_membership( get_current_user_id() ) : null;
		if ( $membership && ! is_wp_error( $membership ) && Click2EU_Config::candidate_dashboard_id() ) {
			return Click2EU_Config::candidate_dashboard_id();
		}
		return Click2EU_Config::join_page_id() ?: $page_id;
	}

	public function standard_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( ! $user instanceof WP_User || ! Click2EU_Auth::is_candidate( $user ) ) {
			return $redirect_to;
		}
		if ( $requested_redirect_to && Click2EU_Route_Policy::is_purchase_url( $requested_redirect_to ) ) {
			return $requested_redirect_to;
		}
		$membership = Click2EU_Category_Access::effective_membership( $user->ID );
		if ( ! is_wp_error( $membership ) && Click2EU_Config::candidate_dashboard_id() ) {
			return get_permalink( Click2EU_Config::candidate_dashboard_id() );
		}
		return Click2EU_Config::join_url();
	}

	public function show_authentication_forms( $content ) {
		if ( is_user_logged_in() || ! is_page( Click2EU_Config::join_page_id() ) || '1' !== ( $_GET['click2eu_auth'] ?? '' ) ) {
			return $content;
		}
		$forms  = '<div class="c2eu-membership c2eu-authentication">';
		$forms .= '<h2>' . esc_html__( 'Register or sign in to continue', 'click2eu-membership' ) . '</h2>';
		$forms .= do_shortcode( '[cariera_login_form][cariera_registration_form]' );
		$forms .= '</div>';
		return $forms . $content;
	}

	public function validate_cariera_registration_role() {
		if ( ! wp_doing_ajax() || 'cariera_ajax_register' !== ( $_REQUEST['action'] ?? '' ) ) {
			return;
		}
		$role = isset( $_POST['cariera_user_role'] ) ? sanitize_key( wp_unslash( $_POST['cariera_user_role'] ) ) : '';
		if ( $role && ! in_array( $role, array( 'candidate', 'employer' ), true ) ) {
			wp_send_json( array( 'register' => false, 'message' => '<span class="job-manager-message error">' . esc_html__( 'Please choose Candidate or Employer.', 'click2eu-membership' ) . '</span>' ), 400 );
		}
	}

	public function membership_dashboard_link() {
		if ( ! Click2EU_Auth::is_candidate() || ! function_exists( 'wc_get_account_endpoint_url' ) ) {
			return;
		}
		$url = wc_get_account_endpoint_url( Click2EU_Dashboard::ENDPOINT );
		echo '<li class="dashboard-menu-item_membership"><a href="' . esc_url( $url ) . '"><i class="icon icon-credit-card"></i>' . esc_html__( 'My Membership', 'click2eu-membership' ) . '</a></li>';
	}
}
