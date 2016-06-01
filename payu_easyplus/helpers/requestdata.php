<?php
/**
 * Created by PhpStorm.
 * User: netcraft
 * Date: 4/8/16
 * Time: 10:12 PM
 */
class PayURequestData
{
    private $payuInterface = null;

    public function setPayuInterface($api)
    {
        $this->payuInterface = $api;
    }

    public function loadRequestData() {
        $api = $this->payuInterface;
        $paymentMethod = $api->getPaymentMethod();
        $order = $api->getOrder();
        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);
        $total = $api->getTotal();

        if($paymentMethod->check() && $paymentMethod->enabled()) {
            $data = array(
                'Api' => $api->getApiVersion(),
                'Safekey' => trim($api->getSafeKey()),
                'TransactionType' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_METHOD,
                'AdditionalInformation' => array(
                    'merchantReference' => MODULE_PAYMENT_PAYU_EASYPLUS_MERCHANT_REFERENCE,
                    'supportedPaymentMethods' => implode(',',MODULE_PAYMENT_PAYU_EASYPLUS_PAYMENT_METHODS),
                    'demoMode' => MODULE_PAYMENT_PAYU_EASYPLUS_TRANSACTION_SERVER == 'Live' ? 'false' : 'true',
                    'secure3d' => MODULE_PAYMENT_PAYU_EASYPLUS_SECURE3D ? 'true' : 'false',
                    'returnUrl' => tep_href_link('ext/modules/payment/paypal/express.php', 'osC_Action=retrieve', 'SSL', true),
                    'cancelUrl' => tep_href_link('ext/modules/payment/paypal/express.php', 'osC_Action=cancel', 'SSL', true, false),
                    //'notificationUrl' => JURI::root () . 'index.php?' .
                    //    'option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component' .
                    //    '&lang=' . vRequest::getCmd('lang',''),
                    'redirectChannel' => MODULE_PAYMENT_PAYU_EASYPLUS_PAGE_STYLE
                ),
                //customer details
                'Customer' => array(
                    "firstName" => $address->first_name,
                    "lastName" => $address->last_name,
                    "mobile" => '27'.str_replace('-', '', $address->phone_1),
                    "email" => $address->email,
                ),
                'Basket' => array(
                    // payment details section
                    'description' => MODULE_PAYMENT_PAYU_EASYPLUS_ORDER_DESCRIPTION . ': ' . $order['details']['BT']->order_number,
                    'amountInCents' => (string)($total['value'] * 100),
                    'currencyCode' => $api->getCurrencyCode(),
                )
            );
            PayuEasyPlusApi::setPayURequestData($data);
        } else {
            tep_redirect(tep_href_link(FILENAME_SHOPPING_CART, '', 'SSL'));
        }
    }

    public function getVar($var) {
        $this->load();
        return $this->{'_' . $var};
    }

    public function setVar($var, $val) {
        $this->{'_' . $var} = $val;
    }
}