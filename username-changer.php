<?php
/**
 * Plugin Name:     Username Changer
 * Description:     Lets you change usernames.
 * Version:         2.1.1
 * Author:          Daniel J Griffiths
 * Author URI:      http://section214.com
 * Text Domain:     username-changer
 *
 * @package         UsernameChanger
 * @author          Daniel J Griffiths <dgriffiths@section214.com>
 * @copyright       Copyright (c) 2014, Daniel J Griffiths
 */


// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


if( ! class_exists( 'Username_Changer' ) ) {

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
			if( ! self::$instance ) {
				self::$instance = new Username_Changer();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->load_textdomain();
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
		 * Include necessary files
		 *
		 * @access      private
		 * @since       1.0.0
		 * @return      void
		 */
		private function includes() {
			if( is_admin() ) {
				require_once USERNAME_CHANGER_DIR . 'includes/functions.php';
				require_once USERNAME_CHANGER_DIR . 'includes/admin/pages.php';
				require_once USERNAME_CHANGER_DIR . 'includes/admin/users/actions.php';
			}
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
