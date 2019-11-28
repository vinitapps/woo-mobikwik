<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://vinit.site
 * @since             1.0.0
 * @package           Woo_Mobikwik
 *
 * @wordpress-plugin
 * Plugin Name:       VP Mobikwik For WooCommerce
 * Description:       This Plugin will help you integrate Mobikwik Wallet in your Woocommerce Store.
 * Version:           1.0.0
 * Author:            Vinit Patil
 * Author URI:        https://vinit.site
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-mobikwik
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WOO_MOBIKWIK_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woo-mobikwik-activator.php
 */
function activate_woo_mobikwik() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-mobikwik-activator.php';
	Woo_Mobikwik_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woo-mobikwik-deactivator.php
 */
function deactivate_woo_mobikwik() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-mobikwik-deactivator.php';
	Woo_Mobikwik_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_woo_mobikwik' );
register_deactivation_hook( __FILE__, 'deactivate_woo_mobikwik' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woo-mobikwik.php';
