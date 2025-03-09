<?php

// Agregar el menú en el admin
function mbsoft_add_admin_menu()
{
  add_menu_page(
    "MB Soft Plugin",             // Título de la página
    'MB Soft',                    // Título del menú
    'manage_options',             // Capacidad requerida
    'mbsoft',                     // Slug del menú
    'mbsoft_display_admin_dashboard', // Función que muestra la página
    'dashicons-admin-generic',   // Icono del menú
    6                            // Posición
  );
}

add_action('admin_menu', 'mbsoft_add_admin_menu');


// Función que muestra el contenido del dashboard
function mbsoft_display_admin_dashboard() {
  // Verificar permisos
  if (!current_user_can('manage_options')) {
      wp_die(__('No tienes permisos para acceder a esta página.', 'mbsoft'));
  }
  
  // Incluir el template principal
  include MBSOFT_PLUGIN_DIR . 'admin/dashboard.php';
}
?>
