<?php

class Saferpay_Business_Model_Scd extends Saferpay_Standard_Model_Abstract
{
	protected $_code = 'saferpay_scd';

	protected $_formBlockType = 'saferpay_be/form';

	protected $_registerCardUrl;

	protected $_cardRefId;


	/**
	 * Return url for redirection after order placed
	 * 
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		$params = array(
			'sfpCardNumber' => '___CCNUM___',
			'sfpCardCvc' => '___CVC___',
			'sfpCardExpiryMonth' => $this->getInfoInstance()->getCcExpMonth(),
			'sfpCardExpiryYear' => $this->getInfoInstance()->getCcExpYear()
		);
		$url = $this->_getRegisterCardUrl();
		$url = $this->_appendQueryParams($url, $params);
		return $url;
	}

	protected function _getRegisterCardUrl()
	{
		if (! $this->_registerCardUrl)
		{
			$params = array(
				'ACCOUNTID' => Mage::getStoreConfig('saferpay/settings/saferpay_account_id'),
				'SUCCESSLINK' => Mage::getUrl('saferpaybe/process/success', array('_nosid' => 1)),
				'FAILLINK' => Mage::getUrl('saferpaybe/process/fail', array('_nosid' => 1)),
				'CARDREFID' => $this->_getCardRefId()
			);
			$url = Mage::getStoreConfig('saferpay/settings/payinit_base_url');
			$url = $this->_appendQueryParams($url, $params);
			$this->_registerCardUrl = trim(file_get_contents($url));
		}

		return $this->_registerCardUrl;
	}

	protected function _appendQueryParams($url, array $params)
	{
		foreach ($params as $k => $v)
		{
			$url .= strpos($url, '?') === false ? '?' : '&';
			$url .= sprintf("%s=%s", $k, urlencode($v));
		}
		return $url;
	}

	public function _getCardRefId()
	{
		if (is_null($this->_cardRefId))
		{
			$this->_cardRefId = md5(mt_rand(0, 1000) . microtime());
		}
		return $this->_cardRefId;
	}
}