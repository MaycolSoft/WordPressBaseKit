<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CATALOGO</title>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js" integrity="sha512-XtmMtDEcNz2j7ekrtHvOVR4iwwaD6o/FUJe6+Zq+HgcCsk3kj4uSQQR8weQ2QVj1o0Pk6PwYLohm206ZzNfubg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css" integrity="sha512-17EgCFERpgZKcm0j0fEq1YCJuyAWdz9KUtv1EjVuaOz8pDnh/0nZxmU6BBXwaaxqoi9PQXnRWqlcDB027hgv9A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css" integrity="sha512-yHknP1/AwR+yx26cB1y0cjvQUMvEa2PFzt1c9LlS4pRQ5NOTZFWbhBig+X9G9eYW/8m0/4OXNx8pxJ6z57x0dw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="./pagina_catalogo/styles.css" />
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />


    <style>
         /* Estilos para el botón del carrito */
         .cart-button {
            cursor: pointer;
            position: relative;
            margin-left: 20px;
        }

        .cart-dropdown {
            position: absolute;
            top: 100%;
            /* right: 0; */
            display: none;
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1;
        }

        .cart-dropdown ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .cart-dropdown li {
            padding: 8px;
            border-bottom: 1px solid #ccc;
        }

        .cart-button:hover .cart-dropdown {
            display: block;
        }
    </style>
</head>
<body>
<?php

$_GET["page"] = intval($_GET["page"]);
if($_GET["page"] <= 0 ){
    $_GET["page"] = 1;
}

?>

<style>
    /* Estilos para el contenedor principal */
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
    }

    /* Estilos para la barra de búsqueda */
    .search-and-cart {
        display: flex;
        align-items: center;
        margin-right: 20px;
    }

    /* Estilos para el formulario de búsqueda */
    .search-form {
        display: flex;
        margin-right: 10px;
    }

    .search-input {
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    .search-button {
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 5px 10px;
        cursor: pointer;
    }

    /* Estilos para el carrito de compras */
    .cart-button {
        display: flex;
        align-items: center;
        position: relative;
    }

    .cart-button i {
        font-size: 24px;
        margin-right: 5px;
    }

    .cart-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 50vw;
        max-width: 300px;
        background-color: #fff;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        z-index: 1;
        overflow-x: auto;
    }

    .total-items {
        position: absolute;
        top: -15px;
        right: -15px;
        background-color: #007bff;
        color: #fff;
        border-radius: 50%;
        padding: 5px;
        font-size: 12px;
        font-weight: bold;
        width: 30px;
        height: 30px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Estilos para los botones NEXT y BACK */
    .pagination-buttons {
        display: flex;
        align-items: center;
    }

    .pagination-form {
        display: flex;
        margin-right: 10px;
    }

    .next-button,
    .back-button {
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 5px 10px;
        cursor: pointer;
    }

    /* Estilos para dispositivos móviles */
    @media (max-width: 767px) {
        .header-container {
            flex-direction: column;
            align-items: center;
        }

        .pagination-buttons {
            margin-top: 10px; /* Agrega espacio entre los botones de paginación y el encabezado */
            text-align: center; /* Centra los botones */
        }
    }

</style>

<div class="header-container">
    <div class="search-and-cart">
        <form action="" method="get" class="search-form">
            <input autocomplete="off" name="search" type="text" value="<?php echo isset($_GET["search"]) ? $_GET["search"] : ''; ?>" placeholder="Buscar por descripción" class="search-input">
            <button type="submit" class="search-button">Buscar</button>
        </form>

        <div class="cart-button">
            <i class="fas fa-shopping-cart"></i>
            <div class="cart-dropdown">
                <ul id="cart-list">
                    <!-- Los elementos del carrito se llenarán aquí -->
                </ul>
            </div>
            <span class="total-items" id="total_items_cart"></span>
        </div>
    </div>

    <div class="pagination-buttons">
        <form action="" method="get" class="pagination-form">
            <input name="search" hidden type="text" value="<?php echo isset($_GET["search"]) ? $_GET["search"] : ''; ?>" placeholder="Buscar por descripción" class="search-input">
            <input type="text" name="page" hidden value="<?php echo isset($_GET["page"]) ? ($_GET["page"] + 1) : 1; ?>">
            <button type="submit" class="next-button">NEXT</button>
        </form>

        <form action="" method="get" class="pagination-form">
            <input name="search" hidden type="text" value="<?php echo isset($_GET["search"]) ? $_GET["search"] : ''; ?>" placeholder="Buscar por descripción" class="search-input">
            <?php if (isset($_GET["page"]) && $_GET["page"] > 1) { ?>
                <input type="text" name="page" hidden value="<?php echo $_GET["page"] - 1; ?>">
                <button type="submit" class="back-button">BACK</button>
            <?php } ?>
        </form>
    </div>
</div>



<script>
let slideIndex = 1;
document.addEventListener("DOMContentLoaded", function() {
    // init();
});

function plusSlides(n, id) {
  showSlides(slideIndex += n, id);
}

function currentSlide(n, id) {
  showSlides(slideIndex = n, id);
}

// function init() {
//     const product = document.getElementsByClassName("product");
//     for (let index = 0; index < product.length; index++) {
//         const element = product[index];
//         // initSlides(1, element);
//     }
// }

// function initSlides(n, domElement) {
//   let i, x, y;
  
//   const slider_container = domElement.getElementsByClassName("slideshow-container");

//   for (x = 0; x < slider_container.length; x++) {
//     i = 0;
//     y =0;
//     slideIndex = 1;
//     let slides = slider_container[x].getElementsByClassName("slides_images");
//     let container_dots = slider_container[x].getElementsByClassName("container-dots");

//     if (n > slides.length) {slideIndex = 1}

//     if (n < 1) {slideIndex = slides.length}
//     for (i = 0; i < slides.length; i++) {
//         slides[i].style.display = "none";  
//     }

//     for (i = 0; i < container_dots.length; i++) {
//         dots = container_dots[i]
//         for (y = 0; y < dots.length; y++) {
//             dots[y].className = dots[y].className.replace(" active", "");
//         }
//         console.log(dots);
//         console.log(slideIndex);
//         dots[slideIndex-1].className += " active";
//     }
//     slides[slideIndex-1].style.display = "block"; 
    
//   }

// }


function showSlides(n, id) {
  let i;
  
  let slides = document.getElementsByClassName(id);
  let dots = document.getElementsByClassName("dot");

  if(!id){
    slides = document.getElementsByClassName("slides_images");
    dots = document.getElementsByClassName("dot-"+id);
  }


  if (n > slides.length) {slideIndex = 1}    
  if (n < 1) {slideIndex = slides.length}
  for (i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";  
  }
  for (i = 0; i < dots.length; i++) {
    dots[i].className = dots[i].className.replace(" active", "");
  }
  slides[slideIndex-1].style.display = "block"; 
  dots[slideIndex-1].className += " active";
}
</script>


<?php
// Configura la URL base de la API de WooCommerce y tus credenciales de autenticación
$url_base = 'https://supertiendachina.com.do/';
$consumer_key = 'ck_88fb2b2bf6ebaffda0a929eb76869237038d6eb7';
$consumer_secret = 'cs_5f1387718f6fb9eb2439156d3b8c80d35bd3e8af';

// Establece el número de página y la cantidad de productos por página
$pagina = $_GET["page"];
$search = $_GET["search"];
$por_pagina = 12;  // Cambia esto al número deseado de productos por página

// Construye la URL del endpoint de productos con los parámetros
$endpoint = "{$url_base}/wp-json/wc/v3/products";
$url = "{$endpoint}?consumer_key={$consumer_key}&consumer_secret={$consumer_secret}&per_page={$por_pagina}&page={$pagina}&search={$search}";

// Inicializa cURL
$ch = curl_init();

// Configura las opciones de cURL
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Realiza la solicitud GET
$response = curl_exec($ch);



// Verifica si la solicitud fue exitosa
if (curl_errno($ch)) {
    echo "Error al realizar la solicitud cURL: " . curl_error($ch);
} else {
    $productos = json_decode($response, true);

    if (is_array($productos)) {

        ?> 
        <ul class="listing">
        <?php
        foreach ($productos as $producto) {
            
            $hash_product = md5($producto['name']);
            $producto_encode = base64_encode(json_encode($producto));

            ?>
            <li class="product">
                <div >

                    <div class="img-wrapper slideshow-container" >

                    <?php
                        $total_images = count($producto["images"]);
                        foreach ($producto["images"] as $key => $value) {
                            $src = $value['src'];
                    ?>

                        <div class="slides_images <?php echo $hash_product;?> fade">
                            <div class="numbertext">1 / <?php echo $total_images;?> </div>
                            <img src="<?php echo $src;?>" style="width:100%">
                            <div class="text"></div>
                        </div>

                    <?php }?> 
                        <a class="prev" onclick="plusSlides(-1, '<?php echo $hash_product;?>')"> ❮ </a>
                        <a class="next" onclick="plusSlides(1, '<?php echo $hash_product;?>')"> ❯ </a>
                    </div>


                    <br>

                    <div style="text-align:center" class="container-dots">
                    <?php 
                        foreach ($producto["images"] as $key => $value) {
                            $add_class = "";
                            if($key === 1){
                                $add_class = "active";
                            }
                            echo "<span class=\"dot dot-$hash_product $add_class \" onclick=\"currentSlide($key, '$hash_product')\"></span> ";
                        }
                    ?>
                    </div>

                </div>
                
                <div class="info">
                <div class="title"> <?php echo $producto['name']; ?> </div>

                <?php 
                $sale_price = floatval($producto["sale_price"]);
                $price = floatval($producto['price']);
                
                if($sale_price > 0){
                    echo "
                        <div class=\"price sale\">$$sale_price</div>
                        <div class=\"price old\">$$price</div>
                    ";
                }else{
                    echo "<div class=\"price\"> $$price </div>";
                }
                ?>                
                
                </div>
                
                <ddiv class="actions-wrapper">
                    <div class="container_buttons_cart">
                        <button class="plus-btn" type="button" name="button" onclick="incrementValue('<?php echo $hash_product;?>', '<?php echo $producto_encode; ?>')">
                            +
                        </button>
                        <input id="<?php echo $hash_product;?>" type="text" name="name" value="0" oninput="checkInput('<?php echo $hash_product;?>', '<?php echo $producto_encode; ?>')">
                        <button class="minus-btn" type="button" name="button" onclick="decrementValue('<?php echo $hash_product;?>', '<?php echo $producto_encode; ?>')">
                            -
                        </button>
                    </div>
                </div>
            </li> 
            <?php
        }
        ?>
        </ul>

        <script>

            function download_csv() {
                const carrito = JSON.parse(localStorage.getItem('carrito')) || [];
                if(carrito.length <= 0){
                    alert("EL CARRITO ESTA VACIO");
                    return;
                }
                
                 // Encabezados del archivo CSV
                const csvHeaders = ['Product Name', 'Quantity', 'Unit Price', 'SKU', 'Category', 'Total'];


                // Contenido del archivo CSV
                const csvContent = carrito.map(item => {
                    const productName = item.detalle.name;
                    const quantity = item.cantidad;
                    const salePrice = parseFloat(item.detalle.sale_price); // Convertir sale_price a número
                    const unitPrice = salePrice > 0 ? salePrice : item.detalle.price; // Usar sale_price si es mayor a cero, de lo contrario, usar price
                    const sku = item.detalle.sku || ''; // SKU (si está disponible)
                    const categories = item.detalle.categories.map(category => category.name).join(' | '); // Categorías (convertidas a cadena y separadas por |)
                    const total = quantity * unitPrice;

                    // Escapar comas y comillas dobles en los datos
                    const escapedProductName = productName.replace(/"/g, '""');

                    // Formatear una fila de datos CSV
                    return `"${escapedProductName}",${quantity},${unitPrice},"${sku}","${categories}",${total}`;
                });

                // Unir encabezados y contenido CSV
                const csvData = [csvHeaders, ...csvContent].join('\n');

                 // Create an <a> element to download the CSV file
                const blob = new Blob([csvData], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'cart.csv';

                // Simulate a click on the link to download the CSV file
                a.click();

                // Revoke the URL object
                URL.revokeObjectURL(url);

            }

            // Función para cargar la cantidad desde localStorage y establecerla en los inputs
            function cargarCantidadDesdeLocalStorage() {
                // Obtén el carrito almacenado en localStorage
                const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

                // Recorre los elementos del carrito y establece la cantidad en los inputs correspondientes
                carrito.forEach(item => {
                    const inputId = `${item.id}`;
                    const inputElement = document.getElementById(inputId);

                    if (inputElement) {
                        inputElement.value = item.cantidad;
                    }
                });

                const cartList = document.getElementById('cart-list');

                // Limpiamos la lista antes de agregar los elementos
                cartList.innerHTML = '';
                document.getElementById("total_items_cart").textContent = carrito.length;
                if (carrito.length > 0 ){
                    const button_convert_csv = document.createElement('button');
                    button_convert_csv.onclick = download_csv;
                    button_convert_csv.textContent = "Download CSV";
                    
                    // Aplica estilos al botón
                    button_convert_csv.style.backgroundColor = "green"; // Cambia el color de fondo a verde
                    button_convert_csv.style.color = "white"; // Cambia el color del texto a blanco
                    button_convert_csv.style.border = "none"; // Elimina el borde
                    button_convert_csv.style.padding = "10px 20px"; // Añade relleno al botón

                    cartList.appendChild(button_convert_csv)
                }

                carrito.forEach(item => {
                    const listItem = document.createElement('li');
                    listItem.textContent = `${item.detalle.name} - Cantidad: ${item.cantidad}`;
                    cartList.appendChild(listItem);
                });
            }

            // Llama a la función al cargar la página
            window.addEventListener('load', cargarCantidadDesdeLocalStorage);


            // Función para incrementar el valor del input
            function incrementValue(id, product) {
                const quantityInput = document.getElementById(id);
                const newValue = parseInt(quantityInput.value) + 1;
                quantityInput.value = newValue;
                addToCart(newValue, product, id); // Llama a la función addToCart con el nuevo valor
            }

            // Función para decrementar el valor del input
            function decrementValue(id, product) {
                const quantityInput = document.getElementById(id);
                const newValue = parseInt(quantityInput.value) - 1;
                if(newValue < 0){
                    return;
                }
                quantityInput.value = newValue;
                addToCart(newValue, product, id); // Llama a la función addToCart con el nuevo valor
            }

            // Función para asegurarse de que el valor del input sea un número válido
            function checkInput(id, product) {
                const quantityInput = document.getElementById(id);
                const newValue = parseInt(quantityInput.value);
                if (isNaN(newValue) || newValue < 1) {
                    quantityInput.value = 1;
                }
                addToCart(newValue, product, id); // Llama a la función addToCart con el nuevo valor
            }


            // Función que se llama cuando se agrega un producto al carrito
            function addToCart(quantity, product, id_hash) {
                // Decodifica el producto (supongo que estás usando base64 para alguna razón)
                product = JSON.parse(atob(product));

                // Obtén el carrito actual desde localStorage o inicialízalo como un array vacío
                const carrito = JSON.parse(localStorage.getItem('carrito')) || [];

                // Busca si el producto ya está en el carrito
                const productIndex = carrito.findIndex(item => item.id === id_hash);

                if (quantity <= 0) {
                    console.log(productIndex);
                    // Si la cantidad es menor o igual a cero, elimina el producto del carrito
                    if (productIndex !== -1) {
                        carrito.splice(productIndex, 1);
                    }
                }else{
                    if (productIndex !== -1) {
                        // Si el producto ya está en el carrito, actualiza la cantidad
                        carrito[productIndex].cantidad = quantity;
                    } else {
                        // Si el producto no está en el carrito, agrégalo
                        carrito.push({
                            id: id_hash,
                            cantidad: quantity,
                            detalle: product
                        });
                    }

                }
                // Almacena el carrito actualizado en localStorage
                localStorage.setItem('carrito', JSON.stringify(carrito));

                // Aquí puedes implementar la lógica adicional para el carrito, como enviar una solicitud al servidor
                // o realizar otras acciones según tus necesidades.

                console.log("Carrito actualizado:", carrito);
                cargarCantidadDesdeLocalStorage();
            }

        </script>
        <?php
    } else {
        echo "No se encontraron productos.";
    }
}

// Cierra la sesión de cURL
curl_close($ch);
?>

</body>
</html>

