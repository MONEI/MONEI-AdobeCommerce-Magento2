/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(['uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'], function (
  Component,
  rendererList
) {
  'use strict';
  rendererList.push({
    type: 'monei_paypal',
    component: 'Monei_MoneiPayment/js/view/payment/method-renderer/monei-paypal-method'
  });
  return Component.extend({});
});
