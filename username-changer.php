<?php
/**
 * Plugin Name:     Username Changer
 * Description:     Lets you <a href='users.php?page=username_changer'>change usernames</a>. 
 * Version:         2.0.0
 * Author:          Daniel J Griffiths
 * Author URI:      http://ghost1227.com
 *
 * @package         Username Changer
 */


// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


// Check if class already exists
if( !class_exists( 'WP_Username_Changer' ) ) {

    class WP_Username_Changer {

        private static $instance;

        /**
         * Get active instance
         *
         * @since       2.0.0
         * @access      public
         * @static
         * @return      object self::$instance
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new WP_Username_Changer();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
                self::$instance->init();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @since       2.0.0
         * @access      private
         * @return      void
         */
        private function setup_constants() {
            // Plugin folder path
            if( !defined( 'WP_USERNAME_CHANGER_DIR' ) )
                define( 'WP_USERNAME_CHANGER_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );

            // Plugin folder URL
            if( !defined( 'WP_USERNAME_CHANGER_URL' ) )
                define( 'WP_USERNAME_CHANGER_URL', plugin_dir_url( WP_USERNAME_CHANGER_DIR ) . basename( dirname( __FILE__ ) ) . '/' );

            // Plugin root file
            if( !defined( 'WP_USERNAME_CHANGER_FILE' ) )
                define( 'WP_USERNAME_CHANGER_FILE', __FILE__ );
        }


        /**
         * Load plugin language files
         *
         * @since       2.0.0
         * @access      public
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = dirname( plugin_basename( WP_USERNAME_CHANGER_FILE ) ) . '/languages/';
            $lang_dir = apply_filters( 'wp_username_changer_languages_directory', $lang_dir );

            // WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'wp-username-changer' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'wp-username-changer', $locale );

            // Setup paths to current locale file
            $mofile_local = $lang_dir . $mofile;
            $mofile_global = WP_LANG_DIR . '/wp-username-changer/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/wp-username-changer folder
                load_textdomain( 'wp-username-changer', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/wp-username-changer/languages/ filder
                load_textdomain( 'wp-username-changer', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'wp-username-changer', false, $lang_dir );
            }
        }


        /**
         * Add plugin actions and filters
         *
         * @since       2.0.0
         * @access      private
         * @return      void
         */
        public function init() {
            // Add menu item
            add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );

            // Add link to users.php    
            add_filter( 'user_row_actions', array( &$this, 'username_changer_link' ), 10, 2 );
        }
        

        /**
         * Add link to user page
         * 
         * @since       2.0.0
         * @access      public
         * @param       array $actions
         * @param       object $user
         * @return      array $actions
         */
        public function username_changer_link( $actions, $user ) {
            if( current_user_can( 'edit_users' ) )
                $actions[] = '<a href="' . admin_url( 'users.php?page=username_changer&id=' . $user->ID ) . '">' . __( 'Change Username', 'wp-username-changer' ) . '</a>';

            return $actions;
        }


        /**
         * Add menu item for Username Changer
         *
         * @since       2.0.0
         * @access      public
         * @return      void
         */
        public function add_admin_menu() {
            // Only admin-level users with the edit_users capability can change usernames
            add_submenu_page(
                'users.php', 
                __( 'Username Changer', 'wp-username-changer' ), 
                __( 'Username Changer', 'wp-username-changer' ), 
                'edit_users', 
                'username_changer', 
                array( &$this, 'add_admin_page' ) 
            );
        }


        /**
         * Add Username Changer page
         *
         * @since       2.0.0
         * @access      public
         * @return      void
         */
        public function add_admin_page() {
            global $wpdb, $userdata, $current_user;

            // Get current user info
            get_currentuserinfo();

            // Make SURE this user can edit users
            if( current_user_can( 'edit_users' ) == false ) {
                echo '<div id="message" class="error"><p><strong>' . __( 'You do not have permission to change a username!', 'wp-username-changer' ) . '</strong></p></div>';
                return;
            }

            if( !empty( $_POST['action'] ) && ( $_POST['action'] == 'update' ) && !empty( $_POST['new_username'] ) && !empty( $_POST['current_username'] ) ) {
                $new_username       = $wpdb->escape( $_POST['new_username'] );
                $current_username   = $wpdb->escape( $_POST['current_username'] );

                if( username_exists( $current_username ) && ( $new_username == $current_username ) ) {
                    // Make sure username exists and username != new username
                    echo '<div id="message" class="error"><p><strong>' . sprintf( __( 'Current Username and New Username cannot both be "%1$s"!', 'wp-username-changer' ), $new_username ) . '</strong></p></div>';
                } elseif( username_exists( $current_username ) && username_exists( $new_username ) ) {
                    // Make sure new username doesn't exist
                    echo '<div id="message" class="error"><p><strong>' . sprintf( __( '"%1$s" cannot be changed to "%2$s", "%3$s" already exists!', 'wp-username-changer' ), $current_username, $new_username, $new_username ) . '</strong></p></div>';
                } elseif( username_exists( $current_username ) && ( $new_username != $current_username ) ) {
                    // Update username!
                    $q          = $wpdb->prepare( "UPDATE $wpdb->users SET user_login = %s WHERE user_login = %s", $new_username, $current_username );
                    $qnn        = $wpdb->prepare( "UPDATE $wpdb->users SET user_nicename = %s WHERE user_login = %s", $new_username, $current_username );

                    // Check if display name is the same as username
                    $usersql    = $wpdb->prepare( "SELECT * from $wpdb->users WHERE user_login = %s", $current_username );
                    $userinfo   = $wpdb->get_row( $usersql );

                    // If display name is the same as username, update both
                    if( $current_username == $userinfo->display_name )
                        $qdn    = $wpdb->prepare( "UPDATE $wpdb->users SET display_name = %s WHERE user_login = %s", $new_username, $new_username );
                    
                    if( false !== $wpdb->query( $q ) ) {
                        $wpdb->query( $qnn );
                        if( isset( $qdn ) )
                            $wpdb->query( $qdn );

                        // If changing own username, display link to re-login
                        if( $current_user->user_login == $current_username ) {
                            echo '<div id="message" class="updated fade"><p><strong>' . sprintf( __( 'Username %1$s was changed to %2$s.&nbsp;&nbsp;Click <a href="%3$s">here</a> to log back in.', 'wp-username-changer' ), $current_username, $new_username, wp_login_url() ) . '</strong></p></div>';
                        } else {
                            echo '<div id="message" class="updated fade"><p><strong>' . sprintf( __( 'Username %1$s was changed to %2$s.', 'wp-username-changer' ), $current_username, $new_username ) . '</strong></p></div>';
                        }
                    } else {
                        // If database error occurred, display it
                        echo '<div id="message" class="error"><p><strong>' . sprintf( __( 'A database error occurred : %1$s', 'wp-username-changer' ), $wpdb->last_error ) . '</strong></p></div>';
                    }
                } else {
                    // Warn if user doesn't exist (this should never happen!)
                    echo '<div id="message" class="error"><p><strong>' . sprintf( __( 'Username "%1$s" doesn\'t exist!', 'wp-username-changer' ), $current_username ) . '</strong></p></div>';
                }
            } elseif( ( $_POST['action'] == 'update' ) && ( empty( $_POST['new_username'] ) || empty( $_POST['current_username'] ) ) ) {
                // All fields are required
                echo '<div id="message" class="error"><p><strong>' . __( 'Both "Current Username" and "New Username" fields are required!', 'wp-username-changer' ) . '</strong></p></div>';
            } ?>

            <div class="wrap">
                <h2><?php echo __( 'Username Changer', 'wp-username-changer' ); ?></h2>

                <br />

                <form name="username_changer" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=username_changer">
                    <input type='hidden' name='action' value='update' />
                    <table style="width: 759px" cellpadding="5" class="widefat post fixed">
                        <thead>
                            <tr>
                                <?php
                                    if( $_REQUEST['id'] != '' ) {
                                        $usersql    = $wpdb->prepare( "SELECT * from $wpdb->users where ID = %d", $_REQUEST['id'] );
                                        $userinfo   = $wpdb->get_row( $usersql );
                                        echo '<th><strong>' . __( 'Rename user to what?', 'wp-username-changer' ) . '</strong></th>';
                                    } else {
                                        echo '<th><strong>' . __( 'Edit which user?', 'wp-username-changer' ) . '</strong></th>';
                                    }
                                ?>
                            </tr>
                            <tr>
                                <td>
                                    <label for="current_username"></label>
                                    <strong><?php echo __( 'Current Username', 'wp-username-changer' ); ?></strong>
                                    <?php
                                        if( $_REQUEST['id'] != '' ) {
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
                                                    echo '<option value="' . esc_attr( $userinfoObj->user_login ) . '">' . esc_html( $userinfoObj->user_login ) . ' (' . esc_html( $userinfoObj->user_email ) . ')</option>';
                                                }
                                            }

                                            echo '</select>';
                                        }
                                    ?>
                                    <br />
                                    <label for="new_username"></label>
                                    <strong style="padding-right: 18px;"><?php echo __( 'New Username', 'wp-username-changer' ); ?></strong>
                                    <input name="new_username" id="new_username" type="text" value="" size="30" />
                                    <div style="float: right;">
                                        <input type="submit" name="submit" class="button-secondary action" value="<?php echo __( 'Save Changes', 'wp-username-changer' ); ?>" />
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


// Off we go!
function WP_UC() {
    return WP_Username_Changer::instance();
}
WP_UC();
