<?php
require_once MBSOFT_PLUGIN_DIR . 'includes/database-functions.php';


function is_feature_active($feature_code)
{
  $db = connecttodatabase();

  $stmt = $db->prepare("SELECT is_active FROM features WHERE feature_code = :code");
  $stmt->bindValue(':code', $feature_code, SQLITE3_TEXT);
  $result = $stmt->execute();

  $row = $result->fetchArray(SQLITE3_ASSOC);
  $db->close();

  return ($row && $row['is_active'] == 1);
}


/**
 * Obtiene las configuraciones de una feature.
 * @param string $feature_code El código de la feature.
 * @return array|null Retorna un array asociativo con las configuraciones o null si falla.
 */
function get_feature_settings($feature_code)
{
  try {
    $db = connecttodatabase();
    $stmt = $db->prepare("SELECT settings FROM features WHERE feature_code = :code");
    $stmt->bindValue(':code', $feature_code, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if ($row && $row['settings']) {
        return json_decode($row['settings'], true);
    }
    return null;
  } catch (\Throwable $e) {
    // Loggear o manejar el error
    return null;
  }
}

/**
 * Guarda las nuevas configuraciones (JSON) para una feature.
 * @param string $feature_code El código de la feature.
 * @param array $settings_data Array asociativo con los nuevos datos de configuración.
 * @return bool True si se guarda correctamente, False en caso contrario.
 */
function save_feature_settings($feature_code, array $settings_data)
{
  try {
    $db = connecttodatabase();

    // 1. Obtener la configuración actual para preservar campos no enviados
    $current_settings = get_feature_settings($feature_code);

    // 2. Combinar (Merge) los datos, dando prioridad a los nuevos
    if ($current_settings) {
        $new_settings = array_merge($current_settings, $settings_data);
    } else {
        $new_settings = $settings_data;
    }

    $json_settings = json_encode($new_settings, JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("
        UPDATE features 
        SET settings = :settings_json, updated_at = datetime('now') 
        WHERE feature_code = :code
    ");
    $stmt->bindValue(':settings_json', $json_settings, SQLITE3_TEXT);
    $stmt->bindValue(':code', $feature_code, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    $db->close();

    return $result !== false;
  } catch (\Throwable $e) {
    // Loggear o manejar el error
    return false;
  }
}









/////////////////////////////////// SHORTCODE ////////////////////////////
// Agregar un shortcode para mostrar la landing page pública
function mbsoft_shortcode_landing()
{
  ob_start();
  include MBSOFT_PLUGIN_DIR . 'public/landing-page.php';
  return ob_get_clean();
}

add_shortcode('mbsoft_landing_page', 'mbsoft_shortcode_landing');
/////////////////////////////////// SHORTCODE ////////////////////////////




/////////////////////////////////// API MBSOFT ////////////////////////////
function mbsoft_api_ajax()
{
  if (isset($_POST['view'])) {
    try {
      //code...

      $view = $_POST['view'];

      $file = MBSOFT_PLUGIN_DIR . "sub_system/$view.php";

      if (file_exists($file)) {
        ob_start();       // Inicia el buffer de salida
        include $file;    // Ejecuta el archivo PHP
        $content = ob_get_clean(); // Captura el contenido generado
        echo $content;
      } else {
        echo "Error: Archivo no encontrado.";
      }
    } catch (\Throwable $th) {
      echo "ERROR cargando la vista: " . $th->getMessage();
    } finally {
      wp_die();
    }
  }

  if (isset($_POST['file']) && $_POST['file'] === 'createOrUpdateProduct') {
    try {
      require_once MBSOFT_PLUGIN_DIR . 'sub_system/createOrUpdateProduct.php';
    } catch (\Throwable $th) {
      echo "ERROR cargando la vista: " . $th->getMessage();
    } finally {
      wp_die();
    }
  }


  // Lógica de Manejo del Botón BHD (Igual que antes)
  if (isset($_POST['action_type']) && $_POST['action_type'] === 'bhd_track_click') {
    require_once MBSOFT_PLUGIN_DIR . 'sub_system/BHDPayButton.php';
    wp_die();
  }
  // Fin Lógica BHD


  require_once MBSOFT_PLUGIN_DIR . 'sub_system/tabla.php';
  wp_die();
}
add_action('wp_ajax_mbsoft_api_ajax', 'mbsoft_api_ajax');
/////////////////////////////////// API MBSOFT ////////////////////////////





/////////////////////////////////// FEATURES FLAGS ////////////////////////////
function mbsoft_toggle_feature_ajax() {
  // 1. Verificación de Seguridad y Permisos
  if ( ! isset( $_POST['feature_code'], $_POST['is_active'], $_POST['nonce'] ) || ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( array( 'message' => 'Faltan parámetros o no tienes permisos.' ) );
      wp_die();
  }
  
  // 2. Verificación de Nonce (Seguridad)
  if ( ! wp_verify_nonce( $_POST['nonce'], 'mbsoft_features_update' ) ) {
      wp_send_json_error( array( 'message' => 'Verificación de seguridad fallida.' ) );
      wp_die();
  }

  // Limpieza y Validación de Datos
  $feature_code = sanitize_key( $_POST['feature_code'] );
  $is_active    = intval( $_POST['is_active'] ); // 1 o 0

  if ( ! preg_match( '/^[a-z0-9_]+$/', $feature_code ) ) {
      wp_send_json_error( array( 'message' => 'Código de feature inválido.' ) );
      wp_die();
  }
  
  // 3. Conexión y Actualización a la Base de Datos
  require_once MBSOFT_PLUGIN_DIR . 'includes/database-functions.php';
  $db = connecttodatabase();
  
  try {
      $stmt = $db->prepare( "UPDATE features SET is_active = :state WHERE feature_code = :code" );
      $stmt->bindValue( ':state', $is_active, SQLITE3_INTEGER );
      $stmt->bindValue( ':code', $feature_code, SQLITE3_TEXT );
      
      $result = $stmt->execute();
      
      if ( $result ) {
          // Éxito
          wp_send_json_success( array( 
              'message' => "Feature $feature_code actualizado con exito.",
              'state' => $is_active
          ) );
      } else {
          // Error al ejecutar la consulta
          wp_send_json_error( array( 'message' => 'Error al actualizar la base de datos.' ) );
      }
      
  } catch (\Throwable $th) {
      wp_send_json_error( array( 'message' => 'Excepción de DB: ' . $th->getMessage() ) );
  } finally {
      $db->close();
  }

  wp_die();
}

add_action('wp_ajax_mbsoft_toggle_feature', 'mbsoft_toggle_feature_ajax');
// add_action('wp_ajax_nopriv_mbsoft_api_ajax', 'mbsoft_api_ajax'); // Agregado nopriv para usuarios no logueados
/////////////////////////////////// FEATURES FLAGS ////////////////////////////












// function mbsoft_custom_script() {
//   wp_enqueue_script(
//     'mbsoft-javascript',  
//     plugin_dir_url( __FILE__ ) . '/js/database.js',
//     [], 
//     true
//   );
//   // wp_enqueue_style('mbsoft-style', get_template_directory_uri() . '/style.css');
// }

// add_action('wp_enqueue_scripts', 'mbsoft_custom_script');









if (is_feature_active('custom_login')) {
  // Logo personalizado en login
  add_action("login_head", function ($atts) {
    $logo_url = 'https://supertiendachina.com.do/wp-content/uploads/2023/09/cropped-LOGO-WEB-PAGE.png';
    echo "
      <style>
        body.login #login h1 a {
            background-image: url('$logo_url');
            background-size: contain; /* Mejor que background-repeat */
            height: 135px;
            width: auto; /* Permite el ancho de la imagen */
        }
      </style>
      ";
    return;
  });

}


if (is_feature_active('hide_no_image_products')) {
    
  add_action('woocommerce_product_query', function($query){
    if (function_exists('is_woocommerce') && is_woocommerce()) {
      $query->set('meta_query', [[
        'key' => '_thumbnail_id',
        'value' => '0',
        'compare' => '>',
      ]]);
    }

  });


  // Agrega un filtro para modificar la consulta de productos en el shortcode [products]
  add_filter('woocommerce_shortcode_products_query', function($query_args, $attributes, $shortcode){
    // Agrega una condición para buscar solo productos con imágenes
    $query_args['meta_query'][] = array(
      'key' => '_thumbnail_id',
      'compare' => 'EXISTS',
    );

    return $query_args;
  }, 10, 3);
  
}


if (is_feature_active('custom_shortcode')) {

  function custom_sale_products_shortcode($atts) {
    // Atributos predeterminados del shortcode
    $atts = shortcode_atts(array(
        'per_page' => '12',      // Número de productos a mostrar
        'columns' => '4',        // Número de columnas
        'orderby' => 'date',     // Ordenar por fecha
        'order' => 'DESC',       // Orden descendente (los más recientes primero)
    ), $atts, 'custom_sale_products');

    // Consulta para obtener productos en oferta
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $atts['per_page'],
        'orderby'        => $atts['orderby'],
        'order'          => $atts['order'],
        'meta_query'     => array(
            array(
                'key'     => '_sale_price',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ),
        ),
    );

    $products = new WP_Query($args);

    // Si hay productos en oferta
    if ($products->have_posts()) {
        // Inicia la salida de HTML
        $output = '<div class="sale-products">';
        $output .= '<h2>¡Productos en Oferta!</h2>';
        $output .= '<ul class="products columns-' . esc_attr($atts['columns']) . '">';

        // Loop para mostrar los productos
        while ($products->have_posts()) {
            $products->the_post();
            $output .= wc_get_template_part('content', 'product');
        }

        $output .= '</ul>';
        $output .= '</div>';

        // Restaura el loop original de WordPress
        wp_reset_postdata();

        return $output;
    } else {
        // Si no hay productos en oferta, muestra un mensaje amigable
        return '<div class="no-sale-products">' .
               '<h2>¡Vuelve Pronto!</h2>' .
               '<p>Actualmente no tenemos productos en oferta, pero estamos preparando algo especial para ti. ¡No te lo pierdas!</p>' .
               '</div>';
    }
  }

  add_shortcode('sale_products', 'custom_sale_products_shortcode');
}


if (is_feature_active('custom_site_icon')) {

    /**
     * Filtra la URL del Site Icon (favicon) para usar una URL personalizada.
     *
     * @param string $url URL del Site Icon (por defecto o configurada).
     * @param int $size Tamaño solicitado del icono.
     * @param int $blog_id ID del sitio (solo relevante en multisitio).
     * @return string La nueva URL personalizada.
     */
    add_filter('site_icon_url', function ($url, $size, $blog_id) {
        
        // Define la URL de tu icono personalizado
        $custom_icon_url = 'https://supertiendachina.com.do/wp-content/uploads/2023/09/cropped-LOGO-WEB-PAGE.png'; 
        
        // Es una buena práctica verificar si se necesita un tamaño específico, 
        // aunque muchos navegadores funcionan bien con la URL base.
        // Si tu imagen es lo suficientemente grande (p. ej., 512x512), 
        // simplemente devolvemos la URL base.
        
        return $custom_icon_url;

    }, 10, 3);
}




if (is_feature_active('pay_bhd_float_button')) {
  require_once MBSOFT_PLUGIN_DIR . 'includes/bhd_payment_gateway.php';
}


