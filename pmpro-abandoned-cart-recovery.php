<?php
/*
Plugin Name: Paid Memberships Pro - Abandoned Cart Recovery
Plugin URI: https://www.paidmembershipspro.com/add-ons/abandoned-cart-recovery/
Description: Recover lost revenue by capturing abandoned carts and following up with customers to complete their purchase.
Version: 0.1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com/
Text Domain: pmpro-abandoned-cart-recovery
Domain Path: /languages
*/

// Definitions
define( 'PMPROACR_VERSION', '0.1' );
define( 'PMPROACR_BASE_FILE', __FILE__ );
define( 'PMPROACR_DIR', dirname( __FILE__ ) );
define( 'PMPROACR_BASENAME', plugin_basename( __FILE__ ) );

// Includes
require_once( PMPROACR_DIR . '/includes/admin.php' );
require_once( PMPROACR_DIR . '/includes/crons.php' );
require_once( PMPROACR_DIR . '/includes/emails.php' );
require_once( PMPROACR_DIR . '/includes/checkout.php' );
require_once( PMPROACR_DIR . '/includes/level-settings.php' );
require_once( PMPROACR_DIR . '/includes/upgradecheck.php' );
require_once( PMPROACR_DIR . '/classes/class-pmproacr-recovery-attempts-list-table.php' );

/**
 * Set up the $wpdb table for this plugin.
 *
 * @since TBD
 */
function pmproacr_init() {
	global $wpdb;
	$wpdb->pmproacr_recovery_attempts = $wpdb->prefix . 'pmproacr_recovery_attempts';
}
add_action( 'init', 'pmproacr_init' );

/**
 * Load the languages folder for translations.
 */
function pmproacr_load_textdomain(){
	load_plugin_textdomain( 'pmpro-abandoned-cart-recovery', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'pmproacr_load_textdomain' );

/**
 * Function to add links to the plugin row meta
 */
function pmproacr_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-abandoned-cart-recovery.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/abandoned-cart-recovery/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-abandoned-cart-recovery' ) ) . '">' . __( 'Docs', 'pmpro-abandoned-cart-recovery' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-abandoned-cart-recovery' ) ) . '">' . __( 'Support', 'pmpro-abandoned-cart-recovery' ) . '</a>',
		);
		$links     = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmproacr_plugin_row_meta', 10, 2 );