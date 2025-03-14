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
      this._bindEvents();
      this._updateTextarea();
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

  // Wait for DOM to be ready and apply the widget to all json textareas
  $(function () {
    try {
      $('textarea.validate-json').moneiJsonFormatter({debug: true});
    } catch (e) {
      console.error('MONEI: Error initializing JSON fields', e);
    }
  });

  // Return the widget for AMD compatibility
  return $.monei.jsonFormatter;
});
