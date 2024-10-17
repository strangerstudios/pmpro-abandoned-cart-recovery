<?php
/**
 * Add options to level settings to enable Abandoned Cart Recovery.
 *
 * @since TBD
 */
function pmproacr_membership_level_before_content_settings() {
	$edit_level_id = $_REQUEST['edit'];
	$enabled = 'yes' === get_pmpro_membership_level_meta( $edit_level_id, 'pmproacr_enabled_for_level', true );
	$acr_checked = $enabled ? ' checked' : '';

	if ( ! empty( $enabled ) ) {
		$section_visibility = 'visible';
		$section_activated  = 'true';
	} else {
		$section_visibility = 'hidden';
		$section_activated  = 'false';
	}
?>
	<div id="pmpro-acr" class="pmpro_section" data-visibility="<?php echo esc_attr( $section_visibility ); ?>" data-activated="<?php echo esc_attr( $section_activated ); ?>">
		<div class="pmpro_section_toggle">
			<button class="pmpro_section-toggle-button" type="button" aria-expanded="<?php echo $section_visibility === 'hidden' ? 'false' : 'true'; ?>">
				<span class="dashicons dashicons-arrow-<?php echo $section_visibility === 'hidden' ? 'down' : 'up'; ?>-alt2"></span>
				<?php esc_html_e( 'Abandoned Cart Recovery Settings', 'pmpro-abandoned-cart-recovery' ); ?>
			</button>
		</div>
		<div class="pmpro_section_inside" <?php echo $section_visibility === 'hidden' ? 'style="display: none"' : ''; ?>>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" valign="top">
							<label for="pmproacr_enabled_for_level"><?php esc_html_e( 'Enable Abandoned Cart Recovery', 'pmpro-abandoned-cart-recovery' ); ?></label>
						</th>
						<td>
							<input type="checkbox" id="pmproacr_enabled_for_level" name="pmproacr_enabled_for_level" value="1" <?php echo $acr_checked; ?> />
							<label for="pmproacr_enabled_for_level"><?php esc_html_e( 'Enable Abandoned Cart Recovery for this level.', 'pmpro-abandoned-cart-recovery' ); ?></label>
							<p class="description"><?php esc_html_e( 'Check the box to activate abandoned cart recovery for this level. Emails will be sent 1 hour, 1 day, and 1 week after the cart is abandoned. You can customize these emails on the Settings > Email Templates screen.', 'pmpro-abandoned-cart-recovery' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
<?php
}
add_action( 'pmpro_membership_level_before_content_settings', 'pmproacr_membership_level_before_content_settings' );

/**
 * Save the Abandoned Cart Recovery settings for a membership level.
 *
 * @since TBD
 * @param int $save_id The ID of the membership level being saved.
 * @return void
 */
function pmproacr_save_membership_level( $save_id ) {
	$enabled = empty( $_REQUEST['pmproacr_enabled_for_level'] ) ? 'no' : 'yes';

	update_pmpro_membership_level_meta( $save_id, 'pmproacr_enabled_for_level', $enabled );
}
add_action( 'pmpro_save_membership_level', 'pmproacr_save_membership_level' );
