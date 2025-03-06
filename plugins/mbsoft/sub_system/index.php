<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tu Sitio Web</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        #container {
            width: 50%;
            margin: auto;
        }

        h1 {
            text-align: center;
            padding: 50px 0;
            color: #333;
        }

        nav {
            background-color: #fff;
            box-shadow: 0px 0px 10px 0px #ccc;
            padding: 10px;
        }

        nav ul {
            padding: 0;
            margin: 0;
            list-style: none;
            text-align: center; /* Centrar los elementos de la lista */
        }

        nav ul li {
            display: inline-block; /* Cambiar a inline-block para permitir padding */
            margin-right: 20px;
            border-radius: 5px; /* Bordes redondeados */
            transition: background-color 0.5s ease-in-out; /* Transición suave de color de fondo */
        }

        nav ul li:hover {
            background-color: #e9ecef; /* Color de fondo al pasar el mouse */
        }

        nav ul li a {
            text-decoration: none;
            color: #333;
            padding: 10px 20px; /* Espaciado interno para los enlaces */
            display: block; /* Hacer que el enlace ocupe todo el espacio del li */
            transition: color 0.5s ease-in-out;
        }

        nav ul li a:hover {
            color: #007bff;
        }
    </style>
</head>
<body>
  <script>
    const ADMIN_AJAX = '<?= admin_url("admin-ajax.php"); ?>';

    function showMbSoftView(view) {
      fetch(ADMIN_AJAX, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
              'action': 'mbsoft_api_ajax',
              'view': view
          })
      })
      .then(response => response.text())
      .then(html => {
          const container = document.getElementById('view-html');
          
          // Extraer y ejecutar scripts manualmente
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = html;
          
          // Insertar el contenido sin los scripts
          container.innerHTML = tempDiv.innerHTML.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');

          // Ejecutar scripts inline y externos
          tempDiv.querySelectorAll('script').forEach(script => {
              const newScript = document.createElement('script');
              if (script.src) {
                  newScript.src = script.src;
                  newScript.async = true;
              } else {
                  newScript.textContent = script.textContent;
              }
              document.body.appendChild(newScript);
          });

          if(view === 'database') {
              setTimeout(initializeModal, 100);
          }

      })
      .catch(error => console.error('Error:', error));
    }

  </script>
  <h1>Bienvenido a Mi Sitio Web </h1>
  <nav>
    <ul>
      <li><a href="javascript:void(0)" onclick="showMbSoftView('database')">Base de Datos</a></li>
      <li><a href="javascript:void(0)" onclick="showMbSoftView('dropDownFiles')">Archivos Desplegables</a></li>
    </ul>
  </nav>
  <hr>
  <div id="view-html"></div>
</body>
</html>
