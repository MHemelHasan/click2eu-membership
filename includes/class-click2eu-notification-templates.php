<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Safe Candidate and administrator transactional email rendering. */
final class Click2EU_Notification_Templates {
	public static function event_title( $event_type ) {
		$titles = array(
			'payment_pending'              => __( 'Membership payment pending', 'click2eu-membership' ),
			'membership_activated'         => __( 'Membership activated', 'click2eu-membership' ),
			'renewal_scheduled'            => __( 'Membership renewal scheduled', 'click2eu-membership' ),
			'renewal_started'              => __( 'Membership renewal started', 'click2eu-membership' ),
			'upgrade_completed'            => __( 'Membership upgrade completed', 'click2eu-membership' ),
			'membership_expiring_7'        => __( 'Membership expires in 7 days', 'click2eu-membership' ),
			'membership_expiring_3'        => __( 'Membership expires in 3 days', 'click2eu-membership' ),
			'membership_expiring_1'        => __( 'Membership expires tomorrow', 'click2eu-membership' ),
			'membership_expired'           => __( 'Membership expired', 'click2eu-membership' ),
			'membership_suspended'         => __( 'Membership suspended', 'click2eu-membership' ),
			'membership_cancelled'         => __( 'Membership cancelled', 'click2eu-membership' ),
			'activation_conflict'          => __( 'Membership activation conflict', 'click2eu-membership' ),
			'upgrade_review_required'      => __( 'Membership upgrade requires review', 'click2eu-membership' ),
			'invalid_payment_lineage'      => __( 'Invalid membership payment lineage', 'click2eu-membership' ),
			'notification_delivery_failure'=> __( 'Membership notification delivery failed', 'click2eu-membership' ),
			'notification_delivery_uncertain'=> __( 'Membership notification delivery outcome uncertain', 'click2eu-membership' ),
		);
		return $titles[ sanitize_key( $event_type ) ] ?? __( 'Membership notification', 'click2eu-membership' );
	}

	private static function event_message( $event_type ) {
		$messages = array(
			'payment_pending'               => __( 'We received your membership order. Access will begin only after payment is verified and the membership is activated.', 'click2eu-membership' ),
			'membership_activated'          => __( 'Your Click2EU membership is active. You can now use the Candidate features included with your selected category.', 'click2eu-membership' ),
			'renewal_scheduled'             => __( 'Your renewal payment was approved. The renewal will begin automatically when your current membership ends.', 'click2eu-membership' ),
			'renewal_started'               => __( 'Your approved renewal is now active.', 'click2eu-membership' ),
			'upgrade_completed'             => __( 'Your approved membership upgrade is complete and the upgraded membership is now active.', 'click2eu-membership' ),
			'membership_expiring_7'         => __( 'Your membership is due to expire in 7 days. Review your membership if you wish to continue access.', 'click2eu-membership' ),
			'membership_expiring_3'         => __( 'Your membership is due to expire in 3 days. Review your membership if you wish to continue access.', 'click2eu-membership' ),
			'membership_expiring_1'         => __( 'Your membership is due to expire tomorrow. Review your membership if you wish to continue access.', 'click2eu-membership' ),
			'membership_expired'            => __( 'Your membership has expired. Protected Candidate actions are no longer available until a new membership is approved.', 'click2eu-membership' ),
			'membership_suspended'          => __( 'Your membership has been suspended following a confirmed order reversal. Contact support if you believe this is incorrect.', 'click2eu-membership' ),
			'membership_cancelled'          => __( 'Your pending or scheduled membership has been cancelled following an order cancellation or reversal.', 'click2eu-membership' ),
			'activation_conflict'           => __( 'A completed membership order could not be activated because another active membership exists. Administrator review is required.', 'click2eu-membership' ),
			'upgrade_review_required'       => __( 'A membership upgrade could not be finalized safely and requires administrator review.', 'click2eu-membership' ),
			'invalid_payment_lineage'       => __( 'A membership upgrade chain contains an invalidated payment lineage and requires administrator review.', 'click2eu-membership' ),
			'notification_delivery_failure' => __( 'A membership notification reached its maximum transport attempts without being accepted.', 'click2eu-membership' ),
			'notification_delivery_uncertain' => __( 'A final notification attempt lost its worker lease before the transport outcome was recorded. It was not resent automatically and requires administrator review.', 'click2eu-membership' ),
		);
		return $messages[ sanitize_key( $event_type ) ] ?? __( 'There is an update to a Click2EU membership.', 'click2eu-membership' );
	}

	public static function context( $notification ) {
		$is_admin   = 'admin' === ( Click2EU_Notifications::event_registry()[ $notification->event_type ] ?? '' );
		$membership = 'membership' === $notification->source_type ? Click2EU_Memberships::get_membership( $notification->source_id ) : null;
		$order_id   = $membership ? absint( $membership->order_id ) : ( 'order' === $notification->source_type ? absint( $notification->source_id ) : 0 );
		$order      = $order_id ? wc_get_order( $order_id ) : false;
		return array(
			'title'       => self::event_title( $notification->event_type ),
			'message'     => self::event_message( $notification->event_type ),
			'membership'  => $membership,
			'order'       => $order,
			'member_url'  => $is_admin ? admin_url( 'admin.php?page=' . Click2EU_Admin::PAGE_SLUG ) : ( Click2EU_Config::candidate_dashboard_id() ? get_permalink( Click2EU_Config::candidate_dashboard_id() ) : Click2EU_Config::join_url() ),
			'is_admin'    => $is_admin,
			'order_url'   => $order && function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'view-order', $order->get_id(), wc_get_page_permalink( 'myaccount' ) ) : '',
			'support_url' => esc_url_raw( apply_filters( 'click2eu_notification_support_url', home_url( '/contact/' ) ) ),
		);
	}

	public static function render_html( $notification ) {
		$context    = self::context( $notification );
		$membership = $context['membership'];
		$rows       = '';
		if ( $membership ) {
			$rows .= self::row( __( 'Package', 'click2eu-membership' ), ucfirst( sanitize_text_field( $membership->package_key ) ) );
			$rows .= self::row( __( 'Job category', 'click2eu-membership' ), sanitize_text_field( $membership->category_label ) );
			if ( ! empty( $membership->starts_at ) ) { $rows .= self::row( __( 'Starts', 'click2eu-membership' ), self::date( $membership->starts_at ) ); }
			if ( ! empty( $membership->expires_at ) ) { $rows .= self::row( __( 'Expires', 'click2eu-membership' ), self::date( $membership->expires_at ) ); }
		}
		$buttons = '<a href="' . esc_url( $context['member_url'] ) . '" style="display:inline-block;background:#2f6bff;color:#fff;text-decoration:none;padding:11px 18px;border-radius:5px;margin:4px 8px 4px 0">' . esc_html( $context['is_admin'] ? __( 'Membership administration', 'click2eu-membership' ) : __( 'My Membership', 'click2eu-membership' ) ) . '</a>';
		if ( $context['order_url'] && ! in_array( $notification->event_type, array( 'activation_conflict', 'upgrade_review_required', 'invalid_payment_lineage', 'notification_delivery_failure', 'notification_delivery_uncertain' ), true ) ) {
			$buttons .= '<a href="' . esc_url( $context['order_url'] ) . '" style="display:inline-block;border:1px solid #2f6bff;color:#12306b;text-decoration:none;padding:10px 17px;border-radius:5px;margin:4px 0">' . esc_html__( 'Order details', 'click2eu-membership' ) . '</a>';
		}
		return '<!doctype html><html><body style="margin:0;background:#f5f8fd;color:#1f2937;font-family:Arial,sans-serif"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px"><table role="presentation" width="600" style="max-width:600px;background:#fff;border-collapse:collapse"><tr><td style="background:#12306b;color:#fff;padding:20px 24px;font-size:24px;font-weight:bold">Click2EU</td></tr><tr><td style="padding:28px 24px"><h1 style="color:#12306b;font-size:24px;margin:0 0 16px">' . esc_html( $context['title'] ) . '</h1><p style="line-height:1.6">' . esc_html( $context['message'] ) . '</p>' . ( $rows ? '<table role="presentation" width="100%" style="border-collapse:collapse;margin:20px 0">' . $rows . '</table>' : '' ) . '<p>' . $buttons . '</p><p style="font-size:13px;color:#64748b">' . sprintf( esc_html__( 'Need help? Visit %s.', 'click2eu-membership' ), '<a href="' . esc_url( $context['support_url'] ) . '">' . esc_html__( 'Click2EU Support', 'click2eu-membership' ) . '</a>' ) . '</p></td></tr></table></td></tr></table></body></html>';
	}

	private static function row( $label, $value ) {
		return '<tr><th align="left" style="padding:8px;border-bottom:1px solid #e5e7eb;color:#12306b">' . esc_html( $label ) . '</th><td style="padding:8px;border-bottom:1px solid #e5e7eb">' . esc_html( $value ) . '</td></tr>';
	}

	public static function render_plain( $notification ) {
		$context = self::context( $notification );
		$lines   = array( 'Click2EU', $context['title'], '', $context['message'] );
		if ( $context['membership'] ) {
			$membership = $context['membership'];
			$lines[] = '';
			$lines[] = __( 'Package:', 'click2eu-membership' ) . ' ' . ucfirst( sanitize_text_field( $membership->package_key ) );
			$lines[] = __( 'Job category:', 'click2eu-membership' ) . ' ' . sanitize_text_field( $membership->category_label );
			if ( ! empty( $membership->expires_at ) ) { $lines[] = __( 'Expires:', 'click2eu-membership' ) . ' ' . self::date( $membership->expires_at ); }
		}
		$lines[] = '';
		$lines[] = ( $context['is_admin'] ? __( 'Membership administration:', 'click2eu-membership' ) : __( 'My Membership:', 'click2eu-membership' ) ) . ' ' . esc_url_raw( $context['member_url'] );
		if ( $context['order_url'] ) { $lines[] = __( 'Order details:', 'click2eu-membership' ) . ' ' . esc_url_raw( $context['order_url'] ); }
		return implode( "\n", $lines );
	}

	private static function date( $utc ) {
		return get_date_from_gmt( $utc, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}
}
