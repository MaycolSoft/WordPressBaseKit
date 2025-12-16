


<div class="mbsoft-plugin" style="margin-top: 1rem;">



<?php
  // Función para verificar si WooCommerce está activo
  if (!function_exists('is_woocommerce_active')) {
      function is_woocommerce_active() {
          if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
              return true;
          }
          if (is_multisite()) {
              $plugins = get_site_option('active_sitewide_plugins');
              if (isset($plugins['woocommerce/woocommerce.php'])) {
                  return true;
              }
          }
          return false;
      }
  }

  if (!is_woocommerce_active()) {
?>
  <div id="mbsoft-notice-woocommerce-overlay">
      <div id="mbsoft-notice-woocommerce-content">
          <h2 style="color: #d63638; margin-top: 0;">🚨 Requisito del Plugin</h2>
          <hr>
          <p style="font-size: 1.1em;">
              Para utilizar las funcionalidades de este plugin, **DEBES INSTALAR Y ACTIVAR WOOCOMMERCE**.
          </p>
          <p>
              Sin WooCommerce activo, la mayoría de las características de este dashboard no funcionarán correctamente.
          </p>
          <a href="<?php echo esc_url(admin_url('plugin-install.php?s=WooCommerce&tab=search&type=term')); ?>" 
            class="button button-primary button-hero" 
            style="margin-top: 15px;">
              Instalar WooCommerce Ahora
          </a>
          <p style="margin-top: 20px; font-size: 0.9em; color: #555;">
              Una vez instalado, refresca esta página para acceder al dashboard.
          </p>
      </div>
  </div>
<?php
    echo css_modal_woocommerce_plugin_only();
  }

  function css_modal_woocommerce_plugin_only() {
    return "
      <style>
      /* 1. Posicionamiento del contenedor principal */
      .mbsoft-plugin {
          position: relative !important; /* CRÍTICO: El contenedor padre debe ser relativo */
          /* Asegura que si el contenido es pequeño, el contenedor principal tiene altura para que el overlay lo cubra */
          min-height: 250px; 
      }

      /* 2. Estilos para el Overlay (prefijo: mbsoft-notice-woocommerce-overlay) */
      #mbsoft-notice-woocommerce-overlay {
          position: absolute; 
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(255, 255, 255, 0.95); 
          z-index: 10; 
          
          /* PROPIEDADES DE CENTRADO */
          display: flex;
          justify-content: center; /* Centrado horizontal */
          align-items: center;    /* Centrado vertical */
          
          text-align: center;
          border-radius: 4px; 
          pointer-events: all; /* Inhabilita clics en el contenido subyacente */
          flex-wrap: wrap;
      }

      /* 3. Estilos del Contenido del Modal (prefijo: mbsoft-notice-woocommerce-content) */
      #mbsoft-notice-woocommerce-content {
          background: #fff;
          padding: 30px;
          border-radius: 8px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
          max-width: 450px;
          width: 90%;
          z-index: 20; 
      }
      
      /* 4. Estilos Opcionales para Atenuar el Contenido Interno */
      .mbsoft-notice-woocommerce-active-.mbsoft-tabs, 
      .mbsoft-notice-woocommerce-active-.mbsoft-tab-content {
        filter: blur(1px);
        pointer-events: none; /* Asegura que no se pueda hacer clic ni si el filtro falla */
      }
          </style>
    ";
  }

?>


<?php
  $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'features';
  $tabs = [
    'features' => 'Funcionalidades',
    'general' => 'Configuración General',
    'database' => 'Ajustes BD',
    'upload_file' => 'Subir Archivo Products',
  ];

  if (is_feature_active("pay_bhd_float_button")) {
    $tabs['pay_bhd_float_button'] = 'Botón de Pago BHD';
  }
?>

  <nav class="mbsoft-tabs">
    <?php foreach ($tabs as $tab_key => $tab_name): ?>
      <a href="<?php echo esc_url(add_query_arg('tab', $tab_key)); ?>"
        class="mbsoft-tab <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
        <?php echo esc_html($tab_name); ?>
      </a>
    <?php endforeach; ?>

    <div class="mbsoft-global-actions">
      <button id="toggle-content" class="button button-secondary button-small">Ocultar Otros</button>
      
      <button id="toggle-dark-mode" class="button button-secondary button-small">Modo Oscuro</button>
    </div>
  </nav>

  <div class="mbsoft-tab-content">
    <?php
    try {
      switch ($current_tab) {
        case 'pay_bhd_float_button':
          include_once MBSOFT_PLUGIN_DIR . 'admin/templates/pay_button_bhd_form.php';
          break;
        case 'upload_file':
          include_once MBSOFT_PLUGIN_DIR . 'admin/templates/upload_file.php';
          break;

        case 'general':
          include_once MBSOFT_PLUGIN_DIR . 'admin/templates/general.php';
          break;

        case 'database':
          include MBSOFT_PLUGIN_DIR . 'admin/templates/database_settings.php';
          break;

        default: // features
          include MBSOFT_PLUGIN_DIR . 'admin/templates/features.php';
          break;
      }
    } catch (\Throwable $th) {
      echo '<div class="notice notice-error"><p>Error: ' . $th->getMessage() . '</p></div>';
    }
    ?>
  </div>
</div>

<style>
  /* Estilos para ocultar elementos */
  #wpbody-content>*:not(.mbsoft-plugin) {
    transition: opacity 0.3s ease, transform 0.3s ease;
  }

  #wpbody-content.hide-others>*:not(.mbsoft-plugin) {
    opacity: 0 !important;
    transform: translateY(-20px) !important;
    pointer-events: none !important;
    height: 0 !important;
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  /* Estilos de las pestañas */
  .mbsoft-tabs {
    display: flex;
    border-bottom: 2px solid #ccd0d4;
    margin-bottom: 1.5rem;
  }

  .mbsoft-tab {
    padding: 12px 20px;
    text-decoration: none;
    color: #2271b1;
    border: 1px solid transparent;
    border-bottom: none;
    margin-bottom: -2px;
    transition: all 0.2s ease;
  }

  .mbsoft-tab:hover {
    background-color: #f6f7f7;
  }

  .mbsoft-tab.active {
    background: #fff;
    border-color: #ccd0d4;
    border-radius: 4px 4px 0 0;
    color: #1d2327;
    font-weight: 500;
  }

  .mbsoft-tab-content {
    background: #fff;
    padding: 1rem;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
  }

</style>










<script>
  jQuery(document).ready(function ($) {
    const $wpbodyContent = $('#wpbody-content');
    const $toggleContentButton = $('#toggle-content');
    const $toggleDarkModeButton = $('#toggle-dark-mode');
    const $body = $('body');

    // --- 1. Lógica Ocultar Otros ---
    function updateHideOthersState(isHidden) {
        $wpbodyContent.toggleClass('hide-others', isHidden);
        $toggleContentButton.text(isHidden ? 'Mostrar Otros' : 'Ocultar Otros');
        localStorage.setItem('mbsoftHideOthers', isHidden);
    }

    // Aplicar estado inicial Ocultar Otros
    const initialHideOthersState = localStorage.getItem('mbsoftHideOthers') === 'true';
    updateHideOthersState(initialHideOthersState);

    // Evento Ocultar Otros
    $toggleContentButton.click(function () {
        const isNowHidden = !$wpbodyContent.hasClass('hide-others');
        updateHideOthersState(isNowHidden);
    });

    
    // --- 2. Lógica Dark Mode ---
    function updateDarkModeState(isDark) {
        $body.toggleClass('dark-mode', isDark);
        $toggleDarkModeButton.text(isDark ? 'Modo Claro' : 'Modo Oscuro');
        localStorage.setItem('mbsoftDarkMode', isDark);
    }

    // Aplicar estado inicial Dark Mode
    const initialDarkModeState = localStorage.getItem('mbsoftDarkMode') === 'true';
    updateDarkModeState(initialDarkModeState);

    // Evento Dark Mode
    $toggleDarkModeButton.click(function () {
        const isNowDark = !$body.hasClass('dark-mode');
        updateDarkModeState(isNowDark);
    });

  });
</script>

<style>
  /* --- NUEVOS ESTILOS PARA EL WRAPPER Y ACCIONES --- */
  .mbsoft-controls-wrapper {
      display: flex; /* Habilita Flexbox */
      justify-content: space-between; /* Mueve los hijos (tabs y actions) a los extremos */
      align-items: flex-end; /* Alinea los elementos en la parte inferior (al nivel de la línea de las pestañas) */
      border-bottom: 2px solid #ccd0d4; /* Movemos el borde inferior aquí */
      margin-bottom: 1.5rem;
  }

  /* Ajusta los tabs para que no tengan el borde inferior */
  .mbsoft-tabs {
      display: flex;
      border-bottom: none; /* Quitamos el borde de aquí, ahora lo tiene el wrapper */
      margin-bottom: 0;
  }

  .mbsoft-global-actions {
      display: flex;
      gap: 8px; /* Espacio entre los nuevos botones */
      margin-bottom: 4px; /* Pequeño ajuste para alineación visual con la línea de las pestañas */
  }

  /* --- ESTILOS DARK MODE (Fácil) --- */
  /* Cambia el color de fondo y texto cuando el body tiene la clase dark-mode */
  body.dark-mode {
      background-color: #1e1e1e; /* Fondo oscuro principal */
      color: #f0f0f0; /* Texto claro */
  }

  body.dark-mode #wpbody-content {
      background-color: #1e1e1e;
  }

  /* Estilos de los contenedores de WordPress */
  body.dark-mode .wrap,
  body.dark-mode .mbsoft-tab-content,
  body.dark-mode .mbsoft-tab.active,
  body.dark-mode .notice {
      background: #252526; /* Contenedores más oscuros */
      border-color: #444;
      color: #f0f0f0;
  }

  /* Estilos de Pestañas y Enlaces en Dark Mode */
  body.dark-mode .mbsoft-controls-wrapper {
      border-bottom-color: #444;
  }

  body.dark-mode .mbsoft-tab {
      color: #6daaf1; /* Enlaces azules claros */
  }

  body.dark-mode .mbsoft-tab:hover {
      background-color: #333;
  }

  body.dark-mode .mbsoft-tab.active {
      background: #252526;
      border-color: #444;
      color: #f0f0f0;
  }
</style>

