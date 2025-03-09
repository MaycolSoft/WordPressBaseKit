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
    require_once MBSOFT_PLUGIN_DIR . 'sub_system/createOrUpdateProduct.php';
    wp_die();
  }

  require_once MBSOFT_PLUGIN_DIR . 'sub_system/tabla.php';
  wp_die();
}

add_action('wp_ajax_mbsoft_api_ajax', 'mbsoft_api_ajax');
// add_action('wp_ajax_nopriv_mbsoft_api_ajax', 'mbsoft_api_ajax');




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

  
  // Agrega un nuevo shortcode personalizado llamado 'custom_short_code_supert_tienda_china'
  function custom_sale_products_shortcode($atts)
  {
    return "<h2>MY SHORTCUT</h2>";

    // $atts = shortcode_atts(array(
    //   'per_page' => '12',
    //   'columns' => '4',
    //   'orderby' => 'title',
    //   'order' => 'ASC',
    //   'condition' => 'meta_key=custom_field&meta_value=your_value'
    // ), $atts, 'custom_short_code_supert_tienda_china');
  
    // // Parsea las condiciones adicionales
    // $condition = shortcode_parse_atts($atts['condition']);
  
    // // Realiza la consulta de productos con las condiciones adicionales
    // $products = new WP_Query(array(
    //   'post_type' => 'product',
    //   'posts_per_page' => $atts['per_page'],
    //   'meta_query' => array($condition),
    //   'orderby' => $atts['orderby'],
    //   'order' => $atts['order']
    // ));
  
    // // Comprueba si hay productos encontrados
    // if ($products->have_posts()) {
    //   // Inicia la salida de HTML
    //   $output = '<ul class="products columns-' . esc_attr($atts['columns']) . '">';
  
    //   // Loop through products and display them
    //   while ($products->have_posts()) {
    //     $products->the_post();
    //     $output .= wc_get_template_part('content', 'product');
    //   }
  
    //   // Finaliza la salida de HTML
    //   $output .= '</ul>';
  
    //   // Restaura el loop original de WordPress
    //   wp_reset_postdata();
  
    //   return $output;
    // }
  
    // return __('No products found', 'textdomain');
  }
  add_shortcode('custom_short_code_supert_tienda_china', 'custom_sale_products_shortcode');
}







