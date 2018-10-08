var EwayPayment = Class.create();
EwayPayment.isEwayRapidMethod = function(method) {
    return ("ewayrapid_saved" === method || "ewayrapid_notsaved" === method);
};
EwayPayment.supportCardTypes = ['AE', 'VI', 'MC', 'JCB', 'DC', 'VE', 'ME'];
EwayPayment.prototype = {
    paymentUrl : null,
    ewayPayment: this,
    initialize: function(form, encryptionKey) {
        if(form) {
            // Init client-side encryption
            if(typeof eCrypt == 'function') {
                form.writeAttribute('data-eway-encrypt-key', encryptionKey);
                eCrypt && eCrypt.init();
            }
        }
    },

    savePaymentWithEncryption: function() {
        if (checkout.loadWaiting!=false) return;
        var validator = new Validation(this.form);
        var formNeeded;
        if (this.validate() && validator.validate()) {
            checkout.setLoadWaiting('payment');
            var form = $(this.form);
            if($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                form = eCrypt.doEncrypt();
            }
            this.ewayForm = form;
            if ($(this.form).down('#ewayrapid_saved_token').value != 'new') {
                formNeeded = $(this.form);
            } else {
                formNeeded = $(form.id);
            }
            var request = new Ajax.Request(
                this.saveUrl,
                {
                    method:'post',
                    onComplete: this.onComplete,
                    onSuccess: this.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout),
                    parameters: formNeeded.serialize()
                }
            );
        }
    },

    savePaymentWithTransEncryption: function() {
        if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
            if (checkout.loadWaiting != false) return;
            var validator = new Validation(this.form);
            if (this.validate() && validator.validate()) {
                checkout.setLoadWaiting('payment');
                var form = $(this.form);
                var _method = $$("input[name='payment[method]']:checked")[0].getValue();
                var _transparent_method = '';

                if (_method == 'ewayrapid_notsaved' && $$("input[name='payment[transparent_notsaved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_notsaved]']:checked")[0];
                } else if (_method == 'ewayrapid_saved' && $$("input[name='payment[transparent_saved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_saved]']:checked")[0];
                }

                if (_transparent_method != '' && $(_transparent_method.id).getValue() == creditcard) {
                    if ($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                        form = eCrypt.doEncrypt();
                    }
                }

                this.ewayForm = form;
                var request = new Ajax.Request(
                    this.saveUrl,
                    {
                        method: 'post',
                        onComplete: this.onComplete,
                        onSuccess: this.onSave,
                        onFailure: checkout.ajaxFailure.bind(checkout),
                        parameters: $(form.id).serialize()
                    }
                );
            }
        }
    },

    saveReviewWithEncryption: function() {
        if (checkout.loadWaiting!=false) return;
        checkout.setLoadWaiting('review');
        var params = Form.serialize(payment.form);
        //var params = payment.ewayForm.serialize();
        if (this.agreementsForm) {
            params += '&'+Form.serialize(this.agreementsForm);
        }
        params.save = true;
        var request = new Ajax.Request(
            this.saveUrl,
            {
                method:'post',
                parameters:params,
                onComplete: this.onComplete,
                onSuccess: this.onSave,
                onFailure: checkout.ajaxFailure.bind(checkout)
            }
        );
    },
    saveReviewWithEncryptionTrans: function () {
        if (EwayPayment.isEwayRapidMethod(payment.currentMethod) && ewayPayment.paymentUrl != null) {
            $('review-please-wait') && $('review-please-wait').show();
            $('review-buttons-container') && $('review-buttons-container').down('button').hide();

            var request = new Ajax.Request(
                ewayPayment.paymentUrl,
                {
                    method: 'post',
                    onComplete: {},
                    onSuccess: function (response) {
                        if (response.responseText != '0') {
                            window.location = response.responseText;
                        }
                        return false;
                    },
                    onFailure: {}
                }
            );
        } else {
            this.prototype.ewaysavedOldOrder();
        }
    },
    subMitForm: function () {
        form = eCrypt.doEncrypt();
        form.submit();
    },

    submitAdminOrder: function() {
        if(editForm.validator && editForm.validator.validate()) {
            if($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                editForm = eCrypt.doEncrypt();
            }
            if (this.orderItemChanged) {
                if (confirm('You have item changes')) {
                    if (editForm.submit()) {
                        disableElements('save');
                    }
                } else {
                    this.itemsUpdate();
                }
            } else {
                if (editForm.submit()) {
                    disableElements('save');
                }
            }
        }
    },

    OneStepCheckout: {
        switchMethod: function(method) {
            $$('.payment-method .form-list').each(function(form) {
                form.style.display = 'none';
                var elements = form.select('input').concat(form.select('select')).concat(form.select('textarea'));
                for (var i=0; i<elements.length; i++) elements[i].disabled = true;
            });

            if ($('payment_form_'+method)){
                var form = $('payment_form_'+method);
                form.style.display = '';
                var elements = form.select('input').concat(form.select('select')).concat(form.select('textarea'));
                for (var i=0; i<elements.length; i++) elements[i].disabled = false;
                this.currentMethod = method;
                if ($('ul_payment_form_'+method)) {
                    $('ul_payment_form_'+method).show();
                }
            }
        }
    },

    FireCheckout: {
        save: function(urlSuffix, forceSave) {
            var currentMethod = payment.currentMethod ? payment.currentMethod : '';
            if(EwayPayment.isEwayRapidMethod(currentMethod)) {
                if (this.loadWaiting != false) {
                    return;
                }

                if (!this.validate()) {
                    return;
                }

                // infostrates tnt
                if (!forceSave && (typeof shippingMethod === 'object')
                    && shippingMethod.getCurrentMethod().indexOf("tnt_") === 0) {

                    shippingMethodTnt(shippingMethodTntUrl);
                    return;
                }
                // infostrates tnt

                checkout.setLoadWaiting(true);

                var params = Form.serialize(this.form, true);
                $('review-please-wait').show();

                encryptedForm = eCrypt.doEncrypt();
                params = Form.serialize(encryptedForm, true);

                urlSuffix = urlSuffix || '';
                var request = new Ajax.Request(this.urls.save + urlSuffix, {
                    method:'post',
                    parameters:params,
                    onSuccess: this.setResponse.bind(this),
                    onFailure: this.ajaxFailure.bind(this)
                });
            } else if(typeof this.ewayOldSave == 'function') {
                this.ewayOldSave(urlSuffix, forceSave);
            }
        },
        savePayment: function(urlSuffix, forceSave) {
            var currentMethod = payment.currentMethod ? payment.currentMethod : '';
            if(EwayPayment.isEwayRapidMethod(currentMethod)) {
                if (this.loadWaiting != false) {
                    return;
                }

                if (!this.validate()) {
                    return;
                }

                // infostrates tnt
                if (!forceSave && (typeof shippingMethod === 'object')
                    && shippingMethod.getCurrentMethod().indexOf("tnt_") === 0) {

                    shippingMethodTnt(shippingMethodTntUrl);
                    return;
                }
                // infostrates tnt

                checkout.setLoadWaiting(true);

                var params = Form.serialize(this.form, true);
                $('review-please-wait').show();

                var _method = $$("input[name='payment[method]']:checked")[0].getValue();
                var _transparent_method = '';
                if (_method == 'ewayrapid_notsaved' && $$("input[name='payment[transparent_notsaved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_notsaved]']:checked")[0];
                } else if (_method == 'ewayrapid_saved' && $$("input[name='payment[transparent_saved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_saved]']:checked")[0];
                }

                if (_transparent_method != '' && $(_transparent_method.id).getValue() == creditcard) {
                    if ($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                        encryptedForm = eCrypt.doEncrypt();
                    }
                }

                params = Form.serialize(encryptedForm, true);

                urlSuffix = urlSuffix || '';
                var request = new Ajax.Request(this.urls.save + urlSuffix, {
                    method:'post',
                    parameters:params,
                    onSuccess: this.setResponse.bind(this),
                    onFailure: this.ajaxFailure.bind(this)
                });
            } else if(typeof this.ewayOldSave == 'function') {
                this.ewayOldSave(urlSuffix, forceSave);
            }
        }
    },

    IWDOnePageCheckout: {
        savePayment: function() {
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                if (IWD.OPC.Checkout.xhr!=null){
                    IWD.OPC.Checkout.xhr.abort();
                }
                IWD.OPC.Checkout.showLoader();
                var ewayForm = eCrypt.doEncrypt();
                form = $j(ewayForm).serializeArray();
                IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
            } else if(typeof IWD.OPC.ewayOldSavePayment == 'function') {
                IWD.OPC.ewayOldSavePayment();
            }
        },
        savePaymentTrans: function() {
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                /*var ewayForm = $(this.form);
                if (IWD.OPC.Checkout.xhr!=null){
                    IWD.OPC.Checkout.xhr.abort();
                }
                IWD.OPC.Checkout.showLoader();
                var _method = $$("input[name='payment[method]']:checked")[0].getValue();
                var _transparent_method = '';
                if (_method == 'ewayrapid_notsaved' && $$("input[name='payment[transparent_notsaved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_notsaved]']:checked")[0];
                } else if (_method == 'ewayrapid_saved' && $$("input[name='payment[transparent_saved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_saved]']:checked")[0];
                }

                if (_transparent_method != '' && $(_transparent_method.id).getValue() == creditcard) {
                    if ($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                        ewayForm = eCrypt.doEncrypt();
                    }
                }*/
                var ewayForm = eCrypt.doEncrypt();
                form = $j(ewayForm).serializeArray();
                IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
            } else if(typeof IWD.OPC.ewayOldSavePayment == 'function') {
                IWD.OPC.ewayOldSavePayment();
            }
        },
        saveOrder: function() {
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                var ewayForm = eCrypt.doEncrypt();
                form = $j(ewayForm).serializeArray();
                form  = IWD.OPC.checkAgreement(form);
                IWD.OPC.Checkout.showLoader();
                if (IWD.OPC.Checkout.config.comment!=="0"){
                    IWD.OPC.saveCustomerComment();
                }

                IWD.OPC.Plugin.dispatch('saveOrder');
                IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.saveOrderUrl ,form, IWD.OPC.prepareOrderResponse,'json');
            } else if(typeof IWD.OPC.ewayOldSaveOrder == 'function') {
                IWD.OPC.ewayOldSaveOrder();
            }
        }
    },
    Lightcheckout : {
        LightcheckoutSubmit: function() {
            if (payment.currentMethod && (payment.currentMethod.indexOf('sagepay') == 0) &&
                (SageServer != undefined) && (review != undefined)) {
                if (checkoutForm.validator.validate()) {
                    review.preparedata();
                }
            }
            else {
                if (checkoutForm.validator.validate()) {
                    this.submit(this.getFormData(), 'save_payment_methods');
                }
            }
        },
        submit: function (params, action) {

            this.showLoadinfo();

            params.action = action;

            var request = new Ajax.Request(this.url,
                {
                    method: 'post',
                    parameters: params,
                    onSuccess: function (transport) {

                        eval('var response = ' + transport.responseText);

                        if (response.messages_block) {
                            var gcheckout_onepage_wrap = $$('div.gcheckout-onepage-wrap')[0];
                            if (gcheckout_onepage_wrap) {
                                new Insertion.Before(gcheckout_onepage_wrap, response.messages_block);
                            }
                            this.disable_place_order = true;
                        } else {
                            this.disable_place_order = false;
                        }

                        if (response.url) {

                            this.existsreview = false;
                            setLocation(response.url);

                        } else {

                            if (response.error) {
                                if (response.message) {
                                    alert(response.message);
                                }
                                this.existsreview = false;
                                this.hideLoadinfo();
                            } else {

                                var process_save_order = false;

                                if (response.methods) {
                                    // Quote isVirtual
                                    this.innerHTMLwithScripts($('gcheckout-onepage-methods'), response.methods);
                                    var wrap = $$('div.gcheckout-onepage-wrap')[0];
                                    if (wrap && !wrap.hasClassName('not_shipping_mode')) {
                                        wrap.addClassName('not_shipping_mode');
                                    }
                                    if ($('billing_use_for_shipping_yes') && $('billing_use_for_shipping_yes').up('li.control')) {
                                        $('billing_use_for_shipping_yes').up('li.control').remove();
                                    }
                                    if ($('gcheckout-shipping-address')) {
                                        $('gcheckout-shipping-address').remove();
                                    }
                                    payment.init();
                                    this.observeMethods();
                                }

                                if (response.shippings) {
                                    if (shipping_rates_block = $('gcheckout-shipping-method-available')) {
                                        this.innerHTMLwithScripts(shipping_rates_block, response.shippings);
                                        this.observeShippingMethods();
                                    }
                                }

                                if (response.payments) {
                                    this.innerHTMLwithScripts($('gcheckout-payment-methods-available'), response.payments);
                                    payment.init();
                                    this.observePaymentMethods();
                                }

                                if (response.gift_message) {
                                    if (giftmessage_block = $('gomage-lightcheckout-giftmessage')) {
                                        this.innerHTMLwithScripts(giftmessage_block, response.gift_message);
                                    }
                                }

                                if (response.toplinks) {
                                    this.replaceTopLinks(response.toplinks);
                                }

                                if (response.minicart) {
                                    this.replaceMiniCart(response);
                                }

                                if (response.cart_sidebar && typeof(GomageProcartConfig) != 'undefined') {
                                    GomageProcartConfig._replaceEnterpriseTopCart(response.cart_sidebar, ($('topCartContent') && $('topCartContent').visible()));
                                }

                                if (response.review) {
                                    this.innerHTMLwithScripts($$('#gcheckout-onepage-review div.totals')[0], response.review);
                                }

                                if (response.content_billing) {
                                    var div_billing = document.createElement('div');
                                    div_billing.innerHTML = response.content_billing;
                                    $('gcheckout-onepage-address').replaceChild(div_billing.firstChild, $('gcheckout-billing-address'));
                                }

                                if (response.content_shipping && $('gcheckout-shipping-address')) {
                                    var div_shipping = document.createElement('div');
                                    div_shipping.innerHTML = response.content_shipping;
                                    $('gcheckout-onepage-address').replaceChild(div_shipping.firstChild, $('gcheckout-shipping-address'));
                                }

                                if (response.content_billing || response.content_shipping) {
                                    this.observeAddresses();
                                    initAddresses();
                                }

                                if (response.section == 'varify_taxvat') {

                                    if ($('billing_taxvat_verified')) {
                                        $('billing_taxvat_verified').remove();
                                    }

                                    if ($('shipping_taxvat_verified')) {
                                        $('shipping_taxvat_verified').remove();
                                    }

                                    this.taxvat_verify_result = response.verify_result;

                                    if ($('billing_taxvat') && $('billing_taxvat').value) {
                                        if (response.verify_result.billing) {
                                            if (label = $('billing_taxvat').parentNode.parentNode.getElementsByTagName('label')[0]) {
                                                label.innerHTML += '<strong id="billing_taxvat_verified" style="margin-left:5px;">(<span style="color:green;">Verified</span>)</strong>';
                                                $('billing_taxvat').removeClassName('validation-failed');
                                            }
                                        } else if ($('billing_taxvat').value) {
                                            if (label = $('billing_taxvat').parentNode.parentNode.getElementsByTagName('label')[0]) {
                                                label.innerHTML += '<strong id="billing_taxvat_verified" style="margin-left:5px;">(<span style="color:red;">Not Verified</span>)</strong>';
                                            }
                                        }
                                    }

                                    if ($('shipping_taxvat') && $('shipping_taxvat').value) {
                                        if (response.verify_result.shipping) {
                                            if (label = $('shipping_taxvat').parentNode.parentNode.getElementsByTagName('label')[0]) {
                                                label.innerHTML += '<strong id="shipping_taxvat_verified" style="margin-left:5px;">(<span style="color:green;">Verified</span>)</strong>';
                                                $('shipping_taxvat').removeClassName('validation-failed');
                                            }
                                        } else if ($('shipping_taxvat').value) {
                                            if (label = $('shipping_taxvat').parentNode.parentNode.getElementsByTagName('label')[0]) {
                                                label.innerHTML += '<strong id="shipping_taxvat_verified" style="margin-left:5px;">(<span style="color:red;">Not Verified</span>)</strong>';
                                            }
                                        }
                                    }

                                }

                                if (response.section == 'centinel') {

                                    if (response.centinel) {
                                        this.showCentinel(response.centinel);
                                    } else {
                                        process_save_order = true;
                                        if ((payment.currentMethod == 'authorizenet_directpost') && ((typeof directPostModel != 'undefined'))) {
                                            directPostModel.saveOnepageOrder();
                                        } else {
                                            this.saveorder();
                                        }
                                    }
                                }

                                this.setBlocksNumber();

                                if (this.existsreview) {
                                    this.existsreview = false;
                                    review.save();
                                }
                                else {
                                    if (!process_save_order) {
                                        this.hideLoadinfo();
                                    }
                                }

                            }

                        }

                    }.bind(this),
                    onFailure: function () {
                        this.existsreview = false;
                    }
                });
        },
        getFormData: function () {
            //var form_data = $('gcheckout-onepage-form').serialize(true);
            var form = eCrypt.doEncrypt();
            var form_data = form.serialize(true);
            for (var key in form_data) {
                if ((key == 'billing[customer_password]') || (key == 'billing[confirm_password]')) {
                    form_data[key] = GlcUrl.encode(form_data[key]);
                }
                if (payment.currentMethod == 'authorizenet_directpost') {
                    if (key.indexOf('payment[') == 0 && key != 'payment[method]' && key != 'payment[use_customer_balance]') {
                        delete form_data[key];
                    }
                }
            }

            return form_data;
        }
    }
};

var EwayPaymentToken = Class.create();
EwayPaymentToken.prototype = {
    savedTokens: null,
    tokenCount: 0,
    isAdmin: false,
    labelEdit: 'Edit',
    labelCancel: 'Cancel edit',
    isEdit: true,
    initialize: function(savedTokens, tokenCount, isAdmin, labelEdit, labelCancel) {
        savedTokens['new']['Card'] = '';
        this.savedTokens = savedTokens;
        this.tokenCount = tokenCount;
        this.isAdmin = isAdmin;
        this.labelEdit = labelEdit;
        this.labelCancel = labelCancel;

        $('ewayrapid_saved_token') && $('ewayrapid_saved_token').observe('change', this.onSavedTokenChanged.bind(this));

        $('ewayrapid_saved_edit') && $('ewayrapid_saved_edit').observe('click', this.onEditClick.bind(this));

        if(this.tokenCount == 1) {
            // Show credit card form in case customer does not have saved credit card (only 'Add new card' option)
            this.ewayrapidToggleCcForm(true);
        } else {
            this.onSavedTokenChanged();
        }
    },

    onSavedTokenChanged: function() {
        if($('ewayrapid_saved_token') && $('ewayrapid_saved_token').value == 'new') {
            this.ewayrapidToggleCcForm(true);
            this.ewayrapidSelectToken('new');
            $('ewayrapid_saved_cc_type') && $('ewayrapid_saved_cc_type').setValue('');
            $('ewayrapid_saved_edit') && $('ewayrapid_saved_edit').hide();
            $$('.help-disabled-cc a').each(function(element){
                element.hide();
            });
        } else {
            this.ewayrapidToggleCcForm(false);
            $('ewayrapid_saved_cc_type') && $('ewayrapid_saved_cc_type').setValue(this.savedTokens[$('ewayrapid_saved_token').getValue()]['Type']);
            if($('ewayrapid_saved_edit')) {
                this.isEdit = true;
                $('ewayrapid_saved_edit').update(this.labelEdit);
                $('ewayrapid_saved_edit').show();
            }
        }
        $('ewayrapid_saved_cc_cid') && $('ewayrapid_saved_cc_cid').setValue('');
    },

    onEditClick: function() {
        if(this.isEdit) {
            this.ewayrapidToggleCcForm(true);
            this.ewayrapidSelectToken($('ewayrapid_saved_token').getValue());
            $('ewayrapid_saved_edit').update(this.labelCancel);
            $('ewayrapid_saved_cc_number').disable();
            $('ewayrapid_saved_cc_number').removeClassName('validate-cc-number').removeClassName('validate-cc-type-auto');
            $$('.help-disabled-cc a').each(function(element){
                element.show();
            });

            this.isEdit = false;
        } else {
            this.ewayrapidToggleCcForm(false);
            $('ewayrapid_saved_edit').update(this.labelEdit);
            this.isEdit = true;
        }
        var validator = new Validation('co-payment-form');
        validator.validate();
        $('advice-validate-cc-type-auto-ewayrapid_saved_cc_number') && $('advice-validate-cc-type-auto-ewayrapid_saved_cc_number').hide();
    },

    ewayrapidToggleCcForm: function(isShow) {
        $$('.saved_token_fields input,.saved_token_fields select').each(function(ele) {
            isShow ? ele.enable() : ele.disable();
        });
        $$('.saved_token_fields').each(function(ele) {
            isShow ? ele.show() : ele.hide();
        });

        isShow && $('ewayrapid_saved_cc_number') ? $('ewayrapid_saved_cc_number').addClassName('validate-cc-number').addClassName('validate-cc-type-auto') : ($('ewayrapid_saved_cc_number') ? $('ewayrapid_saved_cc_number').removeClassName('validate-cc-number').removeClassName('validate-cc-type-auto') : '' );
    },

    ewayrapidSelectToken: function(tokenId) {
        $('ewayrapid_saved_cc_owner').setValue(this.savedTokens[tokenId]['Owner']);
        $('ewayrapid_saved_cc_number').setValue(this.savedTokens[tokenId]['Card']);
        $('ewayrapid_saved_expiration').setValue(this.savedTokens[tokenId]['ExpMonth']);
        $('ewayrapid_saved_expiration_yr').setValue(this.savedTokens[tokenId]['ExpYear']);
        $('ewayrapid_saved_cc_owner').focus();
    }
}

Validation.creditCartTypes = $H({
    // Add Diners Club, Maestro and Visa Electron card type
    'DC': [new RegExp('^3(?:0[0-5]|[68][0-9])[0-9]{11}$'), new RegExp('^[0-9]{3}$'), true],
    'VE': [new RegExp('^(4026|4405|4508|4844|4913|4917)[0-9]{12}|417500[0-9]{10}$'), new RegExp('^[0-9]{3}$'), true],
    'ME': [new RegExp('^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|6390)[0-9]{8,15}$'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],

    'SO': [new RegExp('^(6334[5-9]([0-9]{11}|[0-9]{13,14}))|(6767([0-9]{12}|[0-9]{14,15}))$'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],
    'VI': [new RegExp('^4[0-9]{12}([0-9]{3})?$'), new RegExp('^[0-9]{3}$'), true],
    'MC': [new RegExp('^5[1-5][0-9]{14}$'), new RegExp('^[0-9]{3}$'), true],
    'AE': [new RegExp('^3[47][0-9]{13}$'), new RegExp('^[0-9]{4}$'), true],
    'DI': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3}$'), true],
    'JCB': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3,4}$'), true],
//    'DICL': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3}$'), true],
    'SM': [new RegExp('(^(5[0678])[0-9]{11,18}$)|(^(6[^05])[0-9]{11,18}$)|(^(601)[^1][0-9]{9,16}$)|(^(6011)[0-9]{9,11}$)|(^(6011)[0-9]{13,16}$)|(^(65)[0-9]{11,13}$)|(^(65)[0-9]{15,18}$)|(^(49030)[2-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49033)[5-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49110)[1-2]([0-9]{10}$|[0-9]{12,13}$))|(^(49117)[4-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49118)[0-2]([0-9]{10}$|[0-9]{12,13}$))|(^(4936)([0-9]{12}$|[0-9]{14,15}$))'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],
    'OT': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false]
});

Validation.add('validate-cc-type-auto', 'Invalid credit card number or credit card type is not supported.',
    function(v, elm) {
        // remove credit card number delimiters such as "-" and space
        elm.value = removeDelimiters(elm.value);
        v         = removeDelimiters(v);
        var acceptedTypes = EwayPayment.supportCardTypes;

        var ccType = '';
        Validation.creditCartTypes.each(function(cardType) {
            $cardNumberPattern = cardType.value[0];
            if($cardNumberPattern && v.match($cardNumberPattern)) {
                ccType = cardType.key;

                // Correct JCB/DI type since they has identical pattern:
                if(ccType === 'DI' && v.indexOf('35') == 0) {
                    ccType = 'JCB';
                }

                throw $break;
            }
        });

        if(acceptedTypes.indexOf(ccType) == -1) {
            return false;
        }

        var ccTypeContainer = $(elm.id.substr(0,elm.id.indexOf('_cc_number')) + '_cc_type');
        if (ccTypeContainer) {
            ccTypeContainer.value = ccType;
        }

        return true;
    }
);

Validation.add('eway-validate-phone', 'Please enter a valid phone number.', function(v, elm) {
    return Validation.get('IsEmpty').test(v) || /^[0-9\+\*\(\)]{1,32}$/.test(v);
});

document.observe('dom:loaded', function(){
    /*
     var name = 'ewayrapid_saved_cc_owner';
     // Validate card name
     $('' + name).observe('keyup',function() {
     if($('ewayrapid_saved_cc_owner').up()
     .select('#advice-required-entry-ewayrapid_saved_cc_owner').length > 0) {
     $('ewayrapid_saved_cc_owner').up()
     .select('#advice-required-entry-ewayrapid_saved_cc_owner')[0].remove();
     }
     if($('ewayrapid_saved_cc_owner').value.length > 50) {
     $('ewayrapid_saved_cc_owner').addClassName('validation-failed');
     $('ewayrapid_saved_cc_owner').up().insert(
     '<div id="advice-required-entry-ewayrapid_saved_cc_owner" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('ewayrapid_saved_cc_owner').up()
     .select('#advice-required-entry-ewayrapid_saved_cc_owner').length > 0) {
     $('ewayrapid_saved_cc_owner').up()
     .select('#advice-required-entry-ewayrapid_saved_cc_owner')[0].remove();
     }
     // Remove class require on text field
     $('ewayrapid_saved_cc_owner').removeClassName('validation-failed');
     }
     });

     // First name
     $('address_firstname').observe('keyup',function() {
     if($('address_firstname').up()
     .select('#advice-required-entry-address_firstname').length > 0) {
     $('address_firstname').up()
     .select('#advice-required-entry-address_firstname')[0].remove();
     }
     if($('address_firstname').value.length > 50) {
     $('address_firstname').addClassName('validation-failed');
     $('address_firstname').up().insert(
     '<div id="advice-required-entry-address_firstname" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('ewayrapid_saved_cc_owner').up()
     .select('#advice-required-entry-address_firstname').length > 0) {
     $('address_firstname').up()
     .select('#advice-required-entry-address_firstname')[0].remove();
     }
     // Remove class require on text field
     $('address_firstname').removeClassName('validation-failed');
     }
     });

     // Last name
     $('address_lastname').observe('keyup',function() {
     if($('address_lastname').up()
     .select('#advice-required-entry-address_lastname').length > 0) {
     $('address_lastname').up()
     .select('#advice-required-entry-address_lastname')[0].remove();
     }
     if($('address_lastname').value.length > 50) {
     $('address_lastname').addClassName('validation-failed');
     $('address_lastname').up().insert(
     '<div id="advice-required-entry-address_lastname" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_lastname').up()
     .select('#advice-required-entry-address_lastname').length > 0) {
     $('address_lastname').up()
     .select('#advice-required-entry-address_lastname')[0].remove();
     }
     // Remove class require on text field
     $('address_lastname').removeClassName('validation-failed');
     }
     });

     // Company
     $('address_company').observe('keyup',function() {
     if($('address_company').up()
     .select('#advice-required-entry-address_company').length > 0) {
     $('address_company').up()
     .select('#advice-required-entry-address_company')[0].remove();
     }
     if($('address_company').value.length > 50) {
     $('address_company').addClassName('validation-failed');
     $('address_company').up().insert(
     '<div id="advice-required-entry-address_company" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_company').up()
     .select('#advice-required-entry-address_company').length > 0) {
     $('address_company').up()
     .select('#advice-required-entry-address_company')[0].remove();
     }
     // Remove class require on text field
     $('address_company').removeClassName('validation-failed');
     }
     });

     // job description
     $('address_job_description').observe('keyup',function() {
     if($('address_job_description').up()
     .select('#advice-required-entry-address_job_description').length > 0) {
     $('address_job_description').up()
     .select('#advice-required-entry-address_job_description')[0].remove();
     }
     if($('address_job_description').value.length > 50) {
     $('address_job_description').addClassName('validation-failed');
     $('address_job_description').up().insert(
     '<div id="advice-required-entry-address_job_description" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_job_description').up()
     .select('#advice-required-entry-address_job_description').length > 0) {
     $('address_job_description').up()
     .select('#advice-required-entry-address_job_description')[0].remove();
     }
     // Remove class require on text field
     $('address_job_description').removeClassName('validation-failed');
     }
     });

     // Phone
     $('address_telephone').observe('keyup',function() {
     if($('address_telephone').up()
     .select('#advice-required-entry-address_telephone').length > 0) {
     $('address_telephone').up()
     .select('#advice-required-entry-address_telephone')[0].remove();
     }
     if($('address_telephone').value.length > 32) {
     $('address_telephone').addClassName('validation-failed');
     $('address_telephone').up().insert(
     '<div id="advice-required-entry-address_telephone" ' +
     'class="validation-advice" style="">Maxlength of this field is 32.</div>');
     } else {
     // Remove notify
     if($('address_telephone').up()
     .select('#advice-required-entry-address_telephone').length > 0) {
     $('address_telephone').up()
     .select('#advice-required-entry-address_telephone')[0].remove();
     }
     // Remove class require on text field
     $('address_telephone').removeClassName('validation-failed');
     }
     });
     $('address_mobile').observe('keyup',function() {
     if($('address_mobile').up()
     .select('#advice-required-entry-address_mobile').length > 0) {
     $('address_mobile').up()
     .select('#advice-required-entry-address_mobile')[0].remove();
     }
     if($('address_mobile').value.length > 32) {
     $('address_mobile').addClassName('validation-failed');
     $('address_mobile').up().insert(
     '<div id="advice-required-entry-address_mobile" ' +
     'class="validation-advice" style="">Maxlength of this field is 32.</div>');
     } else {
     // Remove notify
     if($('address_mobile').up()
     .select('#advice-required-entry-address_mobile').length > 0) {
     $('address_mobile').up()
     .select('#advice-required-entry-address_mobile')[0].remove();
     }
     // Remove class require on text field
     $('address_mobile').removeClassName('validation-failed');
     }
     });
     $('address_email').observe('keyup',function() {
     if($('address_email').up()
     .select('#advice-required-entry-address_email').length > 0) {
     $('address_email').up()
     .select('#advice-required-entry-address_email')[0].remove();
     }
     if($('address_email').value.length > 50) {
     $('address_email').addClassName('validation-failed');
     $('address_email').up().insert(
     '<div id="advice-required-entry-address_email" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_email').up()
     .select('#advice-required-entry-address_email').length > 0) {
     $('address_email').up()
     .select('#advice-required-entry-address_email')[0].remove();
     }
     // Remove class require on text field
     $('address_email').removeClassName('validation-failed');
     }
     });
     $('address_fax').observe('keyup',function() {
     if($('address_fax').up()
     .select('#advice-required-entry-address_fax').length > 0) {
     $('address_fax').up()
     .select('#advice-required-entry-address_fax')[0].remove();
     }
     if($('address_fax').value.length > 32) {
     $('address_fax').addClassName('validation-failed');
     $('address_fax').up().insert(
     '<div id="advice-required-entry-address_fax" ' +
     'class="validation-advice" style="">Maxlength of this field is 32.</div>');
     } else {
     // Remove notify
     if($('address_fax').up()
     .select('#advice-required-entry-address_fax').length > 0) {
     $('address_fax').up()
     .select('#advice-required-entry-address_fax')[0].remove();
     }
     // Remove class require on text field
     $('address_fax').removeClassName('validation-failed');
     }
     });
     $('address_street_1').observe('keyup',function() {
     if($('address_street_1').up()
     .select('#advice-required-entry-address_street_1').length > 0) {
     $('address_street_1').up()
     .select('#advice-required-entry-address_street_1')[0].remove();
     }
     if($('address_street_1').value.length > 50) {
     $('address_street_1').addClassName('validation-failed');
     $('address_street_1').up().insert(
     '<div id="advice-required-entry-address_street_1" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_street_1').up()
     .select('#advice-required-entry-address_street_1').length > 0) {
     $('address_street_1').up()
     .select('#advice-required-entry-address_street_1')[0].remove();
     }
     // Remove class require on text field
     $('address_street_1').removeClassName('validation-failed');
     }
     });
     $('address_street_2').observe('keyup',function() {
     if($('address_street_2').up()
     .select('#advice-required-entry-address_street_2').length > 0) {
     $('address_street_2').up()
     .select('#advice-required-entry-address_street_2')[0].remove();
     }
     if($('address_street_2').value.length > 50) {
     $('address_street_2').addClassName('validation-failed');
     $('address_street_2').up().insert(
     '<div id="advice-required-entry-address_street_2" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_street_2').up()
     .select('#advice-required-entry-address_street_2').length > 0) {
     $('address_street_2').up()
     .select('#advice-required-entry-address_street_2')[0].remove();
     }
     // Remove class require on text field
     $('address_street_2').removeClassName('validation-failed');
     }
     });
     $('address_city').observe('keyup',function() {
     if($('address_city').up()
     .select('#advice-required-entry-address_city').length > 0) {
     $('address_city').up()
     .select('#advice-required-entry-address_city')[0].remove();
     }
     if($('address_city').value.length > 50) {
     $('address_city').addClassName('validation-failed');
     $('address_city').up().insert(
     '<div id="advice-required-entry-address_city" ' +
     'class="validation-advice" style="">Maxlength of this field is 30.</div>');
     } else {
     // Remove notify
     if($('address_city').up()
     .select('#advice-required-entry-address_city').length > 0) {
     $('address_city').up()
     .select('#advice-required-entry-address_city')[0].remove();
     }
     // Remove class require on text field
     $('address_city').removeClassName('validation-failed');
     }
     });
     $('address_region').observe('keyup',function() {
     if($('address_region').up()
     .select('#advice-required-entry-address_region').length > 0) {
     $('address_region').up()
     .select('#advice-required-entry-address_region')[0].remove();
     }
     if($('address_region').value.length > 50) {
     $('address_region').addClassName('validation-failed');
     $('address_region').up().insert(
     '<div id="advice-required-entry-address_region" ' +
     'class="validation-advice" style="">Maxlength of this field is 50.</div>');
     } else {
     // Remove notify
     if($('address_region').up()
     .select('#advice-required-entry-address_region').length > 0) {
     $('address_region').up()
     .select('#advice-required-entry-address_region')[0].remove();
     }
     // Remove class require on text field
     $('address_region').removeClassName('validation-failed');
     }
     });
     $('address_zip').observe('keyup',function() {
     if($('address_zip').up()
     .select('#advice-required-entry-address_zip').length > 0) {
     $('address_zip').up()
     .select('#advice-required-entry-address_zip')[0].remove();
     }
     if($('address_zip').value.length > 50) {
     $('address_zip').addClassName('validation-failed');
     $('address_zip').up().insert(
     '<div id="advice-required-entry-address_zip" ' +
     'class="validation-advice" style="">Maxlength of this field is 30.</div>');
     } else {
     // Remove notify
     if($('address_zip').up()
     .select('#advice-required-entry-address_zip').length > 0) {
     $('address_zip').up()
     .select('#advice-required-entry-address_zip')[0].remove();
     }
     // Remove class require on text field
     $('address_zip').removeClassName('validation-failed');
     }
     });
     */

});
