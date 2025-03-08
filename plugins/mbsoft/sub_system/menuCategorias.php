<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once(ABSPATH . 'wp-load.php');




function add_sub_item_to_nav_menu($menu_id, $parent_item_id, $category_name, $id_category) {

    $itemData = array(
        'menu-item-title' => $category_name,
        'menu-item-object-id' => $id_category,
        'menu-item-parent-id' => $parent_item_id,
        'menu-item-db-id' => 0,
        'menu-item-object' => 'category',
        'menu-item-type' => 'taxonomy',
        'menu-item-url' => get_category_link($id_category),
        'menu-item-status' => 'publish',
        'menu-item-target' => '', // Puedes establecer un valor si quieres que el enlace se abra en una nueva ventana o pestaña
        'menu-item-classes' => '', // Aquí puedes especificar clases adicionales para el elemento del menú
        'menu-item-description' => '', // Puedes agregar una descripción para el elemento del menú si lo deseas
        'menu-item-attr-title' => '', // Este atributo proporciona información adicional sobre el elemento del menú
        'menu-item-xfn' => '', // Atributo XFN opcional
    );

    // Prepare the data for the new menu item
    $itemData = array(
        'menu-item-parent-id' => $parent_item_id,
        'menu-item-title' => $category_name,
        'menu-item-url' => get_category_link($id_category),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom', // or 'post_type' if linking to a post or page
        'menu-item-parent-id' => $parent_item_id, // Set the parent menu item ID
    );

    $sub_item_id = wp_update_nav_menu_item($menu_id, 0, $itemData);

    return $sub_item_id;
}


function remove_nav_menu_item($item_id) {
    wp_delete_post($item_id, true);
}

function update_nav_menu_item($menu_id, $item_id, $args) {
    $args['menu-item-db-id'] = $item_id;
    wp_update_nav_menu_item($menu_id, $item_id, $args);
}

function find_menu_item_id_by_title($menu_items, $menu_id, $title) {

    foreach ($menu_items as $menu_item) {
        if ($menu_item->title == $title) {
            return $menu_item->ID; // Retorna el ID del elemento que coincide con el título
        }
    }
    return false; // Retorna false si no se encuentra ningún elemento
}

function find_menu_id_by_name($menu_name) {
    $menu = wp_get_nav_menu_object($menu_name);
    if ($menu) {
        return $menu->term_id; // Retorna el ID del menú
    }
    return false; // Retorna false si no se encuentra el menú
}

function get_child_menu_items($menu_items, $menu_id, $parent_item_id) {

    $child_items = array();

    foreach ($menu_items as $menu_item) {
        if ($menu_item->menu_item_parent && $menu_item->menu_item_parent == $parent_item_id) {
            $child_items[] = $menu_item;
        }
    }

    return $child_items;
}

function compare_and_classify_categories($desired_categories, $current_child_items) {
    $existing_categories = array();
    $new_categories = array();
    $unwanted_categories = array();


    $titles = array_map(function($item) {
        return $item->title;
    }, $current_child_items);


    // Identificar las categorías existentes y las nuevas
    foreach ($desired_categories as $category) {
        if (in_array($category, $titles)) {
            $existing_categories[] = $category;
        } else {
            $new_categories[] = $category;
        }
    }
    
    // Identificar las categorías no deseadas
    foreach ($current_child_items as $current_item) {
        if (!in_array($current_item->title, $desired_categories) && !$current_item->ID == 1198) {
            $unwanted_categories[] = $current_item;
        }
    }

    return array(
        "existing_categories" => $existing_categories,
        "new_categories" => $new_categories,
        "unwanted_categories" => $unwanted_categories
    );
}


function group_childres($menu_items, $category_name = "") {

    $category = NULL;

    foreach ($menu_items as $menu_item) {
        if (trim($menu_item->title) == trim($category_name)) {
            $category = $menu_item;
            break;
        }
    }

    if(!empty($category)){
        $category->children = [];
    }



    $grouped_posts = array();

    // Iteramos sobre los posts para agrupar los hijos bajo sus padres
    foreach ($menu_items as $post) {
        // Obtenemos el ID del padre
        $parent_id = $post->post_parent;

        // Si el ID del padre es 0, significa que es un elemento raíz
        if ($parent_id == 0) {
            // Agregamos el post directamente al nuevo array
            $grouped_posts = (object) $post;
            $grouped_posts->children = [];
        } else {
            die('COMENTADO CATEGORIA CODE');
            // Si el padre ya está en el nuevo array, agregamos este post como su hijo
            if (isset($grouped_posts[$parent_id])) {
                // Verificamos si ya existe el array de hijos
                if (!isset($grouped_posts[$parent_id]->children)) {
                    $grouped_posts[$parent_id]->children = array();
                }
                // Agregamos este post como hijo del padre
                $grouped_posts[$parent_id]->children[] = $post;
            }
        }
    }
    
    return $grouped_posts;
}


function getCategoryLocal($name ="") {
    global $dbPath;

    try {
        // Abre la conexión a la base de datos SQLite
        $db = new SQLite3($dbPath);

        $where = " woocomerce_category_id IS NOT NULL AND woocomerce_category_id != ''";
        if(!empty($name)){
            $where = " name='$name'";
        }

        // Consulta la base de datos para obtener todos los registros de la tabla
        $query = $db->query("SELECT * FROM posted_categories WHERE 1=1 and  $where ");

        // Inicializa un array para almacenar todos los registros
        $information = array();

        // Recorre los resultados de la consulta y los agrega al array
        while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
            $information[] = $row;
        }

        // Cierra la conexión a la base de datos
        $db->close();

        // Devuelve el array con los registros
        return $information;
    } catch (\Throwable $e) {
        // Maneja cualquier error de la base de datos aquí
        return null;
    }
}



try {
    ////////////////////////////////////////
    $category_local = getCategoryLocal();
    $desired_categories = array();

    foreach ($category_local as $category) {
        // Verifica si el campo "name" está definido y no está vacío
        if (isset($category['name']) && !empty($category['name'])) {
            // Agrega la descripción al array de descripciones
            $desired_categories[] = $category['name'];
        }
    }
    
    if(empty($desired_categories)){
        // echo json_encode([
        //     "message" => "No hay Categorias para agregar"
        // ]);
        return;
    }
    ////////////////////////////////////////



    ////////////////////////////////////////
    $menu_name = "BOTONES DE INICIO";
    $menu_id = find_menu_id_by_name($menu_name);

    if (!$menu_id) {
        // echo json_encode([
        //     "message" => "LA COLECCION DE MENU LLAMADA ($menu_name) NO EXISTE"
        // ]);
        return;
    }

    ////////////////////////////////////////



    ////////////////////////////////////////
    $menu_items = wp_get_nav_menu_items($menu_id);
    ////////////////////////////////////////



    ////////////////////////////////////////
    $parent_item_title = "Categorias";
    $parent_item_id = find_menu_item_id_by_title($menu_items, $menu_id, $parent_item_title);

    if (empty($parent_item_id)) {
        // echo json_encode([
        //     "message" => "Menú o elemento padre 'Categorías' no encontrado."
        // ]);
        return;
    }
    ////////////////////////////////////////



    ////////////////////////////////////////
    $current_child_items = get_child_menu_items($menu_items, $menu_id, $parent_item_id);
    $response = compare_and_classify_categories($desired_categories, $current_child_items);
    ////////////////////////////////////////


    foreach ($response["new_categories"] as $key => $value) {
        $category_info = getCategoryLocal($value);

        if(empty($category_info)){
            continue;
        }

        add_sub_item_to_nav_menu(
            $menu_id,
            $parent_item_id, 
            $category_info[0]["name"],
            $category_info[0]["woocomerce_category_id"]
        );
    }
    // echo "OK";
    // $response["existing_categories"]
    // $response["new_categories"]
    // $response["unwanted_categories"] // Categorias que no fueron enviadas en desired_categories por lo que deberia eliminarse del sistema

} catch (\Throwable $th) {
    echo json_encode([
        "message" => $th->getMessage(),
        "file" => "menuCategorias"
    ]);
    die();
}

