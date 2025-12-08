<?php

set_time_limit(0);


require __DIR__ . '/vendor/autoload.php';

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;


// ====================================================================================
// Conexión API destino
// ====================================================================================


/* NUEVAS CLAVES - HOY: 11 NOV 2025 - HORA SERVER: 14:10 */

$url_API_woo = 'https://enmasapromo.cl';
$ck_API_woo = 'ck_728d2d4d51821dd4b926385df43baa5c0c3ea7a7';
$cs_API_woo = 'cs_a676d53d286fa3ba81ed6dde7ffadde6e8b29053';


/*
$url_API_woo = 'http://localhost/wp_local_tester';
$ck_API_woo = 'ck_90b66dd999ced43a7e2f883c6685354ca81ea258';
$cs_API_woo = 'cs_9655ae275a84b706c15ca7520988a422c96fe4d0';
*/

$db_host = 'localhost';
$db_user = 'u253824733_lvOjK'; // Usuario de la base de datos
$db_pass = 'adkwKNI9St'; // Contraseña de la base de datos
$db_name = 'u253824733_xCNYA'; // Nombre de la base de datos

$nombre_atributo = 'Color CDO';
$cod_proveedor = 'CDO';


$total_pages = 1;

for ($page_number = 1; $page_number <= $total_pages; $page_number++) {
    call_api($url_API_woo, $ck_API_woo,$cs_API_woo, $page_number, $total_pages);
}

function call_api($url_API_woo, $ck_API_woo,$cs_API_woo, $page_number, $total_pages)
{

	$woocommerce = new Client(
    $url_API_woo,
    $ck_API_woo,
    $cs_API_woo,
    [
        'wp_api' => true,
        'version' => 'wc/v3',
        'timeout' => 8400000, // 2 horas 20 minutos en milisegundos
        'query_string_auth' => true,
        'verify_ssl' => true, // Verificar SSL en producción
    ]
    );
    
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

    $increment_value = "1";
    

    $increment_value = buscar_incremento();
    if (!$increment_value) {
        exit('❗Error: No se pudo obtener el valor de "increment_value_cdoprom" desde la base de datos.');
    }
        
    
    try {
    
        $cantidad_product = 1;
    
        foreach($items_origin as $product)
        {
            $sku = 'CDO-'.$product['code'];
            $array_variantes = $product['variants'];
            $cantidad_variaciones = count($array_variantes);
    
            //$nombre_atributo = 'color_cdo_prom-'. date("h:i:sa");
            // $nombre_atributo = 'Color CDO';
            
            global $nombre_atributo;


            status_message( 'NOMBRE ATRIBUTO: '. $nombre_atributo);
            print("\n");
    
    
            // PASO 1: VERIFICAR SI EXISTE EL PRODUCTO POR SU SKU
    
            status_message( 'Verificar si existe producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
            print("\n");
    
            $hora_inicio_proceso = date("h:i:sa");
    
            // $objeto_producto = verificar_producto_por_sku($woocommerce, $sku, $product);
            $objeto_producto = verificar_producto_por_sku($woocommerce, $sku);
    
            if (!$objeto_producto) {
    
                status_message( 'NO EXISTE producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
                print("\n");
    
                status_message( 'CREAR producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
                print("\n");
    
                // NO EXISTE...CREAR PRODUCTO PADRE...
    
               $producto_padre_id = crear_producto_padre($woocommerce, $product, $cantidad_variaciones, $increment_value);
    
                    // VERIFICAR ATRIBUTO:
               
                    $verificacion_atributo = verificar_atributo_por_nombre($woocommerce, $nombre_atributo);
    
                    if (!$verificacion_atributo) {
    
                        status_message( 'NO EXISTE EL ATRIBUTO HAY QUE CREARLO a las: '. date("h:i:sa"));
                        print("\n");
    
                        $data_atributo = crear_atributo($woocommerce, $nombre_atributo);
                        $data_atributo_id = $data_atributo->id;
    
                    }
                    else 
                    {
    
                        $verificacion_atributo = reset($verificacion_atributo);
                        $data_atributo_id = $verificacion_atributo->id;
    
    
                        status_message( 'YA EXISTE EL ATRIBUTO y su id es: ' .$data_atributo_id. ' a las ' .date("h:i:sa"));
                        print("\n");
    
                    }
    
                    // VA A CREACION DE VARIACIONES 
    
                    // crear_variaciones_del_producto($woocommerce, $product, $producto_padre_id->id, $cantidad_variaciones, $data_atributo_id, $nombre_atributo, $increment_value);

                    crear_variaciones_del_producto($woocommerce, $product, $producto_padre_id->id, $cantidad_variaciones, $data_atributo_id, $nombre_atributo, $increment_value, $sku, $page_number);
    
    
            }
            else
            {
                status_message( 'EXISTE producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
                print("\n");
    
                actualizar_variaciones_del_producto($woocommerce, $product, $objeto_producto->id, $cantidad_variaciones, $increment_value, $sku, $page_number);
    
            }
    
    
            print("\n");
    
            $hora_fin_proceso = date("h:i:sa");
    
            $diferencial_time_proceso = calcularDiferenciaDeTiempo($hora_inicio_proceso, $hora_fin_proceso, $cantidad_variaciones);
    
            status_message( '***** PAGINA: ' . $page_number . ' de ' . $total_pages );
            status_message( '***** PRODUCTO: ' . $cantidad_product . ' de ' . $cantidad_de_productos_api );
            status_message( '***** TIMER: ' . $diferencial_time_proceso );
            print("\n");
    
            unset($listado_colores);
            unset($atributos_colores);
            unset($variaciones_producto);
            
            $cantidad_product++;
    
        }
    
    } 
    catch ( HttpClientException $e ) 
    {
        echo $e->getMessage(); // Error message
    }
    

}


// ************ FUNCION VERIFICAR SI PRODUCTO PADRE EXISTE

function verificar_producto_por_sku($woocommerce, $sku) {

    $products = $woocommerce->get('products', ['sku' => $sku]);
    return !empty($products) ? $products[0] : null;
}

// ************ FUNCION CREAR PRODUCTO PADRE

function crear_producto_padre($woocommerce, $product, $cantidad_variaciones, $increment_value) {
   
status_message('CREANDO PRODUCTO PADRE');
print("\n");

    $precio_original = $product['variants'][0]['list_price'];
    $precio_final = $precio_original * $increment_value; 

    $data = [

        'sku' => 'CDO-'.$product['code'],
        'name' => $product['name'],
        'type' => 'variable',
        'regular_price' => number_format($precio_final, 2, '.', ''),
        'description' => $product['description'],
        'short_description' => $product['description'],
        'status' => 'publish',
        'manage_stock' => true,
        'stock_quantity' => 1,
        'images' => [
            [
                'src' => $product['variants'][0]['picture']['original']
            ]
        ]

    ];
    
    $wc_product = $woocommerce->post('products', $data);
  
    status_message( 'Se crea PRODUCTO PADRE (VARIABLE) con el SKU: '.$product['code'].' que contiene '.$cantidad_variaciones.' variaciones a las: '. date("h:i:sa"));
    print("\n");

    return $wc_product;
}




/**
 * Helper: devuelve el nombre del color de una variación de forma segura.
 *
 * @param array $variacion
 * @return string|null
 */
function getColorName(array $variacion) {
    // Caso 1: estructura ['color' => ['name' => '...']]
    if (isset($variacion['color']) && is_array($variacion['color']) && isset($variacion['color']['name'])) {
        return $variacion['color']['name'];
    }

    // Caso 2: estructura ['colors' => [ ['name' => '...'], ... ]]
    if (isset($variacion['colors']) && is_array($variacion['colors']) && count($variacion['colors']) > 0) {
        // usamos el índice 0 por seguridad (antes usabas [1] que puede no existir)
        if (isset($variacion['colors'][0]['name'])) {
            return $variacion['colors'][0]['name'];
        }
    }

    // Caso 3: quizá venga directo como 'color_name' u otra clave
    if (isset($variacion['color_name'])) {
        return $variacion['color_name'];
    }

    return null;
}











// ************ FUNCION CREAR VARIACIONES DEL PROD PADRE

function crear_variaciones_del_producto($woocommerce, $product, $producto_padre_id, $cantidad_variaciones, $data_atributo_id, $nombre_atributo, $increment_value, $sku_padre, $page_api) {

   
   global $cod_proveedor;

    $listado_colores = [];
    $batch_variaciones = [];
    $listado_colores_pp = [];

    // Detectar si $product es un producto completo (con 'variants') o una variación individual
    $is_full_product = isset($product['variants']) && is_array($product['variants']) && count($product['variants']) > 0;

    // Normalizar el array de variaciones para iterar
    $variants = $is_full_product ? $product['variants'] : [$product];

    // Prints de depuración seguros (evitar acceso a índices inexistentes)
    status_message('*** tomate - SKU (producto): ' . (isset($product['sku']) ? $product['sku'] : $sku_padre));
    if ($is_full_product) {
        status_message('*** Producto trae ' . count($variants) . ' variaciones');
    } else {
        status_message('*** Se creó/actualiza 1 variación individual');
    }

    // -------- Recorre para buscar todos los colores de las variaciones (sólo si vinieron varias)
    status_message('    ...INICIA CICLO PARA...Buscar colores de variacion en variaciones a las: ' . date("h:i:sa"));

    foreach ($variants as $var_color) {
        status_message('    ...ESTA DENTRO DE CICLO PARA...Buscar colores de variacion en variaciones a las: ' . date("h:i:sa"));

        $color_name = getColorName($var_color);
        if ($color_name !== null) {
            $listado_colores_pp[] = $color_name;
        }
    }

    status_message('FINALIZA CICLO PARA...Buscar colores de variacion en variaciones a las: ' . date("h:i:sa"));
    print("\n");

    status_message('CONTEO VARIACIONES a las: ' . date("h:i:sa") . ' ES : ' . count($variants));
    print("\n");

    // Si es producto completo, actualizamos atributos del producto padre con la lista de opciones encontradas
    if ($is_full_product) {
        // eliminar duplicados y reindexar
        $listado_colores_pp = array_values(array_unique($listado_colores_pp));

        $atributos_producto[] = [
            'id' => $data_atributo_id,
            'name' => $nombre_atributo,
            'options' => $listado_colores_pp,
            'variation' => true,
            'visible' => true,
        ];

        // Actualizar atributos en el producto padre
        $woocommerce->put("products/{$producto_padre_id}", ['attributes' => $atributos_producto]);
    }

    status_message('INICIA CICLO PARA...Buscar llenar array con todas las ' . count($variants) . ' variaciones a las: ' . date("h:i:sa"));

    foreach ($variants as $variacion) {

        $nombre_term = getColorName($variacion);
        if ($nombre_term === null) {
            // fallback para evitar errores
            $nombre_term = 'Sin color';
        }

        $precio_original = isset($variacion['list_price']) ? $variacion['list_price'] : 0;
        $precio_final = $precio_original * $increment_value;

        $data = [
            'sku' => $cod_proveedor . '-VAR-' . (isset($variacion['id']) ? $variacion['id'] : uniqid()),
            'manage_stock' => true,
            'variacion_stock_api' => isset($variacion['stock_available']) ? $variacion['stock_available'] : 0,
            'variacion_stock_anterior' => 0,
            'id_variacion_api' => isset($variacion['id']) ? $variacion['id'] : null,
            'color_variacion' => $nombre_term,
            'stock_quantity' => isset($variacion['stock_available']) ? $variacion['stock_available'] : 0,
            'regular_price' => number_format($precio_final, 2, '.', ''),
            'image' => [
                'src' => isset($variacion['picture']['original']) ? $variacion['picture']['original'] : ''
            ],
            'attributes' => [
                [
                    'id' => $data_atributo_id,
                    'option' => $nombre_term,
                ],
            ],
        ];

        $batch_variaciones[] = $data;
        $listado_colores[] = $nombre_term;
    }

    status_message('FINALIZA CICLO PARA...Buscar llenar array con variaciones a las: ' . date("h:i:sa"));
    print("\n");

    status_message('INICIA CREACION DE VARIACIONES a las ' . date("h:i:sa"));

    // Se crean las variaciones via BATCH (funciona para 1 o N variaciones)
    $batch_data = ['create' => $batch_variaciones];
    $woocommerce->post("products/{$producto_padre_id}/variations/batch", $batch_data);

    status_message('CULMINA CREACION DE VARIACIONES a las ' . date("h:i:sa"));

    // CREA REGISTRO EN AUDITORIA POR CADA VARIACIÓN CREADA
    foreach ($batch_variaciones as $variacion) {

        // OBTENER ID DE VARIACIÓN EXISTENTE (después de crearla)
        $variacion_encontrada = verificar_producto_por_sku($woocommerce, $variacion['sku']);
        $variacion_id = isset($variacion_encontrada->id) ? $variacion_encontrada->id : null;
        $variacion_id = intval($variacion_id);

        $variacion_stock_api = intval($variacion['variacion_stock_api']);
        $variacion_stock_actual = intval(obtener_stock_variacion($woocommerce, $producto_padre_id, $variacion_id));

        // EVALUAR DIFERENCIAL EN STOCK
        $status_auditoria = 'CREADA';

        crear_auditoria_1($producto_padre_id, $sku_padre, $variacion['id_variacion_api'], $status_auditoria, $cod_proveedor, $page_api, 0, $variacion_stock_api, $variacion_stock_actual, $variacion['sku']);
    }

    // limpieza
    unset($listado_colores);
    unset($batch_variaciones);
    unset($batch_data);

    return "ok";
   
   
   
   
   
   
   
}


/**
 * Actualiza las variaciones de un producto de WooCommerce usando la API REST.
 *
 * @param object $woocommerce Cliente de la API de WooCommerce.
 * @param array $product Datos del producto con sus variaciones (del proveedor).
 * @param int $producto_padre_id ID del producto principal en WooCommerce.
 * @param int $cantidad_variaciones Número total de variaciones (parámetro no usado, se puede eliminar).
 * @param float $increment_value Factor de incremento para el precio.
 * @param string $sku_padre SKU del producto padre.
 * @param int $page_api Número de página de la API (para la auditoría).
 * @return string Mensaje de estado.
 */
function actualizar_variaciones_del_producto($woocommerce, $product, $producto_padre_id, $cantidad_variaciones, $increment_value, $sku_padre, $page_api) {


    global $nombre_atributo;
    global $cod_proveedor;

    $variaciones_del_producto_padre = [];
    $variaciones_desde_api = [];
    $variaciones_incompatibles = [];
    
    $batch_variaciones = [];
    
    $contar_vari = 1; // Contador de variaciones procesadas (actualmente no usado en la lógica clave)
    
    // El listado_colores no se usa en el código proporcionado, se mantiene para consistencia, pero es innecesario.
    $listado_colores = []; 

    // --- 1. ACTUALIZAR FECHA DE MODIFICACIÓN DEL PRODUCTO PADRE ---
    
    $new_modified_date = date('Y-m-d H:i:s');
    
    print("\n");
    status_message('NUEVA HORA DE SINCRONIZACIÓN DEL PRODUCTO PADRE: ' . $new_modified_date);
    print("\n");

    $data_fecha = [
        'date_modified' => $new_modified_date,
        'date_modified_gmt' => gmdate('Y-m-d H:i:s'), // Fecha GMT
    ];

    // Se usa $data_fecha para evitar sobrescribir $data de las variaciones.
    $woocommerce->put('products/' . $producto_padre_id, $data_fecha); 

    // ----------------------------------------------------------------------------------------------------------//

    // --- 2. ARMAR ARRAY DE VARIACIONES DESDE API REST
    
    foreach ($product['variants'] as $id_de_api_rest) {
        $variaciones_desde_api[] = $cod_proveedor . '-VAR-' . $id_de_api_rest['id'];
    }

    // --- 3. ARMAR ARRAY DE VARIACIONES EXISTENTES EN TIENDA QUE PASARAN A DRAFT

    $endpoint_variaciones_local = "products/{$producto_padre_id}/variations";
    $variations_result = $woocommerce->get($endpoint_variaciones_local);
    $variations_array = (array) $variations_result;

    if (!empty($variations_array)) {

        foreach ($variations_array as $variation) {
           $variaciones_del_producto_padre[] = $variation->sku;
        }
        
    } else {
        echo "No se encontraron variaciones para el Producto ID **{$producto_padre_id}**.\n";
    }


    // --- 4. ENVIAR A FUNCION COMPARATIVA DE ARRAYS (LOCAL VS. API REST) ---

        comparar_skus_local_vs_api_rest($woocommerce, $variaciones_del_producto_padre, $variaciones_desde_api, 0, $cod_proveedor, $producto_padre_id, $sku_padre, $page_api, 0, 0);

    
    // --- 2. PROCESAR Y CONSTRUIR EL LOTE (BATCH) DE VARIACIONES ---
    
    foreach ($product['variants'] as $index => $variacion) {
        
        $precio_original = $variacion['list_price'];
        $color_variacion = $variacion['color']['name'];
        $precio_final = $precio_original * $increment_value; 
        $sku_variacion = $cod_proveedor . '-VAR-' . $variacion['id'];
        $id_variacion_api = $variacion['id'];

        // OBTENER ID DE VARIACIÓN EXISTENTE
        $variacion_encontrada = verificar_producto_por_sku($woocommerce, $sku_variacion);
        $variacion_id = isset($variacion_encontrada->id) ? $variacion_encontrada->id : null;
        $variacion_id = intval($variacion_id);

        if (!empty($variacion_id)) {




            $variacion_stock_api = intval($variacion['stock_available']);
            $variacion_stock_anterior = obtener_stock_variacion($woocommerce, $producto_padre_id, $variacion_id);

            
            $data = [
                'id' => $variacion_id,
                'manage_stock' => true,
                'stock_quantity' => $variacion_stock_api,
                'regular_price' => number_format($precio_final, 2, '.', ''),
                'variacion_stock_api' => $variacion_stock_api,
                'variacion_stock_anterior' => $variacion_stock_anterior,
                'color_variacion' => $color_variacion,
                'id_variacion_api' => $id_variacion_api,
                'sku_variacion_tienda' => $sku_variacion,

            ];

            $batch_variaciones[] = $data;

            

        } else {

            // ***********************************************************************************************************************************************

            // SI LA VARIACIÓN NO EXISTE, CON LOS DATOS QUUE VIENEN DE LA API REST, SE DEBE REGISTRAR INICIDENCIA
            // Y CREAR LA VARIACIÓN

            print("\n");
            status_message(' NO COINCIDE (Variación no encontrada): ' . $sku_variacion);
            print("\n");

            print("\n");
            status_message(' y su index es: ' . $index);
            print("\n");


            // VERIFICAR ATRIBUTO:
        
            $verificacion_atributo = verificar_atributo_por_nombre($woocommerce, $nombre_atributo);

            if (!$verificacion_atributo) {

                $data_atributo = crear_atributo($woocommerce, $nombre_atributo);
                $data_atributo_id = $data_atributo->id;

            }
            else 
            {

                $verificacion_atributo = reset($verificacion_atributo);
                $data_atributo_id = $verificacion_atributo->id;


            }
            


            /*
            
            $variacion_por_indice = $product['variants'][$index];

            $output_array = [
                'variants' => [
                    $index => $variacion_por_indice
                ]
            ];
            
            */
            

            $variacion_por_indice = $product['variants'][$index];


 
            // print_r($variacion_por_indice);

            crear_variaciones_del_producto($woocommerce, $variacion_por_indice, $producto_padre_id, 1, $data_atributo_id, $nombre_atributo, $increment_value, $sku_padre, $page_api);



        }

        $contar_vari++;
    }


    // --- 3. EJECUTAR LA ACTUALIZACIÓN EN LOTE (BATCH) ---

    // La condición ha sido corregida: verificamos si el array $batch_variaciones tiene elementos.
    if (!empty($batch_variaciones)) {

        status_message('INICIA ACTUALIZACIÓN DE VARIACIONES a las ' . date("h:i:sa"));

        $batch_data = ['update' => $batch_variaciones];
        
        // Ejecutar el batch update
        $respuesta_batch = $woocommerce->post("products/{$producto_padre_id}/variations/batch", $batch_data);

        status_message('FINALIZA ACTUALIZACIÓN DE VARIACIONES a las ' . date("h:i:sa"));

        // CREA REGISTRO EN AUDITORIA POR CADA VARIACIÓN ACTUALIZADA

        foreach ($batch_variaciones as $variacion) {

            // $status_auditoria = 'ACTUALIZADA';

            $variacion_stock_api = intval($variacion['variacion_stock_api']);
            $variacion_stock_actual = intval(obtener_stock_variacion($woocommerce, $producto_padre_id, $variacion['id']));

            // EVALUAR DIFERENCIAL EN STOCK 

            if ($variacion_stock_api == $variacion_stock_actual) {
                $status_auditoria = 'ACTUALIZADA';
            }
            if ($variacion_stock_api != $variacion_stock_actual) {
                $status_auditoria = 'DIFERENCIAL EN STOCK';
            }
            
            crear_auditoria_1($producto_padre_id, $sku_padre, $variacion['id_variacion_api'], $status_auditoria, $cod_proveedor, $page_api, $variacion['variacion_stock_anterior'], $variacion_stock_api, $variacion_stock_actual, $variacion['sku_variacion_tienda']);
        }

    } else {
        // Ninguna variación encontrada para actualizar
        print("\n");
        status_message('AVISO: No se encontraron variaciones para actualizar en el producto padre ID: ' . $producto_padre_id);
        print("\n");
    }

    // --- 4. LIMPIEZA DE MEMORIA ---
    
    // unset() es bueno para liberar memoria de grandes arrays.
    unset($listado_colores); 
    unset($batch_variaciones);

    return "Actualización de variaciones completada para el producto: {$producto_padre_id}";
}


// ************ FUNCION OBTENER STOCK DE VARIACION

/**
 * Obtiene la cantidad de stock actual de una variación de producto específica
 * usando la API REST de WooCommerce.
 *
 * @param object $woocommerce La instancia de la librería de la API de WooCommerce.
 * @param int $producto_padre_id El ID del producto principal (padre).
 * @param int $variacion_id El ID de la variación específica.
 * @return int|null La cantidad de stock actual de la variación, o null si falla.
 */
function obtener_stock_variacion($woocommerce, $producto_padre_id, $variacion_id) {
    
    // Validar que los IDs sean numéricos y que la instancia de WooCommerce sea válida
    if (!is_numeric($producto_padre_id) || !is_numeric($variacion_id) || !is_object($woocommerce)) {
        // En un entorno de producción, puedes manejar este error de forma más elegante
        // o lanzar una excepción.
        // echo "Error: Parámetros de entrada inválidos.";
        return null;
    }
    
    try {
        // 1. Construir el endpoint de la API
        $endpoint = "products/{$producto_padre_id}/variations/{$variacion_id}";
        
        // 2. Realizar la solicitud GET a la API de WooCommerce
        $variation_data = $woocommerce->get($endpoint);
        
        // 3. Verificar si se obtuvieron los datos y si la propiedad existe
        if (isset($variation_data->stock_quantity)) {
            // Devolver la cantidad de stock
            return (int) $variation_data->stock_quantity;
        } else {
            // La API respondió, pero no contiene la información de stock esperada
            // echo "Advertencia: No se encontró la propiedad 'stock_quantity' en la respuesta.";
            return null;
        }
        
    } catch (\Exception $e) {
        // Manejo de errores de conexión o de la API
        // echo "Error al obtener datos de la API: " . $e->getMessage();
        return null;
    }
}

// --------------------------------------------------------------------------------------
// Ejemplo de Uso (simulado):
// --------------------------------------------------------------------------------------

/*
// Necesitarías inicializar la clase $woocommerce con tus credenciales
// $woocommerce = new Client(
//     'https://tudominio.com', 
//     'ck_...', 
//     'cs_...',
//     [ 'wp_api' => true, 'version' => 'wc/v3' ]
// );

// $id_padre = 50;
// $id_variacion = 51;

// $stock_actual = obtener_stock_variacion($woocommerce, $id_padre, $id_variacion);

// if ($stock_actual !== null) {
//     echo "El stock actual de la variación {$id_variacion} es: {$stock_actual}";
// } else {
//     echo "No se pudo obtener el stock de la variación.";
// }
*/








// ************ PASAR VARIACION A BORRADOR 


function eliminar_variacion($woocommerce,$target_sku) {


try {
    // 1. Buscar el producto/variación por SKU
    // El endpoint /products permite filtrar por 'sku'. Esto trae tanto productos simples 
    // como las variaciones (que aparecen como productos hijos en los resultados).
    
    $params = ['sku' => $target_sku];
    $search_result = $woocommerce->get('products', $params);


    if (!empty($search_result)) {
        
            // La API devuelve un array de productos/variaciones coincidentes.
            $found_item = $search_result[0]; // Tomamos el primer resultado

            // 2. Determinar si es una variación y obtener sus IDs
            if ($found_item->parent_id !== 0) {
                // Si parent_id no es 0, es una variación (producto hijo)
                $variation_id = $found_item->id;
                $parent_product_id = $found_item->parent_id;
                
                // 2. Definir el endpoint de la variación para la solicitud DELETE

            $endpoint = "products/{$parent_product_id}/variations/{$variation_id}";


            // 3. Realizar la solicitud DELETE a la API
            // El argumento ['force' => true] asegura la eliminación permanente (no solo a la papelera, 
            // aunque las variaciones no suelen ir a la papelera por defecto).

            $resultado = $woocommerce->delete($endpoint, ['force' => true]);

            if (isset($resultado->id) && $resultado->id == $variation_id) {
                    return "✅ La variación ID {$variation_id} del producto {$parent_product_id} ha sido eliminada correctamente.";
            } else {
                    // Esto puede ocurrir si el producto ya estaba eliminado o hubo un error inesperado
                    return "⚠️ La variación ID {$variation_id} no pudo ser eliminada o no se encontró.";
            }


        } else {
            // Es un producto simple, no una variación.
            echo "❌ El SKU **{$target_sku}** corresponde a un producto simple, no a una variación. No se puede actualizar el estado de esta forma.";
        }
        
    } else {
        echo "## ❌ Error\n";
        echo "No se encontró ningún producto o variación con el SKU **{$target_sku}**.";
    }

} catch (Exception $e) {
    echo "## ❌ Error en la API de WooCommerce\n";
    echo "Error al procesar la solicitud: **" . $e->getMessage() . "**";
}

}


// ************ FUNCION EXTRAER TEXTO A LA DERECHA DE UN DELIMITADOR

/**
 * Extrae la subcadena que se encuentra inmediatamente a la derecha de un delimitador específico.
 *
 * @param string $texto_completo La cadena de donde se va a extraer.
 * @param string $delimitador La subcadena que define el punto de corte (ej: "CDO-VAR-").
 * @return string|false La cadena a la derecha del delimitador, o false si el delimitador no se encuentra.
 */
function extraer_derecha_de_delimitador($texto_completo, $delimitador) {
    
    // 1. Encontrar la posición de inicio del delimitador dentro del texto completo.
    // strpos() devuelve la posición de la primera ocurrencia.
    $posicion_delimitador = strpos($texto_completo, $delimitador);
    
    // 2. Verificar si el delimitador fue encontrado (strpos devuelve false si no lo encuentra).
    if ($posicion_delimitador === false) {
        return false; // El delimitador no existe en el texto.
    }
    
    // 3. Calcular la posición donde debe empezar la extracción.
    // Se suma la posición donde empieza el delimitador + la longitud del delimitador.
    $posicion_inicio = $posicion_delimitador + strlen($delimitador);
    
    // 4. Extraer la subcadena.
    // substr() toma la cadena original y empieza a extraer desde $posicion_inicio
    // hasta el final de la cadena.
    $resultado = substr($texto_completo, $posicion_inicio);
    
    return $resultado;
}



// ************ COMPARAR SKUS 

function comparar_skus_local_vs_api_rest($woocommerce, $variaciones_del_producto_padre, $variaciones_desde_api, $status_auditoria_1, $cod_proveedor, $producto_padre_id, $sku_padre, $page_api, $variacion_stock_anterior, $variacion_stock_actual) {

    $array_skus_incompatibles = array_diff($variaciones_del_producto_padre, $variaciones_desde_api);

    // 3. Lógica Condicional (Si encuentra diferencias, haz algo)
    if (!empty($array_skus_incompatibles)) {
        // --- BLOQUE DE CÓDIGO A EJECUTAR SI HAY DIFERENCIAS ---
        
        echo "\n\n✅ **¡DIFERENCIAS ENCONTRADAS!**\n";
        echo "Se deben procesar " . count($array_skus_incompatibles) . " elementos:\n";
        
        // Aquí iría el ciclo para insertar en MySQL, o el código de procesamiento.
        foreach ($array_skus_incompatibles as $sku_a_insertar) {
            echo "- Procesando SKU: **{$sku_a_insertar}**\n";

            // Lógica de inserción de base de datos o actualización aquí...

            $delimitador = "CDO-VAR-";

            $numero_extraido = extraer_derecha_de_delimitador($sku_a_insertar, $delimitador);

            if ($numero_extraido !== false) {
                echo "Texto original: {$sku_a_insertar}\n";
                echo "Delimitador: {$delimitador}\n";
                echo "Resultado extraído: {$numero_extraido}\n"; // Salida: 79718936
            } else {
                echo "El delimitador '{$delimitador}' no fue encontrado en el texto.";
            }


            // OBTENER ID DE VARIACIÓN EXISTENTE

            $variacion_encontrada = verificar_producto_por_sku($woocommerce, $sku_a_insertar);
            $variacion_id = isset($variacion_encontrada->id) ? $variacion_encontrada->id : null;
            $variacion_id = intval($variacion_id);


            echo "\n";
            echo "MIGBEN EL ID A ELIMINAR ES: " . $variacion_id;
            echo "\n";


            $variacion_stock_anterior = intval(obtener_stock_variacion($woocommerce, $producto_padre_id, $variacion_id));

            echo "\n";
            echo "STOCK DE ELIMINADO ES: " . $variacion_stock_anterior;
            echo "\n";

            $status_auditoria_1 = 'ELIMINADA';


            crear_auditoria_1($producto_padre_id, $sku_padre, $numero_extraido, $status_auditoria_1, $cod_proveedor, $page_api, $variacion_stock_anterior, 0, 0, $sku_a_insertar);

            // Va a funcion que ELIMINA LA VARIACION

            eliminar_variacion($woocommerce, $sku_a_insertar);



        }
        
        // ---------------------------------------------------------
    } else {
        // --- BLOQUE DE CÓDIGO A EJECUTAR SI NO HAY DIFERENCIAS (Opcional) ---
        
        echo "\n\n😴 **SIN DIFERENCIAS.**\n";
        echo "Los arrays están sincronizados. No se requiere ninguna acción.\n";
        
        // --------------------------------------------------------------------
    }



}


// ************ FUNCION BUSCAR STOCK DE UNA VARIACION

function buscar_stock_variacion($woocommerce, $variacion_id) {
    $variacion = $woocommerce->get("products/{$variacion_id}");
    return !empty($variacion) ? intval($variacion->stock_quantity) : 0;
}





// ************ FUNCION VERIFICAR SI ATRIBUTO EXISTEue me k

function verificar_atributo_por_nombre($woocommerce, $nombre_atributo) {

    // Obtener todos los atributos de productos
    $attributes = $woocommerce->get('products/attributes');

    // Filtrar los atributos por "name"
    $nameToFind = $nombre_atributo; // Cambia esto al nombre que quieres buscar
    $filteredAttributes = array_filter($attributes, function ($attribute) use ($nameToFind) {
        return isset($attribute->name) && $attribute->name === $nameToFind;
    });

    
    // Mostrar los atributos encontrados
    if (!empty($filteredAttributes)) {
        return  $filteredAttributes;

    } else {
        return null;
    }

}


// ************ FUNCION CREAR ATRIBUTO

function crear_atributo($woocommerce, $nombre_atributo) {

    status_message( 'CREANDO EL ATRIBUTO...');
    print("\n");

    $data = [
        'name' => $nombre_atributo,
        'type' => 'select', // Puedes ajustar el tipo si es necesario
        'order_by' => 'menu_order',
        'has_archives' => false,
    ];

    return $woocommerce->post('products/attributes', $data);
}


// ************ FUNCION VERIFICAR SI TERMINO EXISTE

function verificar_termino($woocommerce, $atributo_id, $nombre_termino) {

    status_message( 'VERIFICANDO SI EXISTE EL TERMINO: ', $nombre_termino );
    print("\n");

    $results = $woocommerce->get("products/attributes/{$atributo_id}/terms", ['name' => $nombre_termino]);
    return $results;
    
}

// ************ FUNCION CREAR TERMINO

function crear_termino($woocommerce, $atributo_id, $nombre_termino) {
    
    status_message( 'CREANDO EL TERMINO: ', $nombre_termino );
    print("\n");
        
    $data = [
        'name' => $nombre_termino,
        'slug' => $nombre_termino,
    ];

    return $woocommerce->post("products/attributes/{$atributo_id}/terms", $data);

}

// ************ FUNCION BUSCAR PORCENTAJE DE INCREMENTO


function buscar_incremento() {

// Datos de configuración de la base de datos
/*
$db_host = 'localhost';
$db_user = 'u253824733_lvOjK'; // Usuario de la base de datos
$db_pass = 'adkwKNI9St'; // Contraseña de la base de datos
$db_name = 'u253824733_xCNYA'; // Nombre de la base de datos
*/

global $db_host, $db_user, $db_pass, $db_name;

try {
    // Conexión a la base de datos usando PDO
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para determinar el prefijo de las tablas

    $query = "SELECT table_name 
              FROM information_schema.tables 
              WHERE table_schema = :db_name 
              AND table_name LIKE '%_options' LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['db_name' => $db_name]);

    // Obtener el nombre de la tabla `options`
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['table_name'])) {
        $table_name = $result['table_name'];
        // Extraer el prefijo del nombre de la tabla
        $table_prefix = str_replace('options', '', $table_name);
        //echo "Prefijo de la tabla determinado: $table_prefix\n";
    } else {
        die("No se pudo determinar el prefijo de las tablas automáticamente.\n");
    }

    // Consulta específica para buscar el valor de increment_value_cdoprom

    $query = "SELECT option_value FROM {$table_name} WHERE option_name = 'increment_value_cdoprom'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    // Obtener el valor
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['option_value'])) {
        return $result['option_value']; // Devolver el valor encontrado
    } else {
        return false; // Retornar falso si no se encuentra el valor
    }


} catch (PDOException $e) {
    // Manejo de errores
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    return false;
}


}


// ************ FUNCION GUARDAR SKU

function crear_auditoria_1($producto_padre_id, $sku_padre, $id_variacion_api, $status_auditoria_1, $cod_proveedor, $page_api, $variacion_stock_anterior, $variacion_stock_api, $variacion_stock_actual, $sku_variacion_tienda) 
{

// Datos de configuración de la base de datos

/*

$db_host = 'localhost';
$db_user = 'u253824733_lvOjK'; // Usuario de la base de datos
$db_pass = 'adkwKNI9St'; // Contraseña de la base de datos
$db_name = 'u253824733_xCNYA'; // Nombre de la base de datos

*/

global $db_host, $db_user, $db_pass, $db_name;  


try {
    // Conexión a la base de datos usando PDO
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para determinar el prefijo de las tablas

    $query = "SELECT table_name 
              FROM information_schema.tables 
              WHERE table_schema = :db_name 
              AND table_name LIKE '%_auditoria_productos' LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['db_name' => $db_name]);

    // Obtener el nombre de la tabla `options`
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['table_name'])) {
        $table_name = $result['table_name'];
        // Extraer el prefijo del nombre de la tabla
        $table_prefix = str_replace('auditoria_productos', '', $table_name);
        //echo "Prefijo de la tabla determinado: $table_prefix\n";
    } else {
        die("No se pudo determinar el prefijo de las tablas automáticamente.\n");
    }

    
    
    // La consulta INSERT crea una nueva fila cada vez que se ejecuta.
    $sql = "INSERT INTO {$table_name} 
            (post_id, cod_proveedor, sku_padre, id_variacion, page_api, status_variacion_auditoria, variacion_stock_anterior, variacion_stock_api, variacion_stock_actual, sku_variacion_tienda) 
            VALUES (:post_id, :cod_proveedor, :sku_padre, :id_variacion, :page_api, :status_variacion_auditoria, :variacion_stock_anterior, :variacion_stock_api, :variacion_stock_actual, :sku_variacion_tienda)";

    // 5. Ejecutar la consulta preparada
        $stmt_save = $pdo->prepare($sql);
        $success = $stmt_save->execute([
            'post_id'       => $producto_padre_id, 
            'cod_proveedor'      => $cod_proveedor, 
            'sku_padre'      => $sku_padre, 
            'id_variacion' => $id_variacion_api,
            'page_api' => $page_api,
            'status_variacion_auditoria' => $status_auditoria_1,
            'variacion_stock_anterior' => $variacion_stock_anterior,
            'variacion_stock_api' => $variacion_stock_api,
            'variacion_stock_actual' => $variacion_stock_actual,
            'sku_variacion_tienda' => $sku_variacion_tienda
        ]);

        return $success;

} catch (PDOException $e) {
    // Manejo de errores
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    return false;
}


}

// ************ FUNCION GUARDAR SKU

function verificar_problema_1($producto_padre_id, $sku_padre, $sku_variacion) {

// Datos de configuración de la base de datos
/*

$db_host = 'localhost';
$db_user = 'u253824733_lvOjK'; // Usuario de la base de datos
$db_pass = 'adkwKNI9St'; // Contraseña de la base de datos
$db_name = 'u253824733_xCNYA'; // Nombre de la base de datos
*/

global $db_host, $db_user, $db_pass, $db_name;  

try {
    // Conexión a la base de datos usando PDO
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para determinar el prefijo de las tablas

    $query = "SELECT table_name 
              FROM information_schema.tables 
              WHERE table_schema = :db_name 
              AND table_name LIKE '%_postmeta' LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['db_name' => $db_name]);

    // Obtener el nombre de la tabla `options`
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['table_name'])) {
        $table_name = $result['table_name'];
        // Extraer el prefijo del nombre de la tabla
        $table_prefix = str_replace('postmeta', '', $table_name);
        //echo "Prefijo de la tabla determinado: $table_prefix\n";
    } else {
        die("No se pudo determinar el prefijo de las tablas automáticamente.\n");
    }

    // Consulta específica insertar los sku de los productos que dan problema en la actualizacion de stock 
    
    
    // 1. Definir la clave y la tabla
    $meta_key = '_sku_producto_problema_1'; 
    
    // 2. Crear el Array de Datos a Serializar
    $data_to_save = [
        'producto_padre_id' => $producto_padre_id,
        'sku_padre' => $sku_padre,
        'sku_variacion' => $sku_variacion,
        'fecha_transac' => date('Y-m-d H:i:s') // Obtiene la fecha y hora actual de PHP
    ];
    
    // 3. Serializar el Array a la cadena que necesita MySQL
    $serialized_data = serialize($data_to_save);

    $unida_data = "producto_padre_id: ". $producto_padre_id . " sku_padre: " . $sku_padre . " sku_variacion: " . $sku_variacion . " fecha-trans: " . date('Y-m-d H:i:s');
    
    // 4. Consulta SQL de INSERT (Sin verificación de existencia)
    
    // La consulta INSERT crea una nueva fila cada vez que se ejecuta.
    $sql = "INSERT INTO {$table_name} 
            (post_id, meta_key, meta_value) 
            VALUES (:post_id, :meta_key, :unida_data)";
    
    // 5. Ejecutar la consulta preparada
        $stmt_save = $pdo->prepare($sql);
        $success = $stmt_save->execute([
            'post_id'       => $producto_padre_id, 
            'meta_key'      => $meta_key, 
            'unida_data' => $unida_data
        ]);

        return $success;
        



} catch (PDOException $e) {
    // Manejo de errores
    echo "Error al conectar a la base de datos: " . $e->getMessage() . "\n";
    return false;
}


}



function calcularDiferenciaDeTiempo($hora_inicio_proceso, $hora_fin_proceso, $cantidad_variaciones) {
    // Convertir las horas a objetos DateTime
    $inicio = new DateTime($hora_inicio_proceso);
    $fin = new DateTime($hora_fin_proceso);

    // Calcular la diferencia
    $diferencia = $inicio->diff($fin);

    // Convertir la diferencia a segundos
    $diferencia_en_segundos = ($diferencia->h * 3600) + ($diferencia->i * 60) + $diferencia->s;

    // Retornar el resultado en minutos o segundos
    if ($diferencia_en_segundos >= 60) {
        $diferencia_en_minutos = round($diferencia_en_segundos / 60, 2);
        return "El producto con sus ".$cantidad_variaciones." variaciones, se creó ó actualizó en " .$diferencia_en_minutos. " minutos.";
    } else {
        return "El producto con sus ".$cantidad_variaciones." variaciones, se creó ó actualizó en " .$diferencia_en_segundos. " segundos.";
    }
}





/**
 * Parse JSON file.
 *
 * @param  string $file
 * @return array
*/

// function parse_json( $file ) {
// 	$json = json_decode( file_get_contents( $file ), true );

// 	if ( is_array( $json ) && !empty( $json ) ) :
// 		return $json;	
// 	else :
// 		die( 'An error occurred while parsing ' . $file . ' file.' );

// 	endif;
// }

/**
 * Print status message.
 *
 * @param  string $message
 * @return string
*/
function status_message( $message ) {
	echo $message . "\r\n";
}




?>