
<?php

require_once MBSOFT_PLUGIN_DIR . 'includes/database-functions.php';
// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['mbsoft_features_nonce']) || 
        !wp_verify_nonce($_POST['mbsoft_features_nonce'], 'mbsoft_features_update')) {
        die('Verificación de seguridad fallida');
    }
    
    $db = connecttodatabase();
    $currentStates = $_POST['features'] ?? [];
    
    // Obtener todas las features disponibles
    $allFeatures = $db->query("SELECT feature_code FROM features");
    $validFeatures = [];
    while ($row = $allFeatures->fetchArray(SQLITE3_ASSOC)) {
        $validFeatures[] = $row['feature_code'];
    }
    
    // Preparar la actualización
    $stmt = $db->prepare("UPDATE features SET is_active = :state WHERE feature_code = :code");
    
    foreach ($validFeatures as $featureCode) {
        $state = isset($currentStates[$featureCode]) ? 1 : 0;
        
        // Validar código de feature
        if (!preg_match('/^[a-z0-9_]+$/', $featureCode)) {
            continue;
        }
        
        $stmt->bindValue(':state', $state, SQLITE3_INTEGER);
        $stmt->bindValue(':code', $featureCode, SQLITE3_TEXT);
        $stmt->execute();
    }
    
    $db->close();
    
    // Redirigir para evitar reenvío del formulario
    wp_redirect(add_query_arg('updated', 'true'));
    exit;
}
?>







<?php


// En tu archivo PHP que muestra la interfaz admin
$db = connecttodatabase();
$features = $db->query("SELECT * FROM features");
?>

<div class="mbsoft-admin-wrap">
    <form method="post" id="mbsoft-features-form">
        <?php wp_nonce_field('mbsoft_features_update', 'mbsoft_features_nonce'); ?>
        <div class="mbsoft-mini-grid">
            <?php while ($feature = $features->fetchArray(SQLITE3_ASSOC)) : ?>
                <div class="mbsoft-mini-card">
                    <div class="mbsoft-card-compact">
                        <h4 class="mbsoft-card-microtitle"><?= htmlspecialchars($feature['feature_name']) ?></h4>
                        <p class="mbsoft-card-nano"><?= htmlspecialchars($feature['description']) ?></p>
                        <label class="mbsoft-switch-mini">
                            <input type="checkbox" 
                                name="features[<?= $feature['feature_code'] ?>]"
                                <?= $feature['is_active'] ? 'checked' : '' ?>>
                            <span class="mbsoft-switch-track"></span>
                        </label>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <input type="submit" class="button button-primary" value="Guardar cambios" style="margin-top: 20px;">
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mbsoft-features-form');
    
    // Manejador de eventos para todos los switches
    form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Agregar feedback visual opcional
            const originalText = form.parentNode.querySelector('.mbsoft-saving-feedback');
            const feedback = document.createElement('div');
            feedback.className = 'mbsoft-saving-feedback';
            feedback.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #2271b1; color: white; padding: 10px 20px; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
            feedback.textContent = 'Guardando cambios...';
            form.parentNode.appendChild(feedback);
            
            // Enviar formulario
            form.submit();
            
            // Opcional: Remover feedback después de 2 segundos
            setTimeout(() => feedback.remove(), 2000);
        });
    });
});
</script>

<style>
  /* Estilos principales con prefijo mbsoft- */
  .mbsoft-admin-wrap {
    padding: 0rem;
    margin: 0 auto;
  }

  .mbsoft-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
  }

  .mbsoft-mini-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    overflow: hidden;
  }

  .mbsoft-mini-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }

  .mbsoft-card-compact {
    padding: 1.5rem;
    position: relative;
  }

  .mbsoft-card-microtitle {
    font-size: 0.95rem;
    color: #1a1a1a;
    margin: 0 0 0.4rem;
    font-weight: 600;
    line-height: 1.3;
  }

  .mbsoft-card-nano {
    font-size: 0.82rem;
    color: #666666;
    margin: 0;
    line-height: 1.5;
  }

  /* Estilos para el switch personalizado */
  .mbsoft-switch-mini {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    display: inline-block;
    width: 40px;
    height: 22px;
  }

  .mbsoft-switch-mini input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .mbsoft-switch-track {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cccccc;
    transition: .2s;
    border-radius: 11px;
  }

  .mbsoft-switch-track:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .2s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  input:checked+.mbsoft-switch-track {
    background-color: #2271b1;
  }

  input:checked+.mbsoft-switch-track:before {
    transform: translateX(18px);
  }

  /* Estado deshabilitado */
  .mbsoft-switch-mini input:disabled+.mbsoft-switch-track {
    opacity: 0.6;
    cursor: not-allowed;
  }

  /* Efecto de foco accesible */
  .mbsoft-switch-mini input:focus-visible+.mbsoft-switch-track {
    box-shadow: 0 0 0 2px #2271b155;
  }
</style>