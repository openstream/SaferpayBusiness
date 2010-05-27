
if(typeof Saferpay == 'undefined') {
	var Saferpay = {};
}

Saferpay.Business = Class.create({
	initialize: function() {
		payment.save = payment.save.wrap(function (origMethod) {
			if (checkout.loadWaiting != false) return;
			var validator = new Validation(this.form);
			if (this.validate() && validator.validate()) {
				saferpay.ccnum = '';
				saferpay.cvc = '';
				if (this.currentMethod && this.currentMethod == 'saferpay_scd') {
					saferpay.ccnum = $('saferpay_scd_cc_number').value;
					saferpay.cvc = $('saferpay_scd_cc_cid').value;
					saferpay.disableFields();
				}
				origMethod();
				if (this.currentMethod && this.currentMethod == 'saferpay_scd') {
					saferpay.disableFields(false);
					review.onSave = saferpay.processReviewResponse;
				}
			}
		});
	},
	disableFields: function(mode) {
		if (typeof mode == 'undefined') mode = true;
		var form = $('payment_form_' + payment.currentMethod);
		var elements = form.getElementsByClassName('no-submit');
		for (var i=0; i<elements.length; i++) elements[i].disabled = mode;
	},
	processReviewResponse: function(request)
	{
		var transport;
		
		if (request.transport) transport = request.transport;
		else transport = false;
		
		if (payment.currentMethod && payment.currentMethod == 'saferpay_scd') {
			if (transport && transport.responseText) {
				try {
					var response = eval('(' + transport.responseText + ')');
					if (response.redirect) {
						checkout.setLoadWaiting('review');
						var url = response.redirect.replace(/___CCNUM___/, saferpay.ccnum).replace(/___CVC___/, saferpay.cvc);
						location.href = url;
						return;
					}
				}
				catch (e) {}
			}
		}
		review.nextStep(transport);
	}
});

Event.observe(window, 'load', function() {
	saferpay = new Saferpay.Business();
});
