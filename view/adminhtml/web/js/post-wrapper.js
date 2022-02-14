/**
 * @author Monei Team
 * @copyright Copyright © Monei (https://monei.com)
 */
define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate',
    'Magento_Ui/js/modal/modal'
], function ($, confirm, modal) {
    'use strict';

    /**
     * @param {String} url
     * @returns {jQuery}
     */
    function getForm(url) {
        return $('<form>', {
            'action': url,
            'method': 'POST'
        }).append($('<input>', {
            'name': 'form_key',
            'value': window.FORM_KEY,
            'type': 'hidden'
        }));
    }

    $('#order-view-cancel-button').click(function () {
        var popupForm = $('#cancel-monei-order');
        if (popupForm.length) {
            $(document).ready(function(){
                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    buttons: [
                        {
                            text: $.mage.__('Ok'),
                            class: 'action-primary action-accept',
                            click: function () {
                                $.ajax({
                                    url: $('#cancel_url_monei').val(),
                                    dataType: 'json',
                                    method: 'POST',
                                    showLoader: true,
                                    data: {
                                        'order_id': $('#order_id').val(),
                                        'payment_id': $('#payment_id').val(),
                                        'cancel_reason': $('#cancel_reason').val(),
                                    },
                                    success: function (data) {
                                        window.location.href = data.redirectUrl;
                                    }
                                });
                            }
                        },
                        {
                            text: $.mage.__('Cancel'),
                            class: 'action-secondary action-dismiss',
                            click: function () {
                                this.closeModal();
                            }
                        }
                    ]
                };
                $("#cancel-monei-order").modal(options).modal('openModal');
            });
        } else {
            var msg = $.mage.__('Are you sure you want to cancel this order?'),
                url = $('#order-view-cancel-button').data('url');

            confirm({
                'content': msg,
                'actions': {

                    /**
                     * 'Confirm' action handler.
                     */
                    confirm: function () {
                        getForm(url).appendTo('body').trigger('submit');
                    }
                }
            });
        }

        return false;
    });

    $('#order-view-hold-button').click(function () {
        var url = $('#order-view-hold-button').data('url');

        getForm(url).appendTo('body').trigger('submit');
    });

    $('#order-view-unhold-button').click(function () {
        var url = $('#order-view-unhold-button').data('url');

        getForm(url).appendTo('body').trigger('submit');
    });
});
