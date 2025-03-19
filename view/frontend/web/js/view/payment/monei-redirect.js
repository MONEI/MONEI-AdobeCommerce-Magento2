/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */
define(['uiComponent', 'Magento_Checkout/js/model/payment/renderer-list'], function (
  Component,
  rendererList
) {
  'use strict';
  rendererList.push({
    type: 'monei',
    component: 'Monei_MoneiPayment/js/view/payment/method-renderer/monei-redirect-method'
  });
  return Component.extend({});
});
