<?php
/**
 * Resume Checkout Bar — settings, defaults, and admin form rendering.
 *
 * @since TBD
 * @package pmpro-abandoned-cart-recovery
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return default copy + behavior for the Resume Checkout Bar.
 *
 * @since TBD
 * @return array
 */
function pmproacr_resume_get_defaults() {
	return array(
		'enabled'          => 0,
		'cart_ttl_days'    => 30,
		'popup_headline'   => __( 'Take your time — we saved your spot.', 'pmpro-abandoned-cart-recovery' ),
		'popup_body'       => __( "Keep browsing. Whenever you're ready to finish signing up, just tap the Resume Checkout bar at the bottom of the page.", 'pmpro-abandoned-cart-recovery' ),
		'bar_text'         => __( 'Ready to finish?', 'pmpro-abandoned-cart-recovery' ),
		'bar_button_label' => __( 'Resume Checkout', 'pmpro-abandoned-cart-recovery' ),
	);
}

/**
 * Get Resume Checkout Bar settings merged with defaults.
 *
 * @since TBD
 * @return array
 */
function pmproacr_resume_get_settings() {
	$saved = get_option( 'pmproacr_resume_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, pmproacr_resume_get_defaults() );
}

/**
 * Sanitize and save the Resume Checkout Bar settings from $_POST.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_save_settings() {
	if ( empty( $_POST['pmproacr_resume_save'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'pmproacr_resume_save', 'pmproacr_resume_nonce' );

	$defaults = pmproacr_resume_get_defaults();

	$ttl_raw = isset( $_POST['pmproacr_resume_cart_ttl_days'] ) ? absint( wp_unslash( $_POST['pmproacr_resume_cart_ttl_days'] ) ) : $defaults['cart_ttl_days'];
	if ( $ttl_raw < 1 ) {
		$ttl_raw = 1;
	}
	if ( $ttl_raw > 365 ) {
		$ttl_raw = 365;
	}

	$settings = array(
		'enabled'          => empty( $_POST['pmproacr_resume_enabled'] ) ? 0 : 1,
		'cart_ttl_days'    => $ttl_raw,
		'popup_headline'   => isset( $_POST['pmproacr_resume_popup_headline'] ) ? sanitize_text_field( wp_unslash( $_POST['pmproacr_resume_popup_headline'] ) ) : $defaults['popup_headline'],
		'popup_body'       => isset( $_POST['pmproacr_resume_popup_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pmproacr_resume_popup_body'] ) ) : $defaults['popup_body'],
		'bar_text'         => isset( $_POST['pmproacr_resume_bar_text'] ) ? sanitize_text_field( wp_unslash( $_POST['pmproacr_resume_bar_text'] ) ) : $defaults['bar_text'],
		'bar_button_label' => isset( $_POST['pmproacr_resume_bar_button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['pmproacr_resume_bar_button_label'] ) ) : $defaults['bar_button_label'],
	);

	// Fall back to defaults if a copy field was cleared, so the front-end never renders an empty label.
	foreach ( array( 'popup_headline', 'popup_body', 'bar_text', 'bar_button_label' ) as $copy_key ) {
		if ( '' === $settings[ $copy_key ] ) {
			$settings[ $copy_key ] = $defaults[ $copy_key ];
		}
	}

	update_option( 'pmproacr_resume_settings', $settings );

	add_settings_error(
		'pmproacr_resume',
		'pmproacr_resume_saved',
		__( 'Resume Checkout Bar settings saved.', 'pmpro-abandoned-cart-recovery' ),
		'updated'
	);
}
add_action( 'admin_init', 'pmproacr_resume_save_settings' );

/**
 * Render the Resume Checkout Bar settings section on the Abandoned Cart Recovery admin page.
 *
 * @since TBD
 * @return void
 */
function pmproacr_resume_render_settings_section() {
	$settings = pmproacr_resume_get_settings();
	$enabled  = ! empty( $settings['enabled'] );

	settings_errors( 'pmproacr_resume' );
	?>
	<div id="pmproacr-resume-settings" class="pmpro_section" data-visibility="shown" data-activated="true">
		<div class="pmpro_section_toggle">
			<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
				<?php esc_html_e( 'Resume Checkout Bar Settings', 'pmpro-abandoned-cart-recovery' ); ?>
			</button>
		</div>
		<div class="pmpro_section_inside">
			<p class="description">
				<?php esc_html_e( 'Save a visitor’s cart (level, discount code, and payment plan) when they reach the checkout page, then show a popup and a sticky bottom bar that links them back to checkout if they leave without completing.', 'pmpro-abandoned-cart-recovery' ); ?>
			</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'pmproacr_resume_save', 'pmproacr_resume_nonce' ); ?>
				<input type="hidden" name="pmproacr_resume_save" value="1" />
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row" valign="top">
								<label for="pmproacr_resume_enabled"><?php esc_html_e( 'Enable Resume Checkout Bar', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="pmproacr_resume_enabled" name="pmproacr_resume_enabled" value="1" <?php checked( $enabled ); ?> />
								<label for="pmproacr_resume_enabled"><?php esc_html_e( 'Show a popup and bottom bar to recover visitors who leave the checkout page before completing.', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row" valign="top">
								<label for="pmproacr_resume_cart_ttl_days"><?php esc_html_e( 'Save cart for', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</th>
							<td>
								<input type="number" id="pmproacr_resume_cart_ttl_days" name="pmproacr_resume_cart_ttl_days" value="<?php echo esc_attr( $settings['cart_ttl_days'] ); ?>" min="1" max="365" step="1" class="small-text" />
								<?php esc_html_e( 'days', 'pmpro-abandoned-cart-recovery' ); ?>
								<p class="description"><?php esc_html_e( 'How long to remember a saved cart before clearing it. Cleared automatically on a successful checkout.', 'pmpro-abandoned-cart-recovery' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row" valign="top">
								<label for="pmproacr_resume_popup_headline"><?php esc_html_e( 'Popup Headline', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</th>
							<td>
								<input type="text" id="pmproacr_resume_popup_headline" name="pmproacr_resume_popup_headline" value="<?php echo esc_attr( $settings['popup_headline'] ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row" valign="top">
								<label for="pmproacr_resume_popup_body"><?php esc_html_e( 'Popup Body', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</th>
							<td>
								<textarea id="pmproacr_resume_popup_body" name="pmproacr_resume_popup_body" rows="3" class="large-text"><?php echo esc_textarea( $settings['popup_body'] ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row" valign="top">
								<label for="pmproacr_resume_bar_text"><?php esc_html_e( 'Bar Text', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</th>
							<td>
								<input type="text" id="pmproacr_resume_bar_text" name="pmproacr_resume_bar_text" value="<?php echo esc_attr( $settings['bar_text'] ); ?>" class="regular-text" />
							</td>
						</tr>
						<tr>
							<th scope="row" valign="top">
								<label for="pmproacr_resume_bar_button_label"><?php esc_html_e( 'Bar Button Label', 'pmpro-abandoned-cart-recovery' ); ?></label>
							</th>
							<td>
								<input type="text" id="pmproacr_resume_bar_button_label" name="pmproacr_resume_bar_button_label" value="<?php echo esc_attr( $settings['bar_button_label'] ); ?>" class="regular-text" />
							</td>
						</tr>
					</tbody>
				</table>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Resume Bar Settings', 'pmpro-abandoned-cart-recovery' ); ?></button>
				</p>
			</form>
		</div>
	</div>
	<?php
}
