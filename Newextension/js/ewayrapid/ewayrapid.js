var EwayPayment = Class.create();
EwayPayment.isEwayRapidMethod = function(method) {
    return ("ewayrapid_saved" === method || "ewayrapid_notsaved" === method || "ewayrapid_ewayone" === method);
};
EwayPayment.supportCardTypes = ['AE', 'VI', 'MC', 'JCB', 'DC', 'VE', 'ME'];
EwayPayment.prototype = {
    paymentUrl : null,
    ewayPayment: this,
    initialize: function(form, encryptionKey) {
        if (form) {
            // Init client-side encryption
            if (typeof eCrypt == 'function') {
                form.writeAttribute('data-eway-encrypt-key', encryptionKey);
                eCrypt && eCrypt.init();
            }
        }
    },

    savePaymentWithEncryption: function() {
        if (checkout.loadWaiting!=false) return;
        var validator = new Validation(this.form);
        if (this.validate() && validator.validate()) {
            checkout.setLoadWaiting('payment');
            var form = $(this.form);
            if($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var form = ewayForm.encryptForm(formToEncrypt, false);
            }
            this.ewayForm = form;
            var request = new Ajax.Request(
                this.saveUrl,
                {
                    method:'post',
                    onComplete: this.onComplete,
                    onSuccess: this.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout),
                    parameters: $(form).serialize()
                }
            );
        }
    },

    savePaymentWithTransEncryption: function() {
        if (checkout.loadWaiting != false) return;
        var validator = new Validation(this.form);
        if (this.validate() && validator.validate()) {
            checkout.setLoadWaiting('payment');
            var form = $(this.form);
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                var _method = $$("input[name='payment[method]']:checked")[0].getValue();
                var _transparent_method = '';

                if (_method == 'ewayrapid_notsaved' && $$("input[name='payment[transparent_notsaved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_notsaved]']:checked")[0];
                } else if (_method == 'ewayrapid_ewayone' && $$("input[name='payment[transparent_saved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_saved]']:checked")[0];
                }

                if (_transparent_method != '' && $(_transparent_method.id).getValue() == creditcard) {
                    if ($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                        var ewayForm = new EwayForm();
                        var formToEncrypt = ewayForm.findFormToEncrypt();
                        var form = ewayForm.encryptForm(formToEncrypt, false);
                    }
                }

                this.ewayForm = form;
            }
            var request = new Ajax.Request(
                this.saveUrl,
                {
                    method: 'post',
                    onComplete: this.onComplete,
                    onSuccess: this.onSave,
                    onFailure: checkout.ajaxFailure.bind(checkout),
                    parameters: $(form).serialize()
                }
            );
        }
    },

    saveReviewWithEncryption: function() {
        if (EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
            if (checkout.loadWaiting!=false) return;
            checkout.setLoadWaiting('review');
            //var params = Form.serialize(payment.form);
            var params = payment.ewayForm.serialize();
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
        } else {
            ewayPayment.saveUrl = review.saveUrl;
            ewayPayment.onComplete = review.onComplete;
            ewayPayment.onSave = review.onSave;
            ewayPayment.agreementsForm = review.agreementsForm;
            ewayPayment.ewaysavedOldOrder();
        }
    },

    saveReviewWithEncryptionTrans: function () {
        if (EwayPayment.isEwayRapidMethod(payment.currentMethod) && ewayPayment.paymentUrl != null) {
            $('review-please-wait') && $('review-please-wait').show();
            $('review-buttons-container') && $('review-buttons-container').down('button').hide();

            var parameters = {};
            if($('p_method_ewayrapid_transparent_visa') && $('p_method_ewayrapid_transparent_visa').checked){
                if(!$('visa_checkout_call_id').value){
                    document.getElementById('visa_checkout_button').click();
                    return;
                }else{
                    parameters.visa_checkout_call_id = $('visa_checkout_call_id').value;
                }

            }

            var request = new Ajax.Request(
                ewayPayment.paymentUrl,
                {
                    method: 'post',
                    parameters: parameters,
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
            ewayPayment.saveUrl = review.saveUrl;
            ewayPayment.onComplete = review.onComplete;
            ewayPayment.onSave = review.onSave;
            ewayPayment.agreementsForm = review.agreementsForm;
            ewayPayment.ewaysavedOldOrder();
        }
    },

    saveOrderWithIframe: function () {
        if (EwayPayment.isEwayRapidMethod(payment.currentMethod) && ewayPayment.paymentUrl != null) {
            $('review-please-wait') && $('review-please-wait').show();
            $('review-buttons-container') && $('review-buttons-container').down('button').hide();

            var request = new Ajax.Request(
                ewayPayment.paymentUrl,
                {
                    method: 'post',
                    onComplete: {},
                    onSuccess: function (trans) {
                        var res = trans.responseText.evalJSON();
                        if(res.success && res.url && res.returnUrl){

                            var eWAYConfig = {
                                sharedPaymentUrl: res.url
                            };

                            ewayReturnUrl = res.returnUrl;

                            eCrypt.showModalPayment(eWAYConfig, eWayRapidCallback);

                        }else if(res.message){
                            alert(res.message);
                            $('review-please-wait') && $('review-please-wait').hide();
                            $('review-buttons-container') && $('review-buttons-container').down('button').show();
                        }
                    },
                    onFailure: {}
                }
            );
        } else {
            ewayPayment.saveUrl = review.saveUrl;
            ewayPayment.onComplete = review.onComplete;
            ewayPayment.onSave = review.onSave;
            ewayPayment.agreementsForm = review.agreementsForm;
            ewayPayment.ewaysavedOldOrder();
        }
    },

    submitForm: function () {
        eCrypt.submitForm();
    },

    submitAdminOrder: function() {
        if(editForm.validator && editForm.validator.validate()) {
            if($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var submitForm = ewayForm.encryptForm(formToEncrypt, true);
            } else {
                var submitForm = editForm;
            }
            if (this.orderItemChanged) {
                if (confirm('You have item changes')) {
                    if (submitForm.submit()) {
                        disableElements('save');
                    }
                } else {
                    this.itemsUpdate();
                }
            } else {
                if (submitForm.submit()) {
                    disableElements('save');
                }
            }
        }
    },

    submitAdminOrderIFrame: function () {
        if(editForm.validator && editForm.validator.validate()) {
            if($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var submitForm = ewayForm.encryptForm(formToEncrypt, true);
                var request = new Ajax.Request(
                    ewayPayment.paymentUrl,
                    {
                        method: 'post',
                        onComplete: {},
                        parameters: Form.serialize(submitForm, true),
                        onSuccess: function (trans) {
                            var res = trans.responseText.evalJSON();
                            if(res.success && res.url){

                                var eWAYConfig = {
                                    sharedPaymentUrl: res.url
                                };
                                ewayReturnUrl = res.returnUrl;

                                eCrypt.showModalPayment(eWAYConfig, eWayRapidCallback);

                            }else if(res.message){
                                alert(res.message);
                                $('review-please-wait') && $('review-please-wait').hide();
                                $('review-buttons-container') && $('review-buttons-container').down('button').show();
                            }
                        },
                        onFailure: {}
                    }
                );
            } else {
                ewayPayment.submitAdminOrder();
            }
        }
    },

    MultiShipping: {
      submit: function() {
          var validator = new Validation('multishipping-billing-form');
          if (validator.validate()) {
            var form = $(this.form);
            if($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var form = ewayForm.encryptForm(formToEncrypt, false);
            }
            form.submit();
          }
          return false;
      }
    },

    OneStepCheckout: {
        switchMethod: function(method) {
            $$('.payment-method .form-list').each(
                function(form) {
                //form.style.display = 'none';
                var elements = form.select('input').concat(form.select('select')).concat(form.select('textarea'));
                for (var i=0; i<elements.length; i++) elements[i].disabled = true;
                }
            );

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
        },

        saveOrderWithIframe: function (formData) {
            var request = new Ajax.Request(
                ewayPayment.paymentUrl,
                {
                    method: 'post',
                    onComplete: {},
                    parameters: formData,
                    onSuccess: function (trans) {
                        var res = trans.responseText.evalJSON();
                        if(res.success && res.url && res.returnUrl){

                            var eWAYConfig = {
                                sharedPaymentUrl: res.url
                            };

                            ewayReturnUrl = res.returnUrl;

                            eCrypt.showModalPayment(eWAYConfig, eWayRapidCallback);

                        }else if(res.message){
                            alert(res.message);
                        }
                    },
                    onFailure: {}
                }
            );
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

                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var encryptedForm = ewayForm.encryptForm(formToEncrypt, false);

                params = Form.serialize(encryptedForm, true);

                urlSuffix = urlSuffix || '';
                var request = new Ajax.Request(
                    this.urls.save + urlSuffix, {
                    method:'post',
                    parameters:params,
                    onSuccess: this.setResponse.bind(this),
                    onFailure: this.ajaxFailure.bind(this)
                    }
                );
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
                } else if (_method == 'ewayrapid_ewayone' && $$("input[name='payment[transparent_saved]']:checked").length > 0) {
                    _transparent_method = $$("input[name='payment[transparent_saved]']:checked")[0];
                }

                if($('p_method_ewayrapid_transparent_visa') && $('p_method_ewayrapid_transparent_visa').checked){
                    if(!$('visa_checkout_call_id').value){
                        document.getElementById('visa_checkout_button').click();
                        return;
                    }
                }

                //if (_transparent_method != '' && $(_transparent_method.id).getValue() == creditcard) {
                    if ($$("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {

                        var ewayForm = new EwayForm();
                        var formToEncrypt = ewayForm.findFormToEncrypt();
                        var encryptedForm = ewayForm.encryptForm(formToEncrypt, false);
                        params = Form.serialize(encryptedForm, true);
                    }
                //}

                urlSuffix = urlSuffix || '';
                var request = new Ajax.Request(
                    this.urls.save + urlSuffix, {
                    method:'post',
                    parameters:params,
                    onSuccess: this.setResponse.bind(this),
                    onFailure: this.ajaxFailure.bind(this)
                    }
                );
            } else if(typeof this.ewayOldSave == 'function') {
                this.ewayOldSave(urlSuffix, forceSave);
            }
        },

        setResponse: function(response){
            try {
                response = response.responseText.evalJSON();
            } catch (err) {
                alert('An error has occured during request processing. Try again please');
                checkout.setLoadWaiting(false);
                $('review-please-wait').hide();
                return false;
            }

            if (response.redirect) {

                var request = new Ajax.Request(
                    response.redirect,
                    {
                        method: 'post',
                        onComplete: {},
                        onSuccess: function (trans) {
                            checkout.setLoadWaiting(false);
                            var res = trans.responseText.evalJSON();
                            if(res.success && res.url && res.returnUrl){

                                var eWAYConfig = {
                                    sharedPaymentUrl: res.url
                                };

                                ewayReturnUrl = res.returnUrl;

                                eCrypt.showModalPayment(eWAYConfig, eWayRapidCallback);

                            }else if(res.message){
                                alert(res.message);
                                $('review-please-wait').hide();
                            }
                        },
                        onFailure: {}
                    }
                );

                return;
            }

            if (response.order_created) {
                window.location = this.urls.success;
                return;
            } else {
                if (response.captcha) {
                    this.updateCaptcha(response.captcha);
                }
                if (response.error_messages) {
                    var msg = response.error_messages;
                    if (typeof(msg) == 'object') {
                        msg = msg.join("\n");
                    }
                    alert(msg);
                } else if (response.message) {
                    var msg = response.message;
                    if (typeof(msg) == 'object') {
                        msg = msg.join("\n");
                    }
                    alert(msg);
                }
            }

            checkout.setLoadWaiting(false);
            $('review-please-wait').hide();

            if (response.update_section) {
                for (var i in response.update_section) {
                    var el = $('checkout-' + i + '-load');
                    if (el) {
                        var data = {};
                        el.select('input, select').each(
                            function(input) {
                            if ('radio' == input.type || 'checkbox' == input.type) {
                                data[input.id] = input.checked;
                            } else {
                                data[input.id] = input.getValue();
                            }
                            }
                        );

                        el.update(response.update_section[i])
                            .setOpacity(1)
                            .removeClassName('loading');

                        if (i == 'coupon-discount' || i == 'giftcard') {
                            continue;
                        }

                        for (var j in data) {
                            if (!j) {
                                continue;
                            }
                            var input = el.down('#' + j);
                            if (input) {
                                if ('radio' == input.type || 'checkbox' == input.type) {
                                    input.checked = data[j];
                                } else {
                                    input.setValue(data[j]);
                                }
                            }
                        }
                    }

                    if (i === 'shipping-method') {
                        shippingMethod.addObservers();
                    } else if (i === 'review') {
                        this.addCartObservers();
                    }
                }
            }

            if (response.method) {
                if ('centinel' == response.method) {
                    this.showCentinel();
                } else if (0 === response.method.indexOf('billsafe')) {
                    lpg.open();
                    var form = $('firecheckout-form');
                    form.action = BILLSAFE_FORM_ACTION;
                    form.submit();
                }

                else if ('sagepayserver' === response.method
                    || 'sagepayform' === response.method
                    || 'sagepaydirectpro' === response.method
                    || 'sagepaypaypal' === response.method) {

                    var SageServer = new EbizmartsSagePaySuite.Checkout(
                        {
                        //'checkout'  : checkout
                        }
                    );
                    SageServer.code = response.method;
                    SageServer.reviewSave();
                    // SageServer.setPaymentMethod();
                    // SageServer.reviewSave({'tokenSuccess':true});
                }
            }

            if (response.popup) {
                this.showPopup(response.popup);
            } else if (response.body) {
                $(document.body).insert(
                    {
                    'bottom': response.body.content
                    }
                );
            }

            // ogone fix
            if (payment.toggleOpsCcInputs) {
                payment.toggleOpsCcInputs();
            }
            // ogone fix

            return false;
        }
    },

    IWDOnePageCheckout: {
        savePayment: function() {
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                if (IWD.OPC.Checkout.xhr!=null){
                    IWD.OPC.Checkout.xhr.abort();
                }
                IWD.OPC.Checkout.showLoader();

                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var form = ewayForm.encryptForm(formToEncrypt, false);
                form = $j(form).serializeArray();
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

                if ($('p_method_ewayrapid_transparent_visa') && $('p_method_ewayrapid_transparent_visa').checked) {
                    if (!$('visa_checkout_call_id').value) {
                        document.getElementById('visa_checkout_button').click();
                        return;
                    }
                }

                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var form = ewayForm.encryptForm(formToEncrypt, false);
                form = $j(form).serializeArray();
                IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
            } else if(typeof IWD.OPC.ewayOldSavePayment == 'function') {
                IWD.OPC.ewayOldSavePayment();
            }
        },

        preparePaymentResponse: function(response){
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                IWD.OPC.Checkout.xhr = null;

                IWD.OPC.agreements = $j('#checkout-agreements').serializeArray();

                IWD.OPC.getSubscribe();

                if (typeof(response.review)!= "undefined"){
                    IWD.OPC.Decorator.updateGrandTotal(response);
                    $j('#opc-review-block').html(response.review);
                    IWD.OPC.Checkout.removePrice();

                    // need to recheck subscribe and agreenet checkboxes
                    IWD.OPC.recheckItems();
                }

                if (typeof(response.error) != "undefined"){

                    IWD.OPC.Plugin.dispatch('error');

                    $j('.opc-message-container').html(response.error);
                    $j('.opc-message-wrapper').show();
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.unlockPlaceOrder();
                    IWD.OPC.saveOrderStatus = false;

                    return;
                }

                //SOME PAYMENT METHOD REDIRECT CUSTOMER TO PAYMENT GATEWAY
                if (typeof(response.redirect) != "undefined" && IWD.OPC.saveOrderStatus===true){
                    IWD.OPC.Checkout.xhr = null;
                    IWD.OPC.Plugin.dispatch('redirectPayment', response.redirect);
                    if (IWD.OPC.Checkout.xhr==null){
                        var request = new Ajax.Request(
                            response.redirect,
                            {
                                method: 'post',
                                onComplete: {},
                                onSuccess: function (trans) {
                                    var res = trans.responseText.evalJSON();
                                    if(res.success && res.url && res.returnUrl){

                                        var eWAYConfig = {
                                            sharedPaymentUrl: res.url
                                        };

                                        ewayReturnUrl = res.returnUrl;

                                        IWD.OPC.Checkout.hideLoader();
                                        eCrypt.showModalPayment(eWAYConfig, eWayRapidCallback);

                                    }else if(res.message){
                                        alert(res.message);
                                        IWD.OPC.Checkout.hideLoader();
                                        IWD.OPC.Checkout.unlockPlaceOrder();
                                    }
                                },
                                onFailure: {}
                            }
                        );
                    }
                    else
                    {
                        IWD.OPC.Checkout.hideLoader();
                        IWD.OPC.Checkout.unlockPlaceOrder();
                    }

                    return;
                }

                if (IWD.OPC.saveOrderStatus===true){
                    IWD.OPC.saveOrder();
                }
                else
                {
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.unlockPlaceOrder();
                }

                IWD.OPC.Plugin.dispatch('savePaymentAfter');
            }else if(typeof IWD.OPC.ewayOldPreparePaymentResponse == 'function') {
                IWD.OPC.ewayOldPreparePaymentResponse();
            }

        },

        saveOrder: function() {
            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {

                var ewayForm = new EwayForm();
                var formToEncrypt = ewayForm.findFormToEncrypt();
                var form = ewayForm.encryptForm(formToEncrypt, false);
                form = $j(form).serializeArray();

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
                    if ($('p_method_ewayrapid_transparent_visa') && $('p_method_ewayrapid_transparent_visa').checked) {
                        if (!$('visa_checkout_call_id').value) {
                            document.getElementById('visa_checkout_button').click();
                            return;
                        }
                    }
                    this.submit(this.getFormData(), 'save_payment_methods');
                }
            }
        },
        submit: function (params, action) {

            this.showLoadinfo();

            params.action = action;

            var request = new Ajax.Request(
                this.url,
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
                }
            );
        },
        getFormData: function () {
            //var form_data = $('gcheckout-onepage-form').serialize(true);
            var ewayForm = new EwayForm();
            var formToEncrypt = ewayForm.findFormToEncrypt();
            var form = ewayForm.encryptForm(formToEncrypt, false);

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
        },
        saveorder: function () {

            if(EwayPayment.isEwayRapidMethod(payment.currentMethod)){
                this.showLoadinfo();

                var params = this.getFormData();

                var request = new Ajax.Request(
                    this.save_order_url,
                    {
                        method: 'post',
                        parameters: params,
                        onSuccess: function (transport) {
                            eval('var response = ' + transport.responseText);

                            if (response.redirect) {

                                var request = new Ajax.Request(
                                    response.redirect,
                                    {
                                        method: 'post',
                                        onComplete: {},
                                        onSuccess: function (trans) {
                                            var res = trans.responseText.evalJSON();
                                            if(res.success && res.url && res.returnUrl){

                                                var eWAYConfig = {
                                                    sharedPaymentUrl: res.url
                                                };

                                                ewayReturnUrl = res.returnUrl;

                                                eCrypt.showModalPayment(eWAYConfig, eWayRapidCallback);

                                            }else if(res.message){
                                                alert(res.message);
                                                checkout.hideLoadinfo();
                                            }
                                        },
                                        onFailure: {}
                                    }
                                );

                            } else if (response.error) {
                                if (response.message) {
                                    alert(response.message);
                                }
                            } else if (response.update_section) {
                                this.accordion.currentSection = 'opc-review';
                                this.innerHTMLwithScripts($('checkout-update-section'), response.update_section.html);

                            }
                            this.hideLoadinfo();

                        }.bind(this),
                        onFailure: function () {

                        }
                    }
                );
            }else if(typeof checkout.saveOrderOld == 'function') {
                checkout.saveOrderOld();
            }
        }
    },
    MageWorld: {
        submit: function (e, notshipmethod, redirect) {
            $MW_Onestepcheckout('#co-payment-form').show();
            var form = new VarienForm('onestep_form');
            var logic=true;

            // check for shipping type
            if(!$MW_Onestepcheckout('input[name=payment\\[method\\]]:checked').val() || !notshipmethod){
                logic=false;
            }
            if(!$MW_Onestepcheckout('input[name=payment\\[method\\]]:checked').val()){
                if(!$MW_Onestepcheckout('#advice-required-entry_payment').length) {
                $MW_Onestepcheckout('#checkout-payment-method-load').append('<dt><div class="validation-advice" id="advice-required-entry_payment" style="">'+message_payment+'</div></dt>');
                //if($MW_Onestepcheckout('#advice-required-entry_payment').attr('display')!="none"){
                //$MW_Onestepcheckout('#advice-required-entry_payment').css('display','block');
                }
            }
            else
            $MW_Onestepcheckout('#advice-required-entry_payment').remove();
            //$MW_Onestepcheckout('#advice-required-entry_payment').css('display','none');

            if(!notshipmethod){
                if(!$MW_Onestepcheckout('#advice-required-entry_shipping').length){
                $MW_Onestepcheckout('#checkout-shipping-method-loadding').append('<dt><div class="validation-advice" id="advice-required-entry_shipping" style="">'+message_ship+'</div></dt>');
                //if($MW_Onestepcheckout('#advice-required-entry_shipping').attr('display')!="none"){
                //$MW_Onestepcheckout('#advice-required-entry_shipping').css('display','block');
                }

            }
            else
            $MW_Onestepcheckout('#advice-required-entry_shipping').remove();
            //$MW_Onestepcheckout('#advice-required-entry_shipping').css('display','none');

            if(!form.validator.validate())    {
                if(logined()!=1){
                val=$MW_Onestepcheckout('#billing\\:email').val();
                emailvalidated=Validation.get('IsEmpty').test(val) || /^([a-z0-9,!\#\$%&'\*\+\/=\?\^_`\{\|\}~-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z0-9,!\#\$%&'\*\+\/=\?\^_`\{\|\}~-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*@([a-z0-9-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z0-9-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*\.(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]){2,})$/i.test(val);
                if(val!="" && emailvalidated){
                    updateEmailmsg(val);
                }
                }
                //val_emailbill_before=val;
                Event.stop(e);
            }
            else{
                if(logined()!=1){
                    //$MW_Onestepcheckout('#billing\\:email').blur(function(event){
                    //val=this.value;
                    var msgerror=1;
                    val=$MW_Onestepcheckout('#billing\\:email').val();
                    emailvalidated=Validation.get('IsEmpty').test(val) || /^([a-z0-9,!\#\$%&'\*\+\/=\?\^_`\{\|\}~-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z0-9,!\#\$%&'\*\+\/=\?\^_`\{\|\}~-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*@([a-z0-9-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z0-9-]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*\.(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]){2,})$/i.test(val);
                    if(val!="" && emailvalidated){
                        msgerror=updateEmailmsg(val);
                    }
                    //val_emailbill_before=val;
                    if(msgerror==0){
                        return false;
                    }
                }

                if(logic){
                if($MW_Onestepcheckout("input[id*='ewayrapid_'][name='payment[method]']:checked").length > 0) {
                    if ($('p_method_ewayrapid_transparent_visa') && $('p_method_ewayrapid_transparent_visa').checked) {
                        if (!$('visa_checkout_call_id').value) {
                            document.getElementById('visa_checkout_button').click();
                            return;
                        }
                    }
                    eCrypt.submitForm();
                } else {
                    $MW_Onestepcheckout('#onestep_form').submit();
                }

                $MW_Onestepcheckout('#loading-mask').css('display','block');
                $MW_Onestepcheckout('.btn-checkout').attr("disabled","disabled");
                }
                else {
                    return false;
                }
            }
            return false;
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

        $('ewayrapid_ewayone_token') && $('ewayrapid_ewayone_token').observe('change', this.onSavedTokenChanged.bind(this));

        $('ewayrapid_ewayone_edit') && $('ewayrapid_ewayone_edit').observe('click', this.onEditClick.bind(this));

        if(this.tokenCount == 1) {
            // Show credit card form in case customer does not have saved credit card (only 'Add new card' option)
            this.ewayrapidToggleCcForm(true);
        } else {
            this.onSavedTokenChanged();
        }
    },

    onSavedTokenChanged: function() {
        if(typeof secureForm !== 'undefined'){
            secureForm.setupSecureField();
        }
        if($('ewayrapid_ewayone_token') && !$('ewayrapid_ewayone_token').disabled && $('ewayrapid_ewayone_token').value == 'new') {
            this.ewayrapidToggleCcForm(true);
            this.ewayrapidSelectToken('new');
            $('ewayrapid_ewayone_cc_type') && $('ewayrapid_ewayone_cc_type').setValue('');
            $('ewayrapid_ewayone_edit') && $('ewayrapid_ewayone_edit').hide();
            $$('.help-disabled-cc a').each(
                function(element){
                element.hide();
                }
            );
        } else {
            this.ewayrapidToggleCcForm(false);
            $('ewayrapid_ewayone_cc_type') && $('ewayrapid_ewayone_cc_type').setValue(this.savedTokens[$('ewayrapid_ewayone_token').getValue()]['Type']);
            if($('ewayrapid_ewayone_edit')) {
                this.isEdit = true;
                $('ewayrapid_ewayone_edit').update(this.labelEdit);
                $('ewayrapid_ewayone_edit').show();
            }
        }
        $('ewayrapid_ewayone_cc_cid') && $('ewayrapid_ewayone_cc_cid').setValue('');
    },

    onEditClick: function() {
        if(this.isEdit) {
            this.ewayrapidToggleCcForm(true);
            this.ewayrapidSelectToken($('ewayrapid_ewayone_token').getValue());
            $('ewayrapid_ewayone_edit').update(this.labelCancel);
            // This field does not exist.
            if($('ewayrapid_ewayone_cc_number')){
                $('ewayrapid_ewayone_cc_number').disable();
                $('ewayrapid_ewayone_cc_number').removeClassName('validate-cc-number').removeClassName('validate-cc-type-auto');
            }

            // Secure field
            if($('eway-secure-field-card-edit')){
                $('eway-secure-field-card-edit').show();
                $('eway-secure-field-card').hide();
            }

            $$('.help-disabled-cc a').each(
                function(element){
                    element.show();
                }
            );

            this.isEdit = false;
        } else {
            this.ewayrapidToggleCcForm(false);

            if($('eway-secure-field-card-edit')){
                $('eway-secure-field-card-edit').hide();
                $('eway-secure-field-card').show();
            }
            $('ewayrapid_ewayone_edit').update(this.labelEdit);
            this.isEdit = true;
        }
        var validator = new Validation('co-payment-form');
        validator.validate();
        $('advice-validate-cc-type-auto-ewayrapid_ewayone_cc_number') && $('advice-validate-cc-type-auto-ewayrapid_ewayone_cc_number').hide();
    },

    ewayrapidToggleCcForm: function(isShow) {
        $$('.saved_token_fields input,.saved_token_fields select').each(
            function(ele) {
            isShow ? ele.enable() : ele.disable();
            }
        );
        $$('.saved_token_fields').each(
            function(ele) {
            isShow ? ele.show() : ele.hide();
            }
        );

        isShow && $('ewayrapid_ewayone_cc_number') ? $('ewayrapid_ewayone_cc_number').addClassName('validate-cc-number').addClassName('validate-cc-type-auto') : ($('ewayrapid_ewayone_cc_number') ? $('ewayrapid_ewayone_cc_number').removeClassName('validate-cc-number').removeClassName('validate-cc-type-auto') : '' );

        if($('eway-secure-field-edit-token')){
            isShow ? $('eway-secure-field-edit-token').setValue(1) : $('eway-secure-field-edit-token').setValue(0);
        }

        if($('eway-secure-field-card-edit') && $('eway-secure-field-card')){
            isShow ? $('eway-secure-field-card-edit').hide() && $('eway-secure-field-card').show()
                : $('eway-secure-field-card-edit').show() && $('eway-secure-field-card').hide();
        }
    },

    ewayrapidSelectToken: function(tokenId) {
        $('ewayrapid_ewayone_cc_owner') && $('ewayrapid_ewayone_cc_owner').setValue(this.savedTokens[tokenId]['Owner']);
        $('ewayrapid_ewayone_cc_number') && $('ewayrapid_ewayone_cc_number').setValue(this.savedTokens[tokenId]['Card']);
        $('ewayrapid_ewayone_expiration') && $('ewayrapid_ewayone_expiration').setValue(this.savedTokens[tokenId]['ExpMonth']);
        $('ewayrapid_ewayone_expiration_yr') && $('ewayrapid_ewayone_expiration_yr').setValue(this.savedTokens[tokenId]['ExpYear']);
        $('ewayrapid_ewayone_cc_owner') && $('ewayrapid_ewayone_cc_owner').focus();
        $('eway-secure-field-card-edit-input')
        && $('eway-secure-field-card-edit-input').setValue(this.savedTokens[tokenId]['Card'])
        && $('eway-secure-field-card-edit-input').disable();
    }
}

Validation.creditCartTypes = $H(
    {
    'DC': [new RegExp('^3(?:0[0-5]|[68][0-9])[0-9]{11}$'), new RegExp('^[0-9]{3}$'), true],
    'VE': [new RegExp('^(4026|4405|4508|4844|4913|4917)[0-9]{12}|417500[0-9]{10}$'), new RegExp('^[0-9]{3}$'), true],
    'ME': [new RegExp('^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|6390)[0-9]{8,15}$'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],

    'SO': [new RegExp('^(6334[5-9]([0-9]{11}|[0-9]{13,14}))|(6767([0-9]{12}|[0-9]{14,15}))$'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],
    'VI': [new RegExp('^4[0-9]{12}([0-9]{3})?$'), new RegExp('^[0-9]{3}$'), true],
    //'MC': [new RegExp('^5[1-5][0-9]{14}$'), new RegExp('^[0-9]{3}$'), true],
    // For 2017 - new MasterCard Range 2221-2720
    'MC': [new RegExp('(^5[1-5][0-9]{14}$)|(^2221[0-9]{12}$)|(^222[2-9][0-9]{12}$)|(^22[3-9][0-9]{13}$)|(^2[3-6][0-9]{14}$)|(^2720[0-9]{12}$)|(^27[0-1][0-9]{13}$)'), new RegExp('^[0-9]{3}$'), true],
    'AE': [new RegExp('^3[47][0-9]{13}$'), new RegExp('^[0-9]{4}$'), true],
    'DI': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3}$'), true],
    'JCB': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3,4}$'), true],
    //    'DICL': [new RegExp('^(30[0-5][0-9]{13}|3095[0-9]{12}|35(2[8-9][0-9]{12}|[3-8][0-9]{13})|36[0-9]{12}|3[8-9][0-9]{14}|6011(0[0-9]{11}|[2-4][0-9]{11}|74[0-9]{10}|7[7-9][0-9]{10}|8[6-9][0-9]{10}|9[0-9]{11})|62(2(12[6-9][0-9]{10}|1[3-9][0-9]{11}|[2-8][0-9]{12}|9[0-1][0-9]{11}|92[0-5][0-9]{10})|[4-6][0-9]{13}|8[2-8][0-9]{12})|6(4[4-9][0-9]{13}|5[0-9]{14}))$'), new RegExp('^[0-9]{3}$'), true],
    'SM': [new RegExp('(^(5[0678])[0-9]{11,18}$)|(^(6[^05])[0-9]{11,18}$)|(^(601)[^1][0-9]{9,16}$)|(^(6011)[0-9]{9,11}$)|(^(6011)[0-9]{13,16}$)|(^(65)[0-9]{11,13}$)|(^(65)[0-9]{15,18}$)|(^(49030)[2-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49033)[5-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49110)[1-2]([0-9]{10}$|[0-9]{12,13}$))|(^(49117)[4-9]([0-9]{10}$|[0-9]{12,13}$))|(^(49118)[0-2]([0-9]{10}$|[0-9]{12,13}$))|(^(4936)([0-9]{12}$|[0-9]{14,15}$))'), new RegExp('^([0-9]{3}|[0-9]{4})?$'), true],
    'OT': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false]
    }
);

Validation.add(
    'validate-cc-type-auto', 'Invalid credit card number or credit card type is not supported.',
    function(v, elm) {
        // remove credit card number delimiters such as "-" and space
        elm.value = removeDelimiters(elm.value);
        v         = removeDelimiters(v);
        var acceptedTypes = EwayPayment.supportCardTypes;

        var ccType = '';
        Validation.creditCartTypes.each(
            function(cardType) {
            $cardNumberPattern = cardType.value[0];
            if($cardNumberPattern && v.match($cardNumberPattern)) {
                ccType = cardType.key;

                // Correct JCB/DI type since they has identical pattern:
                if(ccType === 'DI' && v.indexOf('35') == 0) {
                    ccType = 'JCB';
                }

                throw $break;
            }
            }
        );

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

Validation.add(
    'eway-validate-phone', 'Please enter a valid phone number.', function(v, elm) {
    return Validation.get('IsEmpty').test(v) || /^[0-9\+\*\(\)]{1,32}$/.test(v);
    }
);

var EwayForm = Class.create(
    {
    ewayPublicKeyAttribute : "data-eway-encrypt-key",
    ewayEncryptAttribute : "data-eway-encrypt-name",

    findFormToEncrypt: function () {
        var forms = document.getElementsByTagName('form');
        for (var i = 0; i < forms.length; i++) {
            var key = forms[i].getAttribute(this.ewayPublicKeyAttribute);
            if (key) {
                return forms[i];
            }
        }
        return null;
    },

    encryptForm: function(form, append) {
        var dataForm = this.cloneForm(form);
        dataForm.setAttribute('id', 'EWAY_FORM_' + form.getAttribute('id'));

        for (formNode = 0, formLength = dataForm.length; formNode < formLength; formNode++) {
            var dataAttribute = dataForm[formNode].getAttribute(this.ewayEncryptAttribute);
            if (dataAttribute != null) {
                dataForm[formNode].name = dataForm[formNode].getAttribute(this.ewayEncryptAttribute);
                dataForm[formNode].value = eCrypt.encryptValue(dataForm[formNode].value);
            }
        }

        // IE && FF will not submit clone form without insert to DOM
        if(append){
            dataForm.style.display = "none";
            form.parentNode.appendChild(dataForm);
        }

        return dataForm;
    },

    cloneForm: function (oldForm) {
        var newForm = oldForm.cloneNode(true);
        this.copySelectLists(oldForm, newForm);
        this.copyTextAreas(oldForm, newForm);
        this.copyCheckboxAndRadioValues(oldForm, newForm);
        return newForm;
    },

    copySelectLists: function (oldForm, newForm) {
        var selectElementsOld = oldForm.getElementsByTagName('Select');
        var selectElementsNew = newForm.getElementsByTagName('Select');

        for (var i = 0; i < selectElementsOld.length; i++) {
            selectElementsNew[i].selectedIndex = selectElementsOld[i].selectedIndex;
        }
    },

    copyTextAreas: function (oldForm, newForm) {
        var textElementsOld = oldForm.getElementsByTagName('TextArea');
        var textElementsNew = newForm.getElementsByTagName('TextArea');

        for (var i = 0; i < textElementsOld.length; i++) {
            textElementsNew[i].value = textElementsOld[i].value;
        }
    },

    copyCheckboxAndRadioValues: function (oldForm, newForm) {
        var inputsOld = oldForm.getElementsByTagName('input');
        var inputsNew = newForm.getElementsByTagName('input');

        for (var i = 0; i < inputsOld.length; i++) {
            if (inputsOld[i].type === 'checkbox' || inputsOld[i].type === 'radio') {
                inputsNew[i].checked = inputsOld[i].checked; // Need this for IE
                inputsNew[i].value = inputsOld[i].value; // Need this for IE10
            }
        }
    }
    }
);

var EwaySecureForm = Class.create(
    {
        publicApiKeyAttribute: 'eway-public-api-key',
        maskValuesAttribute: 'eway-secure-form-mask-value',
        autoComplete: 'eway-secure-form-auto-complete',
        publicApiKeyError: false,
        validFields: {
            'name': false,
            'card': false,
            'expiry': false
        },
        editingMode: false,

        initialize: function (cvnRequire) {
            if(cvnRequire){
                this.validFields['cvn'] = false;
            }
        },

        setupSecureField: function () {
            var publicKeyField = $(this.publicApiKeyAttribute);
            if(!publicKeyField) return;
            var publicApiKey = publicKeyField.value;
            var fieldStyles = '';
            var maskValue = $(this.maskValuesAttribute) && $(this.maskValuesAttribute).value === '1' ? true : false;
            var autoComplete = $(this.autoComplete) && $(this.autoComplete).value === '1' ? true : false;
            var fieldConfig = {
                nameFieldConfig: {
                    publicApiKey: publicApiKey,
                    fieldDivId: "eway-secure-field-name",
                    fieldType: "name",
                    styles: fieldStyles,
                    autocomplete: autoComplete
                },
                cardFieldConfig: {
                    publicApiKey: publicApiKey,
                    fieldDivId: "eway-secure-field-card",
                    fieldType: "card",
                    styles: fieldStyles,
                    maskValues: maskValue
                },
                expiryFieldConfig: {
                    publicApiKey: publicApiKey,
                    fieldDivId: "eway-secure-field-expiry",
                    fieldType: "expirytext",
                    styles: fieldStyles
                },
                cvnFieldConfig: {
                    publicApiKey: publicApiKey,
                    fieldDivId: "eway-secure-field-cvn",
                    fieldType: "cvn",
                    styles: fieldStyles,
                    maskValues: maskValue
                }
            };

            var callBack = this.secureFieldCallback.bind(this);
            for (var field in fieldConfig) {
                if($(fieldConfig[field].fieldDivId)){
                    $(fieldConfig[field].fieldDivId).update(''); //Remove old content.
                    eWAY.setupSecureField(fieldConfig[field], callBack);
                }
            }

            this.resetValidate();
        },

        resetValidate: function () {
            if("name" in this.validFields) this.validFields['name'] = false;
            if("card" in this.validFields) this.validFields['card'] = false;
            if("expiry" in this.validFields) this.validFields['expiry'] = false;
            if("cvn" in this.validFields) this.validFields['cvn'] = false;

            $('eway-secure-field-name-error') && $('eway-secure-field-name-error').hide();
            $('eway-secure-field-card-error') && $('eway-secure-field-card-error').hide();
            $('eway-secure-field-expiry-error') && $('eway-secure-field-expiry-error').hide();
            $('eway-secure-field-cvn-error') && $('eway-secure-field-cvn-error').hide();

        },

        secureFieldCallback: function (event) {
            var field = event.targetField;
            if (!event.fieldValid || !event.valueIsValid) {
                this.validFields[field] = false;
                if (event.errors == 'V6143') {
                    this.publicApiKeyError = true;
                }
                if(event.errors && event.errors.includes('V6148')){
                    alert('Your secure form is expired. Please reload your browser!');
                    location.reload();
                }
            } else {
                this.validFields[field] = true;
                // set the hidden Secure Field Code field
                var secureFieldCode = document.getElementById("securefieldcode");
                secureFieldCode.value = event.secureFieldCode
            }
            this.secureFormValidate(field, true);
        },

        secureFormValidate: function (fieldToValidate, skipShowError) {
            if (fieldToValidate == undefined) {
                var result = true;
                for (var ewayField in this.validFields) {
                    result = this.secureFormValidate(ewayField, skipShowError) && result;
                }
                return result;
            }else{
                var fieldName = 'eway-secure-field-' + fieldToValidate;
                // Field is hidden
                var fieldError = $(fieldName + '-error');

                // If using token, allow pass all field except cvn.
                if($(fieldName) && $(fieldName).up('.saved_token_fields') && !$(fieldName).up('.saved_token_fields').visible()){
                    fieldError.hide();
                    return true;
                }

                // skip validate card number when edit token.
                if(fieldToValidate == 'card' && $(fieldName) && !$(fieldName).visible()){
                    fieldError.hide();
                    return true;
                }

                if (!this.validFields[fieldToValidate]) {
                    if (this.publicApiKeyError) {
                        alert('Invalid Public API Key');
                    }else{
                        if(skipShowError){return false}
                        fieldError && fieldError.show();
                    }
                    return false;
                } else {
                    fieldError && fieldError.hide();
                    return true;
                }
            }
        },

        oldPaymentSave: function () {
            if (checkout.loadWaiting!=false) return;
            var validator = new Validation(this.form);
            if (this.validate() && validator.validate()) {
                checkout.setLoadWaiting('payment');
                new Ajax.Request(
                    this.saveUrl,
                    {
                        method:'post',
                        onComplete: this.onComplete,
                        onSuccess: this.onSave,
                        onFailure: checkout.ajaxFailure.bind(checkout),
                        parameters: Form.serialize(this.form)
                    }
                );
            }
        },

        secureFieldPaymentSave: function(){
            // Only ewayrapid_ewayone support secureField
            if(this.currentMethod === 'ewayrapid_ewayone'){
                if (checkout.loadWaiting!=false) return;
                var secureFieldValid = EwaySecureForm.prototype.secureFormValidate();
                var validator = new Validation(this.form);
                if (this.validate() && validator.validate() && secureFieldValid) {
                    checkout.setLoadWaiting('payment');
                    new Ajax.Request(
                        this.saveUrl,
                        {
                            method:'post',
                            onComplete: this.onComplete,
                            onSuccess: this.onSave,
                            onFailure: checkout.ajaxFailure.bind(checkout),
                            parameters: Form.serialize(this.form)
                        }
                    );
                }
            }else{
                EwaySecureForm.prototype.oldPaymentSave.bind(EwaySecureForm.prototype.payment)();
            }
        },

        oldAdminSubmitOrder: function () {
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
        },

        submitAdminOrder: function() {
            if(!AdminOrder.prototype.paymentMethod === 'ewayrapid_ewayone'){
                return EwaySecureForm.prototype.oldAdminSubmitOrder();
            }
            if(editForm.validator && editForm.validator.validate() && EwaySecureForm.prototype.secureFormValidate()) {
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
        IWDOnePageCheckout: {
            pullPayments: function() {
                // @see IWD.OPC.Checkout.pullPayments
                // skin/frontend/base/default/js/iwd/opc/checkout.js:699
                // Init secure field before pull review.

                IWD.OPC.Checkout.lockPlaceOrder();
                IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/payments',function(response){
                    IWD.OPC.Checkout.xhr = null;

                    if (typeof(response.error)!="undefined"){
                        $j('.opc-message-container').html(response.error);
                        $j('.opc-message-wrapper').show();
                        IWD.OPC.saveOrderStatus = false;
                        IWD.OPC.Checkout.hideLoader();
                        IWD.OPC.Checkout.unlockPlaceOrder();
                        return;
                    }

                    if (typeof(response.payments)!="undefined"){
                        $j('#checkout-payment-method-load').html(response.payments);
                        EwaySecureForm.prototype.setupSecureField();
                        payment.initWhatIsCvvListeners();
                        IWD.OPC.bindChangePaymentFields();
                        IWD.OPC.Decorator.setCurrentPaymentActive();
                    };

                    IWD.OPC.Checkout.pullReview();

                },'json');

            },
            savePayment: function() {
                if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                    if (IWD.OPC.Checkout.xhr!=null){
                        IWD.OPC.Checkout.xhr.abort();
                    }
                    IWD.OPC.Checkout.showLoader();

                    var form = $j('#co-payment-form').serializeArray();

                    IWD.OPC.Checkout.xhr = $j.post(IWD.OPC.Checkout.config.baseUrl + 'onepage/json/savePayment',form, IWD.OPC.preparePaymentResponse,'json');
                } else if(typeof IWD.OPC.ewayOldSavePayment == 'function') {
                    IWD.OPC.ewayOldSavePayment();
                }
            },
            validatePayment: function(){

                // check all required fields not empty
                var is_empty = false;
                $j('#co-payment-form .required-entry').each(function(){
                    if($j(this).val() == '' && $j(this).css('display') != 'none' && !$j(this).attr('disabled'))
                        is_empty = true;
                });


                if(!IWD.OPC.saveOrderStatus){
                    if(is_empty){
                        IWD.OPC.saveOrderStatus = false;
                        IWD.OPC.Checkout.hideLoader();
                        IWD.OPC.Checkout.unlockPlaceOrder();
                        return false;
                    }
                }

                if(EwayPayment.isEwayRapidMethod(payment.currentMethod)) {
                    if(!IWD.OPC.saveOrderStatus){
                        if(!EwaySecureForm.prototype.secureFormValidate('undefined', true)){
                            IWD.OPC.saveOrderStatus = false;
                            IWD.OPC.Checkout.hideLoader();
                            IWD.OPC.Checkout.unlockPlaceOrder();
                            return false;
                        }
                    }else{
                        if(!EwaySecureForm.prototype.secureFormValidate()){
                            IWD.OPC.saveOrderStatus = false;
                            IWD.OPC.Checkout.hideLoader();
                            IWD.OPC.Checkout.unlockPlaceOrder();
                            return false;
                        }
                    }
                }
                ////

                var vp = payment.validate();
                if(!vp)
                {
                    IWD.OPC.saveOrderStatus = false;
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.unlockPlaceOrder();
                    return false;
                }

                var paymentMethodForm = new Validation('co-payment-form', { onSubmit : false, stopOnFirst : false, focusOnError : false});

                if (paymentMethodForm.validate()){
                    IWD.OPC.savePayment();
                }else{
                    IWD.OPC.saveOrderStatus = false;
                    IWD.OPC.Checkout.hideLoader();
                    IWD.OPC.Checkout.unlockPlaceOrder();

                    IWD.OPC.bindChangePaymentFields();
                }
            },
        },
        FireCheckout: {
            save: function(urlSuffix, forceSave) {
                var currentMethod = payment.currentMethod ? payment.currentMethod : '';
                if(EwayPayment.isEwayRapidMethod(currentMethod)) {
                    if (this.loadWaiting != false) {
                        return;
                    }
                    if(!EwaySecureForm.prototype.secureFormValidate()){
                        return;
                    }
                    this.oldSave(urlSuffix, forceSave);
                }else {
                    this.oldSave(urlSuffix, forceSave);
                }

            }
        },
        MagestoreOsc: {
            placeOrder: function(form){
                var payment_method = $RF(form, 'payment[method]');
                if(EwayPayment.isEwayRapidMethod(payment_method)) {
                    if(EwaySecureForm.prototype.secureFormValidate()){
                        oscPlaceOrder(form);
                    }
                }else{
                    oscPlaceOrder(form);
                }
            }
        },
        OneStepCheckout: {
            submit: function () {
                var currentMethod = payment.currentMethod ? payment.currentMethod : '';
                if(EwayPayment.isEwayRapidMethod(currentMethod)) {
                    if(EwaySecureForm.prototype.secureFormValidate()){
                        this.oldSubmit();
                    }else{
                        already_placing_order = false;
                        var submitelement = $('onestepcheckout-place-order');

                        $$('.onestepcheckout-place-order-loading').first() && $$('.onestepcheckout-place-order-loading').first().remove();
                        submitelement.addClassName('orange').removeClassName('grey');
                        submitelement.disabled = false;
                    }
                }else{
                    this.oldSubmit();
                }
            }
        },
        Lightcheckout : {
            LightcheckoutSubmit: function () {
                var currentMethod = payment.currentMethod ? payment.currentMethod : '';
                if(EwayPayment.isEwayRapidMethod(currentMethod)) {
                    checkout.showLoadinfo();
                    var formValid = checkoutForm.validator.validate();
                    var secureValid = EwaySecureForm.prototype.secureFormValidate();
                    if(formValid && secureValid){
                        this.submit(this.getFormData(), 'save_payment_methods');
                    }else{
                        checkout.hideLoadinfo();
                    }
                }else {
                    this.LightcheckoutSubmitOld();
                }
            }
        },
        MultiShipping: {
            submit: function(){
                var currentMethod = payment.currentMethod ? payment.currentMethod : '';
                if(EwayPayment.isEwayRapidMethod(currentMethod)) {
                    if(EwaySecureForm.prototype.secureFormValidate()){
                        return true;
                    }else{
                        return false;
                    }
                }else{
                    return true;
                }
            }
        }
    }
);