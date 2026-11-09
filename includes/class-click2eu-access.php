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
		if ( empty( $_POST ) ) { return; }
		$is_resume = isset( $_POST['submit_resume'] );
		$is_application = isset( $_POST['cariera_addons_resumes_apply_with_resume'] ) || isset( $_POST['cariera_addons_send_application'] );
		if ( ! $is_resume && ! $is_application ) { return; }
		if ( current_user_can( 'manage_options' ) || Click2EU_Auth::is_employer() ) { return; }
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			wp_die( esc_html__( 'Sign in with an active Candidate membership to continue.', 'click2eu-membership' ), esc_html__( 'Candidate membership required', 'click2eu-membership' ), array( 'response' => 403 ) );
		}
		if ( $is_application ) {
			$result = Click2EU_Category_Access::authorize_application(
				get_current_user_id(),
				absint( wp_unslash( $_POST['job_id'] ?? 0 ) ),
				absint( wp_unslash( $_POST['resume_id'] ?? 0 ) )
			);
		} else {
			$resume_id = absint( wp_unslash( $_REQUEST['resume_id'] ?? 0 ) );
			if ( $resume_id ) {
				$resume = get_post( $resume_id );
				if ( ! $resume || (int) $resume->post_author !== get_current_user_id() ) {
					$result = new WP_Error( 'click2eu_resume_ownership', __( 'You cannot modify this resume.', 'click2eu-membership' ) );
				} else {
					$result = Click2EU_Category_Access::authorize_resume_terms( get_current_user_id(), wp_unslash( $_POST['resume_category'] ?? array() ) );
				}
			} else {
				$result = Click2EU_Category_Access::authorize_resume_terms( get_current_user_id(), wp_unslash( $_POST['resume_category'] ?? array() ) );
			}
		}
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Membership category restriction', 'click2eu-membership' ), array( 'response' => 403 ) );
		}
	}

	public function guard_rest( $result, $server, $request ) {
		if ( is_wp_error( $result ) || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) { return $result; }
		if ( ! preg_match( '#^/wp/v2/resumes(?:/(\d+))?/?$#', $request->get_route(), $matches ) ) { return $result; }
		if ( current_user_can( 'manage_options' ) || Click2EU_Auth::is_employer() ) { return $result; }
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return new WP_Error( 'click2eu_candidate_required', __( 'An active Candidate membership is required.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		$resume_id = absint( $matches[1] ?? $request->get_param( 'id' ) );
		if ( $resume_id ) {
			$resume = get_post( $resume_id );
			if ( ! $resume || (int) $resume->post_author !== get_current_user_id() ) {
				return new WP_Error( 'click2eu_resume_ownership', __( 'You cannot modify this resume.', 'click2eu-membership' ), array( 'status' => 403 ) );
			}
		}
		$term_ids = $request->get_param( 'resume_category' );
		if ( null === $term_ids ) { $term_ids = $request->get_param( 'resume-categories' ); }
		if ( null === $term_ids && $resume_id ) { $term_ids = wp_get_object_terms( $resume_id, Click2EU_Category_Access::RESUME_TAXONOMY, array( 'fields' => 'ids' ) ); }
		$authorized = Click2EU_Category_Access::authorize_resume_terms( get_current_user_id(), (array) $term_ids );
		return is_wp_error( $authorized ) ? $authorized : $result;
	}
}
