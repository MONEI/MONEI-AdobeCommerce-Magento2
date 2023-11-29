/*
 * @author Interactiv4 Team
 * @copyright  Copyright © Interactiv4 (https://www.interactiv4.com)
 */
define([
    'mage/utils/wrapper',
    'ko'
], function (wrapper, ko) {
    'use strict';

    var extender = {

        moneiPaymentId: ko.observable(null),
        moneiCardToken: ko.observable(null),

        /**
         * Get monei payment id
         */
        getMoneiPaymentId: function () {
            return this.moneiPaymentId();
        },
        /**
         * Set monei payment id
         */
        setMoneiPaymentId: function (val) {
            this.moneiPaymentId(val);
        },

        /**
         * Get monei card token
         */
        getMoneiCardToken: function () {
            return this.moneiCardToken();
        },
        /**
         * Set monei card token
         */
        setMoneiCardToken: function (val) {
            this.moneiCardToken(val);
        },
    };

    return function (target) {
        return wrapper.extend(target, extender);
    };
});
