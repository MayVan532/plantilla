<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class loginController extends Controller

{
  private $_usuarios;

  protected function getWlApiBaseUrl(): string
  {
    $baseUrl = null;
    if (defined('WL_API_BASE_URL')) {
      $baseUrl = is_string(constant('WL_API_BASE_URL')) ? trim((string)constant('WL_API_BASE_URL')) : null;
    }
    if (!$baseUrl) {
      $env = getenv('WL_API_BASE_URL');
      if (is_string($env) && trim($env) !== '') { $baseUrl = trim($env); }
    }
    if (!$baseUrl) {
      $baseUrl = 'https://apis.likephone.mx/api/v1/whitelabels/generic';
    }
    return rtrim((string)$baseUrl, '/');
  }

  protected function getOtpConfig(): array
  {
    $baseUrl = null;
    $apiKey  = null;

    if (defined('OTP_BASE_URL')) {
      $baseUrl = is_string(constant('OTP_BASE_URL')) ? trim((string)constant('OTP_BASE_URL')) : null;
    }
    if (defined('OTP_API_KEY')) {
      $apiKey = is_string(constant('OTP_API_KEY')) ? trim((string)constant('OTP_API_KEY')) : null;
    }

    if (!$baseUrl) {
      $env = getenv('OTP_BASE_URL');
      if (is_string($env) && trim($env) !== '') { $baseUrl = trim($env); }
    }
    if (!$apiKey) {
      $env = getenv('OTP_API_KEY');
      if (is_string($env) && trim($env) !== '') { $apiKey = trim($env); }
    }

    if ($baseUrl) { $baseUrl = rtrim($baseUrl, '/'); }

    return [
      'base_url' => $baseUrl,
      'api_key'  => $apiKey,
    ];
  }

  protected function callOtpApi(string $url, array $headers, ?array $payload = null): array
  {
    $ch = null;
    try {
      $ch = curl_init();
      if ($ch === false) {
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'curl_init_failed'];
      }

      $baseHeaders = [
        'Accept: application/json',
        'Accept-Encoding: gzip',
        'User-Agent: Mozilla/5.0',
        'Expect:',
      ];
      foreach ($headers as $h) {
        if (is_string($h) && trim($h) !== '') {
          $baseHeaders[] = $h;
        }
      }

      $opt = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $baseHeaders,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
      ];

      if (is_array($payload)) {
        $json = json_encode($payload);
        if ($json === false) {
          return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'json_encode_error:'.json_last_error_msg()];
        }
        $opt[CURLOPT_POSTFIELDS] = $json;
        $baseHeaders[] = 'Content-Type: application/json';
      }

      curl_setopt_array($ch, $opt);
      $raw = curl_exec($ch);
      if ($raw === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'curl_error:'.$code.':'.$err];
      }

      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      $respHeaders = '';
      $respBody = '';
      if (is_int($headerSize) && $headerSize > 0) {
        $respHeaders = substr($raw, 0, $headerSize);
        $respBody = substr($raw, $headerSize);
      } else {
        $respBody = $raw;
      }

      $ok = $status >= 200 && $status < 300;
      $trimBody = is_string($respBody) ? trim($respBody) : '';
      if ($trimBody === '') {
        return ['ok' => $ok, 'status' => $status, 'body' => '', 'error' => $ok ? null : 'http_error', 'raw_headers' => $respHeaders];
      }

      $data = json_decode($respBody, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        return ['ok' => $ok, 'status' => $status, 'body' => $data, 'error' => $ok ? null : 'http_error', 'raw_headers' => $respHeaders];
      }

      return ['ok' => $ok, 'status' => $status, 'body' => $respBody, 'error' => $ok ? null : 'http_error', 'raw_headers' => $respHeaders];
    } catch (\Throwable $e) {
      return ['ok' => false, 'status' => null, 'body' => null, 'error' => $e->getMessage()];
    } finally {
      if ($ch) { @curl_close($ch); }
    }
  }

  protected function otpSend(string $msisdn): array
  {
    $cfg = $this->getOtpConfig();
    $debug = [
      'step' => 'otp_send',
      'msisdn' => $msisdn,
      'base_url' => $cfg['base_url'] ?? null,
    ];

    $baseUrl = $cfg['base_url'] ?? null;
    $apiKey  = $cfg['api_key'] ?? null;
    if (!$baseUrl || !$apiKey) {
      $debug['error'] = 'missing_otp_config';
      return [false, $debug];
    }

    $url = $baseUrl.'/v3/wlapp/otp/'.rawurlencode($msisdn);
    $debug['url'] = $url;
    $res = $this->callOtpApi($url, ['x-api-key: '.$apiKey], null);
    $debug['http_status'] = $res['status'] ?? null;
    $debug['error'] = $res['error'] ?? null;
    $debug['raw_body'] = $res['body'] ?? null;

    if (!empty($res['ok'])) {
      return [true, $debug];
    }
    return [false, $debug];
  }

  protected function otpValid(string $msisdn, string $codigo): array
  {
    $cfg = $this->getOtpConfig();
    $debug = [
      'step' => 'otp_valid',
      'msisdn' => $msisdn,
      'base_url' => $cfg['base_url'] ?? null,
    ];

    $baseUrl = $cfg['base_url'] ?? null;
    $apiKey  = $cfg['api_key'] ?? null;
    if (!$baseUrl || !$apiKey) {
      $debug['error'] = 'missing_otp_config';
      return [false, $debug];
    }

    $url = $baseUrl.'/v3/wlapp/otp/'.rawurlencode($msisdn).'/valid';
    $debug['url'] = $url;
    $res = $this->callOtpApi($url, ['x-api-key: '.$apiKey, 'Content-Type: application/json'], ['codigo' => $codigo]);
    $debug['http_status'] = $res['status'] ?? null;
    $debug['error'] = $res['error'] ?? null;
    $debug['raw_body'] = $res['body'] ?? null;

    if (!empty($res['ok'])) {
      return [true, $debug];
    }
    return [false, $debug];
  }

  protected function getEmpresaApiCredentialsForCurrentPage(): ?array
  {
    try {
      $pid = $this->resolveCmsPageId();
      if ($pid <= 0) { return null; }

      $row = Capsule::connection('cms')
        ->table('pagina_cms as p')
        ->leftJoin('empresas_cms as e', 'p.id_empresa', '=', 'e.id_empresa')
        ->select(['p.id_pagina','p.id_empresa','p.nombre as pagina_nombre','e.nombre as empresa_nombre','e.client_id','e.client_secret'])
        ->where('p.id_pagina', $pid)
        ->first();

      if (!$row) { return null; }

      $clientId     = is_string($row->client_id ?? null) ? trim($row->client_id) : '';
      $clientSecret = is_string($row->client_secret ?? null) ? trim($row->client_secret) : '';
      if ($clientId === '' || $clientSecret === '') {
        return null;
      }

      return [
        'id_pagina'      => (int)($row->id_pagina ?? 0),
        'id_empresa'     => (int)($row->id_empresa ?? 0),
        'pagina_nombre'  => (string)($row->pagina_nombre ?? ''),
        'empresa_nombre' => (string)($row->empresa_nombre ?? ''),
        'client_id'      => $clientId,
        'client_secret'  => $clientSecret,
      ];
    } catch (\Throwable $e) {
      return null;
    }
  }

  protected function registerUserApi(string $token, array $payload): array
  {
    $url = $this->getWlApiBaseUrl().'/account/registerUser';
    $debug = [
      'step' => 'register_user',
      'url'  => $url,
    ];
    try {
      $headers = ['Authorization: Bearer '.$token];
      $res = $this->callJsonApi($url, $headers, $payload);
      $debug['http_status'] = $res['status'] ?? null;
      $debug['error'] = $res['error'] ?? null;
      if (array_key_exists('body', $res)) {
        $debug['raw_body'] = $res['body'];
      }
      if (isset($res['raw_headers']) && is_string($res['raw_headers'])) {
        $debug['raw_headers'] = $res['raw_headers'];
      }
      if (!empty($res['ok']) && is_array($res['body'])) {
        return [$res['body'], $debug];
      }
      return [null, $debug];
    } catch (\Throwable $e) {
      $debug['exception'] = $e->getMessage();
      return [null, $debug];
    }
  }

  protected function callJsonApi(string $url, array $headers, array $payload): array
  {
    $ch = null;
    try {
      $ch = curl_init();
      if ($ch === false) {
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'curl_init_failed'];
      }
      $json = json_encode($payload);
      if ($json === false) {
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'json_encode_error:'.json_last_error_msg()];
      }
      $baseHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Accept-Encoding: gzip',
        'User-Agent: Mozilla/5.0',
        'Expect:',
      ];
      foreach ($headers as $h) {
        if (is_string($h) && trim($h) !== '') {
          $baseHeaders[] = $h;
        }
      }
      curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $baseHeaders,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
      ]);
      $raw = curl_exec($ch);
      if ($raw === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'curl_error:'.$code.':'.$err];
      }
      $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
      $respHeaders = '';
      $respBody = '';
      if (is_int($headerSize) && $headerSize > 0) {
        $respHeaders = substr($raw, 0, $headerSize);
        $respBody = substr($raw, $headerSize);
      } else {
        $respBody = $raw;
      }
      $info = curl_getinfo($ch);
      if (!is_string($respBody) || trim($respBody) === '') {
        return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'empty_response_body', 'raw_headers' => $respHeaders, 'curl_info' => $info];
      }
      $data = json_decode($respBody, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return ['ok' => false, 'status' => $status, 'body' => $respBody, 'error' => 'json_decode_error:'.json_last_error_msg(), 'raw_headers' => $respHeaders, 'curl_info' => $info];
      }
      $ok = $status >= 200 && $status < 300;
      return ['ok' => $ok, 'status' => $status, 'body' => $data, 'error' => $ok ? null : 'http_error', 'raw_headers' => $respHeaders, 'curl_info' => $info];
    } catch (\Throwable $e) {
      return ['ok' => false, 'status' => null, 'body' => null, 'error' => $e->getMessage()];
    } finally {
      if ($ch) { @curl_close($ch); }
    }
  }

  protected function fetchWlToken(array $creds): array
  {
    $debug = ['step' => 'auth-token', 'url' => $this->getWlApiBaseUrl().'/auth/token'];
    try {
      $payload = [
        'client_id'     => $creds['client_id'] ?? '',
        'client_secret' => $creds['client_secret'] ?? '',
      ];
      $res = $this->callJsonApi($debug['url'], [], $payload);
      $debug['http_status'] = $res['status'];
      $debug['error'] = $res['error'];
      if (!$res['ok'] || !is_array($res['body'])) {
        $debug['raw_body'] = $res['body'];
        return [null, $debug];
      }
      $body = $res['body'];
      $token = null;
      if (isset($body['data']['token']) && is_string($body['data']['token'])) {
        $token = trim($body['data']['token']);
      }
      if ($token === null || $token === '') {
        $debug['raw_body'] = $body;
        return [null, $debug];
      }
      $debug['token_len'] = strlen($token);
      return [$token, $debug];
    } catch (\Throwable $e) {
      $debug['exception'] = $e->getMessage();
      return [null, $debug];
    }
  }

  protected function authenticateUserApi(string $token, string $telefono, string $password): array
  {
    $url = $this->getWlApiBaseUrl().'/account/authenticateUser';
    $debug = [
      'step' => 'authenticate_user',
      'url'  => $url,
    ];
    try {
      $headers = ['Authorization: Bearer '.$token];
      $payload = [
        'numero_telefono' => $telefono,
        'password'        => $password,
      ];
      $res = $this->callJsonApi($url, $headers, $payload);
      $debug['http_status'] = $res['status'] ?? null;
      $debug['error'] = $res['error'] ?? null;
      if (array_key_exists('body', $res)) {
        $debug['raw_body'] = $res['body'];
      }
      if (isset($res['raw_headers']) && is_string($res['raw_headers'])) {
        $debug['raw_headers'] = $res['raw_headers'];
      }
      if (isset($res['curl_info']) && is_array($res['curl_info'])) {
        $ci = $res['curl_info'];
        $debug['curl_info'] = [
          'content_type' => $ci['content_type'] ?? null,
          'http_code' => $ci['http_code'] ?? null,
          'header_size' => $ci['header_size'] ?? null,
          'request_size' => $ci['request_size'] ?? null,
          'size_download' => $ci['size_download'] ?? null,
          'redirect_count' => $ci['redirect_count'] ?? null,
          'url' => $ci['url'] ?? null,
        ];
      }
      if (is_array($res['body'])) {
        $body = $res['body'];
        $debug['success'] = $body['success'] ?? null;
        return [$body, $debug];
      }
      return [null, $debug];
    } catch (\Throwable $e) {
      $debug['exception'] = $e->getMessage();
      return [null, $debug];
    }
  }

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
      $telefono = trim((string)$this->getPostParam('telefono'));
      if ($telefono === '') {
        $telefono = trim((string)$this->getPostParam('email'));
      }
      $passRaw  = (string)$this->getPostParam('password');

      if ($telefono === '') {
        $this->_view->_error = 'Debes ingresar tu número de teléfono';
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      if (!$passRaw) {
        $this->_view->_error = 'Debes ingresar tu contraseña';
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      $planesSocioDebug = [
        'step' => 'init',
        'page_id' => $this->resolveCmsPageId(),
      ];
      $creds = $this->getEmpresaApiCredentialsForCurrentPage();
      $planesSocioDebug['empresa'] = $creds ? [
        'id_pagina' => $creds['id_pagina'] ?? null,
        'id_empresa' => $creds['id_empresa'] ?? null,
        'pagina_nombre' => $creds['pagina_nombre'] ?? null,
        'empresa_nombre' => $creds['empresa_nombre'] ?? null,
      ] : null;
      if (!$creds) {
        $this->_view->_error = 'Credenciales API no configuradas para este cliente';
        $this->_view->debug_auth_api = $planesSocioDebug;
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      [$token, $authDbg] = $this->fetchWlToken($creds);
      $planesSocioDebug['auth'] = $authDbg;
      if (!$token) {
        $this->_view->_error = 'No se pudo obtener token';
        $this->_view->debug_auth_api = $planesSocioDebug;
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      [$body, $loginDbg] = $this->authenticateUserApi($token, $telefono, $passRaw);
      $planesSocioDebug['login'] = $loginDbg;
      $this->_view->debug_auth_api = $planesSocioDebug;

      if (!$body || !is_array($body) || empty($body['success'])) {
        $msg = 'Nombre de usuario y / o contraseña incorrectos';
        if (is_array($body) && isset($body['error']) && is_array($body['error']) && isset($body['error']['message']) && is_string($body['error']['message']) && trim($body['error']['message']) !== '') {
          $msg = trim($body['error']['message']);
        } elseif (is_array($body) && isset($body['mensaje']) && is_string($body['mensaje']) && trim($body['mensaje']) !== '') {
          $msg = trim($body['mensaje']);
        }
        $this->_view->_error = $msg;
        $vistas = array('index');
        $this->_view->renderizar($vistas);
        exit;
      }

      Session::set('autenticado', true);
      Session::set('tiempo', time());
      Session::set('api_token', $token);
      Session::set('email_usuario', $telefono);
      if (isset($body['data']) && is_array($body['data'])) {
        if (isset($body['data']['nombre']) && is_string($body['data']['nombre'])) {
          Session::set('nombre_usuario', $body['data']['nombre']);
        }
        if (isset($body['data']['id'])) {
          Session::set('cv_usuario', $body['data']['id']);
        }
      }
      $op_extra = new CORE;
      $tokenrf = $op_extra->cadena_aleatoria(15);
      Session::set('tokencsrf', $tokenrf);
      $this->redireccionar("usuarios");
    }
    $vistas = array('index');
    $this->_view->renderizar($vistas);
  }


  public function registro()
  {
    $debugEnabled = (isset($_GET['debugcms']) && $_GET['debugcms'] == '1');
    $resetGet = isset($_GET['reset']) ? (int)$_GET['reset'] : 0;
    if ($resetGet === 1 || $this->getInt('reset_register') == 1) {
      if ($debugEnabled) {
        $this->_view->debug_pending_register = [
          'action' => 'reset_before',
          'pending_register' => Session::get('pending_register'),
        ];
      }
      Session::destroy('pending_register');
      if ($debugEnabled) {
        $this->_view->debug_pending_register_after = [
          'action' => 'reset_after',
          'pending_register' => Session::get('pending_register'),
        ];
      }
    }

    // Si sólo estás entrando a /registro (GET) sin enviar formularios, no queremos quedarnos
    // atorados en el paso OTP por un pending_register viejo.
    $isGet = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'GET');
    if ($isGet && $resetGet !== 1 && !$debugEnabled) {
      Session::destroy('pending_register');
    }
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

    $this->_view->_error = null;
    $this->_view->_success = null;

    $pending = Session::get('pending_register');
    if (!is_array($pending)) { $pending = null; }
    $this->_view->pending_register = $pending;

    if ($this->getInt('resend_otp') == 1) {
      if (!is_array($pending)) {
        $this->_view->_error = 'Primero completa el formulario para solicitar el código.';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $tel = preg_replace('/\D+/', '', (string)($pending['numero_telefono'] ?? ''));
      if (strlen($tel) !== 10) {
        $this->_view->_error = 'Teléfono inválido.';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $otpDebug = [];
      [$otpOk, $otpDbg] = $this->otpSend($tel);
      $otpDebug['resend'] = $otpDbg;
      $this->_view->debug_otp = $otpDebug;

      if (!$otpOk) {
        $this->_view->_error = 'No se pudo reenviar el código. Intenta de nuevo.';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $pending['otp_sent_at'] = time();
      Session::set('pending_register', $pending);
      $this->_view->pending_register = $pending;
      $this->_view->_success = 'Te reenviamos el código.';
      $vistas = array('registro');
      $this->_view->renderizar($vistas);
      exit;
    }

    if ($this->getInt('enviar_otp') == 1) {
      $data = $_POST;
      $nombre = trim((string)($data['nombre'] ?? ''));
      $apPat  = trim((string)($data['apellido_paterno'] ?? ''));
      $apMat  = trim((string)($data['apellido_materno'] ?? ''));
      $email  = trim((string)($data['email'] ?? ''));
      $pass   = (string)($data['password'] ?? '');
      $pass2  = (string)($data['password2'] ?? '');
      $tel    = preg_replace('/\D+/', '', (string)($data['telefono'] ?? ''));
      $conv   = trim((string)($data['codigo_convenio'] ?? ''));

      $this->_view->datos = $data;

      if ($nombre === '' || $apPat === '' || $email === '' || $pass === '' || $tel === '') {
        $this->_view->_error = 'Completa todos los campos obligatorios';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->_view->_error = 'Correo inválido';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }
      if (strlen($tel) !== 10) {
        $this->_view->_error = 'El teléfono debe tener 10 dígitos';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }
      if ($pass !== $pass2) {
        $this->_view->_error = 'Las contraseñas no coinciden';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }
      if (strlen($pass) < 8) {
        $this->_view->_error = 'La contraseña debe tener mínimo 8 caracteres';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $otpDebug = [];
      [$otpOk, $otpDbg] = $this->otpSend($tel);
      $otpDebug['send'] = $otpDbg;
      $this->_view->debug_otp = $otpDebug;

      if (!$otpOk) {
        $this->_view->_error = 'No se pudo enviar el código. Intenta de nuevo.';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $pendingRegister = [
        'nombre' => $nombre,
        'apellido_paterno' => $apPat,
        'apellido_materno' => $apMat,
        'email' => $email,
        'password' => $pass,
        'numero_telefono' => $tel,
        'codigo_convenio' => $conv,
        'otp_sent_at' => time(),
      ];
      Session::set('pending_register', $pendingRegister);

      $this->_view->pending_register = $pendingRegister;
      $this->_view->_success = 'Te enviamos un código. Ingrésalo para continuar.';
      $vistas = array('registro');
      $this->_view->renderizar($vistas);
      exit;
    }

    if ($this->getInt('validar_otp') == 1) {
      $codigo = trim((string)$this->getPostParam('codigo'));
      $pending = Session::get('pending_register');
      if (!is_array($pending)) {
        $this->_view->_error = 'Primero solicita el código.';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $sentAt = isset($pending['otp_sent_at']) ? (int)$pending['otp_sent_at'] : 0;
      if ($sentAt > 0 && (time() - $sentAt) > 300) {
        $this->_view->_error = 'El código expiró. Solicita uno nuevo.';
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      if ($codigo === '') {
        $this->_view->_error = 'Ingresa el código.';
        $this->_view->pending_register = $pending;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $otpDebug = [];
      [$validOk, $validDbg] = $this->otpValid((string)($pending['numero_telefono'] ?? ''), $codigo);
      $otpDebug['valid'] = $validDbg;
      $this->_view->debug_otp = $otpDebug;

      if (!$validOk) {
        $this->_view->_error = 'Código incorrecto o expirado.';
        $this->_view->pending_register = $pending;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $creds = $this->getEmpresaApiCredentialsForCurrentPage();
      if (!$creds) {
        $this->_view->_error = 'Credenciales API no configuradas para este cliente';
        $this->_view->pending_register = $pending;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      [$token, $authDbg] = $this->fetchWlToken($creds);
      $this->_view->debug_auth_api = ['auth' => $authDbg];
      if (!$token) {
        $this->_view->_error = 'No se pudo obtener token';
        $this->_view->pending_register = $pending;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      $payload = [
        'nombre' => (string)($pending['nombre'] ?? ''),
        'email' => (string)($pending['email'] ?? ''),
        'password' => (string)($pending['password'] ?? ''),
        'numero_telefono' => (string)($pending['numero_telefono'] ?? ''),
        'apellido_paterno' => (string)($pending['apellido_paterno'] ?? ''),
        'apellido_materno' => (string)($pending['apellido_materno'] ?? ''),
        'codigo_convenio' => (string)($pending['codigo_convenio'] ?? ''),
      ];

      [$body, $regDbg] = $this->registerUserApi($token, $payload);
      if ($debugEnabled) {
        $safePayload = $payload;
        if (array_key_exists('password', $safePayload)) {
          $safePayload['password'] = '***';
        }
        $regDbg['payload'] = $safePayload;
      }
      $this->_view->debug_register_api = $regDbg;
      if (!$body || !is_array($body) || empty($body['success'])) {
        $msg = 'No se pudo completar el registro.';
        if (is_array($body) && isset($body['mensaje']) && is_string($body['mensaje']) && trim($body['mensaje']) !== '') {
          $msg = trim($body['mensaje']);
        }
        $this->_view->_error = $msg;
        $this->_view->pending_register = $pending;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      Session::destroy('pending_register');

      // Si estamos en debug, no redireccionar para que se vean los logs
      if ($debugEnabled) {
        $this->_view->_success = 'Registro exitoso.';
        $this->_view->pending_register = null;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      // Auto-login tras registro
      $telLogin = (string)($pending['numero_telefono'] ?? '');
      $passLogin = (string)($pending['password'] ?? '');
      [$loginBody, $loginDbg] = $this->authenticateUserApi($token, $telLogin, $passLogin);
      if ($loginBody && is_array($loginBody) && !empty($loginBody['success'])) {
        Session::set('autenticado', true);
        Session::set('tiempo', time());
        Session::set('api_token', $token);
        Session::set('email_usuario', $telLogin);
        if (isset($loginBody['data']) && is_array($loginBody['data'])) {
          if (isset($loginBody['data']['nombre']) && is_string($loginBody['data']['nombre'])) {
            Session::set('nombre_usuario', $loginBody['data']['nombre']);
          }
          if (isset($loginBody['data']['id'])) {
            Session::set('cv_usuario', $loginBody['data']['id']);
          }
        }
        $op_extra = new CORE;
        $tokenrf = $op_extra->cadena_aleatoria(15);
        Session::set('tokencsrf', $tokenrf);

        $this->_view->_success = 'La cuenta se creó correctamente. Entrando...';
        $this->_view->pending_register = null;
        $this->_view->redirect_url = BASE_URL . 'usuarios';
        $this->_view->redirect_seconds = 3;
        $vistas = array('registro');
        $this->_view->renderizar($vistas);
        exit;
      }

      // Si no se pudo hacer auto-login, llevar a login normal
      $this->redireccionar('login');
    }

    $vistas = array('registro');
    $this->_view->renderizar($vistas);
  }


  public function cerrar()
  {
    Session::destroy();
    $this->redireccionar("");
  }
}

