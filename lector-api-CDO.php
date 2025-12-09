<?php

set_time_limit(0);

require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

// Nombre del archivo de salida JSON/JS
// 🚨 IMPORTANTE: Definir la ruta absoluta es CRUCIAL en WordPress.
// Asumiremos que quieres guardarlo en el mismo directorio que este script.
// Si no funciona, DEBES usar una ruta más específica (ver punto 1 abajo).
const DATA_FILE = __DIR__ . '/lectura_api_cdo.js';
const PAGE_SIZE = 100;

// Array acumulador general para almacenar todos los productos de las páginas
$productos_acumulados = [];

// ... (Resto de variables de conexión) ...
$url_API_woo = 'https://enmasapromo.cl';
$ck_API_woo = 'ck_728d2d4d51821dd4b92638321287163829053'; 
$cs_API_woo = 'cs_a676d53d286fa3ba81ed6dde7ffadde6e8b29053'; 
$total_pages = 5; 


// ====================================================================================
// 1. Verificar y preparar la cabecera del archivo JS - FORZANDO ESCRITURA
// ====================================================================================

status_message('Iniciando proceso de lectura de API. Archivo de destino: ' . DATA_FILE);

try {
    $js_variable_declaration = "const CDO_PRODUCTS_DATA = [];\n\n";

    // Intentar escribir el contenido inicial.
    if (file_put_contents(DATA_FILE, $js_variable_declaration, LOCK_EX) === false) {
        
        // 🚨 SI FALLA LA ESCRITURA INICIAL, intentamos establecer permisos 🚨
        
        // 1. Intentar cambiar permisos del archivo (si ya existe pero no es escribible)
        if (file_exists(DATA_FILE)) {
             @chmod(DATA_FILE, 0666); // Intentar permisos de lectura/escritura para todos
             // Reintentar la escritura
             if (file_put_contents(DATA_FILE, $js_variable_declaration, LOCK_EX) !== false) {
                status_message('✅ ÉXITO: Archivo ya existía, permisos forzados y escritura completada.', true);
                goto end_initialization; // Saltar al resto del código si tiene éxito
             }
        }
        
        // 2. Si todavía falla o no existe, intentar cambiar permisos del directorio
        $dir_path = dirname(DATA_FILE);
        @chmod($dir_path, 0777); // Intentar permisos totales en el directorio
        
        // 3. Reintentar la creación después de cambiar permisos del directorio
        if (file_put_contents(DATA_FILE, $js_variable_declaration, LOCK_EX) === false) {
            // Si todo falla, lanzar la excepción final
            throw new Exception("Fallo en la escritura. Verifique permisos (0777/0666) o la ruta de archivo.");
        }
    }
    
    // Si llegamos aquí sin excepción, la escritura fue exitosa.
    status_message('✅ ÉXITO: El archivo ' . DATA_FILE . ' ha sido creado/inicializado correctamente.', true);

    end_initialization: // Etiqueta para el goto (salto)

} catch (Exception $e) {
    status_message('❌ ERROR CRÍTICO: No se pudo crear/inicializar el archivo ' . DATA_FILE . '. ' . $e->getMessage(), true, true);
    exit(1);
}

// ------------------------------------------------------------------------------------

// Recorrer las páginas de la API
for ($page_number = 1; $page_number <= $total_pages; $page_number++) {
    call_api($url_API_woo, $ck_API_woo, $cs_API_woo, $page_number, $total_pages, $productos_acumulados);
}

// 3. Guardar todo el contenido acumulado en el archivo JS al finalizar
try {
    $json_productos = json_encode($productos_acumulados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    // $js_content = "const CDO_PRODUCTS_DATA = " . $json_productos . ";\n";
    $js_content =  $json_productos . ";\n";
    
    if (file_put_contents(DATA_FILE, $js_content, LOCK_EX) === false) {
        throw new Exception("Fallo al escribir los datos finales en el archivo.");
    }
    status_message("\n✅ PROCESO FINALIZADO: Los datos de " . count($productos_acumulados) . " páginas han sido guardados en " . DATA_FILE . ".", true);
} catch (Exception $e) {
    status_message('❌ ERROR AL ESCRIBIR LOS DATOS FINALES: ' . $e->getMessage(), true, true);
}

// ... (El resto de las funciones call_api y status_message permanecen igual) ...

/**
 * Función auxiliar para imprimir mensajes de estado.
 */
function status_message($message, $important = false, $error = false)
{
    $prefix = $error ? "[ERROR] " : ($important ? ">> " : "   ");
    print($prefix . $message . " \n");
}


/**
 * Llama a la API de origen, obtiene los datos y los acumula en un array SOLO si la cantidad
 * de productos es <= PAGE_SIZE (100).
 */
function call_api($url_API_woo, $ck_API_woo, $cs_API_woo, $page_number, $total_pages, &$productos_acumulados)
{
    status_message("\n--- PAGINA $page_number DE $total_pages ---");

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

        print("   HORA INICIO LECTURA API (Pág $page_number): " . date("H:i:s") . " \n");

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

        print("   HORA FIN LECTURA API (Pág $page_number): " . date("H:i:s") . " \n");

        // Decodificar la respuesta JSON
        $data_origin = json_decode($items_origin, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
        }

        $items_origin = $data_origin['products'] ?? [];
        $cantidad_de_productos_api = count($items_origin);

        status_message('   Cantidad Productos Encontrados (Pág ' . $page_number . '): ' . $cantidad_de_productos_api);
        
        // CONDICIONAL DE 100 PRODUCTOS
        if ($cantidad_de_productos_api <= PAGE_SIZE) {
            
            $productos_acumulados[] = [
                'page' => $page_number,
                'page_size' => PAGE_SIZE, 
                'cantidad_de_productos_api' => $cantidad_de_productos_api,
                'productos' => $items_origin
            ];
            status_message('   Datos de la página ' . $page_number . ' AÑADIDOS al acumulador.');
        } else {
            status_message('   ❗ ADVERTENCIA: La página ' . $page_number . ' superó el límite de ' . PAGE_SIZE . ' productos (' . $cantidad_de_productos_api . '). NO se guardó.');
        }

    } catch (Exception $e) {
        status_message('   ❗ ERROR en call_api para página ' . $page_number . ': ' . $e->getMessage());
    }
}
?>