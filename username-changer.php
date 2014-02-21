<?php
/**
 * Plugin Name:     Username Changer
 * Description:     Lets you change usernames. 
 * Version:         2.0.2
 * Author:          Daniel J Griffiths
 * Author URI:      http://section214.com
 * Text Domain:     username-changer
 *
 * @package         UsernameChanger
 * @author          Daniel J Griffiths <dgriffiths@section214.com>
 * @copyright       Copyright (c) 2014, Daniel J Griffiths
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


if( !class_exists( 'Username_Changer' ) ) {

    class Username_Changer {

        /**
         * @var         Username_Changer $instance The one true Username_Changer
         * @since       2.0.0
         */
        private static $instance;

        /**
         * Get active instance
         *
         * @access      public
         * @since       2.0.0
         * @return      object self::$instance The one true Username_Changer
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new Username_Changer();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       2.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin path
            define( 'USERNAME_CHANGER_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'USERNAME_CHANGER_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Load plugin language files
         *
         * @access      public
         * @since       2.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
            $lang_dir = apply_filters( 'username_changer_lang_dir', $lang_dir );

            // WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'username-changer' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'username-changer', $locale );

            // Setup paths to current locale file
            $mofile_local = $lang_dir . $mofile;
            $mofile_global = WP_LANG_DIR . '/username-changer/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/username-changer folder
                load_textdomain( 'username-changer', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/username-changer/languages/ filder
                load_textdomain( 'username-changer', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'username-changer', false, $lang_dir );
            }
        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       2.0.0
         * @return      void
         */
        public function hooks() {
            // Edit plugin metalinks
            add_filter( 'plugin_row_meta', array( $this, 'plugin_metalinks' ), null, 2 );

            // Add menu item
            add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );

            // Add link to users.php    
            add_filter( 'user_row_actions', array( &$this, 'username_changer_link' ), 10, 2 );

            if( is_multisite() ) {
                // Add link to network/users.php
                add_filter( 'ms_user_row_actions', array( &$this, 'username_changer_link' ), 10, 2 );

                // Add network menu item
                add_action( 'network_admin_menu', array( &$this, 'add_admin_menu' ) );
            }
        }


        /**
         * Modify plugin metalinks
         *
         * @access      public
         * @since       2.0.1
         * @param       array $links The current links array
         * @param       string $file A specific plugin table entry
         * @return      array $links The modified links array
         */
        public function plugin_metalinks( $links, $file ) {
            if( $file == plugin_basename( __FILE__ ) ) {
                $help_link = array(
                    '<a href="http://section214.com/support/forum/username-changer/" target="_blank">' . __( 'Support Forum', 'edd-balanced-gateway' ) . '</a>'
                );

                $docs_link = array(
                    '<a href="http://section214.com/docs/category/username-changer/" target="_blank">' . __( 'Docs', 'edd-balanced-gateway' ) . '</a>'
                );

                $links = array_merge( $links, $help_link, $docs_link );
            }

            return $links;
        }


        /**
         * Add link to user page
         * 
         * @access      public
         * @since       2.0.0
         * @param       array $actions The current user actions
         * @param       object $user The user we are editing
         * @return      array $actions The modified user actions
         */
        public function username_changer_link( $actions, $user ) {
            if( current_user_can( 'edit_users' ) ) {
                if( !is_multisite() || ( is_multisite() && !is_network_admin() && !user_can( $user->ID, 'manage_network' ) ) || ( is_multisite() && is_network_admin() ) ) {
                    $actions[] = '<a href="' . add_query_arg( array( 'page' => 'username_changer', 'id' => $user->ID ) ) . '">' . __( 'Change Username', 'username-changer' ) . '</a>';
                }
            }

            return $actions;
        }


        /**
         * Add menu item for Username Changer
         *
         * @access      public
         * @since       2.0.0
         * @return      void
         */
        public function add_admin_menu() {
            // Only admin-level users with the edit_users capability can change usernames
            add_submenu_page(
                'users.php', 
                __( 'Username Changer', 'username-changer' ), 
                __( 'Username Changer', 'username-changer' ), 
                'edit_users', 
                'username_changer', 
                array( &$this, 'add_admin_page' ) 
            );
        }


        /**
         * Add Username Changer page
         *
         * @access      public
         * @since       2.0.0
         * @global      object $wpdb The WordPress database object
         * @global      array $userdata The data for the current user
         * @global      array $current_user The data for the current user
         * @return      void
         */
        public function add_admin_page() {
            global $wpdb, $userdata, $current_user;

            // Get current user info
            get_currentuserinfo();

            // Make SURE this user can edit users
            if( current_user_can( 'edit_users' ) == false ) {
                echo '<div id="message" class="error"><p><strong>' . __( 'You do not have permission to change a username!', 'username-changer' ) . '</strong></p></div>';
                return;
            }

            if( isset( $_POST['action'] ) && ( $_POST['action'] == 'update' ) && !empty( $_POST['new_username'] ) && !empty( $_POST['current_username'] ) ) {

                // Sanitize the new username
                $new_username       = sanitize_user( $_POST['new_username'] );
                $new_username       = $wpdb->escape( $new_username );
                $current_username   = $wpdb->escape( $_POST['current_username'] );

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
                        $qnn        = $wpdb->prepare( "UPDATE $wpdb->users SET user_nicename = %s WHERE user_login = %s", $new_username, $new_username );

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
    }
}


/**
 * The main function responsible for returning the one true Username_Changer
 * instance to functions everywhere
 *
 * @since       2.0.0
 * @return      Username_Changer The one true Username_Changer
 */
function Username_Changer_load() {
    return Username_Changer::instance();
}
add_action( 'plugins_loaded', 'Username_Changer_load' );
