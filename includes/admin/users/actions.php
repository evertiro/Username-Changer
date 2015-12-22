<?php
/**
 * Admin Pages
 *
 * @package     Username_Changer\Admin\User\Actions
 * @since       2.1.0
 */


// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Add link to user page
 *
 * @since       2.1.0
 * @param       array $actions The current user actions
 * @param       object $user The user we are editing
 * @return      array $actions The modified user actions
 */
function username_changer_action_link( $actions, $user ) {
	if( current_user_can( 'edit_users' ) ) {
		if( ! is_multisite() || ( is_multisite() && ! is_network_admin() && ! user_can( $user->ID, 'manage_network' ) ) || ( is_multisite() && is_network_admin() ) ) {
			$actions[] = '<a href="' . add_query_arg( array( 'page' => 'username_changer', 'id' => $user->ID ) ) . '">' . __( 'Change Username', 'username-changer' ) . '</a>';
		}
	}

	return $actions;
}
add_filter( 'user_row_actions', 'username_changer_action_link', 10, 2 );


// Add link to network/users.php
if( is_multisite() ) {
	add_filter( 'ms_user_row_actions', 'username_changer_action_link', 10, 2 );
}
