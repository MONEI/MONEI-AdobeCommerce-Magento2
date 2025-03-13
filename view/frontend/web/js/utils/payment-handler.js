/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
  'jquery',
  'Magento_Checkout/js/action/redirect-on-success',
  'Magento_Checkout/js/model/full-screen-loader',
  'Monei_MoneiPayment/js/utils/error-handler',
  'moneijs'
], function ($, redirectOnSuccessAction, fullScreenLoader, errorHandler, monei) {
  'use strict';

  return {
    /**
     * Generic function to handle Monei payment token confirmation
     *
     * @param {Object} component - The payment method component
     * @param {String} paymentId - The payment ID
     * @param {String} token - The payment token
     * @param {Object} options - Additional options for confirmPayment
     * @returns {Promise} Promise that resolves when payment is confirmed
     */
    moneiTokenHandler: function (component, paymentId, token, options) {
      var self = this;
      fullScreenLoader.startLoader();

      // Prepare basic parameters
      var confirmParams = {
        paymentId: paymentId,
        paymentToken: token
      };

      // Merge additional options if provided
      if (options) {
        $.extend(confirmParams, options);
      }

      return monei
        .confirmPayment(confirmParams)
        .then(function (result) {
          if (result.nextAction && result.nextAction.redirectUrl) {
            window.location.replace(result.nextAction.redirectUrl);
          } else if (component.redirectAfterPlaceOrder) {
            redirectOnSuccessAction.execute();
          }
        })
        .catch(function (error) {
          errorHandler.handleApiError(error);
          if (typeof component.redirectToCancelOrder === 'function') {
            component.redirectToCancelOrder();
          }
        });
    }
  };
});
