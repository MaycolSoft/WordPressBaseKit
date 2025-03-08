<?php
/**
 * Products Endpoint.
 */

namespace DAPRODS\App\Endpoints\V1;

// Avoid direct file request
defined( 'ABSPATH' ) || die( 'No direct access allowed!' );

use DAPRODS\Core\Endpoint;
use DAPRODS\Core\ProductHelper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class ProductsSearch extends Endpoint {
	/**
	 * API endpoint for the current endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $endpoint = 'products/search';

	/**
	 * Register the routes for handling products functionality.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->get_namespace(),
			$this->get_endpoint(),
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_products_count' ),
					'permission_callback' => array( $this, 'edit_permission' ),
					'args'                => array(
						'stock_status'   => array(
							'description' => 'Filter by stock status (in_stock, out_of_stock, on_backorder)',
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
							),
							'required'    => false,
						),
						'product_status' => array(
							'description' => 'Filter by product status (publish, pending, draft, private)',
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'publish', 'pending', 'draft', 'private' ),
							),
							'required'    => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Handle the request to get products.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 */
	public function get_products_count( WP_REST_Request $request ) {
		// Sanitize the nonce header value
		$nonce = sanitize_text_field( $request->get_header( 'X-WP-NONCE' ) );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( 'Invalid nonce', 403 );
		}

		// Get optional parameters
		$stock_status   = $request->get_param( 'stock_status' );
		$product_status = $request->get_param( 'product_status' );

		// Get product stats with optional filters
		$product_count = ProductHelper::get_product_count( $stock_status, $product_status );
		$response      = array(
			'search_count' => rest_sanitize_value_from_schema( $product_count, array( 'type' => 'integer' ) ),
		);

		return new WP_REST_Response( $response );
	}
}
