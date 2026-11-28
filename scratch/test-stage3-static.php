<?php

$root = dirname( __DIR__ );
$contact = file_get_contents( $root . '/includes/class-click2eu-candidate-contact.php' );
$bootstrap = file_get_contents( $root . '/click2eu-membership.php' );
$verification = file_get_contents( $root . '/includes/class-click2eu-employer-verification.php' );
$category = file_get_contents( $root . '/includes/class-click2eu-category-access.php' );
$access = file_get_contents( $root . '/includes/class-click2eu-access.php' );
$route_policy = file_get_contents( $root . '/includes/class-click2eu-route-policy.php' );
$css = file_get_contents( $root . '/assets/candidate-contact.css' );
$js = file_get_contents( $root . '/assets/candidate-contact.js' );
$pass = 0;
$fail = 0;

function check_stage3( $condition, $label ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

check_stage3( false !== strpos( $bootstrap, "class-click2eu-candidate-contact.php" ), 'Contact class is required' );
check_stage3( false !== strpos( $bootstrap, 'new Click2EU_Candidate_Contact()' ), 'Contact class is instantiated' );
check_stage3( false !== strpos( $contact, "remove_action( 'single_resume_meta_end'" ), 'Exact V1 Invite callback is removed' );
check_stage3( false !== strpos( $contact, "remove_action( 'cariera_candidate_detail_actions'" ), 'Exact V2/V3 Invite callback is removed' );
check_stage3( false === strpos( $contact, 'remove_all_actions' ), 'Shared Resume hooks are never cleared' );
check_stage3( false !== strpos( $contact, "add_action( 'single_resume_meta_end'" ), 'Contact action supports V1' );
check_stage3( false !== strpos( $contact, "add_action( 'cariera_candidate_detail_actions'" ), 'Contact action supports V2/V3' );
check_stage3( false !== strpos( $contact, 'is_approved_employer()' ), 'Approved Employer authorization is enforced' );
check_stage3( false !== strpos( $contact, "current_user_can( 'manage_options' )" ), 'Administrator authorization is supported' );
check_stage3( false !== strpos( $contact, 'check_admin_referer' ), 'Submission nonce is verified' );
check_stage3( false !== strpos( $contact, "'publish' !== \$resume->post_status" ), 'Resume must be published' );
check_stage3( false !== strpos( $contact, 'is_candidate( (int) $resume->post_author )' ), 'Resume owner must be Candidate' );
check_stage3( false !== strpos( $contact, 'resume_owner_is_effectively_active' ), 'Candidate membership must be effectively Active' );
check_stage3( false !== strpos( $contact, '(int) $resume->post_author === get_current_user_id()' ), 'Self-contact is rejected' );
check_stage3( false !== strpos( $contact, 'get_userdata( (int) $resume->post_author )' ), 'Candidate identity is server-derived' );
check_stage3( false !== strpos( $contact, '$candidate->user_email' ), 'Candidate account email is server-derived' );
check_stage3( false === strpos( $contact, "\$_POST['candidate_email']" ), 'Candidate email is never browser supplied' );
check_stage3( false === strpos( $contact, 'name="candidate_email"' ), 'Candidate email is absent from form markup' );
check_stage3( false !== strpos( $contact, 'get_transient( $duplicate_key )' ), 'Duplicate token is checked' );
check_stage3( false !== strpos( $contact, 'get_transient( $cooldown_key )' ), 'Per-Employer/Resume cooldown is checked' );
check_stage3( false !== strpos( $contact, 'HOURLY_LIMIT' ), 'Per-account hourly limit is enforced' );
check_stage3( false !== strpos( $contact, 'wp_mail( $candidate->user_email' ), 'Candidate mail is sent' );
check_stage3( false !== strpos( $contact, "get_option( 'admin_email' )" ), 'Admin destination is configured server-side' );
check_stage3( false !== strpos( $contact, "'Reply-To: '" ), 'Employer account email is used for Reply-To' );
check_stage3( false !== strpos( $contact, "'partial'" ), 'Partial transport acceptance is represented honestly' );
check_stage3( false !== strpos( $contact, 'click2eu_candidate_contact_result' ), 'Non-content delivery outcome hook is emitted' );
check_stage3( false !== strpos( $contact, 'WordPress accepted this message for mail transport' ), 'Email avoids inbox-delivery claim' );
check_stage3( false !== strpos( $contact, 'Employer #' ) && false !== strpos( $contact, 'Candidate #' ) && false !== strpos( $contact, 'Resume #' ), 'Admin copy contains required IDs' );
check_stage3( false !== strpos( $contact, "is_singular( Click2EU_Route_Policy::RESUME_POST_TYPE )" ), 'Assets and output are single-Resume scoped' );
check_stage3( false !== strpos( $css, '.c2eu-resume-contact' ), 'CSS is namespaced' );
check_stage3( false !== strpos( $js, 'showModal' ) && false !== strpos( $js, "event.target.close()" ), 'Accessible dialog open/close behavior exists' );
check_stage3( false !== strpos( $verification, "add_filter( 'registration_errors'" ), 'Stage 2 fallback registration validation exists' );
check_stage3( false !== strpos( $verification, 'WP_User_Query' ) && false !== strpos( $verification, 'paginate_links' ), 'Stage 2 admin pagination exists' );
check_stage3( false !== strpos( $category, 'Click2EU_Auth::is_approved_employer()' ), 'Category workflow no longer trusts Employer role alone' );
check_stage3( false !== strpos( $access, 'if ( Click2EU_Auth::is_approved_employer() )' ), 'POST workflow bypass requires approved Employer' );
check_stage3( false !== strpos( $route_policy, "'click2eu_resume_employer_only'" ), 'Candidate single Resume viewing is explicitly denied' );
check_stage3( false !== strpos( $access, "'resume_manager_user_can_download_resume_file'" ), 'Direct Resume downloads use the authorization filter' );
check_stage3( false !== strpos( $access, 'authorize_resume_download' ), 'Resume download authorization is implemented' );

echo "PASS: {$pass}\nFAIL: {$fail}\nTOTAL: " . ( $pass + $fail ) . "\n";
exit( $fail ? 1 : 0 );
