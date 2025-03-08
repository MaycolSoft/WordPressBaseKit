<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drop Zone</title>
    <!-- Add Font Awesome via CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }

        .drop-zone {
            border: 2px dashed #ccc;
            padding: 20px;
            cursor: pointer;
        }

        #file-info {
            display: none;
            margin-top: 20px;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 10px 0;
        }

        .file-icon {
            font-size: 24px;
            margin-right: 10px;
        }

        .remove-button {
            background-color: #ff0000;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php
        // require_once '../wp-load.php';
        // if( !is_user_logged_in() ){ 
        //     echo '
        //         <a href="../wp-admin">
        //             <h2> PRIMERO DEBES INICIAR SESSION </h2>
        //         </a>
        //     ';
        //     die();
        // }
    ?>
    <style>
        .file-item {
            padding: 10px;
            border: 1px solid #ccc;
            margin-top: 20px;
        }
        
        .form-wrapper {
            margin-top: 10px;
            background-color: #f1f1f1;
            padding: 10px;
            border-radius: 4px;
        }
        
        .form-wrapper select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding:10px;
            margin:10px 0;
            border:0;
            border-bottom:1px solid #eee;
            box-shadow:0 0 15px 4px rgba(0,0,0,0.06);
        }

        .form-wrapper label {
            font-size: 1.2em;
            color: #333;
            margin-right: 10px;
        }

        .form-wrapper-select{
            display: inline-grid;
            margin: 10px;
        }

        .file-item-container{
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            flex-direction: row;
            gap: 10px;
        }

        button {
            background-color: #4CAF50; /* Green */
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
        }
    </style>


    <div class="drop-zone" id="dropZone">
        <p>Drag and drop files here or click to select multiple files.</p>
    </div>

    <button id="upload-button" >Upload Files</button>

    <div id="file-info">
        <!-- Content of uploaded files will be displayed here -->
    </div>

    <div id="response-content"></div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInfo = document.getElementById('file-info');
        const uploadButton = document.getElementById('upload-button');
        const filesMap = {};
        const formData = new FormData();

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('highlight');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('highlight');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('highlight');

            const files = Array.from(e.dataTransfer.files);
            files.forEach(file => {
                displayFileInfo(file);
            });
        });

        dropZone.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.txt, .json'; // You can specify allowed file types here
            input.multiple = true; // Allow selecting multiple files
            input.style.display = 'none';

            input.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                files.forEach(file => {
                    displayFileInfo(file);
                });
            });

            document.body.appendChild(input);
            input.click();
        });

        function displayFileInfo(file) {
            // Generate a unique key for each file and add it to the FormData
            const uniqueKey = generateUniqueKey();
            
            if (!file) {return;}
        
            const fileItemContainer = document.createElement('div');
            fileItemContainer.classList.add('file-item-container');

            /////////////////////////////////////////////////////////
            const reader = new FileReader();
            reader.readAsText(file);

            reader.onload = function(event) {
                try {
                    // Intentar analizar el contenido como JSON
                    const jsonObject = JSON.parse(event.target.result);

                    let keys = Object.keys(jsonObject);
                    if( Array.isArray(jsonObject) === true ){
                        keys = Object.keys(jsonObject[0]);
                    }

                    //////////////////////////////////////////////////////////////////////
                    // Crear el formulario para asignar claves
                    const form = document.createElement('form');

                    const predefinedKeys = ['sku', 'name', 'type', 'regular_price', 'description', 'short_description', 'categories', 'sale_price', 'existencia'];

                    predefinedKeys.forEach(predefinedKey => {
                        //// CREATE DIV
                        const divSelect = document.createElement('div');

                        //// CREATE LABEL
                        const label = document.createElement('label');
                        label.textContent = predefinedKey;

                        //// CREATE SELECT
                        const select = document.createElement('select');
                        // Agregar evento que se dispare al cambiar la selección
                        select.addEventListener('change', function(event) {

                            const parentDiv = event.target.parentNode;
                            // Buscar el elemento 'label' dentro del 'div'
                            const labelElement = parentDiv.querySelector('label');
                            const labelText = labelElement ? labelElement.textContent : 'Label no encontrado';

                            const updateFormData = (formData, uniqueKey, key, newValue) => {
                                // Primero verificamos si la clave única ya existe en el FormData
                                if (formData.has(uniqueKey)) {
                                    // Si la clave existe, obtenemos el valor actual como una cadena JSON
                                    const currentValStr = formData.get(uniqueKey);
                                    
                                    // Convertimos la cadena JSON en un objeto JavaScript
                                    let currentValObj;
                                    try {
                                        currentValObj = JSON.parse(currentValStr);
                                    } catch (e) {
                                        console.error("Error al analizar JSON:", e);
                                        return;
                                    }
                                    
                                    // Actualizamos la clave 'description' en el objeto
                                    currentValObj[key] = newValue;
                                    
                                    // Convertimos el objeto actualizado nuevamente a una cadena JSON
                                    const updatedValStr = JSON.stringify(currentValObj);
                                    
                                    // Utilizamos 'set' para actualizar el valor en el FormData
                                    formData.set(uniqueKey, updatedValStr);
                                } else {
                                    // Si la clave no existe, simplemente agregamos un nuevo objeto JSON con la clave 'description'
                                    const newValObj = {};
                                    newValObj[key]= newValue
                                    formData.append(uniqueKey, JSON.stringify(newValObj));
                                }
                            }

                            updateFormData(formData, `select-${uniqueKey}`, labelText, event.target.value);
                        });


                        //////////////// SELECT COMPONENT ////////////////
                        // Añadir una opción vacía
                        const emptyOption = document.createElement('option');
                        emptyOption.value = '';
                        emptyOption.textContent = '-- Select --';
                        select.appendChild(emptyOption);

                        // Añadir las claves detectadas como opciones
                        keys.forEach(key => {
                            const option = document.createElement('option');
                            option.value = key;
                            option.textContent = key;
                            select.appendChild(option);
                        });
                        //////////////// SELECT COMPONENT ////////////////

                        //////////////// AGREGAR LABEL EN DIV ////////////////
                        divSelect.appendChild(label);
                        divSelect.appendChild(select);
                        divSelect.classList.add('form-wrapper-select');
                        //////////////// AGREGAR LABEL EN DIV ////////////////

                        // Añadir al formulario
                        form.appendChild(divSelect);
                    });

                    // Crear y configurar el div file-item
                    const fileItem2 = document.createElement('div');
                    fileItem2.classList.add('form-wrapper');

                    // Añadir el formulario al div file-item
                    fileItem2.appendChild(form);
                    // fileInfo.appendChild(fileItem2);
                    fileItemContainer.appendChild(fileItem2);

                } catch (e) {
                    console.error("The file doesn't contain valid JSON:", e);
                }
            };

            reader.onerror = function() {
                console.error("An error occurred while reading the file.");
            };
            /////////////////////////////////////////////////////////




            fileInfo.style.display = 'block';


            const fileItem = document.createElement('div');
            fileItem.classList.add('file-item');

            // Create an <i> element for the file icon (using Font Awesome)
            const fileIcon = document.createElement('i');
            fileIcon.classList.add('fas', 'fa-file'); // Font Awesome classes for a file icon

            const fileName = document.createElement('span');
            fileName.textContent = file.name;

            const removeButton = document.createElement('button');
            removeButton.textContent = 'X';
            removeButton.classList.add('remove-button');
            removeButton.addEventListener('click', () => {
                // Remove the file from the display
                fileItemContainer.remove();

                // Remove the file from the FormData by accessing it through the filesMap
                // const uniqueKey = filesMap[file.name];
                formData.delete(`${uniqueKey}`);
                formData.delete(`select-${uniqueKey}`);

                // Remove the unique key from the filesMap
                delete filesMap[file.name];
            });

            // formData.append(`files[${uniqueKey}]`, file);
            formData.append(`${uniqueKey}`, file);

            // Add the unique key to the filesMap
            filesMap[file.name] = uniqueKey;

            fileItem.appendChild(fileIcon);
            fileItem.appendChild(fileName);
            fileItem.appendChild(removeButton);

            fileItemContainer.appendChild(fileItem);
            fileInfo.appendChild(fileItemContainer);

        }

        // Function to generate a unique key for each file
        function generateUniqueKey() {
            return Math.random().toString(36).substr(2, 9);
        }

        uploadButton.addEventListener('click', () => {
            // Create a FormData object
            // const formData = new FormData();

            // Get all elements with the class 'file-item'
            const fileItems = document.querySelectorAll('.file-item');

            // Iterate through elements and add files to FormData object
            // fileItems.forEach(item => {
            //     const fileName = item.querySelector('span').textContent;
            //     formData.append('files[]', fileName); // You can use 'files[]' to indicate an array of files
            // });

            formData.delete('action');
            formData.delete('file');
            formData.append('action', 'mbsoft_api_ajax');
            formData.append('file', 'createOrUpdateProduct');
            // Send POST request to the server
            fetch(ADMIN_AJAX, {
                method: 'POST',
                // headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData,
            })
            .then(response => {
                // Handle server response here
                if (response.ok) {
                    response.text().then((r)=>{
                        const response_content = document.getElementById("response-content");
                        response_content.innerHTML = r;
                    });
                    // console.log('Files uploaded successfully.');
                } else {
                    console.error('Error uploading files.');
                }
            })
            .catch(error => {
                // Handle errors here
                console.error('Error in POST request:', error);
            });
        });

        function readFile(file, uniqueKey){
            // Leer el contenido del archivo como texto
            const reader = new FileReader();
            reader.readAsText(file);
            
            reader.onload = function(event) {
                try {
                    // Intentar analizar el contenido como JSON
                    const jsonObject = JSON.parse(event.target.result);

                    let keys = Object.keys(jsonObject);
                    if( Array.isArray(jsonObject) === true ){
                        keys = Object.keys(jsonObject[0]);
                    }

                    //////////////////////////////////////////////////////////////////////
                    // Crear el formulario para asignar claves
                    const form = document.createElement('form');

                    const predefinedKeys = ['sku', 'descripcion', 'images', 'price', 'oferta'];

                    predefinedKeys.forEach(predefinedKey => {
                        //// CREATE DIV
                        const divSelect = document.createElement('div');

                        //// CREATE LABEL
                        const label = document.createElement('label');
                        label.textContent = predefinedKey;

                        //// CREATE SELECT
                        const select = document.createElement('select');
                        // Agregar evento que se dispare al cambiar la selección
                        select.addEventListener('change', function(event) {

                            const parentDiv = event.target.parentNode;
                            // Buscar el elemento 'label' dentro del 'div'
                            const labelElement = parentDiv.querySelector('label');
                            const labelText = labelElement ? labelElement.textContent : 'Label no encontrado';

                            const updateFormData = (formData, uniqueKey, key, newValue) => {
                                // Primero verificamos si la clave única ya existe en el FormData
                                if (formData.has(uniqueKey)) {
                                    // Si la clave existe, obtenemos el valor actual como una cadena JSON
                                    const currentValStr = formData.get(uniqueKey);
                                    
                                    // Convertimos la cadena JSON en un objeto JavaScript
                                    let currentValObj;
                                    try {
                                        currentValObj = JSON.parse(currentValStr);
                                    } catch (e) {
                                        console.error("Error al analizar JSON:", e);
                                        return;
                                    }
                                    
                                    // Actualizamos la clave 'description' en el objeto
                                    currentValObj[key] = newValue;
                                    
                                    // Convertimos el objeto actualizado nuevamente a una cadena JSON
                                    const updatedValStr = JSON.stringify(currentValObj);
                                    
                                    // Utilizamos 'set' para actualizar el valor en el FormData
                                    formData.set(uniqueKey, updatedValStr);
                                } else {
                                    // Si la clave no existe, simplemente agregamos un nuevo objeto JSON con la clave 'description'
                                    const newValObj = {};
                                    newValObj[key]= newValue
                                    formData.append(uniqueKey, JSON.stringify(newValObj));
                                }
                            }

                            updateFormData(formData, `select-${uniqueKey}`, labelText, event.target.value);
                        });


                        //////////////// SELECT COMPONENT ////////////////
                        // Añadir una opción vacía
                        const emptyOption = document.createElement('option');
                        emptyOption.value = '';
                        emptyOption.textContent = '-- Select --';
                        select.appendChild(emptyOption);

                        // Añadir las claves detectadas como opciones
                        keys.forEach(key => {
                            const option = document.createElement('option');
                            option.value = key;
                            option.textContent = key;
                            select.appendChild(option);
                        });
                        //////////////// SELECT COMPONENT ////////////////

                        //////////////// AGREGAR LABEL EN DIV ////////////////
                        divSelect.appendChild(label);
                        divSelect.appendChild(select);
                        divSelect.classList.add('form-wrapper-select');
                        //////////////// AGREGAR LABEL EN DIV ////////////////

                        // Añadir al formulario
                        form.appendChild(divSelect);
                    });

                    // Crear y configurar el div file-item
                    const fileItem = document.createElement('div');
                    fileItem.classList.add('form-wrapper');

                    // Añadir el formulario al div file-item
                    fileItem.appendChild(form);
                    fileInfo.appendChild(fileItem);

                } catch (e) {
                    console.error("The file doesn't contain valid JSON:", e);
                }
            };
            
            reader.onerror = function() {
                console.error("An error occurred while reading the file.");
            };
        }


    </script>
</body>
</html>