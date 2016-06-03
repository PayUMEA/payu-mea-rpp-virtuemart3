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
	private $payuInterface = null;

	public $successful = false;
	public $transaction_state = '';
	public $transaction_type = '';
	public $result_code = '';
	public $result_message = '';
	public $payu_reference = '';
	public $payment_status = '';
	public $amount = '';
	public $currency_code = '';
	public $transaction_id = '';

    public function setPayuInterface($api)
    {
        $this->payuInterface = $api;
    }

    public function load() 
    {
    	$data = $this->payuInterface->getPaymentInfo();
		if (!empty($data)) {
			// payment information
			$this->setVar('successful', $data['return']['successful']);
			$this->setVar('transaction_state', $data['return']['transactionState']);
			$this->setVar('transaction_type', $data['return']['transactionType']);
			$this->setVar('result_code', $data['return']['resultCode']);
			$this->setVar('result_message', $data['return']['resultMessage']);
			$this->setVar('payu_reference', $data['return']['payUReference']);
			$this->setVar('amount', ($data['return']['paymentMethodsUsed']['amountInCents'] / 100));
			$this->setVar('currency_code', $data['return']['basket']['currencyCode']);

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
