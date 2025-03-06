<?php

///////////////////////////////////////////////////////////////////////////
// Obtener el cuerpo de la solicitud
$_POST = file_get_contents('php://input');


///////////////////////////////////////////////////
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

function getCategoryLocal($name) {
    global $dbPath;
    
    try {

        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);

        // Consulta la base de datos para obtener el registro con el SKU dado
        $information = $db->querySingle("SELECT * FROM posted_categories WHERE 1=1  AND name = '$name'", true);
        
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

// Función para insertar un registro en la tabla
///////////////////////////////////////////////////
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

///////////////////////////////////////////////////
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





// Configuración de la API de WooCommerce
$url_base = 'https://supertiendachina.com.do/';
$consumer_key = 'ck_88fb2b2bf6ebaffda0a929eb76869237038d6eb7';
$consumer_secret = 'cs_5f1387718f6fb9eb2439156d3b8c80d35bd3e8af';

// Función para crear un producto si no existe, o actualizarlo si existe
function createOrUpdateProduct($product_data) {
    global $url_base, $consumer_key, $consumer_secret, $_POST;

    $sku = trim($product_data['sku']);

    if(!isset($_REQUEST["sync_databases"])){
        insertProductLocal( json_encode($product_data), $sku);
        return "SKU: $sku - PRODUCTO ALMACENADO PARA UNA PROXIMA SINCRONIZACION";
    }
    
    $localID = getProductIDBySKULocal($sku);


    if( empty($localID) ){
        $idProduct = getProductIDBySKU($sku);

        if (!($idProduct === NULL)){
            //////// UPDATE LOCAL DATA BASE //////// 
            insertProductLocal( json_encode($product_data), $sku);
            $localID = getProductIDBySKULocal($sku);
            updateRecord($localID["id"], ["woocomerce_product_id" => $idProduct]);
            //////// UPDATE LOCAL DATA BASE //////// 
        }

    }else{
        if(empty($localID["woocomerce_product_id"]) || $localID["woocomerce_product_id"] == "NULL"){
            $idProduct = getProductIDBySKU( $sku );
            
            if (!($idProduct === NULL)) {
                updateRecord($localID["id"], ["woocomerce_product_id" => $idProduct]);
            }
        }else{
            $idProduct = $localID["woocomerce_product_id"];
        }
    }


    if ($idProduct === NULL) {
        // El producto no existe, lo creamos
        $response = createProduct($product_data);
    } else {
        // El producto existe, actualizamos
        $response = updateProduct($idProduct, $product_data);
    }

    return "SKU: $sku - $response";
}

// Función para obtener el ID de un producto por SKU
function getProductIDBySKU($sku) {
    global $url_base, $consumer_key, $consumer_secret;

    $endpoint = "{$url_base}/wp-json/wc/v3/products";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}&sku={$sku}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data[0]['id'])) {
        return $data[0]['id'];
    } else {
        return null;
    }

    curl_close($ch);
}

function getProductBySKU($sku) {
    global $url_base, $consumer_key, $consumer_secret;

    $endpoint = "{$url_base}/wp-json/wc/v3/products";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}&sku={$sku}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $data = json_decode($response, true);

    if (isset($data[0]['id'])) {
        return $data[0];
    } else {
        return null;
    }

    curl_close($ch);
}

function getCategoryByName($name) {
    global $url_base, $consumer_key, $consumer_secret;
    $name = urlencode($name);
    $endpoint = "{$url_base}/wp-json/wc/v3/products/categories";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}&search={$name}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        // Manejar el error cURL
        $error_message = curl_error($ch);
        return "Error cURL: $error_message";
    }

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_status !== 200) {
        // Manejar el código de estado HTTP no exitoso
        curl_close($ch);
        return "Respuesta HTTP no exitosa: $http_status";
    }

    curl_close($ch);

    $data = json_decode($response, true);

    if (!empty($data)) {
        return $data[0]; // Devuelve la primera coincidencia encontrada
    } else {
        return null; // No se encontraron coincidencias
    }
}



// Función para crear un nuevo producto
function createProduct($product_data) {
    global $url_base, $consumer_key, $consumer_secret;

    $endpoint = "{$url_base}/wp-json/wc/v3/products";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);
    $response = json_decode($response);

    $message = "";
    if (curl_errno($ch)) {
        $message = "Error al crear el producto: " . curl_error($ch);
    } else {
        $message = "Producto creado exitosamente.";

        if( isset($response->code) ){
            $message = $response->message;
        }
    }

    curl_close($ch);

    ///////////////////////
    $localID = getProductIDBySKULocal($product_data["sku"]);

    if(!empty($localID) && isset($response->id)){
        updateRecord($localID["id"], ["woocomerce_product_id" => $response->id]);
    }
    ///////////////////////

    return $message;
}

// Función para actualizar un producto existente
function updateProduct($product_id, $product_data) {
    global $url_base, $consumer_key, $consumer_secret;

    $endpoint = "{$url_base}/wp-json/wc/v3/products/{$product_id}";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($product_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);

    $message = "";
    if (curl_errno($ch)) {
        $message = "Error al actualizar el producto: " . curl_error($ch);
    } else {
        $message = "Producto actualizado exitosamente.";
    }

    curl_close($ch);
    return $message;
}



////////////////////////////////////////////////////////////////////////////////////////
// Función para crear un producto si no existe, o actualizarlo si existe
function createOrUpdateCateogry($name) {
    global $url_base, $consumer_key, $consumer_secret, $_POST;

    $name = trim($name);
    $categoryDataLocal = insertCategoryLocal( $name );

    if( empty( $categoryDataLocal["woocomerce_category_id"] ) ){
        $categoryWOO = getCategoryByName($name);

        if (!empty($categoryWOO) )  {
            updateCategoryRecord($categoryDataLocal["id"], ["woocomerce_category_id" => $categoryWOO["id"]]);
            return $categoryWOO["id"];
        }else{
            createCategory($name);
            $categoryWOO = getCategoryByName($name);
            updateCategoryRecord($categoryDataLocal["id"], ["woocomerce_category_id" => $categoryWOO["id"]]);
            return $categoryWOO["id"];
        }
    }
    
    return $categoryDataLocal["woocomerce_category_id"];
}

// Función para crear una nueva categoría
function createCategory($name) {
    global $url_base, $consumer_key, $consumer_secret;

    $endpoint = "{$url_base}/wp-json/wc/v3/products/categories";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["name" => $name]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);
    $response = json_decode($response);

    $message = "";
    if (curl_errno($ch)) {
        $message = "Error al crear la categoría: " . curl_error($ch);
    } else {
        $message = "Categoría creada exitosamente.";

        if (isset($response->code)) {
            $message = $response->message;
        }
    }

    curl_close($ch);
    return $message;
}

// Función para actualizar una categoría existente
function updateCategory($category_id, $name) {
    global $url_base, $consumer_key, $consumer_secret;

    $endpoint = "{$url_base}/wp-json/wc/v3/products/categories/{$category_id}";
    $url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["name" => $name]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);
    $response = json_decode($response);

    $message = "";
    if (curl_errno($ch)) {
        $message = "Error al actualizar la categoría: " . curl_error($ch);
    } else {
        $message = "Categoría actualizada exitosamente.";

        if (isset($response->code)) {
            $message = $response->message;
        }
    }

    curl_close($ch);
    return $message;
}
////////////////////////////////////////////////////////////////////////////////////////






function processFileOfProducts(){
    try {
        $total_files = count($_FILES);
    
        foreach($_FILES as $key=>$value) {
    
            $contenido = file_get_contents( $value["tmp_name"] );
            $json = json_decode($contenido);
            $fields = isset($_POST["select-$key"])? json_decode($_POST["select-$key"]): false;
    
    
            if(!is_array($json)){
                $json = [$json];
            }
    
            $newArray = [];
            foreach ($json as $item) {
    
                $newItem = [];
                foreach ($fields as $key_select => $field) {
    
                    // Buscar en el objeto el campo $field
                    if (isset($item->$field)) {
                        if($key_select === "categories"){
                            // $newItem[$key_select] = $item->$field;
                            continue;
                        }else{
                            $newItem[$key_select] = $item->$field;
                        }
                    }
    
                }
    
                if( empty($newItem) ){
                    return;
                }
    
                echo createOrUpdateProduct($newItem);
                echo "<br>";
            }
            
        }
        
    } catch (Exception $e) {
        // Manejar cualquier excepción si ocurre
        echo "Error: ". $e->getMessage();
        echo "<br>";
    }
}

function processPOSTOfProducts(){
    try {

        $json = json_decode($_POST);

        if($json === NULL || !$json ){
            $_POST = str_replace("'Articulos'", '"Articulos"', "$_POST");
            $json = json_decode($_POST);
        }

        if( $json === NULL || !$json ){
            echo "JSON NO VALIDO";
            return;
        }
        
        if(!is_array($json->Articulos)){
            echo "JSON NO VALIDO";
            return;
        }

        $json = $json->Articulos;
        
        
        $keysEcommerce = ['sku', 'name', 'type', 'regular_price', 'description', 'short_description', 'categories', 'sale_price'];
        $keysJSON = ['codigo', 'descripcio', 'referencia', 'precio1', 'clase', 'nombre', 'foto'];
        
        // Inicializar un array para almacenar errores
        $errors = [];
        
        foreach ($json as $index => $product) {
            $errorFound = false;
            foreach ($keysJSON as $k) {
                if (!isset($product->{$k})) {
                    $errors[] = "La clave '$k' no existe en el elemento JSON INDICE  $index";
                    $errorFound = true;
                }
            }
    
            if($errorFound){
                continue;
            }
 
            $p = [
                'name' => trim($product->descripcio),
                'description' => trim($product->descripcio),
                'type' => 'simple',
                // $product->referencia,
                'regular_price' => floatval(str_replace(',', '', trim($product->precio1))),
                // $product->clase,
                // $product->foto,
                'sku' => trim($product->codigo),
            ];
            //// CATEGORIES
            $idCategory = intval(createOrUpdateCateogry( trim($product->nombre) ));
            //// CATEGORIES


            if(is_int($idCategory) && $idCategory != 0){
                $p["categories"] = [
                    ["id" => $idCategory]
                ];
            }

            echo createOrUpdateProduct($p);
            echo "\n";
        }

        if( count($errors) > 0 ){
            echo "ERRORES: ";
        }

        foreach ($errors as $key => $value) {
            echo "\n " . $value;
        }

    } catch (\Throwable $th) {
        echo 'ERROR ' . $th->getMessage();
    }
}


if( isset($_REQUEST["sync_databases"]) ){

    $db = new SQLite3($dbPath);
    try {

        $results = $db->query(" SELECT 
                id, created_at, updated_at, datos, status, result_process, woocomerce_product_id, sku  
            FROM posted_products  
            WHERE 1=1 
                AND (status = 'NEW' or status = 'UPDATED')
                or (woocomerce_product_id IS NULL OR TRIM(woocomerce_product_id) = '')
                AND status <> 'ERROR'
            LIMIT 15
        ");
        
        $productsToProcess = [];
        
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $productsToProcess[] = $row; // Almacena los registros a actualizar en un array
        }


        $msg = "";
        $db->exec("UPDATE posted_products SET status = 'PROCESSING', updated_at = CURRENT_TIMESTAMP, result_process = '$msg' WHERE id IN (".implode(",", array_column($productsToProcess, "id")).")");

        // $db->exec("UPDATE posted_products SET status = 'PROCESSED', updated_at = CURRENT_TIMESTAMP, result_process = '$msg' WHERE id = {$row['id']}");
        foreach ($productsToProcess  as $key => $row) {
            try {
                $expectedValue1 = "SKU: " . $row["sku"] . " - Producto creado exitosamente.";
                $expectedValue2 = "SKU: " . $row["sku"] . " - Producto actualizado exitosamente.";

                $msg = createOrUpdateProduct(json_decode($row["datos"], true));
                $statusProcess = "PROCESSED";
                if( $msg != $expectedValue1 && $msg != $expectedValue2 ){
                    $statusProcess = "ERROR";
                }

                echo $msg;
                echo "\n";
                $db->exec("UPDATE posted_products SET status = '$statusProcess', updated_at = CURRENT_TIMESTAMP, result_process = '$msg' WHERE id = {$row['id']}");
            } catch (\Throwable $e) {
                $errorMessage = SQLite3::escapeString($e->getMessage());
                $db->exec("UPDATE posted_products SET status = 'ERROR', result_process = '$errorMessage', updated_at = CURRENT_TIMESTAMP WHERE id = {$row['id']}");
            }
        }

    } catch (\Throwable $e) {
        ////////
    }

    $db->close();
    die("");
}


if(isset($_FILES) && count($_FILES) > 0){
    processFileOfProducts();
}else if(isset($_POST)){
    processPOSTOfProducts();
}

?>
