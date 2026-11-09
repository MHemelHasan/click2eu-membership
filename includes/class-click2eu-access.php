<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Enforcement adapters for the centralized Phase-1 route policy. */
final class Click2EU_Access {
	private const LISTING_AJAX_ACTIONS = array( 'job_manager_get_listings', 'cariera_get_companies', 'resume_manager_get_resumes' );
	private const MESSAGE_AJAX_ACTIONS = array( 'cariera_send_message', 'cariera_upload_message_media' );

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'guard_frontend' ), 1 );
		add_action( 'init', array( $this, 'guard_post_actions' ), 0 );
		add_action( 'wp', array( $this, 'guard_resume_dashboard_actions' ), 0 );
		add_filter( 'rest_pre_dispatch', array( $this, 'guard_rest' ), 5, 3 );
		add_action( 'pre_get_posts', array( $this, 'filter_queries' ), 5 );
		add_filter( 'job_manager_get_listings', array( $this, 'filter_job_listing_query' ), 5, 2 );
		add_filter( 'cariera_get_companies', array( $this, 'filter_company_query' ), 5, 2 );
		add_filter( 'resume_manager_get_resumes', array( $this, 'filter_resume_query' ), 5, 2 );
		add_filter( 'resume_manager_my_resume_actions', array( $this, 'filter_resume_dashboard_actions' ), 5, 2 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_sitemap_taxonomies' ) );
		add_filter( 'wp_robots', array( $this, 'private_route_robots' ) );

		foreach ( self::LISTING_AJAX_ACTIONS as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'guard_listing_ajax' ), 0 );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'guard_listing_ajax' ), 0 );
		}
		foreach ( self::MESSAGE_AJAX_ACTIONS as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'guard_message_ajax' ), 0 );
		}

		add_filter( 'pre_option_cariera_company_submission_requires_approval', array( $this, 'force_company_moderation' ) );
		add_filter( 'job_manager_user_can_submit_job_listing', array( $this, 'employer_can_submit_job' ), 20 );
		add_filter( 'submit_job_form_validate_fields', array( $this, 'validate_job_company' ), 120, 3 );
		add_filter( 'rest_pre_insert_company', array( $this, 'moderate_rest_company' ), 10, 2 );
	}

	public function guard_frontend() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || self::is_system_request() ) {
			return;
		}
		if ( Click2EU_Route_Policy::is_commerce_request() ) {
			return;
		}
		if ( is_page( Click2EU_Settings::page_id( 'join' ) ) && Click2EU_Auth::is_candidate() && Click2EU_Route_Policy::can_access_paid_candidate_features() && empty( $_GET['renew_membership'] ) && empty( $_GET['view_plans'] ) ) {
			$dashboard = Click2EU_Settings::page_url( 'candidate_dashboard' );
			if ( $dashboard && ! self::is_current_url( $dashboard ) ) {
				wp_safe_redirect( $dashboard, 302, 'Click2EU Membership' );
				exit;
			}
		}
		if ( Click2EU_Route_Policy::is_protected_html_route() ) {
			nocache_headers();
		}
		$result = Click2EU_Route_Policy::authorize_current_html_route();
		if ( true !== $result ) {
			if ( Click2EU_Auth::is_employer() ) {
				$this->die_denial( $result );
			}
			$this->redirect_to_join( $result );
		}
	}

	private static function is_system_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? wp_unslash( $_SERVER['SCRIPT_NAME'] ) : '';
		return false !== strpos( $script, 'wp-login.php' ) || false !== strpos( $script, 'wp-cron.php' );
	}

	private function redirect_to_join( $error ) {
		if ( is_page( Click2EU_Settings::page_id( 'join' ) ) ) {
			// A Join mapping that also resolves as protected must fail closed rather
			// than expose data or redirect back to itself forever.
			$this->die_denial( $error );
		}
		$join = Click2EU_Settings::page_url( 'join' );
		$join = $join ?: home_url( '/join-us/' );
		if ( self::is_current_url( $join ) ) {
			$this->die_denial( $error );
		}
		$reason = is_wp_error( $error ) && 'click2eu_job_category_denied' === $error->get_error_code() ? 'category' : 'required';
		wp_safe_redirect( add_query_arg( 'membership', $reason, $join ), 302, 'Click2EU Membership' );
		exit;
	}

	private static function is_current_url( $target ) {
		$current_path = trailingslashit( (string) wp_parse_url( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH ) );
		$target_path  = trailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) );
		return $current_path && $target_path && $current_path === $target_path;
	}

	public function guard_listing_ajax() {
		if ( Click2EU_Route_Policy::is_privileged_actor() || Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			return;
		}
		$this->send_json_denial( Click2EU_Route_Policy::denial() );
	}

	public function guard_message_ajax() {
		if ( Click2EU_Route_Policy::is_privileged_actor() || Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			return;
		}
		$this->send_json_denial( Click2EU_Route_Policy::denial() );
	}

	private function send_json_denial( $error ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : array();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 403;
		wp_send_json_error( array(
			'code'    => is_wp_error( $error ) ? $error->get_error_code() : 'click2eu_denied',
			'message' => is_wp_error( $error ) ? $error->get_error_message() : __( 'Access denied.', 'click2eu-membership' ),
		), $status );
	}

	public function guard_post_actions() {
		if ( empty( $_POST ) || current_user_can( 'manage_options' ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
		if ( in_array( $action, self::MESSAGE_AJAX_ACTIONS, true ) && ! Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			$this->send_json_denial( Click2EU_Route_Policy::denial() );
		}

		$is_resume = isset( $_POST['submit_resume'] );
		$is_application = isset( $_POST['cariera_addons_resumes_apply_with_resume'] ) || isset( $_POST['cariera_addons_send_application'] );
		if ( ! $is_resume && ! $is_application ) {
			return;
		}
		if ( Click2EU_Auth::is_employer() ) {
			return;
		}
		if ( ! is_user_logged_in() || ! Click2EU_Auth::is_candidate() ) {
			$this->die_denial( Click2EU_Route_Policy::denial() );
		}
		if ( $is_application ) {
			$result = Click2EU_Category_Access::authorize_application( get_current_user_id(), absint( wp_unslash( $_POST['job_id'] ?? 0 ) ), absint( wp_unslash( $_POST['resume_id'] ?? 0 ) ) );
		} else {
			$resume_id = absint( wp_unslash( $_REQUEST['resume_id'] ?? 0 ) );
			if ( $resume_id ) {
				$resume = get_post( $resume_id );
				$result = $resume && (int) $resume->post_author === get_current_user_id()
					? Click2EU_Category_Access::authorize_resume_terms( get_current_user_id(), wp_unslash( $_POST['resume_category'] ?? array() ) )
					: new WP_Error( 'click2eu_resume_ownership', __( 'You cannot modify this resume.', 'click2eu-membership' ), array( 'status' => 403 ) );
			} else {
				$result = Click2EU_Category_Access::authorize_resume_terms( get_current_user_id(), wp_unslash( $_POST['resume_category'] ?? array() ) );
			}
		}
		if ( is_wp_error( $result ) ) {
			$this->die_denial( $result );
		}
	}

	public function guard_resume_dashboard_actions() {
		if ( ! is_page( Click2EU_Settings::page_id( 'my_resumes' ) ) || empty( $_REQUEST['action'] ) || current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( Click2EU_Auth::is_candidate() && ! Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			$this->die_denial( Click2EU_Route_Policy::denial() );
		}
	}

	private function die_denial( $error ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : array();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 403;
		wp_die( esc_html( is_wp_error( $error ) ? $error->get_error_message() : __( 'Access denied.', 'click2eu-membership' ) ), esc_html__( 'Membership required', 'click2eu-membership' ), array( 'response' => $status ) );
	}

	public function filter_queries( $query ) {
		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! $query instanceof WP_Query ) {
			return;
		}
		// Let the singular request resolve so template_redirect can return the
		// correct Join/category response instead of WordPress turning it into a 404.
		if ( $query->is_main_query() && ( $query->is_singular() || $query->get( 'name' ) || $query->get( 'p' ) ) ) {
			return;
		}
		$post_type = $query->get( 'post_type' );
		$types = $post_type ? (array) $post_type : array();
		if ( ( $query->is_search() || $query->is_feed() ) && ! $types ) {
			$query->set( 'post_type', array( 'post', 'page' ) );
			return;
		}
		if ( ! array_intersect( $types, Click2EU_Route_Policy::protected_post_types() ) ) {
			return;
		}
		if ( ! Click2EU_Route_Policy::is_privileged_actor() && ! Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		if ( in_array( Click2EU_Route_Policy::JOB_POST_TYPE, $types, true ) && Click2EU_Auth::is_candidate() ) {
			$this->apply_job_category_query( $query );
		}
		if ( in_array( Click2EU_Route_Policy::RESUME_POST_TYPE, $types, true ) && ! current_user_can( 'manage_options' ) ) {
			$query->set( 'author__in', Click2EU_Route_Policy::active_candidate_ids() ?: array( 0 ) );
		}
	}

	private function apply_job_category_query( $query ) {
		$category_id = Click2EU_Category_Access::entitled_category_id( get_current_user_id() );
		if ( is_wp_error( $category_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}
		$tax_query = (array) $query->get( 'tax_query' );
		$tax_query[] = array( 'taxonomy' => Click2EU_Category_Access::JOB_TAXONOMY, 'field' => 'term_id', 'terms' => array( absint( $category_id ) ), 'include_children' => false );
		$query->set( 'tax_query', $tax_query );
	}

	public function filter_job_listing_query( $query_args, $args ) {
		if ( Click2EU_Route_Policy::is_privileged_actor() ) {
			return $query_args;
		}
		if ( ! Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			$query_args['post__in'] = array( 0 );
			return $query_args;
		}
		$category_id = Click2EU_Category_Access::entitled_category_id( get_current_user_id() );
		if ( is_wp_error( $category_id ) ) {
			$query_args['post__in'] = array( 0 );
			return $query_args;
		}
		$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? (array) $query_args['tax_query'] : array();
		$query_args['tax_query'][] = array( 'taxonomy' => Click2EU_Category_Access::JOB_TAXONOMY, 'field' => 'term_id', 'terms' => array( absint( $category_id ) ), 'include_children' => false );
		return $query_args;
	}

	public function filter_resume_query( $query_args, $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			$query_args['author__in'] = Click2EU_Route_Policy::active_candidate_ids() ?: array( 0 );
		}
		return $query_args;
	}

	public function filter_company_query( $query_args, $args ) {
		if ( ! Click2EU_Route_Policy::is_privileged_actor() && ! Click2EU_Route_Policy::can_access_paid_candidate_features() ) {
			$query_args['post__in'] = array( 0 );
		}
		return $query_args;
	}

	public function filter_resume_dashboard_actions( $actions, $resume ) {
		return Click2EU_Auth::is_candidate() && ! Click2EU_Route_Policy::can_access_paid_candidate_features() ? array() : $actions;
	}

	public function filter_sitemap_post_types( $post_types ) {
		foreach ( Click2EU_Route_Policy::protected_post_types() as $post_type ) {
			unset( $post_types[ $post_type ] );
		}
		return $post_types;
	}

	public function filter_sitemap_taxonomies( $taxonomies ) {
		foreach ( Click2EU_Route_Policy::protected_taxonomies() as $taxonomy ) {
			unset( $taxonomies[ $taxonomy ] );
		}
		return $taxonomies;
	}

	public function private_route_robots( $robots ) {
		if ( Click2EU_Route_Policy::is_protected_html_route() ) {
			$robots['noindex'] = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	public function guard_rest( $result, $server, $request ) {
		if ( is_wp_error( $result ) || ! $this->is_protected_rest_route( $request->get_route() ) || current_user_can( 'manage_options' ) ) {
			return $result;
		}
		$method = strtoupper( $request->get_method() );
		if ( Click2EU_Auth::is_employer() ) {
			if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && false !== strpos( $request->get_route(), 'job' ) ) {
				$company_id = absint( $request->get_param( '_company_manager_id' ) ?: $request->get_param( 'company_manager_id' ) );
				if ( ! $company_id && $request->get_param( 'id' ) ) {
					$company_id = absint( get_post_meta( absint( $request->get_param( 'id' ) ), '_company_manager_id', true ) );
				}
				$authorized = Click2EU_Route_Policy::approved_company_for_user( $company_id, get_current_user_id() );
				return is_wp_error( $authorized ) ? $this->rest_error( $authorized ) : $result;
			}
			return $result;
		}
		$membership = Click2EU_Route_Policy::current_candidate_membership();
		if ( is_wp_error( $membership ) ) {
			return $this->rest_error( Click2EU_Route_Policy::denial() );
		}
		if ( 'GET' === $method && preg_match( '#/(?:jobs?|job-listings?)/(\d+)/?$#', $request->get_route(), $matches ) ) {
			$authorized = Click2EU_Category_Access::authorize_job( get_current_user_id(), absint( $matches[1] ) );
			return is_wp_error( $authorized ) ? $this->rest_error( $authorized ) : $result;
		}
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) && preg_match( '#/(?:resumes?)(?:/(\d+))?/?$#', $request->get_route(), $matches ) ) {
			$resume_id = absint( $matches[1] ?? $request->get_param( 'id' ) );
			if ( $resume_id ) {
				$resume = get_post( $resume_id );
				if ( ! $resume || (int) $resume->post_author !== get_current_user_id() ) {
					return $this->rest_error( new WP_Error( 'click2eu_resume_ownership', __( 'You cannot modify this resume.', 'click2eu-membership' ), array( 'status' => 403 ) ) );
				}
			}
			$term_ids = $request->get_param( 'resume_category' );
			if ( null === $term_ids && $resume_id ) {
				$term_ids = wp_get_object_terms( $resume_id, Click2EU_Category_Access::RESUME_TAXONOMY, array( 'fields' => 'ids' ) );
			}
			$authorized = Click2EU_Category_Access::authorize_resume_terms( get_current_user_id(), (array) $term_ids );
			return is_wp_error( $authorized ) ? $this->rest_error( $authorized ) : $result;
		}
		return $this->rest_error( new WP_Error( 'click2eu_protected_rest', __( 'This protected API route is unavailable for the current account.', 'click2eu-membership' ), array( 'status' => 403 ) ) );
	}

	private function is_protected_rest_route( $route ) {
		return (bool) preg_match( '#/(?:jobs?|job-listings?|companies?|resumes?)(?:/|$)#i', (string) $route );
	}

	private function rest_error( $error ) {
		$data = $error->get_error_data();
		$status = is_array( $data ) && ! empty( $data['status'] ) ? absint( $data['status'] ) : 403;
		return new WP_Error( $error->get_error_code(), $error->get_error_message(), array( 'status' => $status ) );
	}

	public function force_company_moderation( $pre ) {
		return '1';
	}

	public function employer_can_submit_job( $can_submit ) {
		if ( current_user_can( 'manage_options' ) || ! Click2EU_Auth::is_employer() ) {
			return $can_submit;
		}
		return $can_submit && Click2EU_Route_Policy::user_has_approved_company( get_current_user_id() );
	}

	public function validate_job_company( $valid, $fields, $values ) {
		if ( is_wp_error( $valid ) || ! $valid || current_user_can( 'manage_options' ) || ! Click2EU_Auth::is_employer() ) {
			return $valid;
		}
		$submission = sanitize_key( wp_unslash( $_POST['company_submission'] ?? '' ) );
		if ( 'new_company' === $submission ) {
			return new WP_Error( 'click2eu_company_approval_required', __( 'Submit the company first and wait for administrator approval before posting a job.', 'click2eu-membership' ) );
		}
		$company_id = absint( wp_unslash( $_POST['company_manager_id'] ?? $_POST['_company_manager_id'] ?? 0 ) );
		if ( ! $company_id ) {
			$job_id = absint( wp_unslash( $_REQUEST['job_id'] ?? 0 ) );
			$company_id = $job_id ? absint( get_post_meta( $job_id, '_company_manager_id', true ) ) : 0;
		}
		$authorized = Click2EU_Route_Policy::approved_company_for_user( $company_id, get_current_user_id() );
		return is_wp_error( $authorized ) ? $authorized : $valid;
	}

	public function moderate_rest_company( $prepared_post, $request ) {
		if ( current_user_can( 'manage_options' ) || ! Click2EU_Auth::is_employer() || is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}
		$prepared_post->post_status = 'pending';
		$prepared_post->post_author = get_current_user_id();
		return $prepared_post;
	}
}
