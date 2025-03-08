<?php
/**
 * Class to boot up plugin.
 */

namespace DAPRODS\Core;

use DAPRODS\Core\Base;
use DAPRODS\App;

// Avoid direct file request
defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

final class Loader extends Base {
	/**
	 * Settings helper class instance.
	 *
	 * @since 1.0.0
	 * @var object
	 *
	 */
	public $settings;

	/**
	 * Minimum supported php version.
	 *
	 * @since  1.0.0
	 * @var float
	 *
	 */
	public $php_version = '7.4';

	/**
	 * Minimum WordPress version.
	 *
	 * @since  1.0.0
	 * @var float
	 *
	 */
	public $wp_version = '6.1';

	/**
	 * Initialize functionality of the plugin.
	 *
	 * This is where we kick-start the plugin by defining
	 * everything required and register all hooks.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function __construct() {
		if ( ! $this->can_boot() ) {
			return;
		}

		$this->init();
	}

	/**
	 * Main condition that checks if plugin parts should continue loading.
	 *
	 * @return bool
	 */
	private function can_boot() {
		/**
		 * Checks
		 *  - PHP version
		 *  - WP Version
		 * If not then return.
		 */
		global $wp_version;

		return (
			version_compare( PHP_VERSION, $this->php_version, '>' ) &&
			version_compare( $wp_version, $this->wp_version, '>' )
		);
	}

	/**
	 * Register all the actions and filters.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function init() {
		App\Admin_Pages\DeleteAllProductsAdmin::instance()->init();
		App\Endpoints\V1\ProductsStat::instance();
		App\Endpoints\V1\ProductsSearch::instance();
		App\Endpoints\V1\ProductsTrash::instance();
		App\Endpoints\V1\ProductsRestore::instance();
		App\Endpoints\V1\ProductsDelete::instance();
		App\Endpoints\V1\Settings::instance();
	}
}
