<?php
use Illuminate\Database\Capsule\Manager as Capsule;
/*
 * nota  protected, sólo desde la misma clase, desde las clases que hereden de ella y desde las clases parent.
 */
 abstract class Controller{
	protected $_view;
	protected $_request;
	private $_registry;
	private static $DB_INIT = false;
	private static $CMS_CLIENTS_CFG = null;
	private static $CMS_SECTIONS_CACHE = [];
	
	//el objeto view ya lo tenemos disponible en el controlador pricipal
	public function __construct()
	{
		//si no esta alamacenada la instancia
		$this->_registry= Registry::getInstancia();
		$this->_view= new View(new Request);
		$this->_request = $this->_registry->_request;
		// Asegurar inicialización global de Eloquent/Capsule una sola vez
		$this->bootDatabase();
		
	}

	/**
	 * Carga flags para el header (Personas / Empresas / Likecheck) leyendo secciones_cms por id_pagina.
	 * Deja todos en true si la tabla no existe o hay error, para no romper el menú.
	 */
	protected function loadHeaderSections(int $cmsPageId = 1): void
	{
		// Valores por defecto: todo visible
		$showPersonas  = true;
		$showEmpresas  = true;
		$showLikecheck = true;
		$sectionsMap   = [];
		$secIdPersonas = null;
		$secIdEmpresas = null;
		$secIdLike     = null;
		try {
			$rows = Capsule::connection('cms')
				->table('secciones_cms')
				->select(['id_seccion','nombre','visible'])
				->where('id_pagina', $cmsPageId)
				->get()->toArray();
			if ($rows) {
				$showPersonas = $showEmpresas = $showLikecheck = false;
				$anyVisible   = false;
				foreach ($rows as $r) {
					$sid = (int)($r->id_seccion ?? 0);
					$vis = (int)($r->visible ?? 1);
					if ($vis !== 1) { continue; }
					$anyVisible = true;
					$name = strtolower(trim((string)($r->nombre ?? '')));
					if ($name !== '') {
						$sectionsMap[$name] = $sid;
						if ($name === 'personas')  { $secIdPersonas = $sid; $showPersonas  = true; }
						if ($name === 'empresas')  { $secIdEmpresas = $sid; $showEmpresas  = true; }
						if ($name === 'likecheck') { $secIdLike     = $sid; $showLikecheck = true; }
					}
				}
				// Si no hubo NI UNA fila visible para esta página, no romper el layout: mostrar todo
				if (!$anyVisible) {
					$showPersonas = $showEmpresas = $showLikecheck = true;
				}
			}
		} catch (\Throwable $e) {
			// Silencioso: dejamos todos en true para no romper layout
		}
		if (!empty($sectionsMap)) {
			self::$CMS_SECTIONS_CACHE[$cmsPageId] = $sectionsMap;
		}
		// Exponer ids de seccion más usados al header/footer (con fallback por convención)
		if ($secIdPersonas === null) { $secIdPersonas = $this->resolveCmsSectionId('personas', $cmsPageId); }
		if ($secIdEmpresas === null) { $secIdEmpresas = $this->resolveCmsSectionId('empresas', $cmsPageId); }
		if ($secIdLike     === null) { $secIdLike     = $this->resolveCmsSectionId('likecheck', $cmsPageId); }
		$this->_view->cms_page_id   = $cmsPageId;
		$this->_view->secIdPersonas = $secIdPersonas;
		$this->_view->secIdEmpresas = $secIdEmpresas;
		$this->_view->secIdLike     = $secIdLike;
		$this->_view->showPersonas  = $showPersonas;
		$this->_view->showEmpresas  = $showEmpresas;
		$this->_view->showLikecheck = $showLikecheck;
	}

	protected function getCmsClientsConfig(): array
	{
		if (self::$CMS_CLIENTS_CFG !== null) {
			return is_array(self::$CMS_CLIENTS_CFG) ? self::$CMS_CLIENTS_CFG : [];
		}
		$path = ROOT . 'application' . DS . 'CmsClients.php';
		try {
			if (is_readable($path)) {
				$cfg = require $path;
				self::$CMS_CLIENTS_CFG = is_array($cfg) ? $cfg : [];
				return self::$CMS_CLIENTS_CFG;
			}
		} catch (\Throwable $e) {
		}
		self::$CMS_CLIENTS_CFG = [];
		return [];
	}

	protected function resolveCmsPageId(int $fallback = 1): int
	{
		// Siempre usar SOLO la constante global CMS_PAGE_ID.
		// Si está mal definida (<=0 o sin definir), devolvemos 0 y los controladores
		// no encontrarán contenido (mejor vacío que una página equivocada).
		if (defined('CMS_PAGE_ID')) {
			return (int)CMS_PAGE_ID;
		}
		return 0;
	}

	protected function resolveCmsSectionId(string $logicalName, ?int $cmsPageId = null): int
	{
		$logical = strtolower(trim($logicalName));
		// 1) Cache poblado desde loadHeaderSections
		if (isset(self::$CMS_SECTIONS_CACHE[$cmsPageId]) && is_array(self::$CMS_SECTIONS_CACHE[$cmsPageId])) {
			$map = self::$CMS_SECTIONS_CACHE[$cmsPageId];
			if (isset($map[$logical])) {
				return (int)$map[$logical];
			}
		}
		// 2) Consultar secciones_cms por nombre
		try {
			$rows = Capsule::connection('cms')
				->table('secciones_cms')
				->select(['id_seccion','nombre','visible'])
				->where('id_pagina', $cmsPageId)
				->where('visible', 1)
				->get()->toArray();
			$map = [];
			foreach ($rows as $r) {
				$name = strtolower(trim((string)($r->nombre ?? '')));
				if ($name === '') { continue; }
				$map[$name] = (int)($r->id_seccion ?? 0);
			}
			if (!empty($map)) {
				self::$CMS_SECTIONS_CACHE[$cmsPageId] = $map;
				if (isset($map[$logical])) { return (int)$map[$logical]; }
			}
		} catch (\Throwable $e) {
		}
		// Sin fallback: si no existe la sección en secciones_cms para esa página,
		// devolvemos 0 para que no se cargue contenido cruzado.
		return 0;
	}

	/**
	 * Resuelve dinámicamente el id_pestana (tab) para una página CMS dada.
	 * Usa: CMS_PAGE_ID -> id_pagina, nombre lógico de sección -> id_seccion, y título visible de la pestaña.
	 * Si no encuentra coincidencia exacta, devuelve 0 (no hay contenido para esa pestaña).
	 */
	protected function resolveCmsTabId(string $sectionLogical, string $tabTitle, ?int $cmsPageId = null): int
	{
		$pid = $cmsPageId ?? $this->resolveCmsPageId();
		if ($pid <= 0) { return 0; }
		$sid = $this->resolveCmsSectionId($sectionLogical, $pid);
		if ($sid <= 0) { return 0; }
		try {
			$row = Capsule::connection('cms')
				->table('pestana_cms')
				->select(['id_pestana'])
				->where('id_pagina', $pid)
				->where('id_seccion', $sid)
				->where('visible', 1)
				->where('titulo', $tabTitle)
				->orderBy('orden_menu', 'asc')
				->first();
			return $row ? (int)($row->id_pestana ?? 0) : 0;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	/**
	 * Resuelve dinámicamente el id_subpestana para una pestaña de una sección y página CMS.
	 * Usa: sección lógica + título de pestaña + título de subpestaña. Si no encuentra, devuelve 0.
	 */
	protected function resolveCmsSubtabId(string $sectionLogical, string $tabTitle, string $subtabTitle, ?int $cmsPageId = null): int
	{
		$pid   = $cmsPageId ?? $this->resolveCmsPageId();
		if ($pid <= 0) { return 0; }
		$tabId = $this->resolveCmsTabId($sectionLogical, $tabTitle, $pid);
		if ($tabId <= 0) { return 0; }
		try {
			$row = Capsule::connection('cms')
				->table('subpestanas_cms')
				->select(['id_subpestana'])
				->where('id_pestana', $tabId)
				->where('visible', 1)
				->where('titulo', $subtabTitle)
				->orderBy('orden', 'asc')
				->first();
			return $row ? (int)($row->id_subpestana ?? 0) : 0;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	    protected function bootDatabase(){
        if (self::$DB_INIT) { return; }
        $capsule = new Capsule;
        $hasPrimary = defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS') && (DB_NAME !== null) && (DB_NAME !== '');
        $hasCms = defined('CMS_DB_HOST') && defined('CMS_DB_NAME') && defined('CMS_DB_USER') && defined('CMS_DB_PASS');

        // 1) CMS como conexión primaria (default) y alias 'cms' cuando exista
        if ($hasCms) {
            try {
                $cmsConfig = [
                 'driver' => 'mysql',
                 'host' => CMS_DB_HOST,
                 'database' => CMS_DB_NAME,
                 'username' => CMS_DB_USER,
                 'password' => CMS_DB_PASS,
                 'charset' => 'utf8mb4',
                 'collation' => 'utf8mb4_unicode_ci',
                 'prefix' => '',
                 'strict' => false,
                ];
                // default
                $capsule->addConnection($cmsConfig);
                // alias explícito
                $capsule->addConnection($cmsConfig, 'cms');
            } catch (\Throwable $e) {
                // si CMS falla, seguimos y probamos con primaria
            }
        }

        // 2) Conexión likephoneqa como secundaria opcional (alias 'app')
        if ($hasPrimary) {
            try {
                $appConfig = [
                 'driver' =>'mysql',
                 'host' => DB_HOST,
                 'database' => DB_NAME,
                 'username' => DB_USER,
                 'password' => DB_PASS,
                 'charset' => 'utf8',
                 'collation' => 'utf8_unicode_ci',
                 'prefix' => '',
                 'strict' => false,
                ];
                if ($hasCms) {
                    // si ya hay CMS como default, registrar likephoneqa solo con alias
                    $capsule->addConnection($appConfig, 'app');
                } else {
                    // sin CMS, usar esta como default
                    $capsule->addConnection($appConfig);
                }
            } catch (\Throwable $e) {
                // omitir fallo de secundaria
            }
        }
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        self::$DB_INIT = true;
    }
	
   //no podra ser instanseada
   //obliga que todas las clases que hereden de controller implementen un metodo index por obligacion
   abstract public function index();
	//metodo que se asigne por defecto cuando no se  envie nada o por error
    protected function loadModel($modelo, $modulo=false)
    {
        $modelo = $modelo . 'Model';
        $rutaModelo = ROOT . 'models' . DS . $modelo . '.php';
		//si 1= usara cada modulo sus modelos 2= general
		if(MODELOS==1){
		//sino se envia un modulo
		 if(!$modulo){
            $modulo = $this->_request->getModulo();
        }
		 if($modulo){
           if($modulo != 'default'){
               $rutaModelo = ROOT . 'modules' . DS . $modulo . DS . 'models' . DS . $modelo . '.php';
           } 
         }
		}
        if(is_readable($rutaModelo)){
            require_once $rutaModelo;
            $modelo = new $modelo;
            return $modelo;
        }
        else {
            throw new Exception('Error de modelo');
        }
    }
	//cargador de librerias
	protected function getLibrary($libreria){
	$rutaLibreria = ROOT . 'libs' . DS . $libreria . '.php';
	if(is_readable($rutaLibreria)){
		require_once $rutaLibreria;	
      }else{
      throw new Exception('Error de libreria');
	  }
    }
	
	//Filtrar texto Metodo POST
	protected function getTexto($clave)
	{
		if(isset($_POST[$clave])&& !empty($_POST[$clave]) ){
			$_POST[$clave]=htmlspecialchars($_POST[$clave], ENT_QUOTES);
			return $_POST[$clave];
		}
		
		return '';
	}
	
	//validar numeros enteros
	protected function getInt($clave){
			if(isset($_POST[$clave])&& !empty($_POST[$clave]) ){
			$_POST[$clave]=filter_input(INPUT_POST, $clave,FILTER_VALIDATE_INT);
			return $_POST[$clave];
		    }
		return 0;	
	 }
	//funcion Redireccionar
	protected function redireccionar($ruta=false){
	  if($ruta){
	  	header('location:'.BASE_URL.$ruta);
		  exit;
	  }else{
	  	header('location:'.BASE_URL);
		exit;
	  }
	}
	
	//FILTRAR ENTERO 
	protected function filtrarInt($int){
		$int =(int) $int;
		if(is_int($int)){
			return $int;
		}else{
			return 0;
		}
	}
	
	//obtener POST sin filtros
	protected function getPostParam($clave)
    {
        if(isset($_POST[$clave])){
            return $_POST[$clave];
        }
    }
	
	
   //Evitar injecciones SQL
    protected function getSql($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = strip_tags($_POST[$clave]);
            
            if(!get_magic_quotes_gpc()){
            	//remplazar esto en futuras versiones de php->php7 
                $_POST[$clave] = mysql_escape_string($_POST[$clave]);
            }
            return trim($_POST[$clave]);
        }
    }
     //validar cadena alfanumerico
     protected function getAlphaNum($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = (string) preg_replace('/[^A-Z0-9_]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
        
    }
	//usuario en alfanumerico
   protected function getAlphaUser($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = (string) preg_replace('/[^.A-Z0-9_]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
        
    }
	//obtener cadena sin caracteres que permitan un ataque xss o sql injection
    protected function getCadena($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = (string) preg_replace('/[^A-Z0-9_áéíóúÁÉÍÓÚÑñ\s\-\.\%\,]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
        
    }
   //funcion obtener coordenadas. (sin uso)
  protected function getGps_coordenadas($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = (string) preg_replace('/[^A-Z0-9._áéíóúÁÉÍÓÚÑñ\-]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
        
    }
   //validar numero de tipo double
  protected function getDouble($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = (string) preg_replace('/[^0-9\.]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
        
    }	
	//validar hora
    protected function getHora($clave)
    {
        if(isset($_POST[$clave]) && !empty($_POST[$clave])){
            $_POST[$clave] = (string) preg_replace('/[\:][^A-Z0-9_áéíóúÁÉÍÓÚÑñ\s]/i', '', $_POST[$clave]);
            return trim($_POST[$clave]);
        }
          
    }
    
	 //validar correo electronico
   public function validarEmail($email)
       {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return false;
        }
        
        return true;
    }
    
    
    //validar token
     public function tokencsrf(){
         //si el token no es igual al del sistema eliminar
       if($_SERVER["REQUEST_METHOD"]=="POST"){
         if(Session::get('tokencsrf')!=$_SERVER['HTTP_X_CSRF_TOKEN']){
             exit; 
         }else{
         }
        }
     }
 }
?>