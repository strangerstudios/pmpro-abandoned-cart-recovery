<?php
/**
 * Process abandoned cart recovery attempts.
 *
 * @since TBD
 */
function pmproacr_cron_process_recovery_attempts() {
	global $wpdb;

	// If PMPro is not active, then we can't do anything.
	if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
		return;
	}

	// Get settings for the cron.
	/**
	 * Filter the time until the first reminder.
	 * By default, the first reminder is 1 hour after the token is created.
	 *
	 * @since TBD
	 *
	 * @param int $seconds_until_reminder_1 The time in seconds until the first reminder.
	 */
	$seconds_until_reminder_1 = (int) apply_filters( 'pmproacr_time_until_reminder_1', HOUR_IN_SECONDS );
	/**
	 * Filter the time until the second reminder.
	 * By default, the second reminder is 23 hours after the first reminder (24 hours after the token is created).
	 *
	 * @since TBD
	 *
	 * @param int $seconds_until_reminder_2 The time in seconds until the second reminder.
	 */
	$seconds_until_reminder_2 = (int) apply_filters( 'pmproacr_time_until_reminder_2', HOUR_IN_SECONDS * 23 );
	/**
	 * Filter the time until the third reminder.
	 * By default, the third reminder is 6 days after the second reminder (7 days after the token is created).
	 *
	 * @since TBD
	 *
	 * @param int $seconds_until_reminder_3 The time in seconds until the third reminder.
	 */
	$seconds_until_reminder_3 = (int) apply_filters( 'pmproacr_time_until_reminder_3', DAY_IN_SECONDS * 6 );
	/**
	 * Filter the time until the order is marked as lost.
	 * By default, the order is marked as lost 14 days after the token is created (7 days after the third reminder).
	 *
	 * @since TBD
	 *
	 * @param int $seconds_until_lost The time in seconds until the order is marked as lost.
	 */
	$seconds_until_lost       = (int) apply_filters( 'pmproacr_time_until_lost', DAY_IN_SECONDS * 7 );

	// Get all levels that have abandoned cart recovery enabled.
	$enabled_levels = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pmpro_membership_level_id
			FROM $wpdb->pmpro_membership_levelmeta
			WHERE meta_key = 'pmproacr_enabled_for_level' AND meta_value = 'yes'"
		)
	);

	// Send the first reminder.
	// Get all token orders older than the current time - seconds_until_reminder_1 but after the last timestamp checked.
	// To help with performance and to avoid confusing customers, let's limit the "last timestamp checked" to at most $seconds_until_reminder_1 * 4 in the past.
	$reminder_1_last_datetime_checked    = get_option( 'pmproacr_last_datetime_checked', '0000-00-00 00:00:00' );
	$reminder_1_datetime_lower_bound     = get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds_until_reminder_1 * 4 ), 'Y-m-d H:i:s' );
	$reminder_1_oldest_datetime          = max( $reminder_1_datetime_lower_bound, $reminder_1_last_datetime_checked );
	$reminder_1_newest_datetime          = get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds_until_reminder_1 ), 'Y-m-d H:i:s' );
	$reminder_1_token_orders             = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT o.id, o.user_id, o.membership_id, o.total, o.timestamp
			FROM $wpdb->pmpro_membership_orders o
			WHERE o.timestamp > %s AND o.timestamp < %s AND o.status = 'token' AND o.total > 0 AND o.membership_id IN(" . implode( ',', $enabled_levels ) . ")
			ORDER BY o.timestamp ASC",
			$reminder_1_oldest_datetime,
			$reminder_1_newest_datetime
		)
	);

	// Loop through orders and start a recovery attempt when needed.
	foreach ( $reminder_1_token_orders as $token_order ) {
		// If the user already has a recovery attempt in progress, then skip this order.
		$existing_recovery_attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id
				FROM $wpdb->pmproacr_recovery_attempts
				WHERE user_id = %d AND status = 'in_progress'",
				$token_order->user_id,
			)
		);
		if ( ! empty( $existing_recovery_attempt ) ) {
			update_option( 'pmproacr_last_datetime_checked', $token_order->timestamp );
			continue;
		}

		// Get all orders for the user from the past $seconds_until_reminder_1 * 4 seconds.
		$user_orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.id, o.user_id, o.membership_id, o.total, o.timestamp, o.status
				FROM $wpdb->pmpro_membership_orders o
				WHERE o.user_id = %d AND o.timestamp > %s
				ORDER BY o.timestamp DESC",
				$token_order->user_id,
				$reminder_1_datetime_lower_bound
		   )
		);

		// If the first order in $user_orders is not $token order, then there are more recent orders. Skip this order and we'll get to it later.
		if ( $user_orders[0]->id !== $token_order->id ) {
			update_option( 'pmproacr_last_datetime_checked', $token_order->timestamp );
			continue;
		}

		// If we get here, then we are going to attempt to recover an order for this user as long as the user doesn't already have a better level.
		// Let's get the highest value order in the past $seconds_until_reminder_1 * 4 seconds to determine which specific order to attempt to recover.
		$order_to_recover = null;
		foreach ( $user_orders as $user_order ) {
			// If we hit an order not in token status, then we can stop looking.
			if ( $user_order->status !== 'token' ) {
				break;
			}

			// If we don't have an order to recover yet or if the current order is higher than the order to recover, then set the order to recover to the current order.
			if ( empty( $order_to_recover ) || $user_order->total > $order_to_recover->total ) {
				$order_to_recover = $user_order;
			}
		}

		/**
		 * Filter whether to attempt to recover an order for a user.
		 *
		 * By default, we will attempt to recover an order for a user if the user does not already have a level. This filter can be used to change this behavior.
		 *
		 * @since TBD
		 *
		 * @param bool $recover_order Whether to attempt to recover an order for a user.
		 * @param object $order_to_recover The order to recover.
		 */
		if ( apply_filters( 'pmproacr_should_attempt_recovery', ! pmpro_hasMembershipLevel( null, $order_to_recover->user_id ), $order_to_recover ) ) {
			// Create the recovery attempt.
			$wpdb->insert(
				$wpdb->pmproacr_recovery_attempts,
				array(
					'user_id'             => $order_to_recover->user_id,
					'token_datetime'      => $order_to_recover->timestamp,
					'token_level_id'      => $order_to_recover->membership_id,
					'token_total'         => $order_to_recover->total,
					'token_order_id'      => $order_to_recover->id,
					'status'              => 'in_progress',
					'reminder_1_datetime' => current_time( 'Y-m-d H:i:s', true ),
				)
			);

			// Get the row that was just inserted.
			$recovery_attempt_id = $wpdb->insert_id;
			$recovery_attempt    = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, user_id, token_order_id, token_total, token_level_id
					FROM $wpdb->pmproacr_recovery_attempts
					WHERE id = %d",
					$recovery_attempt_id
				)
			);

			// Send the email.
			pmproacr_send_reminder_email( $recovery_attempt, 1 );
		}

		update_option( 'pmproacr_last_datetime_checked', $token_order->timestamp );
	}

	// Send the second reminder.
	// Get all recovery attempts that:
	// - Are in progress.
	// - Have a first reminder datetime set
	// - Do not have a second reminder datetime set
	// - Have a first reminder datetime older than the current time - seconds_until_reminder_2
	$reminder_2_datetime_cutoff = get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds_until_reminder_2 ), 'Y-m-d H:i:s' );
	$reminder_2_recovery_attempts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, user_id, token_order_id, token_total, token_level_id, reminder_1_datetime
			FROM $wpdb->pmproacr_recovery_attempts
			WHERE status = 'in_progress' AND reminder_1_datetime IS NOT NULL AND reminder_2_datetime IS NULL AND reminder_1_datetime < %s",
			$reminder_2_datetime_cutoff
		)
	);
	foreach( $reminder_2_recovery_attempts as $recovery_attempt ) {
		// Send email to user.
		pmproacr_send_reminder_email( $recovery_attempt, 2 );

		// Update the recovery attempt.
		$wpdb->update(
			$wpdb->pmproacr_recovery_attempts,
			array( 'reminder_2_datetime' => current_time( 'Y-m-d H:i:s', true ) ),
			array( 'id' => $recovery_attempt->id )
		);
	}

	// Send the third reminder.
	// Get all recovery attempts that:
	// - Are in progress.
	// - Have a second reminder datetime set
	// - Do not have a third reminder datetime set
	// - Have a second reminder datetime older than the current time - seconds_until_reminder_3
	$reminder_3_datetime_cutoff = get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds_until_reminder_3 ), 'Y-m-d H:i:s' );
	$reminder_3_recovery_attempts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, user_id, token_order_id, token_total, token_level_id, reminder_2_datetime
			FROM $wpdb->pmproacr_recovery_attempts
			WHERE status = 'in_progress' AND reminder_2_datetime IS NOT NULL AND reminder_3_datetime IS NULL AND reminder_2_datetime < %s",
			$reminder_3_datetime_cutoff
		)
	);
	foreach( $reminder_3_recovery_attempts as $recovery_attempt ) {
		// Send email to user.
		pmproacr_send_reminder_email( $recovery_attempt, 3 );

		// Update the recovery attempt.
		$wpdb->update(
			$wpdb->pmproacr_recovery_attempts,
			array( 'reminder_3_datetime' => current_time( 'Y-m-d H:i:s', true ) ),
			array( 'id' => $recovery_attempt->id )
		);
	}

	// Mark the order as lost.
	// Get all recovery attempts that:
	// - Are in progress.
	// - Have a third reminder datetime set
	// - Have a third reminder datetime older than the current time - seconds_until_lost
	$lost_datetime_cutoff = get_gmt_from_date( date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $seconds_until_lost ), 'Y-m-d H:i:s' );
	$lost_recovery_attempts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, user_id, token_order_id, token_total, token_level_id, reminder_3_datetime
			FROM $wpdb->pmproacr_recovery_attempts
			WHERE status = 'in_progress' AND reminder_3_datetime IS NOT NULL AND reminder_3_datetime < %s",
			$lost_datetime_cutoff
		)
	);
	foreach( $lost_recovery_attempts as $recovery_attempt ) {
		// Update the recovery attempt.
		$wpdb->update(
			$wpdb->pmproacr_recovery_attempts,
			array( 'status' => 'lost' ),
			array( 'id' => $recovery_attempt->id )
		);
	}
}
add_action( 'pmproacr_cron_process_recovery_attempts', 'pmproacr_cron_process_recovery_attempts' );

/**
 * Schedule the cron job.
 *
 * @since TBD
 */
function pmproacr_activation() {
	$next = wp_next_scheduled( 'pmproacr_cron_process_recovery_attempts' );
	if ( ! $next ) {
		wp_schedule_event( time(), 'hourly', 'pmproacr_cron_process_recovery_attempts' );
	}
}
register_activation_hook( PMPROACR_BASE_FILE, 'pmproacr_activation' );

/**
 * Clear the cron job.
 *
 * @since TBD
 */
function pmproacr_deactivation() {	
	wp_clear_scheduled_hook( 'pmproacr_cron_process_recovery_attempts' );
}
register_deactivation_hook( PMPROACR_BASE_FILE, 'pmproacr_deactivation' );
