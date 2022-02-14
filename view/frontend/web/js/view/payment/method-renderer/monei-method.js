/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Monei_MoneiPayment/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators'
    ],
    function (Component, setPaymentMethodAction, additionalValidators) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Monei_MoneiPayment/payment/monei'
            },

            /** Redirect to monei */
            continueToMonei: function () {
                if (additionalValidators.validate()) {
                    setPaymentMethodAction(this.messageContainer);
                    return false;
                }
            },
        });
    }
);
