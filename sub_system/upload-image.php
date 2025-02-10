<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if(empty($_POST)){
    die();
}

$requests_per_minute=600;
require_once "RequestHistory.php";


$scriptPath = __FILE__;
$dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";


// $_POST["imagen"]
// $_POST["codigo"]
// $_POST["clase"]
// $_POST["nombreclase"]
// $_POST["precio"]
// $_POST["existencia"]

try {
    require_once('../wp-load.php');
    include(ABSPATH . 'wp-admin/includes/image.php');

    if (!class_exists('WC_Product')) {
        // Incluye las clases de WooCommerce si no están cargada
        include_once(WC()->plugin_path() . '/includes/abstracts/abstract-wc-product.php');
    }

    // Esto es porque aveces manda un json txt y se carga desde php input
    // y se hace otra logica para ver la informacion y seguir la logica del archivo
    if(empty($_POST)){
        $_POST = file_get_contents('php://input');
        $json = json_decode($_POST);

        if($json === NULL || !$json ){
            $_POST = str_replace("'Articulos'", '"Articulos"', "$_POST");
            $json = json_decode($_POST);
        }

        if( $json === NULL || !$json ){
            die(" JSON NO VALIDO ");
        }

        if(!is_array($json->Articulos)){
            die(" JSON NO VALIDO ");
        }

        $json = $json->Articulos;

        foreach ($json as $index => $product) {

            $p = [
                'name' =>trim($product->descripcio),
                'description' => trim($product->descripcio),
                'type' => 'simple',
                'regular_price' => floatval(str_replace(',', '', trim($product->precio1))),
                'sku' => trim($product->codigo),
                'existencia' => intval(str_replace(',', '', $product->existencia))
            ];

            //// CATEGORIES
            $idCategory = intval(createOrUpdateCateogry( trim($product->nombre) ));
            //// CATEGORIES

            if(is_int($idCategory) && $idCategory != 0){
                $p["categories"] = [
                    ["id" => $idCategory]
                ];
            }

            createOrUpdateProduct($p);
        }

        die("");
    }


    $p = [
        'name' => trim($_POST["descripcion"]),
        'description' => trim($_POST["descripcion"]),
        'type' => 'simple',
        'regular_price' => floatval(str_replace(',', '', trim($_POST["precio"]))),
        'sku' => trim($_POST["codigo"]),
        'existencia' => intval(str_replace(',', '', $_POST["existencia"]))
    ];

    //// CATEGORIES
    if(isset($_POST["nombreclase"])){
        $idCategory = intval(createOrUpdateCateogry( trim($_POST["nombreclase"]) ));
        if(is_int($idCategory) && $idCategory != 0){
            $p["categories"] = [
                ["id" => $idCategory]
            ];
        }
    }
    //// CATEGORIES


    createOrUpdateProduct($p);
    $localID = getProductIDBySKULocal($p["sku"]);


    if( empty($localID) || (empty($localID["woocomerce_product_id"]) || $localID["woocomerce_product_id"] == "NULL")){
        echo json_encode([
            "message" => "Al parecer no se pudo crear el producto, contacta al administrador para mas informacion"
        ]);
    }
    $product_id = $localID["woocomerce_product_id"];

    
    ////////////////////////////////////////////////////////////////////
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_FILES["imagen"])) {
        // Respuesta en caso de solicitud no válida o no se haya enviado una imagen
        $response = ["mensaje" => "Solicitud no válida o no se ha enviado una imagen."];
        echo json_encode($response);
        return;
    }

    $imagen = $_FILES["imagen"];
    $temp_name = $imagen["tmp_name"];
    
    if (!isValidImage($temp_name)) {
        echo json_encode(["message" => "El archivo no es una imagen válida."]);
        return;
    }
    
    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
    $image_info = getimagesize($temp_name);
    
    if (!in_array($image_info[2], $allowed_types)) {
        echo json_encode(["message" => "Tipo de imagen no permitido. Solo se permiten JPEG, PNG y GIF."]);
        return;
    }
    ////////////////////////////////////////////////////////////////////






    /////////////////////////////////////////////////////////////////////
    $upload_dir = __DIR__ . "/";
    $file_name = basename($imagen["name"]);
    $file_path = $upload_dir . $file_name;

    if(empty($localID["images_name"])){
        updateRecord($localID["id"], ["images_name" => json_encode([[ "name" => $file_name ]])]);
    }else{
        $images_name_json = json_decode($localID["images_name"]);
        if(empty($images_name_json)){
            updateRecord($localID["id"], ["images_name" => json_encode([[ "name" => $file_name ]])]);
        }else{
            foreach ($images_name_json as $key => $value) {
                if($value->name == $file_name ){
                    echo json_encode([
                        "message" => "La imagen ya esta subida"
                    ]);
                    die();
                }
            }
        }
    }

    // echo json_encode([
    //     "message" => "LAS IMAGENES NO SE ACTUALIZARAN POR EL MOMENTO"
    // ]);
    // die();
    move_uploaded_file($temp_name, $file_path);
    
    $upload = uploadImageToWordPress($file_name, $file_path, $product_id, $image_info["mime"]);
    
    if (isset($upload['error']) && $upload['error']) {
        echo json_encode(["message"=> 'Error al subir la imagen: ' . $upload['error']]);
        return;
    }
    
    $response = [
        "mensaje"   => "Imagen subida exitosamente",
        "nombre"    => $file_name,
        "tipo"      => $imagen["type"],
        "temporal"  => $temp_name,
        "size"      => $imagen["size"]
    ];
    
    // Eliminar el archivo movido después de subirlo a WordPress
    deleteMovedFile($file_path);
    /////////////////////////////////////////////////////////////////////
    

    echo json_encode($response);
    die();

} catch (\Throwable $th) {
    echo json_encode([
        "message" => $th->getMessage()
    ]);
    die();
}






die("HOLA");


try {
    if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_FILES["imagen"])) {
        // Respuesta en caso de solicitud no válida o no se haya enviado una imagen
        $response = ["mensaje" => "Solicitud no válida o no se ha enviado una imagen."];
        echo json_encode($response);
        return;
    }

    $codigo = trim($_POST["codigo"]);
    $descripcion = trim($_POST["descripcion"]);

    if (empty($codigo)) {
        echo json_encode(["message" => "Código de producto no enviado"]);
        return;
    }

    $db = connectToDatabase();
    $product = getProductFromDatabase($db, $codigo);

    if (empty($product)) {
        echo json_encode(["message" => "Producto ($codigo) no existe"]);
        return;
    }

    $imagen = $_FILES["imagen"];
    $temp_name = $imagen["tmp_name"];

    if (!isValidImage($temp_name)) {
        echo json_encode(["message" => "El archivo no es una imagen válida."]);
        return;
    }

    $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
    $image_info = getimagesize($temp_name);

    if (!in_array($image_info[2], $allowed_types)) {
        echo json_encode(["message" => "Tipo de imagen no permitido. Solo se permiten JPEG, PNG y GIF."]);
        return;
    }

    if (empty($product["woocomerce_product_id"])) {
        echo json_encode(["message" => "El producto no está subido en el sistema (woocomerce_product_id) está vacío"]);
        return;
    }

    $product_id = $product["woocomerce_product_id"];

    $upload_dir = __DIR__ . "/";
    $file_name = basename($imagen["name"]);
    $file_path = $upload_dir . $file_name;

    move_uploaded_file($temp_name, $file_path);

    $upload = uploadImageToWordPress($file_name, $file_path, $product_id, $image_info["mime"]);

    if (isset($upload['error']) && $upload['error']) {
        echo json_encode(["message"=> 'Error al subir la imagen: ' . $upload['error']]);
        return;
    }

    $response = [
        "mensaje"   => "Imagen subida exitosamente",
        "nombre"    => $file_name,
        "tipo"      => $imagen["type"],
        "temporal"  => $temp_name,
        "size"      => $imagen["size"]
    ];

    // Eliminar el archivo movido después de subirlo a WordPress
    deleteMovedFile($file_path);


    echo json_encode($response);
} catch (\Throwable $th) {
    echo json_encode(["message" => $th->getMessage()]);
} finally {
    if (isset($db)) {
        $db->close();
    }
}


function connectToDatabase() {
    $scriptPath = __FILE__;
    $dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";
    return new SQLite3($dbPath);
}

function getProductFromDatabase($db, $codigo) {
    return $db->querySingle("SELECT * FROM posted_products WHERE sku = '$codigo';", true);
}

function isValidImage($temp_name) {
    return (bool)getimagesize($temp_name);
}

function uploadImageToWordPress($file_name, $file_path, $product_id, $mime_type) {

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



    ////////////////// GALLERY //////////////////
    // Recuperar los IDs existentes de la galería de imágenes
    $existing_gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);

    // Añadir el nuevo ID a la lista
    $updated_gallery_ids = $existing_gallery_ids ? $existing_gallery_ids . ',' . $attachment_id : $attachment_id;

    update_post_meta($product_id, '_product_image_gallery', $updated_gallery_ids);
    ////////////////// GALLERY //////////////////

    return ["success" => true];
}

function deleteMovedFile($file_path) {
  if (file_exists($file_path)) {
      unlink($file_path);
  }
}
















//////////////////////////////////////////
function createProduct($product_data=[]){
    try {
        // Crear un nuevo producto
        $product = new WC_Product();

        // Configurar las propiedades del producto
        if(isset($product_data['existencia'])) $product->set_stock_quantity($product_data['existencia']);
        if(isset($product_data['name'])) $product->set_name($product_data['name']);
        if(isset($product_data['status'])) $product->set_status($product_data['status']);
        if(isset($product_data['catalog_visibility'])) $product->set_catalog_visibility($product_data['catalog_visibility']);
        if(isset($product_data['description'])) $product->set_description($product_data['description']);
        if(isset($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        } else {
            $product->set_sku( 'SKU-' . uniqid() );
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
    
        // Guardar el producto
        $product_id = $product->save();
    
        // ir una imagen al producto
        // if ( $product_id ) {
        //     $attachment_id = wp_insert_attachment( array(
        //         'post_title' => 'Imagen del Producto',
        //         'post_content' => '',
        //         'post_status' => 'inherit',
        //         'post_mime_type' => 'image/jpeg',
        //         'guid' => 'http://example.com/wp-content/uploads/2024/03/imagen-del-producto.jpg',
        //     ), 'http://example.com/wp-content/uploads/2024/03/imagen-del-producto.jpg', $product_id );

        //     // Asociar la imagen al producto
        //     $product->set_image_id( $attachment_id );
        //     $product->save();
        // }

        return $product_id;

    } catch (\WC_Data_Exception $e) {
        // Manejar la excepción específica de WooCommerce para SKU duplicado o no válido
        if($e->getErrorCode() === 'product_invalid_sku') {
            // Llamar a la función para actualizar el producto
            die("PRODUCTO EXISTE");
            $product_id = updateProduct($product_data);
            return $product_id;
        } else {
            // Manejar otras excepciones de WooCommerce
            // Por ejemplo, imprimir el error y detener la ejecución
            echo 'Error: ' . $e->getMessage();
            die();
        }
    } catch (\Throwable $th) {
        //throw $th;
        print_r($th);
        die();
    }

}

function updateProduct($product_id, $product_data) {

    try {        
        
        // Cargar el producto existente
        $product = new WC_Product($product_id);

        // Actualizar las propiedades del producto con los datos proporcionados
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
        // Actualizar las propiedades del producto
        // $product->set_sku($product_data['sku']);
        // $product->set_short_description($product_data['short_description']);
        // $product->set_sale_price($product_data['sale_price']);
        // Guardar los cambios en el producto
        $product->save();

        return $product;
    } catch (\Throwable $th) {
        echo json_encode([
            "message" => $th->getMessage()
        ]);
        die();
    }

}

function createCategory($name, $descripcion = "", $parent = 0){
    try {

        // Crea la categoría.
        $category_id = wp_insert_term(
            $name, // Nombre de la categoría.
            'product_cat', // Taxonomía de productos.
            array(
                // 'slug' => $category_data['slug'], // Slug de la categoría.
                'description' => $descripcion,
                'parent' => $parent,
            )
        );

        // Verifica si la categoría se creó correctamente.
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
            "message"=> "ERROR CREANDO CATEGORIA: " . $th->getMessage(),
        ]);
        die();
    }
}

function getCategoryByName($category_name){
    try {
        $category = get_term_by('name', $category_name, 'product_cat');

        if ($category) {
            return $category;
        } else {
            return false;
        }
    } catch (\Throwable $th) {
        echo json_encode([
            "message"=> "ERROR OBTENIENDO CATEGORIA: " . $th->getMessage(),
        ]);
        die();
    }
}
//////////////////////////////////////////













//////////////////////////////////////////
function updateCategoryRecord($recordId, $fieldsToUpdate) {
    try {
        global $dbPath;
        
        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);

        // Construye la consulta SQL dinámica para actualizar los campos
        $sql = "UPDATE posted_categories SET ";
        $updates = array();

        foreach ($fieldsToUpdate as $field => $value) {
            $updates[] = "$field = :$field";
        }

        $sql .= implode(", ", $updates);
        $sql .= " WHERE id = :id";

        // Prepara la consulta SQL
        $stmt = $db->prepare($sql);

        // Vincula los valores de los campos
        foreach ($fieldsToUpdate as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }

        // Vincula el ID del registro
        $stmt->bindValue(":id", $recordId);

        // Ejecuta la consulta SQL
        $stmt->execute(); 

        // var_dump($stmt->getSQL());
        // Cierra la conexión a la base de datos
        $db->close(); 

        // Retorna true si la actualización fue exitosa
        return true;
    } catch (\Throwable $e) {
        // Maneja cualquier error aquí (puedes registrar el error o lanzar una excepción según tus necesidades)
        return false;
    }
}

function insertProductLocal($data, $sku) {
    try {
        global $dbPath;
        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);

        
        // Verifica si el SKU ya existe en la tabla
        $existingRecord = $db->querySingle("SELECT sku, datos FROM posted_products WHERE sku = '$sku'", true);


        if ( !empty($existingRecord) ) {

            $newStatus = '';
            if( md5($existingRecord['datos']) != md5($data) ){
                $newStatus = " status = 'UPDATED', ";
            }

            // El SKU ya existe, actualiza el registro
            $sql = "UPDATE posted_products SET $newStatus datos = '$data', updated_at = CURRENT_TIMESTAMP WHERE sku = :sku";
        } else {
            // El SKU no existe, inserta un nuevo registro
            $sql = "INSERT INTO posted_products (datos, sku) VALUES ('$data', :sku)";
        }

        // Prepara la sentencia SQL
        $stmt = $db->prepare($sql);

        // Vincula los parámetros
        $stmt->bindValue(':sku', $sku, SQLITE3_TEXT);

        // Ejecuta la sentencia SQL
        $stmt->execute();

        // Cierra la conexión a la base de datos
        $db->close();

        // echo "Registro insertado o actualizado correctamente.";
    } catch (\Throwable $e) {
        // echo "Error al insertar o actualizar el registro: " . $e->getMessage();
    }
}

function insertCategoryLocal($name) {
    try {
        global $dbPath;

        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);
        
        // Verifica si el SKU ya existe en la tabla
        $existingRecord = $db->querySingle("SELECT * FROM posted_categories WHERE name = '$name'", true);

        if ( !empty($existingRecord) ) {
            return $existingRecord;
        }
        
        
        $sql = "INSERT INTO posted_categories (name) VALUES ('$name')";
        
        // Prepara la sentencia SQL
        $stmt = $db->prepare($sql);
        
        // Ejecuta la sentencia SQL
        $stmt->execute();
        
        $result  = $db->querySingle("SELECT * FROM posted_categories WHERE name = '$name'", true);
        // Cierra la conexión a la base de datos
        $db->close();

        return $result;
        // echo "Registro insertado o actualizado correctamente.";
    } catch (\Throwable $e) {
        // echo "Error al insertar o actualizar el registro: " . $e->getMessage();
    }
}

function createOrUpdateCateogry($name) {

    $name = trim($name);
    $categoryDataLocal = insertCategoryLocal( $name );

    if( !empty( $categoryDataLocal["woocomerce_category_id"] ) ){
        
        return $categoryDataLocal["woocomerce_category_id"];
    }

    $categoryWOO = getCategoryByName($name);

    if (!empty($categoryWOO) )  {
        updateCategoryRecord($categoryDataLocal["id"], ["woocomerce_category_id" => $categoryWOO->term_id]);
    }else{
        $__category = createCategory($name);
        updateCategoryRecord(
            $categoryDataLocal["id"],
            ["woocomerce_category_id" => $__category["term_id"]]
        );

        require_once "menuCategorias.php";

        return $__category["term_id"];
    }

    return $categoryWOO->term_id;

}

function createOrUpdateProduct($product_data) {

    $sku = trim($product_data['sku']);

    // buscar el record local
    $localID = getProductIDBySKULocal($sku);



    if( empty($localID) ){
        // si no existe local el producto lo insertamos local
        
        $product = getProductIDBySKU($sku); //lo buscamos en WOOCOMERCE

        if (!empty($product)){
            //////// UPDATE LOCAL DATA BASE //////// 
            insertProductLocal( json_encode($product_data), $sku);
            $localID = getProductIDBySKULocal($sku);
            updateRecord($localID["id"], ["woocomerce_product_id" => $product->get_id()]);
            //////// UPDATE LOCAL DATA BASE //////// 

            $idProduct = $product->get_id();
        }

    }else{
        if(empty($localID["woocomerce_product_id"]) || $localID["woocomerce_product_id"] == "NULL"){
            $product = getProductIDBySKU( $sku );
            $idProduct = $product->get_id();

            if (!empty($product)) {
                updateRecord($localID["id"], ["woocomerce_product_id" => $idProduct]);
            }
        }else{
            $idProduct = $localID["woocomerce_product_id"];
        }
    }


    if (empty($idProduct)) {
        // El producto no existe, lo creamos
        $idProduct = createProduct($product_data);

        //////// UPDATE LOCAL DATA BASE //////// 
        insertProductLocal( json_encode($product_data), $sku);
        $localID = getProductIDBySKULocal($sku);
        updateRecord($localID["id"], ["woocomerce_product_id" => $idProduct, "status"=>"PROCESSED", 'result_process'=> 'Creado via upload-image process']);
        //////// UPDATE LOCAL DATA BASE //////// 

        $response = "Producto creado ";
    } else {
        // esta igual que en la base de datos local entonces no se hace nada
        if($localID["status"] != "UPDATED" && md5($localID["datos"]) == md5(json_encode($product_data))){
            return true;
        }

        // El producto existe, actualizamos
        $response = updateProduct($idProduct, $product_data);
        updateRecord($localID["id"], [
            "datos" => json_encode($product_data),
            "status" => 'PROCESSED',
            "result_process" => "Producto actualizado exitosamente via upload-image."
        ]);
        $response = "FUNCION ACTUALIZAR PRODUCTO COMENTADA";
    }

    return true;
}

function getProductIDBySKU($sku) {
    try {
        // Define los argumentos para la búsqueda, especificando el SKU del producto.
        $args = array(
            'sku' => $sku
        );

        // Realiza la búsqueda de productos con el SKU especificado.
        $products = wc_get_products($args);

        // Verifica si se encontró el producto.
        if (!empty($products)) {
            // Accede al primer producto encontrado (si hay más de uno, puedes iterar sobre $products).
            $product = $products[0];

            // Ahora puedes acceder a la información del producto, por ejemplo, su nombre.
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
    global $dbPath;
    
    try {

        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);
        // Consulta la base de datos para obtener el registro con el SKU dado
        $information = $db->querySingle("SELECT * FROM posted_products WHERE sku = '$sku' ", true);


        if ( empty($information) ){
            return NULL;
        }

        $db->close();

        return $information;
    } catch (\Throwable $e) {
        // Maneja cualquier error de la base de datos aquí
        // echo "Error al obtener el registro por SKU: " . $e->getMessage();
        return null;
    }
}

function updateRecord($recordId, $fieldsToUpdate) {
    try {
        global $dbPath;
        
        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);

        // Construye la consulta SQL dinámica para actualizar los campos
        $sql = "UPDATE posted_products SET ";
        $updates = array();

        foreach ($fieldsToUpdate as $field => $value) {
            $updates[] = "$field = :$field";
        }

        $sql .= implode(", ", $updates);
        $sql .= " WHERE id = :id";

        // Prepara la consulta SQL
        $stmt = $db->prepare($sql);

        // Vincula los valores de los campos
        foreach ($fieldsToUpdate as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }

        // Vincula el ID del registro
        $stmt->bindValue(":id", $recordId);

        // Ejecuta la consulta SQL
        $stmt->execute(); 

        // var_dump($stmt->getSQL());
        // Cierra la conexión a la base de datos
        $db->close(); 

        // Retorna true si la actualización fue exitosa
        return true;
    } catch (\Throwable $e) {
        // Maneja cualquier error aquí (puedes registrar el error o lanzar una excepción según tus necesidades)
        return false;
    }
}



?>
