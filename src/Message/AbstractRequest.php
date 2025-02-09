<?php
/**
 * @author Ricardo Assing (ricardo@tsiana.ca)
 */

namespace Omnipay\FirstAtlanticCommerce\Message;

use Omnipay\FirstAtlanticCommerce\Constants;
use Omnipay\FirstAtlanticCommerce\Support\ThreeDSecure;
use Omnipay\FirstAtlanticCommerce\Support\TransactionCode;
use Omnipay\FirstAtlanticCommerce\Exception\GatewayHTTPException;

abstract class AbstractRequest extends \Omnipay\Common\Message\AbstractRequest
implements \Omnipay\FirstAtlanticCommerce\Support\FACParametersInterface
{
    const SIGNATURE_METHOD_SHA1 = 'SHA1';

    const PARAM_CACHE_TRANSACTION = 'cacheTransaction';
    const PARAM_CACHE_REQUEST = 'cacheRequest';

    protected $data = [];
    protected $XMLDoc;
    protected $TransactionCacheDir = 'transactions/';

    protected $FACServices = [
        "Authorize" => [
            "request"=>"AuthorizeRequest",
            "response"=>"AuthorizeResponse"
        ],
        "TransactionStatus" => [
            "request"=>"TransactionStatusRequest",
            "response"=>"TransactionStatusResponse"
        ],
        "TransactionModification" => [
            "request"=>"TransactionModificationRequest",
            "response"=>"TransactionModificationResponse"
        ],
        "Tokenize" => [
            "request"=>"TokenizeRequest",
            "response"=>"TokenizeResponse"
        ],
        "Authorize3DS" => [
            "request"=>"Authorize3DSRequest",
            "response"=>"Authorize3DSResponse"
        ],
        "HostedPagePreprocess" => [
            "request"=>"HostedPagePreprocessRequest",
            "response"=>"HostedPagePreprocessResponse"
        ],
        "HostedPageResults" => [
            "request"=>"string",
            "response"=>"HostedPageResultsResponse"
        ]
    ];

    public function signTransaction()
    {
        $signature = null;

        switch ($this->getMessageClassName())
        {
            case "HostedPagePreprocess":
            case "Authorize3DS":
            case "Authorize":
                $data = $this->getFacPwd().$this->getFacId().$this->getFacAcquirer().$this->getTransactionId().$this->getAmountForFAC().$this->getCurrencyNumeric();
                $hash = sha1($data, true);
                $signature = base64_encode($hash);

                break;
        }

        return $this->setSignature($signature);
    }

    public function sendData($data)
    {
        $this->createNewXMLDoc($data);

        $httpResponse = $this->httpClient
            ->request("POST", $this->getEndpoint().$this->getMessageClassName(), [
            "Content-Type"=>"text/html"
        ], $this->XMLDoc->asXML());

        if($this->getCacheRequest())
        {
            if (!is_dir($this->TransactionCacheDir))
            {
                $cacheDirExists = mkdir($this->TransactionCacheDir);
            }
            else
            {
                $cacheDirExists = true;
            }

            if ($cacheDirExists)
                $this->XMLDoc->asXML($this->TransactionCacheDir.$this->getMessageClassName().'Request_'.$this->getTransactionId().'.xml');
        }

        switch ($httpResponse->getStatusCode())
        {
            case "200":
                $responseContent = $httpResponse->getBody()->getContents();

                $responseClassName = __NAMESPACE__."\\".$this->FACServices[$this->getMessageClassName()]["response"];

                $responseXML = new \SimpleXMLElement($responseContent);
                $responseXML->registerXPathNamespace("fac", Constants::PLATFORM_XML_NS);

                if($this->getCacheTransaction())
                {
                    if (!is_dir($this->TransactionCacheDir))
                    {
                        $cacheDirExists = mkdir($this->TransactionCacheDir);
                    }
                    else
                    {
                        $cacheDirExists = true;
                    }

                    if ($cacheDirExists)
                        $responseXML->asXML($this->TransactionCacheDir.$this->getMessageClassName().'Response_'.$this->getTransactionId().'.xml');
                }

                return $this->response = new $responseClassName($this, $responseXML);

            default:
                throw new GatewayHTTPException($httpResponse->getReasonPhrase(), $httpResponse->getStatusCode());
        }
    }

    protected function createNewXMLDoc($data)
    {
        $rootElement = $this->FACServices[$this->getMessageClassName()]["request"];

        if(is_string($data))
        {
            $this->XMLDoc = new \SimpleXMLElement("<".$rootElement." xmlns=\"".Constants::PLATFORM_XML_NS."\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">".$data."</".$rootElement.">");
        }
        else
        {
            $this->XMLDoc = new \SimpleXMLElement("<".$rootElement." xmlns=\"".Constants::PLATFORM_XML_NS."\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" />");

            $this->createXMLFromData($this->XMLDoc, $data);
        }
    }

    protected function createXMLFromData(\SimpleXMLElement $parent, $data)
    {
        if (is_array($data))
        {
            foreach ($data as $elementName=>$value)
            {
                if (is_array($value))
                {
                    $element = $parent->addChild($elementName);
                    $this->createXMLFromData($element, $value);
                }
                else
                {
                    $parent->addChild($elementName, $value);
                }
            }
        }
        elseif (is_string($data))
        {
            $parent->addChild($data);
        }
    }

    protected function getEndpoint()
    {
        return ($this->getTestMode()) ? Constants::PLATFORM_XML_UAT : Constants::PLATFORM_XML_PROD;
    }

    public function getMessageClassName()
    {
        $className = explode("\\",get_called_class());
        return array_pop($className);
    }

    public function setFacId($FACID)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACID, $FACID);
    }

    public function getFacId()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACID);
    }

    public function setFacPwd($PWD)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACPWD, $PWD);
    }

    public function getFacPwd()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACPWD);
    }

    public function setFacAcquirer($ACQ)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACAQID, $ACQ);
    }

    public function getFacAcquirer()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACAQID);
    }

    public function setFacCurrencyList($list)
    {
        return $this->setParameter(Constants::CONFIG_KEY_FACCUR, $list);
    }

    public function getFacCurrencyList()
    {
        return $this->getParameter(Constants::CONFIG_KEY_FACCUR);
    }

    public function setIPAddress($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP))
        {
            return $this->setClientIp($ip);
        }

        return $this;
    }

    public function getIPAddress()
    {
        return $this->getClientIp();
    }

    public function getCustomerReference()
    {
        return $this->getParameter(Authorize::PARAM_CUSTOMER_REF);
    }

    public function setCustomerReference($ref)
    {
        return $this->setParameter(Authorize::PARAM_CUSTOMER_REF, $ref);
    }

    public function getSignature()
    {
        return $this->getParameter(Authorize::PARAM_SIGNATURE);
    }

    public function setSignature($signature)
    {
        return $this->setParameter(Authorize::PARAM_SIGNATURE, $signature);
    }

    public function getSignatureMethod()
    {
        if (!$this->getParameter(Authorize::PARAM_SIGNATURE_METHOD))
        {
            $this->setSignatureMethod();
        }

        return $this->getParameter(Authorize::PARAM_SIGNATURE_METHOD);
    }

    public function setSignatureMethod($algo = self::SIGNATURE_METHOD_SHA1)
    {
        return $this->setParameter(Authorize::PARAM_SIGNATURE_METHOD, $algo);
    }

    public function getAmountForFAC()
    {
        $length = 12;
        $amount = $this->getAmountInteger();

        while (strlen($amount) < $length)
        {
            $amount = "0".$amount;
        }

        return $amount;
    }

    public function setTransactionCode(TransactionCode $transactionCode)
    {
        return $this->setParameter(Authorize::PARAM_TRANSACTIONCODE, $transactionCode);
    }

    public function getTransactionCode() : TransactionCode
    {
        return $this->getParameter(Authorize::PARAM_TRANSACTIONCODE);
    }

    public function setCacheTransaction(bool $value)
    {
        return $this->setParameter(AbstractRequest::PARAM_CACHE_TRANSACTION, $value);
    }

    public function getCacheTransaction()
    {
        return $this->getParameter(AbstractRequest::PARAM_CACHE_TRANSACTION);
    }

    public function setCacheRequest(bool $value)
    {
        return $this->setParameter(AbstractRequest::PARAM_CACHE_REQUEST, $value);
    }

    public function getCacheRequest()
    {
        return $this->getParameter(AbstractRequest::PARAM_CACHE_REQUEST);
    }

    /**
     * Override parent method to ensure returned value is 3 digit string. (Required by FAC).
     * @return string|null
     */
    public function getCurrencyNumeric()
    {
        $currency = parent::getCurrencyNumeric();
        if (is_string($currency) && strlen($currency) == 2) return "0".$currency;

        return $currency;
    }

    public function getTransactionId()
    {
        $transactionId = parent::getTransactionId();
        $orderNumberPrefix = $this->getOrderNumberPrefix();

        if (empty($transactionId) && $this->getOrderNumberAutoGen() === true)
        {
            $transactionId = microtime(true);
        }

        if (!empty($orderNumberPrefix) && !empty($transactionId)) $transactionId = $orderNumberPrefix.$transactionId;

        $this->setTransactionId($transactionId);
        $this->setOrderNumberPrefix('');

        return $transactionId;
    }

    public function setOrderNumberPrefix($value)
    {
        return $this->setParameter(Constants::GATEWAY_ORDER_NUMBER_PREFIX, $value);
    }

    public function getOrderNumberPrefix()
    {
        return $this->getParameter(Constants::GATEWAY_ORDER_NUMBER_PREFIX);
    }

    public function setOrderNumberAutoGen($value)
    {
        return $this->setParameter(Constants::GATEWAY_ORDER_NUMBER_AUTOGEN, $value);
    }

    public function getOrderNumberAutoGen()
    {
        return $this->getParameter(Constants::GATEWAY_ORDER_NUMBER_AUTOGEN);
    }

    public function setThreeDSecureDetails($threeDSecureDetails)
    {
        $threeDSecureDetails = new ThreeDSecure($threeDSecureDetails);

        return $this->setParameter('ThreeDSecureDetails', $threeDSecureDetails);
    }

    public function getThreeDSecureDetails()
    {
        return $this->getParameter('ThreeDSecureDetails');
    }

    public function setCustomDataTax($customDataTax)
    {
        //$threeDSecureDetails = new ThreeDSecure($threeDSecureDetails);

        return $this->setParameter('CustomDataTax', '|TX' . $customDataTax);
    }

    public function getCustomDataTax()
    {
        return $this->getParameter('CustomDataTax');
    }
}
