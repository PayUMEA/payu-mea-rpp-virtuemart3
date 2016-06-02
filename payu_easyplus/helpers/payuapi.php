<?php
/**
 * Created by PhpStorm.
 * User: netcraft
 * Date: 4/8/16
 * Time: 10:11 PM
 */
defined('_JEXEC') or die('Restricted access');

class PayuEasyPlusApi
{
    protected $method;
    protected $cart;
    protected $order;
    protected $context;
    protected $total;
    protected static $requestData;
    protected $responseData;
    protected $paymentInfo;
    protected $currency_code_3;
    protected $plugin;

    protected static $ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

    private static $_soapClient = null;

    // @var string The base sandbox URL for the PayU API endpoint.
    protected static $sandboxUrl = 'https://staging.payu.co.za/service/PayUAPI';
    protected static $sandboxCheckoutUrl = 'https://staging.payu.co.za/rpp.do';

    // @var string The base live URL for the PayU API endpoint.
    protected static $liveUrl = 'https://secure.payu.co.za/service/PayUAPI';
    protected static $liveCheckoutUrl = 'https://secure.payu.co.za/rpp.do';

    // @var string The PayU safe key to be used for requests.
    protected $safeKey;

    // @var string|null The version of the PayU API to use for requests.
    protected static $apiVersion = 'ONE_ZERO';

    protected static $username = '';

    protected static $password = '';

    protected $merchantRef = '';

    protected $payuReference = '';

    protected static $rppUrl = '';
    protected static $checkoutUrl = '';

    function __construct ($method, $plugin) {
        $this->method = $method;
        $this->plugin = $plugin;
        $session = JFactory::getSession();
        $this->context = $session->getId();
    }

    /**
     * @return string The safe key used for requests.
     */
    public function getSafeKey()
    {
        return $this->safeKey;
    }

    /**
     * Sets the safe key to be used for requests.
     *
     * @param string $safeKey
     */
    public function setSafeKey($safeKey)
    {
        $this->safeKey = $safeKey;
    }

    /**
     * @return string The API version used for requests. null if we're using the
     *    latest version.
     */
    public static function getApiVersion()
    {
        return self::$apiVersion;
    }

    /**
     * @return string The soap user used for requests.
     */
    public static function getUsername()
    {
        return self::$username;
    }

    /**
     * Sets the soap username to be used for requests.
     *
     * @param string $username
     */
    public static function setUsername($username)
    {
        self::$username = $username;
    }

    /**
     * @return string The soap password used for requests.
     */
    public static function getPassword()
    {
        return self::$password;
    }

    /**
     * Sets the soap password to be used for requests.
     *
     * @param string $password
     */
    public static function setPassword($password)
    {
        self::$password = $password;
    }

    /**
     * @return string The merchant reference to identify captured payments..
     */
    public function getMerchantReference()
    {
        return $this->reference;
    }

    /**
     * Sets the merchant reference to identify captured payments.
     *
     * @param string $reference
     */
    public function setMerchantReference($reference)
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return string The reference from PayU.
     */
    public function getPayUReference()
    {
        return $this->payuReference;
    }

    /**
     * Sets the PayU reference.
     *
     * @param string $reference
     */
    public function setPayUReference($reference)
    {
        $this->payuReference = $reference;

        return $this;
    }

    /**
     * @return string The soap wsdl endpoint to send requests.
     */
    public static function getSoapEndpoint()
    {
        return self::$rppUrl;
    }

    /**
     * @return string The redirect payment page url to be used for requests.
     */
    public static function getCheckoutUrl()
    {
        return self::$checkoutUrl;
    }

    /**
     * Sets the redirect payment page url to be used for requests.
     *
     * @param string $gateway
     */
    public static function setGatewayEndpoint($gateway)
    {
        if(!$gateway) {
            self::$rppUrl = self::$sandboxUrl;
            self::$checkoutUrl = self::$sandboxCheckoutUrl;
        } else {
            self::$rppUrl = self::$liveUrl;
            self::$checkoutUrl = self::$liveCheckoutUrl;
        }
    }

    public function getPaymentInfo()
    {
        return $this->paymentInfo;
    }

    public function getTransactionInfo()
    {
        $data = array();
        $data['Api'] = self::getApiVersion();
        $data['Safekey'] = $this->method->safe_key;
        $data['AdditionalInformation']['payUReference'] = $this->getPayUReference();

        $result = self::getSoapSingleton()->getTransaction($data);
        $this->paymentInfo = json_decode(json_encode($result), true);
        //return $this->paymentInfo;
        $responseData = new PayuResponseData();
        $responseData->setPayuInterface($this);
        return $responseData->load();
    }

    public static function setPayURequestData ($txn_data) {
        self::$requestData = $txn_data;
    }

    public static function getPayURequestData () {
        return self::$requestData;
    }     

    public function postPayURequestData()
    {
        $data = self::getPayURequestData();
        $response = self::getSoapSingleton()->setTransaction($data);
        
        return json_decode(json_encode($response), true);
    }

    public function prepareRequestData () {
        $requestData = new PayuRequestData();
        $requestData->setPayuInterface($this);
        $requestData->loadRequestData();
    }

    private static function getSoapHeader()
    {
        $header  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
        $header .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
        $header .= '<wsse:Username>'.self::getUsername().'</wsse:Username>';
        $header .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.self::getPassword().'</wsse:Password>';
        $header .= '</wsse:UsernameToken>';
        $header .= '</wsse:Security>';

        return $header;
    }
    private static function getSoapSingleton()
    {
        if(is_null(self::$_soapClient))
        {
            $header = self::getSoapHeader();
            $soapWsdlUrl = self::getSoapEndpoint().'?wsdl';
            self::$rppUrl = $soapWsdlUrl;

            $headerbody = new \SoapVar($header, XSD_ANYXML, null, null, null);
            $soapHeader = new \SOAPHeader(self::$ns, 'Security', $headerbody, true);

            self::$_soapClient = new \SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
            self::$_soapClient->__setSoapHeaders($soapHeader);
        }
        return self::$_soapClient;
    }

    public function isPaymentSuccessful()
    {
        return $this->payuTransactionData['return']['successful'];
    }

    public function getTotalCaptured()
    {
        return ($this->payuTransactionData['return']['paymentMethodsUsed']['amountInCents'] / 100);
    }

    public function getDisplayMessage()
    {
        return $this->payuTransactionData['return']['displayMessage'];
    }

    public function isFraudDetected()
    {
        return isset($this->payuTransactionData['return']['fraud']['resultCode']);
    }

    public function getTransactionState()
    {
        return $this->payuTransactionData['return']['transactionState'];
    }

    public function debugLog ($message, $title = '', $type = 'message', $echo = false, $doVmDebug = false) {
        $this->plugin->debugLog($message, $title, $type, $doVmDebug);
    }

    public function getContext () {
        return $this->context;
    }

    public function getPaymentMethod() {
        return $this->method;
    }

    public function setCart ($cart) {
        $this->cart = $cart;
        if (!isset($this->cart->cartPrices) or empty($this->cart->cartPrices)) {
            $this->cart->prepareCartData();
        }
    }

    public function setOrder ($order) {
        $this->order = $order;
    }

    public function getOrder () {
        return $this->order;
    }

    public function setTotal ($total) {
        $this->total = $total;
    }

    public function getTotal () {
        return $this->total;
    }

    public function setCurrencyCode ($code) {
        $this->currency_code_3 = $code;
    }

    public function getCurrencyCode () {
        return $this->currency_code_3;
    }

    public function init()
    {
        $method = $this->method;
        if($method) {
            $this->setSafeKey(trim($method->safe_key));
            $this->setUsername(trim($method->api_username));
            $this->setPassword(trim($method->api_password));
            $this->setGatewayEndpoint($method->gateway);
        }
    }
}