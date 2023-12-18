var config = {
    map: {
        '*': {
            moneijs: 'https://js.monei.com/v2/monei.js',
        }
    },
    shim: {
        'moneijs': {
            exports: 'monei',
        },
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/quote': {
                'Monei_MoneiPayment/js/model/quote-mixin': true
            }
        }
    }
};
