<?php

/*
* @author Kenneth Onah.
* @version $Id: PAYU-SA.php 7487 2016-04-11 15:03:42Z alatak $
* @package VirtueMart
* @subpackage payment
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
*
* http://virtuemart.org
*/

defined ('_JEXEC') or die('Restricted access');

if (!class_exists ('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
if (!class_exists('PayuEasyPlusApi')) {
    require(VMPATH_ROOT . DS.'plugins'. DS.'vmpayment'. DS.'payu_easyplus'. DS.'payu_easyplus'. DS.'helpers'. DS .'payuapi.php');
}
if (!class_exists('PayuRequestData')) {
    require(VMPATH_ROOT .   DS.'plugins'. DS.'vmpayment'. DS.'payu_easyplus'. DS.'payu_easyplus'. DS.'helpers'. DS.'requestdata.php');
}
if (!class_exists('PayuResponseData')) {
    require(VMPATH_ROOT .   DS.'plugins'. DS.'vmpayment'. DS.'payu_easyplus'. DS.'payu_easyplus'. DS.'helpers'. DS.'responsedata.php');
}

class plgVmPaymentPayu_easyplus extends vmPSPlugin {

    function __construct (& $subject, $config) {

        parent::__construct ($subject, $config);

        // unique filelanguage for all SKRILL methods
        $jlang = JFactory::getLanguage ();
        $jlang->load ('plg_vmpayment_payu_easyplus', JPATH_ADMINISTRATOR, null, true);
        $this->_loggable = true;
        $this->_debug = true;
        $this->tableFields = array_keys ($this->getTableSQLFields ());
        $this->_tablepkey = 'id'; //virtuemart_SKRILL_id';
        $this->_tableId = 'id'; //'virtuemart_SKRILL_id';
        $varsToPush = $this->getVarsToPushToTable();
        $this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL () {

        return $this->createTableSQL ('Payment PayU EasyPlus Table');
    }

    protected function getVarsToPushToTable() {
        return array('safe_key'          	=> array('', 'char'),
                     'api_username'      	=> array('', 'char'),
                     'api_password'      	=> array('', 'char'),
                     'merchant_ref'      	=> array('', 'char'),
                     'payment_action'    	=> array('', 'char'),
                     'payment_currency'  	=> array('', 'char'),
                     'payment_logos'     	=> array('', 'char'),
                     'gateway'           	=> array(0, 'int'),
                     'countries'         	=> array('', 'char'),
                     'cost_per_transaction' => array('', 'int'),
		             'cost_percent_total' 	=> array('', 'int'),
                     'secure3d'          	=> array(0, 'int'),
                     'redirect_channel'  	=> array('', 'char'),
                     'payment_method'    	=> array('', 'char'),
                     'min_amount'        	=> array('', 'int'),
                     'max_amount'        	=> array('', 'int'),
                     'tax_id'              	=> array(0, 'int'),
                     'status_pending'    	=> array('', 'char'),
                     'status_success'    	=> array('', 'char'),
                     'status_cancelled'   	=> array('', 'char'),
                     'status_denied'        => array('', 'char'));
    }

    function _processStatus (&$mb_data, $vmorder, $method) {

        switch ($mb_data->getVar('result_code')) {
            case 00 :
                $mb_data->setVar('payment_status', $method->status_success);
                break;
            case 301 :
            case 999 :
                $mb_data->setVar('payment_status', $method->status_cancelled);
                break;
            default:
                $mb_data->setVar('payment_status', $method->status_denied);
                break;
        }

        // TODO
        $total_to_pay = round($vmorder['details']['BT']->order_total, 2);
        $amount_paid = $mb_data->getVar('amount');
        
        if($mb_data->getVar('successful')) {
            if($total_to_pay != $amount_paid) {
                return true;
            }
        }
        return false;
    }

    function _getPaymentResponseHtml ($paymentTable, $payment_name) {
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table>' . "\n";
        $html .= $this->getHtmlRow ('COM_VIRTUEMART_PAYMENT_NAME', $payment_name);

        if (!empty($paymentTable)) {
            $html .= $this->getHtmlRow ('VMPAYMENT_PAYU_ORDER_NUMBER', $paymentTable->order_number);
        }
        $html .= '</table>' . "\n";

        return $html;
    }

    function _getInternalData ($virtuemart_order_id, $order_number = '') {

        $db = JFactory::getDBO ();
        $q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
        if ($order_number) {
            $q .= " `order_number` = '" . $order_number . "'";
        } else {
            $q .= ' `virtuemart_order_id` = ' . $virtuemart_order_id;
        }

        $db->setQuery ($q);
        if (!($paymentTable = $db->loadObject ())) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        return $paymentTable;
    }

    function _storeInternalData ($method, $mb_data, $virtuemart_order_id) {

        // get all know columns of the table
        $db = JFactory::getDBO ();
        $query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
        $db->setQuery ($query);
        $columns = $db->loadColumn (0);
        
        $post_msg = '';
        foreach ($mb_data as $key => $value) {
            $post_msg .= $key . "=" . $value . "<br />";
            $table_key = 'mb' . $key;
            if (in_array ($table_key, $columns)) {
                $response_fields[$table_key] = $value;
            }
        }

        $response_fields['payment_name'] = $this->renderPluginName ($method);
        $response_fields['mbresponse_raw'] = $post_msg;
        $response_fields['order_number'] = $mb_data->getVar('transaction_id');
        $response_fields['virtuemart_order_id'] = $virtuemart_order_id;
        $this->storePSPluginInternalData ($response_fields, 'virtuemart_order_id', true);
    }

    function getTableSQLFields () {

        $SQLfields = array(
            'id'                            => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'           => 'int(1) UNSIGNED',
            'order_number'                  => ' char(64)',
            'virtuemart_paymentmethod_id'   => 'mediumint(1) UNSIGNED',
            'payment_name'                  => 'varchar(5000)',
            'payment_order_total'           => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'              => 'char(3) ',
            'cost_per_transaction'          => 'decimal(10,2)',
            'cost_percent_total'            => 'decimal(10,2)',
            'tax_id'                        => 'smallint(1)',
            'user_session'                  => 'varchar(255)',

            // status report data returned by PayU to the merchant
            'mb_successful'         => 'tinyint(1)',
            'mb_transaction_state'  => 'varchar(255)',
            'mb_transaction_type'	=> 'varchar(255)',
            'mb_result_code' 		=> 'varchar(255)',
            'mb_result_message'		=> 'varchar(255)',
            'mb_payu_reference'     => 'varchar(255)',
            'mb_payment_action' 	=> 'varchar(255)',
            'mb_amount'    		   	=> 'decimal(19,2)',
            'mb_currency_code'      => 'char(3)',
            'mbresponse_raw'        => 'varchar(512)'
        );

        return $SQLfields;
    }

    function plgVmConfirmedOrder ($cart, $order) {

        if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        } // Another method was selected, do nothing

        if (!$this->selectedThisElement ($method->payment_element)) {
            return false;
        }

        $session = JFactory::getSession ();
        $return_context = $session->getId ();

        if (!class_exists ('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists ('VirtueMartModelCurrency')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
        }

        $usrBT = $order['details']['BT'];
        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

        if (!class_exists ('TableVendors')) {
            require(VMPATH_ADMIN . DS . 'tables' . DS . 'vendors.php');
        }

        $vendorModel = VmModel::getModel ('Vendor');
        $vendorModel->setId (1);
        $vendor = $vendorModel->getVendor ();
        $vendorModel->addImages ($vendor, 1);
        $this->getPaymentCurrency ($method);

        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
            $method->payment_currency . '" ';
        $db = JFactory::getDBO ();
        $db->setQuery ($q);
        $currency_code_3 = $db->loadResult ();

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);
        $cartCurrency = CurrencyDisplay::getInstance($cart->pricesCurrency);

        if ($totalInPaymentCurrency['value'] <= 0) {
            vmInfo (vmText::_ ('VMPAYMENT_PAYU_PAYMENT_AMOUNT_INCORRECT'));
            return false;
        }

        if (empty($method->safe_key)
        	OR empty($method->api_username)
    		OR empty($method->api_password)) {
            vmInfo (vmText::_ ('VMPAYMENT_PAYU_INVALID_CONFIGURATION'));
            return false;
        }

        $payuInterface = $this->_loadPayUInterface($method);
        $payuInterface->debugLog('order number: ' . 
        	$order['details']['BT']->order_number, 'plgVmConfirmedOrder', 'message');
        $payuInterface->setCart($cart);
        $payuInterface->setOrder($order);
        $payuInterface->setTotal($totalInPaymentCurrency);
        $payuInterface->setcurrencyCode($currency_code_3);
        $payuInterface->prepareRequestData();

        // Prepare data that should be stored in the database
        $dbValues['user_session'] = $return_context;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName ($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData ($dbValues);

		$result = $payuInterface->postPayURequestData();
        if ($result && !$result['return']['successful']) {
        	$errstr = $result['return']['resultMessage'];
        	$errno = $result['return']['resultCode'];
            $this->sendEmailToVendorAndAdmins ("Error with PAYU: ",
                vmText::sprintf ('VMPAYMENT_PAYU_ERROR_POSTING_IPN', $errstr, $errno));
            $this->logInfo ('Process IPN ' . vmText::sprintf ('VMPAYMENT_PAYU_ERROR_POSTING_IPN', $errstr, $errno), 'message');

            vmInfo (vmText::_ ('VMPAYMENT_PAYU_DISPLAY_GWERROR'));
            return false;
        } 

        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession ();

        $checkoutUrl = $payuInterface->getCheckoutUrl() . '?PayUReference=' . $result['return']['payUReference'];
        $app = JFactory::getApplication();
		$app->redirect($checkoutUrl);
    }

    function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
            return null;
        } // Another method was selected, do nothing

        if (!$this->selectedThisElement ($method->payment_element)) {
            return false;
        }

        $this->getPaymentCurrency ($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnPaymentResponseReceived (&$html) {

        if (!class_exists ('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $reference = vRequest::get('PayUReference', '');
        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = vRequest::getInt ('pm', 0);
        $order_number = vRequest::getString ('on', 0);

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
            return;
        }

        if (!($payment = $this->getDataByOrderId ($virtuemart_order_id))) {
            return;
        }

        $method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement ($method->payment_element)) {
            return false;
        }

        if (!$payment) {
            $this->logInfo ('getDataByOrderId payment not found: exit ', 'ERROR');
            return null;
        }

        if($virtuemart_order_id) {
            $modelOrder = VmModel::getModel ('orders');
            $order = $modelOrder->getOrder ($virtuemart_order_id);
            $payuInterface = $this->_loadPayUInterface($method, $this);
            $payuInterface->setOrder($order);
            $payuInterface->setPayUReference($reference);
            $mb_data = $payuInterface->getTransactionInfo();

            if(!empty($mb_data)) {
                $mb_data->setVar('transaction_id', $order_number);
                $this->_storeInternalData ($method, $mb_data, $virtuemart_order_id);

                $error_msg = $this->_processStatus ($mb_data, $order, $method);
                if ($error_msg) {
                    $order['customer_notified'] = 0;
                    $order['order_status'] = $method->status_cancelled;
                    $order['comments'] = vmText::_('VMPAYMENT_PAYU_PAYMENT_AMOUNT_INCORRECT');
                    $this->logInfo ('process IPN ' . $error_msg, 'ERROR');
                } else {
                    $this->logInfo ('process IPN OK', 'message');
                }

                if (strcmp ($mb_data->getVar('payment_status'), $method->status_success) == 0) {
                    $order['customer_notified'] = 1;
                    $order['order_status'] = $method->status_success;
                    $order['comments'] = vmText::sprintf ('VMPAYMENT_PAYU_PAYMENT_STATUS_CONFIRMED', $order_number);
                    vmInfo($order['comments']);
                } elseif (strcmp ($mb_data->getVar('payment_status'), $method->status_denied) == 0) {
                    $order['customer_notified'] = 0;
                    $order['comments'] = vmText::sprintf ('VMPAYMENT_PAYU_PAYMENT_STATUS_DENIED', $order_number, $mb_data->getVar('result_message'));
                    $order['order_status'] = $method->status_denied;
                    vmError($order['comments'], $order['comments']);
                } else {
                    $order['customer_notified'] = 0;
                    $order['comments'] = vmText::sprintf ('VMPAYMENT_PAYU_PAYMENT_STATUS_CANCELLED', $order_number, $mb_data->getVar('result_message'));
                    $order['order_status'] = $method->status_cancelled;
                    vmInfo(vmText::_($order['comments']));
                }

                $this->logInfo ('plgVmOnPaymentNotification return new_status:' . $order['order_status'], 'message');

                $modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, true);

                vmdebug ('PayU plgVmOnPaymentResponseReceived', $mb_data);
                $payment_name = $this->renderPluginName ($method);
                $html = $this->_getPaymentResponseHtml ($payment, $payment_name);
                $link=	JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=".$order['details']['BT']->order_number."&order_pass=".$order['details']['BT']->order_pass, false) ;

                $html .='<br />
        		<a class="vm-button-correct" href="'.$link.'">'.vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER').'</a>';
            }
        }

        $cart = VirtueMartCart::getCart ();
        $cart->emptyCart ();
        return true;
    }

    function plgVmOnUserPaymentCancel () {

        if (!class_exists ('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $order_number = vRequest::getString ('on', '');
        $virtuemart_paymentmethod_id = vRequest::getInt ('pm', '');
        $reference = vRequest::getString('payUReference', '');

        if (empty($order_number) ||
            empty($virtuemart_paymentmethod_id) ||
            !$this->selectedThisByMethodId ($virtuemart_paymentmethod_id)
        ) {
            return null;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
            return null;
        }

        if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
            return null;
        }

        $method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id);
        if (!$this->selectedThisElement ($method->payment_element)) {
            return null;
        }
        $modelOrder = VmModel::getModel ('orders');
        $order = $modelOrder->getOrder ($virtuemart_order_id);
        $payuInterface = $this->_loadPayUInterface($method, $this);
        $payuInterface->setOrder($order);
        $payuInterface->setPayUReference($reference);
        $mb_data = $payuInterface->getTransactionInfo();

        VmInfo (vmText::sprintf('VMPAYMENT_PAYU_PAYMENT_CANCELLED', $order_number, $mb_data->getVar('result_message')));
        $session = JFactory::getSession ();
        $return_context = $session->getId ();
        if (strcmp ($paymentTable->user_session, $return_context) === 0) {
            $this->handlePaymentUserCancel ($virtuemart_order_id);
        }

        return true;
    }

    function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $payment_method_id) {

        if (!$this->selectedThisByMethodId ($payment_method_id)) {
            return null;
        } // Another method was selected, do nothing

        if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }

        $this->getPaymentCurrency ($paymentTable);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
            $paymentTable->payment_currency . '" ';
        $db = JFactory::getDBO ();
        $db->setQuery ($q);
        $currency_code_3 = $db->loadResult ();
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE ();
        $html .= $this->getHtmlRowBE ('PAYMENT_NAME', $paymentTable->payment_name);

        $code = "mb_";
        foreach ($paymentTable as $key => $value) {
            if (substr ($key, 0, strlen ($code)) == $code) {
                $html .= $this->getHtmlRowBE ($key, $value);
            }
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    protected function checkConditions ($cart, $method, $cart_prices) {

        $this->convert_condition_amount($method);

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $this->getCartAmount($cart_prices);
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
            OR
            ($method->min_amount <= $amount AND ($method->max_amount == 0)));

        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array ($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array ($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {
            if ($amount_cond) {
                return true;
            }
        }

        return false;
    }

    private function _loadPayUInterface($method) {

        if ($method->payment_element == 'payu_easyplus') {
            $payuInterface = new PayuEasyPlusApi($method, $this);
            $payuInterface->init();
        } else {
            Vmerror('Wrong payu mode');
            return null;
        }

        return $payuInterface;
    }
    /**
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

        return $this->onStoreInstallPluginTable ($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
     *
     */
    public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg) {

        return $this->OnSelectCheck ($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object  $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on success, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

        return $this->displayListFE ($cart, $selected, $htmlIn);
    }


    public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

        return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

        return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

        $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

    public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
    return null;
    }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

        return $this->onShowOrderPrint ($order_number, $method_id);
    }

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not activated.

    public function plgVmOnUpdateOrderPayment(  $_formData) {
    return null;
    }
     */
    /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.

    public function plgVmOnUpdateOrderLine(  $_formData) {
    return null;
    }
     */
    /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise

    public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
    return null;
    }
     */

    /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise

    public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
    return null;
    }
     */
    function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

        return $this->setOnTablePluginParams ($name, $id, $table);
    }

} // end of class plgVmpaymentSkrill

// No closing tag
