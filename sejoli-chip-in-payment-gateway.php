<?php
/**
 *
 * @link              https://ridwan-arifandi.com
 * @since             1.0.0
 * @package           Sejoli
 *
 * @wordpress-plugin
 * Plugin Name:       Sejoli - Chip In Payment Gateway
 * Plugin URI:        https://sejoli.co.id
 * Description:       Integrate Sejoli Premium WordPress Membership Plugin with Chip In Payment Gateway.
 * Version:           1.0.0
 * Requires PHP: 	  7.4.0
 * Author:            Sejoli
 * Author URI:        https://sejoli.co.id
 * Text Domain:       sejoli-chip-in
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {

	die;

}

// Register payment gateway
add_filter('sejoli/payment/available-libraries', function( array $libraries ) {

    require_once ( plugin_dir_path( __FILE__ ) . '/class-chip-in-payment-gateway.php' );

    $libraries['chip-in'] = new \SejoliChipIn();

    return $libraries;

});

add_action( 'plugins_loaded', 'sejoli_chip_in_plugin_init' ); 
function sejoli_chip_in_plugin_init() {

    load_plugin_textdomain( 'sejoli-chip-in', false, dirname(plugin_basename(__FILE__)).'/languages/' );

}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';