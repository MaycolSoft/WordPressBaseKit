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



// Agregar un shortcode para mostrar la landing page pública
function mbsoft_shortcode_landing()
{
  ob_start();
  include MBSOFT_PLUGIN_DIR . 'public/landing-page.php';
  return ob_get_clean();
}

add_shortcode('mbsoft_landing_page', 'mbsoft_shortcode_landing');





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
add_action('wp_ajax_nopriv_mbsoft_api_ajax', 'mbsoft_api_ajax'); // Agregado nopriv para usuarios no logueados




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
    echo "
      <style>
          body.login #login h1 a {
              background: url('https://supertiendachina.com.do/wp-content/uploads/2023/09/cropped-LOGO-WEB-PAGE.png') no-repeat scroll center top transparent;
              height: 135px;
              width: unset;
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



if (is_feature_active('pay_bhd_float_button')) {

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

    // Registra el shortcode
    add_shortcode('pay_bhd_float_button', 'pay_bhd_float_button_shortcode');

    // Opcional: Para asegurar que el CSS se cargue en el frontend si el shortcode no se usa en el cuerpo de una página
    /*
    function enqueue_pay_bhd_float_button_styles() {
        if (has_shortcode(get_post_field('post_content', get_the_ID()), 'pay_bhd_float_button')) {
            wp_enqueue_style('montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@700&display=swap', array(), null);
            // Si el CSS estuviera en un archivo separado, lo encolarías aquí
        }
    }
    add_action('wp_enqueue_scripts', 'enqueue_pay_bhd_float_button_styles');
    */


    // **PARTE 2: ENCOLAR SCRIPT (DEBE IR EN UN ARCHIVO JS REAL)**
    /**
     * Encola el script JavaScript y localiza variables.
     */
    function pay_bhd_float_button_scripts() {
        // Encolamos el script (asumiendo que está en una carpeta 'js' de tu plugin/tema)
        wp_enqueue_script('bhd-button-tracker', get_template_directory_uri() . '/js/bhd-button-tracker.js', array('jquery'), null, true);

        // Localizamos las variables para que el JS sepa la URL de AJAX y el nonce.
        wp_localize_script('bhd-button-tracker', 'bhd_ajax_object', array(
            // Usamos la acción AJAX que ya tienes
            'ajax_action' => 'mbsoft_api_ajax',
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('bhd_click_nonce') // Nonce de seguridad
        ));
    }
    add_action('wp_enqueue_scripts', 'pay_bhd_float_button_scripts');
}
