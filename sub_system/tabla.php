<?php
header('Content-Type: application/json');

if (!isset($_GET['inputValue']) || $_GET['inputValue'] != 'mb') {
    echo json_encode(['error' => 'NO SE ENVIO LA CLAVE PARA ACCEDER AL SERVICIO']);
    die();
}


$scriptPath = __FILE__;
$dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";

if(!isset($_GET['name'])) {
    echo json_encode(['error' => 'No table name provided']);
    exit();
}

$tableName = $_GET['name'];
$db = new SQLite3($dbPath);

$_POST = file_get_contents('php://input');
$_POST = json_decode($_POST, true);

if(isset($_POST["remove"])){
    require_once "removeRecord.php";
    exit();
}

if(isset($_POST["update"])){
    require_once "updateStatusProduct.php";
    exit();
}

if(isset($_POST["reUploadImage"]) || isset($_POST["viewUploadedImage"])){
    require_once "reUploadImage.php";
    exit();
}


// Obtiene el esquema de la tabla
$schema = $db->query("PRAGMA table_info($tableName);");
$columns = [];
while ($column = $schema->fetchArray()) {
    $columns[] = $column['name'];
}


$where = " WHERE 1=1 ";
foreach ($_GET as $key => $value) {
    if(empty(trim($value)) || $key == "inputValue" || $key == "name" ){
        continue;
    }

    $where .= " AND UPPER($key) LIKE '%" . strtoupper($value) . "%'";
}


// Obtiene los datos de la tabla
$order_by = " ORDER BY id DESC;";
if( $tableName == "sqlite_sequence" ){
    $order_by = "";
}

$result = $db->query("SELECT * FROM $tableName $where $order_by ");
$rows = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

$db->close();

echo json_encode(['columns' => $columns, 'rows' => $rows]);
?>
