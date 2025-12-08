<?php

// 1. Agregar la nueva columna 'SKU VARIACIONES'
function agregar_columna_var_skus_woocommerce($columns) {
    // Definimos la clave interna como 'var_skus' y el título a mostrar
    $new_columns = array();
    foreach ($columns as $key => $name) {
        $new_columns[$key] = $name;
        if ($key === 'sku') { 
            $new_columns['var_skus'] = 'SKU VARIACIONES'; 
        }
    }
    return $new_columns;
}
add_filter('manage_edit-product_columns', 'agregar_columna_var_skus_woocommerce', 20);

// 2. Mostrar los SKUs, Stock y Status de Auditoría de las variaciones en la columna
function mostrar_skus_variaciones_en_columna($column, $post_id) {
    // Se necesita el objeto global de base de datos de WordPress
    global $wpdb; 
    
    // Usamos la clave 'var_skus'
    if ($column == 'var_skus') { 
        $product = wc_get_product($post_id);

        if ($product->is_type('variable')) {
            $variation_data = array();
            $variation_ids = $product->get_children();

            if (!empty($variation_ids)) {
                // Definimos el nombre de la tabla para reutilizarlo
                $table_name = 'wp_auditoria_productos'; 
                
                foreach ($variation_ids as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    $sku = $variation->get_sku();
                    
                    // --- 2a. Obtención de Stock ---
                    $stock = $variation->get_stock_quantity();
                    $stock_display = is_null($stock) ? 'N/A' : $stock;
                    
                    $status_display = 'N/D'; // Valor por defecto: No Disponible
                    
                    if (!empty($sku)) {
                        // --- 2b. Búsqueda en la tabla wp_auditoria_productos ---
                        
                        // Prepara y ejecuta la consulta SQL para obtener el status
                        $status = $wpdb->get_var( $wpdb->prepare(
                            "SELECT status_variacion_auditoria 
                             FROM {$table_name} 
                             WHERE sku_variacion_tienda = %s",
                            $sku
                        ) );
                        
                        // Si se encuentra el status, se utiliza; si no, mantiene 'N/D'
                        if (!empty($status)) {
                            $status_display = $status;
                        }
                        
                        // --- 2c. Formato Final ---
                        // Formato: SKU (Stock) [Status Auditoría]
                        $variation_data[] = $sku . ' (' . $stock_display . ') [' . $status_display . ']';
                    }
                }
            }

            if (!empty($variation_data)) {
                // Muestra la lista de SKU/Stock/Status
                echo '<div class="var-skus-content">';
                echo '<strong>' . implode(', <br>', $variation_data) . '</strong>';
                echo '</div>';
            } else {
                echo '<span style="color: grey;">Sin SKUs de variación</span>';
            }
        } elseif ($product->is_type('simple')) {
            // Lógica para producto simple (para mantener consistencia)
            $simple_sku = $product->get_sku();
            $simple_stock = $product->get_stock_quantity();

            $simple_stock_display = is_null($simple_stock) ? 'N/A' : $simple_stock;
            $simple_status_display = 'N/D';
            
            if (!empty($simple_sku)) {
                 $table_name = 'wp_auditoria_productos'; 
                 $status = $wpdb->get_var( $wpdb->prepare(
                    "SELECT status_variacion_auditoria FROM {$table_name} WHERE sku_variacion_tienda = %s",
                    $simple_sku
                ) );

                if (!empty($status)) {
                    $simple_status_display = $status;
                }
            }
            
            echo '<span style="color: #0073aa;">Simple: ' . $simple_sku . ' (' . $simple_stock_display . ') [' . $simple_status_display . ']</span>';

        } else {
            echo '—';
        }
    }
}
add_action('manage_product_posts_custom_column', 'mostrar_skus_variaciones_en_columna', 10, 2);


// 3. Agregar CSS para forzar el flujo horizontal (Ancho ajustado)
function agregar_css_columna_var_skus() {
    global $pagenow, $post_type;
    
    if (is_admin() && $pagenow == 'edit.php' && $post_type == 'product') {
        $custom_css = '
            .column-var_skus {
                width: 250px; /* Aumentado para acomodar el SKU + Stock + Status de Auditoría */
                white-space: normal !important; 
            }
            .column-var_skus .var-skus-content {
                white-space: normal !important; 
                overflow: visible !important;
            }
            .column-var_skus {
                vertical-align: top; 
            }
        ';
        // Inyecta el CSS en la página del administrador para asegurar el formato horizontal
        wp_add_inline_style('list-tables', $custom_css);
    }
}
add_action('admin_enqueue_scripts', 'agregar_css_columna_var_skus');


?>