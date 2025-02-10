<?php

try {

    $id = $_POST["id"];
    $db = new SQLite3($dbPath);

    $db->busyTimeout(5000);
    $db->exec("UPDATE posted_products SET status = 'UPDATED', updated_at = CURRENT_TIMESTAMP  WHERE id = $id");

    echo json_encode(['message' => "UPDATED"]);
} catch (\Throwable $th) {
    echo json_encode(['error' => $th->getMessage()]);
}finally{
    $db->close();
}

