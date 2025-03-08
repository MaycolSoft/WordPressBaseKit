<?php
/**
 * Delete All Products Admin block.
 */

namespace DAPRODS\App\Admin_Pages;

// Abort if called directly.
defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

use DAPRODS\Core\Base;

class DeleteAllProductsAdmin extends Base {
	/**
	 * The page title.
	 *
	 * @var string
	 */
	private $page_title;

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'delete-all-products-admin';

	/**
	 * Page Assets.
	 *
	 * @var array
	 */
	private $page_scripts = array();

	/**
	 * Assets version.
	 *
	 * @var string
	 */
	private $assets_version = '';

	/**
	 * A unique string id to be used in markup and jsx.
	 *
	 * @var string
	 */
	private $unique_id = '';

	/**
	 * Initializes the page.
	 *
	 * @return void
	 * @since 1.0.0
	 *
	 */
	public function init() {
		if ( is_admin() ) {
			$this->page_title     = __( 'Delete All Products', 'delete-all-products' );
			$this->assets_version = ! empty( $this->script_data( 'version' ) ) ? $this->script_data( 'version' ) : DAPRODS_VERSION;
			$this->unique_id      = "daprods_deleteallproducts_main_wrap-{$this->assets_version}";

			add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_filter( 'plugin_action_links_' . DAPRODS_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
		}
	}

	/**
	 * Add action links to the plugin page.
	 *
	 * @param array $links
	 * @return array
	 */
	public function add_action_links( $links ) {
		$new_links = array(
			'<a href="' . admin_url( 'edit.php?post_type=product&page=' . $this->page_slug ) . '">' . __( 'Delete Products', 'delete-all-products' ) . '</a>',
		);
		return array_merge( $new_links, $links );
	}

	public function register_admin_page() {
		$page = add_submenu_page(
			'edit.php?post_type=product', // Parent slug for WooCommerce Products menu
			$this->page_title,
			__( 'Delete Products', 'delete-all-products' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'callback' )
		);

		add_action( 'load-' . $page, array( $this, 'prepare_assets' ) );
	}

	/**
	 * The admin page callback method.
	 *
	 * @return void
	 */
	public function callback() {
		$this->view();
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function prepare_assets() {
		if ( ! is_array( $this->page_scripts ) ) {
			$this->page_scripts = array();
		}

		$handle       = 'daprods_deleteallproducts';
		$src          = DAPRODS_ASSETS_URL . '/js/deleteallproductspage.min.js';
		$style_src    = DAPRODS_ASSETS_URL . '/css/deleteallproductspage.min.css';
		$dependencies = ! empty( $this->script_data( 'dependencies' ) )
			? $this->script_data( 'dependencies' )
			: array(
				'react',
				'wp-element',
				'wp-i18n',
				'wp-is-shallow-equal',
				'wp-polyfill',
			);

		$this->page_scripts[ $handle ] = array(
			'src'       => $src,
			'style_src' => $style_src,
			'deps'      => $dependencies,
			'ver'       => $this->assets_version,
			'strategy'  => true,
			'localize'  => array(
				'dom_element_id' => $this->unique_id,
				'restEndpoint'   => array(
					'productsStat'    => rest_url() . 'delete-all-products/v1/products/stat',
					'productsSearch'  => rest_url() . 'delete-all-products/v1/products/search',
					'productsTrash'   => rest_url() . 'delete-all-products/v1/products/trash',
					'productsDelete'  => rest_url() . 'delete-all-products/v1/products/delete',
					'productsRestore' => rest_url() . 'delete-all-products/v1/products/restore',
					'settings' => rest_url() . 'delete-all-products/v1/settings',
				),
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
			),
		);
	}

	/**
	 * Gets assets data for given key.
	 *
	 * @param string $key
	 *
	 * @return string|array
	 */
	protected function script_data( string $key = '' ) {
		$raw_script_data = $this->raw_script_data();

		return ! empty( $key ) && ! empty( $raw_script_data[ $key ] ) ? $raw_script_data[ $key ] : '';
	}

	/**
	 * Gets the script data from assets php file.
	 *
	 * @return array
	 */
	protected function raw_script_data(): array {
		static $script_data = null;

		if ( is_null( $script_data ) && file_exists( DAPRODS_DIR . 'assets/js/deleteallproductspage.min.asset.php' ) ) {
			$script_data = include DAPRODS_DIR . 'assets/js/deleteallproductspage.min.asset.php';
		}

		return (array) $script_data;
	}

	/**
	 * Prepares assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! empty( $this->page_scripts ) ) {
			foreach ( $this->page_scripts as $handle => $page_script ) {
				wp_register_script(
					$handle,
					$page_script['src'],
					$page_script['deps'],
					$page_script['ver'],
					$page_script['strategy']
				);

				if ( ! empty( $page_script['localize'] ) ) {
					wp_localize_script( $handle, 'daprodsDeleteAllProducts', $page_script['localize'] );
				}

				wp_enqueue_script( $handle );

				if ( ! empty( $page_script['style_src'] ) ) {
					wp_enqueue_style( $handle, $page_script['style_src'], array(), $this->assets_version );
				}

				wp_set_script_translations( $handle, 'delete-all-products', DAPRODS_LANGUAGES_DIR );
			}
		}
	}

	/**
	 * Prints the wrapper element which React will use as root.
	 *
	 * @return void
	 */
	protected function view() {
		echo '<div id="' . esc_attr( $this->unique_id ) . '"></div>';
	}
}
