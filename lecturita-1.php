<?php

const DATA_FILE = __DIR__ . '/lectura_api_cdo.js';

/**
 * Función auxiliar para imprimir mensajes de estado.
 */
function status_message($message, $error = false)
{
    $prefix = $error ? "[ERROR] " : ">> ";
    echo $prefix . $message . " \n";
}

// ============================================================================
// 1. Leer el archivo
// ============================================================================

status_message('Iniciando lectura del archivo: ' . DATA_FILE);

if (!file_exists(DATA_FILE)) {
    status_message('El archivo ' . DATA_FILE . ' no existe.', true);
    exit(1);
}

// Intentar leer el contenido completo del archivo
$js_content = file_get_contents(DATA_FILE);

if ($js_content === false) {
    status_message('No se pudo leer el archivo ' . DATA_FILE . '. Verifique permisos.', true);
    exit(1);
}

status_message('✅ Lectura del archivo completada.');

// ============================================================================
// 2. Decodificar el contenido JSON
// ============================================================================

// Quitar el punto y coma final (si existe) para asegurar un JSON válido
$json_string = rtrim(trim($js_content), ';');

// Decodificar el JSON a un array asociativo de PHP (TRUE en el segundo parámetro)
$datos_cdo = json_decode($json_string, true);

if ($datos_cdo === null && json_last_error() !== JSON_ERROR_NONE) {
    status_message('❌ ERROR: Fallo al decodificar JSON. Error: ' . json_last_error_msg(), true);
    exit(1);
}

// Verificar si la decodificación resultó en un array (como se espera de la estructura anterior)
if (!is_array($datos_cdo)) {
    status_message('❌ ERROR: El contenido decodificado no es un array.', true);
    exit(1);
}

status_message('✅ JSON decodificado correctamente. Total de páginas encontradas: ' . count($datos_cdo));

// ============================================================================
// 3. Recorrer el contenido y acceder a los datos
// ============================================================================

status_message("\n--- Iniciando recorrido de datos por página ---");

$page_count = 0;

// Recorrer el array principal. Cada elemento ($page_data) es un objeto de página.
foreach ($datos_cdo as $page_data) {
    $page_count++;
    
    // Acceder a las claves esperadas de cada objeto de página
    $page_number = $page_data['page'] ?? 'N/A';
    $product_count = $page_data['cantidad_de_productos_api'] ?? 0;
    $productos = $page_data['productos'] ?? [];

    echo "--------------------------------------------------------\n";
    echo ">> Página: **$page_number** (Elemento #$page_count)\n";
    echo ">> Productos encontrados: **$product_count**\n";
    echo "--------------------------------------------------------\n";

    // Si quieres recorrer los productos dentro de cada página:
    if (!empty($productos)) {
        echo "   Detalles de Productos:\n";
        
        foreach ($productos as $producto) {
            $product_id = $producto['id'] ?? 'ID Desconocido';
            $product_name = $producto['name'] ?? 'Nombre Desconocido';
            
            // Aquí puedes procesar cada producto individualmente
            echo "   - ID: $product_id, Nombre: $product_name\n";
        }
    } else {
        echo "   (No hay productos listados para esta página.)\n";
    }
}

status_message("\n✅ Recorrido completado. Se procesaron $page_count elementos.");
?>