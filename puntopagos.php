<?

/*
* puntopagos.php -- Declaracion de objetos y rutinas principal
* Parte de modulo de pagos de puntopagos para prestashop
*
* Author: Matias Fernandez <matias.fernandez@gmail.com>
* Copyright (C) 2012  Matias Fernandez
*
*/


class puntopagos extends PaymentModule  //this declares the class and specifies it will extend the standard payment module
{
 
    private $_html = '';
    private $_postErrors = array();
 
    function __construct()
    {
        $this->name = 'puntopagos';
        $this->tab = 'payments_gateways';
        $this->version = 1;
 
        parent::__construct(); // The parent construct is required for translations
 
        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Puntopagos');
        $this->description = $this->l('Pague con PuntoPagos');
		
		$this->RFC1123_FORMAT = '%a, %d %b %Y %H:%M:%S GMT';
 
}

	public function install() {
		if (!parent::install()
		OR !$this->createPaymentcardtbl() //calls function to create payment card table
		            OR !$this->registerHook('invoice')
		OR !$this->registerHook('payment')
		OR !$this->registerHook('paymentReturn'))
		return false;
		return true;
	}
	
	function uninstall() {
		return (
			parent::uninstall() AND $this->dropPaymentcardtbl()
			);
	}
	
	function createPaymentcardtbl()	{
		/**Function called by install - 
		* creates the "order_paymentcard" table required for storing payment card details
		* Column Descriptions: id_payment the primary key. 
		* id order: Stores the order number associated with this payment card
		* cardholder_name: Stores the card holder name
		* cardnumber: Stores the card number
		* expiry date: Stores date the card expires
		*/
		
		$db = Db::getInstance();
		
		$query = "CREATE TABLE `"._DB_PREFIX_."puntopagos` (
			`id` varchar(255) COLLATE utf8_bin NOT NULL,
			`trx_id` varchar(255) COLLATE utf8_bin DEFAULT NULL,
			`id_customer` int(10) NOT NULL,
			`token` varchar(255) COLLATE utf8_bin DEFAULT NULL,
			`total` float NOT NULL,
			`error` varchar(255) COLLATE utf8_bin NOT NULL,
			`status` varchar(4) COLLATE utf8_bin NOT NULL,
			`date` datetime NOT NULL,
			`medio_pago` varchar(255) COLLATE utf8_bin NOT NULL,
			`fecha_aprobacion` varchar(255) COLLATE utf8_bin NOT NULL,
			`numero_tarjeta` varchar(32) COLLATE utf8_bin NOT NULL,
			`numero_cuotas` int(3) NOT NULL,
			`valor_cuota` float NOT NULL,
			`primer_vencimiento` varchar(255) COLLATE utf8_bin NOT NULL,
			`numero_operacion` varchar(255) COLLATE utf8_bin NOT NULL,
			`codigo_autorizacion` varchar(255) COLLATE utf8_bin NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `id` (`id`),
			UNIQUE KEY `trx_id` (`trx_id`),
			KEY `token` (`token`)
		  ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$db->Execute($query);
		
		return true;
	}
	
	function dropPaymentcardtbl() {
		$db = Db::getInstance();
		
		$query = "DROP TABLE IF EXISTS "._DB_PREFIX_."puntopagos`";
		
		$db->Execute($query);
		
		return true;
	}

	/**
	* hookPayment($params)
	* Called in Front Office at Payment Screen - displays user this module as payment option
	*/
	function hookPayment($params) 	{
		global $smarty;
		
		$smarty->assign(array('this_path' => $this->_path, 'this_path_ssl' => Configuration::get('PS_FO_PROTOCOL').$_SERVER['HTTP_HOST'].__PS_BASE_URI__."modules/{$this->name}/"));
		
		return $this->display(__FILE__, 'payment.tpl');
	}


	/*public function execPayment($cart) {
		if (!$this->active)
		return ;
		
		global $cookie, $smarty;
		
		$smarty->assign(array( 'this_path' => $this->_path, 'this_path_ssl' => (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/'.$this->name.'/' 		));
		
		//return $this->display(__FILE__, 'payment_execution.tpl');
		echo $cart;
		return;
	}*/
	

	public function do_post_request($url, $data, $optional_headers = null) {
	  $params = array('http' => array(
				  'method' => 'POST',
				  'content' => $data
				));
	  if ($optional_headers !== null) {
		$params['http']['header'] = $optional_headers;
	  }
	  $ctx = stream_context_create($params);
	  $fp = fopen($url, 'rb', false, $ctx);
	  if (!$fp) {
		die("Problem with $url, $php_errormsg");
	  }
	  $response = @stream_get_contents($fp);
	  if ($response === false) {
		die("Problem reading data from $url, $php_errormsg");
	  }
	  return $response;
	}

	public function authorization_string($action, $trx_id, $monto, $fecha, $token='') {
		// $action = create or status
		
		$monto = number_format((int)$monto, 2, '.', '');
		$fecha = strftime($this->RFC1123_FORMAT, $fecha);
		
		if ($action == 'create') {
			$retval = "transaccion/crear\n$trx_id\n$monto\n$fecha";
		} else {
			$retval = "transaccion/traer\n$token\n$trx_id\n$monto\n$fecha";
		}
		return($retval);
	}

	public function create_headers($authorization_string, $time, $puntopagos_keyid, $puntopagos_secret) {
		$sign = base64_encode(hash_hmac('sha1', $authorization_string, $puntopagos_secret, true));
		$retval =  "Fecha: ". strftime($this->RFC1123_FORMAT, $time). "\r\n";
		$retval .= "Autorizacion: PP $puntopagos_keyid:$sign\r\n";
		$retval .= "Content-Type: application/json\r\n";
		return($retval);
	}
}
?>