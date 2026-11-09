<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Click2EU_Access {
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'guard_frontend' ), 20 );
		add_action( 'init', array( $this, 'guard_actions' ), 1 );
		add_filter( 'rest_pre_dispatch', array( $this, 'guard_rest' ), 10, 3 );
	}

	public function guard_frontend() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return;
		}
		$user_id = get_current_user_id();
		$status  = Click2EU_Memberships::get_membership_status( $user_id );

		if ( is_page( Click2EU_Config::join_page_id() ) ) {
			if ( 'active' === $status && Click2EU_Config::candidate_dashboard_id() ) {
				$renewal_id = isset( $_GET['renew_membership'] ) ? absint( wp_unslash( $_GET['renew_membership'] ) ) : 0;
				$active     = $renewal_id ? Click2EU_Memberships::get_membership( $renewal_id ) : null;
				if ( $active && (int) $active->candidate_user_id === $user_id && 'active' === $active->status && ! empty( $active->expires_at ) && ! Click2EU_Memberships::has_pending_membership( $user_id ) && ! Click2EU_Memberships::has_scheduled_membership( $user_id ) ) {
					return;
				}
				wp_safe_redirect( get_permalink( Click2EU_Config::candidate_dashboard_id() ) );
				exit;
			}
			if ( 'pending' === $status && ! isset( $_GET['c2eu_error'] ) ) {
				wp_safe_redirect( Click2EU_Auth::my_orders_url() );
				exit;
			}
			return;
		}

		if ( 'active' === $status ) {
			return;
		}
		$protected = array_filter( array( Click2EU_Config::candidate_dashboard_id(), Click2EU_Config::submit_resume_id() ) );
		if ( is_page( $protected ) || is_singular( 'resume' ) && get_current_user_id() === (int) get_post_field( 'post_author', get_queried_object_id() ) ) {
			wp_safe_redirect( add_query_arg( 'membership', 'required', Click2EU_Config::join_url() ) );
			exit;
		}
	}

	public function guard_actions() {
		if ( empty( $_POST ) || ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() || Click2EU_Memberships::has_active_membership( get_current_user_id() ) ) {
			return;
		}
		$restricted = isset( $_POST['submit_resume'] ) || isset( $_POST['cariera_addons_resumes_apply_with_resume'] );
		if ( ! $restricted ) {
			return;
		}
		wp_die( esc_html__( 'An active Candidate membership is required for this action.', 'click2eu-membership' ), esc_html__( 'Membership required', 'click2eu-membership' ), array( 'response' => 403 ) );
	}

	public function guard_rest( $result, $server, $request ) {
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() || Click2EU_Memberships::has_active_membership( get_current_user_id() ) ) {
			return $result;
		}
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) && preg_match( '#/(?:resume|application)s?(?:/|$)#i', $request->get_route() ) ) {
			return new WP_Error( 'click2eu_membership_required', __( 'An active Candidate membership is required.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		return $result;
	}
}
