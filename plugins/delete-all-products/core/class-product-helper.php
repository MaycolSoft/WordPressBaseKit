<?php
/**
 * Product Helper.
 */

namespace DAPRODS\Core;

class ProductHelper {
	/**
	 * Get the count of all products by status.
	 *
	 * @return array
	 */
	public static function get_product_stat() {
		// Get the count of all products by status
		$product_counts = wp_count_posts( 'product' );

		// Sanitize the product counts
		$products_all  = intval( $product_counts->publish ) + intval( $product_counts->pending ) + intval( $product_counts->draft ) + intval( $product_counts->private );
		$trashed_count = intval( $product_counts->trash );

		// Prepare and return the stat
		$stat = array(
			'all'   => rest_sanitize_value_from_schema( $products_all, array( 'type' => 'integer' ) ),
			'trash' => rest_sanitize_value_from_schema( $trashed_count, array( 'type' => 'integer' ) ),
		);

		return $stat;
	}

	/**
	 * Get product stats based on optional stock and product status filters.
	 *
	 * @param array|null $stock_status Filter by stock status.
	 * @param array|null $product_status Filter by product status.
	 * @return array Product stats.
	 * @since 1.0.0
	 */
	public static function get_product_count( $stock_status = null, $product_status = null ) {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'fields'         => 'ids', // We only need the count, so we retrieve the IDs
		);

		// Apply product status filter if provided
		if ( ! empty( $product_status ) ) {
			$args['post_status'] = $product_status;
		}

		// Apply stock status filter if provided
		if ( ! empty( $stock_status ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_stock_status',
					'value'   => $stock_status,
					'compare' => 'IN',
				),
			);
		}

		// Execute the query
		$query         = new \WP_Query( $args );
		$product_count = $query->found_posts;

		return $product_count;
	}
}
