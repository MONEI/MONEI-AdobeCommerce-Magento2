/*
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */

define([
  'jquery',
  'Magento_Checkout/js/model/quote',
  'Magento_Checkout/js/model/url-builder',
  'mage/storage',
  'Magento_Checkout/js/model/error-processor',
  'Magento_Customer/js/model/customer',
  'Magento_Checkout/js/model/full-screen-loader',
  'Magento_CheckoutAgreements/js/model/agreements-assigner'
], function (
  $,
  quote,
  urlBuilder,
  storage,
  errorProcessor,
  customer,
  fullScreenLoader,
  agreementsAssigner
) {
  'use strict';

  /**
   * Filter template data.
   *
   * @param {Object|Array} data
   */
  var filterTemplateData = function (data) {
    return _.each(data, function (value, key, list) {
      if (_.isArray(value) || _.isObject(value)) {
        list[key] = filterTemplateData(value);
      }

      if (key === '__disableTmpl') {
        delete list[key];
      }
    });
  };

  return function (messageContainer) {
    var serviceUrl,
      payload,
      paymentData = quote.paymentMethod();

    paymentData = filterTemplateData(paymentData);

    if (paymentData.hasOwnProperty('title')) {
      delete paymentData['title'];
    }

    agreementsAssigner(paymentData);

    /**
     * Checkout for guest and registered customer.
     */
    if (!customer.isLoggedIn()) {
      serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/payment-information', {
        cartId: quote.getQuoteId()
      });
      payload = {
        cartId: quote.getQuoteId(),
        email: quote.guestEmail,
        paymentMethod: paymentData,
        billingAddress: quote.billingAddress()
      };
    } else {
      serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
      payload = {
        cartId: quote.getQuoteId(),
        paymentMethod: paymentData,
        billingAddress: quote.billingAddress()
      };
    }

    fullScreenLoader.startLoader();

    return storage
      .post(serviceUrl, JSON.stringify(payload))
      .done(function (response) {
        // redirect to monei
        if (response.redirectUrl) {
          window.location.replace(response.redirectUrl);
          return;
        }
      })
      .fail(function (response) {
        errorProcessor.process(response, messageContainer);
        fullScreenLoader.stopLoader();
      });
  };
});
