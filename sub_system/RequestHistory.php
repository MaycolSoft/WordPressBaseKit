<?php 
date_default_timezone_set('America/Santo_Domingo'); 

////////////////////////////////////////////////////////////////////////////////
$scriptPath = __FILE__;
$dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";

$requiring_file = "GENERIC";
if(isset($_SERVER["SCRIPT_FILENAME"]) || !empty($_SERVER["SCRIPT_FILENAME"])){
    $requiring_file = basename($_SERVER["SCRIPT_FILENAME"]);
    $requiring_file = str_replace(".php", "", $requiring_file);
}

function createTableIfNotExistsThrottle() {
    try {
        global $dbPath;

        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);

        // Define la sentencia SQL para crear la tabla si no existe
        $sql = "CREATE TABLE IF NOT EXISTS throttle_control (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT,
            timestamp INTEGER,
            request_count INTEGER
        )";

        // Ejecuta la sentencia SQL
        $db->exec($sql);

        $sql = "CREATE TABLE IF NOT EXISTS history_webhook (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            method TEXT,
            body TEXT,
            params TEXT,
            authorization TEXT,
            ip TEXT,
            headers TEXT,
            timestamp DATETIME
        )";

        $db->exec($sql);

        // Cierra la conexión a la base de datos
        $db->close();

        // echo "La tabla se creó correctamente o ya existía.";
    } catch (\Throwable $e) {
        die("Error al crear la tabla: " . $e->getMessage());
    }
}

function createFilesInfoTable() {
    global $dbPath;
    $db = new SQLite3($dbPath);

    $sql = "CREATE TABLE IF NOT EXISTS files_info (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        webhook_id INTEGER,
        filename TEXT,
        filetype TEXT,
        filesize INTEGER,
        FOREIGN KEY (webhook_id) REFERENCES history_webhook(id)
    )";

    $db->exec($sql);
    $db->close();
}

function createFilesDatabaseAndTable() {
    $dbPathForFiles = dirname(__FILE__) . DIRECTORY_SEPARATOR . "files_database.sqlite";

    $db = new SQLite3($dbPathForFiles);

    // Crea una nueva tabla para almacenar la información de los archivos
    $sql = "CREATE TABLE IF NOT EXISTS files_data (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        webhook_id INTEGER,
        filename TEXT,
        filetype TEXT,
        filesize INTEGER,
        filecontent BLOB
    )";

    $db->exec($sql);
    $db->close();

    return $dbPathForFiles; // Devuelve la ruta de la nueva base de datos para su uso posterior
}

function createTableIfNotExistsProducts() {
    try {
        global $dbPath;
        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);



        // Define la sentencia SQL para crear la tabla si no existe
        $sql = "CREATE TABLE IF NOT EXISTS posted_products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            datos TEXT,
            status TEXT DEFAULT 'NEW',
            result_process TEXT DEFAULT '',
            woocomerce_product_id INTEGER,
            sku TEXT
        )";
        
        $db->exec($sql);

        // Define la sentencia SQL para crear la tabla si no existe
        $sql = "CREATE TABLE IF NOT EXISTS posted_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            name TEXT,
            external_id TEXT,
            woocomerce_category_id INTEGER
        )";

        // Ejecuta la sentencia SQL
        $db->exec($sql);

        // Cierra la conexión a la base de datos
        $db->close();

        // echo "La tabla se creó correctamente o ya existía.";
    } catch (Exception $e) {
        // echo "Error al crear la tabla: " . $e->getMessage();
    }
}

createTableIfNotExistsProducts();
createFilesInfoTable();
createTableIfNotExistsThrottle();
$dbPathForFiles = createFilesDatabaseAndTable();
////////////////////////////////////////////////////////////////////////////////




////////////////////////////////////////////////////////////////////////////////
function throttleControl(){
    global $dbPath, $requests_per_minute;
    if(!isset($requests_per_minute)){
        // Límite de solicitudes por minuto
        $requests_per_minute = 10;
    }

    // Obtén la IP del cliente y el timestamp actual
    $ip = $_SERVER['REMOTE_ADDR'];
    $current_time = time();

    // Abre la conexión a la base de datos SQLite
    $db = new SQLite3($dbPath);

    // Busca un registro existente para la IP del cliente
    $sql = "SELECT * FROM throttle_control WHERE ip = '$ip'";
    $result = $db->querySingle($sql, true);

    if ($result) {
        if($current_time - $result['timestamp'] < 60) {
            // Si el registro existente es dentro del último minuto, incrementa el contador de solicitudes
            $request_count = $result['request_count'] + 1;
            $sql = "UPDATE throttle_control SET request_count = $request_count WHERE id = " . $result['id'];
            $db->exec($sql);
        } else {
            // Si el registro existente es más antiguo que un minuto, reinicia el contador de solicitudes y actualiza el timestamp
            $sql = "UPDATE throttle_control SET request_count = 1, timestamp = $current_time WHERE id = " . $result['id'];
            $db->exec($sql);
            $request_count = 1;
        }
    } else {
        // Si no se encuentra un registro, crea uno nuevo
        $sql = "INSERT INTO throttle_control (ip, timestamp, request_count) VALUES ('$ip', $current_time, 1)";
        $db->exec($sql);
        $request_count = 1;
    }

    // Si se ha superado el límite de solicitudes por minuto, devuelve un error 429
    if ($request_count > $requests_per_minute) {
        http_response_code(429);
        echo 'Límite de solicitudes excedido. Intente nuevamente más tarde.';
        $db->close();
        exit;
    }

    $db->close();
}


function saveFilesInfo($webhookId) {
    global $dbPath;
    if (!empty($_FILES)) {
        $db = new SQLite3($dbPath);

        foreach ($_FILES as $file) {
            $filename = $file['name'];
            $filetype = $file['type'];
            $filesize = $file['size'];

            $sql = "INSERT INTO files_info (webhook_id, filename, filetype, filesize) VALUES ('$webhookId', '$filename', '$filetype', '$filesize')";
            $db->exec($sql);
        }

        $db->close();
    }
}


function saveFilesData($webhookId, $dbPath) {
    if (empty($_FILES)) {
        return;
    }

    $db = new SQLite3($dbPath);

    foreach ($_FILES as $file) {
        $filename = $file['name'];
        $filetype = $file['type'];
        $filesize = $file['size'];
        $fileContent = file_get_contents($file['tmp_name']); // Lee el contenido binario

        // Prepara e inserta la información del archivo en la base de datos
        $stmt = $db->prepare("INSERT INTO files_data (webhook_id, filename, filetype, filesize, filecontent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $webhookId, SQLITE3_INTEGER);
        $stmt->bindValue(2, $filename, SQLITE3_TEXT);
        $stmt->bindValue(3, $filetype, SQLITE3_TEXT);
        $stmt->bindValue(4, $filesize, SQLITE3_INTEGER);
        $stmt->bindValue(5, $fileContent, SQLITE3_BLOB);
        $stmt->execute();
    }

    $db->close();
}

function saveHistory(){
    global $dbPath, $requiring_file;
    // Abre la conexión a la base de datos SQLite
    $db = new SQLite3($dbPath);

    // Prepara los datos de la solicitud
    $method = $_SERVER['REQUEST_METHOD'];
    $body = file_get_contents('php://input');
    $params = json_encode($_REQUEST);
    $authorization = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    $authorization = empty($authorization)? $requiring_file: "$requiring_file - $authorization";
    // $requiring_file
    // var_dump($authorization);
    // die();
    $ip = $_SERVER['REMOTE_ADDR'];
    $timestamp = date('Y-m-d H:i:s');

    // Recupera el encabezado HTTP y lo convierte en formato JSON
    $headers = json_encode(getallheaders());

    // Inserta un nuevo registro en la tabla history_webhook, incluyendo el encabezado
    $sql = "INSERT INTO history_webhook (method, body, params, authorization, ip, timestamp, headers) VALUES ('$method', '$body', '$params', '$authorization', '$ip', '$timestamp', '$headers')";
    $db->exec($sql);
    $lastInsertId = $db->lastInsertRowID(); // Obtener el ID de la inserción
    $db->close();

    return $lastInsertId; // Devolver el ID para su uso posterior
}



if (!function_exists('activeDebug')) {
    function activeDebug(){
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }
}


// Evitar procesamiento de solicitud cuando se llama desde CLI
if (PHP_SAPI === 'cli') {
    // No hacer nada cuando se llama desde CLI
    return;
}


////////////////////////////////////////////////////////////////////////////////
try {
    
    throttleControl();
    $webhookId = saveHistory(); // Guarda la solicitud y obtén el ID
    saveFilesInfo($webhookId); // Guarda la información de los archivos con el ID de la solicitud
    saveFilesData($webhookId, $dbPathForFiles);


    if( isset($_REQUEST["active_debug"]) ){
        activeDebug();
    }

} catch (\Throwable $th) {
    // echo $th->getMessage();
}



