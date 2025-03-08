/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
    'jquery',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function ($, globalMessageList, $t) {
    'use strict';

    return {
        /**
         * Handle API error responses
         * 
         * @param {Object} error The error response object
         */
        handleApiError: function(error) {
            var errorMessage = '';
            
            // Handle messages with parameters
            if (error.parameters && error.parameters.length) {
                var translatedMessage = $.mage.__(error.message);
                errorMessage = translatedMessage;
                
                // Replace placeholders with actual values
                $.each(error.parameters, function(index, value) {
                    errorMessage = errorMessage.replace('%' + (index + 1), value);
                });
            } else {
                errorMessage = error.message || $t('An error occurred during the payment process');
            }
            
            globalMessageList.addErrorMessage({
                message: errorMessage
            });
        }
    };
});