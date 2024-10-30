/**
  *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the OpenGPL v3 license (GNU Public License V3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@e-abi.ee so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category   WooCommmerce payment method
 * @package    mTasku payment for WooCommerce
 * @copyright  Copyright (c) 2023 Aktsiamaailm LLC (http://en.e-abi.ee/)
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt  GNU Public License V3.0
 * @author     Matis Halmann
 * 

 */
(function ($) {
    $.fn.eabi_telia_mtasku_payment_form = function (options) {
        var transactionHandle = null;
        var dataKey = 'eabi_telia_mtasku';

        if (typeof options === 'string') {
            options = {qrTagUrl: options};
        }

        // set default values
        // typeNumber < 1 for automatic calculation
        options = $.extend({}, {
            imageSrc: null,
            qrTagUrl: null,
            apiCheckUrl: null,
            qrCodeLocation: '[data-role=qr-code]',
            cancelButtonLocation: '[data-role=cancel-button]',
            errorMessageLocation: '[data-role=error-message]',
            confirmButtonLocation: '[data-role=submit-form]',
            startButtonLocation: '[data-role=start-button]',
            errorMessageLocationIsGlobal: false,
            confirmButtonLocationIsGlobal: false,
            errorMessageHolderHtml: '<ul class="woocommerce-error" role="alert"></ul>',
            errorMessageElementHtml: '<li></li>',
            cancelText: 'Cancel',
            startPaymentText: 'Open mTasku app',
            allowRedirect: false
        }, options);

        function clearForm(target) {
            if (transactionHandle) {
                clearTimeout(transactionHandle);
                transactionHandle = null;
            }
            jQuery(target).closest('.form-block').remove();
        }

        function isHandHeldDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        function showError(target, errorMessage) {
            var errorMessageHolder = $(options.errorMessageHolderHtml);
            if (Array.isArray(errorMessage)) {
                $.each(errorMessage, function (index, value) {
                    var errorMessageItem = $(options.errorMessageElementHtml).text(value);
                    errorMessageHolder.append(errorMessageItem);
                });
            } else {
                $.each([errorMessage], function (index, value) {
                    var errorMessageItem = $(options.errorMessageElementHtml).text(value);
                    errorMessageHolder.append(errorMessageItem);
                });

            }

            if (options.errorMessageLocationIsGlobal) {
                jQuery(options.errorMessageLocation).html(errorMessageHolder);
            } else {
                jQuery(target).find(options.errorMessageLocation).html(errorMessageHolder);
            }
        }

        function fetchData(responseDefer) {
            if (options.apiCheckUrl) {
                jQuery.ajax({
                    url: options.apiCheckUrl,
                    type: 'post',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status == 'complete') {
                            responseDefer.resolve(response);
                        } else if (response.status == 'waiting') {
                        } else if (response.status == 'error') {
                            responseDefer.reject(response);
                        }


                    },
                    error: function (response) {
                        responseDefer.reject({ status: 'error'});

                    },
                    complete: function (response) {
                        if (transactionHandle) {
                            clearTimeout(transactionHandle);
                        }
                        transactionHandle = setTimeout(function () {
                            fetchData(responseDefer);
                        }, 1000);
                    }

                });

            }
        };

        var createForm = function (arguments, target) {
            var qrOptions = {
                ecLevel: 'H',
                size: 230,
                text: arguments.qrTagUrl
            };
            var qrCodeGeneratorDefer = $.Deferred();
            var transactionStatusDefer = $.Deferred();
            var result = null;

            if (arguments.imageSrc) {
                $("<img />").attr('src', arguments.imageSrc).on('load', function () {

                    if (this) {
                        qrOptions = $.extend(qrOptions, {
                            image: this,
                            mode: 4,
                            mSize: 0.2
                        });
                    }
                    qrCodeGeneratorDefer.resolve(qrOptions);
                });
            } else {
                qrCodeGeneratorDefer.resolve(qrOptions);
                //no image src
            }

            $.when(transactionStatusDefer.promise()).then(function (transactionResponse) {
                //called on valid HTTP 2xx response
                    if (transactionResponse.status == 'complete') {
                        clearForm(target);
                        if (options.confirmButtonLocation) {
                            if (options.confirmButtonLocationIsGlobal) {
                                jQuery(options.confirmButtonLocation).click();
                            } else {
                                $(target).find(options.confirmButtonLocation).click();

                            }
                        }
                        if (options.allowRedirect && transactionResponse.redirect) {
                            window.location.href = transactionResponse.redirect;
                        }

                    } else if (transactionResponse.status == 'error') {
                        clearForm(target);
                        showError(target, options.genericErrorText);
                    } else if (transactionResponse.status == 'waiting') {
                    }

                },
                function (transactionResponse) {
                    //called on invalid HTTP response
                    clearForm(target);
                    showError(target, options.genericErrorText);

                }, function (transactionResponse) {

                });

            $.when(qrCodeGeneratorDefer.promise()).then(
                function (qrCodeData) {
                    $(target).find(options.qrCodeLocation).qrcode(qrCodeData);
                    $(target).find(options.cancelButtonLocation).text(options.cancelText);
                    $(target).find(options.startButtonLocation).text(options.startPaymentText);

                    $(target).find(options.qrCodeLocation).on('click', function () {
                        window.location.href = qrCodeData.text;
                    });
                    $(target).find(options.startButtonLocation).on('click', function () {
                        window.location.href = qrCodeData.text;
                    });
                    $(target).find(options.cancelButtonLocation).on('click', function () {
                        showError(target, options.cancelErrorText);
                        clearForm(target);
                    });

                    if (isHandHeldDevice()) {
                        $(target).find(options.startButtonLocation).removeClass('not-visible');
                    }

                    transactionHandle = setTimeout(function () {
                        //monitor response success and click on the buttons if needed.
                        fetchData(transactionStatusDefer);
                    }, 1000);

                },
                function (qrCodeData) {
                    clearForm(target);
                    showError(target, options.genericErrorText);

                },
                function (qrCodeData) {

                }
            );

            return result;


        };


        return this.each(function (index, element) {
            var form;
            if (!$(element).data(dataKey)) {
                form = createForm(options, element);
                $(element).data(dataKey, true);
            }
            return form;
        });

    };
})(jQuery);