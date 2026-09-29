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

// Check if the DB needs to be upgraded.
if ( is_admin() || defined('WP_CLI') ) {
	pmproacr_check_for_upgrades();
}
