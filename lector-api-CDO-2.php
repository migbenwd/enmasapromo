<?php

set_time_limit(0);

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

// Nombre del archivo de salida JSON/JS
const DATA_FILE = 'lectura_api_cdo.js';
// Tamaño de página deseado
const PAGE_SIZE = 100;

// Array acumulador general para almacenar todos los productos de las páginas
$productos_acumulados = [];

// ====================================================================================
// Conexión API destino (WooCommerce) - Se mantiene pero no se usa en este paso
// ====================================================================================

$url_API_woo = 'https://enmasapromo.cl';
$ck_API_woo = 'ck_728d2d4d51821dd4b92638321287163829053'; 
$cs_API_woo = 'cs_a676d53d286fa3ba81ed6dde7ffadde6e8b29053'; 

$total_pages = 5; // Mantener 5 páginas por defecto

// 1. Verificar si existe y preparar la cabecera del archivo JS
status_message('Preparando el archivo de datos: ' . DATA_FILE);

try {
    // Definimos el contenido inicial como una variable JavaScript (array vacío)
    $js_variable_declaration = "const CDO_PRODUCTS_DATA = [];\n\n";

    if (file_put_contents(DATA_FILE, $js_variable_declaration) === false) {
        throw new Exception("No se pudo crear o inicializar el archivo: " . DATA_FILE);
    }
    status_message('✔ Archivo ' . DATA_FILE . ' inicializado para guardar datos.');
} catch (Exception $e) {
    status_message('❗ ERROR CRÍTICO al manejar el archivo: ' . $e->getMessage());
    exit();
}

// Recorrer las páginas de la API
for ($page_number = 1; $page_number <= $total_pages; $page_number++) {
    call_api($url_API_woo, $ck_API_woo, $cs_API_woo, $page_number, $total_pages, $productos_acumulados);
}

// 3. Guardar todo el contenido acumulado en el archivo JS
try {
    // 3.1 Convertir el array completo a una cadena JSON
    // Usamos JSON_UNESCAPED_UNICODE para manejar caracteres especiales
    $json_productos = json_encode($productos_acumulados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // 3.2 Crear la asignación a la variable JavaScript
    // Nota: El array original lo guardamos como un array de objetos, donde cada objeto
    // representa una página y contiene la lista de productos de esa página.
    $js_content = "const CDO_PRODUCTS_DATA = " . $json_productos . ";\n";
    
    // Escribir el contenido final en el archivo, reemplazando el contenido inicial
    if (file_put_contents(DATA_FILE, $js_content) === false) {
        throw new Exception("No se pudo escribir en el archivo: " . DATA_FILE);
    }
    status_message("\n✔ LECTURA COMPLETA. Datos guardados en la variable CDO_PRODUCTS_DATA de " . DATA_FILE . ".");
} catch (Exception $e) {
    status_message('❗ ERROR AL ESCRIBIR EN ARCHIVO: ' . $e->getMessage());
}

// Función auxiliar para imprimir mensajes de estado
function status_message($message)
{
    print($message . " \n");
}


/**
 * Llama a la API de origen, obtiene los datos y los acumula en un array.
 * @param string $url_API_woo URL de WooCommerce (no se usa aquí).
 * @param string $ck_API_woo Consumer Key (no se usa aquí).
 * @param string $cs_API_woo Consumer Secret (no se usa aquí).
 * @param int $page_number Número de página a consultar.
 * @param int $total_pages Número total de páginas.
 * @param array $productos_acumulados Array pasado por referencia para acumular los resultados.
 */
function call_api($url_API_woo, $ck_API_woo, $cs_API_woo, $page_number, $total_pages, &$productos_acumulados)
{
    status_message("\n--- PAGINA $page_number DE $total_pages ---");

    // ====================================================================================
    // Conexión API origen (CDO Promocionales)
    // Se asegura que page_size=100 según el requerimiento.
    // ====================================================================================

    try {
        $url_API = "http://api.chile.cdopromocionales.com/v2/products?auth_token=2HPTRKTWv1UAD2cXekjDWQ&page_size=" . PAGE_SIZE . "&page_number=" . $page_number;

        $ch = curl_init();
        if ($ch === false) {
            throw new Exception("Error al inicializar cURL.");
        }

        curl_setopt($ch, CURLOPT_URL, $url_API);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8400); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); 

        print("✔  HORA INICIO LECTURA API (Pág $page_number): " . date("H:i:s") . " \n");

        $items_origin = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($items_origin === false) {
            throw new Exception("Error en la petición cURL: " . $curl_error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("Error HTTP $http_code en la API de origen.");
        }

        print("✔  HORA FIN LECTURA API (Pág $page_number): " . date("H:i:s") . " \n");

        // Decodificar la respuesta JSON
        $data_origin = json_decode($items_origin, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
        }

        // Obtener la lista de productos
        $items_origin = $data_origin['products'] ?? [];
        $cantidad_de_productos_api = count($items_origin);

        print("\n");
        status_message('Cantidad Productos Encontrados (Pág ' . $page_number . '): ' . $cantidad_de_productos_api);
        print("\n");

        // 2. Guardar el valor en el array acumulador
        $productos_acumulados[] = [
            'page' => $page_number,
            'page_size' => PAGE_SIZE, // Añadimos el tamaño de página para referencia
            'cantidad_de_productos_api' => $cantidad_de_productos_api,
            'productos' => $items_origin
        ];

    } catch (Exception $e) {
        // Manejo de errores de conexión o procesamiento
        status_message('❗ ERROR en call_api para página ' . $page_number . ': ' . $e->getMessage());
    }
}

?>