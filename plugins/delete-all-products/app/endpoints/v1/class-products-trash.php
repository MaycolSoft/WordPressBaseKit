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

class ProductsTrash extends Endpoint {
	/**
	 * API endpoint for the current endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $endpoint = 'products/trash';

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
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'trash_products' ),
					'permission_callback' => array( $this, 'edit_permission' ),
					'args'                => array(
						'stock_status'   => array(
							'description' => 'Filter by stock status (instock, outofstock, onbackorder)',
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
	 * Handle the request to delete products.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 * @since 1.0.0
	 */
	public function trash_products( WP_REST_Request $request ) {
		// Sanitize the nonce header value
		$nonce = sanitize_text_field( $request->get_header( 'X-WP-NONCE' ) );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_REST_Response( 'Invalid nonce', 403 );
		}

		// Get optional parameters
		$stock_status   = $request->get_param( 'stock_status' );
		$product_status = $request->get_param( 'product_status' );

		// Build query arguments dynamically based on provided filters
		$args = array(
			'post_type'      => 'product',
			'post_status'    => $product_status ? $product_status : array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => 10,
		);

		// Add stock status filter if provided
		if ( $stock_status ) {
			$meta_query = array();
			foreach ( $stock_status as $status ) {
				$meta_query[] = array(
					'key'     => '_stock_status',
					'value'   => $status,
					'compare' => '=',
				);
			}
			$args['meta_query'] = array(
				'relation' => 'OR',
				$meta_query,
			);
		}

		// Get the products based on the filtered arguments
		$posts = get_posts( $args );

		$total_trashed = 0;
		foreach ( $posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$product->delete( false );
				++$total_trashed;
			}
		}

		// Prepare the response data
		$response = array(
			'search_count' => ProductHelper::get_product_count( $stock_status, $product_status ),
			'total'        => $total_trashed,
			'stat'         => ProductHelper::get_product_stat(),
		);

		return new WP_REST_Response( $response );
	}
}
