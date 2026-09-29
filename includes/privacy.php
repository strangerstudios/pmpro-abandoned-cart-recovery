<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 
 * Add suggested Privacy Policy language for Paid Memberships Pro - Abandoned Cart Recovery.
 *
 * @since 0.1
 */
function pmproacr_add_privacy_policy_content() {
	// Check for support.
	if ( ! function_exists( 'wp_add_privacy_policy_content') ) {
		return;
	}

	$content = '';
	$content .= '<h2>' . __( 'Abandoned Cart Recovery', 'pmpro-abandoned-cart-recovery' ) . '</h2>';
	$content .= '<p>' . __( "When a user begins membership checkout but does not complete the purchase within a specified time, the cart is considered abandoned.", 'pmpro-abandoned-cart-recovery' ) . '</p>';
	$content .= '<p>' . __( "An email is sent to the user to complete the purchase via a link provided in the email.", 'pmpro-abandoned-cart-recovery' ) . '</p>';
	$content .= '<p>' . __( "The user can unsubscribe from abandoned cart emails using the unsubscribe link in the emails.", 'pmpro-abandoned-cart-recovery' ) . '</p>';

	wp_add_privacy_policy_content( 'Paid Memberships Pro - Abandoned Cart Recovery', $content );
}
add_action( 'admin_init', 'pmproacr_add_privacy_policy_content' );

/**
 * Show a message on the Membership Checkout page related to our use of the user's data.
 *
 * @since 0.1
 */
function pmproacr_show_privacy_message() {
	// Get the checkout level.
	$checkout_level = pmpro_getLevelAtCheckout();

	// Check if Abandoned Cart Recovery is enabled for this level.
	$enabled = 'yes' === get_pmpro_membership_level_meta( $checkout_level->id, 'pmproacr_enabled_for_level', true );

	// If a Privacy Policy page is assigned and published, set the message.
	$pmproacr_privacy_message = '';
	$privacy_policy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( ! empty( $privacy_policy_page_id ) ) {
		$privacy_policy_page = get_post( $privacy_policy_page_id );
		if ( $privacy_policy_page instanceof WP_Post && $privacy_policy_page->post_status === 'publish' && $enabled ) {
			$pmproacr_privacy_message = '<p>' . sprintf(
				/* translators: %s: Privacy Policy page URL */
				__( 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our <a href="%s" target="_blank">privacy policy</a>.', 'pmpro-abandoned-cart-recovery' ),
				esc_url( get_permalink( $privacy_policy_page_id ) )
			) . '</p>';
		}
	}

	/**
	 * Filter the message shown on the Membership Checkout page related to our use of the user's data.
	 */
	$pmproacr_privacy_message = apply_filters( 'pmproacr_privacy_message', $pmproacr_privacy_message );

	// If the message is empty, return early.
	if ( empty( $pmproacr_privacy_message ) ) {
		return;
	}

	$allowed_html = array (
		'a' => array (
			'class' => array(),
			'href' => array(),
			'target' => array(),
			'title' => array(),
		),
		'p' => array(
			'class' => array(),
		),
		'b' => array(
			'class' => array(),
		),
		'em' => array(
			'class' => array(),
		),
		'br' => array(),
		'strong' => array(),
	);
	echo wp_kses( $pmproacr_privacy_message, $allowed_html );
}
add_action( 'pmpro_checkout_before_submit_button', 'pmproacr_show_privacy_message' );
