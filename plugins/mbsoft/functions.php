<?php


// Función para instalar el tema copiándolo desde el plugin
function mpa_install_theme()
{
    $plugin_theme_dir = plugin_dir_path(__FILE__) . 'themes';

    if (!file_exists(MBSOFT_THEME_DIR)) {
        mpa_copy_folder($plugin_theme_dir, MBSOFT_THEME_DIR);
    }
} 

// Función para desinstalar el tema (eliminarlo)
function mpa_uninstall_theme()
{
    if (file_exists(MBSOFT_THEME_DIR)) {
        mpa_delete_folder(MBSOFT_THEME_DIR);
    }
}

// Función para activar el tema en WordPress
function mpa_activate_theme()
{
    if (file_exists(MBSOFT_THEME_DIR)) {
        switch_theme('mbsoft-theme');
    }
}

// Función para copiar una carpeta completa
function mpa_copy_folder($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (($file = readdir($dir)) !== false) {
        if ($file !== '.' && $file !== '..') {
            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;
            if (is_dir($srcFile)) {
                mpa_copy_folder($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }
    closedir($dir);
}

// Función para eliminar una carpeta completa
function mpa_delete_folder($folder)
{
    if (!is_dir($folder))
        return;
    foreach (scandir($folder) as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $folder . '/' . $file;
            is_dir($filePath) ? mpa_delete_folder($filePath) : unlink($filePath);
        }
    }
    rmdir($folder);
}





// Agregar un shortcode para mostrar la landing page pública
function mbsoft_shortcode_landing()
{
    ob_start();
    include MPA_PLUGIN_DIR . 'public/landing-page.php';
    return ob_get_clean();
}







