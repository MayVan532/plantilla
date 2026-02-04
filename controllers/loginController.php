<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class loginController extends Controller

{
  private $_usuarios;

  public function __construct()
  {
    parent::__construct();
    // Asegurar que el header (secciones, logos, etc.) respete CMS_PAGE_ID también en login
    $pid = $this->resolveCmsPageId();
    $this->loadHeaderSections($pid);
    $this->_usuarios = $this->loadModel('usuarios');
  }

  /** Carga variables de footer para la sección Personas también en las vistas de login/registro. */
  protected function loadFooterPersonas(): void
  {
    try {
      try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
      if (!class_exists('CmsRepository')) {
        $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
        if (is_readable($repoPath)) { require_once $repoPath; }
      }
      $pid = $this->resolveCmsPageId();
      $sid = $this->resolveCmsSectionId('personas', $pid);
      $footerRes = CmsRepository::loadBlockByCoordsTypes([
        'id_pagina'  => $pid,
        'id_seccion' => $sid,
        'tipo_bloque'=> 'footer',
        'visible'    => 1,
      ], ['imagen','link','social','texto','correo','telefono'], 1);
      $by   = $footerRes['byType'] ?? [];
      $dbg  = $footerRes['debug'] ?? [];
      $blockId = isset($footerRes['block']->id_bloque) ? (int)$footerRes['block']->id_bloque : 0;

      $rows = [];
      if ($blockId > 0) {
        try {
          $rows = Capsule::connection('cms')
            ->table('elementos_bloques')
            ->select(['tipo','contenido','link','visible'])
            ->where('id_bloque', $blockId)
            ->where('visible', 1)
            ->orderBy('id_elemento')
            ->get()->toArray();
        } catch (\Throwable $e) { $rows = []; $dbg['rows_error'] = $e->getMessage(); }
      }

      $logo = null; $docs = []; $aviso = null; $copy = null; $tel = null; $mail = null; $addr = null; $socials = [];
      foreach ($rows as $r) {
        $tipo = strtolower(trim((string)($r->tipo ?? '')));
        $cont = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
        $lnk  = is_string($r->link ?? null) ? trim($r->link) : '';
        switch ($tipo) {
          case 'imagen':
            if ($logo === null && $cont !== '') { $logo = $cont; }
            break;
          case 'link':
            if ($cont !== '' && $lnk !== '') { $docs[] = ['t'=>$cont, 'u'=>$lnk]; }
            break;
          case 'direccion':
            if ($addr === null && $cont !== '') { $addr = $cont; }
            break;
          case 'aviso':
            if ($aviso === null && ($cont !== '' || $lnk !== '')) { $aviso = $cont !== '' ? $cont : $lnk; }
            break;
          case 'social':
            if ($cont !== '' && $lnk !== '') {
              $lnkNorm = $lnk;
              if (!preg_match('#^https?://#i', $lnkNorm)) { $lnkNorm = 'https://'.ltrim($lnkNorm, '/'); }
              $socials[strtolower($cont)] = $lnkNorm;
            }
            if (($cont === 'correo' || $cont === 'email') && !$mail) { $mail = $lnk ?: $cont; }
            if (($cont === 'telefono') && !$tel) { $tel = $lnk ?: $cont; }
            break;
          case 'texto':
            if ($aviso === null && preg_match('/aviso\s+de\s+privacidad/i', $cont)) { $aviso = $cont; break; }
            if ($copy  === null && preg_match('/copyright/i', $cont)) { $copy = $cont; break; }
            if ($addr === null && $cont !== '' && !preg_match('/\+?\d[\d\s\.-]{6,}/', $cont)) { $addr = $cont; }
            break;
          case 'telefono':
            if ($tel === null && $cont !== '') { $tel = $cont; }
            break;
          case 'correo':
            if ($mail === null && $cont !== '') { $mail = $cont; }
            break;
          case 'copyright':
            if ($copy === null && $cont !== '') { $copy = $cont; }
            break;
        }
      }

      $this->_view->debug_footer  = $dbg + ['blockId' => $blockId, 'rows' => count($rows)];
      $this->_view->footer_logo   = $logo;
      $this->_view->footer_docs   = $docs;
      $this->_view->footer_aviso  = $aviso;
      $this->_view->footer_copy   = $copy;
      $this->_view->footer_tel    = $tel;
      $this->_view->footer_mail   = $mail;
      $this->_view->footer_addr   = $addr;
      $this->_view->footer_social = $socials;
    } catch (\Throwable $e) {
      $this->_view->debug_footer = ['error' => $e->getMessage()];
    }
  }

  protected function cmsResolveIdLogin(string $titulo, ?string $tipoBloque = null): int
  {
    try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
    if (!class_exists('CmsRepository')) {
      $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
      if (is_readable($repoPath)) { require_once $repoPath; }
    }
    $pid = $this->resolveCmsPageId();
    if ($pid <= 0) { return 0; }
    $sid = $this->resolveCmsSectionId('personas', $pid);
    if ($sid <= 0) { return 0; }
    // Pestaña de login en Personas: título visible "Inicia Sesion" (según menú del header)
    $tabId = $this->resolveCmsTabId('personas', 'Inicia Sesion', $pid);
    $coords = [
      'id_pagina'  => $pid,
      'id_seccion' => $sid,
      'titulo'     => $titulo,
      'visible'    => 1,
    ];
    if ($tabId > 0) {
      $coords['id_pestana'] = $tabId;
    }
    if ($tipoBloque) {
      $coords['tipo_bloque'] = $tipoBloque;
    }
    $id = CmsRepository::resolveBlockId($coords);
    return $id ? (int)$id : 0;
  }
  public function index()
  {
    // Footer Personas dinámico también en login
    $this->loadFooterPersonas();

    // Cargar CMS de la pantalla de login (bloques 33-35)
    try {
      try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
      if (!class_exists('CmsRepository')) {
        $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
        if (is_readable($repoPath)) { require_once $repoPath; }
      }
      // 33: Banner principal (Personas > Inicia Sesion) – dinámico por página/sección/pestaña
      $id33 = $this->cmsResolveIdLogin('Banner principal', 'banner');
      $b33  = CmsRepository::loadElementsByBlockId($id33, 'imagen', 1);
      $this->_view->login_banner1 = $b33['items'][0] ?? null;
      $this->_view->login_banner1_link = CmsRepository::getFirstLinkForType($id33, 'imagen', 1);
      $this->_view->debug_bloque33 = $b33['debug'] ?? [];
      // 34: Banner secundario
      $id34 = $this->cmsResolveIdLogin('Banner secundario', 'banner');
      $b34  = CmsRepository::loadElementsByBlockId($id34, 'imagen', 1);
      $this->_view->login_banner2 = $b34['items'][0] ?? null;
      $this->_view->login_banner2_link = CmsRepository::getFirstLinkForType($id34, 'imagen', 1);
      $this->_view->debug_bloque34 = $b34['debug'] ?? [];
      // 35: Necesitas ayuda (whatsapp/link/texto)
      $id35 = $this->cmsResolveIdLogin('Necesitas ayuda', 'boton');
      $b35  = CmsRepository::loadBlockByIdTypes($id35, ['boton','link','texto','titulo'], 1);
      $this->_view->login_help = $b35['byType'] ?? [];
      $this->_view->debug_bloque35 = $b35['debug'] ?? [];
      // Enlace directo (columna link del tipo 'link') y mensaje opcional (texto/titulo)
      try { $this->_view->login_help_link = CmsRepository::getFirstLinkForType($id35, 'link', 1); } catch (\Throwable $e) { $this->_view->login_help_link = null; }
      $msg35 = $b35['byType']['texto'][0] ?? ($b35['byType']['titulo'][0] ?? null);
      $this->_view->login_help_msg = is_string($msg35) ? trim($msg35) : null;
    } catch (\Throwable $e) {}

    if ($this->getInt('enviar') == 1) {
      $this->_view->datos = $_POST;
      $this->_view->datos['email'] = $this->getPostParam('email');

      //validar correo
      if (!$this->validarEmail($this->getPostParam('email'))) {
        $this->_view->_error = 'La dirección de correo electrónico es inválida';
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      //validar password			
      if (!$this->getPostParam('password')) {
        $this->_view->_error = 'Debes ingresar tu contraseña';
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }
      $data = 0;
      $password = Hash::getHash('sha1', $this->getPostParam('password'), HASH_KEY);
      $row1 = $this->_usuarios->verificar_usuario(
        $this->getPostParam('email'),
        $password
      );
      $ca = count($row1);
      if ($ca > 0) {
        $data = 1;
      }
      if ($data == 0) {
        $this->_view->_error = 'Nombre de usuario y / o contraseña incorrectos';
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      Session::set('autenticado', true);
      Session::set('tipo_usuario', $row1[0]['tipo_usuario']);
      Session::set('cv_usuario', $row1[0]['cv_usuario']);
      Session::set('nombre_usuario', $row1[0]['nombre_usuario']);
      Session::set('email_usuario', $this->getPostParam('email'));
      Session::set('tiempo', time());
      $op_extra = new CORE;
      $tokenrf = $op_extra->cadena_aleatoria(15);
      Session::set('tokencsrf', $tokenrf);
      // Después de iniciar sesión, llevar al nuevo panel de usuarios
      $this->redireccionar("personas/usuarios");
    }
    $vistas = array('index');
    $this->_view->renderizar($vistas);
  }


  public function registro()
  {
    // Footer Personas dinámico también en registro
    $this->loadFooterPersonas();

    // Cargar mismos banners y bloque de ayuda que en index()
    try {
      try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
      if (!class_exists('CmsRepository')) {
        $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
        if (is_readable($repoPath)) { require_once $repoPath; }
      }
      // 33: Banner principal
      $id33 = $this->cmsResolveIdLogin('Banner principal', 'banner');
      $b33  = CmsRepository::loadElementsByBlockId($id33, 'imagen', 1);
      $this->_view->login_banner1 = $b33['items'][0] ?? null;
      $this->_view->login_banner1_link = CmsRepository::getFirstLinkForType($id33, 'imagen', 1);
      $this->_view->debug_bloque33 = $b33['debug'] ?? [];
      // 34: Banner secundario
      $id34 = $this->cmsResolveIdLogin('Banner secundario', 'banner');
      $b34  = CmsRepository::loadElementsByBlockId($id34, 'imagen', 1);
      $this->_view->login_banner2 = $b34['items'][0] ?? null;
      $this->_view->login_banner2_link = CmsRepository::getFirstLinkForType($id34, 'imagen', 1);
      $this->_view->debug_bloque34 = $b34['debug'] ?? [];
      // 35: Necesitas ayuda (whatsapp/link/texto)
      $id35 = $this->cmsResolveIdLogin('Necesitas ayuda', 'boton');
      $b35  = CmsRepository::loadBlockByIdTypes($id35, ['boton','link','texto','titulo'], 1);
      $this->_view->login_help = $b35['byType'] ?? [];
      $this->_view->debug_bloque35 = $b35['debug'] ?? [];
      try { $this->_view->login_help_link = CmsRepository::getFirstLinkForType($id35, 'link', 1); } catch (\Throwable $e) { $this->_view->login_help_link = null; }
      $msg35 = $b35['byType']['texto'][0] ?? ($b35['byType']['titulo'][0] ?? null);
      $this->_view->login_help_msg = is_string($msg35) ? trim($msg35) : null;
    } catch (\Throwable $e) {}

    $vistas = array('registro');
    $this->_view->renderizar($vistas);
  }


  public function cerrar()
  {
    Session::destroy();
    $this->redireccionar("");
  }
}

