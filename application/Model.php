<?php
use Illuminate\Database\Capsule\Manager as Capsule;
class Model extends Illuminate\Database\Eloquent\Model
{
 public function __construct() {
$capsule = new Capsule;
//Indicamos en el siguiente array los datos de configuración de la BD
$capsule->addConnection([
 'driver' =>'mysql',
 'host' => DB_HOST,
 'database' => DB_NAME,
 'username' => DB_USER,
 'password' => DB_PASS,
 'charset' => 'utf8',
 'collation' => 'utf8_unicode_ci',
 'prefix' => '',
 'strict' => false,
]);

 // Segunda conexión: CMS (solo lectura)
 $capsule->addConnection([
  'driver' => 'mysql',
  'host' => CMS_DB_HOST,
  'database' => CMS_DB_NAME,
  'username' => CMS_DB_USER,
  'password' => CMS_DB_PASS,
  'charset' => 'utf8mb4',
  'collation' => 'utf8mb4_unicode_ci',
  'prefix' => '',
  'strict' => false,
 ], 'cms');
 $capsule->setAsGlobal();
//Y finalmente, iniciamos Eloquent
$capsule->bootEloquent();

   } 
}
?>