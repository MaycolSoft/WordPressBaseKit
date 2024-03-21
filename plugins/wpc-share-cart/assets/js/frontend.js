'use strict';

(function($) {
  $(document).on('click touch', '.wpcss-checkbox-all', function() {
    $('.wpcss-checkbox-all').prop('checked', this.checked);
    $('.wpcss-checkbox').not(this).prop('checked', this.checked);
  });

  $(document).on('click touch', '.wpcss-popup-close', function() {
    $('.wpcss-area').removeClass('wpcss-area-show');
  });

  $(document).on('click touch', '.wpcss-area', function(e) {
    if ($(e.target).closest('.wpcss-popup').length === 0) {
      $('.wpcss-area').removeClass('wpcss-area-show');
    }
  });

  
  $(document).on('click touch', '.wpcss-btn', function(e) {
    e.preventDefault();
    var hash = $(this).data('hash');
    const whatsapp_number = $(this).data('phone_number');
    const extra_message = $(this).data('extra_message');
    

    $(this).removeClass('wpcss-added').addClass('wpcss-adding');

    var data = {
      action: 'wpcss_share',
      hash: hash,
      nonce: wpcss_vars.nonce,
      "return_only_url": 1
    };

    $.post(wpcss_vars.ajax_url, data, function(response) {

      // Tu fragmento de texto HTML
      var htmlString = response;

      // Crear un analizador DOM
      var parser = new DOMParser();

      // Parsear el fragmento de texto HTML
      var doc = parser.parseFromString(htmlString, 'text/html');

      // Encontrar el elemento con el ID "wpcss_copy_url"
      var copyUrlElement = doc.getElementById('wpcss_copy_url');

      // Verificar si el elemento existe
      if (copyUrlElement) {
          // Obtener el valor del atributo "value" del elemento
          var copyUrlValue = copyUrlElement.value;
          window.location.replace(`https://api.whatsapp.com/send?phone=${whatsapp_number}&text=${extra_message}%20%20${copyUrlValue}`);
      } else {
          console.log('El elemento con ID wpcss_copy_url no fue encontrado.');
      }

      return;

      
      $('.wpcss-btn').removeClass('wpcss-adding').addClass('wpcss-added');
      $('.wpcss-popup-content').html(response);
      $('.wpcss-area').addClass('wpcss-area-show');
    });
  });

  // copy link
  $(document).
      on('click touch', '#wpcss_copy_url, #wpcss_copy_btn', function(e) {
        wpcss_copy_to_clipboard('#wpcss_copy_url');
      });

  function wpcss_copy_to_clipboard(el) {
    // resolve the element
    el = (typeof el === 'string') ? document.querySelector(el) : el;

    // handle iOS as a special case
    if (navigator.userAgent.match(/ipad|ipod|iphone/i)) {
      // save current contentEditable/readOnly status
      var editable = el.contentEditable;
      var readOnly = el.readOnly;

      // convert to editable with readonly to stop iOS keyboard opening
      el.contentEditable = true;
      el.readOnly = true;

      // create a selectable range
      var range = document.createRange();
      range.selectNodeContents(el);

      // select the range
      var selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      el.setSelectionRange(0, 999999);

      // restore contentEditable/readOnly to original state
      el.contentEditable = editable;
      el.readOnly = readOnly;
    } else {
      el.select();
    }

    // execute copy command
    document.execCommand('copy');

    // alert
    alert(wpcss_vars.copied_text.replace('%s', el.value));
  }
})(jQuery);