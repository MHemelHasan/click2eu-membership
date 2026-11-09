<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Central Phase-1 route and actor policy. Enforcement adapters live in Click2EU_Access. */
final class Click2EU_Route_Policy {
	const JOB_POST_TYPE = 'job_listing';
	const COMPANY_POST_TYPE = 'company';
	const RESUME_POST_TYPE = 'resume';

	public static function is_privileged_actor() {
		return current_user_can( 'manage_options' ) || Click2EU_Auth::is_employer();
	}

	public static function current_candidate_membership() {
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return new WP_Error( 'click2eu_candidate_required', __( 'A Candidate account with an active membership is required.', 'click2eu-membership' ), array( 'status' => 401 ) );
		}
		return Click2EU_Category_Access::effective_membership( get_current_user_id() );
	}

	public static function can_access_paid_candidate_features() {
		if ( self::is_privileged_actor() ) {
			return true;
		}
		$membership = self::current_candidate_membership();
		return ! is_wp_error( $membership );
	}

	public static function denial() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'click2eu_authentication_required', __( 'Sign in and activate a membership to continue.', 'click2eu-membership' ), array( 'status' => 401 ) );
		}
		return new WP_Error( 'click2eu_membership_required', __( 'An effective Active membership is required to access this feature.', 'click2eu-membership' ), array( 'status' => 403 ) );
	}

	public static function protected_post_types() {
		return array( self::JOB_POST_TYPE, self::COMPANY_POST_TYPE, self::RESUME_POST_TYPE );
	}

	public static function protected_taxonomies() {
		return array_filter( array(
			'job_listing_category',
			'job_listing_region',
			'job_listing_type',
			'company_category',
			'company_region',
			'resume_category',
			'resume_skill',
		), 'taxonomy_exists' );
	}

	public static function is_allowed_candidate_page() {
		$allowed = array_filter( array(
			Click2EU_Settings::page_id( 'homepage' ),
			Click2EU_Settings::page_id( 'join' ),
			Click2EU_Settings::page_id( 'login' ),
			Click2EU_Settings::page_id( 'candidate_dashboard' ),
			Click2EU_Settings::page_id( 'my_profile' ),
			Click2EU_Settings::page_id( 'my_resumes' ),
			Click2EU_Settings::page_id( 'my_account' ),
			absint( get_option( 'woocommerce_cart_page_id' ) ),
			absint( get_option( 'woocommerce_checkout_page_id' ) ),
			absint( get_option( 'wp_page_for_privacy_policy' ) ),
		) );
		return is_page( $allowed );
	}

	public static function is_protected_html_route() {
		if ( is_singular( self::protected_post_types() ) || is_post_type_archive( self::protected_post_types() ) || is_tax( self::protected_taxonomies() ) ) {
			return true;
		}
		$mapped = array_filter( array(
			Click2EU_Settings::page_id( 'job_search' ),
			Click2EU_Settings::page_id( 'company_listings' ),
			Click2EU_Settings::page_id( 'submit_resume' ),
		) );
		if ( $mapped && is_page( $mapped ) ) {
			return true;
		}
		if ( is_singular( 'page' ) ) {
			$content = (string) get_post_field( 'post_content', get_queried_object_id() );
			foreach ( array( 'jobs', 'job', 'job_summary', 'companies', 'resumes', 'submit_resume_form' ) as $shortcode ) {
				if ( has_shortcode( $content, $shortcode ) ) {
					return true;
				}
			}
		}
		if ( is_search() ) {
			$post_type = get_query_var( 'post_type' );
			return $post_type && array_intersect( (array) $post_type, self::protected_post_types() );
		}
		return false;
	}

	public static function authorize_current_html_route() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( ! self::is_protected_html_route() ) {
			return true;
		}
		if ( Click2EU_Auth::is_employer() ) {
			return is_singular( self::RESUME_POST_TYPE ) && ! self::resume_owner_is_effectively_active( get_queried_object_id() )
				? new WP_Error( 'click2eu_resume_hidden', __( 'This resume is not currently available.', 'click2eu-membership' ), array( 'status' => 403 ) )
				: true;
		}
		$membership = self::current_candidate_membership();
		if ( is_wp_error( $membership ) ) {
			return self::denial();
		}
		if ( is_singular( self::JOB_POST_TYPE ) ) {
			return Click2EU_Category_Access::authorize_job( get_current_user_id(), get_queried_object_id() );
		}
		if ( is_tax( 'job_listing_category' ) ) {
			$term = get_queried_object();
			return $term && absint( $term->term_id ) === absint( $membership->category_term_id )
				? true
				: new WP_Error( 'click2eu_job_category_denied', __( 'This job category is outside your membership entitlement.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		if ( is_singular( self::RESUME_POST_TYPE ) && ! self::resume_owner_is_effectively_active( get_queried_object_id() ) ) {
			return new WP_Error( 'click2eu_resume_hidden', __( 'This resume is not currently available.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function active_candidate_ids() {
		global $wpdb;
		$table = Click2EU_Memberships::table_name();
		$now = Click2EU_Time::mysql();
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT candidate_user_id FROM {$table} WHERE status='active' AND (expires_at IS NULL OR expires_at>%s)", $now ) ) );
	}

	public static function resume_owner_is_effectively_active( $resume_id ) {
		$author_id = absint( get_post_field( 'post_author', absint( $resume_id ) ) );
		if ( ! $author_id ) {
			return false;
		}
		$membership = Click2EU_Category_Access::effective_membership( $author_id );
		return ! is_wp_error( $membership );
	}

	public static function approved_company_for_user( $company_id, $user_id ) {
		$company = get_post( absint( $company_id ) );
		if ( ! $company || self::COMPANY_POST_TYPE !== $company->post_type || 'publish' !== $company->post_status ) {
			return new WP_Error( 'click2eu_company_approval_required', __( 'An approved company profile is required before posting a job.', 'click2eu-membership' ) );
		}
		if ( (int) $company->post_author !== absint( $user_id ) && ! current_user_can( 'edit_post', $company->ID ) ) {
			return new WP_Error( 'click2eu_company_ownership', __( 'You may only post jobs for a company you are authorized to manage.', 'click2eu-membership' ) );
		}
		return true;
	}

	public static function user_has_approved_company( $user_id ) {
		$ids = get_posts( array(
			'post_type'      => self::COMPANY_POST_TYPE,
			'post_status'    => 'publish',
			'author'         => absint( $user_id ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		) );
		return ! empty( $ids );
	}
}
