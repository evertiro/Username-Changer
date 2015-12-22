<?php
/**
 * Helper functions
 *
 * @package     Username_Changer\Functions
 * @since       2.1.0
 */


// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Check if Co-Authors Plus is installed
 *
 * @since       1.0.0
 * @param       string $plugin The path to the plugin to check
 * @return      boolean true if installed and active, false otherwise
 */
function username_changer_plugin_installed( $plugin = false ){
	$ret = false;

	if( $plugin ) {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );

		if( in_array( $plugin, $active_plugins ) ) {
			$ret = true;
		}
	}

	return $ret;
}