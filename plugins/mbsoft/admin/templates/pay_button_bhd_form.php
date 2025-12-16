


<?php

  // CSS
  wp_enqueue_style(
      'mbsoft-pay-bhd-form',
      MBSOFT_PLUGIN_URL . 'admin/css/pay-bhd-form.css',
      [],
      '1.0.1'
  );

  // JS
  wp_enqueue_script(
      'mbsoft-pay-bhd-form',
      MBSOFT_PLUGIN_URL . 'admin/js/pay-bhd-form.js',
      ['jquery'],
      '1.0',
      true
  );
?>







<?php

  $feature_code = 'pay_bhd_float_button';
  
  // Condición principal
  if (!is_feature_active($feature_code)) {
      echo '<div class="bhd-settings-container notice notice-warning"><p>La funcionalidad "Botón de Pago BHD" no está activada. Actívela para aplicar las configuraciones.</p></div>';
      return;
  }

  // Obtener las configuraciones actuales
  $settings = get_feature_settings($feature_code) ?? [
    'api_key' => '', 
    'api_secret' => '', 
    'merchant_name' => 'Mi Tienda', 
    'merchant_id' => '', 
    'currency' => 'DOP'
  ];

  
  // Variables para el formulario
  $api_key = esc_attr($settings['api_key'] ?? '');
  $api_secret = esc_attr($settings['api_secret'] ?? '');
  $merchant_name = esc_attr($settings['merchant_name'] ?? '');
  $merchant_id = esc_attr($settings['merchant_id'] ?? '');
  $currency = esc_attr($settings['currency'] ?? 'DOP');
  $returned_url = esc_attr($settings['returned_url'] ?? home_url()   . '/verify-order/complete' );
  $cancelled_url = esc_attr($settings['cancelled_url'] ?? home_url() . '/verify-order/cancel' );
?>



<div style="display: flex; gap: 20px; flex-flow: wrap;">

  <div class="pay-button-bhd-form-container" style="flex-grow:2">
    <h2>
      <span class="dashicons dashicons-money"></span>
      Configuración de Pago BHD Flotante
    </h2>
    <form id="bhd-settings-form" method="post" class="pay-button-bhd-form-modern-form">

      <?php wp_nonce_field('bhd_settings_form_nonce', 'mbsoft_pay_bhd_button_nonce'); ?>

      <div class="pay-button-bhd-form-group">
          <label for="bhd_api_key">
              <span class="dashicons dashicons-lock"></span> 
              API Key
          </label>
          <input type="text" id="bhd_api_key" name="api_key" value="<?php echo $api_key; ?>" placeholder="Ingresa la API Key" required>
      </div>
      
      <div class="pay-button-bhd-form-group pay-button-bhd-form-has-toggle">
          <label for="bhd_api_secret">
              <span class="dashicons dashicons-hidden"></span> 
              API Secret
          </label>
          <input type="password" id="bhd_api_secret" name="api_secret" value="<?php echo $api_secret; ?>" placeholder="Ingresa el API Secret" required>
          <button type="button" class="pay-button-bhd-form-toggle-btn" aria-label="Mostrar API Secret">
              <span class="dashicons dashicons-visibility" id="toggle-icon"></span>
          </button>
      </div>

      <div class="pay-button-bhd-form-group">
          <label for="bhd_merchant_name">
              <span class="dashicons dashicons-admin-home"></span>
              Nombre del Comercio
          </label>
          <input type="text" id="bhd_merchant_name" name="merchant_name" value="<?php echo $merchant_name; ?>" placeholder="Ej: Mi Tienda Online" required>
      </div>

      
      <div class="pay-button-bhd-form-group">
          <label for="bhd_returned_url">
            <span class="dashicons dashicons-external"></span> Returned URL
          </label>
          <input type="text" id="bhd_returned_url" name="returned_url" value="<?php echo $returned_url; ?>" placeholder="URL a la que se redirige tras el pago exitoso" required>
      </div>

      <div class="pay-button-bhd-form-group">
          <label for="bhd_cancelled_url">
            <span class="dashicons dashicons-dismiss"></span> Cancelled URL
          </label>
          <input type="text" id="bhd_cancelled_url" name="cancelled_url" value="<?php echo $cancelled_url; ?>" placeholder="URL a la que se redirige si el pago es cancelado" required>
      </div>

      <div class="pay-button-bhd-form-group">
          <label for="bhd_merchant_id">
              <span class="dashicons dashicons-tag"></span>
              Merchant ID
          </label>
          <input type="text" id="bhd_merchant_id" name="merchant_id" value="<?php echo $merchant_id; ?>" placeholder="ID del comercio asignado" required>
      </div>

      <div class="pay-button-bhd-form-group">
          <label for="bhd_currency">
              <span class="dashicons dashicons-image-filter"></span>
              Moneda (Currency)
          </label>
          <select id="bhd_currency" name="currency" required>
              <option value="DOP" <?php selected($currency, 'DOP'); ?>>Pesos Dominicanos (DOP)</option>
              <option value="USD" <?php selected($currency, 'USD'); ?>>Dólar Americano (USD)</option>
          </select>
          <span class="pay-button-bhd-form-help-text">Moneda por defecto para el pago.</span>
      </div>
      
      <div id="bhd-settings-message" class="pay-button-bhd-form-status-message"></div>

      <button type="submit" id="bhd-settings-save-btn" class="pay-button-bhd-form-submit-button">
          <span class="dashicons dashicons-saved"></span>
          Guardar Configuraciones
      </button>
    </form>
  </div>




  <div class="pay-button-bhd-form-container" style="flex-grow:1; max-width: 400px;">

    <h2>
      <span class="dashicons dashicons-admin-site-alt3"></span>
      Ajustes de WooCommerce Checkout
    </h2>
    <p class="pay-button-bhd-form-help-text">
      Estos campos controlan cómo aparece el método de pago BHD en el carrito y la página de pago de WooCommerce.
    </p>

    <?php
      $wc_settings = $settings['wc_payment_gateway'] ?? [];
      $wc_enabled = esc_attr($wc_settings['enabled'] ?? 'no');
      $wc_title = esc_attr($wc_settings['method_title'] ?? 'Pago BHD');
      $wc_description = esc_attr($wc_settings['method_description'] ?? 'Paga directamente con tu cuenta BHD.');
    ?>

    <form id="bhd-wc-settings-form" class="pay-button-bhd-form-modern-form">
      <?php wp_nonce_field('mbsoft_bhd_wc_settings_nonce', 'mbsoft_bhd_wc_settings_form_nonce'); ?>
      <div class="pay-button-bhd-form-group pay-button-bhd-form-checkbox-group">
        <input type="checkbox" id="bhd_wc_enabled" name="wc_enabled" value="yes" <?php checked($wc_enabled, 'yes'); ?>>
        <label for="bhd_wc_enabled">
          Habilitar Pago BHD en Checkout
        </label>
      </div>

      <div class="pay-button-bhd-form-group">
        <label for="bhd_wc_title">
          <span class="dashicons dashicons-welcome-write-blog"></span> Título en Checkout
        </label>
        <input type="text" id="bhd_wc_title" name="wc_title" value="<?php echo $wc_title; ?>" placeholder="Ej: Pagar con BHD" required>
      </div>

      <div class="pay-button-bhd-form-group">
        <label for="bhd_wc_description">
          <span class="dashicons dashicons-editor-ul"></span> Descripción en Checkout
        </label>
        <textarea class="pay-button-bhd-form-textarea" id="bhd_wc_description" name="wc_description" rows="3" placeholder="Descripción bajo el título..."><?php echo $wc_description; ?></textarea>
      </div>

      <!-- Button -->
      <button type="submit" id="bhd-wc-settings-save-btn" class="pay-button-bhd-form-submit-button">
        <span class="dashicons dashicons-saved"></span>
        Guardar Ajustes de WooCommerce
      </button>
    </form>

    <div class="pay-button-bhd-form-container">
      <h2>
        <span class="dashicons dashicons-cart"></span>
        Probar Pago BHD
      </h2>
      <form id="bhd-pay-form">
        <?php wp_nonce_field('mbsoft_bhd_pay_nonce', 'mbsoft_bhd_pay_form_nonce'); ?>


        <div class="bhd-form-group">
          <label for="bhd_amount">Monto</label>
          <span class="dashicons dashicons-money"></span>
          <input type="number" id="bhd_amount" name="amount" step="0.01" required value="100.00">
        </div>

        <div class="bhd-form-group">
          <label for="bhd_tax">Impuesto</label>
          <span class="dashicons dashicons-chart-bar"></span>
          <input type="number" id="bhd_tax" name="tax" step="0.01" required value="18.00">
        </div>


        <div class="bhd-form-group">
          <label for="bhd_currency_pay_button">Moneda</label>
          <span class="dashicons dashicons-admin-site-alt3"></span>
          <select id="bhd_currency_pay_button" name="currency">
            <option value="">Usar moneda por defecto</option>
            <option value="RD">RD</option>
            <option value="USD">USD</option>
          </select>
        </div>

        <button type="submit" class="bhd-pay-submit">
          <span class="dashicons dashicons-cart"></span>
          Pagar con BHD
        </button>
      </form>
    </div>

  </div>

</div>







