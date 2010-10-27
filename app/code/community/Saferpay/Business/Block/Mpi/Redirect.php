<?php

class Saferpay_Business_Block_Mpi_Redirect extends Mage_Core_Block_Template
{
	public function getCcInfo()
	{
		$info = array();
		switch ($this->getCardTypeCode())
		{
			case Saferpay_Business_Model_Cc::CARD_TYPE_VISA:
				$info = array(
					'code' => 'VI',
					'lable' => 'Visa',
					'title' => 'Verified by Visa',
				);
				break;
			case Saferpay_Business_Model_Cc::CARD_TYPE_MASTERCARD:
				$info = array(
					'code' => 'MC',
					'lable' => 'MasterCard',
					'title' => 'MasterCard SecureCode',
				);
				break;
			case Saferpay_Business_Model_Cc::CARD_TYPE_TEST:
				$info = array(
					'code' => 'VI',
					'lable' => 'Visa TestCard',
					'title' => 'Visa TestCard 3DS',
				);
				break;
			default:
				$info = array(
					'code' => 'NO_3DS_CARD',
					'lable' => 'No 3D-secure enabled card type',
					'title' => '3D-Secure not supported by your card.',
				);
				break;
		}
		return new Varien_Object($info);
	}

	public function is3dsCard()
	{
		if ($this->getMethod())
		{
			if (in_array($this->getCardTypeCode(), $this->getMethod()->get3dSecureCardTypes()))
			{
				return true;
			}
			if ($this->getCardTypeCode() == Saferpay_Business_Model_Cc::CARD_TYPE_TEST)
			{
				return true;
			}
		}
		return false;
	}

	public function getMethod3dsTitle()
	{
		return $this->getCcInfo()->getTitle();
	}

	public function getCcType()
	{
		return $this->getCcInfo()->getCode();
	}

	public function getCcLable()
	{
		return $this->getCcInfo()->getLable();
	}

	public function getMethodCode()
	{
		if ($this->getMethod())
		{
			return $this->getMethod()->getCode();
		}
		return '';
	}
}
