<?php

try {
    require_once(ABSPATH . 'wp-load.php');
    $msg = "";
    $sqlite_msg = "";
    $id = $_POST["id"];
    $sqlRemoveRecord = "DELETE FROM $tableName WHERE id = $id";

    $db->busyTimeout(5000);
    if ($tableName == "history_webhook"){
        if($db->exec($sqlRemoveRecord)){
            $sqlite_msg .= " SQLITE: ALL CORRECT ";
        }else{
            $sqlite_msg .= " SQLITE: " . $db->lastErrorMsg();
        }
    }else if ($tableName == "posted_products"){
        $r = $db->querySingle("SELECT * FROM $tableName WHERE id = $id", true);

        if(!empty($r["woocomerce_product_id"])){
            $msg .= removeProduct($r["woocomerce_product_id"]);
        }

        if($db->exec($sqlRemoveRecord)){
            $sqlite_msg .= " SQLITE: ALL CORRECT ";
        }else{
            $sqlite_msg .= " SQLITE: " . $db->lastErrorMsg();
        }
    }else if ($tableName == "files_info"){
        if($db->exec($sqlRemoveRecord)){
            $sqlite_msg .= " SQLITE: ALL CORRECT ";
        }else{
            $sqlite_msg .= " SQLITE: " . $db->lastErrorMsg();
        }
    }else if ($tableName == "posted_categories"){
        $r = $db->querySingle("SELECT * FROM $tableName WHERE id = $id", true);
        $msg = "";

        if(!empty($r["woocomerce_category_id"])){
            remove_sub_item_from_nav_menu($r);
            $msg = deleteCategory($r["woocomerce_category_id"]);
        }

        if($db->exec($sqlRemoveRecord)){
            $sqlite_msg .= " SQLITE: ALL CORRECT ";
        }else{
            $sqlite_msg .= " SQLITE: " . $db->lastErrorMsg();
        }
    }


    $db->close();
    echo json_encode([
        'message' => "RECORD REMOVED",
        'woo_message' => $msg,
        'sqlite_message' => $sqlite_msg,
    ]);
} catch (\Throwable $th) {
    $db->close();
    echo json_encode(['error' => $th->getMessage()]);
    die();
}

function removeProduct($productId){

    $url_base = 'https://supertiendachina.com.do/';
    $consumer_key = 'ck_88fb2b2bf6ebaffda0a929eb76869237038d6eb7';
    $consumer_secret = 'cs_5f1387718f6fb9eb2439156d3b8c80d35bd3e8af';

    // URL de tu sitio de WooCommerce y endpoint de la API REST
    $url = "$url_base/wp-json/wc/v3/products/$productId";

    // Inicializar cURL
    $ch = curl_init();

    // Configurar la petición cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); // Método DELETE para eliminar
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Devolver la respuesta como string
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ':' . $consumer_secret); // Autenticación básica

    // Ejecutar la petición cURL y obtener la respuesta
    $response = curl_exec($ch);

    // Verificar si ocurrió un error
    if(curl_errno($ch)){
        throw new Exception('Error en cURL: ' . curl_error($ch));
    }

    // Cerrar el recurso cURL
    curl_close($ch);

    // Mostrar la respuesta
    return $response; 
}


function deleteCategory($categoryId) {
    try {
        $taxonomy = 'product_cat'; // Taxonomía de categorías de productos.

        // Intenta eliminar la categoría.
        $result = wp_delete_term($categoryId, $taxonomy);

        if (!is_wp_error($result)) {
            return "Categoría eliminada con éxito";
        } else {
            echo json_encode([
                "message" => $result->get_error_message()
            ]);
            die();
        }
    } catch (\Throwable $th) {
        echo json_encode([
            "message"=> "ERROR ELIMINANDO CATEGORIA: " . $th->getMessage(),
        ]);
        die();
    }
}



function remove_sub_item_from_nav_menu($categoryInfo) {

    if(empty($categoryInfo["woocomerce_category_id"])){
        return;
    }

    $menu_name = "BOTONES DE INICIO";;
    $menu = wp_get_nav_menu_object($menu_name);
    if (empty($menu)) {
        return;
    }

    ////////////////////////////////////////
    $menu_items = wp_get_nav_menu_items($menu->term_id);
    ////////////////////////////////////////



    ////////////////////////////////////////
    $title = $categoryInfo["name"];
    
    foreach ($menu_items as $menu_item) {
        if ($menu_item->title == $title) {
            wp_delete_post($menu_item->ID, true);
        }
    }

}



