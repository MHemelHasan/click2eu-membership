<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Click2EU Employer Verification
 *
 * Handles Employer verification status lifecycle, registration field validation
 * and persistence, auto-login prevention, login blocking, and Admin review UI.
 */
final class Click2EU_Employer_Verification {
	const PAGE_SLUG = 'click2eu-employer-verification';

	// User meta keys
	const META_STATUS       = '_click2eu_employer_verification_status';
	const META_COMPANY_NAME = '_click2eu_company_name';
	const META_REG_NUMBER   = '_click2eu_company_registration_number';
	const META_SUBMITTED_AT = '_click2eu_employer_verification_submitted_at';
	const META_REVIEWED_AT  = '_click2eu_employer_verification_reviewed_at';
	const META_REVIEWED_BY  = '_click2eu_employer_verification_reviewed_by';
	const META_ADMIN_NOTE   = '_click2eu_employer_verification_admin_note';

	// Allowed statuses
	const STATUS_PENDING   = 'pending';
	const STATUS_APPROVED  = 'approved';
	const STATUS_REJECTED  = 'rejected';
	const STATUS_SUSPENDED = 'suspended';

	public function __construct() {
		// Admin UI & Actions
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );

		// Frontend registration assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_registration_assets' ) );

		// Standard WordPress registration form hook
		add_action( 'register_form', array( __CLASS__, 'render_standard_registration_fields' ) );
		add_filter( 'registration_errors', array( __CLASS__, 'validate_standard_registration_fields' ), 10, 3 );

		// Cariera AJAX registration validation (priority 5 runs before Cariera's priority 10)
		add_action( 'wp_ajax_nopriv_cariera_ajax_register', array( $this, 'validate_cariera_employer_registration' ), 5 );

		// User registration persistence
		add_action( 'user_register', array( $this, 'on_user_register' ), 10, 1 );

		// Auto-login interception for newly registered employers
		add_action( 'cariera_core_register_process_completed', array( $this, 'intercept_cariera_registration_response' ), 10, 2 );

		// Authentication pipeline: block unapproved employers
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_unapproved_employer_login' ), 10, 2 );

		// Active session guard: drop unapproved/suspended employer sessions
		add_action( 'init', array( $this, 'guard_active_session' ), 1 );
	}

	/**
	 * Returns all allowed verification statuses.
	 *
	 * @return array
	 */
	public static function allowed_statuses() {
		return array(
			self::STATUS_PENDING,
			self::STATUS_APPROVED,
			self::STATUS_REJECTED,
			self::STATUS_SUSPENDED,
		);
	}

	/**
	 * Gets the verification status for an employer user.
	 *
	 * Fail-closed policy:
	 * - Only users with the 'employer' role have a status.
	 * - Missing, empty, or unknown statuses on employers default to 'pending'.
	 * - Non-employers return an empty string.
	 *
	 * @param WP_User|int $user User object or ID.
	 * @return string Status string or empty string if not an employer.
	 */
	public static function get_status( $user ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', absint( $user ) );
		}
		if ( ! $user || ! ( $user instanceof WP_User ) || ! in_array( 'employer', (array) $user->roles, true ) ) {
			return '';
		}

		$status = get_user_meta( $user->ID, self::META_STATUS, true );
		if ( in_array( $status, array( self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_SUSPENDED ), true ) ) {
			return $status;
		}

		return self::STATUS_PENDING;
	}

	/**
	 * Checks if an employer user is approved.
	 *
	 * @param WP_User|int $user User object or ID.
	 * @return bool
	 */
	public static function is_approved( $user ) {
		return self::STATUS_APPROVED === self::get_status( $user );
	}

	/**
	 * Sets the verification status and records audit metadata.
	 *
	 * @param int         $user_id     Target user ID.
	 * @param string      $status      New status.
	 * @param int         $reviewer_id Reviewer user ID (defaults to current user).
	 * @param string|null $admin_note  Optional internal admin note.
	 * @return bool True on success, false on invalid input.
	 */
	public static function set_status( $user_id, $status, $reviewer_id = 0, $admin_note = null ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || ! in_array( 'employer', (array) $user->roles, true ) ) {
			return false;
		}

		if ( ! in_array( $status, self::allowed_statuses(), true ) ) {
			return false;
		}

		$reviewer_id = absint( $reviewer_id ) ?: get_current_user_id();
		$now         = class_exists( 'Click2EU_Time' ) ? Click2EU_Time::mysql() : current_time( 'mysql', 1 );

		update_user_meta( $user->ID, self::META_STATUS, $status );
		update_user_meta( $user->ID, self::META_REVIEWED_AT, $now );
		update_user_meta( $user->ID, self::META_REVIEWED_BY, $reviewer_id );

		if ( null !== $admin_note ) {
			update_user_meta( $user->ID, self::META_ADMIN_NOTE, sanitize_textarea_field( $admin_note ) );
		}

		return true;
	}

	/**
	 * Retrieves company verification details for an employer.
	 *
	 * @param int $user_id Target user ID.
	 * @return array
	 */
	public static function get_employer_details( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user ) {
			return array();
		}

		return array(
			'user_id'             => $user->ID,
			'user_login'          => $user->user_login,
			'display_name'        => $user->display_name,
			'user_email'          => $user->user_email,
			'status'              => self::get_status( $user ),
			'company_name'        => (string) get_user_meta( $user->ID, self::META_COMPANY_NAME, true ),
			'registration_number' => (string) get_user_meta( $user->ID, self::META_REG_NUMBER, true ),
			'submitted_at'        => (string) get_user_meta( $user->ID, self::META_SUBMITTED_AT, true ) ?: $user->user_registered,
			'reviewed_at'         => (string) get_user_meta( $user->ID, self::META_REVIEWED_AT, true ),
			'reviewed_by'         => absint( get_user_meta( $user->ID, self::META_REVIEWED_BY, true ) ),
			'admin_note'          => (string) get_user_meta( $user->ID, self::META_ADMIN_NOTE, true ),
		);
	}

	/**
	 * Enqueue employer registration script on the frontend.
	 */
	public function enqueue_registration_assets() {
		if ( is_user_logged_in() ) {
			return;
		}

		$js_path = dirname( __DIR__ ) . '/assets/employer-registration.js';
		$ver     = file_exists( $js_path ) ? filemtime( $js_path ) : Click2EU_Config::VERSION;

		wp_enqueue_script(
			'click2eu-employer-registration',
			plugins_url( 'assets/employer-registration.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			$ver,
			true
		);

		wp_localize_script(
			'click2eu-employer-registration',
			'c2eu_reg_i18n',
			array(
				'company_name'             => __( 'Company Name', 'click2eu-membership' ),
				'company_name_placeholder' => __( 'Your Company Name', 'click2eu-membership' ),
				'reg_number'               => __( 'Company Registration Number', 'click2eu-membership' ),
				'reg_number_placeholder'   => __( 'e.g. HRB 12345 or Registration ID', 'click2eu-membership' ),
			)
		);
	}

	/**
	 * Standard WordPress registration fields fallback.
	 */
	public static function render_standard_registration_fields() {
		if ( 'employer' !== get_option( 'default_role' ) ) {
			return;
		}
		?>
		<p class="c2eu-employer-registration-field">
			<label for="c2eu_company_name"><?php esc_html_e( 'Company Name (Employers)', 'click2eu-membership' ); ?><br />
			<input type="text" name="c2eu_company_name" id="c2eu_company_name" class="input" value="" size="25" /></label>
		</p>
		<p class="c2eu-employer-registration-field">
			<label for="c2eu_company_registration_number"><?php esc_html_e( 'Company Registration Number (Employers)', 'click2eu-membership' ); ?><br />
			<input type="text" name="c2eu_company_registration_number" id="c2eu_company_registration_number" class="input" value="" size="25" /></label>
		</p>
		<?php
	}

	/**
	 * Validates the WordPress registration fallback when Employer is the
	 * configured default role. Candidate/default-role registrations remain
	 * untouched and never receive Employer-only requirements.
	 *
	 * @param WP_Error $errors               Registration errors.
	 * @param string   $sanitized_user_login Sanitized login.
	 * @param string   $user_email           Submitted email.
	 * @return WP_Error
	 */
	public static function validate_standard_registration_fields( $errors, $sanitized_user_login, $user_email ) {
		unset( $sanitized_user_login, $user_email );
		if ( 'employer' !== get_option( 'default_role' ) ) {
			return $errors;
		}

		$company_name = self::submitted_company_name();
		$reg_number   = self::submitted_registration_number();
		if ( '' === $company_name ) {
			$errors->add( 'click2eu_company_name_required', __( 'Company Name is required for Employer registration.', 'click2eu-membership' ) );
		} elseif ( 160 < self::text_length( $company_name ) ) {
			$errors->add( 'click2eu_company_name_too_long', __( 'Company Name must be 160 characters or fewer.', 'click2eu-membership' ) );
		}
		if ( '' === $reg_number ) {
			$errors->add( 'click2eu_registration_number_required', __( 'Company Registration Number is required for Employer registration.', 'click2eu-membership' ) );
		} elseif ( 100 < self::text_length( $reg_number ) ) {
			$errors->add( 'click2eu_registration_number_too_long', __( 'Company Registration Number must be 100 characters or fewer.', 'click2eu-membership' ) );
		}
		return $errors;
	}

	/**
	 * Validates employer registration fields during Cariera AJAX registration.
	 * Runs at priority 5 before Cariera's priority 10 handler.
	 */
	public function validate_cariera_employer_registration() {
		// Only check if action is cariera_ajax_register.
		if ( 'cariera_ajax_register' !== ( $_REQUEST['action'] ?? '' ) ) {
			return;
		}

		$role = isset( $_POST['cariera_user_role'] ) ? sanitize_key( wp_unslash( $_POST['cariera_user_role'] ) ) : '';

		// Candidate registration is completely untouched.
		if ( 'employer' !== $role ) {
			return;
		}

		// Verify nonce if present.
		if ( isset( $_POST['register_security'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['register_security'] ) ), 'cariera-ajax-register-nonce' ) ) {
			return; // Let Cariera output its session expired message.
		}

		$company_name = self::submitted_company_name();
		$reg_number   = self::submitted_registration_number();

		if ( '' === $company_name ) {
			wp_send_json(
				array(
					'register' => false,
					'message'  => '<span class="job-manager-message error">' . esc_html__( 'Company Name is required for Employer registration.', 'click2eu-membership' ) . '</span>',
				)
			);
			return;
		}

		if ( '' === $reg_number ) {
			wp_send_json(
				array(
					'register' => false,
					'message'  => '<span class="job-manager-message error">' . esc_html__( 'Company Registration Number is required for Employer registration.', 'click2eu-membership' ) . '</span>',
				)
			);
			return;
		}
		if ( 160 < self::text_length( $company_name ) || 100 < self::text_length( $reg_number ) ) {
			wp_send_json(
				array(
					'register' => false,
					'message'  => '<span class="job-manager-message error">' . esc_html__( 'Company details are too long. Please shorten them and try again.', 'click2eu-membership' ) . '</span>',
				)
			);
		}
	}

	private static function submitted_company_name() {
		return sanitize_text_field( wp_unslash( $_POST['c2eu_company_name'] ?? $_POST['company_name'] ?? '' ) );
	}

	private static function submitted_registration_number() {
		return sanitize_text_field( wp_unslash( $_POST['c2eu_company_registration_number'] ?? $_POST['company_registration_number'] ?? '' ) );
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * Persists verification status and company metadata when an employer user is created.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user_register( $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || ! in_array( 'employer', (array) $user->roles, true ) ) {
			return;
		}

		// Initialize status as pending.
		update_user_meta( $user->ID, self::META_STATUS, self::STATUS_PENDING );

		$company_name = self::submitted_company_name();
		$reg_number   = self::submitted_registration_number();

		if ( '' !== $company_name ) {
			update_user_meta( $user->ID, self::META_COMPANY_NAME, $company_name );
		}
		if ( '' !== $reg_number ) {
			update_user_meta( $user->ID, self::META_REG_NUMBER, $reg_number );
		}

		$now = class_exists( 'Click2EU_Time' ) ? Click2EU_Time::mysql() : current_time( 'mysql', 1 );
		update_user_meta( $user->ID, self::META_SUBMITTED_AT, $now );
	}

	/**
	 * Intercepts Cariera's registration response for newly registered employers.
	 *
	 * Destroys any session created by Cariera auto-login and sends an explicit
	 * pending verification response so that client-side JavaScript does not
	 * attempt to redirect the employer into a dashboard.
	 *
	 * @param array $final   Response array from Cariera.
	 * @param int   $user_id Newly registered user ID.
	 */
	public function intercept_cariera_registration_response( $final, $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || ! in_array( 'employer', (array) $user->roles, true ) ) {
			return; // Candidates and other roles pass through untouched.
		}

		// Kill any session or auth cookie that Cariera auto-login created.
		if ( function_exists( 'wp_destroy_current_session' ) ) {
			wp_destroy_current_session();
		}
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );

		// Return verification-pending status. status: true tells Cariera JS not to auto-redirect.
		wp_send_json(
			array(
				'register' => true,
				'status'   => true,
				'message'  => '<span class="job-manager-message success">' . esc_html__( 'Registration submitted! Your employer account is pending administrative verification. You will be notified once an administrator approves your account.', 'click2eu-membership' ) . '</span>',
				'role'     => 'employer',
			)
		);
	}

	/**
	 * Blocks unapproved employers from logging in.
	 *
	 * @param WP_User|WP_Error $user     User object or authentication error.
	 * @param string           $password Password string.
	 * @return WP_User|WP_Error
	 */
	public static function block_unapproved_employer_login( $user, $password ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		// Only check users with the 'employer' role.
		if ( ! in_array( 'employer', (array) $user->roles, true ) ) {
			return $user;
		}

		// Administrators are always exempt.
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return $user;
		}

		$status = self::get_status( $user->ID );

		if ( self::STATUS_APPROVED === $status ) {
			return $user;
		}

		if ( self::STATUS_REJECTED === $status ) {
			return new WP_Error(
				'click2eu_employer_rejected',
				__( 'Your employer account registration was not approved. Please contact Click2EU support for details.', 'click2eu-membership' )
			);
		}

		if ( self::STATUS_SUSPENDED === $status ) {
			return new WP_Error(
				'click2eu_employer_suspended',
				__( 'Your employer account has been suspended. Please contact Click2EU support for assistance.', 'click2eu-membership' )
			);
		}

		// Default is pending.
		return new WP_Error(
			'click2eu_employer_pending',
			__( 'Your employer account is currently pending administrative verification. You will be able to log in once your account has been approved.', 'click2eu-membership' )
		);
	}

	/**
	 * Active session guard: drops unapproved or suspended employer sessions on subsequent requests.
	 */
	public function guard_active_session() {
		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! in_array( 'employer', (array) $user->roles, true ) ) {
			return;
		}

		$status = self::get_status( $user->ID );
		if ( self::STATUS_APPROVED !== $status ) {
			if ( function_exists( 'wp_destroy_current_session' ) ) {
				wp_destroy_current_session();
			}
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
		}
	}

	/**
	 * Registers the Employer Verification menu page under Click2EU Memberships.
	 */
	public function register_admin_page() {
		add_submenu_page(
			Click2EU_Admin::PAGE_SLUG,
			__( 'Employer Verification', 'click2eu-membership' ),
			__( 'Employer Verification', 'click2eu-membership' ),
			Click2EU_Admin::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Handles admin review actions (approve, reject, suspend, set_pending).
	 */
	public function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( Click2EU_Admin::CAPABILITY ) ) {
			return;
		}

		if ( empty( $_POST['c2eu_verification_action'] ) || empty( $_POST['target_user_id'] ) ) {
			return;
		}

		$user_id = absint( $_POST['target_user_id'] );
		check_admin_referer( 'c2eu_verify_employer_' . $user_id, 'c2eu_nonce' );

		$action = sanitize_key( $_POST['c2eu_verification_action'] );
		$note   = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : null;

		$map = array(
			'approve'     => self::STATUS_APPROVED,
			'reject'      => self::STATUS_REJECTED,
			'suspend'     => self::STATUS_SUSPENDED,
			'set_pending' => self::STATUS_PENDING,
		);

		if ( 'save_note' === $action ) {
			$current_st = self::get_status( $user_id );
			$success    = self::set_status( $user_id, $current_st, get_current_user_id(), $note );
			$msg        = $success ? 'note_saved_ok' : 'error';
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'c2eu_msg' => $msg ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! isset( $map[ $action ] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'c2eu_msg' => 'invalid_action' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$success = self::set_status( $user_id, $map[ $action ], get_current_user_id(), $note );

		$msg = $success ? $action . '_ok' : 'error';
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'c2eu_msg' => $msg ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Renders the Employer Verification admin screen.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( Click2EU_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to access this screen.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}

		$allowed_tabs = array( 'all', 'pending', 'approved', 'rejected', 'suspended' );
		$current_tab  = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : 'all';
		$current_tab  = in_array( $current_tab, $allowed_tabs, true ) ? $current_tab : 'all';
		$paged        = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page     = 25;
		$counts       = array();
		foreach ( $allowed_tabs as $status ) {
			$count_query = new WP_User_Query( self::employer_query_args( $status, 1, 0 ) );
			$counts[ $status ] = absint( $count_query->get_total() );
		}

		$query_args = self::employer_query_args( $current_tab, $per_page, ( $paged - 1 ) * $per_page );
		$user_query = new WP_User_Query( $query_args );
		$total_items = absint( $user_query->get_total() );
		$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
		if ( $paged > $total_pages ) {
			$paged = $total_pages;
			$query_args['offset'] = ( $paged - 1 ) * $per_page;
			$user_query = new WP_User_Query( $query_args );
		}
		$details_list = array_map( static function ( $employer ) {
			return self::get_employer_details( $employer->ID );
		}, $user_query->get_results() );

		// Notice display
		$notice_key = sanitize_key( $_GET['c2eu_msg'] ?? '' );
		?>
		<div class="wrap c2eu-admin-verification">
			<h1><?php esc_html_e( 'Employer Verification', 'click2eu-membership' ); ?></h1>
			<p><?php esc_html_e( 'Review and manage Employer account registrations. Unapproved employers cannot log in or view candidate resumes.', 'click2eu-membership' ); ?></p>

			<?php if ( $notice_key ) : ?>
				<?php if ( strpos( $notice_key, '_ok' ) !== false ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Employer verification status updated successfully.', 'click2eu-membership' ); ?></p></div>
				<?php elseif ( 'error' === $notice_key ) : ?>
					<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Action could not be completed. Please verify user role and nonces.', 'click2eu-membership' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<nav class="c2eu-admin-filters" style="margin: 18px 0; display: flex; gap: 8px;">
				<?php
				$tabs = array(
					'all'       => __( 'All', 'click2eu-membership' ),
					'pending'   => __( 'Pending Review', 'click2eu-membership' ),
					'approved'  => __( 'Approved', 'click2eu-membership' ),
					'rejected'  => __( 'Rejected', 'click2eu-membership' ),
					'suspended' => __( 'Suspended', 'click2eu-membership' ),
				);
				foreach ( $tabs as $k => $label ) {
					$url = add_query_arg( array( 'page' => self::PAGE_SLUG, 'status_filter' => $k ), admin_url( 'admin.php' ) );
					$is_cur = $current_tab === $k;
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="c2eu-filter-tab <?php echo $is_cur ? 'current' : ''; ?>" style="padding: 7px 12px; border: 1px solid #c3c4c7; border-radius: 4px; text-decoration: none; background: <?php echo $is_cur ? '#2271b1' : '#fff'; ?>; color: <?php echo $is_cur ? '#fff' : '#2271b1'; ?>;">
						<?php echo esc_html( $label ); ?> (<?php echo esc_html( $counts[ $k ] ); ?>)
					</a>
				<?php } ?>
			</nav>

			<div class="c2eu-admin-table-wrap">
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width: 60px;"><?php esc_html_e( 'ID', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Employer', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Company Name', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Registration No.', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Submitted', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Status', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Reviewed', 'click2eu-membership' ); ?></th>
							<th><?php esc_html_e( 'Internal Admin Note', 'click2eu-membership' ); ?></th>
							<th style="min-width: 220px;"><?php esc_html_e( 'Actions', 'click2eu-membership' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $details_list ) ) : ?>
							<tr>
								<td colspan="9" style="text-align: center; padding: 24px; color: #64748b;">
									<?php esc_html_e( 'No employer accounts found in this view.', 'click2eu-membership' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $details_list as $emp ) : ?>
								<?php
								$reviewer_name = '';
								if ( $emp['reviewed_by'] ) {
									$rev = get_userdata( $emp['reviewed_by'] );
									$reviewer_name = $rev ? $rev->display_name : '#' . $emp['reviewed_by'];
								}

								$badge_colors = array(
									'pending'   => 'background: #fffbeb; color: #b45309; border: 1px solid #fde68a;',
									'approved'  => 'background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;',
									'rejected'  => 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;',
									'suspended' => 'background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;',
								);
								$style = $badge_colors[ $emp['status'] ] ?? '';
								?>
								<tr>
									<td>#<?php echo esc_html( $emp['user_id'] ); ?></td>
									<td>
										<strong><?php echo esc_html( $emp['display_name'] ); ?></strong><br />
										<small><?php echo esc_html( $emp['user_email'] ); ?></small><br />
										<small style="color: #64748b;"><?php echo esc_html( $emp['user_login'] ); ?></small>
									</td>
									<td>
										<strong><?php echo esc_html( $emp['company_name'] ?: '—' ); ?></strong>
									</td>
									<td>
										<code><?php echo esc_html( $emp['registration_number'] ?: '—' ); ?></code>
									</td>
									<td>
										<small><?php echo esc_html( $emp['submitted_at'] ?: '—' ); ?></small>
									</td>
									<td>
										<span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; text-transform: uppercase; <?php echo esc_attr( $style ); ?>">
											<?php echo esc_html( $emp['status'] ); ?>
										</span>
									</td>
									<td>
										<?php if ( $emp['reviewed_at'] ) : ?>
											<small><?php echo esc_html( $emp['reviewed_at'] ); ?></small><br />
											<small style="color: #64748b;"><?php printf( esc_html__( 'by %s', 'click2eu-membership' ), esc_html( $reviewer_name ) ); ?></small>
										<?php else : ?>
											<small style="color: #94a3b8;">—</small>
										<?php endif; ?>
									</td>
									<td>
										<form id="c2eu_form_<?php echo esc_attr( $emp['user_id'] ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>">
											<?php wp_nonce_field( 'c2eu_verify_employer_' . $emp['user_id'], 'c2eu_nonce' ); ?>
											<input type="hidden" name="target_user_id" value="<?php echo esc_attr( $emp['user_id'] ); ?>" />
											<textarea name="admin_note" rows="2" style="width: 100%; min-width: 160px; font-size: 12px; margin-bottom: 4px;" placeholder="<?php esc_attr_e( 'Private admin note...', 'click2eu-membership' ); ?>"><?php echo esc_textarea( $emp['admin_note'] ); ?></textarea>
											<button type="submit" name="c2eu_verification_action" value="save_note" class="button button-small" style="font-size: 11px;">
												<?php esc_html_e( 'Save Note Only', 'click2eu-membership' ); ?>
											</button>
										</form>
									</td>
									<td>
										<div style="display: flex; gap: 4px; flex-wrap: wrap;">
											<?php if ( 'approved' !== $emp['status'] ) : ?>
												<button type="submit" form="c2eu_form_<?php echo esc_attr( $emp['user_id'] ); ?>" name="c2eu_verification_action" value="approve" class="button button-primary button-small" style="background: #059669; border-color: #047857;">
													<?php esc_html_e( 'Approve', 'click2eu-membership' ); ?>
												</button>
											<?php endif; ?>

											<?php if ( 'rejected' !== $emp['status'] ) : ?>
												<button type="submit" form="c2eu_form_<?php echo esc_attr( $emp['user_id'] ); ?>" name="c2eu_verification_action" value="reject" class="button button-small" style="color: #dc2626; border-color: #fca5a5;">
													<?php esc_html_e( 'Reject', 'click2eu-membership' ); ?>
												</button>
											<?php endif; ?>

											<?php if ( 'suspended' !== $emp['status'] ) : ?>
												<button type="submit" form="c2eu_form_<?php echo esc_attr( $emp['user_id'] ); ?>" name="c2eu_verification_action" value="suspend" class="button button-small" style="color: #d97706; border-color: #fcd34d;">
													<?php esc_html_e( 'Suspend', 'click2eu-membership' ); ?>
												</button>
											<?php endif; ?>

											<?php if ( 'pending' !== $emp['status'] ) : ?>
												<button type="submit" form="c2eu_form_<?php echo esc_attr( $emp['user_id'] ); ?>" name="c2eu_verification_action" value="set_pending" class="button button-small">
													<?php esc_html_e( 'Set Pending', 'click2eu-membership' ); ?>
												</button>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s employer', '%s employers', $total_items, 'click2eu-membership' ), number_format_i18n( $total_items ) ) ); ?></span>
					<?php
					$base = str_replace( '999999999', '%#%', esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'status_filter' => $current_tab, 'paged' => 999999999 ), admin_url( 'admin.php' ) ) ) );
					echo wp_kses_post( paginate_links( array( 'base' => $base, 'current' => $paged, 'total' => $total_pages ) ) );
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function employer_query_args( $status, $number, $offset ) {
		$args = array(
			'role'        => 'employer',
			'orderby'     => 'registered',
			'order'       => 'DESC',
			'number'      => absint( $number ),
			'offset'      => absint( $offset ),
			'count_total' => true,
		);
		if ( 'all' === $status ) {
			return $args;
		}
		if ( self::STATUS_PENDING === $status ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => self::META_STATUS, 'compare' => 'NOT EXISTS' ),
				array( 'key' => self::META_STATUS, 'value' => array( self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_SUSPENDED ), 'compare' => 'NOT IN' ),
			);
			return $args;
		}
		$args['meta_key']   = self::META_STATUS;
		$args['meta_value'] = $status;
		return $args;
	}
}
