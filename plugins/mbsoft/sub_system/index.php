<div class="btn-list">
  <button onclick="showMbSoftView('database')">Base de Datos</button>
  <button onclick="showMbSoftView('dropDownFiles')">Archivos</button>
</div>

<div id="view-html"></div>

<style>
  .btn-list {
    display: flex;
    gap: 8px;
    margin-bottom: 10px;
  }
  .btn-list button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background: #007bff;
    color: #fff;
    cursor: pointer;
  }
  .btn-list button:hover {
    background: #0056b3;
  }
</style>


<script>
  let ADMIN_AJAX = '<?= admin_url("admin-ajax.php"); ?>';
  let CURRENT_VIEW = '';

  async function showMbSoftView(view) {
    if (CURRENT_VIEW === view) return;
    CURRENT_VIEW = view;
    const container = document.getElementById('view-html');

    // Limpiar el contenedor (se remueven todos los nodos, incluidos los scripts)
    container.innerHTML = '<p>Cargando...</p>';

    try {
      const response = await fetch(ADMIN_AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'mbsoft_api_ajax',
          view: view
        })
      });

      const html = await response.text();
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = html;

      // Extraer scripts para ejecutarlos de forma separada
      const scripts = tempDiv.querySelectorAll('script');
      // Remover scripts del HTML para insertarlo sin ellos
      scripts.forEach(script => script.remove());
      container.innerHTML = tempDiv.innerHTML;

      // Eliminar cualquier script dinámico anterior (opcional si no se usara innerHTML para borrar)
      container.querySelectorAll('.dynamic-script').forEach(s => s.remove());

      // Ejecutar scripts inline y externos, marcándolos para poder eliminarlos luego
      scripts.forEach(script => {
        const newScript = document.createElement('script');
        newScript.classList.add('dynamic-script');
        if (script.src) {
          newScript.src = script.src;
          newScript.async = true;
        } else {
          newScript.textContent = script.textContent;
        }
        container.appendChild(newScript);
      });

      if (view === 'database') {
        setTimeout(initializeModal, 100);
      }
    } catch (error) {
      console.error('Error:', error);
      container.innerHTML = '<p>Error al cargar la vista.</p>';
    }
  }
</script>
