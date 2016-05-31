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

class PayuResponseData implements \IteratorAggregate
{
	private $_payuInterface = null;
	private $_data = '';

	public $_successful = false;
	public $_transaction_state = '';
	public $_transaction_type = '';
	public $_result_code = '';
	public $_result_message = '';
	public $_payu_reference = '';
	public $_payment_status = '';
	public $_amount = '';
	public $_currency_code = '';
	public $_transaction_id = '';

    public function setPayuInterface($api)
    {
        $this->_payuInterface = $api;
        $this->_data = $api->getPaymentInfo();
    }

    public function load() {

		if (!empty($this->_data)) {
			$data = $this->_data;
			// payment information
			$this->_successful = $data['return']['successful'];
			$this->_transaction_state = $data['return']['transactionState'];
			$this->_transaction_type = $data['return']['transactionType'];
			$this->_result_code = $data['return']['resultCode'];
			$this->_result_message = $data['return']['resultMessage'];
			$this->_payu_reference = $data['return']['payUReference'];
			$this->_amount = ($data['return']['paymentMethodsUsed']['amountInCents'] / 100);
			$this->_currency_code = $data['return']['basket']['currencyCode'];

			return $this;
		}
	}

	public function getVar($var) {
		$this->load();
		return $this->{'_' . $var};
	}

	public function setVar($var, $val) {
		$this->{'_' . $var} = $val;
	}

	public function getIterator() {
		return new \ArrayIterator($this);
	}
}
