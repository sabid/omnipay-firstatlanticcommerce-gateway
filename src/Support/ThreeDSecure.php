<?php
/**
 * @author Ricardo Assing (ricardo@tsiana.ca)
 */

namespace Omnipay\FirstAtlanticCommerce\Support;

class ThreeDSecure extends AbstractSupport
{
    const PARAM_ECI_INDICATOR = 'ECIIndicator';
    const PARAM_AUTHENTICATION_RESULT = 'AuthenticationResult';
    const PARAM_TRANSACTION_STAIN = 'TransactionStain';
    const PARAM_CAVV = 'CAVV';
    const PARAM_PROTOCOL_VERSION = 'ProtocolVersion';
    const PARAM_DS_TRANS_ID = 'DSTransId';


    const ECI_INDICATOR_VISA_FULL = "05";
    const ECI_INDICATOR_VISA_NOT_ENROLLED = "06";
    const ECI_INDICATOR_MASTERCARD_FULL = "02";
    const ECI_INDICATOR_MASTERCARD_NOT_ENROLLED = "01";

    const AUTHENTICATION_RESULT_ATTEMPTED = "A";
    const AUTHENTICATION_RESULT_NOT_SUPPORTED = "N";
    const AUTHENTICATION_RESULT_FAILED = "U";
    const AUTHENTICATION_RESULT_SUCCESS = "Y";

    protected $data = [
        self::PARAM_ECI_INDICATOR => null,
        self::PARAM_AUTHENTICATION_RESULT => null,
        self::PARAM_TRANSACTION_STAIN => null,
        self::PARAM_CAVV => null,
        self::PARAM_PROTOCOL_VERSION => null,
        self::PARAM_DS_TRANS_ID => null
    ];

    public function getECIIndicator()
    {
        return $this->data[self::PARAM_ECI_INDICATOR];
    }

    public function getAuthenticationResult()
    {
        return $this->data[self::PARAM_AUTHENTICATION_RESULT];
    }

    public function getTransactionStain()
    {
        return $this->data[self::PARAM_TRANSACTION_STAIN];
    }

    public function getCAVV()
    {
        return $this->data[self::PARAM_CAVV];
    }

    public function getProtocolVersion()
    {
        return $this->data[self::PARAM_PROTOCOL_VERSION];
    }

    public function getDSTransId()
    {
        return $this->data[self::PARAM_DS_TRANS_ID];
    }

    public function isAuthenticationSuccess()
    {
        if ($this->getAuthenticationResult() === self::AUTHENTICATION_RESULT_SUCCESS) return true;

        return false;
    }
}
