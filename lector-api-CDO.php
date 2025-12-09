<?php

set_time_limit(0);


require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;


// ====================================================================================
// Conexión API destino
// ====================================================================================

$url_API_woo = 'https://enmasapromo.cl';
$ck_API_woo = 'ck_728d2d4d51821dd4b926385df43baa5c0c3ea7a7';
$cs_API_woo = 'cs_a676d53d286fa3ba81ed6dde7ffadde6e8b29053';


$total_pages = 5;

for ($page_number = 1; $page_number <= $total_pages; $page_number++) {
    call_api($url_API_woo, $ck_API_woo,$cs_API_woo, $page_number, $total_pages);
}

function call_api($url_API_woo, $ck_API_woo,$cs_API_woo, $page_number, $total_pages)
{

    
    // ====================================================================================
    // Conexión API origen
    // ====================================================================================
    
    
    $url_API="http://api.chile.cdopromocionales.com/v2/products?auth_token=2HPTRKTWv1UAD2cXekjDWQ&page_size=100&page_number=".$page_number;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_API);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 segundos para conectar
    curl_setopt($ch, CURLOPT_TIMEOUT, 8400); // 2 horas 20 minutos en segundos
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Verificar SSL si se usa HTTP

	print("\n");
	print("✔  HORA INICIO LECTURA API: " . date("h:i:sa") . " \n");

	$items_origin = curl_exec($ch);
	curl_close($ch);

	if (!$items_origin) {
		exit('❗Error en API origen');
	}
    
    print("✔  HORA FIN LECTURA API: ". date("h:i:sa")." \n");
    
    $items_origin = json_decode($items_origin, true);
    $items_origin = $items_origin['products'];
    $cantidad_de_productos_api = count($items_origin);

    print("\n");
    status_message('Cantidad Productos Por SKU: '. $cantidad_de_productos_api);
    print("\n");

}

?>