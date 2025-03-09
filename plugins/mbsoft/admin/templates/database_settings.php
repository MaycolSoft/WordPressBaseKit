<?php
// database_settings.php

// Verificar seguridad
if (!defined('ABSPATH') || !current_user_can('manage_options'))
  exit;



function get_sqlite_tables() {
  $db = connecttodatabase();
  
  $result = $db->query("
      SELECT name 
      FROM sqlite_master 
      WHERE type = 'table' 
      AND name NOT LIKE 'sqlite_%'
  ");
  
  $tables = [];
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $tables[] = $row['name'];
  }
  
  $db->close();
  return $tables;
}

// Uso:
$tables = get_sqlite_tables();





// Procesar reset de tablas
if (isset($_POST['reset_table'])) {
  check_admin_referer('mbsoft_reset_table');
  $table_name = sanitize_key($_POST['table_name']);

  try {
    $db = connecttodatabase();

    $db->exec("DROP TABLE IF EXISTS $table_name");

    createAllTablesIfNotExists();
    $db->close();
    echo '<div class="notice notice-success"><p>Tabla reiniciada correctamente: ' . $table_name . '</p></div>';
  } catch (Exception $e) {
    echo '<div class="notice notice-error"><p>Error: ' . $e->getMessage() . '</p></div>';
  }
}

?>





<div class="mbsoft-database-settings">
  <h2>Ajustes de Base de Datos</h2>

  <div class="mbsoft-db-status">
    <div class="mbsoft-status-card">
      <h3>Información General</h3>
      <p>Ubicación: <code><?php echo esc_html(MBSOFT_DATABASE_PATH); ?></code></p>
      <p>Tamaño: <?php echo MBSOFT_DATABASE_PATH ? size_format(filesize(MBSOFT_DATABASE_PATH)) : 'No existe'; ?></p>
    </div>
  </div>

  <div class="mbsoft-tables-grid">
    <?php foreach ($tables as $table):
      $db = connecttodatabase();
      $count = $db->querySingle("SELECT COUNT(*) FROM $table");
      $db->close();
      ?>
      <div class="mbsoft-table-card">
        <div class="mbsoft-table-header">
          <h3><?php echo esc_html($table); ?></h3>
          <span class="mbsoft-table-count">Registros: <?php echo $count; ?></span>
        </div>

        <form method="post" onsubmit="return confirm('¿Estás seguro de querer reiniciar esta tabla?');">
          <?php wp_nonce_field('mbsoft_reset_table'); ?>
          <input type="hidden" name="table_name" value="<?php echo esc_attr($table); ?>">
          <button type="submit" name="reset_table" class="button button-danger">
            Reiniciar Tabla
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<style>
  .mbsoft-database-settings {
    max-width: 1200px;
    padding: 20px;
  }

  .mbsoft-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 2rem;
  }

  .mbsoft-table-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  }

  .mbsoft-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .mbsoft-table-count {
    background: #f6f7f7;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.9em;
  }

  .button-danger {
    background: #d63638;
    border-color: #d63638;
    color: white;
  }

  .button-danger:hover {
    background: #a6282a;
    border-color: #a6282a;
    color: white;
  }

  .mbsoft-status-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #ccd0d4;
    margin-bottom: 2rem;
  }
</style>

<script>
  // Confirmación antes de resetear
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[name="reset_table"]').forEach(button => {
      button.addEventListener('click', (e) => {
        if (!confirm(
          '¿Estás seguro de querer reiniciar esta tabla? Todos los datos se perderán permanentemente.'
        )) {
          e.preventDefault();
        }
      });
    });
  });
</script>
