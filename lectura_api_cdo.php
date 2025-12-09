<?php


/**
 * Función que simula la llamada a la API y la paginación de la lectura del archivo.
 * * @param string $url_API_woo URL de la API de WooCommerce.
 * @param string $ck_API_woo Consumer Key.
 * @param string $cs_API_woo Consumer Secret.
 * @param int $page_number El número de página actual a procesar.
 * @param int $total_pages El número total de páginas (actualmente un valor fijo).
 * @return void
 */
 
 
function call_api($url_API_woo, $ck_API_woo,$cs_API_woo, $page_number, $total_pages)
{
    // Carga de la data desde el archivo DATA_FILE
    // NOTA: Se asume que DATA_FILE contiene una estructura JSON como: 
    // {"productos": [{"code": "...", "page": 1, ...}, {"code": "...", "page": 2, ...}]}
    $data_json = file_get_contents(__DIR__ . '/' . DATA_FILE);
    $data_array = json_decode($data_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        exit('❗Error: No se pudo decodificar el archivo JSON: ' . DATA_FILE);
    }

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
    
    // ** MODIFICACIÓN CLAVE: Filtrar solo los productos para la página actual **
    $all_products = $data_array['productos'] ?? [];
    
    // Filtramos los productos para que solo se incluyan los de la página actual.
    // Se asume que cada producto tiene una clave 'page' con el número de página.
    $items_origin = array_filter($all_products, function ($product) use ($page_number) {
        // Asegúrate de que $product['page'] exista y sea igual al $page_number.
        return isset($product['page']) && (int)$product['page'] === $page_number;
    });
    
    // El array filtrado debe ser reindexado para evitar problemas con foreach.
    $items_origin = array_values($items_origin);
    // ** FIN MODIFICACIÓN CLAVE **

    $cantidad_de_productos_api = count($items_origin);

    print("\n");
    status_message('Cantidad Productos Por SKU para la Página ' . $page_number . ': '. $cantidad_de_productos_api);
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
            // Se asume que $product['variants'] existe para el conteo
            $array_variantes = $product['variants'] ?? []; 
            $cantidad_variaciones = count($array_variantes);
    
            global $nombre_atributo;


            status_message( 'NOMBRE ATRIBUTO: '. $nombre_atributo);
            print("\n");
    
    
            // PASO 1: VERIFICAR SI EXISTE EL PRODUCTO POR SU SKU
    
            status_message( 'Verificar si existe producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
            print("\n");
    
            $hora_inicio_proceso = date("h:i:sa");
    
            $objeto_producto = verificar_producto_por_sku($woocommerce, $sku);
    
            if (!$objeto_producto) {
    
                status_message( 'NO EXISTE producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
                print("\n");
    
                status_message( 'CREAR producto por el SKU: '. $sku . ' a las '. date("h:i:sa"));
                print("\n");
    
                // NO EXISTE...CREAR PRODUCTO PADRE...
    
                // Asegúrate de definir crear_producto_padre si no está en el código provisto
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
    
            // Asegúrate de definir calcularDiferenciaDeTiempo si no está en el código provisto
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