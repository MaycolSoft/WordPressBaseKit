<?php

define('MBSOFT_DATABASE_PATH', MBSOFT_PLUGIN_DIR . "database.sqlite");




////////////////////////////////////////////////////////
function connecttodatabase()
{
  $dbPath = MBSOFT_DATABASE_PATH;
  if (!is_writable(dirname($dbPath))) {
    die("El directorio " . dirname($dbPath) . " no es escribible.");
  }

  return new Sqlite3($dbPath);
}

function createAllTablesIfNotExists()
{
  try {
    $db = connecttodatabase();

    // Iniciar transacción
    $db->exec('BEGIN TRANSACTION');

    // Tabla throttle_control
    $db->exec("CREATE TABLE IF NOT EXISTS throttle_control (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          ip TEXT,
          timestamp INTEGER,
          request_count INTEGER
      )");

    // Tabla history_webhook
    $db->exec("CREATE TABLE IF NOT EXISTS history_webhook (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          method TEXT,
          body TEXT,
          params TEXT,
          authorization TEXT,
          ip TEXT,
          headers TEXT,
          timestamp DATETIME
      )");

    // Tablas de archivos (unificadas)
    $db->exec("CREATE TABLE IF NOT EXISTS files_data (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          webhook_id INTEGER,
          filename TEXT,
          filetype TEXT,
          filesize INTEGER,
          filecontent BLOB,
          FOREIGN KEY (webhook_id) REFERENCES history_webhook(id)
      )");

    // Tablas de productos y categorías
    $db->exec("CREATE TABLE IF NOT EXISTS posted_products (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          datos TEXT,
          status TEXT DEFAULT 'NEW',
          result_process TEXT DEFAULT '',
          woocomerce_product_id INTEGER,
          sku TEXT
      )");

    $db->exec("CREATE TABLE IF NOT EXISTS posted_categories (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          name TEXT,
          external_id TEXT,
          woocomerce_category_id INTEGER
      )");

    // Validar directorio escribible (una sola vez)
    $dbPath = MBSOFT_DATABASE_PATH;
    if (!is_writable(dirname($dbPath))) {
      throw new Exception("Directorio no escribible: " . dirname($dbPath));
    }

    $db->exec("
            CREATE TABLE IF NOT EXISTS files_info (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                webhook_id INTEGER NOT NULL,
                filename TEXT NOT NULL,
                filetype TEXT NOT NULL,
                filesize INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (webhook_id) REFERENCES history_webhook(id) ON DELETE CASCADE
            )
        ");

    // Crear índice para búsquedas por webhook
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_info_webhook ON files_info (webhook_id)");



    // Nueva tabla para features (funcionalidades)
    $db->exec("CREATE TABLE IF NOT EXISTS features (
      feature_code TEXT PRIMARY KEY,
      feature_name TEXT NOT NULL,
      description TEXT,
      is_active INTEGER DEFAULT 0 CHECK(is_active IN (0, 1)),
      default_active INTEGER DEFAULT 0 CHECK(default_active IN (0, 1)),
      settings TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME
    )");


    // Datos iniciales
    $initialFeatures = [
      [
        'custom_login',
        'Login Branding',
        'Personalización de la página de acceso',
        0,
        0,
        '{"css_file": "login-styles.css"}'
      ],
      [
        'hide_no_image_products',
        'Productos sin imagen',
        'Ocultar productos sin imagen principal',
        0,
        1,
        '{"post_types": ["product"]}'
      ],
      [
        'custom_shortcode',
        'Shortcode Personalizado',
        'Implementa [mbsoft_feature]',
        1,
        1,
        '{"shortcode": "mbsoft_feature"}'
      ],
      [
        'pay_bhd_float_button',
        'Botón de Pago BHD',
        'Muestra un botón flotante para hacer un pago en BHD. Utilza el shortcode [pay_bhd_float_button]',
        0,
        1,
        '{"shortcode": "pay_bhd_float_button"}'
      ]
    ];

    $stmt = $db->prepare("
        INSERT OR IGNORE INTO features 
        (feature_code, feature_name, description, is_active, default_active, settings)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($initialFeatures as $feature) {
      $stmt->bindValue(1, $feature[0], SQLITE3_TEXT);
      $stmt->bindValue(2, $feature[1], SQLITE3_TEXT);
      $stmt->bindValue(3, $feature[2], SQLITE3_TEXT);
      $stmt->bindValue(4, $feature[3], SQLITE3_INTEGER);
      $stmt->bindValue(5, $feature[4], SQLITE3_INTEGER);
      $stmt->bindValue(6, $feature[5], SQLITE3_TEXT);
      $stmt->execute();
    }

    $db->exec('COMMIT');
    $db->close();

    return MBSOFT_DATABASE_PATH;

  } catch (\Throwable $e) {
    $db->exec('ROLLBACK');
    die("Error creando tablas: " . $e->getMessage());
  }
}
////////////////////////////////////////////////////////



// Obtener información de la base de datos
$db_path = MBSOFT_DATABASE_PATH;
$db_exists = file_exists($db_path);

// Crear base de datos y tablas si no existen
if (!$db_exists) {
  createAllTablesIfNotExists();
}


