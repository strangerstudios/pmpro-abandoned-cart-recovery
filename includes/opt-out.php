<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the key used to verify an opt-out link for a user.
 *
 * @since 1.0.4
 *
 * @param WP_User $user The user to get the opt-out key for.
 * @return string The opt-out key.
 */
function pmproacr_get_opt_out_key( $user ) {
	return hash_hmac( 'sha256', $user->ID . '|' . $user->user_email, wp_salt( 'auth' ) );
}

/**
 * Get the URL that a user can visit to opt out of abandoned cart emails.
 *
 * @since 1.0.4
 *
 * @param WP_User $user The user to get the opt-out URL for.
 * @return string The opt-out URL.
 */
function pmproacr_get_opt_out_url( $user ) {
	return add_query_arg(
		array(
			'pmproacr_opt_out'     => urlencode( $user->user_email ),
			'pmproacr_opt_out_key' => pmproacr_get_opt_out_key( $user ),
		),
		home_url()
	);
}

/**
 * Process opt-out requests.
 *
 * @since 0.1
 */
function pmproacr_process_opt_out() {
	global $wpdb;

	if ( ! isset( $_REQUEST['pmproacr_opt_out'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opt-out links are verified with the signed opt-out key below.
		return;
	}

	// $_REQUEST['pmproacr_opt_out'] is the email address to opt out.
	// $_REQUEST['pmproacr_opt_out_key'] verifies that the link came from an email sent to that user.
	$email = is_string( $_REQUEST['pmproacr_opt_out'] ) ? sanitize_email( wp_unslash( $_REQUEST['pmproacr_opt_out'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opt-out links are verified with the signed opt-out key below.
	$key   = ( isset( $_REQUEST['pmproacr_opt_out_key'] ) && is_string( $_REQUEST['pmproacr_opt_out_key'] ) ) ? sanitize_text_field( wp_unslash( $_REQUEST['pmproacr_opt_out_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opt-out links are verified with the signed opt-out key below.
	$user  = empty( $email ) ? false : get_user_by( 'email', $email );
	if ( ! $user || empty( $key ) || ! hash_equals( pmproacr_get_opt_out_key( $user ), $key ) ) {
		// Show a banner that the opt-out has failed.
		add_action( 'wp_footer', 'pmproacr_show_opt_out_failed_banner' );
		return;
	}

	// Update the user meta to opt out.
	update_user_meta( $user->ID, 'pmproacr_opt_out', 11 );

	// Mark all in-progress recovery attempts as lost.
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->pmproacr_recovery_attempts,
		array( 'status' => 'lost' ),
		array( 'user_id' => $user->ID, 'status' => 'in_progress' )
	);

	// Show a banner confirming the opt-out request.
	add_action( 'wp_footer', 'pmproacr_show_opt_out_banner', 11 );
}
add_action( 'wp', 'pmproacr_process_opt_out' );

/**
 * Show a banner confirming the opt-out request.
 *
 * @since 0.1
 */
function pmproacr_show_opt_out_banner() {
	// $_REQUEST['pmproacr_opt_out'] is the email address to opt out.
	$email = sanitize_email( wp_unslash( $_REQUEST['pmproacr_opt_out'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Display only; this callback is only hooked after pmproacr_process_opt_out() confirmed the value is set.

	// Show the banner.
	?>
	<div class="pmproacr-opt-out-banner pmproacr-opt-out-banner-success">
		<p><?php echo esc_html( sprintf(
			/* translators: %s is the email address */
			__( 'You have successfully opted out of abandoned cart recovery emails for the email address %s.', 'pmpro-abandoned-cart-recovery' ),
			$email
		) ); ?></p>
	</div>
	<?php
}

/**
 * Show a banner that the opt-out has failed.
 *
 * @since 0.1
 */
function pmproacr_show_opt_out_failed_banner() {
	// Show the banner. Don't show the email address so that this banner can't be used to check if an email address is registered.
	?>
	<div class="pmproacr-opt-out-banner pmproacr-opt-out-banner-failed">
		<p><?php esc_html_e( 'There was an error processing your opt-out request. This opt-out link is not valid.', 'pmpro-abandoned-cart-recovery' ); ?></p>
	</div>
	<?php
}