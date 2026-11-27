<?php
/**
 * Click2EU Stage 1 — Functional Mock Test
 *
 * Verifies runtime behavior of Click2EU_Auth, Click2EU_Resume_Directory,
 * access control states, filter sanitization, and card rendering safety.
 */

define( 'ABSPATH', __DIR__ . '/../' );

// Mock WordPress functions
$mock_options = array(
    'resume_manager_per_page' => 10,
    'resume_manager_resumes_page_id' => 114,
);
$mock_user_meta = array();
$mock_current_user = null;
$mock_posts = array();

class WP_User {
    public $ID = 0;
    public $roles = array();
    public function __construct( $id, $roles ) {
        $this->ID = $id;
        $this->roles = (array) $roles;
    }
    public function exists() {
        return $this->ID > 0;
    }
}

class WP_Query {
    public $query_vars = array();
    public $posts = array();
    public $found_posts = 0;
    public $max_num_pages = 0;
    public function __construct( $args = array() ) {
        $this->query_vars = $args;
    }
    public function have_posts() { return false; }
    public function the_post() {}
}

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code, $message, $data = array() ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
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

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html_e( $text, $domain = '' ) {
    echo esc_html( $text );
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr_e( $text, $domain = '' ) {
    echo esc_attr( $text );
}

function esc_url( $url ) {
    return filter_var( $url, FILTER_SANITIZE_URL );
}

function sanitize_text_field( $str ) {
    return trim( strip_tags( (string) $str ) );
}

function sanitize_title( $title ) {
    return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) $title ) );
}

function wp_unslash( $val ) {
    return is_array( $val ) ? array_map( 'wp_unslash', $val ) : stripslashes( (string) $val );
}

function get_option( $name, $default = false ) {
    global $mock_options;
    return $mock_options[ $name ] ?? $default;
}

function wp_get_current_user() {
    global $mock_current_user;
    return $mock_current_user ?: new WP_User( 0, array() );
}

function get_user_by( $field, $value ) {
    global $mock_users;
    return $mock_users[ $value ] ?? false;
}

function user_can( $user, $cap ) {
    if ( is_numeric( $user ) ) {
        $user = get_user_by( 'id', $user );
    }
    if ( ! $user || ! $user->exists() ) return false;
    if ( 'manage_options' === $cap && in_array( 'administrator', (array) $user->roles, true ) ) return true;
    return false;
}

function current_user_can( $cap ) {
    return user_can( wp_get_current_user(), $cap );
}

function get_user_meta( $user_id, $key, $single = false ) {
    global $mock_user_meta;
    return $mock_user_meta[ $user_id ][ $key ] ?? '';
}

function apply_filters( $tag, $value ) { return $value; }
function get_post_status( $post = null ) { return 'publish'; }
function add_query_arg( $key, $value, $url = '' ) { return $url . '?' . $key . '=' . $value; }
function wp_validate_redirect( $location, $default = '' ) { return $location ?: $default; }
function wp_login_url( $redirect = '' ) { return 'http://click2eu.local/wp-login.php' . ( $redirect ? '?redirect_to=' . urlencode( $redirect ) : '' ); }
function home_url( $path = '' ) { return 'http://click2eu.local' . $path; }
function is_admin() { return false; }
function is_page( $page = '' ) { return false; }
function is_singular( $post_types = '' ) { return false; }
function get_post( $post = null ) { return null; }
function get_permalink( $id = 0 ) { return 'http://click2eu.local/resumes/'; }
function wp_style_is( $handle, $list = 'enqueued' ) { return false; }
function plugins_url( $path = '', $plugin = '' ) { return 'http://click2eu.local/wp-content/plugins/click2eu-membership/' . ltrim( $path, '/' ); }
function wp_enqueue_style() {}
function add_shortcode() {}
function add_action() {}
function add_filter() {}
function remove_filter() {}
function taxonomy_exists( $tax ) { return false; }
function get_terms() { return array(); }
function wp_reset_postdata() {}
function selected( $selected, $current, $echo = true ) {
    $res = (string) $selected === (string) $current ? ' selected="selected"' : '';
    if ( $echo ) echo $res;
    return $res;
}
function __ ( $text, $domain = '' ) { return $text; }

// Load plugin classes
require_once __DIR__ . '/../includes/class-click2eu-config.php';
require_once __DIR__ . '/../includes/class-click2eu-auth.php';
require_once __DIR__ . '/../includes/class-click2eu-settings.php';
require_once __DIR__ . '/../includes/class-click2eu-resume-directory.php';

// Setup Mock Users
$mock_users = array(
    1 => new WP_User( 1, array( 'administrator' ) ),
    2 => new WP_User( 2, array( 'employer' ) ),       // Unapproved / pending
    3 => new WP_User( 3, array( 'employer' ) ),       // Approved
    4 => new WP_User( 4, array( 'employer' ) ),       // Rejected
    5 => new WP_User( 5, array( 'employer' ) ),       // Suspended
    6 => new WP_User( 6, array( 'candidate' ) ),      // Candidate
    7 => new WP_User( 7, array( 'subscriber' ) ),     // Other
);

$mock_user_meta[3]['_click2eu_employer_verification_status'] = 'approved';
$mock_user_meta[4]['_click2eu_employer_verification_status'] = 'rejected';
$mock_user_meta[5]['_click2eu_employer_verification_status'] = 'suspended';

$pass = 0; $fail = 0;
function test( $desc, $cond ) {
    global $pass, $fail;
    if ( $cond ) {
        echo "PASS: $desc\n";
        $pass++;
    } else {
        echo "FAIL: $desc\n";
        $fail++;
    }
}

echo "\n=== Click2EU Stage 1 Functional Mock Tests ===\n\n";

// 1. Guest access state
$mock_current_user = null;
test( 'Guest gets "guest" access state', 'guest' === Click2EU_Resume_Directory::get_access_state() );

// 2. Administrator access state
$mock_current_user = $mock_users[1];
test( 'Admin gets "allowed" access state', 'allowed' === Click2EU_Resume_Directory::get_access_state() );

// 3. Unapproved Employer access state
$mock_current_user = $mock_users[2];
test( 'Unapproved employer gets "pending" access state', 'pending' === Click2EU_Resume_Directory::get_access_state() );
test( 'Unapproved employer is NOT approved', false === Click2EU_Auth::is_approved_employer( $mock_users[2] ) );

// 4. Approved Employer access state
$mock_current_user = $mock_users[3];
test( 'Approved employer gets "allowed" access state', 'allowed' === Click2EU_Resume_Directory::get_access_state() );
test( 'Approved employer is_approved_employer is true', true === Click2EU_Auth::is_approved_employer( $mock_users[3] ) );

// 5. Rejected Employer access state
$mock_current_user = $mock_users[4];
test( 'Rejected employer gets "rejected" access state', 'rejected' === Click2EU_Resume_Directory::get_access_state() );
test( 'Rejected employer is NOT approved', false === Click2EU_Auth::is_approved_employer( $mock_users[4] ) );

// 6. Suspended Employer access state
$mock_current_user = $mock_users[5];
test( 'Suspended employer gets "suspended" access state', 'suspended' === Click2EU_Resume_Directory::get_access_state() );
test( 'Suspended employer is NOT approved', false === Click2EU_Auth::is_approved_employer( $mock_users[5] ) );

// 7. Candidate access state (denied even with membership)
$mock_current_user = $mock_users[6];
test( 'Candidate gets "candidate_denied" access state', 'candidate_denied' === Click2EU_Resume_Directory::get_access_state() );
test( 'Candidate is_candidate returns true with User object', true === Click2EU_Auth::is_candidate( $mock_users[6] ) );
test( 'Candidate is_candidate returns true with ID', true === Click2EU_Auth::is_candidate( 6 ) );
test( 'Candidate is NOT approved employer', false === Click2EU_Auth::is_approved_employer( $mock_users[6] ) );

// 8. Filter sanitization
$_GET['c2eu_resume_keyword']  = '<script>alert("xss")</script>Senior Developer ';
$_GET['c2eu_resume_location'] = 'Berlin, Germany<b>';
$_GET['c2eu_resume_category'] = 'engineering';
$_GET['c2eu_resume_skill']    = 'php';
$_GET['c2eu_paged']           = '3';

$sanitized = Click2EU_Resume_Directory::get_sanitized_filters();
test( 'Keyword sanitization strips tags and trims', $sanitized['keyword'] === 'alert("xss")Senior Developer' );
test( 'Location sanitization strips tags', $sanitized['location'] === 'Berlin, Germany' );
test( 'Category sanitized', $sanitized['category'] === 'engineering' );
test( 'Skill sanitized', $sanitized['skill'] === 'php' );
test( 'Page is integer 3', $sanitized['paged'] === 3 );

// 9. Shortcode output check for Candidate
$mock_current_user = $mock_users[6];
$dir_instance = new Click2EU_Resume_Directory();
$output = $dir_instance->render_shortcode();
test( 'Candidate shortcode outputs candidate notice', strpos( $output, 'c2eu-resume-notice--candidate' ) !== false );
test( 'Candidate notice contains Dashboard CTA', strpos( $output, 'Candidate Dashboard' ) !== false );
test( 'Candidate notice does NOT contain resume list', strpos( $output, 'c2eu-resume-grid' ) === false );

// 10. Shortcode output check for Guest
$mock_current_user = null;
$output = $dir_instance->render_shortcode();
test( 'Guest shortcode outputs guest notice', strpos( $output, 'c2eu-resume-notice--guest' ) !== false );
test( 'Guest notice contains Sign In CTA', strpos( $output, 'Sign In / Register as Employer' ) !== false );
test( 'Guest notice does NOT contain resume list', strpos( $output, 'c2eu-resume-grid' ) === false );

// 11. Shortcode output check for Pending Employer
$mock_current_user = $mock_users[2];
$output = $dir_instance->render_shortcode();
test( 'Pending employer shortcode outputs pending notice', strpos( $output, 'c2eu-resume-notice--pending' ) !== false );
test( 'Pending notice contains verification pending text', strpos( $output, 'Employer Verification Pending' ) !== false );
test( 'Pending notice does NOT contain resume list', strpos( $output, 'c2eu-resume-grid' ) === false );

echo "\n=== RESULTS ===\n";
echo "PASS: $pass\nFAIL: $fail\nTOTAL: " . ( $pass + $fail ) . "\n";
if ( $fail === 0 ) {
    echo "ALL FUNCTIONAL MOCK TESTS PASSED!\n";
} else {
    exit( 1 );
}
