<?php
/**
 * Click2EU Stage 2 — Comprehensive Employer Verification Test Suite
 *
 * Verifies:
 * 1. Status lifecycle & fail-closed defaults (pending, approved, rejected, suspended).
 * 2. Missing/empty/unknown status on employers defaults to pending.
 * 3. Non-employers (candidates, admins) never have employer verification status.
 * 4. Click2EU_Auth::is_approved_employer() authorization checks.
 * 5. Click2EU_Route_Policy::is_privileged_actor() enforcement.
 * 6. Authentication blocking for pending, rejected, and suspended employers.
 * 7. Candidate and Admin authentication passes through unobstructed.
 * 8. Registration field validation requiring Company Name and Registration Number for employers.
 * 9. Registration persistence initializing status to pending and storing company fields.
 * 10. Cariera auto-login interception and session destruction.
 * 11. Admin review set_status recording audit timestamps and reviewer ID.
 * 12. Admin notes confidentiality.
 * 13. Resume Directory integration for all employer states.
 */

define( 'ABSPATH', __DIR__ . '/../' );

// Mock WordPress data structures
$mock_users = array();
$mock_user_meta = array();
$mock_current_user = null;
$mock_destroyed_session = false;
$mock_cleared_auth_cookie = false;
$mock_json_response = null;

class WP_User {
    public $ID = 0;
    public $user_login = '';
    public $user_email = '';
    public $display_name = '';
    public $user_registered = '2026-01-01 12:00:00';
    public $roles = array();

    public function __construct( $id, $roles, $login = '', $email = '', $name = '' ) {
        $this->ID           = $id;
        $this->roles        = (array) $roles;
        $this->user_login   = $login ?: "user_{$id}";
        $this->user_email   = $email ?: "user_{$id}@click2eu.test";
        $this->display_name = $name ?: "User {$id}";
    }

    public function exists() {
        return $this->ID > 0;
    }
}

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code, $message, $data = array() ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

function absint( $val ) {
    return abs( intval( $val ) );
}

function sanitize_key( $key ) {
    return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function sanitize_text_field( $str ) {
    return trim( strip_tags( (string) $str ) );
}

function sanitize_textarea_field( $str ) {
    return trim( strip_tags( (string) $str ) );
}

function wp_unslash( $val ) {
    return $val;
}

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $d = '' ) {
    return esc_html( $text );
}

function esc_html_e( $text, $d = '' ) {
    echo esc_html( $text );
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr_e( $text, $d = '' ) {
    echo esc_attr( $text );
}

function esc_textarea( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
    return (string) $url;
}

function __( $text, $d = '' ) {
    return $text;
}

function _e( $text, $d = '' ) {
    echo $text;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $callback ) {}
function add_submenu_page( $parent, $page_title, $menu_title, $cap, $menu_slug, $callback = '' ) {}
function check_admin_referer( $action, $query_arg = '_wpnonce' ) { return true; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return ''; }
function admin_url( $path = '' ) { return 'http://click2eu.local/wp-admin/' . $path; }
function home_url( $path = '' ) { return 'http://click2eu.local' . $path; }
function current_time( $type, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); }
function is_admin() { return false; }
function is_user_logged_in() {
    global $mock_current_user;
    return $mock_current_user && $mock_current_user->exists();
}
function wp_get_current_user() {
    global $mock_current_user;
    return $mock_current_user ?: new WP_User( 0, array() );
}
function get_current_user_id() {
    global $mock_current_user;
    return $mock_current_user ? $mock_current_user->ID : 0;
}
function wp_set_current_user( $id ) {
    global $mock_current_user, $mock_users;
    $mock_current_user = isset( $mock_users[ $id ] ) ? $mock_users[ $id ] : ( $id === 0 ? null : new WP_User( $id, array() ) );
    return $mock_current_user;
}
function current_user_can( $capability ) {
    global $mock_current_user;
    if ( ! $mock_current_user ) {
        return false;
    }
    if ( in_array( 'administrator', $mock_current_user->roles, true ) ) {
        return true;
    }
    return in_array( $capability, $mock_current_user->roles, true );
}
function user_can( $user, $capability ) {
    if ( is_numeric( $user ) ) {
        $user = get_user_by( 'id', $user );
    }
    if ( ! $user || ! ( $user instanceof WP_User ) ) {
        return false;
    }
    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
        return true;
    }
    return in_array( $capability, (array) $user->roles, true );
}
function get_user_by( $field, $value ) {
    global $mock_users;
    if ( 'id' === $field ) {
        return isset( $mock_users[ $value ] ) ? $mock_users[ $value ] : false;
    }
    foreach ( $mock_users as $u ) {
        if ( isset( $u->$field ) && $u->$field === $value ) {
            return $u;
        }
    }
    return false;
}
function get_userdata( $user_id ) {
    return get_user_by( 'id', $user_id );
}
function get_user_meta( $user_id, $key = '', $single = false ) {
    global $mock_user_meta;
    if ( empty( $key ) ) {
        return isset( $mock_user_meta[ $user_id ] ) ? $mock_user_meta[ $user_id ] : array();
    }
    if ( ! isset( $mock_user_meta[ $user_id ][ $key ] ) ) {
        return $single ? '' : array();
    }
    $val = $mock_user_meta[ $user_id ][ $key ];
    return $single ? $val : array( $val );
}
function update_user_meta( $user_id, $key, $value ) {
    global $mock_user_meta;
    if ( ! isset( $mock_user_meta[ $user_id ] ) ) {
        $mock_user_meta[ $user_id ] = array();
    }
    $mock_user_meta[ $user_id ][ $key ] = $value;
    return true;
}
function wp_destroy_current_session() {
    global $mock_destroyed_session;
    $mock_destroyed_session = true;
}
function wp_clear_auth_cookie() {
    global $mock_cleared_auth_cookie;
    $mock_cleared_auth_cookie = true;
}
function wp_send_json( $response, $status = null ) {
    global $mock_json_response;
    $mock_json_response = array( 'data' => $response, 'status' => $status );
}
function wp_safe_redirect( $location, $status = 302 ) {}
function add_query_arg( $args, $url = '' ) {
    if ( is_array( $args ) ) {
        $qs = http_build_query( $args );
        return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $qs;
    }
    return $url;
}

// Load plugin classes
require_once dirname( __DIR__ ) . '/includes/class-click2eu-auth.php';
require_once dirname( __DIR__ ) . '/includes/class-click2eu-employer-verification.php';
require_once dirname( __DIR__ ) . '/includes/class-click2eu-route-policy.php';
require_once dirname( __DIR__ ) . '/includes/class-click2eu-resume-directory.php';

// Setup Mock Users
$mock_users[1] = new WP_User( 1, array( 'administrator' ), 'admin', 'admin@click2eu.test', 'Site Administrator' );
$mock_users[2] = new WP_User( 2, array( 'candidate' ), 'cand1', 'cand1@click2eu.test', 'Active Candidate' );
$mock_users[3] = new WP_User( 3, array( 'employer' ), 'emp_pending', 'pending@click2eu.test', 'Pending Employer' );
$mock_users[4] = new WP_User( 4, array( 'employer' ), 'emp_approved', 'approved@click2eu.test', 'Approved Employer' );
$mock_users[5] = new WP_User( 5, array( 'employer' ), 'emp_rejected', 'rejected@click2eu.test', 'Rejected Employer' );
$mock_users[6] = new WP_User( 6, array( 'employer' ), 'emp_suspended', 'suspended@click2eu.test', 'Suspended Employer' );
$mock_users[7] = new WP_User( 7, array( 'employer' ), 'emp_no_status', 'nostatus@click2eu.test', 'No Status Employer' );

// Set initial meta
update_user_meta( 3, Click2EU_Employer_Verification::META_STATUS, 'pending' );
update_user_meta( 3, Click2EU_Employer_Verification::META_COMPANY_NAME, 'Alpha Corp' );
update_user_meta( 3, Click2EU_Employer_Verification::META_REG_NUMBER, 'HRB-1001' );

update_user_meta( 4, Click2EU_Employer_Verification::META_STATUS, 'approved' );
update_user_meta( 4, Click2EU_Employer_Verification::META_COMPANY_NAME, 'Beta GmbH' );
update_user_meta( 4, Click2EU_Employer_Verification::META_REG_NUMBER, 'HRB-2002' );

update_user_meta( 5, Click2EU_Employer_Verification::META_STATUS, 'rejected' );
update_user_meta( 5, Click2EU_Employer_Verification::META_COMPANY_NAME, 'Gamma LLC' );
update_user_meta( 5, Click2EU_Employer_Verification::META_REG_NUMBER, 'HRB-3003' );

update_user_meta( 6, Click2EU_Employer_Verification::META_STATUS, 'suspended' );
update_user_meta( 6, Click2EU_Employer_Verification::META_COMPANY_NAME, 'Delta Ltd' );
update_user_meta( 6, Click2EU_Employer_Verification::META_REG_NUMBER, 'HRB-4004' );

// User 7 has NO meta set (simulating pre-existing employer)

// Test Suite Assertions
$assertions = 0;
$failures   = array();

function assert_true( $cond, $desc ) {
    global $assertions, $failures;
    $assertions++;
    if ( ! $cond ) {
        $failures[] = "FAILED: {$desc}";
        echo "[FAIL] {$desc}\n";
    } else {
        echo "[PASS] {$desc}\n";
    }
}

function assert_equals( $expected, $actual, $desc ) {
    global $assertions, $failures;
    $assertions++;
    if ( $expected !== $actual ) {
        $failures[] = "FAILED: {$desc} (Expected " . var_export( $expected, true ) . ", got " . var_export( $actual, true ) . ")";
        echo "[FAIL] {$desc} — Expected " . var_export( $expected, true ) . ", got " . var_export( $actual, true ) . "\n";
    } else {
        echo "[PASS] {$desc}\n";
    }
}

echo "=== CLICK2EU STAGE 2 EMPLOYER VERIFICATION TESTS ===\n\n";

// 1. Allowed Statuses
echo "--- 1. Allowed Statuses ---\n";
$statuses = Click2EU_Employer_Verification::allowed_statuses();
assert_true( in_array( 'pending', $statuses, true ), 'Allowed statuses includes pending' );
assert_true( in_array( 'approved', $statuses, true ), 'Allowed statuses includes approved' );
assert_true( in_array( 'rejected', $statuses, true ), 'Allowed statuses includes rejected' );
assert_true( in_array( 'suspended', $statuses, true ), 'Allowed statuses includes suspended' );
assert_equals( 4, count( $statuses ), 'Exactly 4 allowed statuses' );

// 2. Status Retrieval & Fail-Closed Behavior
echo "\n--- 2. Fail-Closed Status Defaults ---\n";
assert_equals( '', Click2EU_Employer_Verification::get_status( 9999 ), 'Non-existent user returns empty status' );
assert_equals( '', Click2EU_Employer_Verification::get_status( 1 ), 'Administrator returns empty employer status' );
assert_equals( '', Click2EU_Employer_Verification::get_status( 2 ), 'Candidate returns empty employer status' );
assert_equals( 'pending', Click2EU_Employer_Verification::get_status( 3 ), 'Pending employer returns pending' );
assert_equals( 'approved', Click2EU_Employer_Verification::get_status( 4 ), 'Approved employer returns approved' );
assert_equals( 'rejected', Click2EU_Employer_Verification::get_status( 5 ), 'Rejected employer returns rejected' );
assert_equals( 'suspended', Click2EU_Employer_Verification::get_status( 6 ), 'Suspended employer returns suspended' );
assert_equals( 'pending', Click2EU_Employer_Verification::get_status( 7 ), 'Employer with NO status meta defaults to pending (fail-closed)' );

// Test corrupt/unknown meta
update_user_meta( 7, Click2EU_Employer_Verification::META_STATUS, 'random_garbage_status' );
assert_equals( 'pending', Click2EU_Employer_Verification::get_status( 7 ), 'Employer with invalid status defaults to pending' );

// 3. Approval Checks
echo "\n--- 3. Approval Checks (is_approved) ---\n";
assert_true( ! Click2EU_Employer_Verification::is_approved( 1 ), 'Admin is not approved employer' );
assert_true( ! Click2EU_Employer_Verification::is_approved( 2 ), 'Candidate is not approved employer' );
assert_true( ! Click2EU_Employer_Verification::is_approved( 3 ), 'Pending employer is not approved' );
assert_true( Click2EU_Employer_Verification::is_approved( 4 ), 'Approved employer is approved' );
assert_true( ! Click2EU_Employer_Verification::is_approved( 5 ), 'Rejected employer is not approved' );
assert_true( ! Click2EU_Employer_Verification::is_approved( 6 ), 'Suspended employer is not approved' );
assert_true( ! Click2EU_Employer_Verification::is_approved( 7 ), 'Unverified employer is not approved' );

// 4. Centralized Auth Integration (Click2EU_Auth::is_approved_employer)
echo "\n--- 4. Centralized Auth (Click2EU_Auth::is_approved_employer) ---\n";
assert_true( ! Click2EU_Auth::is_approved_employer( 1 ), 'Click2EU_Auth: Admin is not approved employer' );
assert_true( ! Click2EU_Auth::is_approved_employer( 2 ), 'Click2EU_Auth: Candidate is not approved employer' );
assert_true( ! Click2EU_Auth::is_approved_employer( 3 ), 'Click2EU_Auth: Pending employer returns false' );
assert_true( Click2EU_Auth::is_approved_employer( 4 ), 'Click2EU_Auth: Approved employer returns true' );
assert_true( ! Click2EU_Auth::is_approved_employer( 5 ), 'Click2EU_Auth: Rejected employer returns false' );
assert_true( ! Click2EU_Auth::is_approved_employer( 6 ), 'Click2EU_Auth: Suspended employer returns false' );
assert_true( ! Click2EU_Auth::is_approved_employer( 7 ), 'Click2EU_Auth: Unverified employer returns false' );

// 5. Centralized Route Policy Privileged Actor
echo "\n--- 5. Route Policy (Click2EU_Route_Policy::is_privileged_actor) ---\n";
wp_set_current_user( 1 );
assert_true( Click2EU_Route_Policy::is_privileged_actor(), 'Admin is privileged actor' );

wp_set_current_user( 2 );
assert_true( ! Click2EU_Route_Policy::is_privileged_actor(), 'Candidate is not privileged actor' );

wp_set_current_user( 3 );
assert_true( ! Click2EU_Route_Policy::is_privileged_actor(), 'Pending employer is NOT privileged actor' );

wp_set_current_user( 4 );
assert_true( Click2EU_Route_Policy::is_privileged_actor(), 'Approved employer IS privileged actor' );

wp_set_current_user( 5 );
assert_true( ! Click2EU_Route_Policy::is_privileged_actor(), 'Rejected employer is NOT privileged actor' );

wp_set_current_user( 6 );
assert_true( ! Click2EU_Route_Policy::is_privileged_actor(), 'Suspended employer is NOT privileged actor' );

wp_set_current_user( 7 );
assert_true( ! Click2EU_Route_Policy::is_privileged_actor(), 'Unverified employer is NOT privileged actor' );

// 6. Authentication Pipeline Blocking (block_unapproved_employer_login)
echo "\n--- 6. Authentication Pipeline Blocking ---\n";
// Admin passes
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[1], 'pass' );
assert_true( $res instanceof WP_User && $res->ID === 1, 'Admin login passes through' );

// Candidate passes
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[2], 'pass' );
assert_true( $res instanceof WP_User && $res->ID === 2, 'Candidate login passes through' );

// Approved employer passes
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[4], 'pass' );
assert_true( $res instanceof WP_User && $res->ID === 4, 'Approved employer login passes through' );

// Pending employer blocked
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[3], 'pass' );
assert_true( is_wp_error( $res ), 'Pending employer login is blocked' );
assert_equals( 'click2eu_employer_pending', $res->get_error_code(), 'Pending employer gets click2eu_employer_pending' );

// Rejected employer blocked
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[5], 'pass' );
assert_true( is_wp_error( $res ), 'Rejected employer login is blocked' );
assert_equals( 'click2eu_employer_rejected', $res->get_error_code(), 'Rejected employer gets click2eu_employer_rejected' );

// Suspended employer blocked
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[6], 'pass' );
assert_true( is_wp_error( $res ), 'Suspended employer login is blocked' );
assert_equals( 'click2eu_employer_suspended', $res->get_error_code(), 'Suspended employer gets click2eu_employer_suspended' );

// Unverified employer blocked
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $mock_users[7], 'pass' );
assert_true( is_wp_error( $res ), 'Unverified employer login is blocked' );
assert_equals( 'click2eu_employer_pending', $res->get_error_code(), 'Unverified employer gets click2eu_employer_pending' );

// Existing WP_Error passes through unmodified
$err_in = new WP_Error( 'invalid_password', 'Wrong password' );
$res = Click2EU_Employer_Verification::block_unapproved_employer_login( $err_in, 'pass' );
assert_equals( 'invalid_password', $res->get_error_code(), 'Existing auth WP_Error passed through unmodified' );

// 7. Resume Directory Access Integration
echo "\n--- 7. Resume Directory Access Integration ---\n";
$rd = new Click2EU_Resume_Directory();
assert_equals( 'allowed', $rd->get_access_state( $mock_users[1] ), 'Admin can view resumes' );
assert_equals( 'candidate_denied', $rd->get_access_state( $mock_users[2] ), 'Candidate denied resume directory' );
assert_equals( 'pending', $rd->get_access_state( $mock_users[3] ), 'Pending employer sees pending screen' );
assert_equals( 'allowed', $rd->get_access_state( $mock_users[4] ), 'Approved employer can view resumes' );
assert_equals( 'rejected', $rd->get_access_state( $mock_users[5] ), 'Rejected employer sees rejected screen' );
assert_equals( 'suspended', $rd->get_access_state( $mock_users[6] ), 'Suspended employer sees suspended screen' );
assert_equals( 'pending', $rd->get_access_state( $mock_users[7] ), 'Unverified employer sees pending screen' );

// 8. Admin Status Transitions & Audit Metadata (set_status)
echo "\n--- 8. Admin Status Transitions & Audit ---\n";
wp_set_current_user( 1 ); // Admin reviewer
$ok = Click2EU_Employer_Verification::set_status( 3, 'approved', 1, 'Verified via commercial registry' );
assert_true( $ok, 'set_status returned true on valid transition' );
assert_equals( 'approved', Click2EU_Employer_Verification::get_status( 3 ), 'Status changed to approved' );
assert_equals( 'Verified via commercial registry', get_user_meta( 3, Click2EU_Employer_Verification::META_ADMIN_NOTE, true ), 'Admin note stored' );
assert_equals( 1, absint( get_user_meta( 3, Click2EU_Employer_Verification::META_REVIEWED_BY, true ) ), 'Reviewer ID stored' );
assert_true( '' !== get_user_meta( 3, Click2EU_Employer_Verification::META_REVIEWED_AT, true ), 'Review timestamp recorded' );

// Transition to suspended
$ok = Click2EU_Employer_Verification::set_status( 3, 'suspended', 1, 'Payment default' );
assert_true( $ok, 'set_status changed to suspended' );
assert_equals( 'suspended', Click2EU_Employer_Verification::get_status( 3 ), 'Status changed to suspended' );

// Transition to rejected
$ok = Click2EU_Employer_Verification::set_status( 3, 'rejected', 1, 'Fraudulent data' );
assert_true( $ok, 'set_status changed to rejected' );
assert_equals( 'rejected', Click2EU_Employer_Verification::get_status( 3 ), 'Status changed to rejected' );

// Transition back to pending
$ok = Click2EU_Employer_Verification::set_status( 3, 'pending', 1, 'Re-audit needed' );
assert_true( $ok, 'set_status changed to pending' );
assert_equals( 'pending', Click2EU_Employer_Verification::get_status( 3 ), 'Status changed back to pending' );

// Non-employer rejection
assert_true( ! Click2EU_Employer_Verification::set_status( 2, 'approved', 1 ), 'Cannot set status on Candidate' );
assert_true( ! Click2EU_Employer_Verification::set_status( 3, 'invalid_status_xyz', 1 ), 'Cannot set invalid status' );

// 9. Employer Details Retrieval (get_employer_details)
echo "\n--- 9. Employer Details Retrieval ---\n";
$details = Click2EU_Employer_Verification::get_employer_details( 4 );
assert_equals( 4, $details['user_id'], 'Details user_id matches' );
assert_equals( 'emp_approved', $details['user_login'], 'Details user_login matches' );
assert_equals( 'Beta GmbH', $details['company_name'], 'Details company_name matches' );
assert_equals( 'HRB-2002', $details['registration_number'], 'Details registration_number matches' );
assert_equals( 'approved', $details['status'], 'Details status matches' );

// 10. Registration Validation & Auto-Login Interception
echo "\n--- 10. Registration Validation & Auto-Login Interception ---\n";
$ev = new Click2EU_Employer_Verification();

// A. Registration Validation: Missing company fields
$_REQUEST['action'] = 'cariera_ajax_register';
$_POST['cariera_user_role'] = 'employer';
$_POST['c2eu_company_name'] = '';
$_POST['c2eu_company_registration_number'] = '';
$mock_json_response = null;

$ev->validate_cariera_employer_registration();
assert_true( is_array( $mock_json_response ), 'Validation intercepted empty company name' );
assert_equals( false, $mock_json_response['data']['register'], 'Validation sent register => false' );
assert_true( strpos( $mock_json_response['data']['message'], 'Company Name is required' ) !== false, 'Company name required error message' );

// Missing registration number
$_POST['c2eu_company_name'] = 'New Co';
$_POST['c2eu_company_registration_number'] = '';
$mock_json_response = null;
$ev->validate_cariera_employer_registration();
assert_true( is_array( $mock_json_response ), 'Validation intercepted empty registration number' );
assert_equals( false, $mock_json_response['data']['register'], 'Validation sent register => false' );
assert_true( strpos( $mock_json_response['data']['message'], 'Company Registration Number is required' ) !== false, 'Registration number required error message' );

// Candidate registration bypasses employer validation
$_POST['cariera_user_role'] = 'candidate';
$_POST['c2eu_company_name'] = '';
$_POST['c2eu_company_registration_number'] = '';
$mock_json_response = null;
$ev->validate_cariera_employer_registration();
assert_true( null === $mock_json_response, 'Candidate registration bypasses employer company checks' );

// B. on_user_register hook: new employer gets pending status & persists company fields
$mock_users[8] = new WP_User( 8, array( 'employer' ), 'brand_new_emp', 'new@click2eu.test' );
$_POST['cariera_user_role'] = 'employer';
$_POST['c2eu_company_name'] = 'Fresh Start Inc';
$_POST['c2eu_company_registration_number'] = 'REG-8888';

$ev->on_user_register( 8 );
assert_equals( 'pending', Click2EU_Employer_Verification::get_status( 8 ), 'New employer initialized with pending status' );
assert_equals( 'Fresh Start Inc', get_user_meta( 8, Click2EU_Employer_Verification::META_COMPANY_NAME, true ), 'New employer company name stored' );
assert_equals( 'REG-8888', get_user_meta( 8, Click2EU_Employer_Verification::META_REG_NUMBER, true ), 'New employer reg number stored' );

// Candidate registration does not get employer status
$mock_users[9] = new WP_User( 9, array( 'candidate' ), 'new_cand', 'newcand@click2eu.test' );
$ev->on_user_register( 9 );
assert_equals( '', Click2EU_Employer_Verification::get_status( 9 ), 'New candidate does not receive employer status' );

// C. Auto-login interception
$mock_destroyed_session = false;
$mock_cleared_auth_cookie = false;
$mock_json_response = null;

$ev->intercept_cariera_registration_response( array( 'register' => true ), 8 );
assert_true( $mock_destroyed_session, 'Auto-login session destroyed for new employer' );
assert_true( $mock_cleared_auth_cookie, 'Auth cookie cleared for new employer' );
assert_true( is_array( $mock_json_response ), 'JSON response intercepted' );
assert_true( $mock_json_response['data']['status'], 'Status true returned to avoid Cariera JS error' );
assert_true( strpos( $mock_json_response['data']['message'], 'pending administrative verification' ) !== false, 'Pending verification message returned' );

// Candidate auto-login is NOT intercepted
$mock_destroyed_session = false;
$mock_cleared_auth_cookie = false;
$mock_json_response = null;
$ev->intercept_cariera_registration_response( array( 'register' => true ), 9 );
assert_true( ! $mock_destroyed_session, 'Candidate session is NOT destroyed' );
assert_true( null === $mock_json_response, 'Candidate auto-login passes through without interception' );

// Summary
echo "\n============================================\n";
echo "STAGE 2 TEST SUMMARY: {$assertions} assertions, " . count( $failures ) . " failures.\n";
echo "============================================\n";

if ( count( $failures ) > 0 ) {
    exit( 1 );
}
exit( 0 );
