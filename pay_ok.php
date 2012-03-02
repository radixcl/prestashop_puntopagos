<?

/*
* pay_ok.php -- Conclusion de pago exitoso
* Parte de modulo de pagos de puntopagos para prestashop
*
* Author: Matias Fernandez <matias.fernandez@gmail.com>
* Copyright (C) 2012  Matias Fernandez
*
*/

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/puntopagos_config.php');
require_once(dirname(__FILE__).'/puntopagos.php');

if (!$cookie->isLogged())
    Tools::redirect('authentication.php?back=order.php');

$token = $_REQUEST['token'];
$token = addslashes($token);

$db = Db::getInstance();


if (trim($token) == '') {
	echo "ERROR: Token no v&aacute;lido";
	die();	
}

// verificar que token exista previamente en la BD
$result = $db->ExecuteS('SELECT id FROM `'._DB_PREFIX_.'puntopagos` WHERE `token` ="' . $token . '";');
if ($result[0]['id'] == '') {
	echo "ERROR: Token no v&aacute;lido";
	die();
}

$paid = 0;
// esperar por cambio de status del token a PAID
// quien cambia el status es puntopagos a traves de REST en pay_notif.php
for ($i = 0; $i < 10; $i++) {	// 10 intentos, 10 segundos
	$result = $db->ExecuteS('SELECT status FROM `'._DB_PREFIX_.'puntopagos` WHERE `token` ="' . $token . '";');
	if ($result[0]['status'] == 'PAID') {
		$paid = 1;
		break;
	}
	if ($result[0]['status'] == 'FAIL') {
		$paid = 2;
		break;
	}
	sleep(1);
}

$total = floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', ''));
$currency = new Currency(intval(isset($_POST['currency_payement']) ? $_POST['currency_payement'] : $cookie->id_currency));

$customer = new Customer((int)$cart->id_customer);
$puntopagos = new puntopagos();


// pagado o no pagado?
if ($paid == 0) {
	echo "ERROR: No se recibi&oacute; notificaci&oacute;n de pago desde puntopagos.<br>";
	echo "Token: " . htmlentities($token) . '<br>';
	echo "Por favor comuniquese con la administraci&oacute;n del sitio y notifique este error.";
	$query = "UPDATE `"._DB_PREFIX_."puntopagos` SET `status` = 'ERRN', `error` = 'No se recibe notificacion desde puntopagos' WHERE token = '$token' AND `status` != 'FAIL'";
	$db->Execute($query);
	$puntopagos->validateOrder((int)($cart->id), Configuration::get('PS_OS_ERROR'), $total, $puntopagos->displayName, 'No se recibio notificacion de pagos desde puntopagos, token: ' . htmlentities($token), array(), NULL, false, $customer->secure_key);
	echo "<hr>";
	$location = __PS_BASE_URI__.'order-confirmation.php?id_cart='.$cart->id.'&id_module='.$puntopagos->id.'&id_order='.$puntopagos->currentOrder.'&key='.$order->secure_key;
	echo '<button type="button" onclick="window.location=\'' . $location . '\';">Continuar</button>';
	die();
} else if ($paid == 2) {
	echo "ERROR: Firma de notificacion de pago invalida.<br>";
	echo "Token: " . htmlentities($token) . '<br>';
	echo "Por favor comuniquese con la administraci&oacute;n del sitio y notifique este error.";
	$puntopagos->validateOrder((int)($cart->id), Configuration::get('PS_OS_ERROR'), $total, $puntopagos->displayName, 'Firma de notificacion de pagos invalida, token: ' . htmlentities($token), array(), NULL, false, $customer->secure_key);
	echo "<hr>";
	$location = __PS_BASE_URI__.'order-confirmation.php?id_cart='.$cart->id.'&id_module='.$puntopagos->id.'&id_order='.$puntopagos->currentOrder.'&key='.$order->secure_key;
	echo '<button type="button" onclick="window.location=\'' . $location . '\';">Continuar</button>';
	die();
}

if ($cart->id_customer == 0 OR $cart->id_address_delivery == 0 OR $cart->id_address_invoice == 0)
        Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');



// procesar orden
$puntopagos->validateOrder((int)($cart->id), _PS_OS_PREPARATION_, $total, $puntopagos->displayName, "Pagado con puntopagos, token: $token", array(), (int)($currency->id), false,$customer->secure_key);


Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?id_cart='.$cart->id.'&id_module='.$puntopagos->id.'&id_order='.$puntopagos->currentOrder.'&key='.$order->secure_key);

?>