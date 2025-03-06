<?php
/*
Plugin Name: MB Soft
Plugin URI: http://mbsoft.com/mbsoft-plugin-wordpress
Description: Plugin con una landing page pública y un dashboard de administración.
Version: 1.0
Author: MB
Author URI: http://mbsoft.com
License: GPL2
*/

// Definir constantes para las rutas del plugin
define('MBSOFT_THEME_DIR', get_theme_root() . '/mbsoft-theme');
define('MBSOFT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MBSOFT_PLUGIN_URL', plugin_dir_url(__FILE__));


require_once 'functions.php';


// Incluir funcionalidades de administración si estamos en el área de admin
if (is_admin()) {
  require_once MBSOFT_PLUGIN_DIR . 'admin/admin-dashboard.php';
}




add_shortcode('mbsoft_landing_page', 'mbsoft_shortcode_landing');






function mbsoft_api_ajax() {
  if(isset($_POST['view'])) {
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

    wp_die();
  }


  require_once MBSOFT_PLUGIN_DIR . 'sub_system/tabla.php';
  wp_die();
}

add_action('wp_ajax_mbsoft_api_ajax', 'mbsoft_api_ajax');
// add_action('wp_ajax_nopriv_mbsoft_api_ajax', 'mbsoft_api_ajax');



