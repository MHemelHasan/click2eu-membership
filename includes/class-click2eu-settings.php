<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Phase-1 page mapping, fixed launch policy and non-sensitive integration diagnostics. */
final class Click2EU_Settings {
	const OPTION = 'click2eu_phase1_settings';
	const PAGE_SLUG = 'click2eu-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	public static function page_definitions() {
		return array(
			'homepage'            => array( 'label' => __( 'Homepage', 'click2eu-membership' ), 'option' => 'page_on_front' ),
			'join'                => array( 'label' => __( 'Join Us', 'click2eu-membership' ), 'option' => Click2EU_Config::JOIN_PAGE_OPTION, 'shortcode' => 'click2eu_packages_section' ),
			'login'               => array( 'label' => __( 'Login / Registration', 'click2eu-membership' ), 'option' => 'cariera_login_register_page' ),
			'candidate_dashboard' => array( 'label' => __( 'Candidate Dashboard', 'click2eu-membership' ), 'option' => 'resume_manager_candidate_dashboard_page_id', 'shortcode' => 'candidate_dashboard' ),
			'my_profile'          => array( 'label' => __( 'My Profile', 'click2eu-membership' ), 'option' => 'cariera_dashboard_profile_page' ),
			'my_resumes'          => array( 'label' => __( 'My Resumes', 'click2eu-membership' ), 'option' => 'resume_manager_candidate_dashboard_page_id', 'shortcode' => 'candidate_dashboard' ),
			'submit_resume'       => array( 'label' => __( 'Submit Resume', 'click2eu-membership' ), 'option' => 'resume_manager_submit_resume_form_page_id', 'shortcode' => 'submit_resume_form' ),
			'my_account'          => array( 'label' => __( 'WooCommerce My Account', 'click2eu-membership' ), 'option' => 'woocommerce_myaccount_page_id', 'shortcode' => 'woocommerce_my_account' ),
			'employer_dashboard'  => array( 'label' => __( 'Employer Dashboard', 'click2eu-membership' ), 'option' => 'job_manager_job_dashboard_page_id', 'shortcode' => 'job_dashboard' ),
			'submit_company'      => array( 'label' => __( 'Submit Company', 'click2eu-membership' ), 'option' => 'cariera_submit_company_page', 'shortcode' => 'submit_company' ),
			'company_dashboard'   => array( 'label' => __( 'Company Dashboard', 'click2eu-membership' ), 'option' => 'cariera_company_dashboard_page', 'shortcode' => 'company_dashboard' ),
			'submit_job'          => array( 'label' => __( 'Submit Job', 'click2eu-membership' ), 'option' => 'job_manager_submit_job_form_page_id', 'shortcode' => 'submit_job_form' ),
			'job_dashboard'       => array( 'label' => __( 'Job Dashboard', 'click2eu-membership' ), 'option' => 'job_manager_job_dashboard_page_id', 'shortcode' => 'job_dashboard' ),
			'job_search'          => array( 'label' => __( 'Job Search', 'click2eu-membership' ), 'option' => 'job_manager_jobs_page_id', 'shortcode' => 'jobs' ),
			'company_listings'    => array( 'label' => __( 'Company Listings', 'click2eu-membership' ), 'option' => 'cariera_companies_page' ),
		);
	}

	public static function defaults() {
		$pages = array();
		foreach ( self::page_definitions() as $key => $definition ) {
			$pages[ $key ] = absint( get_option( $definition['option'] ) );
		}
		return array(
			'pages'  => $pages,
			'policy' => array(
				'paid_job_browsing'   => 1,
				'paid_company_details' => 1,
				'paid_resume_submit'   => 1,
				'paid_applications'    => 1,
			),
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array_replace_recursive( self::defaults(), $saved );
	}

	public static function page_id( $key ) {
		$settings = self::all();
		return absint( $settings['pages'][ $key ] ?? 0 );
	}

	public static function page_url( $key ) {
		$page_id = self::page_id( $key );
		return $page_id ? get_permalink( $page_id ) : '';
	}

	public static function page_is_valid( $page_id ) {
		$page = get_post( absint( $page_id ) );
		return $page && 'page' === $page->post_type && 'publish' === $page->post_status;
	}

	public function register_page() {
		add_submenu_page(
			Click2EU_Admin::PAGE_SLUG,
			__( 'Click2EU Membership Settings', 'click2eu-membership' ),
			__( 'Settings', 'click2eu-membership' ),
			Click2EU_Admin::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_setting() {
		register_setting(
			'click2eu_phase1',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array( 'pages' => array(), 'policy' => self::defaults()['policy'] );
		foreach ( self::page_definitions() as $key => $definition ) {
			$page_id = absint( $input['pages'][ $key ] ?? 0 );
			if ( $page_id && ! self::page_is_valid( $page_id ) ) {
				add_settings_error( self::OPTION, 'invalid_' . $key, sprintf( __( '%s must be a published WordPress Page.', 'click2eu-membership' ), $definition['label'] ) );
				$page_id = 0;
			}
			$clean['pages'][ $key ] = $page_id;
		}
		return $clean;
	}

	public function render_page() {
		if ( ! current_user_can( Click2EU_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage membership settings.', 'click2eu-membership' ), '', array( 'response' => 403 ) );
		}
		$settings = self::all();
		$pages = get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) );
		echo '<div class="wrap c2eu-admin-memberships c2eu-settings"><h1>' . esc_html__( 'Click2EU Membership Settings', 'click2eu-membership' ) . '</h1>';
		settings_errors( self::OPTION );
		echo '<form method="post" action="options.php">';
		settings_fields( 'click2eu_phase1' );
		echo '<h2>' . esc_html__( 'A. Page Mapping', 'click2eu-membership' ) . '</h2><p>' . esc_html__( 'Select the matching page in this environment. Numeric IDs are deliberately not portable between sites.', 'click2eu-membership' ) . '</p><table class="form-table" role="presentation">';
		foreach ( self::page_definitions() as $key => $definition ) {
			$current = absint( $settings['pages'][ $key ] ?? 0 );
			echo '<tr><th scope="row"><label for="c2eu-page-' . esc_attr( $key ) . '">' . esc_html( $definition['label'] ) . '</label></th><td><select class="regular-text c2eu-page-select" id="c2eu-page-' . esc_attr( $key ) . '" name="' . esc_attr( self::OPTION ) . '[pages][' . esc_attr( $key ) . ']"><option value="0">' . esc_html__( '— Not mapped —', 'click2eu-membership' ) . '</option>';
			foreach ( $pages as $page ) {
				echo '<option value="' . esc_attr( $page->ID ) . '" ' . selected( $current, $page->ID, false ) . '>' . esc_html( $page->post_title . ' (#' . $page->ID . ')' ) . '</option>';
			}
			echo '</select>';
			if ( $current && self::page_is_valid( $current ) ) {
				echo ' <a href="' . esc_url( get_permalink( $current ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'click2eu-membership' ) . '</a>';
			} else {
				echo '<p class="description c2eu-diagnostic-error">' . esc_html__( 'Missing or unpublished. Protected actions remain fail-closed.', 'click2eu-membership' ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table><h2>' . esc_html__( 'B. Access Policy', 'click2eu-membership' ) . '</h2><p>' . esc_html__( 'These launch-critical Phase-1 controls are enforced and cannot be disabled from the UI.', 'click2eu-membership' ) . '</p><fieldset>';
		foreach ( array( 'paid_job_browsing' => 'Paid-only Job Browsing', 'paid_company_details' => 'Paid-only Company Details', 'paid_resume_submit' => 'Paid-only Resume Submission', 'paid_applications' => 'Paid-only Job Applications' ) as $key => $label ) {
			echo '<p><label><input type="checkbox" checked disabled> ' . esc_html( $label ) . '</label><input type="hidden" name="' . esc_attr( self::OPTION ) . '[policy][' . esc_attr( $key ) . ']" value="1"></p>';
		}
		echo '<p><strong>' . esc_html__( 'Candidate login:', 'click2eu-membership' ) . '</strong> ' . esc_html__( 'Join Us when unpaid or Pending; Candidate Dashboard when Active.', 'click2eu-membership' ) . '</p></fieldset>';
		submit_button();
		echo '</form><hr><h2>' . esc_html__( 'C. Integration Diagnostics', 'click2eu-membership' ) . '</h2>';
		$this->render_diagnostics();
		echo '</div>';
	}

	private function render_diagnostics() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$core_active = is_plugin_active( 'cariera-plugin/cariera-core.php' );
		$addons_active = is_plugin_active( 'cariera-addons/cariera-addons.php' );
		$core_file = WP_PLUGIN_DIR . '/cariera-plugin/cariera-core.php';
		$addons_file = WP_PLUGIN_DIR . '/cariera-addons/cariera-addons.php';
		$core_data = file_exists( $core_file ) ? get_plugin_data( $core_file, false, false ) : array();
		$addons_data = file_exists( $addons_file ) ? get_plugin_data( $addons_file, false, false ) : array();
		$roles = wp_roles()->roles;
		$rows = array(
			__( 'Plugin version', 'click2eu-membership' ) => Click2EU_Config::VERSION,
			__( 'WooCommerce / HPOS', 'click2eu-membership' ) => ( defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Inactive', 'click2eu-membership' ) ) . ' / ' . ( $hpos ? 'enabled' : 'disabled or unknown' ),
			__( 'Cariera Core / Addons', 'click2eu-membership' ) => ( $core_data['Version'] ?? 'unknown' ) . ' ' . ( $core_active ? 'active' : 'inactive' ) . ' / ' . ( $addons_data['Version'] ?? 'unknown' ) . ' ' . ( $addons_active ? 'active' : 'inactive' ),
			__( 'Authentication route', 'click2eu-membership' ) => Click2EU_Auth::cariera_auth_url(),
			__( 'Candidate / Employer roles', 'click2eu-membership' ) => ( isset( $roles['candidate'] ) ? 'candidate verified' : 'candidate missing' ) . ' / ' . ( isset( $roles['employer'] ) ? 'employer verified' : 'employer missing' ),
			__( 'Post types', 'click2eu-membership' ) => 'job_listing: ' . ( post_type_exists( 'job_listing' ) ? 'yes' : 'no' ) . '; company: ' . ( post_type_exists( 'company' ) ? 'yes' : 'no' ) . '; resume: ' . ( post_type_exists( 'resume' ) ? 'yes' : 'no' ),
			__( 'Taxonomies', 'click2eu-membership' ) => 'job_listing_category: ' . ( taxonomy_exists( 'job_listing_category' ) ? 'yes' : 'no' ) . '; resume_category: ' . ( taxonomy_exists( 'resume_category' ) ? 'yes' : 'no' ),
			__( 'Membership database', 'click2eu-membership' ) => (string) get_option( Click2EU_Config::DB_VERSION_OPTION, 'missing' ),
			__( 'Notification database', 'click2eu-membership' ) => (string) get_option( Click2EU_Config::NOTIFICATION_DB_VERSION_OPTION, 'missing' ),
			__( 'Resume category mapping', 'click2eu-membership' ) => Click2EU_Category_Access::resume_category_map() ? 'configured' : 'missing',
			__( 'Handler coverage', 'click2eu-membership' ) => $core_active && $addons_active ? 'runtime hooks available; execute staging E2E' : 'source-verified only; Cariera runtime E2E blocked',
		);
		echo '<table class="widefat striped c2eu-diagnostics"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table><h3>' . esc_html__( 'Mapped pages', 'click2eu-membership' ) . '</h3><ul>';
		foreach ( self::page_definitions() as $key => $definition ) {
			$page_id = self::page_id( $key );
			echo '<li><strong>' . esc_html( $definition['label'] ) . ':</strong> ' . ( $page_id && self::page_is_valid( $page_id ) ? '<a href="' . esc_url( get_permalink( $page_id ) ) . '">#' . esc_html( $page_id ) . ' ' . esc_html( get_the_title( $page_id ) ) . '</a>' : '<span class="c2eu-diagnostic-error">' . esc_html__( 'missing/unpublished', 'click2eu-membership' ) . '</span>' ) . '</li>';
		}
		echo '</ul>';
	}
}
