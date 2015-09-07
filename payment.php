<?php

/*
* payment.php -- Comenzar el proceso de pagos
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

if (!Context::getContext()->customer->isLogged())
    Tools::redirect('authentication.php?back=order.php');

$time = time();
setlocale(LC_TIME, 'C');

$puntopagos = new puntopagos();

$total = $cart->getOrderTotal(true, Cart::BOTH);
$total2 = number_format((int)$total, 2, '.', '');
//$uniqid = date('Uu');
$uniqid = (int)($cart->id);

$authorization_string = $puntopagos->authorization_string('create', $uniqid, $total,$time, '');
$id_customer = $cookie->{'id_customer'};

$headers = $puntopagos->create_headers($authorization_string, $time, $puntopagos_keyid, $puntopagos_secret);
$body = array('trx_id' => $uniqid,
			  'medio_pago' => '3',
			  'monto' => $total2,
			  'detalle' => '',
			  'fecha' => strftime($puntopagos->RFC1123_FORMAT, $time));
$json_body = json_encode($body);

$response = $puntopagos->do_post_request("https://$puntopagos_host/transaccion/crear", $json_body, $headers);

$db = Db::getInstance();
$query = "INSERT INTO `"._DB_PREFIX_."puntopagos` (id, id_customer, total, status, `date`) VALUES ($uniqid, '$id_customer', '$total', 'INIT', NOW() )";
$db->Execute($query);

$json_response = json_decode($response);

if ($json_response->{'respuesta'} != '00') {
	// puntopagos ha respondido con un error
	$query = "UPDATE `"._DB_PREFIX_."puntopagos` SET `status` = 'ERR', `error` = '" . $json_response->{'error'} . "' WHERE id = '$uniqid'";
	$db->Execute($query);
	header('Location: trx_error.php?tid=' . $uniqid);
	die();
}

$query = "UPDATE `"._DB_PREFIX_."puntopagos` SET `status` = 'PAY', `token` = '" . $json_response->{'token'} . "' WHERE id = '$uniqid'";
$db->Execute($query);

Tools::redirectLink('https://' . $puntopagos_host . '/transaccion/procesar/' . $json_response->{'token'});

?>