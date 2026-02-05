<?php
//version 0.0.7
//ini_set('display_errors', 0); //cuando se pase ha produccion cambiar por 0
//PATRON SINGLETON
define('DS', DIRECTORY_SEPARATOR);
date_default_timezone_set("America/Mexico_City");
//obtine la ruta de donde esta el proyecto para evitar problemas de rutas es DS
define('ROOT', realpath(dirname(__FILE__)) . DS);
//ruta de las aplicacion
define('APP_PATH', ROOT . 'application' . DS);
try {
    //cargar vendors
    require_once APP_PATH . 'Autoload.php';
    require_once APP_PATH . 'Config.php';
    require_once 'vendor/autoload.php';

    if (!isset($_GET['url']) || $_GET['url'] === '' || $_GET['url'] === '/') {
        header('location:' . BASE_URL . 'personas/inicio/');
        exit;
    }

    Session::init();
    $registry = Registry::getInstancia();
    $registry->_request = new Request();
    Bootstrap::run($registry->_request);
    //Operador de Resolución de Alcance (::)
} catch (Exception $e) {
    echo $e->getMessage();
}
