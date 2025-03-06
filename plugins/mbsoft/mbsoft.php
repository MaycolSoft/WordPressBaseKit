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

require_once 'functions.php';



// Definir constantes para las rutas del plugin
define('MBSOFT_THEME_DIR', get_theme_root() . '/mbsoft-theme');
define('MPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MPA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir funcionalidades de administración si estamos en el área de admin
if (is_admin()) {
  require_once MPA_PLUGIN_DIR . 'admin/admin-dashboard.php';
}



// Agregar un shortcode para mostrar la landing page pública
function mbsoft_shortcode_landinga()
{
    ob_start();
    include MPA_PLUGIN_DIR . 'public/landing-page.php';
    return ob_get_clean();
}



add_shortcode('mbsoft_landing_page', 'mbsoft_shortcode_landinga');
