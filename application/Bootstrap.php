<?php
  class Bootstrap{
  	//pasamos el objeto del Request
  	public static function run(Request $peticion){
  		$modulo = $peticion->getModulo();
  		// Datos base del request
		$ctrlBase = $peticion->getControlador();
		$metodo   = $peticion->getMetodo();
		$args     = $peticion->getArgs();

		// Alias del panel: /usuarios[/Seccion]
		// El router original interpreta /usuarios/Recargar como controlador=usuarios, metodo=Recargar.
		// Aquí lo normalizamos a personasController->usuarios('Recargar').
		if ($ctrlBase === 'usuarios') {
			$section = $metodo;
			$metodo = 'usuarios';
			if (is_string($section) && $section !== '' && $section !== 'index') {
				array_unshift($args, $section);
			}
			$ctrlBase = 'personas';
		}

		// Alias de registro: /registro -> loginController->registro
		if ($ctrlBase === 'registro' || $ctrlBase === 'register') {
			$ctrlBase = 'login';
			$metodo = 'registro';
		}

		// Mapear rutas de Personas sin prefijo de controlador
		// /cobertura, /compatibilidad, /estatusenvio, /apn, /faq, /quienessomos, /portabilidad, /recargas, /recargar_empresas, /contacto
		$personasMap = array('cobertura','compatibilidad','estatusenvio','apn','faq','quienessomos','portabilidad','recargas','recargar_empresas','contacto','usuarios');
		if (in_array($ctrlBase, $personasMap)) {
			$controllerName = 'personas';
  			$metodo = $ctrlBase ?: 'index';
  		} else {
  			$controllerName = $ctrlBase;
  		}

  		$controllerClass = $controllerName . 'Controller';

  		// Resolver ruta del archivo del controlador
  		if($modulo){
  			$rutaModulo = ROOT . 'controllers' . DS . $modulo . 'Controller.php';
  			if(is_readable($rutaModulo)){
  				require_once $rutaModulo;
  				$rutaControlador = ROOT . 'modules'. DS . $modulo . DS . 'controllers' . DS . $controllerClass . '.php';
  			}else{
  				throw new Exception('Error de base de modulo');
  			}
  		}else{
  			$rutaControlador = ROOT . 'controllers' . DS . $controllerClass . '.php';
  		}

  		// Cargar, instanciar y despachar
  		if(is_readable($rutaControlador)){
  			require_once $rutaControlador;
  			$controller = new $controllerClass;
  			if(!is_callable(array($controller,$metodo))){
  				$metodo = 'index';
  			}
  			if(isset($args) && count($args)){
  				call_user_func_array(array($controller, $metodo), $args);
  			}else{
  				call_user_func(array($controller, $metodo));
  			}
  		}else{
  			throw new Exception('no encontrado');
  		}
  	}
  }
?>