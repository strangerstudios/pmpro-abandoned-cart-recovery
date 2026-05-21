<?php

/**
 * Add the Abandoned Cart Recovery settings page.
 *
 * @since 0.1
 */
function pmproacr_admin_add_page() {
	$recovery_attempts_list_table_hook = add_submenu_page(
		'pmpro-dashboard',
		__( 'Abandoned Cart Recovery', 'pmpro-abandoned-cart-recovery' ),
		__( 'Abandoned Cart Recovery', 'pmpro-abandoned-cart-recovery' ),
		'manage_options',
		'pmpro-acr',
		'pmproacr_admin_page'
	);
	add_action( 'load-' . $recovery_attempts_list_table_hook, 'PMProACR_Recovery_Attempts_List_Table::hook_screen_options' );
}
add_action( 'admin_menu', 'pmproacr_admin_add_page' );

/**
 * Display the Abandoned Cart Recovery settings page.
 *
 * @since 0.1
 */
function pmproacr_admin_page() {
	$recovery_attempts_list_table = new PMProACR_Recovery_Attempts_List_Table();
	$recovery_attempts_list_table->prepare_items();

	require_once PMPRO_DIR . '/adminpages/admin_header.php';

	?>
	<h1><?php esc_html_e( 'Abandoned Cart Recovery', 'pmpro-abandoned-cart-recovery' ); ?></h1>
	<?php
	pmproacr_resume_render_settings_section();
	$recovery_attempts_list_table->display();
	require_once PMPRO_DIR . '/adminpages/admin_footer.php';
}

/**
 * Register the Abandoned Cart Recovery report.
 *
 * @since 1.0
 *
 * @param array $pmpro_reports The existing PMPro reports.
 * @return array The modified PMPro reports with the Abandoned Cart Recovery report added.
 */
function pmproacr_register_report_pmproacr_results( $pmpro_reports ) {
	$pmpro_reports['pmproacr_results'] = __( 'Abandoned Cart Recoveries', 'pmpro-abandoned-cart-recovery' );

	return $pmpro_reports;
}
add_filter( 'pmpro_registered_reports', 'pmproacr_register_report_pmproacr_results' );

/**
 * Add the Abandoned Cart Recovery report widget to the reports page.
 *
 * @since 0.1
 */
function pmpro_report_pmproacr_results_widget() {
	$past_30_days_data = pmproacr_get_results_data( get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS * 30 ), 'Y-m-d H:i:s' ), current_time( 'Y-m-d H:i:s', true ), true );
	$past_12_months_data = pmproacr_get_results_data( get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS * 365 ), 'Y-m-d H:i:s' ), current_time( 'Y-m-d H:i:s', true ), true );
	$all_time_data = pmproacr_get_results_data( '1970-01-01 00:00:00', current_time( 'Y-m-d H:i:s', true ), true );
	?>
	<span id="pmpro_report_pmproacr_results" class="pmpro_report-holder">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col">&nbsp;</th>
					<th scope="col"><?php esc_html_e('Recovered Revenue','pmpro-abandoned-cart-recovery'); ?></th>
					<th scope="col"><?php esc_html_e('Recovered Orders','pmpro-abandoned-cart-recovery'); ?></th>
					<th scope="col"><?php esc_html_e('Recovery Attempts','pmpro-abandoned-cart-recovery'); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e('Past 30 Days','pmpro-abandoned-cart-recovery'); ?></th>
					<td><?php echo esc_html( pmpro_formatPrice( $past_30_days_data['recovered_revenue'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $past_30_days_data['recovered_orders'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $past_30_days_data['recovery_attempts'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Past 12 Months','pmpro-abandoned-cart-recovery'); ?></th>
					<td><?php echo esc_html( pmpro_formatPrice( $past_12_months_data['recovered_revenue'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $past_12_months_data['recovered_orders'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $past_12_months_data['recovery_attempts'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('All Time','pmpro-abandoned-cart-recovery'); ?></th>
					<td><?php echo esc_html( pmpro_formatPrice( $all_time_data['recovered_revenue'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $all_time_data['recovered_orders'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $all_time_data['recovery_attempts'] ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</span>
	<?php
}

/**
 * Calculate the Abandoned Cart Recovery report data between a given date range.
 *
 * @since 0.1
 *
 * @param string $start_date The start date of the date range in the format 'Y-m-d'.
 * @param string $end_date The end date of the date range in the format 'Y-m-d'.
 * @param bool $widget Whether the data is being calculated for a widget.
 * @return array The Abandoned Cart Recovery report data.
 */
function pmproacr_get_results_data( $start_date, $end_date, $widget = false ) {
	global $wpdb;
	$data = array();

	// Get the total number of recovery attempts.
	$data['recovery_attempts'] = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->pmproacr_recovery_attempts} WHERE token_datetime BETWEEN %s AND %s",
			$start_date,
			$end_date
		)
	);

	// Get the total number of successful recoveries.
	$data['recovered_orders'] = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->pmproacr_recovery_attempts} WHERE token_datetime BETWEEN %s AND %s AND status = 'recovered'",
			$start_date,
			$end_date
		)
	);

	// Get the total recovered revenue.
	$data['recovered_revenue'] = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(recovered_total) FROM {$wpdb->pmproacr_recovery_attempts} WHERE token_datetime BETWEEN %s AND %s AND status = 'recovered'",
			$start_date,
			$end_date
		)
	);

	// If this is for the widget, return the data now.
	if ( $widget ) {
		return $data;
	}

	// Get the total number of lost recovery attempts.
	$data['lost_attempts'] = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->pmproacr_recovery_attempts} WHERE token_datetime BETWEEN %s AND %s AND status = 'lost'",
			$start_date,
			$end_date
		)
	);

	// Get the total number of in-progress recovery attempts.
	$data['in_progress_attempts'] = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->pmproacr_recovery_attempts} WHERE token_datetime BETWEEN %s AND %s AND status = 'in_progress'",
			$start_date,
			$end_date
		)
	);

	return $data;
}