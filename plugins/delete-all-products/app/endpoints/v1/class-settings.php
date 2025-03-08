<?php
/**
 * API endpoint class for settings
 */

namespace DAPRODS\App\Endpoints\V1;

// Avoid direct file request
defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

use DAPRODS\Core\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Settings extends Endpoint {
	/**
	 * API endpoint for the current endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $endpoint = 'settings';

	/**
	 * Register the routes for handling store settings functionality.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		\register_rest_route(
			$this->get_namespace(),
			$this->get_endpoint(),
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'edit_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'edit_permission' ),
					'args'                => array(
						'delete_product_images' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Get store settings.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 */
	public function get_settings( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-NONCE' );
		if ( ! \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( \esc_html__( 'Invalid nonce', 'delete-all-products' ), 403 );
		}

		$delete_product_images    = \get_option( 'daprods_delete_product_images', '' );

		$response_data = array(
			'delete_product_images'    => \esc_html( $delete_product_images ),
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Save store settings.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 */
	public function save_settings( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-NONCE' );
		if ( ! \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( \esc_html__( 'Invalid nonce', 'delete-all-products' ), 403 );
		}

		$delete_product_images    = isset( $request['delete_product_images'] ) ? \sanitize_text_field( $request['delete_product_images'] ) : '';

		\update_option( 'daprods_delete_product_images', $delete_product_images );

		return new WP_REST_Response(
			array(
				'message' => \esc_html__( 'Settings saved successfully.', 'delete-all-products' ),
			),
			200
		);
	}
}
