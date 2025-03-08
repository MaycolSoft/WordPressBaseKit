<?php

$requests_per_minute=600;

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "RequestHistory.php";
require_once "GeneralFunctions.php";


if(!isset($_FILES) || count($_FILES) == 0){
    return print_r(json_encode([
        "message" => "No se ha enviado ningun archivo"
    ]));
}


processFileOfProducts();



function processFileOfProducts(){
    try {
        $newResponse = [];
        $total_files = count($_FILES);

        foreach($_FILES as $key=>$value) {

            $contenido = file_get_contents( $value["tmp_name"] );
            $json = json_decode($contenido);

            ////////////////////////////////////////////////////
            $select_key = "select-$key";
            $fields = isset($_POST[$select_key])? json_decode($_POST[$select_key]): false;

            if($fields === NULL){
                if (isset($_POST[$select_key])) {
                    // Primero obtenemos el string JSON recibido
                    $jsonStr = $_POST[$select_key];
                    
                    // Muchas veces el string viene con barras invertidas que hacen fallar la decodificación.
                    // Usamos stripslashes para eliminarlas y obtener un JSON válido.
                    $jsonStrClean = stripslashes($jsonStr);
                    
                    $fields = json_decode($jsonStrClean, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // echo 'Error decodificando JSON en CAMPOS SELECCIONADOS: ' . json_last_error_msg();
                        $newResponse[] = [
                            "message" => "Error decodificando JSON en CAMPOS SELECCIONADOS: " . json_last_error_msg()
                        ];
                        continue;
                    }
                } else {
                    $fields = false;
                }
            }
            ////////////////////////////////////////////////////



            if($fields===false){
                $newResponse[] = [
                    "message" => "NO SE HA SELECCIONADO NINGUN CAMPO"
                ];
                continue;
            }


            if(!is_array($json)){
                $json = [$json];
            }

            $newArray = [];
            foreach ($json as $item) {
    
                $newItem = [];
                foreach ($fields as $key_select => $field) {
    
                    // Buscar en el objeto el campo $field
                    if (isset($item->$field)) {
                        $newItem[$key_select] = $item->$field;
                        // if($key_select === "categories"){
                        //     // $newItem[$key_select] = $item->$field;
                        //     continue;
                        // }else{
                        //     $newItem[$key_select] = $item->$field;
                        // }
                    }
    
                }
    
                if( empty($newItem) ){
                    continue;
                }
    
                try {
                    $newResponse[] = wrappedCreateOrUpdateProduct($newItem);
                } catch (\Throwable $th) {
                    $newResponse[] = json_encode([
                        "message" => $th->getMessage(),
                        "product" => $newItem
                    ]);
                }
            }
        }

        return print_r(json_encode($newResponse));

    } catch (\Throwable $e) {
       return print_r( json_encode([
            "message" => $e->getMessage()
        ]));
    }
}


function wrappedCreateOrUpdateProduct($product){
    try {

        $p = [
            'name'          => trim($product["name"]),
            'description'   => trim($product["description"]),
            'type'          => 'simple',
            'regular_price' => floatval(str_replace(',', '', trim($product["regular_price"]))),
            'sale_price'    => floatval(str_replace(',', '', trim($product["sale_price"]))),
            'sku'           => trim($product["sku"]),
            'existencia'    => intval(str_replace(',', '', $product["existencia"]))
        ];

        // print_r($p);
 



        //// CATEGORIES
        if(isset($product["categories"])){
            $idCategory = intval(createOrUpdateCateogry( trim($product["categories"]) ));
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
            return [
                "message" => "Al parecer no se pudo crear el producto, contacta al administrador para mas informacion"
            ];
        };


        $product_id = $localID["woocomerce_product_id"];


        ////////////////////////////////////////////////////////////////////
        if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_FILES["imagen"])) {
            // Respuesta en caso de solicitud no válida o no se haya enviado una imagen
            $response = [
                "mensaje"     => "Producto Creado o Actualizado exitosamente",
                "product_id"  => $product_id,
                "sku"         => $p["sku"],
                "nombre"      => $p["name"]
            ];

            return $response;
        }

        $imagen = $_FILES["imagen"];
        $temp_name = $imagen["tmp_name"];

        if (!isValidImage($temp_name)) {
            return ["message" => "El archivo no es una imagen válida."];
        }

        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        $image_info = getimagesize($temp_name);
        
        if (!in_array($image_info[2], $allowed_types)) {
            return ["message" => "Tipo de imagen no permitido. Solo se permiten JPEG, PNG y GIF."];
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
                        return [
                            "message" => "La imagen ya esta subida"
                        ];
                    }
                }
            }
        }


        move_uploaded_file($temp_name, $file_path);

        $upload = uploadImageToWordPress($file_name, $file_path, $product_id, $image_info["mime"]);

        if (isset($upload['error']) && $upload['error']) {
            return ["message"=> 'Error al subir la imagen: ' . $upload['error']];
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

    
        return $response;
    
    } catch (\Throwable $th) {
        return [
            "message" => $th->getMessage(),
            "product" => $p
        ];
    }
    
}



