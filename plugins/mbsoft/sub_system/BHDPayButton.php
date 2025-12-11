<?php

// Verificar el Nonce de seguridad
if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bhd_click_nonce')) {
  wp_send_json_error(array('message' => 'Error de seguridad (Nonce inválido).'));
  wp_die();
}

// Obtener la información del carrito de WooCommerce
if (WC()->cart) {
  $cart_data = array(
    'items_count' => WC()->cart->get_cart_contents_count(),
    'total_price' => WC()->cart->get_total('edit'),
    'cart_details' => array()
  );

  foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
    $product = $cart_item['data'];
    $cart_data['cart_details'][] = array(
      'product_id' => $product->get_id(),
      'name'       => $product->get_name(),
      'quantity'   => $cart_item['quantity'],
      'line_total' => $cart_item['line_total']
    );
  }

  // --- Aquí va tu lógica para procesar la data del carrito ---

  wp_send_json_success(array('message' => 'Click capturado y carrito leído.', 'cart' => $cart_data));
} else {
  wp_send_json_error(array('message' => 'Error: El carrito de WooCommerce no está disponible.'));
}
