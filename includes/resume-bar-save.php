<?php
/**
 * Resume Checkout Bar — capture cart info on checkout page load, hydrate for logged-in users,
 * and clear the saved cart on a successful checkout.
 *
 * Cart payload is stored in:
 *   - Cookie  : pmproacr_resume_cart (JSON, TTL from settings)
 *   - User meta: pmproacr_resume_cart (logged-in users, for cross-device persistence)
 *
 * @since TBD
 * @package pmpro-abandoned-cart-recovery
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PMPROACR_RESUME_COOKIE' ) ) {
	define( 'PMPROACR_RESUME_COOKIE', 'pmproacr_resume_cart' );
}
if ( ! defined( 'PMPROACR_RESUME_DISMISS_COOKIE' ) ) {
	define( 'PMPROACR_RESUME_DISMISS_COOKIE', 'pmproacr_resume_dismissed' );
}

/**
 * Read the saved cart payload for the current visitor.
 *
 * Prefers user meta for logged-in users (so the cart survives device/cookie loss), then
 * falls back to the cookie. Returns an empty array when there is no saved cart.
 *
 * @since TBD
 * @return array
 */
function pmproacr_resume_get_saved_cart() {
	$cart = array();

	if ( is_user_logged_in() ) {
		$meta = get_user_meta( get_current_user_id(), 'pmproacr_resume_cart', true );
		if ( is_array( $meta ) && ! empty( $meta['level_id'] ) ) {
			$cart = $meta;
		}
	}

	if ( empty( $cart ) && ! empty( $_COOKIE[ PMPROACR_RESUME_COOKIE ] ) ) {
		$decoded = json_decode( wp_unslash( $_COOKIE[ PMPROACR_RESUME_COOKIE ] ), true );
		if ( is_array( $decoded ) && ! empty( $decoded['level_id'] ) ) {
			$cart = $decoded;
		}
	}

	if ( empty( $cart ) ) {
		return array();
	}

	// Check TTL — drop the cart if it has aged out beyond the configured window.
	$settings = pmproacr_resume_get_settings();
	$ttl      = max( 1, (int) $settings['cart_ttl_days'] ) * DAY_IN_SECONDS;
	if ( empty( $cart['saved_at'] ) || ( time() - (int) $cart['saved_at'] ) > $ttl ) {
		pmproacr_resume_clear_cart();
		return array();
	}

	return $cart;
}

/**
 * Persist a cart payload to the cookie + user meta.
 *
 * @since TBD
 * @param array $cart Cart payload (level_id, discount_code, payment_plan, token, saved_at).
 * @return void
 */
function pmproacr_resume_set_cart( $cart ) {
	if ( empty( $cart['level_id'] ) ) {
		return;
	}

	$settings = pmproacr_resume_get_settings();
	$ttl      = max( 1, (int) $settings['cart_ttl_days'] ) * DAY_IN_SECONDS;
	$expires  = time() + $ttl;

	$payload = array(
		'token'         => ! empty( $cart['token'] ) ? sanitize_text_field( $cart['token'] ) : wp_generate_uuid4(),
		'level_id'      => (int) $cart['level_id'],
		'discount_code' => isset( $cart['discount_code'] ) ? preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $cart['discount_code'] ) : '',
		'payment_plan'  => isset( $cart['payment_plan'] ) ? sanitize_text_field( (string) $cart['payment_plan'] ) : '',
		'saved_at'      => time(),
	);

	if ( ! headers_sent() ) {
		setcookie(
			PMPROACR_RESUME_COOKIE,
			wp_json_encode( $payload ),
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	$_COOKIE[ PMPROACR_RESUME_COOKIE ] = wp_json_encode( $payload );

	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), 'pmproacr_resume_cart', $payload );
	}
}

/**
 * Clear the saved cart from both cookie and user meta.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_clear_cart() {
	if ( ! headers_sent() ) {
		setcookie(
			PMPROACR_RESUME_COOKIE,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}
	unset( $_COOKIE[ PMPROACR_RESUME_COOKIE ] );

	if ( is_user_logged_in() ) {
		delete_user_meta( get_current_user_id(), 'pmproacr_resume_cart' );
	}
}

/**
 * Capture the current checkout cart when a visitor lands on the checkout page.
 *
 * Runs late in wp so $pmpro_level is populated. Reads the same request params PMPro core
 * already accepts (pmpro_level, pmpro_discount_code, pmpropp_chosen_plan).
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_capture_checkout_cart() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$settings = pmproacr_resume_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	if ( ! function_exists( 'pmpro_is_checkout' ) || ! pmpro_is_checkout() ) {
		return;
	}

	global $pmpro_level;

	$level_id = 0;
	if ( ! empty( $pmpro_level->id ) ) {
		$level_id = (int) $pmpro_level->id;
	} elseif ( ! empty( $_REQUEST['pmpro_level'] ) ) {
		$level_id = (int) $_REQUEST['pmpro_level'];
	} elseif ( ! empty( $_REQUEST['level'] ) ) {
		$level_id = (int) $_REQUEST['level'];
	}

	if ( empty( $level_id ) ) {
		return;
	}

	$discount_code = '';
	if ( ! empty( $_REQUEST['pmpro_discount_code'] ) ) {
		$discount_code = preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['pmpro_discount_code'] ) ) );
	} elseif ( ! empty( $_REQUEST['discount_code'] ) ) {
		$discount_code = preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_REQUEST['discount_code'] ) ) );
	}

	$payment_plan = '';
	if ( ! empty( $_REQUEST['pmpropp_chosen_plan'] ) ) {
		$payment_plan = sanitize_text_field( wp_unslash( $_REQUEST['pmpropp_chosen_plan'] ) );
	}

	// Preserve the existing token if we already have a saved cart, so dismissal scoping is stable across page loads.
	$existing = pmproacr_resume_get_saved_cart();
	$token    = ! empty( $existing['token'] ) && ! empty( $existing['level_id'] ) && (int) $existing['level_id'] === $level_id
		? $existing['token']
		: wp_generate_uuid4();

	pmproacr_resume_set_cart( array(
		'token'         => $token,
		'level_id'      => $level_id,
		'discount_code' => $discount_code,
		'payment_plan'  => $payment_plan,
	) );
}
add_action( 'wp', 'pmproacr_resume_capture_checkout_cart', 99 );

/**
 * AJAX endpoint for the front-end JS to update the saved cart when discount code or
 * payment plan changes after page load.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_ajax_update_cart() {
	check_ajax_referer( 'pmproacr_resume', 'nonce' );

	$settings = pmproacr_resume_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		wp_send_json_error( 'disabled' );
	}

	$level_id = isset( $_POST['level_id'] ) ? (int) $_POST['level_id'] : 0;
	if ( empty( $level_id ) ) {
		wp_send_json_error( 'no_level' );
	}

	$discount_code = isset( $_POST['discount_code'] ) ? preg_replace( '/[^A-Za-z0-9\-]/', '', sanitize_text_field( wp_unslash( $_POST['discount_code'] ) ) ) : '';
	$payment_plan  = isset( $_POST['payment_plan'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_plan'] ) ) : '';

	$existing = pmproacr_resume_get_saved_cart();
	$token    = ! empty( $existing['token'] ) && (int) $existing['level_id'] === $level_id ? $existing['token'] : wp_generate_uuid4();

	pmproacr_resume_set_cart( array(
		'token'         => $token,
		'level_id'      => $level_id,
		'discount_code' => $discount_code,
		'payment_plan'  => $payment_plan,
	) );

	wp_send_json_success();
}
add_action( 'wp_ajax_pmproacr_resume_update_cart', 'pmproacr_resume_ajax_update_cart' );
add_action( 'wp_ajax_nopriv_pmproacr_resume_update_cart', 'pmproacr_resume_ajax_update_cart' );

/**
 * On successful checkout, clear the saved cart so the bar doesn't nag a brand-new member.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_clear_on_checkout() {
	pmproacr_resume_clear_cart();
}
add_action( 'pmpro_after_checkout', 'pmproacr_resume_clear_on_checkout', 20 );

/**
 * When a logged-in visitor lands on the site, hydrate the cookie from their user meta
 * (handles the cross-device case where they have a saved cart but no cookie locally).
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_hydrate_cookie_for_user() {
	if ( ! is_user_logged_in() || is_admin() || wp_doing_ajax() ) {
		return;
	}

	if ( ! empty( $_COOKIE[ PMPROACR_RESUME_COOKIE ] ) ) {
		return;
	}

	$settings = pmproacr_resume_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	$meta = get_user_meta( get_current_user_id(), 'pmproacr_resume_cart', true );
	if ( ! is_array( $meta ) || empty( $meta['level_id'] ) ) {
		return;
	}

	$ttl = max( 1, (int) $settings['cart_ttl_days'] ) * DAY_IN_SECONDS;
	if ( empty( $meta['saved_at'] ) || ( time() - (int) $meta['saved_at'] ) > $ttl ) {
		delete_user_meta( get_current_user_id(), 'pmproacr_resume_cart' );
		return;
	}

	pmproacr_resume_set_cart( $meta );
}
add_action( 'wp', 'pmproacr_resume_hydrate_cookie_for_user', 5 );
