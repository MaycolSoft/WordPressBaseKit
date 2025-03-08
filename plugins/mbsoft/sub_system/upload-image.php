<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if(empty($_POST)){
    die();
}

$requests_per_minute=600;
require_once "RequestHistory.php";
require_once "GeneralFunctions.php";

$scriptPath = __FILE__;
$dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";

try {


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
        'name'          => trim($_POST["descripcion"]),
        'description'   => trim($_POST["descripcion"]),
        'type'          => 'simple',
        'regular_price' => floatval(str_replace(',', '', trim($_POST["precio"]))),
        'sku'           => trim($_POST["codigo"]),
        'existencia'    => intval(str_replace(',', '', $_POST["existencia"]))
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
    die(json_encode([
        "message" => $th->getMessage()
    ]));
}


