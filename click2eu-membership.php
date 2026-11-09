<?php
/**
 * Plugin Name: Click2EU Membership
 * Description: Candidate membership packages, manual-payment activation, access control and WooCommerce integration for Click2EU.
 * Version: 0.7.1
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Author: HemelHasan
 * License: GPL-2.0-or-later
 * Text Domain: click2eu-membership
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-click2eu-config.php';
require_once __DIR__ . '/includes/class-click2eu-auth.php';
require_once __DIR__ . '/includes/class-click2eu-time.php';
require_once __DIR__ . '/includes/class-click2eu-memberships.php';
require_once __DIR__ . '/includes/class-click2eu-expiration.php';
require_once __DIR__ . '/includes/class-click2eu-orders.php';
require_once __DIR__ . '/includes/class-click2eu-upgrades.php';
require_once __DIR__ . '/includes/class-click2eu-category-access.php';
require_once __DIR__ . '/includes/class-click2eu-notification-templates.php';
require_once __DIR__ . '/includes/class-click2eu-notifications.php';
require_once __DIR__ . '/includes/class-click2eu-access.php';
require_once __DIR__ . '/includes/class-click2eu-cariera.php';
require_once __DIR__ . '/includes/class-click2eu-status.php';
require_once __DIR__ . '/includes/class-click2eu-dashboard.php';
require_once __DIR__ . '/includes/class-click2eu-admin.php';

register_activation_hook( __FILE__, array( 'Click2EU_Memberships', 'install' ) );
register_activation_hook( __FILE__, array( 'Click2EU_Dashboard', 'activate' ) );
register_activation_hook( __FILE__, array( 'Click2EU_Expiration', 'activate' ) );
register_activation_hook( __FILE__, array( 'Click2EU_Notifications', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Click2EU_Expiration', 'deactivate' ) );
register_deactivation_hook( __FILE__, array( 'Click2EU_Upgrades', 'deactivate' ) );
register_deactivation_hook( __FILE__, array( 'Click2EU_Notifications', 'deactivate' ) );

/**
 * Uses WooCommerce CRUD for order and order-item metadata (HPOS-friendly).
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

final class Click2EU_Membership_Phase_One {
    private const VERSION = '0.7.1';

    /** Phase 1: existing, separately-created WooCommerce simple products. */
    private const PRODUCTS = array(
        'basic'   => 11868,
        'pro'     => 11869,
        'premium' => 11870,
        'elite'   => 11871,
    );

    /** Feature copy from the user's reference HTML. Product short descriptions override this. */
    private const DEFAULT_FEATURES = array(
        'basic' => array(
            'Profile visible to approved employers',
            'One trade category',
            'Support by chat and email',
        ),
        'pro' => array(
            'Everything in Basic',
            'See how many employers viewed you',
            'Faster support',
        ),
        'premium' => array(
            'Everything in Pro',
            'Video introduction on your profile',
            'Shown higher in employer searches',
        ),
        'elite' => array(
            'Everything in Premium',
            'A full year of visibility',
            'Priority support',
        ),
    );

    private $form_count = 0;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_shortcode( 'click2eu_packages', array( $this, 'packages_shortcode' ) );
        add_shortcode( 'click2eu_packages_section', array( $this, 'packages_section_shortcode' ) );
        add_shortcode( 'click2eu_package', array( $this, 'package_shortcode' ) );
        add_shortcode( 'click2eu_join', array( $this, 'join_shortcode' ) );

        // The form POSTs to its current frontend page. No cart/product IDs in checkout URLs.
        add_action( 'template_redirect', array( $this, 'process_join' ), 5 );

        // This filter is used by both classic checkout and the Checkout Block's Store API.
        add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );

        // Classic checkout: write order item metadata before the item is saved.
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'classic_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'classic_order_meta' ), 10, 2 );

        // Checkout Block / Store API: the classic checkout order hooks do not necessarily fire.
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'block_order_meta' ), 10, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'block_order_meta' ), 10, 1 );
    }

    private function product_map() {
        /** Developers may override product IDs without editing this file. */
        $products = apply_filters( 'click2eu_membership_products', self::PRODUCTS );
        return is_array( $products ) ? $products : self::PRODUCTS;
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'click2eu-jakarta-font',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            array(),
            null
        );
        wp_enqueue_style(
            'click2eu-membership',
            plugin_dir_url( __FILE__ ) . 'assets/membership.css',
            array( 'click2eu-jakarta-font' ),
            self::VERSION
        );
    }

    private function categories() {
        if ( ! taxonomy_exists( 'job_listing_category' ) ) {
            return new WP_Error( 'missing_taxonomy', 'Job categories are unavailable. Enable Job Categories in WP Job Manager.' );
        }

        return get_terms( array(
            'taxonomy'   => 'job_listing_category',
            'hide_empty' => false, // Include categories even if no jobs have been posted yet.
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );
    }

    /**
     * [click2eu_packages] renders ONLY the four cards.
     * [click2eu_packages section="yes"] is also supported for convenience.
     */
    public function packages_shortcode( $atts ) {
        $atts = $this->appearance_atts( $atts, 'click2eu_packages' );
        if ( 'yes' === strtolower( $atts['section'] ) ) {
            return $this->render_section( $atts );
        }
        return '<div class="c2eu-membership c2eu-cards-only">' . $this->render_grid( $atts ) . '</div>';
    }

    /** Entire reference HTML section: eyebrow, heading, intro, cards and disclaimer. */
    public function packages_section_shortcode( $atts ) {
        $atts = $this->appearance_atts( $atts, 'click2eu_packages_section' );
        return $this->render_section( $atts );
    }

    /** [click2eu_package package="basic"] renders a complete single card. */
    public function package_shortcode( $atts ) {
        $atts = $this->appearance_atts( $atts, 'click2eu_package' );
        $tier = sanitize_key( $atts['package'] );
        if ( ! isset( $this->product_map()[ $tier ] ) ) {
            return '<div class="c2eu-notice">Invalid package tier.</div>';
        }
        $categories = $this->get_ready_categories();
        if ( is_wp_error( $categories ) ) {
            return '<div class="c2eu-notice">' . esc_html( $categories->get_error_message() ) . '</div>';
        }
        return '<div class="c2eu-membership c2eu-single-card">' . $this->render_card( $tier, $this->product_map()[ $tier ], $categories, $atts ) . '</div>';
    }

    private function appearance_atts( $atts, $shortcode ) {
        return shortcode_atts( array(
            'package'        => 'basic',
            'show_duration'  => 'yes',  // DISPLAY ONLY; not membership entitlement.
            'show_day_rate'  => 'yes',
            'section'        => 'no',
            'id'             => 'plans',
            'eyebrow'        => 'PLANS',
            'title'          => 'Pick how long you want to be visible',
            'subtitle'       => 'One payment, no automatic renewal. Pay by bank transfer.',
            'disclaimer'     => 'We do not guarantee a job. Plans give you visibility to employers.',
        ), $atts, $shortcode );
    }

    private function get_ready_categories() {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return new WP_Error( 'missing_wc', 'WooCommerce must be active to show packages.' );
        }
        $categories = $this->categories();
        if ( is_wp_error( $categories ) ) {
            return $categories;
        }
        if ( empty( $categories ) ) {
            return new WP_Error( 'missing_categories', 'No job categories found. Please add categories in WP Job Manager first.' );
        }
        return $categories;
    }

    private function render_section( $atts ) {
        $id = sanitize_html_class( $atts['id'] );
        $html = '<section' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' class="c2eu-membership c2eu-plans-section">';
        $html .= '<div class="c2eu-plans-container">';
        $html .= '<header class="c2eu-plans-header">';
        if ( '' !== trim( $atts['eyebrow'] ) ) {
            $html .= '<p class="c2eu-plans-eyebrow">' . esc_html( $atts['eyebrow'] ) . '</p>';
        }
        if ( '' !== trim( $atts['title'] ) ) {
            $html .= '<h2 class="c2eu-plans-title">' . esc_html( $atts['title'] ) . '</h2>';
        }
        if ( '' !== trim( $atts['subtitle'] ) ) {
            $html .= '<p class="c2eu-plans-subtitle">' . esc_html( $atts['subtitle'] ) . '</p>';
        }
        $html .= '</header>';
        $html .= $this->render_grid( $atts );
        if ( '' !== trim( $atts['disclaimer'] ) ) {
            $html .= '<p class="c2eu-plans-disclaimer">' . esc_html( $atts['disclaimer'] ) . '</p>';
        }
        $html .= '</div></section>';
        return $html;
    }

    private function render_grid( $atts ) {
        $categories = $this->get_ready_categories();
        if ( is_wp_error( $categories ) ) {
            return '<div class="c2eu-notice">' . esc_html( $categories->get_error_message() ) . '</div>';
        }
        $html = '<div class="c2eu-package-grid">';
        foreach ( self::PRODUCTS as $tier => $default_product_id ) {
            $map = $this->product_map();
            $html .= $this->render_card( $tier, isset( $map[ $tier ] ) ? $map[ $tier ] : $default_product_id, $categories, $atts );
        }
        return $html . '</div>';
    }

    private function render_card( $tier, $product_id, $categories, $atts ) {
        $product = wc_get_product( absint( $product_id ) );
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            return '<div class="c2eu-notice">The ' . esc_html( ucfirst( $tier ) ) . ' product is unavailable. Check product ID ' . esc_html( $product_id ) . '.</div>';
        }
        $elite = 'elite' === $tier;
        $html = '<article class="c2eu-package-card c2eu-package-card--' . esc_attr( $tier ) . '">';
        if ( $elite ) {
            $html .= '<span class="c2eu-package-card__badge">Best value</span>';
        }
        $html .= '<h3 class="c2eu-package-card__title">' . esc_html( ucfirst( $tier ) ) . '</h3>';
        $display_price = function_exists( 'wc_get_price_to_display' ) ? wc_get_price_to_display( $product ) : $product->get_price();
        $price_args = ( is_numeric( $display_price ) && abs( (float) $display_price - round( (float) $display_price ) ) < 0.00001 ) ? array( 'decimals' => 0 ) : array();
        $html .= '<div class="c2eu-package-card__price">' . wp_kses_post( wc_price( $display_price, $price_args ) ) . '</div>';

        // Display the trusted server-side term used for new membership snapshots.
        $days = Click2EU_Config::duration_for_package( $tier );
        if ( 'yes' === strtolower( $atts['show_duration'] ) && $days ) {
            $html .= '<p class="c2eu-package-card__duration">' . esc_html( $days ) . ' days';
            if ( 'yes' === strtolower( $atts['show_day_rate'] ) && function_exists( 'wc_get_price_to_display' ) && is_numeric( $product->get_price() ) ) {
                $daily = wc_get_price_to_display( $product ) / $days;
                $html .= ' <span aria-hidden="true">·</span> ≈ ' . wp_kses_post( wc_price( $daily, array( 'decimals' => 2 ) ) ) . ' / day';
            }
            $html .= '</p>';
        }

        $html .= $this->features_html( $tier, $product );
        $html .= $this->join_form( $tier, $categories );
        $html .= '</article>';
        return $html;
    }

    private function features_html( $tier, $product ) {
        $description = trim( $product->get_short_description() );
        if ( '' !== $description ) {
            // Existing WooCommerce product short descriptions take priority over default copy.
            return '<div class="c2eu-package-card__features">' . wp_kses_post( wpautop( $description ) ) . '</div>';
        }
        $html = '<div class="c2eu-package-card__features"><ul>';
        foreach ( self::DEFAULT_FEATURES[ $tier ] as $feature ) {
            $html .= '<li>' . esc_html( $feature ) . '</li>';
        }
        return $html . '</ul></div>';
    }

    /**
     * Use within existing Elementor card, e.g. [click2eu_join package="basic"].
     * Renders ONLY the dropdown and Join button; Elementor handles the surrounding card.
     */
    public function join_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'package' => 'basic' ), $atts, 'click2eu_join' );
        $tier = sanitize_key( $atts['package'] );
        if ( ! isset( $this->product_map()[ $tier ] ) ) {
            return '<div class="c2eu-notice">Invalid package tier.</div>';
        }
        if ( ! function_exists( 'wc_get_product' ) ) {
            return '<div class="c2eu-notice">WooCommerce must be active.</div>';
        }
        $product = wc_get_product( absint( $this->product_map()[ $tier ] ) );
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            return '<div class="c2eu-notice">Package product not found.</div>';
        }
        $categories = $this->categories();
        if ( is_wp_error( $categories ) ) {
            return '<div class="c2eu-notice">' . esc_html( $categories->get_error_message() ) . '</div>';
        }
        if ( empty( $categories ) ) {
            return '<div class="c2eu-notice">Please add job categories first.</div>';
        }
        return '<div class="c2eu-membership c2eu-join-standalone">' . $this->join_form( $tier, $categories ) . '</div>';
    }

    private function join_form( $tier, $categories ) {
        $map = $this->product_map();
        if ( ! isset( $map[ $tier ] ) ) {
            return '';
        }

        $this->form_count++;
		$renewal_id = isset( $_GET['renew_membership'] ) ? absint( wp_unslash( $_GET['renew_membership'] ) ) : 0;
		$renewal    = $renewal_id ? Click2EU_Memberships::get_membership( $renewal_id ) : null;
		$is_renewal = $renewal && is_user_logged_in() && (int) $renewal->candidate_user_id === get_current_user_id() && 'active' === $renewal->status && $tier === $renewal->package_key;
		$selected_category = $is_renewal ? absint( $renewal->category_term_id ) : 0;
        $select_id = 'c2eu-category-' . sanitize_html_class( $tier ) . '-' . $this->form_count;
        $html = '<form class="c2eu-join-form" method="post" action="">';
        $html .= '<input type="hidden" name="click2eu_join_submit" value="1">';
        $html .= '<input type="hidden" name="click2eu_package" value="' . esc_attr( $tier ) . '">';
        $html .= wp_nonce_field( 'click2eu_join_' . $tier, 'click2eu_nonce', true, false );
        $html .= '<label class="screen-reader-text" for="' . esc_attr( $select_id ) . '">Select your job category</label>';
        $html .= '<select id="' . esc_attr( $select_id ) . '" name="click2eu_category_id" required aria-required="true">';
        $html .= '<option value="">Select job category *</option>';
        foreach ( $categories as $category ) {
			$html .= '<option value="' . esc_attr( $category->term_id ) . '"' . selected( $selected_category, $category->term_id, false ) . '>' . esc_html( $category->name ) . '</option>';
        }
        $html .= '</select>';

        $error_code = isset( $_GET['c2eu_error'] ) ? sanitize_key( wp_unslash( $_GET['c2eu_error'] ) ) : '';
        $error_tier = isset( $_GET['c2eu_tier'] ) ? sanitize_key( wp_unslash( $_GET['c2eu_tier'] ) ) : '';
        if ( $error_code && $tier === $error_tier ) {
            $errors = array(
                'invalid_category' => 'Please select a valid job category.',
                'invalid_product'  => 'This package is unavailable. Please contact support.',
                'add_to_cart'      => 'Could not start checkout. Please try again.',
                'security'         => 'Your session expired. Please try again.',
                'woocommerce'      => 'The checkout service is currently unavailable.',
                'candidate_only'   => 'Candidate memberships require a Candidate account.',
                'already_active'   => 'Your Candidate membership is already active.',
				'upgrade_unavailable' => 'Mid-term upgrades are unavailable. Renewal must use the same package and job category.',
				'renewal_unavailable' => 'This membership cannot be renewed yet. Please contact support.',
            );
            if ( isset( $errors[ $error_code ] ) ) {
                $html .= '<p class="c2eu-form-error" role="alert">' . esc_html( $errors[ $error_code ] ) . '</p>';
            }
        }
		$html .= '<button type="submit" class="c2eu-join-button">' . esc_html( $is_renewal ? __( 'Renew', 'click2eu-membership' ) : __( 'Join', 'click2eu-membership' ) ) . '</button>';
        $html .= '</form>';
        return $html;
    }

    private function redirect_error( $code, $tier ) {
        $url = wp_get_referer();
        if ( ! $url ) {
            $url = home_url( '/' );
        }
        $url = remove_query_arg( array( 'c2eu_error', 'c2eu_tier' ), $url );
        wp_safe_redirect( add_query_arg( array(
            'c2eu_error' => $code,
            'c2eu_tier'  => $tier,
        ), $url ) );
        exit;
    }

    public function process_join() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['click2eu_join_submit'] ) ) {
            return;
        }

        $tier = isset( $_POST['click2eu_package'] ) ? sanitize_key( wp_unslash( $_POST['click2eu_package'] ) ) : '';
        $map  = $this->product_map();
        if ( ! isset( $map[ $tier ] ) ) {
            $this->redirect_error( 'invalid_product', $tier );
        }

        $nonce = isset( $_POST['click2eu_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['click2eu_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'click2eu_join_' . $tier ) ) {
            $this->redirect_error( 'security', $tier );
        }

        // Candidate accounts register and sign in through the existing Cariera flow.
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( Click2EU_Auth::cariera_auth_url() );
            exit;
        }

        if ( ! Click2EU_Auth::is_candidate() ) {
            $this->redirect_error( 'candidate_only', $tier );
        }

		$user_id = get_current_user_id();
		if ( Click2EU_Memberships::has_pending_membership( $user_id ) || Click2EU_Memberships::has_scheduled_membership( $user_id ) ) {
            wp_safe_redirect( Click2EU_Auth::my_orders_url() );
            exit;
        }

        if ( ! function_exists( 'wc_get_product' ) || ! function_exists( 'WC' ) ) {
            $this->redirect_error( 'woocommerce', $tier );
        }

        $category_id = isset( $_POST['click2eu_category_id'] ) ? absint( wp_unslash( $_POST['click2eu_category_id'] ) ) : 0;
        if ( ! $category_id || ! taxonomy_exists( 'job_listing_category' ) ) {
            $this->redirect_error( 'invalid_category', $tier );
        }
        $category = get_term( $category_id, 'job_listing_category' );
        if ( ! $category || is_wp_error( $category ) || 'job_listing_category' !== $category->taxonomy ) {
            $this->redirect_error( 'invalid_category', $tier );
        }

		$renewal_of = 0;
		if ( Click2EU_Memberships::has_active_membership( $user_id ) ) {
			$active = Click2EU_Memberships::get_membership_for_user( $user_id );
			if ( ! $active || empty( $active->expires_at ) ) {
				$this->redirect_error( 'renewal_unavailable', $tier );
			}
			if ( $active->package_key !== $tier || absint( $active->category_term_id ) !== $category_id ) {
				$this->redirect_error( 'upgrade_unavailable', $tier );
			}
			$renewal_of = absint( $active->membership_id );
		}

        $product_id = absint( $map[ $tier ] );
        $product    = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'simple' ) || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            $this->redirect_error( 'invalid_product', $tier );
        }

        if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }
        if ( ! WC()->cart ) {
            $this->redirect_error( 'woocommerce', $tier );
        }

        $new_cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array(
            'click2eu_package'       => $tier,
            'click2eu_category_id'   => $category_id,
            'click2eu_category_name' => sanitize_text_field( $category->name ),
			'click2eu_renewal_of_membership_id' => $renewal_of,
			'click2eu_renewal_type' => $renewal_of ? 'early' : '',
        ) );
        if ( ! $new_cart_key ) {
            $this->redirect_error( 'add_to_cart', $tier );
        }

        // A candidate package checkout should contain exactly the selected membership item.
        // Only remove other cart items once add_to_cart succeeded, to avoid losing the cart on failure.
        foreach ( array_keys( WC()->cart->get_cart() ) as $cart_key ) {
            if ( $cart_key !== $new_cart_key ) {
                WC()->cart->remove_cart_item( $cart_key );
            }
        }
        WC()->cart->set_quantity( $new_cart_key, 1, false );
        WC()->cart->calculate_totals();
        WC()->cart->set_session();

        // URL is clean: /checkout/, with no product ID or category in its query string.
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    private function readable_description( $product_id ) {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return '';
        }
        $product = wc_get_product( absint( $product_id ) );
        if ( ! $product ) {
            return '';
        }
        $html = $product->get_short_description();
        if ( ! $html ) {
            $tier = array_search( absint( $product_id ), array_map( 'absint', $this->product_map() ), true );
            if ( false !== $tier && isset( self::DEFAULT_FEATURES[ $tier ] ) ) {
                return implode( '; ', self::DEFAULT_FEATURES[ $tier ] );
            }
            return '';
        }
        // WooCommerce short description overrides the source HTML's fallback feature copy.
        $html = preg_replace( '#</(?:li|p)>#i', '; ', $html );
        $plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ), " \t\n\r\0\x0B;" );
        return sanitize_text_field( $plain );
    }

    public function cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['click2eu_category_name'] ) ) {
            return $item_data;
        }
        $category = sanitize_text_field( $cart_item['click2eu_category_name'] );
        $item_data[] = array(
            'key'     => 'Job Category',
            'name'    => 'Job Category',
            'raw_key' => 'click2eu_job_category',
            'value'   => $category,
            'display' => $category,
        );
        $details = $this->readable_description( $cart_item['product_id'] ?? 0 );
        if ( $details ) {
            $item_data[] = array(
                'key'     => 'Package Includes',
                'name'    => 'Package Includes',
                'raw_key' => 'click2eu_package_includes',
                'value'   => $details,
                'display' => $details,
            );
        }
        return $item_data;
    }

    private function add_metadata_to_item( $item, $cart_item ) {
        if ( empty( $cart_item['click2eu_category_name'] ) || ! method_exists( $item, 'update_meta_data' ) ) {
            return;
        }
        $item->update_meta_data( 'Job Category', sanitize_text_field( $cart_item['click2eu_category_name'] ) );
        $item->update_meta_data( '_click2eu_category_id', absint( $cart_item['click2eu_category_id'] ?? 0 ) );
        $item->update_meta_data( '_click2eu_package', sanitize_key( $cart_item['click2eu_package'] ?? '' ) );
        $item->update_meta_data( '_click2eu_duration_days', Click2EU_Config::duration_for_package( $cart_item['click2eu_package'] ?? '' ) );
		$item->update_meta_data( '_click2eu_renewal_of_membership_id', absint( $cart_item['click2eu_renewal_of_membership_id'] ?? 0 ) );
		$item->update_meta_data( '_click2eu_renewal_type', sanitize_key( $cart_item['click2eu_renewal_type'] ?? '' ) );
        $details = $this->readable_description( $cart_item['product_id'] ?? 0 );
        if ( $details ) {
            $item->update_meta_data( 'Package Includes', $details );
        }
    }

    public function classic_order_item_meta( $item, $cart_item_key, $cart_item, $order ) {
        $this->add_metadata_to_item( $item, $cart_item );
    }

    private function add_metadata_to_order( $order ) {
        if ( ! is_object( $order ) || ! method_exists( $order, 'get_items' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        $cart_items = WC()->cart->get_cart();
        $map        = $this->product_map();
        foreach ( $cart_items as $cart_item ) {
            if ( empty( $cart_item['click2eu_category_name'] ) ) {
                continue;
            }
            $product_id = absint( $cart_item['product_id'] ?? 0 );
            if ( ! in_array( $product_id, array_map( 'absint', array_values( $map ) ), true ) ) {
                continue;
            }
            $order->update_meta_data( '_click2eu_package', sanitize_key( $cart_item['click2eu_package'] ?? '' ) );
            $order->update_meta_data( '_click2eu_category_id', absint( $cart_item['click2eu_category_id'] ?? 0 ) );
            $order->update_meta_data( '_click2eu_category_name', sanitize_text_field( $cart_item['click2eu_category_name'] ) );
            $order->update_meta_data( '_click2eu_duration_days', Click2EU_Config::duration_for_package( $cart_item['click2eu_package'] ?? '' ) );
			$order->update_meta_data( '_click2eu_renewal_of_membership_id', absint( $cart_item['click2eu_renewal_of_membership_id'] ?? 0 ) );
			$order->update_meta_data( '_click2eu_renewal_type', sanitize_key( $cart_item['click2eu_renewal_type'] ?? '' ) );

            // Store API / block checkout needs a separate path for order LINE metadata.
            foreach ( $order->get_items( 'line_item' ) as $item ) {
                if ( absint( $item->get_product_id() ) === $product_id ) {
                    $this->add_metadata_to_item( $item, $cart_item );
                    $item->save();
                }
            }
            break; // Exactly one membership item per Phase 1 order.
        }
    }

    public function classic_order_meta( $order, $data ) {
        $this->add_metadata_to_order( $order );
        // Classic checkout will persist $order when WooCommerce saves it.
    }

    public function block_order_meta( $order ) {
        $this->add_metadata_to_order( $order );
        $order->save(); // Block hooks can run when the order already exists.
    }
}

add_action( 'plugins_loaded', array( 'Click2EU_Memberships', 'maybe_install' ), 20 );
add_action( 'plugins_loaded', array( 'Click2EU_Notifications', 'maybe_install' ), 21 );

new Click2EU_Membership_Phase_One();
new Click2EU_Orders();
new Click2EU_Upgrades();
new Click2EU_Category_Access();
new Click2EU_Access();
new Click2EU_Cariera();
new Click2EU_Status();
new Click2EU_Dashboard();
new Click2EU_Expiration();
new Click2EU_Admin();
new Click2EU_Notifications();
