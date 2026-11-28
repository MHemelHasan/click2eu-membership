<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Secure approved-Employer-to-Candidate contact workflow for single Resumes. */
final class Click2EU_Candidate_Contact {
	const ACTION = 'click2eu_contact_candidate';
	const NONCE_ACTION = 'click2eu_contact_candidate';
	const COOLDOWN_SECONDS = 300;
	const HOURLY_LIMIT = 10;
	private static $rendered = false;

	public function __construct() {
		add_action( 'wp', array( $this, 'replace_vendor_invite_action' ), 1 );
		add_action( 'single_resume_meta_end', array( $this, 'render_contact_action' ), 100 );
		add_action( 'cariera_candidate_detail_actions', array( $this, 'render_contact_action' ), 50 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submission' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submission' ) );
	}

	/** Remove only Cariera's exact Invite Candidate display callbacks. */
	public function replace_vendor_invite_action() {
		$class = 'Cariera_Core\\Extensions\\Resume\\Invite_Candidate';
		if ( ! class_exists( $class ) || ! is_callable( array( $class, 'instance' ) ) ) {
			return;
		}
		$instance = $class::instance();
		remove_action( 'single_resume_meta_end', array( $instance, 'display_invite_candidate' ), 100 );
		remove_action( 'cariera_candidate_detail_actions', array( $instance, 'display_invite_candidate' ), 50 );
	}

	public function enqueue_assets() {
		if ( ! is_singular( Click2EU_Route_Policy::RESUME_POST_TYPE ) ) {
			return;
		}
		$css = dirname( __DIR__ ) . '/assets/candidate-contact.css';
		$js  = dirname( __DIR__ ) . '/assets/candidate-contact.js';
		wp_enqueue_style( 'click2eu-candidate-contact', plugins_url( 'assets/candidate-contact.css', dirname( __FILE__ ) ), array(), file_exists( $css ) ? filemtime( $css ) : Click2EU_Config::VERSION );
		wp_enqueue_script( 'click2eu-candidate-contact', plugins_url( 'assets/candidate-contact.js', dirname( __FILE__ ) ), array(), file_exists( $js ) ? filemtime( $js ) : Click2EU_Config::VERSION, true );
	}

	public function render_contact_action() {
		if ( self::$rendered || ! is_singular( Click2EU_Route_Policy::RESUME_POST_TYPE ) ) {
			return;
		}
		$resume_id = get_queried_object_id();
		if ( ! $this->can_contact( $resume_id ) ) {
			return;
		}
		self::$rendered = true;
		$user = wp_get_current_user();
		$company = current_user_can( 'manage_options' )
			? get_bloginfo( 'name' )
			: (string) get_user_meta( $user->ID, Click2EU_Employer_Verification::META_COMPANY_NAME, true );
		$dialog_id = 'c2eu-contact-resume-' . absint( $resume_id );
		$status = sanitize_key( wp_unslash( $_GET['c2eu_contact'] ?? '' ) );
		$notices = array(
			'success' => __( 'Your message was accepted by WordPress for delivery to the Candidate and administrator.', 'click2eu-membership' ),
			'partial' => __( 'Your request was only partially accepted by the configured mail transport. The administrator has been notified where possible.', 'click2eu-membership' ),
			'failed' => __( 'WordPress could not accept the message for delivery. Please try again later.', 'click2eu-membership' ),
			'rate_limited' => __( 'Please wait before contacting this Candidate again.', 'click2eu-membership' ),
			'duplicate' => __( 'This contact request was already processed.', 'click2eu-membership' ),
		);
		?>
		<div class="c2eu-resume-contact">
			<?php if ( isset( $notices[ $status ] ) ) : ?>
				<div class="c2eu-resume-contact__notice c2eu-resume-contact__notice--<?php echo esc_attr( $status ); ?>" role="status"><?php echo esc_html( $notices[ $status ] ); ?></div>
			<?php endif; ?>
			<button type="button" class="btn btn-main btn-effect c2eu-resume-contact__open" data-c2eu-dialog="<?php echo esc_attr( $dialog_id ); ?>"><?php esc_html_e( 'Contact Candidate', 'click2eu-membership' ); ?></button>
			<dialog id="<?php echo esc_attr( $dialog_id ); ?>" class="c2eu-resume-contact__dialog" aria-labelledby="<?php echo esc_attr( $dialog_id ); ?>-title">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="c2eu-resume-contact__form">
					<div class="c2eu-resume-contact__header"><h2 id="<?php echo esc_attr( $dialog_id ); ?>-title"><?php esc_html_e( 'Contact Candidate', 'click2eu-membership' ); ?></h2><button type="button" class="c2eu-resume-contact__close" aria-label="<?php esc_attr_e( 'Close contact form', 'click2eu-membership' ); ?>">&times;</button></div>
					<p><?php esc_html_e( 'Your message will be emailed without exposing the Candidate’s private email address.', 'click2eu-membership' ); ?></p>
					<div class="c2eu-resume-contact__grid">
						<label><?php esc_html_e( 'Employer name', 'click2eu-membership' ); ?><input type="text" value="<?php echo esc_attr( $user->display_name ); ?>" readonly /></label>
						<label><?php esc_html_e( 'Company name', 'click2eu-membership' ); ?><input type="text" value="<?php echo esc_attr( $company ); ?>" readonly /></label>
					</div>
					<label><?php esc_html_e( 'Employer email', 'click2eu-membership' ); ?><input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly /></label>
					<label><?php esc_html_e( 'Subject', 'click2eu-membership' ); ?><input type="text" name="c2eu_contact_subject" maxlength="160" required /></label>
					<label><?php esc_html_e( 'Message', 'click2eu-membership' ); ?><textarea name="c2eu_contact_message" rows="7" maxlength="5000" required></textarea></label>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="resume_id" value="<?php echo esc_attr( $resume_id ); ?>" />
					<input type="hidden" name="c2eu_contact_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>" />
					<?php wp_nonce_field( self::NONCE_ACTION . '_' . $resume_id, 'c2eu_contact_nonce' ); ?>
					<button type="submit" class="btn btn-main btn-effect"><?php esc_html_e( 'Send Message', 'click2eu-membership' ); ?></button>
				</form>
			</dialog>
		</div>
		<?php
	}

	private function can_contact( $resume_id ) {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_options' ) && ! Click2EU_Auth::is_approved_employer() ) ) {
			return false;
		}
		$resume = get_post( absint( $resume_id ) );
		if ( ! $resume || Click2EU_Route_Policy::RESUME_POST_TYPE !== $resume->post_type || 'publish' !== $resume->post_status ) {
			return false;
		}
		if ( (int) $resume->post_author === get_current_user_id() || ! Click2EU_Auth::is_candidate( (int) $resume->post_author ) ) {
			return false;
		}
		return Click2EU_Route_Policy::resume_owner_is_effectively_active( $resume_id );
	}

	public function handle_submission() {
		$resume_id = absint( $_POST['resume_id'] ?? 0 );
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Authentication required.', 'click2eu-membership' ), '', array( 'response' => 401 ) );
		}
		if ( ! current_user_can( 'manage_options' ) && ! Click2EU_Auth::is_approved_employer() ) {
			wp_die( esc_html__( 'Approved Employer access required.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE_ACTION . '_' . $resume_id, 'c2eu_contact_nonce' );
		if ( ! $this->can_contact( $resume_id ) ) {
			wp_die( esc_html__( 'This Candidate is not available for contact.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}

		$subject = sanitize_text_field( wp_unslash( $_POST['c2eu_contact_subject'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['c2eu_contact_message'] ?? '' ) );
		if ( '' === $subject || '' === $message || $this->length( $subject ) > 160 || $this->length( $message ) > 5000 ) {
			wp_die( esc_html__( 'Enter a valid subject and message.', 'click2eu-membership' ), '', array( 'response' => 400 ) );
		}

		$token = sanitize_key( wp_unslash( $_POST['c2eu_contact_token'] ?? '' ) );
		$user_id = get_current_user_id();
		$duplicate_key = 'c2eu_contact_token_' . md5( $user_id . '|' . $token );
		if ( ! $token || get_transient( $duplicate_key ) ) {
			$this->redirect_result( $resume_id, 'duplicate' );
		}
		$cooldown_key = 'c2eu_contact_cd_' . md5( $user_id . '|' . $resume_id );
		$hour_key = 'c2eu_contact_hour_' . $user_id;
		$hour_count = absint( get_transient( $hour_key ) );
		if ( get_transient( $cooldown_key ) || $hour_count >= self::HOURLY_LIMIT ) {
			$this->redirect_result( $resume_id, 'rate_limited' );
		}

		$resume = get_post( $resume_id );
		$candidate = get_userdata( (int) $resume->post_author );
		if ( ! $candidate || ! is_email( $candidate->user_email ) ) {
			wp_die( esc_html__( 'Candidate contact is unavailable.', 'click2eu-membership' ), '', array( 'response' => 422 ) );
		}
		$employer = wp_get_current_user();
		$company = current_user_can( 'manage_options' ) ? get_bloginfo( 'name' ) : (string) get_user_meta( $employer->ID, Click2EU_Employer_Verification::META_COMPANY_NAME, true );
		$reply_to = sanitize_email( $employer->user_email );
		if ( ! is_email( $reply_to ) ) {
			wp_die( esc_html__( 'Your account email is not valid for replies.', 'click2eu-membership' ), '', array( 'response' => 422 ) );
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . sanitize_text_field( $employer->display_name ) . ' <' . $reply_to . '>' );
		$candidate_body = $this->email_html( sprintf( __( 'Hello %s,', 'click2eu-membership' ), $candidate->display_name ), $subject, $message, $employer->display_name, $company, get_permalink( $resume_id ), false, $employer->ID, $candidate->ID, $resume_id );
		$admin_body = $this->email_html( __( 'Employer-to-Candidate contact request', 'click2eu-membership' ), $subject, $message, $employer->display_name, $company, get_permalink( $resume_id ), true, $employer->ID, $candidate->ID, $resume_id );

		set_transient( $duplicate_key, 1, DAY_IN_SECONDS );
		set_transient( $cooldown_key, 1, self::COOLDOWN_SECONDS );
		set_transient( $hour_key, $hour_count + 1, HOUR_IN_SECONDS );
		$candidate_accepted = wp_mail( $candidate->user_email, $subject, $candidate_body, $headers );
		$admin_email = sanitize_email( get_option( 'admin_email' ) );
		$admin_accepted = $admin_email && wp_mail( $admin_email, sprintf( '[Click2EU Contact] %s', $subject ), $admin_body, $headers );
		$result = $candidate_accepted && $admin_accepted ? 'success' : ( $candidate_accepted || $admin_accepted ? 'partial' : 'failed' );
		do_action( 'click2eu_candidate_contact_result', $result, $employer->ID, $candidate->ID, $resume_id, (bool) $candidate_accepted, (bool) $admin_accepted );
		$this->redirect_result( $resume_id, $result );
	}

	private function email_html( $heading, $subject, $message, $employer_name, $company, $resume_url, $admin_copy, $employer_id, $candidate_id, $resume_id ) {
		$reference = $admin_copy ? '<p><strong>' . esc_html__( 'Reference IDs:', 'click2eu-membership' ) . '</strong> ' . esc_html( "Employer #{$employer_id}; Candidate #{$candidate_id}; Resume #{$resume_id}" ) . '</p>' : '';
		return '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#0f2747"><h2>' . esc_html( $heading ) . '</h2><p><strong>' . esc_html__( 'From:', 'click2eu-membership' ) . '</strong> ' . esc_html( $employer_name ) . ' — ' . esc_html( $company ) . '</p><p><strong>' . esc_html( $subject ) . '</strong></p><div style="white-space:pre-wrap">' . nl2br( esc_html( $message ) ) . '</div><p><a href="' . esc_url( $resume_url ) . '">' . esc_html__( 'View Resume', 'click2eu-membership' ) . '</a></p>' . $reference . '<p style="color:#64748b">' . esc_html__( 'WordPress accepted this message for mail transport; inbox delivery is not guaranteed.', 'click2eu-membership' ) . '</p></div>';
	}

	private function redirect_result( $resume_id, $result ) {
		$url = add_query_arg( 'c2eu_contact', sanitize_key( $result ), get_permalink( $resume_id ) );
		wp_safe_redirect( $url );
		exit;
	}

	private function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
