<?php
/**
 * Admin Pages
 *
 * @package     Username_Changer\Admin\Pages
 * @since       2.1.0
 */


// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Add menu item for Username Changer
 *
 * @since       2.1.0
 * @return      void
 */
function username_changer_add_admin_menu() {
	// Only admin-level users with the edit_users capability can change usernames
	add_submenu_page(
		'users.php',
		__( 'Username Changer', 'username-changer' ),
		__( 'Username Changer', 'username-changer' ),
		'edit_users',
		'username_changer',
		'username_changer_add_admin_page'
	);
}
add_action( 'admin_menu', 'username_changer_add_admin_menu' );


// Add network menu item
if( is_multisite() ) {
	add_action( 'network_admin_menu', 'username_changer_add_admin_menu' );
}


/**
 * Add Username Changer page
 *
 * @since       2.1.0
 * @global      object $wpdb The WordPress database object
 * @global      array $userdata The data for the current user
 * @global      array $current_user The data for the current user
 * @return      void
 */
function username_changer_add_admin_page() {
	global $wpdb, $userdata, $current_user;

	// Get current user info
	$current_user = wp_get_current_user();

	// Make SURE this user can edit users
	if( current_user_can( 'edit_users' ) == false ) {
		echo '<div id="message" class="error"><p><strong>' . __( 'You do not have permission to change a username!', 'username-changer' ) . '</strong></p></div>';
		return;
	}

	if( isset( $_POST['action'] ) && ( $_POST['action'] == 'update' ) && !empty( $_POST['new_username'] ) && !empty( $_POST['current_username'] ) ) {

		// Sanitize the new username
		$new_username     = sanitize_user( $_POST['new_username'] );
		$new_username     = esc_sql( $new_username );
		$current_username = esc_sql( $_POST['current_username'] );

		if( username_exists( $current_username ) ) {
			$current_user_data  = get_user_by( 'login', $current_username );

			if( $new_username == $current_username ) {
				// Make sure username exists and username != new username
				echo '<div id="message" class="error"><p><strong>' . sprintf( __( 'Current Username and New Username cannot both be "%1$s"!', 'username-changer' ), $new_username ) . '</strong></p></div>';
			} elseif( username_exists( $new_username ) ) {
				// Make sure new username doesn't exist
				echo '<div id="message" class="error"><p><strong>' . sprintf( __( '"%1$s" cannot be changed to "%2$s", "%3$s" already exists!', 'username-changer' ), $current_username, $new_username, $new_username ) . '</strong></p></div>';
			} elseif( is_multisite() && user_can( $current_user_data->id, 'manage_network' ) && !is_network_admin() ) {
				// Super Admins must be changed from Network Dashboard
				echo '<div id="message" class="error"><p><strong>' . __( '"Super Admin usernames must be changed from the Network Dashboard!', 'username-changer' ) . '</strong></p></div>';
			} elseif( $new_username != $current_username ) {
				// Update username!
				$q          = $wpdb->prepare( "UPDATE $wpdb->users SET user_login = %s WHERE user_login = %s", $new_username, $current_username );
				$qnn        = $wpdb->prepare( "UPDATE $wpdb->users SET user_nicename = %s WHERE user_login = %s", $new_username, strtolower( str_replace( ' ', '-', $new_username ) ) );

				// Check if display name is the same as username
				$usersql    = $wpdb->prepare( "SELECT * from $wpdb->users WHERE user_login = %s", $current_username );
				$userinfo   = $wpdb->get_row( $usersql );

				// If display name is the same as username, update both
				if( $current_username == $userinfo->display_name ) {
					$qdn    = $wpdb->prepare( "UPDATE $wpdb->users SET display_name = %s WHERE user_login = %s", $new_username, $new_username );
				}

				// If the user is a Super Admin, update their permissions
				if( is_multisite() && is_super_admin( $current_user_data->id ) ) {
					grant_super_admin( $current_user_data->id );
				}

				if( false !== $wpdb->query( $q ) ) {
					$wpdb->query( $qnn );

					if( isset( $qdn ) ) {
						$wpdb->query( $qdn );
					}

					// Reassign Coauthor Attribution
					if( username_changer_plugin_installed( 'co-authors-plus/co-authors-plus.php' ) ) {
						global $coauthors_plus;
						$coauthor_posts = get_posts(
							array(
								'post_type'      => get_post_types(),
								'posts_per_page' => -1,
								'tax_query'      => array(
									array(
										'taxonomy' => $coauthors_plus->coauthor_taxonomy,
										'field'    => 'name',
										'terms'    => $current_username
									)
								)
							)
						);
						$current_term = get_term_by( 'name', $current_username, $coauthors_plus->coauthor_taxonomy );
						wp_delete_term( $current_term->term_id, $coauthors_plus->coauthor_taxonomy );
						if ( !empty( $coauthor_posts ) ) {
							foreach ( $coauthor_posts as $coauthor_post ) {
								$coauthors_plus->add_coauthors( $coauthor_post->ID, array( $new_username ), true );
							}
						}
					}

					// If changing own username, display link to re-login
					if( $current_user->user_login == $current_username ) {
						echo '<div id="message" class="updated fade"><p><strong>' . sprintf( __( 'Username %1$s was changed to %2$s.&nbsp;&nbsp;Click <a href="%3$s">here</a> to log back in.', 'username-changer' ), $current_username, $new_username, wp_login_url() ) . '</strong></p></div>';
					} else {
						echo '<div id="message" class="updated fade"><p><strong>' . sprintf( __( 'Username %1$s was changed to %2$s.', 'username-changer' ), $current_username, $new_username ) . '</strong></p></div>';
					}
				} else {
					// If database error occurred, display it
					echo '<div id="message" class="error"><p><strong>' . sprintf( __( 'A database error occurred : %1$s', 'username-changer' ), $wpdb->last_error ) . '</strong></p></div>';
				}
			}
		} else {
			// Warn if user doesn't exist (this should never happen!)
			echo '<div id="message" class="error"><p><strong>' . sprintf( __( 'Username "%1$s" doesn\'t exist!', 'username-changer' ), $current_username ) . '</strong></p></div>';
		}
	} elseif( ( isset( $_POST['action'] ) && $_POST['action'] == 'update' ) && ( empty( $_POST['new_username'] ) || empty( $_POST['current_username'] ) ) ) {
		// All fields are required
		echo '<div id="message" class="error"><p><strong>' . __( 'Both "Current Username" and "New Username" fields are required!', 'username-changer' ) . '</strong></p></div>';
	} ?>

	<div class="wrap">
		<h2><?php echo __( 'Username Changer', 'username-changer' ); ?></h2>

		<br />

		<form name="username_changer" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=username_changer">
			<input type='hidden' name='action' value='update' />
			<table style="width: 759px" cellpadding="5" class="widefat post fixed">
				<thead>
					<tr>
						<?php
							if( isset( $_REQUEST['id'] ) && $_REQUEST['id'] != '' ) {
								$usersql    = $wpdb->prepare( "SELECT * from $wpdb->users where ID = %d", $_REQUEST['id'] );
								$userinfo   = $wpdb->get_row( $usersql );
								echo '<th><strong>' . __( 'Rename user to what?', 'username-changer' ) . '</strong></th>';
							} else {
								echo '<th><strong>' . __( 'Edit which user?', 'username-changer' ) . '</strong></th>';
							}
						?>
					</tr>
					<tr>
						<td>
							<label for="current_username"></label>
							<strong><?php echo __( 'Current Username', 'username-changer' ); ?></strong>
							<?php
								if( isset( $_REQUEST['id'] ) && $_REQUEST['id'] != '' ) {
									$usersql    = $wpdb->prepare( "SELECT * from $wpdb->users where ID = %d", $_REQUEST['id'] );
									$userinfo   = $wpdb->get_row( $usersql );
									echo '<input name="current_username" id="current_username" type="text" value="' . esc_attr( $userinfo->user_login ) . '" size="30" readonly />';
								} else {
									echo '<select name="current_username" id="current_username">';

									echo '<option value=""></option>';

									$usersql    = "SELECT * from $wpdb->users order by user_login asc";
									$userinfo   = $wpdb->get_results( $usersql );

									if( $userinfo ) {
										foreach( $userinfo as $userinfoObj ) {
											if( !is_multisite() || ( is_multisite() && !is_network_admin() && !user_can( $userinfoObj->ID, 'manage_network' ) ) || ( is_multisite() && is_network_admin() ) ) {
												echo '<option value="' . esc_attr( $userinfoObj->user_login ) . '">' . esc_html( $userinfoObj->user_login ) . ' (' . esc_html( $userinfoObj->user_email ) . ')</option>';
											}
										}
									}

									echo '</select>';
								}
							?>
							<br />
							<label for="new_username"></label>
							<strong style="padding-right: 18px;"><?php echo __( 'New Username', 'username-changer' ); ?></strong>
							<input name="new_username" id="new_username" type="text" value="" size="30" />
							<div style="float: right;">
								<input type="submit" name="submit" class="button-secondary action" value="<?php echo __( 'Save Changes', 'username-changer' ); ?>" />
							</div>
						</td>
					</tr>
				</thead>
			</table>
		</form>
	</div>
	<?php
}
