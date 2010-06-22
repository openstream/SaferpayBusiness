<?php

class Saferpay_Business_Model_Elv extends Saferpay_Business_Model_Abstract
{
	protected $_code = 'saferpaybe_elv';

	protected $_formBlockType = 'saferpay_be/form_elv';
	protected $_infoBlockType = 'saferpay_be/info';

	/*
	 * Availability options
	 */
	protected $_isGateway              = true;
	protected $_canAuthorize           = false;
	protected $_canCapture             = true;
	protected $_canCapturePartial      = true;
	protected $_canRefund              = false;
	protected $_canVoid                = false;
	protected $_canUseInternal         = false;
	protected $_canUseCheckout         = true;
	protected $_canUseForMultishipping = false;
	
	/**
	 * Return url for redirection after order placed
	 * 
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
	{
		$params = array(
			'sfpCardBLZ' => '__BLZ__',
			'sfpCardKonto' => '__KTO__'
		);
		$url = $this->_getRegisterCardRefUrl();
		$url = $this->_appendQueryParams($url, $params);
		Mage::log($url);
		return $url;
	}

	public function importRegisterResponseData($data)
	{
		$data = $this->_parseResponseXml($data);

		$this->validateRegisterResponseData($data);

		$this->_addPaymentInfoData(array(
			'card_ref_id' => $data['CARDREFID'],
		));

		return $this;
	}

	public function execute()
	{
		Mage::log(__METHOD__);
		$this->getInfoInstance()->setStatus(self::STATUS_UNKNOWN);

		$this->authorize($this->getInfoInstance(), $this->getOrder()->getGrandTotal());

		if ($this->getConfigPaymentAction() == self::ACTION_AUTHORIZE_CAPTURE)
		{
			$this->_createInvoice();
			
			$this->getOrder()
				->sendNewOrderEmail()
				->setEmailSent(true)
				->save();
		}
		
		return $this;
	}
}