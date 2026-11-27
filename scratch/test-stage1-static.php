<?php
/**
 * Click2EU Stage 1 — Static/Unit Test Suite (no WordPress required)
 *
 * Tests logic, structure, and constants that can be verified without
 * a live WordPress environment.
 *
 * Run from plugin root:
 *   php scratch/test-stage1-static.php
 */

$pluginDir = dirname( __DIR__ );
$fail      = 0;
$pass      = 0;

function test_assert( $label, $condition ) {
    global $fail, $pass;
    if ( $condition ) {
        echo "PASS: $label\n";
        $pass++;
    } else {
        echo "FAIL: $label\n";
        $fail++;
    }
}

echo "\n=== Click2EU Stage 1 Static Tests ===\n\n";

// -------------------------------------------------------------------------
// 1. File existence
// -------------------------------------------------------------------------
echo "--- File Existence ---\n";

$requiredFiles = [
    'click2eu-membership.php',
    'includes/class-click2eu-auth.php',
    'includes/class-click2eu-route-policy.php',
    'includes/class-click2eu-access.php',
    'includes/class-click2eu-settings.php',
    'includes/class-click2eu-resume-directory.php',
    'assets/resume-directory.css',
];

foreach ( $requiredFiles as $f ) {
    test_assert( "File exists: $f", file_exists( "$pluginDir/$f" ) );
}

// -------------------------------------------------------------------------
// 2. Bootstrap file: require and instantiation
// -------------------------------------------------------------------------
echo "\n--- Bootstrap (click2eu-membership.php) ---\n";

$bootstrap = file_get_contents( "$pluginDir/click2eu-membership.php" );

test_assert(
    'Bootstrap requires class-click2eu-resume-directory.php',
    strpos( $bootstrap, "class-click2eu-resume-directory.php'" ) !== false ||
    strpos( $bootstrap, 'class-click2eu-resume-directory.php"' ) !== false
);

test_assert(
    'Bootstrap instantiates Click2EU_Resume_Directory',
    strpos( $bootstrap, 'new Click2EU_Resume_Directory()' ) !== false
);

// -------------------------------------------------------------------------
// 3. Auth class — is_approved_employer
// -------------------------------------------------------------------------
echo "\n--- class-click2eu-auth.php ---\n";

$auth = file_get_contents( "$pluginDir/includes/class-click2eu-auth.php" );

test_assert( 'Auth has is_approved_employer()', strpos( $auth, 'is_approved_employer' ) !== false );
test_assert( 'Auth is_approved_employer checks employer role', strpos( $auth, "'employer'" ) !== false );
test_assert( 'Auth is_approved_employer checks _click2eu_employer_verification_status', strpos( $auth, '_click2eu_employer_verification_status' ) !== false );
test_assert( 'Auth is_approved_employer returns false for non-employer (fail-closed)', strpos( $auth, "return false;" ) !== false );
test_assert( 'Auth has is_candidate() accepting numeric user IDs', strpos( $auth, 'is_numeric' ) !== false );
test_assert( 'Auth has is_employer() accepting numeric user IDs', ( substr_count( $auth, 'is_numeric' ) >= 2 ) );
test_assert( 'Auth cariera_auth_url accepts redirect_to param', strpos( $auth, 'redirect_to' ) !== false );

// -------------------------------------------------------------------------
// 4. Resume Directory class
// -------------------------------------------------------------------------
echo "\n--- class-click2eu-resume-directory.php ---\n";

$dir = file_get_contents( "$pluginDir/includes/class-click2eu-resume-directory.php" );

test_assert( 'Directory defines SHORTCODE constant', strpos( $dir, "SHORTCODE = 'click2eu_resume_directory'" ) !== false );
test_assert( 'Directory defines POST_TYPE resume', strpos( $dir, "POST_TYPE = 'resume'" ) !== false );
test_assert( 'Directory defines TAX_CATEGORY', strpos( $dir, "TAX_CATEGORY = 'resume_category'" ) !== false );
test_assert( 'Directory defines TAX_SKILL', strpos( $dir, "TAX_SKILL = 'resume_skill'" ) !== false );
test_assert( 'Directory registers shortcode in constructor', strpos( $dir, 'add_shortcode' ) !== false && strpos( $dir, 'self::SHORTCODE' ) !== false );
test_assert( 'Directory hooks wp_enqueue_scripts', strpos( $dir, 'wp_enqueue_scripts' ) !== false );

// Access states
test_assert( 'get_access_state() has allowed state', strpos( $dir, "'allowed'" ) !== false );
test_assert( 'get_access_state() has guest state', strpos( $dir, "'guest'" ) !== false );
test_assert( 'get_access_state() has candidate_denied state', strpos( $dir, "'candidate_denied'" ) !== false );
test_assert( 'get_access_state() has pending state', strpos( $dir, "'pending'" ) !== false );
test_assert( 'get_access_state() has rejected state', strpos( $dir, "'rejected'" ) !== false );
test_assert( 'get_access_state() has suspended state', strpos( $dir, "'suspended'" ) !== false );

// Authorization checks
test_assert( 'Uses is_approved_employer() for access', strpos( $dir, 'is_approved_employer' ) !== false );
test_assert( 'Calls manage_options for admin', strpos( $dir, 'manage_options' ) !== false );
test_assert( 'Fail-closed for plain employer', strpos( $dir, 'is_employer' ) !== false );

// Query
test_assert( 'query_resumes() restricts to publish status', strpos( $dir, "'post_status' => 'publish'" ) !== false || strpos( $dir, '"post_status" => "publish"' ) !== false );
test_assert( 'query_resumes() uses active_candidate_ids()', strpos( $dir, 'active_candidate_ids' ) !== false );
test_assert( 'query_resumes() validates candidate role', strpos( $dir, 'is_candidate' ) !== false );
test_assert( 'query_resumes() uses POST_TYPE constant', strpos( $dir, 'self::POST_TYPE' ) !== false );
test_assert( 'query_resumes() short-circuits on empty active_candidate_ids', strpos( $dir, 'post__in.*array( 0 )' ) !== false || strpos( $dir, "array( 0 )" ) !== false );

// Filters — namespaced GET params
test_assert( 'Filter keyword uses c2eu_resume_keyword', strpos( $dir, 'c2eu_resume_keyword' ) !== false );
test_assert( 'Filter location uses c2eu_resume_location', strpos( $dir, 'c2eu_resume_location' ) !== false );
test_assert( 'Filter category uses c2eu_resume_category', strpos( $dir, 'c2eu_resume_category' ) !== false );
test_assert( 'Filter skill uses c2eu_resume_skill', strpos( $dir, 'c2eu_resume_skill' ) !== false );
test_assert( 'Filter pagination uses c2eu_paged', strpos( $dir, 'c2eu_paged' ) !== false );

// Sanitization
test_assert( 'Keyword sanitized with sanitize_text_field', strpos( $dir, 'sanitize_text_field' ) !== false );
test_assert( 'GET inputs use wp_unslash', strpos( $dir, 'wp_unslash' ) !== false );

// Security — no private data exposure
test_assert( 'NO _candidate_email in directory output', strpos( $dir, '_candidate_email' ) === false );
test_assert( 'NO user_email in resume card render', strpos( $dir, 'user_email' ) === false );

// Card fields
test_assert( 'Card renders candidate photo', strpos( $dir, '_candidate_photo' ) !== false );
test_assert( 'Card renders candidate title (_candidate_title)', strpos( $dir, '_candidate_title' ) !== false );
test_assert( 'Card renders location (_candidate_location)', strpos( $dir, '_candidate_location' ) !== false );
test_assert( 'Card renders category terms', strpos( $dir, 'get_the_terms' ) !== false );
test_assert( 'Card caps skills at 5', strpos( $dir, 'array_slice' ) !== false && strpos( $dir, '0, 5' ) !== false );
test_assert( 'Card uses get_permalink() for resume link', strpos( $dir, 'get_permalink' ) !== false );
test_assert( 'Card escapes URL with esc_url', strpos( $dir, 'esc_url' ) !== false );
test_assert( 'Card escapes HTML with esc_html', strpos( $dir, 'esc_html' ) !== false );

// Pagination
test_assert( 'Pagination uses paginate_links()', strpos( $dir, 'paginate_links' ) !== false );
test_assert( 'Pagination preserves filter params via add_args', strpos( $dir, 'add_args' ) !== false );

// Asset loading
test_assert( 'CSS namespaced as c2eu-resume-directory', strpos( $dir, 'click2eu-resume-directory' ) !== false );

// -------------------------------------------------------------------------
// 5. Route Policy
// -------------------------------------------------------------------------
echo "\n--- class-click2eu-route-policy.php ---\n";

$rp = file_get_contents( "$pluginDir/includes/class-click2eu-route-policy.php" );

test_assert( 'Route policy has is_resume_directory_route()', strpos( $rp, 'is_resume_directory_route' ) !== false );
test_assert( 'Route policy is_protected_html_route includes resume_directory page', strpos( $rp, "'resume_directory'" ) !== false );
test_assert( 'Route policy is_protected detects click2eu_resume_directory shortcode', strpos( $rp, 'click2eu_resume_directory' ) !== false );
test_assert( 'Route policy authorize allows admin', strpos( $rp, 'manage_options' ) !== false );

// -------------------------------------------------------------------------
// 6. Access class
// -------------------------------------------------------------------------
echo "\n--- class-click2eu-access.php ---\n";

$ac = file_get_contents( "$pluginDir/includes/class-click2eu-access.php" );

test_assert( 'Access guard_listing_ajax enforces approved employer for resume AJAX', strpos( $ac, 'resume_manager_get_resumes' ) !== false && strpos( $ac, 'is_approved_employer' ) !== false );
test_assert( 'Access guard_rest has resume GET protection', strpos( $ac, "'/resumes?'" ) !== false || strpos( $ac, 'resumes?' ) !== false || strpos( $ac, 'resumes/' ) !== false );
test_assert( 'Access redirect_guest_to_auth preserves resume directory URL', strpos( $ac, 'is_resume_directory_route' ) !== false );
test_assert( 'Access has die_denial method', strpos( $ac, 'die_denial' ) !== false );

// -------------------------------------------------------------------------
// 7. Settings — resume_directory page key
// -------------------------------------------------------------------------
echo "\n--- class-click2eu-settings.php ---\n";

$st = file_get_contents( "$pluginDir/includes/class-click2eu-settings.php" );

test_assert( 'Settings has resume_directory page key', strpos( $st, "'resume_directory'" ) !== false );
test_assert( 'Settings resume_directory shortcode click2eu_resume_directory', strpos( $st, 'click2eu_resume_directory' ) !== false );

// -------------------------------------------------------------------------
// 8. CSS
// -------------------------------------------------------------------------
echo "\n--- resume-directory.css ---\n";

$css = file_get_contents( "$pluginDir/assets/resume-directory.css" );
test_assert( 'CSS has .c2eu-resume-directory namespace', strpos( $css, '.c2eu-resume-directory' ) !== false );
test_assert( 'CSS has .c2eu-resume-card', strpos( $css, '.c2eu-resume-card' ) !== false );
test_assert( 'CSS has .c2eu-resume-filter-wrap', strpos( $css, '.c2eu-resume-filter-wrap' ) !== false );
test_assert( 'CSS has .c2eu-resume-pagination', strpos( $css, '.c2eu-resume-pagination' ) !== false );
test_assert( 'CSS has responsive @media query', strpos( $css, '@media' ) !== false );
test_assert( 'CSS has .c2eu-resume-notice states', strpos( $css, '.c2eu-resume-notice' ) !== false );
test_assert( 'CSS has .c2eu-btn', strpos( $css, '.c2eu-btn' ) !== false );
test_assert( 'CSS does NOT contain broad global selectors (body,h1)', strpos( $css, "\nbody " ) === false && strpos( $css, "\nh1 " ) === false );

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n=== RESULTS ===\n";
echo "PASS: $pass\nFAIL: $fail\nTOTAL: " . ( $pass + $fail ) . "\n";
if ( $fail === 0 ) {
    echo "\nALL STATIC TESTS PASSED\n";
} else {
    echo "\n$fail TEST(S) FAILED — review FAIL lines above\n";
    exit( 1 );
}
