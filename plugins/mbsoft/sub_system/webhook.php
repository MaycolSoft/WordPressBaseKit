<?php

require_once "RequestHistory.php";

if( 
    ( isset($argv[1]) && $argv[1] === "sync_databases") || 
    ( isset($_REQUEST["sync_databases"]) && $_REQUEST["sync_databases"] == 1 ) 
    ){
    $_REQUEST["sync_databases"] = 1;
}
 
require_once "createOrUpdateProduct.php";
?>
