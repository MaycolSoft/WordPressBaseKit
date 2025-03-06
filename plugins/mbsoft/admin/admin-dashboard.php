<?php

// Agregar el menú en el admin
function mpa_add_admin_menu()
{
  add_menu_page(
    "MB Soft Plugin",             // Título de la página
    'MB Soft',                    // Título del menú
    'manage_options',             // Capacidad requerida
    'mbsoft',                     // Slug del menú
    'mpa_display_admin_dashboard', // Función que muestra la página
    'dashicons-admin-generic',   // Icono del menú
    6                            // Posición
  );
}

add_action('admin_menu', 'mpa_add_admin_menu');



// Función que muestra el contenido del dashboard
function mpa_display_admin_dashboard()
{
  ?>
  <div class="wrap">
    <h1>MB Soft Plugin</h1>
    <p>Aquí puedes configurar los ajustes de Mi Plugin.</p>
    <?php include MBSOFT_PLUGIN_DIR . 'admin/templates/admin-dashboard.php'; ?>
  </div>
  <?php
}


// Procesar la acción cuando se presiona el botón
if (isset($_POST['mpa_theme_action'])) {
  if ($_POST['mpa_theme_action'] === "install") {
    mpa_install_theme();
  } elseif ($_POST['mpa_theme_action'] === "activate") {
    mpa_activate_theme();
  } elseif ($_POST['mpa_theme_action'] === "uninstall") {
    mpa_uninstall_theme();
  }

  // Recargar la página para actualizar el estado del botón
  echo '<meta http-equiv="refresh" content="0">';
}
?>