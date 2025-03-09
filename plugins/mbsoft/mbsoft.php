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


require_once MBSOFT_PLUGIN_DIR . 'includes/functions.php';


// Incluir funcionalidades de administración si estamos en el área de admin
if (is_admin()) {
  require_once MBSOFT_PLUGIN_DIR . 'admin/index.php';
}





