(function ($, Drupal, window, document) {
  'use strict';

  // Example of Drupal behavior loaded.
  Drupal.behaviors.eventSession = {
    attach: function (context, settings) {

      $('.cci-session-modal-form .session-wrapper .form-item').hide();

      $('.cci-session-modal-form .session-wrapper').on('click', function () {
        if ($(this).find('input.form-checkbox').attr('disabled') !== 'disabled') {
          if ($(this).hasClass('selected')) {
            $(this).removeClass('selected');
            $(this).find('input.form-checkbox').prop('checked', false);
          }
          else {
            $(this).addClass('selected');
            $(this).find('input.form-checkbox').prop('checked', true);
          }
        }
      });
    }
  };

})(jQuery, Drupal, this, this.document);