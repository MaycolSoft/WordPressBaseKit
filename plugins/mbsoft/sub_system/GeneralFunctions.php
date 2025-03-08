<?php 


require_once(ABSPATH . 'wp-load.php');
include_once(ABSPATH . 'wp-admin/includes/image.php');

if (!class_exists('WC_Product')) {
    include_once(wc()->plugin_path() . '/includes/abstracts/abstract-wc-product.php');
}



if(!function_exists('connecttodatabase')){
    function connecttodatabase() {
        $scriptPath = __FILE__;
        $dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";
        if (!is_writable(dirname($dbPath))) {
            die("El directorio " . dirname($dbPath) . " no es escribible.");
        }

        return new Sqlite3($dbPath);
    }
}




function getproductfromdatabase($db, $codigo) {
    return $db->querysingle("select * from posted_products where sku = '$codigo';", true);
}

function isvalidimage($temp_name) {
    return (bool)getimagesize($temp_name);
}

function uploadimagetowordpress($file_name, $file_path, $product_id, $mime_type) {

    $upload = wp_upload_bits($file_name, null, file_get_contents($file_path));

    if ($upload['error']) {
        return $upload['error'];
    }

    $attachment_data = [
        'post_title' => $file_name,
        'post_content' => '',
        'post_status' => 'inherit',
        'post_mime_type' => $mime_type, 
        'post_parent' => $product_id
    ];

    $attachment_id = wp_insert_attachment($attachment_data, $upload['file'], $product_id);
    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    wp_update_attachment_metadata($attachment_id, $attachment_metadata);
    update_post_meta($product_id, '_thumbnail_id', $attachment_id);



    ////////////////// gallery //////////////////
    // recuperar los ids existentes de la galería de imágenes
    $existing_gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);

    // añadir el nuevo id a la lista
    $updated_gallery_ids = $existing_gallery_ids ? $existing_gallery_ids . ',' . $attachment_id : $attachment_id;

    update_post_meta($product_id, '_product_image_gallery', $updated_gallery_ids);
    ////////////////// gallery //////////////////

    return ["success" => true];
}

function deletemovedfile($file_path) {
  if (file_exists($file_path)) {
      unlink($file_path);
  }
}
















//////////////////////////////////////////
function createproduct($product_data=[]){
    try {
        // crear un nuevo producto
        $product = new WC_Product();

        // configurar las propiedades del producto
        if(isset($product_data['existencia'])) $product->set_stock_quantity($product_data['existencia']);
        if(isset($product_data['name'])) $product->set_name($product_data['name']);
        if(isset($product_data['status'])) $product->set_status($product_data['status']);
        if(isset($product_data['catalog_visibility'])) $product->set_catalog_visibility($product_data['catalog_visibility']);
        if(isset($product_data['description'])) $product->set_description($product_data['description']);
        if(isset($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        } else {
            $product->set_sku( 'sku-' . uniqid() );
        }
        if(isset($product_data['price'])) $product->set_price($product_data['price']);
        if(isset($product_data['regular_price'])) $product->set_regular_price($product_data['regular_price']);
        if(isset($product_data['stock_status'])) $product->set_stock_status($product_data['stock_status']);
        if(isset($product_data['reviews_allowed'])) $product->set_reviews_allowed($product_data['reviews_allowed']);
        if(isset($product_data['sold_individually'])) $product->set_sold_individually($product_data['sold_individually']);
        // if(isset($product_data['category_ids'])) $product->set_category_ids(array_map('intval', $product_data['category_ids']));
        if(isset($product_data['categories'])) {
            $product->set_category_ids(array_map('intval', array_map(function ($abc) {
                return $abc["id"];
            }, array_values($product_data['categories']))));
        }
        
        
        if(isset($product_data['manage_stock'])){
            $product->set_manage_stock($product_data['manage_stock']);
        }else{
            $product->set_manage_stock( true );
        }
    
        // guardar el producto
        $product_id = $product->save();
    
        // ir una imagen al producto
        // if ( $product_id ) {
        //     $attachment_id = wp_insert_attachment( array(
        //         'post_title' => 'imagen del producto',
        //         'post_content' => '',
        //         'post_status' => 'inherit',
        //         'post_mime_type' => 'image/jpeg',
        //         'guid' => 'http://example.com/wp-content/uploads/2024/03/imagen-del-producto.jpg',
        //     ), 'http://example.com/wp-content/uploads/2024/03/imagen-del-producto.jpg', $product_id );

        //     // asociar la imagen al producto
        //     $product->set_image_id( $attachment_id );
        //     $product->save();
        // }

        return $product_id;

    } catch (\wc_data_exception $e) {
        // manejar la excepción específica de woocommerce para sku duplicado o no válido
        if($e->geterrorcode() === 'product_invalid_sku') {
            // llamar a la función para actualizar el producto
            die("producto existe");
            // $product_id = updateproduct($product_data);
            // return $product_id;
        } else {
            // manejar otras excepciones de woocommerce
            // por ejemplo, imprimir el error y detener la ejecución
            echo 'error: ' . $e->getMessage();
            die();
        }
    } catch (\Throwable $th) {
        //throw $th;
        print_r($th);
        die();
    }

}

function updateproduct($product_id, $product_data) {

    try {        
        
        // cargar el producto existente
        $product = new WC_Product($product_id);

        // actualizar las propiedades del producto con los datos proporcionados
        // if(isset($product_data['type'])) $product->set_type($product_data['type']);
        if(isset($product_data['name'])) $product->set_name($product_data['name']);
        if(isset($product_data['description'])) $product->set_description($product_data['description']);
        if(isset($product_data['regular_price'])) $product->set_regular_price($product_data['regular_price']);

        
        if(isset($product_data['price'])) $product->set_price($product_data['price']);
        if(isset($product_data['status'])) $product->set_status($product_data['status']);
        if(isset($product_data['catalog_visibility'])) $product->set_catalog_visibility($product_data['catalog_visibility']);
        if(isset($product_data['manage_stock'])) $product->set_manage_stock($product_data['manage_stock']);
        if(isset($product_data['stock_status'])) $product->set_stock_status($product_data['stock_status']);
        if(isset($product_data['reviews_allowed'])) $product->set_reviews_allowed($product_data['reviews_allowed']);
        if(isset($product_data['sold_individually'])) $product->set_sold_individually($product_data['sold_individually']);
        
        if(isset($product_data['categories'])) {
            $product->set_category_ids(array_map('intval', array_map(function ($abc) {
                return $abc["id"];
            }, array_values($product_data['categories']))));
        }
        // actualizar las propiedades del producto
        // $product->set_sku($product_data['sku']);
        // $product->set_short_description($product_data['short_description']);
        // $product->set_sale_price($product_data['sale_price']);
        // guardar los cambios en el producto
        $product->save();

        return $product;
    } catch (\Throwable $th) {
        echo json_encode([
            "message" => $th->getMessage()
        ]);
        die();
    }

}

function createcategory($name, $descripcion = "", $parent = 0){
    try {

        // crea la categoría.
        $category_id = wp_insert_term(
            $name, // nombre de la categoría.
            'product_cat', // taxonomía de productos.
            array(
                // 'slug' => $category_data['slug'], // slug de la categoría.
                'description' => $descripcion,
                'parent' => $parent,
            )
        );

        // verifica si la categoría se creó correctamente.
        if (!is_wp_error($category_id)) {
            return $category_id;
        } else {
            echo json_encode([
                "message" => $category_id->get_error_message()
            ]);
            die();
        }

    } catch (\Throwable $th) {
        echo json_encode([
            "message"=> "error creando categoria: " . $th->getMessage(),
        ]);
        die();
    }
}

function getcategorybyname($category_name){
    try {
        $category = get_term_by('name', $category_name, 'product_cat');

        if ($category) {
            return $category;
        } else {
            return false;
        }
    } catch (\Throwable $th) {
        echo json_encode([
            "message"=> "error obteniendo categoria: " . $th->getMessage(),
        ]);
        die();
    }
} 











//////////////////////////////////////////
function updatecategoryrecord($recordid, $fieldstoupdate) {
    try {
        $db = connecttodatabase();

        // construye la consulta sql dinámica para actualizar los campos
        $sql = "update posted_categories set ";
        $updates = array();

        foreach ($fieldstoupdate as $field => $value) {
            $updates[] = "$field = :$field";
        }

        $sql .= implode(", ", $updates);
        $sql .= " where id = :id";

        // prepara la consulta sql
        $stmt = $db->prepare($sql);

        // vincula los valores de los campos
        foreach ($fieldstoupdate as $field => $value) {
            $stmt->bindvalue(":$field", $value);
        }

        // vincula el id del registro
        $stmt->bindvalue(":id", $recordid);

        // ejecuta la consulta sql
        $stmt->execute(); 

        // var_dump($stmt->getsql());
        // cierra la conexión a la base de datos
        $db->close(); 

        // retorna true si la actualización fue exitosa
        return true;
    } catch (\Throwable $e) {
        die("error en updatecategoryrecord: " . $e->getMessage());
        // maneja cualquier error aquí (puedes registrar el error o lanzar una excepción según tus necesidades)
        // return false;
    }
}

function insertProductLocal($data, $sku) {
    try {
        // abre la conexión a la base de datos sqlite
        $db = connecttodatabase();

        
        // verifica si el sku ya existe en la tabla
        $existingrecord = $db->querysingle("select sku, datos from posted_products where sku = '$sku'", true);


        if ( !empty($existingrecord) ) {

            $newstatus = '';
            if( md5($existingrecord['datos']) != md5($data) ){
                $newstatus = " status = 'updated', ";
            }

            // el sku ya existe, actualiza el registro
            $sql = "update posted_products set $newstatus datos = '$data', updated_at = current_timestamp where sku = :sku";
        } else {
            // el sku no existe, inserta un nuevo registro
            $sql = "insert into posted_products (datos, sku) values ('$data', :sku)";
        }

        // prepara la sentencia sql
        $stmt = $db->prepare($sql);

        // vincula los parámetros
        $stmt->bindvalue(':sku', $sku, SQLITE3_TEXT);

        // ejecuta la sentencia sql
        $stmt->execute();

        // cierra la conexión a la base de datos
        $db->close();

        // echo "registro insertado o actualizado correctamente.";
    } catch (\Throwable $e) {
        // echo "error al insertar o actualizar el registro: " . $e->getMessage();
        die("Error en insertProductLocal: " . $e->getMessage());
    }
}

function insertcategorylocal($name) {
    try {
        $db = connecttodatabase();
        
        // verifica si el sku ya existe en la tabla
        $existingrecord = $db->querysingle("select * from posted_categories where name = '$name'", true);

        if ( !empty($existingrecord) ) {
            return $existingrecord;
        }
        
        
        $sql = "insert into posted_categories (name) values ('$name')";
        
        // prepara la sentencia sql
        $stmt = $db->prepare($sql);
        
        // ejecuta la sentencia sql
        $stmt->execute();
        
        $result  = $db->querysingle("select * from posted_categories where name = '$name'", true);
        // cierra la conexión a la base de datos
        $db->close();

        return $result;
        // echo "registro insertado o actualizado correctamente.";
    } catch (\Throwable $e) {
        die("Error en insertcategorylocal: " . $e->getMessage());
        // echo "error al insertar o actualizar el registro: " . $e->getMessage();
    }
}

function createorupdatecateogry($name) {

    $name = trim($name);
    $categorydatalocal = insertcategorylocal( $name );

    if( !empty( $categorydatalocal["woocomerce_category_id"] ) ){
        return $categorydatalocal["woocomerce_category_id"];
    }

    $categorywoo = getcategorybyname($name);



    if (!empty($categorywoo) )  {
        updatecategoryrecord($categorydatalocal["id"], ["woocomerce_category_id" => $categorywoo->term_id]);
    }else{
        $__category = createcategory($name);
        updatecategoryrecord(
            $categorydatalocal["id"],
            ["woocomerce_category_id" => $__category["term_id"]]
        );

        require_once "menuCategorias.php";

        return $__category["term_id"];
    }

    return $categorywoo->term_id;

}

function createorupdateproduct($product_data) {

    $sku = trim($product_data['sku']);

    // buscar el record local
    $localid = getProductIDBySKULocal($sku);


    if( empty($localid) ){
        // si no existe local el producto lo insertamos local

        $product = getproductidbysku($sku); //lo buscamos en woocomerce

        if (!empty($product)){
            //////// update local data base //////// 
            insertProductLocal( json_encode($product_data), $sku);

            $localid = getProductIDBySKULocal($sku);
            updateRecord($localid["id"], ["woocomerce_product_id" => $product->get_id()]);
            //////// update local data base //////// 

            $idproduct = $product->get_id();
        }

    }else{
        if(empty($localid["woocomerce_product_id"]) || $localid["woocomerce_product_id"] == "null"){
            $product = getproductidbysku( $sku );
            $idproduct = $product->get_id();

            if (!empty($product)) {
                updateRecord($localid["id"], ["woocomerce_product_id" => $idproduct]);
            }
        }else{
            $idproduct = $localid["woocomerce_product_id"];
        }
    }


    if (empty($idproduct)) {
        // el producto no existe, lo creamos
        $idproduct = createproduct($product_data);

        //////// update local data base //////// 
        insertProductLocal( json_encode($product_data), $sku);
        $localid = getProductIDBySKULocal($sku);
        updateRecord($localid["id"], ["woocomerce_product_id" => $idproduct, "status"=>"processed", 'result_process'=> 'creado via upload-image process']);
        //////// update local data base //////// 

        $response = "producto creado ";
    } else {
        // esta igual que en la base de datos local entonces no se hace nada
        if($localid["status"] != "updated" && md5($localid["datos"]) == md5(json_encode($product_data))){
            return true;
        }

        // el producto existe, actualizamos
        $response = updateproduct($idproduct, $product_data);
        updateRecord($localid["id"], [
            "datos" => json_encode($product_data),
            "status" => 'processed',
            "result_process" => "producto actualizado exitosamente via upload-image."
        ]);
        $response = "funcion actualizar producto comentada";
    }

    return true;
}

function getproductidbysku($sku) {
    try {
        // define los argumentos para la búsqueda, especificando el sku del producto.
        $args = array(
            'sku' => $sku
        );

        // realiza la búsqueda de productos con el sku especificado.
        $products = wc_get_products($args);

        // verifica si se encontró el producto.
        if (!empty($products)) {
            // accede al primer producto encontrado (si hay más de uno, puedes iterar sobre $products).
            $product = $products[0];

            // ahora puedes acceder a la información del producto, por ejemplo, su nombre.
            return $product;
        } 

        return false;

    } catch (\Throwable $th) {
        echo json_encode([
            "message"=> $th->getMessage(),
        ]);
        die();
    }
}

function getProductIDBySKULocal($sku) {
    try {

        // abre la conexión a la base de datos sqlite
        $db = connecttodatabase();

        $information = $db->querysingle("select * from posted_products where sku = '$sku' ", true);


        if ( empty($information) ){
            return null;
        }

        $db->close();

        return $information;
    } catch (\Throwable $e) {
        die("error en getProductIDBySKULocal: " . $e->getMessage());
    }
}

function updateRecord($recordid, $fieldstoupdate) {
    try {
        // abre la conexión a la base de datos sqlite
        $db = connecttodatabase();

        // construye la consulta sql dinámica para actualizar los campos
        $sql = "update posted_products set ";
        $updates = array();

        foreach ($fieldstoupdate as $field => $value) {
            $updates[] = "$field = :$field";
        }

        $sql .= implode(", ", $updates);
        $sql .= " where id = :id";

        // prepara la consulta sql
        $stmt = $db->prepare($sql);

        // vincula los valores de los campos
        foreach ($fieldstoupdate as $field => $value) {
            $stmt->bindvalue(":$field", $value);
        }

        // vincula el id del registro
        $stmt->bindvalue(":id", $recordid);

        // ejecuta la consulta sql
        $stmt->execute(); 

        // var_dump($stmt->getsql());
        // cierra la conexión a la base de datos
        $db->close(); 

        // retorna true si la actualización fue exitosa
        return true;
    } catch (\Throwable $e) {
        die("error en updateRecord: " . $e->getMessage());
        // maneja cualquier error aquí (puedes registrar el error o lanzar una excepción según tus necesidades)
        // return false;
    }
}



?>
