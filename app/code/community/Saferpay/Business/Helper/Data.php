<?php
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
 * @copyright Copyright (c) 2010 Openstream Internet Solutions (http://www.openstream.ch)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Saferpay_Business_Helper_Data extends Mage_Core_Helper_Data
{
	public $error_code = array(0 => 'The authorization or request processing was successful.',
							   5 => 'The access to the specified account was denied by Saferpay.',
							  23 => 'Invalid action attribute or action not possible.',
							  61 => 'The static checks failed on this card (range check, LUHN check digit).',
							  62 => 'Invalid expiration date.',
							  63 => 'The card has expired.',
							  64 => 'The card type is unknown, the BIN range could not be assigned to a known brand.',
							  65 => 'The processor has denied the transaction request.',
							  67 => 'No contract exists for the card/currency combination specified.',
							  68 => 'More than one contracts exist for this card/currency combination.',
							  75 => 'The amount is not plain numerical.',
							  76 => 'The connection to the card processor could not be established or was broken during the request.',
							  77 => 'No endpoint is specified for the processor of the card.',
							  78 => 'A system error has occurred during processing the request. Retry the request.',
							  79 => 'Function Unknown',
							  80 => 'Terminal does not exist.',
							  81 => 'The terminal type does not support the requested service.',
							  82 => 'Transaction not found.',
							  83 => 'The specified currency code is invalid.',
							  84 => 'The specified amout is invalid or does not match the rules for the currency.',
							  85 => 'No more credits available',
							  86 => 'Double transaction',
							  87 => 'Access denied',
							  88 => 'Reservation invalid',
							  89 => 'Amount of reservation overbooked.',
							  90 => 'The contract for this card is currently disabled.',
							  97 => 'Transaction already captured (PayComplete)',
							  98 => 'Invalid digital signature of message content received from Saferpay.',
							 102 => 'Function not supported by provider.',
							 103 => 'Function not allowed',
							 104 => 'Card number in customer black list.',
							 105 => 'Card number not in country BIN range list.',
							 110 => 'Timeout waiting on authorization response.',
							 113 => 'The CVC contains a wrong value, must be 3 or 4 digits only.',
							 114 => 'Missing CVC Number',
							 115 => 'Communication error to GICC provider.',
							 120 => 'Received no answer for authorization request.',
							 130 => 'Received unknown message type from provider.',
							 150 => 'Authorization response from provider invalid.',
							 151 => 'Timeout waiting on authorization response.',
							 152 => 'The processor has denied the transaction request for this terminal.',
							 153 => 'A problem occurred during syncronisation with the processor.',
							 154 => 'The processors response is invalid it contains a format error.',
							 301 => 'The Merchant Plug-In application aborted errorous.',
							7000 => 'Internal error during registration, DESCRIPTION contains further details',
							7001 => 'Registration request could not be processed completely',
							7002 => 'The registration process could not match a valid card scheme',
							7003 => 'Registration request malformed or wrong field content',
							7004 => 'Card reference number not found in database.',
							7005 => 'Missing attribute in registration request',
							7006 => 'Card reference number already existing in database',
							7007 => 'Unknown Error');

	/**
	 * Unified round() implementation for the Saferpay extension
	 *
	 * @param mixed $value String, Integer or Float
	 * @return float
	 */
	public function round($value)
	{
		return Zend_Locale_Math::round($value, 2);
	}
	
	/**
	 * Return the attributes as an array that are part of the saferpay xml response
	 *
	 * @param string $xml
	 * @return array
	 */
	public function _parseResponseXml($xml)
	{
		if($xml && $xml = simplexml_load_string($xml)){
			$data = (array)$xml->attributes();
			return $data['@attributes'];
		}else{
			return array();
		}
	}
}