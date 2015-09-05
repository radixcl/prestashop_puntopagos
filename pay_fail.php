<?php

/*
* pay_fail.php -- Manejar fallo de pago
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

$customer = new Customer((int)$cart->id_customer);
$puntopagos = new puntopagos();


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

$puntopagos->validateOrder((int)($cart->id), Configuration::get('PS_OS_ERROR'), $total, $puntopagos->displayName, 'Pago rechazado por puntopagos', array(), NULL, false, $customer->secure_key);

Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?id_cart='.$cart->id.'&id_module='.$puntopagos->id.'&id_order='.$puntopagos->currentOrder.'&key='.$order->secure_key);

?>