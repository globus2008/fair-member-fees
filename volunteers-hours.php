<?php
/**
 * The plugin bootstrap file
 *
 * @since             1.0.0
 * @package           volunteers-hours
 *
 * @wordpress-plugin
 * Plugin Name:       Volunteers Hours
 * Description:       A unique way of fair calculation of the membership fee based on the number of part-time hours worked. A tool for recording part-time hours. Option to pay membership fees using a payment gateway.
 * Version:           1.0.0
 * Author:            Tomas Vesely
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       volunteers-hours
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// GLOBALS AND CONSTANTS
if( ! defined( 'VH_VERSION' ) )     { define( 'VH_VERSION', '1.0.0' ); }
if( ! defined( 'VH_PLUGIN_NAME' ) ) { define( 'VH_PLUGIN_NAME', 'volunteers-hours' ); }
if( ! defined( 'VH_PATH' ) )        { define( 'VH_PATH', __DIR__ ); }

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

// HEADER STRINGS (For translation)
esc_html__( 'A unique way of fair calculation of the membership fee based on the number of part-time hours worked. A tool for recording part-time hours. Option to pay membership fees using a payment gateway.', 'volunteers-hours' );
esc_html__( 'Volunteers Hours', 'volunteers-hours' );

/*
function volunteers_styles() {
    wp_enqueue_style( 'my-unique-text-style', plugins_url( 'includes/volunteers-styles.css', __FILE__ ) );
}
add_action( 'wp_enqueue_scripts', 'volunteers_styles' );
*/

function load_all_styles() {
    wp_register_style('all-styles', plugins_url('includes/volunteers-styles.css', __FILE__));
    wp_enqueue_style('all-styles');
}
add_action('wp_enqueue_scripts', 'load_all_styles');

/*
function drt_enqueue_scripts() {
    wp_enqueue_script( 'vh-functions', plugins_url( 'includes/vh-functions.js', __FILE__ ), array(), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'vh_enqueue_scripts' );

*/
require_once plugin_dir_path( __FILE__ ) . 'includes/brigady-form.php' ; 
require_once plugin_dir_path( __FILE__ ) . 'includes/brigady-list.php' ; 
require_once plugin_dir_path( __FILE__ ) . 'includes/clenove.php' ; 
require_once plugin_dir_path( __FILE__ ) . 'includes/clenske-poplatky.php' ;
require_once plugin_dir_path( __FILE__ ) . 'includes/clenove-payment.php' ;
require_once plugin_dir_path( __FILE__ ) . 'includes/ltc-nastaveni.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ltc-menu.php'; 

/*
// Registrování akčních háčků pro vytvoření a smazání stránky
register_activation_hook(__FILE__, 'create_drt_tournaments_table');
register_activation_hook(__FILE__, 'create_tournament_record');
register_activation_hook(__FILE__, 'create_drt_main_page');
register_activation_hook(__FILE__, 'create_drt_help_page');
register_activation_hook(__FILE__, 'create_drt_example_page');
register_activation_hook(__FILE__, 'update_drt_main_page_content_on_activation');
register_activation_hook(__FILE__, 'update_drt_help_page_content_on_activation');
register_activation_hook(__FILE__, 'update_drt_example_page_content_on_activation');
*/
function volunteers_hours_load_textdomain() {
    load_plugin_textdomain( 'volunteers-hours', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'volunteers_hours_load_textdomain' );

// Deklarace globální proměnné
global $vh_output_form;

// Registrace hooku pro spuštění funkce při aktivaci pluginu
register_activation_hook( __FILE__, 'clenove_plugin_activate' );
// Registrace hooku pro přidání administrační stránky
//add_action( 'admin_menu', 'clenove_plugin_menu' );

// Registrace hooku pro kalkulaci hodnoty brigádnických hodin při uložení brigády
add_action( 'save_post', 'calculate_user_hours' );

// Registrace hooku pro kalkulaci součtu brigádnických hodin u všech členů
add_action( 'admin_init', 'calculate_total_hours' );
// Spuštění funkce při načtení administrativního rozhraní
add_action( 'admin_init', 'calculate_total_hours' );
register_activation_hook( __FILE__, 'calculate_total_hours' );

 // Update 
 // @version 1.1.0 
function vh_check_version() {
	if( defined( 'IFRAME_REQUEST' ) ) { return; }
	$old_version = get_option( 'vh_version' );
	if( $old_version !== VH_VERSION ) {
		update_option('vh_version',VH_VERSION);
	}
}
add_action( 'init', 'vh_check_version', 5 );

// Registrování akčních háčků pro vytvoření a smazání stránky
register_activation_hook(__FILE__, 'create_ltc_page');
register_uninstall_hook(__FILE__, 'delete_ltc_page');
//register_activation_hook(__FILE__, 'update_ltc_page_content_on_activation');
