<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Server-side Candidate entitlement and category authorization service. */
final class Click2EU_Category_Access {
	const JOB_TAXONOMY = 'job_listing_category';
	const RESUME_TAXONOMY = 'resume_category';
	const RESUME_MAP_OPTION = 'click2eu_resume_category_map';

	public function __construct() {
		add_filter( 'application_form_validate_fields', array( $this, 'validate_application_form' ), 5, 3 );
		add_filter( 'submit_resume_form_validate_fields', array( $this, 'validate_resume_form' ), 5, 3 );
		add_action( 'wp', array( $this, 'setup_application_ui' ), 5 );
	}

	public static function effective_membership( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! Click2EU_Auth::is_candidate( $user ) ) {
			return new WP_Error( 'click2eu_candidate_required', __( 'A Candidate account is required.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		$membership = Click2EU_Memberships::get_membership_for_user( $user->ID );
		if ( ! $membership || 'active' !== $membership->status || ! Click2EU_Expiration::is_effectively_active( $membership ) ) {
			return new WP_Error( 'click2eu_membership_required', __( 'Your membership is not active.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		$category_id = absint( $membership->category_term_id );
		$term = $category_id && taxonomy_exists( self::JOB_TAXONOMY ) ? get_term( $category_id, self::JOB_TAXONOMY ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'click2eu_membership_category_missing', __( 'Your membership category is unavailable. Please contact support.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		return $membership;
	}

	public static function entitled_category_id( $user_id ) {
		$membership = self::effective_membership( $user_id );
		return is_wp_error( $membership ) ? $membership : absint( $membership->category_term_id );
	}

	public static function authorize_job( $user_id, $job_id ) {
		$category_id = self::entitled_category_id( $user_id );
		if ( is_wp_error( $category_id ) ) { return $category_id; }
		$job = get_post( absint( $job_id ) );
		if ( ! $job || 'job_listing' !== $job->post_type || 'publish' !== $job->post_status ) {
			return new WP_Error( 'click2eu_invalid_job', __( 'This job is unavailable.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		$job_categories = wp_get_object_terms( $job->ID, self::JOB_TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $job_categories ) || empty( $job_categories ) ) {
			return new WP_Error( 'click2eu_job_category_missing', __( 'This job has no authorized category and cannot accept this application.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		return in_array( $category_id, array_map( 'absint', $job_categories ), true )
			? true
			: new WP_Error( 'click2eu_job_category_denied', __( 'This job is outside your selected membership category.', 'click2eu-membership' ), array( 'status' => 403 ) );
	}

	public static function resume_category_map() {
		$map = apply_filters( 'click2eu_resume_category_map', get_option( self::RESUME_MAP_OPTION, array() ) );
		if ( ! is_array( $map ) ) { return array(); }
		$normalized = array();
		foreach ( $map as $job_category_id => $resume_category_ids ) {
			$job_category_id = absint( $job_category_id );
			if ( ! $job_category_id ) { continue; }
			$normalized[ $job_category_id ] = array_values( array_unique( array_filter( array_map( 'absint', (array) $resume_category_ids ) ) ) );
		}
		return $normalized;
	}

	public static function authorized_resume_category_ids( $user_id ) {
		$job_category_id = self::entitled_category_id( $user_id );
		if ( is_wp_error( $job_category_id ) ) { return $job_category_id; }
		if ( ! taxonomy_exists( self::RESUME_TAXONOMY ) ) {
			return new WP_Error( 'click2eu_resume_integration_unavailable', __( 'Resume category authorization is unavailable. Please contact support.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		$map = self::resume_category_map();
		$ids = $map[ $job_category_id ] ?? array();
		$ids = array_values( array_filter( $ids, static function ( $id ) {
			$term = get_term( $id, self::RESUME_TAXONOMY );
			return $term && ! is_wp_error( $term );
		} ) );
		return $ids ?: new WP_Error( 'click2eu_resume_category_unmapped', __( 'Your membership category has no configured resume-category mapping.', 'click2eu-membership' ), array( 'status' => 403 ) );
	}

	public static function authorize_resume_terms( $user_id, $term_ids ) {
		$allowed = self::authorized_resume_category_ids( $user_id );
		if ( is_wp_error( $allowed ) ) { return $allowed; }
		$term_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $term_ids ) ) ) );
		if ( ! $term_ids ) {
			return new WP_Error( 'click2eu_resume_category_required', __( 'Please select a category covered by your membership.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		foreach ( $term_ids as $term_id ) {
			if ( ! in_array( $term_id, $allowed, true ) ) {
				return new WP_Error( 'click2eu_resume_category_denied', __( 'Please select a category covered by your membership.', 'click2eu-membership' ), array( 'status' => 403 ) );
			}
		}
		return true;
	}

	public static function authorize_resume( $user_id, $resume_id ) {
		$resume = get_post( absint( $resume_id ) );
		if ( ! $resume || 'resume' !== $resume->post_type || (int) $resume->post_author !== (int) $user_id ) {
			return new WP_Error( 'click2eu_resume_ownership', __( 'You cannot use or modify this resume.', 'click2eu-membership' ), array( 'status' => 403 ) );
		}
		$terms = wp_get_object_terms( $resume->ID, self::RESUME_TAXONOMY, array( 'fields' => 'ids' ) );
		return is_wp_error( $terms ) ? $terms : self::authorize_resume_terms( $user_id, $terms );
	}

	public static function authorize_application( $user_id, $job_id, $resume_id = 0 ) {
		$job = self::authorize_job( $user_id, $job_id );
		if ( is_wp_error( $job ) ) { return $job; }
		return $resume_id ? self::authorize_resume( $user_id, $resume_id ) : true;
	}

	private static function privileged_workflow() {
		return current_user_can( 'manage_options' ) || Click2EU_Auth::is_employer();
	}

	public function validate_application_form( $valid, $fields, $values ) {
		if ( is_wp_error( $valid ) || self::privileged_workflow() ) { return $valid; }
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return new WP_Error( 'click2eu_candidate_required', __( 'Sign in with an active Candidate membership to apply.', 'click2eu-membership' ) );
		}
		$job_id = absint( wp_unslash( $_POST['job_id'] ?? 0 ) );
		$resume_id = absint( wp_unslash( $_POST['resume_id'] ?? 0 ) );
		$authorized = self::authorize_application( get_current_user_id(), $job_id, $resume_id );
		return true === $authorized ? $valid : $authorized;
	}

	public function validate_resume_form( $valid, $fields, $values ) {
		if ( is_wp_error( $valid ) || self::privileged_workflow() ) { return $valid; }
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			return new WP_Error( 'click2eu_candidate_required', __( 'An active Candidate membership is required.', 'click2eu-membership' ) );
		}
		$resume_id = absint( wp_unslash( $_REQUEST['resume_id'] ?? 0 ) );
		if ( $resume_id ) {
			$resume = get_post( $resume_id );
			if ( ! $resume || (int) $resume->post_author !== get_current_user_id() ) {
				return new WP_Error( 'click2eu_resume_ownership', __( 'You cannot modify this resume.', 'click2eu-membership' ) );
			}
		}
		$term_ids = $values['resume_fields']['resume_category'] ?? array();
		$result = self::authorize_resume_terms( get_current_user_id(), $term_ids );
		return true === $result ? $valid : $result;
	}

	public function setup_application_ui() {
		if ( ! is_singular( 'job_listing' ) || self::privileged_workflow() ) { return; }
		$job_id = get_queried_object_id();
		$result = is_user_logged_in() && Click2EU_Auth::is_candidate() ? self::authorize_job( get_current_user_id(), $job_id ) : new WP_Error( 'click2eu_candidate_required', __( 'Sign in with an active Candidate membership to apply.', 'click2eu-membership' ) );
		if ( true === $result ) { return; }
		add_filter( 'job_manager_show_job_apply_' . absint( $job_id ), '__return_false', 1 );
		add_action( 'job_manager_before_job_apply_' . absint( $job_id ), static function () use ( $result ) {
			echo '<p class="job-manager-error">' . esc_html( $result->get_error_message() ) . '</p>';
		}, 1 );
	}

	public static function mapping_status( $job_category_id ) {
		$job_category_id = absint( $job_category_id );
		if ( ! taxonomy_exists( self::RESUME_TAXONOMY ) ) { return __( 'Resume integration inactive', 'click2eu-membership' ); }
		$ids = self::resume_category_map()[ $job_category_id ] ?? array();
		if ( ! $ids ) { return __( 'Resume category mapping missing', 'click2eu-membership' ); }
		$missing = array_values( array_filter( $ids, static function ( $id ) {
			$term = get_term( $id, self::RESUME_TAXONOMY );
			return ! $term || is_wp_error( $term );
		} ) );
		return $missing
			? sprintf( __( 'Resume mapping contains missing terms: %s', 'click2eu-membership' ), implode( ', ', array_map( 'absint', $missing ) ) )
			: sprintf( __( 'Mapped to resume categories: %s', 'click2eu-membership' ), implode( ', ', array_map( 'absint', $ids ) ) );
	}
}
