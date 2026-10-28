<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run any necessary upgrades to the DB.
 */
function pmproacr_check_for_upgrades() {
	$db_version = get_option( 'pmproacr_db_version' );

	// If we can't find the DB tables, reset db_version to 0
	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->pmproacr_recovery_attempts = $wpdb->prefix . 'pmproacr_recovery_attempts';
	$table_exists = $wpdb->query("SHOW TABLES LIKE '" . $wpdb->pmproacr_recovery_attempts . "'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checks whether the custom table exists; table name is built from the site prefix.
	if(!$table_exists)
		$db_version = 0;

	// Default options.
	if ( ! $db_version ) {
		pmproacr_db_delta();
		update_option( 'pmproacr_db_version', 1 );
	}

	// Move opt-outs from the old opt-out link to PMPro email preferences. Waits until PMPro has email preferences.
	if ( $db_version < 1.1 && pmproacr_use_pmpro_email_preferences() ) {
		if ( pmproacr_migrate_opt_outs() ) {
			update_option( 'pmproacr_db_version', 1.1 );
		}
	}
}

/**
 * Move users who opted out with the old opt-out link to PMPro email preferences.
 *
 * Abandoned cart emails are in the "Offers and promotions" email category.
 *
 * @since TBD
 *
 * @return bool True if every user was moved, false if any should be tried again later.
 */
function pmproacr_migrate_opt_outs() {
	global $wpdb;

	$user_ids = $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pmproacr_opt_out'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade.
	$migrated_all = true;
	foreach ( $user_ids as $user_id ) {
		pmpro_update_user_email_opt_out( (int) $user_id, 'promotional', true, 'link' );

		// Only remove the old opt-out once the new one is saved. Otherwise, try again next time.
		if ( ! pmpro_get_user_email_opt_out( (int) $user_id, 'promotional' ) ) {
			$migrated_all = false;
			continue;
		}

		// The old opt-out is shared by every site in a network, but PMPro email preferences are per site. Keep it so other sites can move it too.
		if ( ! is_multisite() ) {
			delete_user_meta( (int) $user_id, 'pmproacr_opt_out' );
		}
	}

	return $migrated_all;
}

/**
 * Make sure the DB is set up correctly.
 */
function pmproacr_db_delta() {
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	global $wpdb;
	$wpdb->hide_errors();
	$wpdb->pmproacr_recovery_attempts = $wpdb->prefix . 'pmproacr_recovery_attempts';

	$collate = '';
	if ( $wpdb->has_cap( 'collation' ) ) {
		$collate = $wpdb->get_charset_collate();
	}

	// pmproacr_recovery_attempts
	$sqlQuery = "
		CREATE TABLE `" . $wpdb->pmproacr_recovery_attempts . "` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) unsigned NOT NULL,
			`token_datetime` datetime NOT NULL,
			`token_level_id` int(11) unsigned NOT NULL,
			`token_total` decimal(18,8) NOT NULL,
			`token_order_id` bigint(20) unsigned NOT NULL,
			`status` varchar(32) NOT NULL,
			`reminder_1_datetime` datetime DEFAULT NULL,
			`reminder_2_datetime` datetime DEFAULT NULL,
			`reminder_3_datetime` datetime DEFAULT NULL,
			`recovered_datetime` datetime DEFAULT NULL,
			`recovered_level_id` int(11) unsigned DEFAULT NULL,
			`recovered_total` decimal(18,8) DEFAULT NULL,
			`recovered_order_id` bigint(20) unsigned DEFAULT NULL,  
			PRIMARY KEY (`id`),
			KEY `user_id` (`user_id`),
			KEY `token_order_id` (`token_order_id`),
			KEY `status` (`status`),
			KEY `reminder_1_datetime` (`reminder_1_datetime`),
			KEY `reminder_2_datetime` (`reminder_2_datetime`),
			KEY `reminder_3_datetime` (`reminder_3_datetime`),
			KEY `recovered_datetime` (`recovered_datetime`)
		) $collate;
	";
	dbDelta( $sqlQuery );
}

// Check if the DB needs to be upgraded. Runs on init so that upgrades can use pluggable functions.
if ( is_admin() || defined('WP_CLI') ) {
	add_action( 'init', 'pmproacr_check_for_upgrades', 0 );
}
