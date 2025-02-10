<?php

$fileId  = $_POST["id"];

function returnFileResponse($fileId) {
    try {
        $dbPathForFiles = dirname(__FILE__) . DIRECTORY_SEPARATOR . "files_database.sqlite";
        
        $db = new SQLite3($dbPathForFiles);

        $stmt = $db->prepare("SELECT * FROM files_data WHERE webhook_id = ?");
        $stmt->bindValue(1, $fileId, SQLITE3_INTEGER);
        $result = $stmt->execute();

        $file = $result->fetchArray(SQLITE3_ASSOC);

        if ($file) {
            return $file;
        } else {
            return false;
        }

    } catch (\Throwable $th) {
        echo $th->getMessage(); // Corregido de get_message() a getMessage()
    }finally{
        $db->close();
    }
}

// Función para cargar una imagen en WordPress desde la base de datos
function uploadImageFromDatabaseToWordPress($file_name, $file_content, $product_id, $mime_type) {

    $upload = wp_upload_bits($file_name, null, $file_content);

    if ($upload['error']) {
        return [
            "status" => false,
            "info" => $upload,
            "error" => $upload['error']
        ];
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

    // Añadir el ID del archivo adjunto a la galería de imágenes del producto
    $existing_gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);
    $updated_gallery_ids = $existing_gallery_ids ? $existing_gallery_ids . ',' . $attachment_id : $attachment_id;
    update_post_meta($product_id, '_product_image_gallery', $updated_gallery_ids);

    return [
        "success" => true,
        "status" => true,
    ];
}


function hasProductImage($product_id) {
    require_once('../wp-load.php');
    include(ABSPATH . 'wp-admin/includes/image.php');

    // Verificar si hay una imagen destacada
    $thumbnail_id = get_post_meta($product_id, '_thumbnail_id', true);
    
    // Verificar si hay imágenes en la galería
    $gallery_ids = get_post_meta($product_id, '_product_image_gallery', true);

    // Si hay una imagen destacada o imágenes en la galería, retorna verdadero
    if (!empty($thumbnail_id) || !empty($gallery_ids)) {
        return true;
    } else {
        return false;
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


try {

    $db = connectToDatabase();
    //////////////////////////////////////////////////////////////
    $fileInfoResut = $db->querySingle("SELECT * FROM files_info WHERE id = $fileId", true);
    
    if(!$fileInfoResut){
        echo json_encode([
            "message" => "NO EXISTE EL REGISTRO ($fileId)"
        ]);
        return;
    }
    //////////////////////////////////////////////////////////////





    //////////////////////////////////////////////////////////////
    $webhook_id = $fileInfoResut["webhook_id"];
    $webhookData = $db->querySingle("SELECT * FROM history_webhook WHERE id = $webhook_id", true);
    if(!$webhookData){
        echo json_encode([
            "message" => "NO EXISTE EL REGISTRO WEBHOOK ID ($webhook_id)"
        ]);
        return;   
    }

    $paramsWebHook = json_decode($webhookData["params"]);
    
    if(empty($paramsWebHook)){
        echo json_encode([
            "message" => "NO ES UN JSON VALIDO EN COLUMN params de WEBHOOK ID ($webhook_id)"
        ]);
        return;
    }
    //////////////////////////////////////////////////////////////
    
    


    //////////////////////////////////////////////////////////////
    $product = getProductFromDatabase($db, $paramsWebHook->codigo);
    
    if (empty($product)) {
        echo json_encode([
            "message" => "Producto ($paramsWebHook->codigo) no existe",
            "product" => $product
        ]);
        return;
    }

    if (empty($product["woocomerce_product_id"])) {
        echo json_encode([
            "message" => "El producto no esta subido en el sistema (woocomerce_product_id) está vacoo",
            "product" => $product
        ]);
        return;
    }

    $product_id = $product["woocomerce_product_id"];

    if(hasProductImage($product_id) &&  !isset($_POST["viewUploadedImage"])){
        echo json_encode([
            "message" => "EL PRODUCTO YA POSEE UNA IMAGEN"
        ]);
        return;
    }
    //////////////////////////////////////////////////////////////


    //////////////////////////////////////////////////////////////
    $file_data = returnFileResponse($fileInfoResut["webhook_id"]);
    if(!$file_data){
        echo json_encode([
            "message" => "NO EXISTE EL REGISOTR ($fileId)"
        ]);
        return;
    }
    //////////////////////////////////////////////////////////////


    if(isset($_POST["viewUploadedImage"])){
        function getImageMimeType($fileData) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            return $finfo->buffer($fileData);
        }

        // die(base64_encode($file_data['filecontent']));

        header('Content-Type: ' . getImageMimeType($file_data['filecontent']));
        echo $file_data['filecontent'];
        die();

        // header('Content-Type: image/jpeg'); // Cambia el tipo de contenido según el tipo de imagen
        // echo $file_data['filecontent'];
        // die();
    }

    $upload_result = uploadImageFromDatabaseToWordPress(
        $file_data['filename'],
        $file_data['filecontent'],
        $product_id,
        $file_data['filetype']
    );


    if($upload_result["status"]){
        echo json_encode([
            "status" => true,
            "message" => "LISTO",
            "data" => $upload_result
        ]);
    }else{
        echo json_encode([
            "status" => false,
            "message" => $upload_result["error"],
            "data" => $upload_result,
            "filecontent" => strlen($file_data['filecontent']),
            "filetype" => $file_data['filetype'],

        ]);
    }
    return;
} catch (\Throwable $th) {
    echo json_encode([
        "message" => $th->getMessage(),
        "trace" => "Trace: <pre>" . $th->getTraceAsString() . "</pre>"
    ]);
    return;
}finally{
    $db->close();
}
