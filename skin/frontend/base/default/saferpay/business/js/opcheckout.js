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
		var savedCard = $('saferpaybe_cc_saved_card');
		if (savedCard) {
			this.switchSavedCards();
			Event.observe(savedCard, 'change', this.switchSavedCards);
		}
		var obj1 = typeof payment == 'undefined' ? Payment.prototype : payment;
		if(typeof obj1.save != 'undefined'){
			obj1.save = obj1.save.wrap(function (origMethod) {
				if (checkout.loadWaiting != false) return;
				var validator = new Validation(this.form);
				if (this.validate() && validator.validate()) {
					saferpay.ccnum = '';
					saferpay.cccvc = '';
					saferpay.ccexpmonth = '';
					saferpay.ccexpyear = '';
					saferpay.elvacc = '';
					saferpay.elvbank = '';
					if (this.currentMethod && this.currentMethod.substr(0, 11) == 'saferpaybe_') {
						if (this.currentMethod == 'saferpaybe_cc') {
							saferpay.ccnum = $('saferpaybe_cc_cc_number').value;
							saferpay.cccvc = $('saferpaybe_cc_cc_cid').value;
							saferpay.ccexpmonth = $('saferpaybe_cc_expiration').value;
							saferpay.ccexpyear = $('saferpaybe_cc_expiration_yr').value;
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
						var obj2 = typeof review == 'undefined' ? Review.prototype : review;
						//console.debug('using ' + (typeof review == 'undefined' ? 'Review.prototype' : 'review'));
						obj2.save = obj2.save.wrap(function (origMethod) {
							saferpay.disableFields();
							origMethod();
							saferpay.disableFields(false);
						});
						if (typeof review == 'undefined'){
							// Magento 1.5
							//console.debug('Wrapping Review.prototype.nextStep()');
							Review.prototype.nextStep = Review.prototype.nextStep.wrap(saferpay.processReviewResponse);
							Review.prototype.resetLoadWaiting = Review.prototype.resetLoadWaiting.wrap(saferpay.resetLoadWaiting);
						} else {
							// Magento 1.4
							//console.debug('Setting review.onSave');
							review.onSave = saferpay.updateReviewResponse;
							review.onComplete = saferpay.updateLoadWaiting;
						}
					}
				}
			});
		}else{
			checkout.submitComplete = checkout.submitComplete.wrap(function(origMethod, request){				
				saferpay.ccnum = '';
				saferpay.cccvc = '';
				saferpay.ccexpmonth = '';
				saferpay.ccexpyear = '';
				saferpay.elvacc = '';
				saferpay.elvbank = '';
				if (payment.currentMethod && payment.currentMethod.substr(0, 11) == 'saferpaybe_') {
					if (payment.currentMethod == 'saferpaybe_cc') {
						saferpay.ccnum = $('saferpaybe_cc_cc_number').value;
						saferpay.cccvc = $('saferpaybe_cc_cc_cid').value;
						saferpay.ccexpmonth = $('saferpaybe_cc_expiration').value;
						saferpay.ccexpyear = $('saferpaybe_cc_expiration_yr').value;
					}
					else if (payment.currentMethod == 'saferpaybe_elv') {
						saferpay.elvacc = $('saferpaybe_elv_account_number').value;
						saferpay.elvbank = $('saferpaybe_elv_bank_code').value;
					}
					saferpay.disableFields();
					var transport;
					if (request.transport) transport = request.transport;
					else transport = false;
					saferpay.processReviewResponse(review.nextStep, transport);
				}else{
					origMethod(request);
				}
			});
		}
	},
	switchSavedCards: function() {
		if ($('saferpaybe_cc_saved_card').value == '') {
			$$('#container_payment_method_saferpaybe_cc li').each(Element.show);
		} else {
			$$('#container_payment_method_saferpaybe_cc li:not(.saferpay_be_saved_cards)').each(Element.hide);
		}
	},
	disableFields: function(mode) {
		if (typeof mode == 'undefined') mode = true;
		var form = $('payment_form_' + payment.currentMethod);
		var elements = form.getElementsByClassName('no-submit');
		for (var i=0; i<elements.length; i++) elements[i].disabled = mode;
	},
	/*
	 * Wrapper for Magento 1.4
	 * Mageto 1.5 calls resetLoadWaiting directly
	 */
	updateLoadWaiting: function(request)
	{
		//console.debug('updateLoadWaiting');
		var transport;
		if (request.transport) transport = request.transport;
		else transport = false;
		saferpay.resetLoadWaiting(transport);
	},
	resetLoadWaiting: function(transport)
	{
		//console.debug('resetLoadWaiting');
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
	/*
	 * Wrapper for Magento 1.4
	 * Magento 1.5 calls processReviewResponse() directly
	 */
	updateReviewResponse: function(request)
	{
		//console.debug('updateReviewResponse');
		var transport;
	    if (request.transport) transport = request.transport;
		else transport = false;
		saferpay.processReviewResponse(review.nextStep, transport);
	},
	processReviewResponse: function(origMethod, transport) {
		//console.debug('processReviewResponse');
		if (payment.currentMethod && payment.currentMethod.substr(0, 11) == 'saferpaybe_') {
			if (transport && transport.responseText) {
				try {
					var response = eval('(' + transport.responseText + ')');
					if (response.redirect) {

						/*
						 * Display 3D-Secure notification
						 */
						var form = new Element('form', {'action': response.redirect, 'method': 'post', 'id': 'saferpay_be_transport'});
						$$('body')[0].insert(form);
						if (String(saferpay.ccnum).length > 0) {
							form.insert(new Element('input', {'type': 'hidden', 'name': 'sfpCardNumber', 'value':  saferpay.ccnum}));
							form.insert(new Element('input', {'type': 'hidden', 'name': 'sfpCardCvc', 'value':  saferpay.cccvc}));
							form.insert(new Element('input', {'type': 'hidden', 'name': 'sfpCardExpiryMonth', 'value':  saferpay.ccexpmonth}));
							form.insert(new Element('input', {'type': 'hidden', 'name': 'sfpCardExpiryYear', 'value':  saferpay.ccexpyear}));
						}
						else if (String(saferpay.elvbank).length > 0) {
							form.insert(new Element('input', {'type': 'hidden', 'name': 'sfpCardBLZ', 'value':  saferpay.elvbank}));
							form.insert(new Element('input', {'type': 'hidden', 'name': 'sfpCardKonto',  'value':  saferpay.elvacc}));
						}
						form.submit();
						
						return true;
					}
				}
				catch (e) {}
			}
		}
		origMethod(transport);
	}
});

/*
 * Extend the cc validation scripts
 */
Validation.creditCartTypes = Validation.creditCartTypes.merge({
	'VP': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false],
	'BC': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false],
	'MO': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false],
	'UP': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false],
	'TC': [false, new RegExp('^([0-9]{3}|[0-9]{4})?$'), false]
});

Event.observe(window, 'load', function() {
	saferpay = new Saferpay.Business();
/*	
	payment.save.wrap(function (origMethod){
	 alert(1);
	 origMethod();
	});
*/	
});
