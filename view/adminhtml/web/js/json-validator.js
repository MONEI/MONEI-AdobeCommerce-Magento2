/**
 * @category  Monei

 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

define(['jquery', 'mage/translate', 'jquery/validate'], function ($, $t) {
  'use strict';

  /**
   * Add custom JSON validator to the validation rules
   */
  return function () {
    // Check if validator already exists to avoid redefining
    if (!$.validator.methods['validate-json']) {
      $.validator.addMethod(
        'validate-json',
        function (value) {
          if (value === '') {
            return true;
          }

          try {
            JSON.parse(value);
            return true;
          } catch (e) {
            return false;
          }
        },
        $t('Please enter valid JSON. Examples: {"height":"45px"} or {"base":{"height":"30px"}}')
      );
    }

    // Initialize validation on document ready
    $(function () {
      try {
        // Force validation on JSON fields
        $('textarea.validate-json').each(function () {
          var field = $(this);

          // Trigger validation when the field loses focus
          field.on('blur', function () {
            if (field.val()) {
              try {
                JSON.parse(field.val());
                field.removeClass('validation-failed');
                field.next('.validation-advice').remove();
              } catch (e) {
                if (!field.hasClass('validation-failed')) {
                  field.addClass('validation-failed');
                  var advice = $(
                    '<div class="validation-advice">' +
                      $t(
                        'Please enter valid JSON. Examples: {"height":"45px"} or {"base":{"height":"30px"}}'
                      ) +
                      '</div>'
                  );
                  field.after(advice);
                }
              }
            }
          });
        });
      } catch (e) {
        console.error('MONEI: Error initializing JSON validator', e);
      }
    });
  };
});
