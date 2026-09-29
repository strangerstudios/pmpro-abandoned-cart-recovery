<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set up email templates.
 *
 * @since 1.0
 */
function pmproacr_init_email_templates() {
	if ( class_exists( 'PMPro_Email_Template' ) ) {
		// Using PMPro v3.4+. Include the email template class.
		include_once( PMPROACR_DIR . '/classes/email-templates/class-pmpro-email-template-pmproacr-reminder-1.php' );
		include_once( PMPROACR_DIR . '/classes/email-templates/class-pmpro-email-template-pmproacr-reminder-2.php' );
		include_once( PMPROACR_DIR . '/classes/email-templates/class-pmpro-email-template-pmproacr-reminder-3.php' );
	} else {
		// Using PMPro version under v3.4. Use the old filter.
		add_filter( 'pmproet_templates', 'pmproacr_email_templates' );
	}
}
add_action( 'init', 'pmproacr_init_email_templates', 8 ); // Priority 8 to ensure the pmproet_templates hook is added before PMPro loads email templates.

/**
 * Add the reminder email templates.
 *
 * @since 0.1
 *
 * @param $templates array The email templates.
 * @return array The email templates.
 */
function pmproacr_email_templates( $templates ) {
	// Allowed strings for kses checks below.
	$allowed_html = array(
		'a' => array(
			'href' => array(),
			'title' => array(),
		),
		'p' => array(),
	);

	$templates['pmproacr_reminder_1'] = array(
		'description' => esc_html__( 'Abandoned Cart Recovery - Reminder 1', 'pmpro-abandoned-cart-recovery' ),
		'subject'     => esc_html__( 'Your membership is waiting.', 'pmpro-abandoned-cart-recovery' ),
		'body'        => '<p>' . esc_html__( 'We noticed you started signing up for !!membership_level_name!! membership but did not complete the checkout process.', 'pmpro-abandoned-cart-recovery' ) . '</p>

' . wp_kses( __( '<p><a href="!!checkout_url!!">Click here to complete membership checkout now</a>.</p>', 'pmpro-abandoned-cart-recovery' ), $allowed_html ) . '

<p>' . wp_kses( __( 'If you do not want to receive any more emails about this attempted checkout, <a href="!!opt_out_url!!">click here to opt out of future emails</a>.', 'pmpro-abandoned-cart-recovery' ), $allowed_html ) . '</p>',
		'help_text'   => esc_html__( 'This email is sent as the first reminder to complete a purchase.', 'pmpro-abandoned-cart-recovery' )
	);

	$templates['pmproacr_reminder_2'] = array(
		'description' => esc_html__( 'Abandoned Cart Recovery - Reminder 2', 'pmpro-abandoned-cart-recovery' ),
		'subject'     => esc_html__( 'Reminder: Your !!sitename!! membership is waiting.', 'pmpro-abandoned-cart-recovery' ),
		'body'        => '<p>' . esc_html__( 'It looks like you may have forgotten to complete checkout for the !!membership_level_name!! membership at !!sitename!!.', 'pmpro-abandoned-cart-recovery' ) . '</p>

<p><a href="!!checkout_url!!">' . esc_html__( 'Complete Your Purchase Now', 'pmpro-abandoned-cart-recovery' ) . '</a></p>

<p>' . wp_kses( __( 'If you do not want to receive any more emails about this attempted checkout, <a href="!!opt_out_url!!">click here to opt out of these emails</a>.', 'pmpro-abandoned-cart-recovery' ), $allowed_html ) . '</p>',
		'help_text'   => esc_html__( 'This email is sent as the second reminder to complete a purchase.', 'pmpro-abandoned-cart-recovery' )
	);

	$templates['pmproacr_reminder_3'] = array(
		'description' => esc_html__( 'Abandoned Cart Recovery - Reminder 3', 'pmpro-abandoned-cart-recovery' ),
		'subject'     => esc_html__( 'Final Reminder: Complete membership checkout at !!sitename!! today.', 'pmpro-abandoned-cart-recovery' ),
		'body'        => '<p>' . esc_html__( 'This is your final reminder to complete your !!membership_level_name!! membership checkout at !!sitename!!.', 'pmpro-abandoned-cart-recovery' ) . '</p>

<p><a href="!!checkout_url!!">' . esc_html__( 'Complete Your Purchase Now', 'pmpro-abandoned-cart-recovery' ) . '</a></p>',
		'help_text'   => esc_html__( 'This email is sent as the third reminder to complete a purchase.', 'pmpro-abandoned-cart-recovery' )
	);

	return $templates;
}

/**
 * Send a reminder email.
 *
 * @since 0.1
 *
 * @param object $recovery_attempt The recovery attempt.
 * @param int $reminder_number The reminder number.
 */
function pmproacr_send_reminder_email( $recovery_attempt, $reminder_number ) {
	// Get the user.
	$user  = get_userdata( $recovery_attempt->user_id );
	$level = pmpro_getLevel( $recovery_attempt->token_level_id );

	// If we don't have a user or a level, bail.
	if ( empty( $user ) || empty( $level ) ) {
		return;
	}

	// Send the email.
	$template_class = 'PMPro_Email_Template_PMProACR_Reminder_' . $reminder_number;
	if ( class_exists( $template_class ) ) {
		// Using PMPro v3.4+. Create an instance of the email template class.
		$email_template = new $template_class( $user, $level );
		$email_template->send();
	} else {
		// Using PMPro version under v3.4. Use the legacy logic.
		$email           = new PMProEmail();
		$email->template = 'pmproacr_reminder_' . $reminder_number;
		$email->email    = $user->user_email;
		$email->data     = array(
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
			'display_name' => $user->display_name,
			'header_name' => $user->display_name,
			'sitename' => get_option('blogname'),
			'siteemail' => get_option('pmpro_from_email'),
			'login_link' => pmpro_login_url(),
			'login_url' => pmpro_login_url(),
			'membership_id' => $level->id,
			'membership_level_name' => $level->name,
			'checkout_url' => pmpro_login_url( pmpro_url( 'checkout', '?pmpro_level=' . $level->id ) ),
			'levels_url' => pmpro_login_url( pmpro_url( 'levels' ) ),
			'opt_out_url' => pmproacr_get_opt_out_url( $user ),
		);
		$email->sendEmail();
	}
}
