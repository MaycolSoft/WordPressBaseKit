<div>
  <h2>Configuración del Plugin</h2>

  <?php
  // Verificar si el tema ya está instalado y/o activado
  $theme_installed = file_exists(MBSOFT_THEME_DIR);
  $current_theme = wp_get_theme();
  $is_active = ($current_theme->get('TextDomain') == 'my-theme');

  // Definir la acción y el texto del botón
  if (!$theme_installed) {
    $button_text = "Instalar Tema";
    $button_action = "install";
  } elseif (!$is_active) {
    $button_text = "Activar Tema";
    $button_action = "activate";
  } else {
    $button_text = "Desinstalar Tema";
    $button_action = "uninstall";
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


  <form method="post" action="options.php">
    <?php
    settings_fields('mpa_settings_group');
    do_settings_sections('mpa_settings_group');
    ?>
    <table class="form-table">
      <tr valign="top">
        <th scope="row">Opción 1</th>
        <td>
          <input type="text" name="mpa_option1" value="<?php echo esc_attr(get_option('mpa_option1')); ?>" />
        </td>
      </tr>
    </table>
    <?php submit_button(); ?>
  </form>

  <!-- Formulario con botón dinámico -->
  <!-- <form method="post">
    <input type="hidden" name="mpa_theme_action" value="<?php echo $button_action; ?>">
    <button type="submit" class="button-primary"><?php echo $button_text; ?></button>
  </form> -->
</div>