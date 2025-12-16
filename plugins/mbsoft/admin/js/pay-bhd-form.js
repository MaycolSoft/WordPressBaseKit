
jQuery(document).ready(function ($) {

  /* -------------------------------------------
  * 1. Lógica del Toggle (Mostrar/Ocultar API Secret)
  * ------------------------------------------- */
  const secretInput = $('#bhd_api_secret');
  const toggleButton = $('.pay-button-bhd-form-toggle-btn');
  const toggleIcon = $('#toggle-icon');

  toggleButton.on('click', function () {
    if (secretInput.attr('type') === 'password') {
      secretInput.attr('type', 'text');
      toggleIcon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
      toggleButton.attr('aria-label', 'Ocultar API Secret');
    } else {
      secretInput.attr('type', 'password');
      toggleIcon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
      toggleButton.attr('aria-label', 'Mostrar API Secret');
    }
  });


  /* -------------------------------------------
  * 2. Manejo del Formulario con AJAX (Usando $.post como solicitaste)
  * ------------------------------------------- */
  const form = $('#bhd-settings-form');
  const submitBtn = $('#bhd-settings-save-btn');
  const statusMessage = $('#bhd-settings-message');

  const nonceValue = form.find('#mbsoft_pay_bhd_button_nonce').val();
  const actionValue = 'handle_bhd_float_button_settings_save'

  form.on('submit', function (e) {
    e.preventDefault(); // Detener el envío estándar del formulario

    // 1. Mostrar cargando y deshabilitar botón
    submitBtn.attr('disabled', true).text('Guardando...');
    statusMessage.removeClass('pay-button-bhd-form-status-success pay-button-bhd-form-status-error').hide();

    // 2. Recolectar datos del formulario
    let data = form.serializeArray();

    // 3. Agregar los datos obligatorios de AJAX (action y nonce)
    // Convertimos el array serializado a un objeto para agregar las claves de AJAX
    let postData = {};
    $.each(data, function (index, field) {
      postData[field.name] = field.value;
    });

    postData.action = actionValue;
    postData.nonce = nonceValue;


    const feedbackText = "Guardando...";
    const $feedback = showFeedback(feedbackText);

    // 4. Llamada AJAX usando $.post
    $.post(ajaxurl, postData)
      .done(function (response) {
        if (response.success) {
          $feedback.text('✅ Guardado con éxito');
          $feedback.css('background', '#46b450'); // Color verde para éxito
        } else {
          $feedback.text('❌ Error: ' + (response.data.message || 'Desconocido'));
          $feedback.css('background', '#d63638'); // Color rojo para error
        }

      }).fail(function () {
        $feedback.text('❌ Error de conexión con el servidor.');
        $feedback.css('background', '#d63638');
      }).always(function () {
        // Habilitar el switch después de 1 segundo y remover el feedback
        setTimeout(function () {
          $feedback.fadeOut(500, function () { $(this).remove(); });
        }, 1000);

        submitBtn.attr('disabled', false).text('Guardar');
      });
  });


  function showFeedback(text) {
    $('.mbsoft-saving-feedback').remove(); // Limpiar feedback anterior
    const $feedback = $('<div class="mbsoft-saving-feedback"></div>');

    $feedback.css({
      'position': 'fixed',
      'top': '20px',
      'right': '20px',
      'background': '#2271b1',
      'color': 'white',
      'padding': '10px 20px',
      'border-radius': '4px',
      'box-shadow': '0 2px 5px rgba(0,0,0,0.2)',
      'zIndex': 9999
    }).text(text);

    $('body').append($feedback);

    // Set Timer to remove feedback after 3 seconds
    setTimeout(function () {
      $feedback.fadeOut(500, function () { $(this).remove(); });
    }, 3000);
    return $feedback;
  }













  const bhd_pay_form = $('#bhd-pay-form');

  bhd_pay_form.on('submit', function (e) {
    e.preventDefault();

    const amount   = $('#bhd_amount').val();
    const tax      = $('#bhd_tax').val();
    const currency = $('#bhd_currency_pay_button').val();
    const nonce    = $('#mbsoft_bhd_pay_form_nonce').val();

    if (!amount || amount <= 0) {
      alert('Monto inválido');
      return;
    }

    const submitBtn = bhd_pay_form.find('.bhd-pay-submit');
    submitBtn.prop('disabled', true).text('Procesando...');

    $.post(ajaxurl, {
      action: 'mbsoft_bhd_create_payment',
      nonce: nonce,
      amount: amount,
      tax: tax,
      currency: currency
    })
    .done(function (response) {

      if (!response.success) {
        alert(response.data?.message || 'Error inesperado');
        return;
      }

      const { redirect_url, jwt } = response.data;

      if (!redirect_url || !jwt) {
        alert('Respuesta inválida del banco');
        return;
      }

      const url = redirect_url + '' + encodeURIComponent(jwt);
      window.open(url, '_blank', 'noopener,noreferrer');

    })
    .fail(function () {
      alert('Error de conexión con el servidor');
    })
    .always(function () {
      submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-cart"></span> Pagar con BHD');
    });
  });



  const bhd_wc_settings_form = $('#bhd-wc-settings-form');
  bhd_wc_settings_form.on('submit', function (e) {
    e.preventDefault();

    const wcData = {
      wc_enabled: $('#bhd_wc_enabled').is(':checked') ? 'yes' : 'no', 
      method_title: $('#bhd_wc_title').val(),
      method_description: $('#bhd_wc_description').val(),
      nonce: $('#mbsoft_bhd_wc_settings_form_nonce').val()
    };

    const submitBtn = $('#bhd-wc-settings-save-btn');
    submitBtn.prop('disabled', true).text('Guardando...');

    $.post(ajaxurl, {
      action: 'mbsoft_bhd_save_wc_settings',
      ...wcData
    })
    .done(function (response) {
      if (response.success) {
        showFeedback('Ajustes de WooCommerce guardados con éxito').css('background', '#46b450');
      }
      else {
        showFeedback('Error al guardar ajustes de WooCommerce').css('background', '#d63638');
      }
    })
    .fail(function () {
      showFeedback('Error de conexión con el servidor').css('background', '#d63638');
    })
    .always(function () {
      submitBtn.prop('disabled', false).text('Guardar Ajustes de WooCommerce');
    });

  });

});



