<?php
/**
 * Resume Checkout Bar — front-end render + asset enqueue.
 *
 * @since TBD
 * @package pmpro-abandoned-cart-recovery
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the resume URL with level, discount, and payment plan params.
 *
 * @since TBD
 * @param array $cart Saved cart payload.
 * @return string
 */
function pmproacr_resume_build_url( $cart ) {
	if ( empty( $cart['level_id'] ) || ! function_exists( 'pmpro_url' ) ) {
		return '';
	}

	$args = array( 'pmpro_level' => (int) $cart['level_id'] );
	if ( ! empty( $cart['discount_code'] ) ) {
		$args['pmpro_discount_code'] = $cart['discount_code'];
	}
	if ( ! empty( $cart['payment_plan'] ) ) {
		$args['pmpropp_chosen_plan'] = $cart['payment_plan'];
	}

	return add_query_arg( $args, pmpro_url( 'checkout' ) );
}

/**
 * Should the Resume Checkout Bar render on the current request?
 *
 * @since TBD
 * @return bool
 */
function pmproacr_resume_should_render() {
	if ( is_admin() || wp_doing_ajax() ) {
		return false;
	}

	$settings = pmproacr_resume_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return false;
	}

	if ( ! function_exists( 'pmpro_is_checkout' ) ) {
		return false;
	}

	// Don't show on the checkout page itself.
	if ( pmpro_is_checkout() ) {
		return false;
	}

	$cart = pmproacr_resume_get_saved_cart();
	if ( empty( $cart['level_id'] ) ) {
		return false;
	}

	/**
	 * Filter whether the Resume Checkout Bar should render on the current request.
	 *
	 * @since TBD
	 *
	 * @param bool  $should Whether the bar should render.
	 * @param array $cart   The saved cart payload.
	 */
	return (bool) apply_filters( 'pmproacr_resume_should_render', true, $cart );
}

/**
 * Enqueue the Resume Checkout Bar assets when the bar should render.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_enqueue_assets() {
	if ( ! pmproacr_resume_should_render() ) {
		return;
	}

	$cart     = pmproacr_resume_get_saved_cart();
	$settings = pmproacr_resume_get_settings();
	$base_url = plugins_url( '', PMPROACR_BASE_FILE );

	wp_enqueue_style(
		'pmproacr-resume-bar',
		$base_url . '/css/resume-bar.css',
		array(),
		PMPROACR_VERSION
	);

	wp_enqueue_script(
		'pmproacr-resume-bar',
		$base_url . '/js/resume-bar.js',
		array(),
		PMPROACR_VERSION,
		true
	);

	wp_localize_script(
		'pmproacr-resume-bar',
		'pmproacrResume',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'pmproacr_resume' ),
			'resumeUrl'       => pmproacr_resume_build_url( $cart ),
			'cartToken'       => isset( $cart['token'] ) ? $cart['token'] : '',
			'popupHeadline'   => $settings['popup_headline'],
			'popupBody'       => $settings['popup_body'],
			'barText'         => $settings['bar_text'],
			'barButtonLabel'  => $settings['bar_button_label'],
			'gotItLabel'      => __( 'Got it', 'pmpro-abandoned-cart-recovery' ),
			'dismissLabel'    => __( 'Dismiss', 'pmpro-abandoned-cart-recovery' ),
			'confirmHeadline' => __( 'Hide the Resume Checkout bar?', 'pmpro-abandoned-cart-recovery' ),
			'confirmBody'     => sprintf(
				/* translators: %s: URL to the Membership Levels page. */
				__( 'You can always pick up where you left off from our <a href="%s">Membership Levels</a> page.', 'pmpro-abandoned-cart-recovery' ),
				esc_url( function_exists( 'pmpro_url' ) ? pmpro_url( 'levels' ) : home_url( '/levels/' ) )
			),
			'confirmCancel'   => __( 'Cancel', 'pmpro-abandoned-cart-recovery' ),
			'confirmConfirm'  => __( 'Hide', 'pmpro-abandoned-cart-recovery' ),
			'closeLabel'      => __( 'Close', 'pmpro-abandoned-cart-recovery' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pmproacr_resume_enqueue_assets' );

/**
 * Render the Resume Checkout Bar markup into the page footer.
 *
 * Markup starts hidden; the JS reveals popup or bar based on sessionStorage state.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_render_markup() {
	if ( ! pmproacr_resume_should_render() ) {
		return;
	}
	?>
	<div id="pmproacr-resume-root" aria-live="polite"></div>
	<?php
}
add_action( 'wp_footer', 'pmproacr_resume_render_markup' );
