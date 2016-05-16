<?php
/**
 * Created by PhpStorm.
 * User: netcraft
 * Date: 4/8/16
 * Time: 10:12 PM
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('PayuEasyPlusApi')) {
    require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'payu_easyplus'. DS.'payu_easyplus'. DS.'helpers'. DS .'payuapi.php');
}

class PayuRequestData
{
    private $_payuInterface = null;

    public function setPayuInterface($api)
    {
        $this->_payuInterface = $api;
    }

    public function loadRequestData() {
        $api = $this->_payuInterface;
        $method = $api->getMethod();
        $order = $api->getOrder();
        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);
        $total = $api->getTotal();

        $data = array(
            'Api' => $api->getApiVersion(),
            'Safekey' => trim($method->safe_key),
            'TransactionType' => $method->payment_action,
            'AdditionalInformation' => [
                'merchantReference' => $method->merchant_ref,
                'supportedPaymentMethods' => implode(',',$method->payment_method),
                'demoMode' => $method->gateway ? 'false' : 'true',
                'secure3d' => $method->secure3d ? 'true' : 'false',
                'returnUrl' => JURI::root () . 'index.php' . 
                    '?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived' .
                    '&on=' .$order['details']['BT']->order_number .
                    '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id .
                    '&Itemid=' . vRequest::getInt ('Itemid') .
                    '&lang='. vRequest::getCmd('lang',''),
                'cancelUrl' => JURI::root () . 'index.php?' . 
                    'option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' .
                    $order['details']['BT']->order_number .
                    '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id .
                    '&Itemid=' . vRequest::getInt ('Itemid') .
                    '&lang='. vRequest::getCmd('lang',''),
                //'notificationUrl' => JURI::root () . 'index.php?' .
                //    'option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component' .
                //    '&lang=' . vRequest::getCmd('lang',''),
                'redirectChannel' => $method->redirect_channel
            ],
            //customer details
            'Customer' => [
                "firstName" => $address->first_name,
                "lastName" => $address->last_name,
                "mobile" => '27'.str_replace('-', '', $address->phone_1),
                "email" => $address->email,
            ],
            'Basket' => [
                // payment details section
                'description' => vmText::_ ('VMPAYMENT_PAYU_ORDER_NUMBER') . ': ' . $order['details']['BT']->order_number,
                'amountInCents' => (string)($total['value'] * 100),
                'currencyCode' => $api->getCurrencyCode(),
            ]
        );
        //$this->save();
        PayuEasyPlusApi::setPayURequestData($data);
    }

    public function save() {
        if (!class_exists('vmCrypt')) {
            require(VMPATH_ADMIN . DS . 'helpers' . DS . 'vmcrypt.php');
        }
        $session = JFactory::getSession();
        $sessionData = new stdClass();
        $sessionData->selected_method = $this->_selected_method;
        // card information
        $sessionData->cc_type = $this->_cc_type;
        $sessionData->cc_number = vmCrypt::encrypt($this->_cc_number);
        $sessionData->cc_cvv =vmCrypt::encrypt( $this->_cc_cvv);
        $sessionData->cc_expire_month = $this->_cc_expire_month;
        $sessionData->cc_expire_year = $this->_cc_expire_year;
        $sessionData->cc_valid = $this->_cc_valid;
        //Customer settings
        $sessionData->autobilling_max_amount = $this->_autobilling_max_amount;
        //PayPal Express
        $sessionData->token = $this->_token;
        $sessionData->payer_id = $this->_payer_id;
        $sessionData->first_name = $this->_first_name;
        $sessionData->last_name = $this->_last_name;
        $sessionData->payer_email = $this->_payer_email;
		$sessionData->txn_type = $this->_txn_type;

        $session->set('payu', json_encode($sessionData), 'vm');
    }

    public function reset() {
        $this->_selected_method = '';
        // card information
        $this->_cc_type = '';
        $this->_cc_number = '';
        $this->_cc_cvv = '';
        $this->_cc_expire_month = '';
        $this->_cc_expire_year = '';
        //Customer settings
        $this->_autobilling_max_amount = '';
        //PayPal Express
        $this->_token = '';
        $this->_payer_id = '';
        $this->_first_name = '';
        $this->_last_name = '';
        $this->_payer_email = '';
		$this->_txn_type = '';

        $this->save();
    }

    public function clear() {
        $session = JFactory::getSession();
        $session->clear('payu', 'vm');
    }

    public function getVar($var) {
        $this->load();
        return $this->{'_' . $var};
    }

    public function setVar($var, $val) {
        $this->{'_' . $var} = $val;
    }

    public function getData()
    {
        $this->_data;
    }
}