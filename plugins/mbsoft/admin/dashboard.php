

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
  ?>

  <nav class="mbsoft-tabs">
    <?php foreach ($tabs as $tab_key => $tab_name): ?>
      <a href="<?php echo esc_url(add_query_arg('tab', $tab_key)); ?>"
        class="mbsoft-tab <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
        <?php echo esc_html($tab_name); ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mbsoft-tab-content">
    <?php
    try {
      //code...
      switch ($current_tab) {
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

  /* Estilos del botón */
  #toggle-content {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    padding: 10px 20px;
    background-color: #2271b1;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }

  #toggle-content:hover {
    background-color: #135e96;
  }
</style>

<script>
  jQuery(document).ready(function ($) {
    // Crear el botón
    const $button = $('<button id="toggle-content">Ocultar Otros</button>').appendTo('body');

    // Verificar el estado guardado en localStorage
    const isHidden = localStorage.getItem('mbsoftHideOthers') === 'true';

    // Aplicar el estado inicial
    if (isHidden) {
      $('#wpbody-content').addClass('hide-others');
      $button.text('Mostrar Otros');
    } else {
      $('#wpbody-content').removeClass('hide-others');
      $button.text('Ocultar Otros');
    }

    // Alternar visibilidad
    $button.click(function () {
      const $wpbodyContent = $('#wpbody-content');
      $wpbodyContent.toggleClass('hide-others');

      // Guardar el estado en localStorage
      const isNowHidden = $wpbodyContent.hasClass('hide-others');
      localStorage.setItem('mbsoftHideOthers', isNowHidden);

      // Cambiar texto del botón
      if (isNowHidden) {
        $button.text('Mostrar Otros');
      } else {
        $button.text('Ocultar Otros');
      }
    });
  });
</script>


