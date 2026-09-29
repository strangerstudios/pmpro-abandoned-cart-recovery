<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * After a checkout is completed, update any in_progress recovery attempts to recovered.
 *
 * @since 0.1
 *
 * @param int $user_id The ID of the user who completed the checkout.
 * @param MemberOrder $order The order that was completed.
 */
function pmproacr_after_checkout( $user_id, $order ) {
	global $wpdb;

	// If the order was free, don't mark the recovery attempt as recovered.
	if ( empty( $order->total ) ) {
		return;
	}

	// Update the recovery attempt to recovered.
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->pmproacr_recovery_attempts,
		array(
			'status'             => 'recovered',
			'recovered_datetime' => current_time( 'Y-m-d H:i:s', true ),
			'recovered_level_id' => $order->membership_id,
			'recovered_total'    => $order->total,
			'recovered_order_id' => $order->id
		),
		array(
			'user_id' => $user_id,
			'status'  => 'in_progress'
		)
	);
}
add_action( 'pmpro_after_checkout', 'pmproacr_after_checkout', 10, 2 );
