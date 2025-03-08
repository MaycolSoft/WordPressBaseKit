<?php
/**
 * Main file for WordPress.
 *
 * @wordpress-plugin
 * Plugin Name:     Delete All Products for WooCommerce
 * Description:     Efficiently delete all WooCommerce products in just a few clicks
 * Author:          ThemeDyno
 * Author URI:      https://themedyno.com/
 * Version:         1.4.0
 * Text Domain:     delete-all-products
 * Domain Path:     /languages
 *
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Avoid direct file request
defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

// Support for site-level autoloading.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Plugin version.
if ( ! defined( 'DAPRODS_VERSION' ) ) {
	define( 'DAPRODS_VERSION', '1.0.0' );
}

// Define DAPRODS_PLUGIN_FILE.
if ( ! defined( 'DAPRODS_PLUGIN_FILE' ) ) {
	define( 'DAPRODS_PLUGIN_FILE', __FILE__ );
}

// Plugin directory.
if ( ! defined( 'DAPRODS_DIR' ) ) {
	define( 'DAPRODS_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin basename.
if ( ! defined( 'DAPRODS_PLUGIN_BASENAME' ) ) {
	define( 'DAPRODS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Languages directory.
if ( ! defined( 'DAPRODS_LANGUAGES_DIR' ) ) {
	define( 'DAPRODS_LANGUAGES_DIR', DAPRODS_DIR . '/languages' );
}

// Plugin url.
if ( ! defined( 'DAPRODS_URL' ) ) {
	define( 'DAPRODS_URL', plugin_dir_url( __FILE__ ) );
}

// Assets url.
if ( ! defined( 'DAPRODS_ASSETS_URL' ) ) {
	define( 'DAPRODS_ASSETS_URL', DAPRODS_URL . '/assets' );
}

/**
 * DAPRODS_DeleteAllProductsHandler class.
 */
class DAPRODS_DeleteAllProductsHandler {

	/**
	 * Holds the class instance.
	 *
	 * @var DAPRODS_DeleteAllProductsHandler $instance
	 */
	private static $instance = null;

	/**
	 * Return an instance of the class
	 *
	 * Return an instance of the DAPRODS_DeleteAllProductsHandler Class.
	 *
	 * @return DAPRODS_DeleteAllProductsHandler class instance.
	 * @since 1.0.0
	 *
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class initializer.
	 */
	public function load() {
		load_plugin_textdomain(
			'delete-all-products',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		DAPRODS\Core\Loader::instance();
	}
}

// Init the plugin and load the plugin instance for the first time.
add_action(
	'plugins_loaded',
	function () {
		DAPRODS_DeleteAllProductsHandler::get_instance()->load();
	}
);
