<?php
function autoloadCore($class){ 
   //cambia a mayuscula la primera letra y las demas minusculas
    if(file_exists(APP_PATH . ucfirst(strtolower($class)) . '.php')){
        include_once APP_PATH . ucfirst(strtolower($class)) . '.php';
    }
}
//cargar las librerias de forma automatica
function autoloadLibs($class){ 
    // Ruta en minúsculas (comportamiento original)
    $lower = ROOT . 'libs' . DS . 'class.' . strtolower($class) . '.php';
    if (file_exists($lower)) {
        include_once $lower;
        return;
    }
    // Ruta respetando el nombre de la clase (por si el archivo está con mayúsculas)
    $asIs = ROOT . 'libs' . DS . 'class.' . $class . '.php';
    if (file_exists($asIs)) {
        include_once $asIs;
        return;
    }
    // Ruta con primera letra mayúscula por compatibilidad
    $ucfirst = ROOT . 'libs' . DS . 'class.' . ucfirst(strtolower($class)) . '.php';
    if (file_exists($ucfirst)) {
        include_once $ucfirst;
        return;
    }
}

spl_autoload_register("autoloadCore");
spl_autoload_register("autoloadLibs");

?>
