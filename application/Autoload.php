<?php
function autoloadCore($class){ 
   //cambia a mayuscula la primera letra y las demas minusculas
    if(file_exists(APP_PATH . ucfirst(strtolower($class)) . '.php')){
        include_once APP_PATH . ucfirst(strtolower($class)) . '.php';
    }
}
//cargar las librerias de forma automatica
function autoloadLibs($class){ 
    if(file_exists(ROOT . 'libs' . DS . 'class.' . strtolower($class) . '.php')){
        include_once ROOT . 'libs' . DS . 'class.' . strtolower($class) . '.php';
    }
}

spl_autoload_register("autoloadCore");
spl_autoload_register("autoloadLibs");

?>
