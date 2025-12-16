<?php




/////////////////////////////////// LOAD PAYMENT GATEWAY METHOD ///////////////////////////////////
function init_bhd_payment_gateway() {
  // Asegurarte de que la clase WC_Payment_Gateway existe
  if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
  }

  class WC_Gateway_MBSOFT_Payment_BHD extends WC_Payment_Gateway {


    /**
     * Propiedad CRÍTICA para la visibilidad en el checkout.
     */
    public $supports = [ 'products', 'refunds', 'default_currency' ]; // Asegúrate de incluir 'default_currency' o la moneda de tu tienda

    /**
     * Define las monedas que soporta tu pasarela.
     */
    public $supports_currency = ['DOP', 'USD'];


    public function __construct() {

      // 1. Obtener settings guardados de tu DB personalizada
      $settings = get_feature_settings('pay_bhd_float_button');
      $wc_settings = $settings['wc_payment_gateway'] ?? []; // Usa un array vacío si no existe la clave

      // 2. Asignar propiedades de la clase usando TUS settings
      // NOTA: Si un setting no existe en tu DB, usará el valor de fallback que definas (por ejemplo, el valor hardcodeado)

      $this->id                 = $wc_settings['id'] ?? 'mbsoft_bhd'; // Siempre usa un ID único
      $this->icon               = $wc_settings['icon'] ?? ''; // O tu URL de icono personalizada
      
      // Puedes dejar esto en false a menos que tu método requiera campos en el checkout
      $this->has_fields         = (bool) ($wc_settings['has_fields'] ?? false); 
      
      $this->method_title       = $wc_settings['method_title'] ?? 'Pago BHD Personalizado';
      $this->method_description = $wc_settings['method_description'] ?? 'Paga con nuestro método de pago BHD personalizado.';

      // 3. Inicializar Settings de WooCommerce (Necesario para get_option)
      // Aunque no uses el formulario de Woo, es buena práctica inicializarlo
      // $this->init_form_fields();
      $this->init_settings();

      // 4. Obtener/Sobrescribir los valores finales (los que verá el cliente)
      // Sobreescribir title y description con TUS valores, ignorando los de Woo
      $this->title       = $this->method_title; 
      $this->description = $this->method_description;

      // 5. Habilitación (Controlada por tu DB)
      $this->enabled = $wc_settings['enabled'] === 'yes' ? 'yes' : 'no';

      // 6. Remover la acción de GUARDAR CONFIGURACIÓN de WooCommerce (Si quieres usar solo tu Dashboard)
      // QUITA ESTA LÍNEA si no quieres que el botón 'Gestionar' funcione para guardar:
      // remove_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Define los campos de configuración que apareceran en el admin.
     */
    public function init_form_fields() {
      $this->form_fields = array(
        'enabled' => array(
            'title'   => __( 'Enable/Disable', 'mbsoft-bhd-payment' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable Mi Nuevo Método de Pago', 'mbsoft-bhd-payment' ),
            'default' => 'no'
        ),
        'title' => array(
            'title'       => __( 'Title', 'mbsoft-bhd-payment' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'mbsoft-bhd-payment' ),
            'default'     => __( 'Mi Nuevo Método de Pago', 'mbsoft-bhd-payment' ),
            'desc_tip'    => true,
        ),
        'description' => array(
            'title'       => __( 'Description', 'mbsoft-bhd-payment' ),
            'type'        => 'textarea',
            'description' => __( 'This controls the description which the user sees during checkout.', 'mbsoft-bhd-payment' ),
            'default'     => __( 'Paga con nuestro nuevo método.', 'mbsoft-bhd-payment' ),
        ),
        // Puedes agregar más campos de configuración aquí (ej. API Keys)
      );
      // $this->form_fields = array();
    }

    /**
     * Procesa el pago y lo redirige.
     * * @param int $order_id ID de la orden.
     * @return array Resultado del proceso de pago.
     */
    public function process_payment( $order_id ) {

      $order = wc_get_order( $order_id );
      $error_message = 'Error desconocido al procesar el pago.';

      try{
        // 1. OBTENER DATOS CRÍTICOS
        $monto_total = $order->get_total();
        $moneda      = $order->get_currency();
        $orden_id    = $order->get_id();
        $order_key   = $order->get_order_key(); // Este será tu transactionId

        $result = mbsoft_create_url_signed_bhd_payment($monto_total, 0, $moneda, $orden_id, $order_key);

        if (isset($result['status']) && $result['status'] === 'success') {
          // **LÓGICA DE ÉXITO:** (TODO CORRECTO AQUÍ)
          // $order->update_status( 'on-hold', __( 'Redirigiendo al cliente a la pasarela de pago BHD.', 'mbsoft-bhd-payment' ) );
          $order->update_status( 'pending', __( 'Redirigiendo al cliente a la pasarela de pago BHD.', 'mbsoft-bhd-payment' ) );
          $order->add_order_note( "Pago BHD iniciado. Redirigiendo a: " . $result['redirect_url'], true );
          WC()->cart->empty_cart();

          return array(
            'result'   => 'success',
            'redirect' => $result['redirect_url'] . $result['jwt'],
          );

        } else {
          // **LÓGICA DE FALLO 1: La API respondió, pero con 'error' o 'failure'**
          // Simplemente establecemos el mensaje de error y dejamos que el código de fallo global lo maneje.
          $error_message = $result['message'] ?? 'La pasarela de pago devolvió un resultado no exitoso o incompleto.';
        }
      }catch(\Throwable $e){
        $error_message = 'Fallo inesperado del sistema (Excepción): ' . $e->getMessage();
      }

      // Notificar al usuario
      wc_add_notice( 'Error al procesar el pago: ' . $error_message, 'error' );

      // Registrar la nota en la orden
      $order->add_order_note( 'ERROR DE PAGO BHD: Proceso fallido. Mensaje: ' . $error_message );

      // Retornar el array de fallo de WooCommerce (Mantener al usuario en checkout)
      return array(
        'result' => 'fail', 
      );

    }


    /**
     * Comprueba si el método de pago está disponible para el checkout.
     * @return bool
     */
    public function is_available() {
      return true;
      // Llama al método base para la verificación inicial (habilita/deshabilita)
      $is_available = parent::is_available(); 

      return $is_available;

      if ( $is_available ) {
        // 1. **Comprobación de Moneda:** Asegura que la moneda del carrito coincide con tu moneda soportada.
        if ( ! in_array( get_woocommerce_currency(), $this->supports_currency ) ) {
          return false;
        }

        // 2. **Comprobación de País/Zona de Venta:** (Opcional, pero segura)
        // Asegura que la dirección de envío/facturación está en una zona soportada (si tienes restricciones).
        // Por ahora, asumiremos que no tienes restricciones geográficas, pero es bueno saber que este es el punto de fallo común.

        // 3. **Comprobación del Carrito:** Asegura que el carrito tiene productos elegibles.
        // Algunos gateways requieren un mínimo o máximo de compra.
      }

      return $is_available;
    }
  }

  function mbsoft_bhd_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_MBSOFT_Payment_BHD';
    return $gateways;
  }
  add_filter( 'woocommerce_payment_gateways', 'mbsoft_bhd_payment_gateway' );

}

add_action( 'plugins_loaded', 'init_bhd_payment_gateway' );
/////////////////////////////////// LOAD PAYMENT GATEWAY METHOD ///////////////////////////////////




/////////////////////////////////// BOTON DE PAGO BHD FLOATING BUTTON SHORTCODE ///////////////////////////////////
function pay_bhd_float_button_shortcode() {
  // Enlace de destino (ejemplo: URL de tu página de pago o contacto)
  $target_url = '#'; // **¡IMPORTANTE! Reemplaza esto con tu URL real**

  // CSS en línea para el botón flotante (puedes moverlo a un archivo CSS externo para mejor práctica)
  $css = '
      <style>
          @keyframes pulse-green {
              0% {
                  box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7);
              }
              70% {
                  box-shadow: 0 0 0 20px rgba(46, 204, 113, 0);
              }
              100% {
                  box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
              }
          }

          .bhd-float-button {
              position: fixed;
              bottom: 20px;
              right: 20px;
              z-index: 1000; /* Asegura que esté por encima de otros elementos */
          }

          .bhd-float-button a {
              display: flex;
              align-items: center;
              justify-content: center;
              background-color: #2ecc71; /* Verde brillante (similar al BHD) */
              color: white;
              padding: 15px 30px;
              border-radius: 50px; /* Bordes muy redondeados para un aspecto moderno */
              text-decoration: none;
              font-size: 20px;
              font-family: \'Montserrat\', sans-serif; /* Fuente moderna */
              font-weight: 700;
              letter-spacing: 1px;
              text-transform: uppercase;
              box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
              transition: all 0.3s ease;
              animation: pulse-green 2s infinite; /* Animación de pulsación */
          }

          .bhd-float-button a:hover {
              background-color: #27ae60; /* Un verde un poco más oscuro al pasar el ratón */
              box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3), 0 0 30px rgba(46, 204, 113, 0.8); /* Efecto de brillo más fuerte */
              transform: translateY(-2px); /* Pequeño levantamiento */
              animation: none; /* Detener la pulsación al hacer hover */
          }
      </style>
      <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap" rel="stylesheet">
  ';

  // Estructura HTML del botón flotante
  $output = $css;
  $output .= '<div class="bhd-float-button">';
  $output .= '<a href="' . esc_url($target_url) . '" target="_blank" rel="noopener noreferrer">';
  $output .= 'Pagar Aquí BHD';
  $output .= '</a>';
  $output .= '</div>';

  return $output;
}

add_shortcode('pay_bhd_float_button', 'pay_bhd_float_button_shortcode');
/////////////////////////////////// BOTON DE PAGO BHD FLOATING BUTTON SHORTCODE ///////////////////////////////////












//////////////////////////// BOTON DE PAGO BHD WOOCOMMERCE SETTINGS ///////////////////////////////////
function mbsoft_bhd_save_wc_settings() {
  // 1. Verificación de Seguridad y Permisos
  if ( ! wp_verify_nonce( $_POST['nonce'], 'mbsoft_bhd_wc_settings_nonce' ) ) {
    wp_send_json_error( array( 'message' => 'Verificación de seguridad fallida.' ) );
    wp_die();
  }

  $feature_code = 'pay_bhd_float_button';


  // 2. Recolectar y sanitizar los datos
  $settings_to_save = [
    'wc_payment_gateway' => [
      'enabled'            => isset($_POST['wc_enabled']) && $_POST['wc_enabled'] === 'yes' ? 'yes' : 'no',
      'method_title'       => sanitize_text_field($_POST['method_title'] ?? ''),
      'method_description' => sanitize_textarea_field($_POST['method_description'] ?? ''),
      ]
  ];
  
  // 3. Guardar en la DB
  if (save_feature_settings($feature_code, $settings_to_save)) {
    wp_send_json_success( array( 'message' => 'Configuraciones de WooCommerce guardadas exitosamente.' ) );
  } else {
    wp_send_json_error( array( 'message' => 'Error al guardar las configuraciones de WooCommerce. Intenta nuevamente.' ) );
  }
}

add_action('wp_ajax_mbsoft_bhd_save_wc_settings', 'mbsoft_bhd_save_wc_settings');
//////////////////////////// BOTON DE PAGO BHD WOOCOMMERCE SETTINGS ///////////////////////////////////








//////////////////////////// BHD PAYMENT CREATE URL SIGNED ///////////////////////////////////
function mbsoft_bhd_create_payment() {

  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'No autorizado'], 403);
  }

  if ( ! wp_verify_nonce( $_POST['nonce'], 'mbsoft_bhd_pay_nonce' ) ) {
    wp_send_json_error( array( 'message' => 'Verificación de seguridad fallida.' ) );
    wp_die();
  }

  // Validar inputs del mini form
  $amount   = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
  $tax      = isset($_POST['tax']) ? floatval($_POST['tax']) : 0;
  $currency = sanitize_text_field($_POST['currency'] ?? '');

  if ($amount <= 0) {
    wp_send_json_error(['message' => 'Monto inválido']);
  }


  $result = mbsoft_create_url_signed_bhd_payment($amount, $tax, $currency);

  if ($result['status'] === 'success') {
    wp_send_json_success([
      'redirect_url' => $result['redirect_url'],
      'jwt'          => $result['jwt'],
      'raw'          => $result['raw'],
    ]);
  } else {
    wp_send_json_error([
      'message' => $result['message'],
      'raw'     => $result['raw'] ?? null,
    ]);
  }


}

function mbsoft_create_url_signed_bhd_payment($amount, $tax = 0, $currency = null, $billingNumber = null, $transactionId=null) {
  try{
    // Obtener settings guardados
    $settings = get_feature_settings('pay_bhd_float_button');

    if (!$settings) {
      return [
        'status' => 'error',
        'message' => 'Configuración no encontrada'
      ];
    }



    //////////////////////// VALIDATE INPUTS ////////////////////////
    if ($amount <= 0) {
      return [
        'status' => 'error',
        'message' => 'Monto inválido'
      ];
    }


    if ($billingNumber === null) {
      $billingNumber = rand(100000, 999999);
    }

    if ($transactionId === null) {
      $transactionId = wp_generate_uuid4();
    }
    //////////////////////// VALIDATE INPUTS ////////////////////////


    $amount = number_format($amount, 2, '.', '');
    $tax = number_format($tax, 2, '.', '');

    //////////////////////// Create payload for BHD API ////////////////////////
    $payload = [
      'clientId'       => $settings['api_key'],
      'clientSecret'   => $settings['api_secret'],
      'billingNumber'  => strval($billingNumber),
      'currency'       => $currency ?: $settings['currency'],
      'amount'         => $amount,
      'tax'            => $tax,
      'creditHold'     => $settings['credit_hold'] ?? 'Y',
      'returnedURL'    => $settings['returned_url'],
      'cancelledURL'   => $settings['cancelled_url'],
      'transactionId'  => $transactionId,
      'scope'          => 'login',
      'description'    => $settings['description'] ?? 'Pago BHD',
    ];
    //////////////////////// Create payload for BHD API ////////////////////////
    




    //////////////////////// Llamada HTTP al API BHD ////////////////////////
    $response = wp_remote_post(
      'https://api-bdp-np.bhd.com.do/bhdleon/boton/v2/proveedores/autenticacion',
      [
        'headers' => [
          'Content-Type' => 'application/json',
        ],
        'body'    => wp_json_encode($payload),
        'timeout' => 30,
      ]
    );
    //////////////////////// Llamada HTTP al API BHD ////////////////////////


    //////////////////////// Handle response ////////////////////////
    if (is_wp_error($response)) {
      return [
        'status' => 'error',
        'message' => 'Error de conexión con BHD'
      ];
    }
    //////////////////////// Handle response ////////////////////////



    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($body['redirect_url']) || empty($body['jwt'])) {
      return [
        'status' => 'error',
        'message' => 'Respuesta inválida de BHD',
        'raw'     => $body
      ];
    }

    return [
      'status' => 'success',
      'redirect_url' => $body['redirect_url'],
      'jwt'          => $body['jwt'],
      'raw'          => $body,
    ];

  } catch (\Throwable $th) {
    return [
      'status' => 'error',
      'message' => 'Excepción: ' . $th->getMessage()
    ];
  }
}

add_action('wp_ajax_mbsoft_bhd_create_payment', 'mbsoft_bhd_create_payment');
//////////////////////////// BHD PAYMENT CREATE URL SIGNED ///////////////////////////////////




/////////////////////////////////// BOTON DE PAGO BHD FLOATING BUTTON ///////////////////////////////////
function handle_bhd_float_button_settings_save() {
  // 1. Verificación de Seguridad y Permisos
  
  if ( ! wp_verify_nonce( $_POST['nonce'], 'bhd_settings_form_nonce' ) ) {
    wp_send_json_error( array( 'message' => 'Verificación de seguridad fallida.' ) );
    wp_die();
  }
  
  $feature_code = 'pay_bhd_float_button';
  // 2. Recolectar y sanitizar los datos
  $settings_to_save = [
      'api_key'       => sanitize_text_field($_POST['api_key'] ?? ''),
      'api_secret'    => sanitize_text_field($_POST['api_secret'] ?? ''),
      'merchant_name' => sanitize_text_field($_POST['merchant_name'] ?? ''),
      'merchant_id'   => sanitize_text_field($_POST['merchant_id'] ?? ''),
      'currency'      => sanitize_text_field($_POST['currency'] ?? 'DOP'),
      'returned_url'  => esc_url_raw($_POST['returned_url'] ?? ''),
      'cancelled_url' => esc_url_raw($_POST['cancelled_url'] ?? ''),
  ];

  // 3. Guardar en la DB
  if (save_feature_settings($feature_code, $settings_to_save)) {
      wp_send_json_success( array( 'message' => 'Configuraciones guardadas exitosamente.' ) );
  } else {
      wp_send_json_error( array( 'message' => 'Error al guardar las configuraciones. Intenta nuevamente.' ) );
  }
}

add_action('wp_ajax_handle_bhd_float_button_settings_save', 'handle_bhd_float_button_settings_save');
/////////////////////////////////// BOTON DE PAGO BHD FLOATING BUTTON ///////////////////////////////////















/////////////////////////////////// WEB HOOKS ///////////////////////////////////
/**
 * Agrega 'verify-order' y 'action' a las variables de consulta de WordPress.
 * (Hook: query_vars)
 */
// function mbsoft_add_query_vars( $vars ) {
//   $vars[] = 'verify_order';
//   $vars[] = 'action';
//   return $vars;
// }
// add_filter( 'query_vars', 'mbsoft_add_query_vars' );



// /**
//  * Define la regla de reescritura para URLs limpias como /verify-order/complete/ o /verify-order/cancel/.
//  * (Hook: init)
//  */
// function mbsoft_add_rewrite_rules() {
//   add_rewrite_rule(
//     '^(?:[^/]+/)?verify-order/(complete|cancel)/?$', // <-- REGLA MODIFICADA
//     'index.php?verify_order=1&action=$matches[1]',
//     'top'
//   );
// }
// add_action( 'init', 'mbsoft_add_rewrite_rules' );


/**
 * Intercepta la solicitud cuando se accede a /verify-order/...
 * y ejecuta la lógica de confirmación o cancelación del pago.
 * (Hook: template_redirect)
 */
add_action( 'template_redirect', 'mbsoft_handle_verify_order_request' );

function mbsoft_handle_verify_order_request() {

  global $wp;

  $current_path = $wp->request; 

  if ( 
    strpos( $current_path, 'verify_order/complete' ) === false && 
    strpos( $current_path, 'verify_order/cancel' ) === false 
  ) {
    return;
  }



  ////////////////////////////////////////////////////////////
  $is_complete = strpos( $current_path, 'verify_order/complete' );
  $is_cancel = strpos( $current_path, 'verify_order/cancel' );
  ////////////////////////////////////////////////////////////




  ////////////////////////////////////////////////////////////
  $transaction_state = isset( $_GET['transactionState'] )
    ? sanitize_text_field( $_GET['transactionState'] )
    : '';

  $process_id = isset( $_GET['processTransactionId'] )
    ? sanitize_text_field( $_GET['processTransactionId'] )
    : '';

  $reference = isset( $_GET['reference'] )
    ? absint( $_GET['reference'] )
    : 0;
  ////////////////////////////////////////////////////////////

  if ( ! $reference ) {
    wp_die( 'Referencia de orden inválida', 'Error', [ 'response' => 400 ] );
  }




  $order = wc_get_order( $reference );

  if ( ! $order ) {
    wp_die( 'Orden no encontrada', 'Error', [ 'response' => 404 ] );
  }

  ///////// Check if order is pending //////////////////
  if ( !$order->has_status( 'pending' ) ) {
    $actual_status = $order->get_status();
    wp_die( "La orden no está en estado pendiente. Estado actual: {$actual_status}", 'Error', [ 'response' => 400 ] );
  }
  ////////////////////////////////////////////////////////////


  if ( $is_complete !== false ) {

    if ( $order->needs_payment() ) {
      $order->payment_complete( $process_id );
      $order->add_order_note(
        "Pago confirmado por BHD. Transaction ID: {$process_id} State: {$transaction_state}",
        true
      );
    }

    wp_redirect( $order->get_checkout_order_received_url() );
    exit;
  }

  if ( $is_cancel !== false ) {
    $order->update_status(
      'cancelled',
      'Pago cancelado por el usuario en la pasarela BHD.'
    );

    wp_redirect( wc_get_page_permalink( 'cart' ) );
    exit;
  }

  wp_die( 'Acción inválida', 'Error', [ 'response' => 404 ] );
}
/////////////////////////////////// WEB HOOKS ///////////////////////////////////



// register_activation_hook( __FILE__, function () {
//   mbsoft_add_rewrite_rules();
//   flush_rewrite_rules();
// });

// register_deactivation_hook( __FILE__, function () {
//   flush_rewrite_rules();
// });

