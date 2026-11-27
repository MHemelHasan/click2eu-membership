<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Click2EU Resume Directory
 *
 * Provides the [click2eu_resume_directory] shortcode, access control,
 * filtered resume queries, card rendering, and pagination.
 */
final class Click2EU_Resume_Directory {
	const SHORTCODE = 'click2eu_resume_directory';
	const POST_TYPE = 'resume';
	const TAX_CATEGORY = 'resume_category';
	const TAX_SKILL = 'resume_skill';

	public function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Determines the access state for the current or specified user.
	 *
	 * Fail-closed states:
	 * - 'allowed'          : Administrator or Approved Employer.
	 * - 'guest'            : Unauthenticated visitor.
	 * - 'candidate_denied' : Candidate account (even with active membership).
	 * - 'pending'          : Employer awaiting verification approval.
	 * - 'rejected'         : Employer verification rejected.
	 * - 'suspended'        : Employer account suspended.
	 * - 'denied'           : Generic unauthorized actor.
	 *
	 * @param WP_User|int|null $user User object, ID, or null for current user.
	 * @return string
	 */
	public static function get_access_state( $user = null ) {
		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', absint( $user ) );
		} elseif ( ! ( $user instanceof WP_User ) ) {
			$user = wp_get_current_user();
		}

		if ( ! $user || ! $user->exists() ) {
			return 'guest';
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return 'allowed';
		}

		if ( Click2EU_Auth::is_approved_employer( $user ) ) {
			return 'allowed';
		}

		if ( Click2EU_Auth::is_employer( $user ) ) {
			$status = get_user_meta( $user->ID, '_click2eu_employer_verification_status', true );
			if ( 'rejected' === $status ) {
				return 'rejected';
			}
			if ( 'suspended' === $status ) {
				return 'suspended';
			}
			return 'pending';
		}

		if ( Click2EU_Auth::is_candidate( $user ) ) {
			return 'candidate_denied';
		}

		return 'denied';
	}

	/**
	 * Enqueue assets only when appropriate.
	 */
	public function enqueue_assets() {
		if ( ! $this->should_enqueue_assets() ) {
			return;
		}

		$css_path = dirname( __DIR__ ) . '/assets/resume-directory.css';
		$ver      = file_exists( $css_path ) ? filemtime( $css_path ) : Click2EU_Config::VERSION;

		wp_enqueue_style(
			'click2eu-resume-directory',
			plugins_url( 'assets/resume-directory.css', dirname( __FILE__ ) ),
			array(),
			$ver
		);
	}

	/**
	 * Checks if styles should be enqueued on the current request.
	 *
	 * @return bool
	 */
	private function should_enqueue_assets() {
		if ( is_admin() ) {
			return false;
		}

		$mapped_page = class_exists( 'Click2EU_Settings' ) ? Click2EU_Settings::page_id( 'resume_directory' ) : 0;
		if ( $mapped_page && is_page( $mapped_page ) ) {
			return true;
		}

		if ( is_singular( 'page' ) ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render the [click2eu_resume_directory] shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public function render_shortcode( $atts = array() ) {
		// Enqueue styles if not already done.
		if ( ! wp_style_is( 'click2eu-resume-directory', 'enqueued' ) ) {
			$css_path = dirname( __DIR__ ) . '/assets/resume-directory.css';
			$ver      = file_exists( $css_path ) ? filemtime( $css_path ) : Click2EU_Config::VERSION;
			wp_enqueue_style(
				'click2eu-resume-directory',
				plugins_url( 'assets/resume-directory.css', dirname( __FILE__ ) ),
				array(),
				$ver
			);
		}

		$access_state = self::get_access_state();

		ob_start();
		echo '<div class="c2eu-resume-directory">';

		if ( 'allowed' !== $access_state ) {
			$this->render_access_notice( $access_state );
		} else {
			$this->render_directory_content( $atts );
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Renders the notice when access is not 'allowed'.
	 *
	 * @param string $state Access state string.
	 */
	private function render_access_notice( $state ) {
		switch ( $state ) {
			case 'guest':
				$login_url = Click2EU_Auth::cariera_auth_url( get_permalink() );
				?>
				<div class="c2eu-resume-notice c2eu-resume-notice--guest">
					<div class="c2eu-resume-notice__icon">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
					</div>
					<h3 class="c2eu-resume-notice__title"><?php esc_html_e( 'Employer Sign In Required', 'click2eu-membership' ); ?></h3>
					<p class="c2eu-resume-notice__text"><?php esc_html_e( 'The Click2EU Resume Directory is exclusively available to registered and verified employers. Please sign in or register an employer account to browse qualified candidate profiles.', 'click2eu-membership' ); ?></p>
					<div class="c2eu-resume-notice__actions">
						<a href="<?php echo esc_url( $login_url ); ?>" class="c2eu-btn c2eu-btn--primary">
							<?php esc_html_e( 'Sign In / Register as Employer', 'click2eu-membership' ); ?> &rarr;
						</a>
					</div>
				</div>
				<?php
				break;

			case 'candidate_denied':
				$dashboard_url = class_exists( 'Click2EU_Settings' ) ? Click2EU_Settings::page_url( 'candidate_dashboard' ) : '';
				$dashboard_url = $dashboard_url ?: home_url( '/candidate-dashboard/' );
				?>
				<div class="c2eu-resume-notice c2eu-resume-notice--candidate">
					<div class="c2eu-resume-notice__icon">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
					</div>
					<h3 class="c2eu-resume-notice__title"><?php esc_html_e( 'Employer Access Only', 'click2eu-membership' ); ?></h3>
					<p class="c2eu-resume-notice__text"><?php esc_html_e( 'The Resume Directory is accessible to verified employers only. Candidates cannot browse other candidate profiles. You can manage your own resume, skills, and applications from your Candidate Dashboard.', 'click2eu-membership' ); ?></p>
					<div class="c2eu-resume-notice__actions">
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="c2eu-btn c2eu-btn--secondary">
							<?php esc_html_e( 'Go to Candidate Dashboard', 'click2eu-membership' ); ?> &rarr;
						</a>
					</div>
				</div>
				<?php
				break;

			case 'pending':
				?>
				<div class="c2eu-resume-notice c2eu-resume-notice--pending">
					<div class="c2eu-resume-notice__icon">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
					</div>
					<h3 class="c2eu-resume-notice__title"><?php esc_html_e( 'Employer Verification Pending', 'click2eu-membership' ); ?></h3>
					<p class="c2eu-resume-notice__text"><?php esc_html_e( 'Thank you for registering. Your employer account is currently pending administrative verification. Once our team approves your company registration, you will have full access to search and view candidate resumes.', 'click2eu-membership' ); ?></p>
				</div>
				<?php
				break;

			case 'rejected':
				?>
				<div class="c2eu-resume-notice c2eu-resume-notice--restricted">
					<div class="c2eu-resume-notice__icon">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
					</div>
					<h3 class="c2eu-resume-notice__title"><?php esc_html_e( 'Verification Not Approved', 'click2eu-membership' ); ?></h3>
					<p class="c2eu-resume-notice__text"><?php esc_html_e( 'Your employer verification request was not approved. Please contact Click2EU support if you believe this was an error.', 'click2eu-membership' ); ?></p>
				</div>
				<?php
				break;

			case 'suspended':
				?>
				<div class="c2eu-resume-notice c2eu-resume-notice--restricted">
					<div class="c2eu-resume-notice__icon">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"></polygon><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
					</div>
					<h3 class="c2eu-resume-notice__title"><?php esc_html_e( 'Account Suspended', 'click2eu-membership' ); ?></h3>
					<p class="c2eu-resume-notice__text"><?php esc_html_e( 'Your employer account has been suspended. Please contact Click2EU support for assistance.', 'click2eu-membership' ); ?></p>
				</div>
				<?php
				break;

			default:
				?>
				<div class="c2eu-resume-notice c2eu-resume-notice--denied">
					<div class="c2eu-resume-notice__icon">
						<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
					</div>
					<h3 class="c2eu-resume-notice__title"><?php esc_html_e( 'Access Denied', 'click2eu-membership' ); ?></h3>
					<p class="c2eu-resume-notice__text"><?php esc_html_e( 'An approved employer account is required to access the Resume Directory.', 'click2eu-membership' ); ?></p>
				</div>
				<?php
				break;
		}
	}

	/**
	 * Sanitizes search parameters from GET input.
	 *
	 * @return array
	 */
	public static function get_sanitized_filters() {
		return array(
			'keyword'  => isset( $_GET['c2eu_resume_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['c2eu_resume_keyword'] ) ) : '',
			'location' => isset( $_GET['c2eu_resume_location'] ) ? sanitize_text_field( wp_unslash( $_GET['c2eu_resume_location'] ) ) : '',
			'category' => isset( $_GET['c2eu_resume_category'] ) ? sanitize_text_field( wp_unslash( $_GET['c2eu_resume_category'] ) ) : '',
			'skill'    => isset( $_GET['c2eu_resume_skill'] ) ? sanitize_text_field( wp_unslash( $_GET['c2eu_resume_skill'] ) ) : '',
			'paged'    => max( 1, absint( $_GET['c2eu_paged'] ?? ( $_GET['paged'] ?? 1 ) ) ),
		);
	}

	/**
	 * Executes the resume directory query.
	 *
	 * Guaranteed constraints:
	 * - post_type = 'resume'
	 * - post_status = 'publish'
	 * - Author IN active candidate IDs
	 * - Nonce/N+1 optimized (pre-caches meta and terms)
	 *
	 * @param array $filters Sanitized filters.
	 * @return WP_Query
	 */
	public static function query_resumes( $filters = array() ) {
		$filters = ! empty( $filters ) ? $filters : self::get_sanitized_filters();

		// Candidate authors with effective Active Click2EU membership.
		$raw_candidate_ids = Click2EU_Route_Policy::active_candidate_ids();

		// Strictly verify candidate role (exclude admin or non-candidate accounts).
		$active_candidate_ids = array();
		if ( ! empty( $raw_candidate_ids ) ) {
			foreach ( $raw_candidate_ids as $uid ) {
				if ( Click2EU_Auth::is_candidate( $uid ) ) {
					$active_candidate_ids[] = absint( $uid );
				}
			}
		}

		$per_page = absint( get_option( 'resume_manager_per_page', 10 ) );
		$per_page = $per_page > 0 ? $per_page : 10;

		// Short-circuit if no candidates have active membership.
		if ( empty( $active_candidate_ids ) ) {
			return new WP_Query( array(
				'post_type'           => self::POST_TYPE,
				'post_status'         => 'publish',
				'post__in'            => array( 0 ),
				'posts_per_page'      => $per_page,
				'paged'               => $filters['paged'],
				'ignore_sticky_posts' => true,
			) );
		}

		$query_args = array(
			'post_type'                      => self::POST_TYPE,
			'post_status'                    => 'publish',
			'author__in'                     => $active_candidate_ids,
			'posts_per_page'                 => $per_page,
			'paged'                          => $filters['paged'],
			'orderby'                        => 'date',
			'order'                          => 'DESC',
			'ignore_sticky_posts'            => true,
			'update_post_meta_cache'         => true,
			'update_post_term_cache'         => true,
			'click2eu_resume_directory_query' => true,
		);

		// Location meta filter.
		if ( ! empty( $filters['location'] ) ) {
			$loc = $filters['location'];
			$query_args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => '_candidate_location',
					'value'   => $loc,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'geolocation_formatted_address',
					'value'   => $loc,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'geolocation_state_long',
					'value'   => $loc,
					'compare' => 'LIKE',
				),
			);
		}

		// Tax query for category and/or skill.
		$tax_queries = array();

		if ( ! empty( $filters['category'] ) && taxonomy_exists( self::TAX_CATEGORY ) ) {
			$cat_val   = $filters['category'];
			$field     = is_numeric( $cat_val ) ? 'term_id' : 'slug';
			$terms_val = is_numeric( $cat_val ) ? absint( $cat_val ) : sanitize_title( $cat_val );

			$tax_queries[] = array(
				'taxonomy'         => self::TAX_CATEGORY,
				'field'            => $field,
				'terms'            => array( $terms_val ),
				'include_children' => true,
			);
		}

		if ( ! empty( $filters['skill'] ) && taxonomy_exists( self::TAX_SKILL ) ) {
			$skill_val = $filters['skill'];
			$field     = is_numeric( $skill_val ) ? 'term_id' : 'slug';
			$terms_val = is_numeric( $skill_val ) ? absint( $skill_val ) : sanitize_title( $skill_val );

			$tax_queries[] = array(
				'taxonomy' => self::TAX_SKILL,
				'field'    => $field,
				'terms'    => array( $terms_val ),
			);
		}

		if ( ! empty( $tax_queries ) ) {
			if ( count( $tax_queries ) > 1 ) {
				$tax_queries['relation'] = 'AND';
			}
			$query_args['tax_query'] = $tax_queries;
		}

		// Keyword search filter across post title, content, excerpt, candidate title/name, and taxonomies.
		$keyword = $filters['keyword'];
		$filter_callback = null;

		if ( ! empty( $keyword ) ) {
			$filter_callback = function( $search, $wp_query ) use ( $keyword ) {
				if ( ! $wp_query->get( 'click2eu_resume_directory_query' ) ) {
					return $search;
				}
				global $wpdb;
				$like = '%' . $wpdb->esc_like( $keyword ) . '%';

				$title_part   = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );
				$content_part = $wpdb->prepare( "{$wpdb->posts}.post_content LIKE %s", $like );
				$excerpt_part = $wpdb->prepare( "{$wpdb->posts}.post_excerpt LIKE %s", $like );
				$meta_part    = $wpdb->prepare(
					"{$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_candidate_title', '_candidate_name', '_candidate_location') AND meta_value LIKE %s )",
					$like
				);
				$tax_part     = $wpdb->prepare(
					"{$wpdb->posts}.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id WHERE tt.taxonomy IN ('resume_category', 'resume_skill') AND t.name LIKE %s )",
					$like
				);

				return " AND ( {$title_part} OR {$content_part} OR {$excerpt_part} OR {$meta_part} OR {$tax_part} ) ";
			};

			add_filter( 'posts_search', $filter_callback, 10, 2 );
		}

		$query = new WP_Query( $query_args );

		if ( $filter_callback ) {
			remove_filter( 'posts_search', $filter_callback, 10 );
		}

		return $query;
	}

	/**
	 * Renders the full directory content for authorized users.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	private function render_directory_content( $atts ) {
		$filters = self::get_sanitized_filters();
		$query   = self::query_resumes( $filters );

		$this->render_filter_bar( $filters );
		$this->render_results( $query, $filters );
		$this->render_pagination( $query, $filters );

		wp_reset_postdata();
	}

	/**
	 * Renders the filter form bar.
	 *
	 * @param array $filters Active filters.
	 */
	private function render_filter_bar( $filters ) {
		$action_url = get_permalink();
		$has_filters = ! empty( $filters['keyword'] ) || ! empty( $filters['location'] ) || ! empty( $filters['category'] ) || ! empty( $filters['skill'] );

		// Retrieve categories if taxonomy exists.
		$categories = taxonomy_exists( self::TAX_CATEGORY ) ? get_terms( array(
			'taxonomy'   => self::TAX_CATEGORY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) ) : array();

		// Retrieve skills if taxonomy exists.
		$skills = taxonomy_exists( self::TAX_SKILL ) ? get_terms( array(
			'taxonomy'   => self::TAX_SKILL,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) ) : array();
		?>
		<div class="c2eu-resume-filter-wrap">
			<form method="get" action="<?php echo esc_url( $action_url ); ?>" class="c2eu-resume-filters">
				<div class="c2eu-resume-filters__row">
					<!-- Keyword -->
					<div class="c2eu-form-group c2eu-form-group--keyword">
						<label for="c2eu_resume_keyword" class="c2eu-form-label"><?php esc_html_e( 'Keywords', 'click2eu-membership' ); ?></label>
						<div class="c2eu-input-icon-wrap">
							<svg class="c2eu-input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
							<input
								type="text"
								id="c2eu_resume_keyword"
								name="c2eu_resume_keyword"
								class="c2eu-form-input"
								placeholder="<?php esc_attr_e( 'Candidate name, title, skill...', 'click2eu-membership' ); ?>"
								value="<?php echo esc_attr( $filters['keyword'] ); ?>"
							/>
						</div>
					</div>

					<!-- Location -->
					<div class="c2eu-form-group c2eu-form-group--location">
						<label for="c2eu_resume_location" class="c2eu-form-label"><?php esc_html_e( 'Location', 'click2eu-membership' ); ?></label>
						<div class="c2eu-input-icon-wrap">
							<svg class="c2eu-input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
							<input
								type="text"
								id="c2eu_resume_location"
								name="c2eu_resume_location"
								class="c2eu-form-input"
								placeholder="<?php esc_attr_e( 'City, region or country...', 'click2eu-membership' ); ?>"
								value="<?php echo esc_attr( $filters['location'] ); ?>"
							/>
						</div>
					</div>

					<!-- Category -->
					<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<div class="c2eu-form-group c2eu-form-group--category">
						<label for="c2eu_resume_category" class="c2eu-form-label"><?php esc_html_e( 'Category', 'click2eu-membership' ); ?></label>
						<select id="c2eu_resume_category" name="c2eu_resume_category" class="c2eu-form-select">
							<option value=""><?php esc_html_e( 'All Categories', 'click2eu-membership' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php selected( $filters['category'], $cat->slug ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<!-- Skill -->
					<?php if ( ! empty( $skills ) && ! is_wp_error( $skills ) ) : ?>
					<div class="c2eu-form-group c2eu-form-group--skill">
						<label for="c2eu_resume_skill" class="c2eu-form-label"><?php esc_html_e( 'Skill', 'click2eu-membership' ); ?></label>
						<select id="c2eu_resume_skill" name="c2eu_resume_skill" class="c2eu-form-select">
							<option value=""><?php esc_html_e( 'All Skills', 'click2eu-membership' ); ?></option>
							<?php foreach ( $skills as $sk ) : ?>
								<option value="<?php echo esc_attr( $sk->slug ); ?>" <?php selected( $filters['skill'], $sk->slug ); ?>>
									<?php echo esc_html( $sk->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endif; ?>

					<!-- Actions -->
					<div class="c2eu-form-group c2eu-form-group--submit">
						<button type="submit" class="c2eu-btn c2eu-btn--primary c2eu-btn--search">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
							<?php esc_html_e( 'Filter Resumes', 'click2eu-membership' ); ?>
						</button>
						<?php if ( $has_filters ) : ?>
							<a href="<?php echo esc_url( $action_url ); ?>" class="c2eu-btn c2eu-btn--reset" title="<?php esc_attr_e( 'Clear all filters', 'click2eu-membership' ); ?>">
								<?php esc_html_e( 'Reset', 'click2eu-membership' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders resume cards or empty state.
	 *
	 * @param WP_Query $query Query object.
	 * @param array    $filters Active filters.
	 */
	private function render_results( $query, $filters ) {
		$total       = (int) $query->found_posts;
		$has_filters = ! empty( $filters['keyword'] ) || ! empty( $filters['location'] ) || ! empty( $filters['category'] ) || ! empty( $filters['skill'] );

		?>
		<div class="c2eu-resume-results-header">
			<div class="c2eu-resume-count">
				<?php
				if ( 0 === $total ) {
					esc_html_e( 'No candidates found', 'click2eu-membership' );
				} elseif ( 1 === $total ) {
					esc_html_e( 'Showing 1 candidate profile', 'click2eu-membership' );
				} else {
					$per_page    = $query->query_vars['posts_per_page'];
					$current_page = $filters['paged'];
					$from        = ( ( $current_page - 1 ) * $per_page ) + 1;
					$to          = min( $total, $current_page * $per_page );
					/* translators: 1: start number, 2: end number, 3: total found */
					printf( esc_html__( 'Showing %1$d–%2$d of %3$d candidate profiles', 'click2eu-membership' ), $from, $to, $total );
				}
				?>
			</div>
		</div>
		<?php

		if ( ! $query->have_posts() ) {
			?>
			<div class="c2eu-resume-empty">
				<div class="c2eu-resume-empty__icon">
					<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
				</div>
				<h4 class="c2eu-resume-empty__title"><?php esc_html_e( 'No Candidate Resumes Found', 'click2eu-membership' ); ?></h4>
				<p class="c2eu-resume-empty__text">
					<?php if ( $has_filters ) : ?>
						<?php esc_html_e( 'No candidate profiles matched your search criteria. Try removing or modifying some of your filters.', 'click2eu-membership' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'There are currently no active candidate profiles available in the directory.', 'click2eu-membership' ); ?>
					<?php endif; ?>
				</p>
				<?php if ( $has_filters ) : ?>
					<div class="c2eu-resume-empty__actions">
						<a href="<?php echo esc_url( get_permalink() ); ?>" class="c2eu-btn c2eu-btn--secondary">
							<?php esc_html_e( 'Clear All Filters', 'click2eu-membership' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		echo '<div class="c2eu-resume-grid">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$this->render_resume_card( get_post() );
		}

		echo '</div>';
	}

	/**
	 * Renders a single resume card.
	 *
	 * NEVER displays candidate email or private contact info.
	 *
	 * @param WP_Post $post Resume post object.
	 */
	private function render_resume_card( $post ) {
		$resume_id   = $post->ID;
		$candidate_name  = get_the_title( $post );
		$candidate_title = get_post_meta( $resume_id, '_candidate_title', true );
		$location        = get_post_meta( $resume_id, '_candidate_location', true );
		if ( empty( $location ) ) {
			$location = get_post_meta( $resume_id, 'geolocation_formatted_address', true );
		}

		// Candidate Photo resolution.
		$photo_url = '';
		if ( function_exists( 'get_the_candidate_photo' ) ) {
			$photo_url = get_the_candidate_photo( $post );
		}
		if ( empty( $photo_url ) ) {
			$meta_photo = get_post_meta( $resume_id, '_candidate_photo', true );
			if ( is_numeric( $meta_photo ) ) {
				$photo_url = wp_get_attachment_image_url( $meta_photo, 'thumbnail' );
			} elseif ( is_string( $meta_photo ) && ! empty( $meta_photo ) ) {
				$photo_url = esc_url_raw( $meta_photo );
			}
		}
		if ( empty( $photo_url ) && function_exists( 'get_avatar_url' ) ) {
			$photo_url = get_avatar_url( $post->post_author, array( 'size' => 120 ) );
		}

		// Categories.
		$categories = taxonomy_exists( self::TAX_CATEGORY ) ? get_the_terms( $resume_id, self::TAX_CATEGORY ) : array();

		// Skills (capped to 5).
		$skills = taxonomy_exists( self::TAX_SKILL ) ? get_the_terms( $resume_id, self::TAX_SKILL ) : array();
		if ( empty( $skills ) || is_wp_error( $skills ) ) {
			$meta_skills = get_post_meta( $resume_id, '_resume_skills', true );
			if ( is_string( $meta_skills ) && ! empty( $meta_skills ) ) {
				$skill_names = array_map( 'trim', explode( ',', $meta_skills ) );
				$skills = array();
				foreach ( $skill_names as $sname ) {
					if ( '' !== $sname ) {
						$skills[] = (object) array( 'name' => $sname );
					}
				}
			} else {
				$skills = array();
			}
		}
		$capped_skills = is_array( $skills ) ? array_slice( $skills, 0, 5 ) : array();
		$skills_count  = is_array( $skills ) ? count( $skills ) : 0;
		$extra_skills  = $skills_count > 5 ? $skills_count - 5 : 0;

		// Summary: reliable public excerpt trimmed to 24 words.
		$summary = ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 );

		// Actual permalink.
		$permalink = get_permalink( $resume_id );

		?>
		<article class="c2eu-resume-card" id="c2eu-resume-card-<?php echo esc_attr( $resume_id ); ?>">
			<div class="c2eu-resume-card__inner">
				<div class="c2eu-resume-card__header">
					<!-- Photo -->
					<div class="c2eu-resume-card__photo-wrap">
						<?php if ( ! empty( $photo_url ) ) : ?>
							<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $candidate_name ); ?>" class="c2eu-resume-card__photo" loading="lazy" />
						<?php else : ?>
							<div class="c2eu-resume-card__photo-fallback">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
							</div>
						<?php endif; ?>
					</div>

					<!-- Identity -->
					<div class="c2eu-resume-card__identity">
						<h3 class="c2eu-resume-card__name">
							<a href="<?php echo esc_url( $permalink ); ?>" class="c2eu-resume-card__name-link">
								<?php echo esc_html( $candidate_name ); ?>
							</a>
						</h3>

						<?php if ( ! empty( $candidate_title ) ) : ?>
							<div class="c2eu-resume-card__occupation">
								<?php echo esc_html( $candidate_title ); ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $location ) ) : ?>
							<div class="c2eu-resume-card__location">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
								<span><?php echo esc_html( $location ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Categories -->
				<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
					<div class="c2eu-resume-card__categories">
						<?php foreach ( $categories as $cat ) : ?>
							<span class="c2eu-badge c2eu-badge--category"><?php echo esc_html( $cat->name ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<!-- Skills -->
				<?php if ( ! empty( $capped_skills ) ) : ?>
					<div class="c2eu-resume-card__skills">
						<?php foreach ( $capped_skills as $sk ) : ?>
							<span class="c2eu-badge c2eu-badge--skill"><?php echo esc_html( $sk->name ); ?></span>
						<?php endforeach; ?>
						<?php if ( $extra_skills > 0 ) : ?>
							<span class="c2eu-badge c2eu-badge--more">+<?php echo esc_html( $extra_skills ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<!-- Summary -->
				<?php if ( ! empty( $summary ) ) : ?>
					<p class="c2eu-resume-card__summary">
						<?php echo esc_html( $summary ); ?>
					</p>
				<?php endif; ?>

				<!-- Footer CTA -->
				<div class="c2eu-resume-card__footer">
					<a href="<?php echo esc_url( $permalink ); ?>" class="c2eu-btn c2eu-btn--view-profile">
						<?php esc_html_e( 'View Profile', 'click2eu-membership' ); ?> &rarr;
					</a>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Renders pagination with preserved filters.
	 *
	 * @param WP_Query $query Query object.
	 * @param array    $filters Active filters.
	 */
	private function render_pagination( $query, $filters ) {
		$max_num_pages = (int) $query->max_num_pages;
		if ( $max_num_pages <= 1 ) {
			return;
		}

		$current_page = $filters['paged'];

		$add_args = array();
		if ( ! empty( $filters['keyword'] ) ) {
			$add_args['c2eu_resume_keyword'] = $filters['keyword'];
		}
		if ( ! empty( $filters['location'] ) ) {
			$add_args['c2eu_resume_location'] = $filters['location'];
		}
		if ( ! empty( $filters['category'] ) ) {
			$add_args['c2eu_resume_category'] = $filters['category'];
		}
		if ( ! empty( $filters['skill'] ) ) {
			$add_args['c2eu_resume_skill'] = $filters['skill'];
		}

		$base = add_query_arg( 'c2eu_paged', '%#%', get_permalink() );

		$links = paginate_links( array(
			'base'      => $base,
			'format'    => '',
			'current'   => $current_page,
			'total'     => $max_num_pages,
			'add_args'  => $add_args,
			'prev_text' => '&larr; ' . __( 'Previous', 'click2eu-membership' ),
			'next_text' => __( 'Next', 'click2eu-membership' ) . ' &rarr;',
			'type'      => 'list',
		) );

		if ( $links ) {
			echo '<nav class="c2eu-resume-pagination" aria-label="' . esc_attr__( 'Resume Directory Pagination', 'click2eu-membership' ) . '">';
			echo $links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
			echo '</nav>';
		}
	}
}
