/**
 * @copyright Copyright © Monei (https://monei.com)
 */

define(['jquery', 'jquery/ui', 'Magento_Ui/js/modal/alert', 'mage/translate'], function (
  $,
  ui,
  alert,
  $t
) {
  'use strict';

  // Create monei namespace if it doesn't exist
  $.monei = $.monei || {};

  // Define the widget using jQuery UI widget factory
  $.widget('monei.jsonFormatter', {
    options: {
      buttonSelector: '.format-json-button',
      debug: false
    },

    /**
     * Widget creation
     * @private
     */
    _create: function () {
      if (this.options.debug) {
        console.log('MONEI: Initializing JSON Formatter widget', this.element);
      }

      try {
        this._bindFormatButton();
      } catch (e) {
        console.error('MONEI: Error initializing JSON Formatter widget', e);
      }
    },

    /**
     * Bind click event to format button
     * @private
     */
    _bindFormatButton: function () {
      var self = this;
      var button = $('<button>', {
        type: 'button',
        class: 'format-json-button',
        text: $t('Format JSON'),
        click: function (e) {
          e.preventDefault();
          self._formatJson();
        }
      });

      this.element.after(button);
    },

    /**
     * Format JSON in textarea
     * @private
     */
    _formatJson: function () {
      var value = this.element.val();

      if (!value) {
        return;
      }

      try {
        // Parse and format JSON
        var jsonObj = JSON.parse(value);
        var formattedJson = JSON.stringify(jsonObj, null, 4);

        // Update textarea with formatted JSON
        this.element.val(formattedJson);
      } catch (e) {
        // Show error alert if JSON is invalid
        alert({
          title: $t('Invalid JSON'),
          content: $t('The JSON entered is invalid: %1').replace('%1', e.message)
        });

        if (this.options.debug) {
          console.error('MONEI: JSON formatting error', e);
        }
      }
    }
  });

  // Wait for DOM to be ready
  $(function () {
    try {
      // Check if widget is registered
      if ($.fn.moneiJsonFormatter) {
        $('textarea.validate-json').moneiJsonFormatter({debug: true});
      } else {
        console.error('MONEI: moneiJsonFormatter widget not available');
      }
    } catch (e) {
      console.error('MONEI: Error initializing JSON fields', e);
    }
  });

  // Return the widget for AMD compatibility
  return $.monei.jsonFormatter;
});
