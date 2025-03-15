/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(['jquery', 'Magento_Ui/js/model/messageList', 'mage/translate'], function (
  $,
  globalMessageList,
  $t
) {
  'use strict';

  return {
    /**
     * Handle API error responses
     *
     * @param {Object} error The error response object
     */
    handleApiError: function (error) {
      var errorMessage = '';

      // Handle messages with parameters
      if (error.parameters && error.parameters.length) {
        // Manually replace parameters in the message
        errorMessage = error.message;
        for (var i = 0; i < error.parameters.length; i++) {
          var placeholder = '%' + (i + 1);
          errorMessage = errorMessage.replace(placeholder, error.parameters[i]);
        }
      } else {
        errorMessage = error.message || $t('An error occurred during the payment process');
      }

      globalMessageList.addErrorMessage({
        message: errorMessage
      });
    }
  };
});
