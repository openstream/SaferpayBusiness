/**
 * Saferpay Business Magento Payment Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Saferpay Business to
 * newer versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright Copyright (c) 2010 Openstream Internet Solutions, Switzerland
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
				saferpay.cccvc = '';
				saferpay.elvacc = '';
				saferpay.elvbank = '';
				if (this.currentMethod && this.currentMethod.substr(0, 11) == 'saferpaybe_') {
					if (this.currentMethod == 'saferpaybe_cc') {
						saferpay.ccnum = $('saferpaybe_cc_cc_number').value;
						saferpay.cccvc = $('saferpaybe_cc_cc_cid').value;
					}
					else if (this.currentMethod == 'saferpaybe_elv') {
						saferpay.elvacc = $('saferpaybe_elv_account_number').value;
						saferpay.elvbank = $('saferpaybe_elv_bank_code').value;
					}
					saferpay.disableFields();
				}
				origMethod();
				if (this.currentMethod && this.currentMethod.substr(0, 11) == 'saferpaybe_') {
					saferpay.disableFields(false);

					review.save = review.save.wrap(function (origMethod) {
						saferpay.disableFields();
						origMethod();
						saferpay.disableFields(false);
					});
					review.onSave = saferpay.processReviewResponse;
					review.onComplete = saferpay.updateLoadWaiting;
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
	updateLoadWaiting: function(request)
	{
		var transport;
		if (request.transport) transport = request.transport;
		else transport = false;
		
		if (transport && transport.responseText) {
			try {
				var response = eval('(' + transport.responseText + ')');
				if (response.redirect) {
					/*
					 * Keep the spinner active
					 */
					return true;
				}
			}
			catch (e) {}
		}
		/*
		 * Some kind of error - deactivate the spinner
		 */
		checkout.setLoadWaiting(false);
	},
	processReviewResponse: function(request)
	{
		var transport;
		
		if (request.transport) transport = request.transport;
		else transport = false;
		if (payment.currentMethod && payment.currentMethod.substr(0, 11) == 'saferpaybe_') {
			if (transport && transport.responseText) {
				try {
					var response = eval('(' + transport.responseText + ')');
					if (response.redirect) {
						var url = response.redirect.replace(/___CCNUM___/, saferpay.ccnum).replace(/___CVC___/, saferpay.cccvc);
						url = url.replace(/__BLZ__/, saferpay.elvbank).replace(/__KTO__/, saferpay.elvacc);
						window.location.href = url;
						return true;
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
