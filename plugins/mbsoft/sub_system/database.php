
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>HISTORY CONSULT</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            /* margin: 20px; */
            background-color: #f4f4f4;
            color: #333;
        }

        h1 {
            text-align: center;
            color: #2C3E50;
            margin-bottom: 20px;
        }

        .table-list, #tableData {
            width: 98%;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.1);
            padding: 5px;
            border-radius: 8px; /* Esquinas redondeadas */
        }

        ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
        }

        li {
            margin-bottom: 10px;
            transition: transform 0.3s ease;
        }

        li:hover {
            transform: scale(1.1); /* Agrandar elemento al pasar el cursor */
        }

        button {
            background-color: #4CAF50; /* Verde */
            border: none;
            color: white;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px; /* Esquinas redondeadas */
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        button:hover {
            background-color: #45a049;
            transform: translateY(-5px); /* Mover hacia arriba al pasar el cursor */
        }

        /* Estilos para la tabla */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border: 1px solid #ddd;
            animation: fadeIn 0.5s ease; /* Aplica la animación de entrada */
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            transition: background-color 0.3s ease; /* Animación de fondo al pasar el cursor */
        }

        th {
            background-color: #4CAF50;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:hover {
            background-color: #ddd;
        }

        /* Animación de entrada de tabla */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }




        /* Estilo del botón de cierre (x) */
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute; /* Ajustar la posición */
            top: 10px; /* Ajustar la posición en la parte superior */
            right: 20px; /* Ajustar la posición en el lado derecho */
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        /* Estilo del Modal (fondo) */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
        }

        /* Estilo del contenedor del modal */
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto; /* 15% from the top and centered */
            padding: 20px;
            border: 1px solid #888;
            width: 80%; /* Could be more or less, depending on screen size */
            position: relative; /* Ajustar la posición relativa */
        }

        @media (max-width: 600px) {
            .modal-content {
                width: 95%; /* Aumenta el ancho del modal */
                font-size: 14px; /* Ajusta el tamaño del texto */
            }

            .close {
                font-size: 22px; /* Ajusta el tamaño del botón de cierre */
                top: 5px; /* Ajustar la posición en la parte superior */
                right: 10px; /* Ajustar la posición en el lado derecho */
            }
        }


    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        #filterForm {
            animation: fadeIn 0.5s ease;
        }
        #filterForm button:hover {
            background-color: #45a049; /* Cambiar color de fondo al pasar el cursor */
            transform: translateY(-3px); /* Mover hacia arriba al pasar el cursor */
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

    </style>
    <style>
        #filterForm {
            display: flex;
            flex-wrap: wrap; /* Permite que los elementos del formulario se envuelvan en la siguiente línea */
            gap: 10px; /* Espacio entre cada fila y columna */
            animation: fadeIn 0.5s ease;
        }

        #filterForm div {
            flex: 1 0 45%; /* Cada div tomará el 45% del espacio total, permitiendo dos divs por fila con un pequeño espacio entre ellos */
            display: flex;
            flex-direction: column; /* Organiza label e input en una columna */
        }

        #filterForm label,
        #filterForm input {
            width: 100%; /* Ocupar todo el ancho disponible del div contenedor */
        }

        #filterForm button {
            width: 100%; /* Ocupar todo el ancho disponible */
            padding: 10px;
            margin-top: 20px; /* Margen superior para separar el botón de los inputs */
            cursor: pointer;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        #filterForm button:hover {
            background-color: #45a049;
            transform: translateY(-3px);
        }
        #dinamicForm {
            display: none; /* Oculta el formulario por defecto */
            /* Otros estilos del formulario... */
        }

    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/toastr.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css" rel="stylesheet">

</head>
<body>

    <!-- Modal -->
    <div id="myModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <p>Por favor, ingresa el valor requerido:</p>
            <input type="text" id="modalInput" name="modalInput">
        </div>
    </div>

    <div id="myModal-imagen-preview" class="modal">
        <span class="close" 
        onclick="document.getElementById('imagen-preview').innerHTML=''; document.getElementById('myModal-imagen-preview').style.display='none'"
        >&times;</span>
        <div class="modal-content">
            <div id="imagen-preview"></div>
        </div>
    </div>

    <h1>HISTORY CONSULT</h1>
    <style>
        .table-list {
            text-align: center;
            position: -webkit-sticky;
            position: sticky;
            top: 0;
        }

        .table-list  h2 {
            margin: 0;
        }

        .table-list li{
            margin: 0;
            padding: 0;
        }
    </style>

    <!-- style="position: -webkit-sticky; position: sticky; top: 0; " -->
    <div>
        <div class="table-list" style="position: -webkit-sticky;position: sticky;top: 0;">
            <h2>Lista de Tablas</h2>
            
            <ul id="tableList">
                <?php
                $scriptPath = __FILE__;
                $dbPath = dirname($scriptPath) . DIRECTORY_SEPARATOR . "database.sqlite";
                $db = new SQLite3($dbPath);

                $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table';");

                while ($table = $tables->fetchArray()) {
                    $tableName = $table['name'];
                    echo "<li><button onclick='showTable(\"$tableName\")'>$tableName</button></li>";
                }

                $db->close();
                ?>
            </ul>
        </div>

        <div style="display: flex;">
            <button onclick="toggleFormButton()" style="background-color: yellow; color: black; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s, color 0.3s;" onmouseover="this.style.backgroundColor='orange'" onmouseout="this.style.backgroundColor='yellow'">Mostrar Filtros</button>    
            <button onclick="checkUncheckAll()" style="background-color: blue; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s, color 0.3s;" onmouseover="this.style.backgroundColor='lightblue'" onmouseout="this.style.backgroundColor='blue'">CHECK ALL / UNCHECK ALL</button>
            <button id="btnRemoveRecordsCheked" onclick="removeRecordsCheked()" style="display:none; background-color: red; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s, color 0.3s;" >REMOVE CHEKED RECORDS</button>
            <button 
                id="btnReUploadAllImage" 
                onclick="reUploadAllImage()" 
                style="display:none; background-color: red; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.3s, color 0.3s;" 
            >
                Sync All Image
            </button>
        </div>
    </div>

    <div id="dinamicForm" style="display: none"></div>
    <div id="tableData"></div>

    <script>
        function toggleFormButton () {
            const form = document.getElementById("dinamicForm");
            if (form.style.display === "none") {
                form.style.display = "block";
                this.textContent = "Ocultar Filtros"; // Cambia el texto del botón a "Ocultar Filtros"
            } else {
                form.style.display = "none";
                this.textContent = "Mostrar Filtros"; // Cambia el texto del botón a "Mostrar Filtros"
            }
        }

        function initializeModal() {

            // Obtén el modal
            const _modal_auth = document.getElementById("myModal");
            
            document.getElementById("modalInput").addEventListener("keyup", function(event) {
                if (event.key === "Enter") {
                    _modal_auth.style.display = "none";
                }
            });
            // // Obtén el elemento <span> que cierra el modal
            // var span = document.getElementsByClassName("close")[0];
    
            // // Cuando el usuario hace clic en <span> (x), cierra el modal
            // span.onclick = function() {
            //     _modal_auth.style.display = "none";
            // }
    
            // // Cuando el usuario hace clic en cualquier lugar fuera del modal, ciérralo
            // window.onclick = function(event) {
            //     if (event.target == _modal_auth) {
            //         _modal_auth.style.display = "none";
            //     }
            // }
    
            // Muestra el modal cuando se carga la página
            // window.onload = function() {
            // }
            _modal_auth.style.display = "block";
        }




        let tableNameSelected = "";
        

        function reUploadAllImage() {
            const buttons = document.getElementsByClassName("button-reupload-files");
            let index = 0; // Variable para rastrear el índice actual del botón
            let batchSize = 5; // Número de botones a procesar en cada iteración

            // Función para hacer clic en un lote de botones
            const clickButtonsBatch = () => {
                const endIndex = Math.min(index + batchSize, buttons.length); // Calcular el índice final del lote
                for (let i = index; i < endIndex; i++) {
                    buttons[i].click(); // Hacer clic en cada botón en el lote
                }
                index = endIndex; // Actualizar el índice para el próximo lote
                if (index >= buttons.length) {
                    clearInterval(intervalId); // Detener el temporizador si ya clicamos en todos los botones
                }
            }

            var N = 5000; // N segundos (en milisegundos)
            var intervalId = setInterval(clickButtonsBatch, N); // Configurar el temporizador para ejecutar la función cada N segundos
        }

        function removeRecordsCheked() {
            if(tableNameSelected == "posted_products"){
                // alert("Solo se eliminaran 10 registros.");
                SELECTED_RECORDS.slice(0, 9).forEach((v) => {
                    removeRecord(v.tableName, v.id);
                    const fila = document.getElementById(`id-tr-${v.id}`); // Obtén la fila por su ID
                    if (fila) {
                        fila.parentNode.removeChild(fila); // Elimina la fila del DOM
                    }
                });
                return;
            }
            SELECTED_RECORDS.forEach((v)=>{
                removeRecord(v.tableName, v.id);
                const fila = document.getElementById(`id-tr-${v.id}`); // Obtén la fila por su ID
                if (fila) {
                    fila.parentNode.removeChild(fila); // Elimina la fila del DOM
                }
            });
        }
        // Función para remover el registro
        async function removeRecord(tableName, id) {
            try {
                // Datos que deseas enviar en el cuerpo de la solicitud (en este caso, un objeto JSON)
                const data = {
                    "remove": "1",
                    "name": tableName,
                    "id": id
                };
    
                // Configuración de la solicitud
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json' // Tipo de contenido del cuerpo de la solicitud
                    },
                    body: JSON.stringify(data) // Convierte el objeto JavaScript en una cadena JSON
                };


                const inputValue = document.getElementById("modalInput").value;


                // Realizar la solicitud POST
                const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                    'method': 'POST',
                    'headers': { 'Content-Type': 'application/x-www-form-urlencoded' },
                    'body': new URLSearchParams({
                        'action': 'mbsoft_api_ajax',
                        'name': tableName,
                        'id': id
                    })
                });
                // const response = await fetch(`<?= MBSOFT_PLUGIN_DIR;?>/sub_system/tabla.php?name=${tableName}&inputValue=${inputValue}`, options);
                const responseData = await response.json();

                if (responseData.error) {
                    return alert(responseData.error);
                }

                console.log(responseData);
            } catch (error) {
                console.log(error);
            }
        }

        async function updateRecord(tableName, id){
            try {
                // Datos que deseas enviar en el cuerpo de la solicitud (en este caso, un objeto JSON)
                const data = {
                    "update": "1",
                    "name": tableName,
                    "id": id
                };
    
                // Configuración de la solicitud
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json' // Tipo de contenido del cuerpo de la solicitud
                    },
                    body: JSON.stringify(data) // Convierte el objeto JavaScript en una cadena JSON
                };


                const inputValue = document.getElementById("modalInput").value;


                // Realizar la solicitud POST
                const response = await fetch(`<?= MBSOFT_PLUGIN_DIR;?>/sub_system/tabla.php?name=${tableName}&inputValue=${inputValue}`, options);
                const responseData = await response.json();

                if (responseData.error) {
                    console.log(responseData.error);
                    return responseData.error;
                }

                console.log(responseData);
            } catch (error) {
                console.log(error);
            }
        }

        async function reUploadImage(tableName, id){
            try {
                // Datos que deseas enviar en el cuerpo de la solicitud (en este caso, un objeto JSON)
                const data = {
                    "reUploadImage": "1",
                    "name": tableName,
                    "id": id
                };

                // Configuración de la solicitud
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json' // Tipo de contenido del cuerpo de la solicitud
                    },
                    body: JSON.stringify(data) // Convierte el objeto JavaScript en una cadena JSON
                };


                const inputValue = document.getElementById("modalInput").value;


                // Realizar la solicitud POST
                const response = await fetch(`<?= MBSOFT_PLUGIN_DIR;?>/sub_system/tabla.php?name=${tableName}&inputValue=${inputValue}`, options);
                const responseData = await response.json();

                if (responseData.error) {
                    console.log(responseData.error);
                    return responseData.error;
                }

                if(responseData?.status == false){
                    toastr.error(responseData?.message);
                }else if(responseData?.message){
                    toastr.info(responseData?.message);
                }

                console.log(responseData);
            } catch (error) {
                console.log(error);
            }
        }

        async function viewUploadedImage(tableName, id) {
            try {
                // Datos que deseas enviar en el cuerpo de la solicitud (en este caso, un objeto JSON)
                const data = {
                    "viewUploadedImage": "1",
                    "name": tableName,
                    "id": id
                };

                // Configuración de la solicitud
                const options = {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json' // Tipo de contenido del cuerpo de la solicitud
                    },
                    body: JSON.stringify(data) // Convierte el objeto JavaScript en una cadena JSON
                };

                const inputValue = document.getElementById("modalInput").value;

                // Realizar la solicitud POST
                const response = await fetch(`<?= MBSOFT_PLUGIN_DIR;?>/sub_system/tabla.php?name=${tableName}&inputValue=${inputValue}`, options);

                if (!response.ok) {
                    throw new Error('Error al obtener la imagen');
                }

                // Convertir la respuesta en un blob (objeto binario)
                const imageData = await response.blob();

                // Crear una URL para la imagen blob, que puede ser utilizada como src en un elemento de imagen
                const imageUrl = URL.createObjectURL(imageData);

                // Crear un elemento de imagen
                const imageElement = document.createElement('img');

                // Establecer el atributo src del elemento de imagen con la URL de la imagen
                imageElement.src = imageUrl;

            
                 // Eliminar cualquier imagen anterior en el modal
                const modalPreview = document.getElementById("imagen-preview");
                modalPreview.innerHTML = '';
 
                // Agregar el elemento de imagen al contenedor del modal
                modalPreview.appendChild(imageElement);

                // Abrir el modal
                const modal = document.getElementById("myModal-imagen-preview");
                modal.style.display = "block";

                return imageUrl;
            } catch (error) {
                console.error('Error:', error.message);
                return null; // Retorna null en caso de error para indicar que no se pudo obtener la imagen
            }
        }


        function checkUncheckAll() {
            const checkboxes = document.querySelectorAll("#tableData input[type='checkbox']");
            let checkAll = true;

            checkboxes.forEach(function(checkbox) {
                if (!checkbox.checked) {
                    checkAll = false;
                }
            });

            checkboxes.forEach(function(checkbox) {
                checkbox.checked = !checkAll;
                const event = new Event('change');
                checkbox.dispatchEvent(event);
            });
        }

        // Array para almacenar los registros seleccionados
        let SELECTED_RECORDS = [];

        // Función que quieres ejecutar cuando selectedRecords.length > 0
        function showDeleteButtonCheked() {
            document.getElementById("btnRemoveRecordsCheked").text = `REMOVER ${SELECTED_RECORDS.length}`;
            if (SELECTED_RECORDS.length > 0) {
                document.getElementById("btnRemoveRecordsCheked").style.display = "block";
            }else{
                document.getElementById("btnRemoveRecordsCheked").style.display = "none";
            }
        }
 

        async function showTable(tableName, filters) {
            let sameTable = (tableNameSelected == tableName);

            tableNameSelected = tableName;
            let inputValue = document.getElementById("modalInput").value;

            //////////////////////////////
            document.getElementById("btnReUploadAllImage").style.display = 'none';
            if( tableNameSelected == "files_info" ){
                document.getElementById("btnReUploadAllImage").style.display = 'block';
            }
            //////////////////////////////


            fetch(ADMIN_AJAX, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'mbsoft_api_ajax',
                    name: tableName,
                    inputValue: inputValue,
                    filters: filters
                })
            })
            .then(response => response.json())
            .then(data => {
                const tableData = document.getElementById('tableData');
                tableData.innerHTML = ''; 
                SELECTED_RECORDS = [];

                if (data.error) {
                    tableData.textContent = data.error;
                    return;
                }

                if (!sameTable) dinamicForm(data);

                const table = document.createElement('table');
                const thead = document.createElement('thead');
                const headerRow = document.createElement('tr');
                data.columns.forEach(column => {
                    const th = document.createElement('th');
                    th.textContent = column;
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);
                table.appendChild(thead);

                const tbody = document.createElement('tbody');
                data.rows.forEach(row => {
                    const tr = document.createElement('tr');
                    data.columns.forEach(column => {
                        const td = document.createElement('td');
                        td.textContent = row[column];
                        tr.appendChild(td);
                    });
                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);
                tableData.appendChild(table);
            })
            .catch(error => console.error('Error fetching the table data:', error));

            return;

            fetch(`<?= MBSOFT_PLUGIN_DIR;?>/sub_system/tabla.php?name=${tableName}&inputValue=${inputValue}&${filters}`)
            .then(response => response.json())
            .then(data => {
                const tableData = document.getElementById('tableData');
                tableData.innerHTML = ''; // Limpiar cualquier dato previo
                SELECTED_RECORDS = [];

                if (data.error) {
                    tableData.textContent = data.error;
                    return;
                }

                if(!sameTable)
                    dinamicForm(data);

                // Crear encabezado de la tabla
                const table = document.createElement('table');
                const thead = document.createElement('thead');
                const headerRow = document.createElement('tr');
                data.columns.forEach(column => {
                    const th = document.createElement('th');
                    th.textContent = column;
                    headerRow.appendChild(th);
                });
                thead.appendChild(headerRow);
                table.appendChild(thead);

                // Crear cuerpo de la tabla
                const tbody = document.createElement('tbody');
                data.rows.forEach(row => {
                    const tr = document.createElement('tr');
                    data.columns.forEach(column => {
                        const td = document.createElement('td');
                        if (column == "id") {
                            td.textContent = row[column];
                            tr.id = `id-tr-${row[column]}`;

                            if(tableName == "files_info" || tableName == "posted_products"){

                                if(tableName == "files_info"){
                                    // viewUploadedImage
                                    const viewBtn = document.createElement('button');
                                    viewBtn.textContent = 'View';
                                    viewBtn.style.backgroundColor = 'green'; // Color del botón
                                    viewBtn.style.color = 'white'; // Color del texto
                                    viewBtn.style.padding = '5px 10px'; // Padding del botón
                                    viewBtn.style.marginLeft = '5px'; // Margen izquierdo del botón
                                    viewBtn.style.cursor = 'pointer'; // Cursor del botón
                                    viewBtn.addEventListener('click', async function() {
                                        viewUploadedImage(tableName, row[column]);
                                    });

                                    td.appendChild(viewBtn);

                                }
                                
                                // Crear el botón REMOVE
                                const updateBtn = document.createElement('button');
                                updateBtn.textContent = tableName === 'files_info'? 'Re Upload' : 'CHANGE STATUS!';
                                updateBtn.style.backgroundColor = '#4c4ced'; // Color del botón
                                updateBtn.style.color = 'white'; // Color del texto
                                updateBtn.style.padding = '5px 10px'; // Padding del botón
                                updateBtn.style.marginLeft = '5px'; // Margen izquierdo del botón
                                updateBtn.style.cursor = 'pointer'; // Cursor del botón
                                updateBtn.classList.add('button-reupload-files');
                                updateBtn.addEventListener('click', async function() {
                                    // if (confirm('Are you sure you want to update this record?')) {
                                        // Deshabilitar el botón y mostrar el mensaje de carga
                                        updateBtn.disabled = true;
                                        updateBtn.textContent = 'LOADING...';

                                        try {
                                            updateBtn.disabled = false;
                                            
                                            if(tableName == "files_info" ){
                                                await reUploadImage(tableName, row[column]);
                                                updateBtn.textContent = 'LISTO!';
                                            }else{
                                                // Realizar la operación asincrónica
                                                await updateRecord(tableName, row[column]);
                                                // Si se completó exitosamente, volver a habilitar el botón y restaurar su texto original
                                                updateBtn.textContent = 'CHANGE STATUS!';
                                            }
                                            
                                        } catch (error) {
                                            updateBtn.disabled = false;
                                            // Si hay un error, manejarlo aquí
                                            console.error('Error occurred:', error);
                                            updateBtn.textContent = 'ERROR!';
                                            // Aquí puedes añadir una lógica para manejar errores, como mostrar un mensaje de error al usuario
                                            // alert('An error occurred while updating the record.');
                                        }
                                    // }
                                });

                                td.appendChild(updateBtn);
                            }
                            

                            // Crear el botón REMOVE
                            const removeBtn = document.createElement('button');
                            removeBtn.textContent = 'REMOVE!';
                            removeBtn.style.backgroundColor = '#FF5733'; // Color del botón
                            removeBtn.style.color = 'white'; // Color del texto
                            removeBtn.style.padding = '5px 10px'; // Padding del botón
                            removeBtn.style.marginLeft = '5px'; // Margen izquierdo del botón
                            removeBtn.style.cursor = 'pointer'; // Cursor del botón
                            removeBtn.addEventListener('click', function() {
                                if (confirm('Are you sure you want to remove this record?')) {
                                    removeRecord(tableName, row[column]);
                                }
                            });
                            td.appendChild(removeBtn);

                            // Crear el checkbox
                            const checkbox = document.createElement('input');
                            checkbox.type = 'checkbox';
                            checkbox.style.marginLeft = '5px'; // Margen izquierdo del checkbox
                            checkbox.addEventListener('change', function() {
                                if (this.checked) {
                                    // Añadir el valor y el nombre de la tabla al array si el checkbox está seleccionado
                                    SELECTED_RECORDS.push({ "tableName": tableName, "id": row[column] });
                                } else {
                                    // Eliminar el valor y el nombre de la tabla del array si el checkbox está deseleccionado
                                    SELECTED_RECORDS = SELECTED_RECORDS.filter(record => record.tableName !== tableName || record.id !== row[column]);
                                }
                                showDeleteButtonCheked();
                            });
                            td.appendChild(checkbox);
                        }else if (column == "headers" || column == "params"){
                            
                            // Verificar si la celda contiene un JSON
                            let headersData;
                            try {
                                headersData = JSON.parse(row[column]);
                            } catch (error) {
                                // Si no es un JSON válido, mostrar un botón
                                const showInfoBtn = document.createElement('button');
                                showInfoBtn.textContent = 'MOSTRAR INFORMACIÓN';
                                showInfoBtn.style.backgroundColor = '#3498db'; // Color del botón
                                showInfoBtn.style.color = 'white'; // Color del texto
                                showInfoBtn.style.padding = '5px 10px'; // Padding del botón
                                showInfoBtn.style.cursor = 'pointer'; // Cursor del botón
                                showInfoBtn.addEventListener('click', function() {
                                    // Aquí puedes definir la lógica para mostrar la información según tus necesidades
                                    console.log("Mostrar información para la fila: ", row);
                                });
                                td.appendChild(showInfoBtn);
                            }

                            // Si es un JSON válido, mostrar el JSON parseado
                            if (headersData) {
                                const jsonContent = document.createElement('pre');
                                jsonContent.textContent = JSON.stringify(headersData, null, 2); // Formatear el JSON
                                td.appendChild(jsonContent);
                            }

                            // // Añadir la celda a la fila
                            // tr.appendChild(td);
                        }else if (column == "body"){
                            const divCollapsible = document.createElement('div');
                            divCollapsible.className = 'collapsible';

                            const spanToggle = document.createElement('button');
                            spanToggle.className = 'toggle';
                            spanToggle.textContent = 'VER CONTENIDO';
                            divCollapsible.appendChild(spanToggle);

                            const divContent = document.createElement('div');
                            divContent.style.display = 'none';
                            divContent.className = 'content';
                            divContent.textContent = row[column];
                            divCollapsible.appendChild(divContent);

                            spanToggle.addEventListener('click', function() {
                                this.textContent = this.textContent == 'VER CONTENIDO' ? 'OCULTAR CONTENIDO' : 'VER CONTENIDO';
                                divContent.style.display = divContent.style.display === 'none' ? 'block' : 'none';
                            });

                            td.appendChild(divCollapsible);
                        }else{
                            td.textContent = row[column];
                        }

                        //// ADD COLUMN ////
                        tr.appendChild(td);
                    });

                    tbody.appendChild(tr);
                });
                table.appendChild(tbody);

                tableData.appendChild(table);
            })
            .catch(error => console.error('Error fetching the table data:', error));
        }

        async function dinamicForm(data) {
            const containerDiv = document.getElementById('dinamicForm');
            containerDiv.innerHTML = ''; // Limpiar el contenedor
            const form = document.createElement('form');
            form.id = 'filterForm';
            form.style.width = '50%'; // Ancho del formulario
            form.style.margin = 'auto'; // Centrar el formulario
            form.style.backgroundColor = '#f4f4f4'; // Color de fondo del formulario
            form.style.padding = '20px'; // Padding del formulario
            form.style.borderRadius = '8px'; // Esquinas redondeadas
            form.style.boxShadow = '0px 0px 10px 0px rgba(0, 0, 0, 0.1)'; // Sombra del formulario

            data.columns.forEach(column => {
                const div = document.createElement('div'); // Contenedor para cada campo
                div.style.marginBottom = '10px'; // Margen inferior para cada campo
                const label = document.createElement('label');
                label.textContent = column;
                label.style.display = 'block'; // Hacer que la etiqueta ocupe toda la línea
                const input = document.createElement('input');
                input.name = column;
                input.style.width = '100%'; // Hacer que el input ocupe toda la línea
                input.style.padding = '10px'; // Padding del input
                input.style.boxSizing = 'border-box'; // Asegurarse de que el padding no afecte el ancho total
                div.appendChild(label);
                div.appendChild(input);
                form.appendChild(div);
            });

            const submitButton = document.createElement('button');
            submitButton.type = 'button';
            submitButton.textContent = 'Filter';
            submitButton.style.backgroundColor = '#4CAF50'; // Color del botón
            submitButton.style.color = 'white'; // Color del texto del botón
            submitButton.style.border = 'none'; // Quitar borde del botón
            submitButton.style.padding = '10px 20px'; // Padding del botón
            submitButton.style.cursor = 'pointer'; // Cambiar cursor al pasar sobre el botón
            submitButton.style.borderRadius = '4px'; // Esquinas redondeadas del botón
            submitButton.onclick = function() {
                applyFilters();
            };
            form.appendChild(submitButton);
            containerDiv.appendChild(form); 
        }

        function applyFilters() {
            var inputValue = document.getElementById("modalInput").value;
            const form = document.getElementById('filterForm');
            const formData = new FormData(form);
            let queryString = ``; // ya que 'mb' es el valor requerido
            formData.forEach((value, key) => {
                queryString += `&${key}=${value}`;
            });
            showTable(tableNameSelected, queryString)
        }
    </script>

</body>
</html>
