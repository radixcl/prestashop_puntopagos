<?

/*
* pay_notif.php -- Receptor de notificacion de pago
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

function reslash_multi(&$val,$key) {
	if (is_array($val))
		array_walk($val,'reslash_multi',$new);
	else {
		$val = addslashes($val);
	}
}


$puntopagos = new puntopagos();
$db = Db::getInstance();


$method = $_SERVER['REQUEST_METHOD'];
$resource = $_SERVER['REQUEST_URI'];

$fecha = $_SERVER['HTTP_FECHA'];
$autorizacion = $_SERVER['HTTP_AUTORIZACION'];


if ($method == 'POST') {
	header('HTTP/1.1 100 Continue');
    $entity = file_get_contents('php://input');
	// decodificar json entity
	$decoded_entity = json_decode($entity, true);
	$monto = number_format((int)$decoded_entity['monto'], 2, '.', '');
	$data =  "transaccion/notificacion\n$decoded_entity[token]\n$decoded_entity[trx_id]\n$monto\n$fecha";
	
	$sign = base64_encode(hash_hmac('sha1', $data, $puntopagos_secret, true));
	$pp = "PP $puntopagos_keyid:$sign\r\n";
	
	// verificar firma
	if (trim($pp) == trim($autorizacion)) {
		// firma correcta
		if ($decoded_entity['respuesta'] == '00') {
			// respuesta correcta desde puntopagos
			$answer = array('respuesta' => '00',
							'token' => $decoded_entity['token']
							);
			$json_answer = json_encode($answer);
			echo 'Content-Length: ' . $_SERVER['CONTENT_LENGTH'] . "\n";
			echo 'Actual length: ' . strlen($json_answer) . "\n";
			//header('Entity body: ' . $json_answer . "\n");
			echo "\n$json_answer";
			array_walk($decoded_entity, 'reslash_multi');
			$query = "UPDATE `"._DB_PREFIX_."puntopagos` SET `status` = 'PAID', medio_pago = '$decoded_entity[medio_pago]', fecha_aprobacion = '$decoded_entity[fecha_aprobacion]', numero_tarjeta = '$decoded_entity[numero_tarjeta]', numero_cuotas = '$decoded_entity[numero_cuotas]', valor_cuota = '$decoded_entity[valor_cuota]', primer_vencimiento = '$decoded_entity[primer_vencimiento]', numero_operacion = '$decoded_entity[numero_operacion]', codigo_autorizacion = '$decoded_entity[codigo_autorizacion]' WHERE id = '$decoded_entity[trx_id]'";
			$db->Execute($query);
		} else {
			// pago rechazado o algun otro error desde puntopagos
			$answer = array('respuesta' => '00',
							'token' => $decoded_entity['token']
							);
			$json_answer = json_encode($answer);
			echo 'Content-Length: ' . $_SERVER['CONTENT_LENGTH'] . "\n";
			echo 'Actual length: ' . strlen($json_answer) . "\n";
			echo "\n$json_answer";
			array_walk($decoded_entity, 'reslash_multi');
			$query = "UPDATE `"._DB_PREFIX_."puntopagos` SET `status` = 'REJE', medio_pago = '$decoded_entity[medio_pago]', fecha_aprobacion = '$decoded_entity[fecha_aprobacion]', numero_tarjeta = '$decoded_entity[numero_tarjeta]', numero_cuotas = '$decoded_entity[numero_cuotas]', valor_cuota = '$decoded_entity[valor_cuota]', primer_vencimiento = '$decoded_entity[primer_vencimiento]', numero_operacion = '$decoded_entity[numero_operacion]', codigo_autorizacion = '$decoded_entity[codigo_autorizacion]' WHERE id = '$decoded_entity[trx_id]'";
			$db->Execute($query);
			
		}
	} else {
		// firma incorrecta
		$answer = array('respuesta' => '99',
						'token' => $decoded_entity['token'],
						'error' => "Firma incorrecta"
						);
		$json_answer = json_encode($answer);
		echo 'Content-Length: ' . $_SERVER['CONTENT_LENGTH'] . "\n" ;
		echo 'Actual length: ' . strlen($json_answer) . "\n" ;
	    //header('Entity body: ' . $json_answer . "\n");
		echo "\n$json_answer";
		$query = "UPDATE `"._DB_PREFIX_."puntopagos` SET `status` = 'FAIL' WHERE id = '$decoded_entity[trx_id]'";
		$db->Execute($query);
	}

}



?>