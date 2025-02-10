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
    <div id="container">
        <h1>Bienvenido a Mi Sitio Web </h1>
        <nav>
            <ul>
                <!-- <li><a href="tabla.php">Tabla</a></li> -->
                <li><a href="database.php">Base de Datos</a></li>
                <!-- <li><a href="webhook.php">Webhook</a></li> -->
                <li><a href="dropDownFiles.php">Archivos Desplegables</a></li>
            </ul>
        </nav>
    </div>
</body>
</html>
