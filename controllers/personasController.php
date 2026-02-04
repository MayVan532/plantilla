<?php
use Illuminate\Database\Capsule\Manager as Capsule;
class personasController extends Controller

{
    public function __construct()
    {
        parent::__construct();
        $pid = $this->resolveCmsPageId();
        $this->loadHeaderSections($pid);
    }
    protected function getEmpresaApiCredentialsForCurrentPage(): ?array
    {
        try {
            $pid = $this->resolveCmsPageId();
            if ($pid <= 0) { return null; }

            // pagina_cms.id_pagina = CMS_PAGE_ID => pagina_cms.id_empresa => empresas_cms (client_id, client_secret)
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

    protected function callJsonApi(string $url, array $headers, array $payload): array
    {
        $ch = null;
        try {
            $ch = curl_init();
            if ($ch === false) {
                return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'curl_init_failed'];
            }
            $json = json_encode($payload);
            $baseHeaders = [
                'Content-Type: application/json',
                'Accept: application/json',
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
                CURLOPT_TIMEOUT        => 15,
            ]);
            $raw = curl_exec($ch);
            if ($raw === false) {
                $err = curl_error($ch);
                $code = curl_errno($ch);
                return ['ok' => false, 'status' => null, 'body' => null, 'error' => 'curl_error:'.$code.':'.$err];
            }
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['ok' => false, 'status' => $status, 'body' => $raw, 'error' => 'json_decode_error:'.json_last_error_msg()];
            }
            $ok = $status >= 200 && $status < 300;
            return ['ok' => $ok, 'status' => $status, 'body' => $data, 'error' => $ok ? null : 'http_error'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => null, 'body' => null, 'error' => $e->getMessage()];
        } finally {
            if ($ch) { @curl_close($ch); }
        }
    }

    protected function fetchSocioToken(array $creds): array
    {
        $debug = ['step' => 'auth-token', 'url' => 'https://apis.likephone.mx/api/v1/platform/socioscomerciales/auth/token'];
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
            // Basado en la respuesta de Postman: { mensaje, data: { token, token_type } }
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

    protected function fetchPlanesByProductType(string $token, string $tipoProducto): array
    {
        $debug = [
            'step' => 'list-planes',
            'url'  => 'https://apis.likephone.mx/api/v1/platform/socioscomerciales/planes/listPlanesByProductType',
            'tipo_producto' => $tipoProducto,
        ];
        try {
            $headers = ['Authorization: Bearer '.$token];
            $payload = ['tipo_producto' => $tipoProducto];
            $res = $this->callJsonApi($debug['url'], $headers, $payload);
            $debug['http_status'] = $res['status'];
            $debug['error'] = $res['error'];
            if (!$res['ok'] || !is_array($res['body'])) {
                $debug['raw_body'] = $res['body'];
                return [[], $debug];
            }
            $body = $res['body'];
            // Estructura real: { success, mensaje, data: { planes: [ {...}, ... ] }, meta: {...} }
            $planes = [];
            if (isset($body['data']['planes']) && is_array($body['data']['planes'])) {
                $planes = $body['data']['planes'];
            }
            $debug['planes_count'] = is_array($planes) ? count($planes) : 0;
            return [$planes, $debug];
        } catch (\Throwable $e) {
            $debug['exception'] = $e->getMessage();
            return [[], $debug];
        }
    }

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
            $by = $footerRes['byType'] ?? [];
            $dbg = $footerRes['debug'] ?? [];
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

            // Mapear datos desde filas crudas
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
                        // Si no es aviso ni copyright ni teléfono, úsalo como dirección si aún no hay
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

            // Exponer a la vista
            $this->_view->debug_footer = $dbg + ['blockId' => $blockId, 'rows' => count($rows)];
            $this->_view->footer_logo = $logo;
            $this->_view->footer_docs = $docs;
            $this->_view->footer_aviso = $aviso;
            $this->_view->footer_copy  = $copy;
            $this->_view->footer_tel   = $tel;
            $this->_view->footer_mail  = $mail;
            $this->_view->footer_addr  = $addr;
            $this->_view->footer_social = $socials;
        } catch (\Throwable $e) {
            $this->_view->debug_footer = ['error' => $e->getMessage()];
        }
    }

    // Helper: resuelve id_bloque con parámetros mínimos para este controlador (Personas => id_seccion=1, id_pagina=1)
    protected function cmsResolveId(int $pestanaId, string $titulo, ?string $tipoBloque = null, ?int $subpestanaId = null)
    {
        try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
        if (!class_exists('CmsRepository')) {
            $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
            if (is_readable($repoPath)) { require_once $repoPath; }
        }
        $pid = $this->resolveCmsPageId();
        $sid = $this->resolveCmsSectionId('personas', $pid);
        $coords = [
            'id_pagina'    => $pid,
            'id_seccion'   => $sid,
            'titulo'       => $titulo,
            'visible'      => 1,
        ];
        if ($pestanaId > 0) {
            $coords['id_pestana'] = $pestanaId;
        }
        if ($tipoBloque)   { $coords['tipo_bloque']   = $tipoBloque; }
        if ($subpestanaId) { $coords['id_subpestana'] = $subpestanaId; }
        $id = CmsRepository::resolveBlockId($coords);
        return $id ? (int)$id : 0; // 0 indica "no resuelto" y carga vacía de forma segura
    }

    // Página principal de Personas
    public function index()
    {
        // Footer personas para esta y todas las vistas
        $this->loadFooterPersonas();
        // Cargar imágenes del carrusel desde CMS usando repositorio reutilizable
        $debug = ['step' => 'init-repo'];
        try {
           // Asegura carga del repositorio (incluye el archivo y registra la clase)
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }

            // test conexión rápida
            $ping = Capsule::connection('cms')->select('SELECT 1 as ok');
            $debug['conn_ok'] = isset($ping[0]->ok) ? (int)$ping[0]->ok : 0;

            //MODO A
            // MODO A: pestaña Inicio (Personas) resuelta dinámicamente por título
            $PESTANA = $this->resolveCmsTabId('personas', 'Inicio');

            // Cargar datos del bloque 1: carrusel (con posibles links por imagen) usando id_bloque resuelto
            $id1 = $this->cmsResolveId($PESTANA, 'Carrusel principal', 'carrusel', null);
            $bloque1 = CmsRepository::loadZippedItemsByIdWithLinks($id1, ['imagen'], 1);
            $items = [];
            foreach (($bloque1['items'] ?? []) as $row) {
                $img = $row['imagen'] ?? null; $lnk = $row['link'] ?? null;
                if ($img) { $items[] = ['img'=>$img, 'link'=>$lnk]; }
            }
            $this->_view->carouselItems = $items;
            $this->_view->carouselImages = array_map(function($r){ return $r['img']; }, $items); // compat
            $this->_view->carouselTotal  = count($items);
            $this->_view->debug_bloque1 = $bloque1['debug'];



            //MODO B
            // Cargar datos del bloque 2: Plan Activación (id resuelto)
            $id2 = $this->cmsResolveId($PESTANA, 'Plan Activación', 'imagen_texto_boton', null);
            $bloque2 = CmsRepository::loadBlockByIdTypes($id2, ['titulo','subtitulo','texto','boton','imagen'], 1);
            $this->_view->planActivacion = $bloque2['byType'];
            $this->_view->debug_bloque2 = $bloque2['debug']; 

            // Cargar datos del bloque 3: Cobertura (id resuelto)
            $id3 = $this->cmsResolveId($PESTANA, 'Cobertura', 'imagen_texto_botonlink', null);
            $bloque3 = CmsRepository::loadBlockByIdTypes($id3, ['titulo','subtitulo','texto','boton','imagen'], 1);
            $this->_view->cobertura = $bloque3['byType'];
            // Link del botón desde la columna 'link' del tipo 'boton'
            try { $this->_view->cobertura_link = CmsRepository::getFirstLinkForType($id3, 'boton', 1); } catch (\Throwable $e) { $this->_view->cobertura_link = null; }
            $this->_view->debug_bloque3 = $bloque3['debug'];


            //MODO C
            
            // Cargar datos del bloque 4: Beneficios (id resuelto)
            $id4 = $this->cmsResolveId($PESTANA, 'Beneficios', 'imagen_texto', null);
            $bloque4 = CmsRepository::loadZippedItemsById($id4, ['imagen','titulo','texto'], 1);
            $this->_view->beneficios = [
            'items' => array_map(function($r){
                return [
                    'img'   => $r['imagen'] ?? null,
                    'title' => $r['titulo'] ?? null,
                    'text'  => $r['texto']  ?? null,
                ];
            }, $bloque4['items'])
            ];
            $this->_view->debug_bloque4 = $bloque4['debug'];

            // Cargar datos del bloque 5: seccion e-sim (id resuelto)
            $id5 = $this->cmsResolveId($PESTANA, 'Banner', 'imagen_boton', null);
            $bloque5 = CmsRepository::loadBlockByIdTypes($id5, ['titulo','subtitulo','texto','boton','imagen'], 1);
            $this->_view->seccion_esim = $bloque5['byType'];
            // Obtener links por cada botón (en orden y alineados a los textos de botón)
            try {
                $rows5 = Capsule::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['tipo','link'])
                    ->where('id_bloque', $id5)
                    ->where('visible', 1)
                    ->orderBy('id_elemento')
                    ->get()->toArray();
                $btnTexts = isset($bloque5['byType']['boton']) && is_array($bloque5['byType']['boton']) ? $bloque5['byType']['boton'] : [];
                $expected = count($btnTexts);
                $btnLinksAligned = [];
                $n = count($rows5);
                for ($i = 0; $i < $n && count($btnLinksAligned) < $expected; $i++) {
                    $t = strtolower(trim((string)($rows5[$i]->tipo ?? '')));
                    if ($t !== 'boton') continue;
                    $l = is_string($rows5[$i]->link ?? null) ? trim($rows5[$i]->link) : '';
                    if ($l === '') {
                        // Buscar el siguiente 'link' visible
                        for ($j = $i + 1; $j < $n; $j++) {
                            $tj = strtolower(trim((string)($rows5[$j]->tipo ?? '')));
                            if ($tj === 'link') {
                                $cand = is_string($rows5[$j]->link ?? null) ? trim($rows5[$j]->link) : '';
                                if ($cand !== '') { $l = $cand; $i = $j; break; }
                            }
                        }
                    }
                    $btnLinksAligned[] = $l;
                }
                // Normalizar URLs (agregar https:// si falta y no es ruta relativa)
                $norm = array_map(function($u){
                    if (!is_string($u) || $u==='') return '';
                    $u = trim($u);
                    if (stripos($u,'http')===0 || stripos($u,'mailto:')===0 || stripos($u,'tel:')===0) return $u;
                    if ($u[0] === '/') { return (defined('BASE_URL') ? rtrim(BASE_URL,'/') : '') . $u; }
                    return 'https://'.ltrim($u,'/');
                }, $btnLinksAligned);
                $this->_view->seccion_esim_links = $norm;
            } catch (\Throwable $e) { $this->_view->seccion_esim_links = []; }
            $this->_view->debug_bloque5 = $bloque5['debug']; 


            // Cargar datos del bloque 6: Recarga likes (id resuelto)
            $id6 = $this->cmsResolveId($PESTANA, 'Recarga Likes', 'imagen_texto_botonlink', null);
            $bloque6 = CmsRepository::loadBlockByIdTypes($id6, ['titulo','subtitulo','texto','boton','imagen'], 1);
            $this->_view->Recargalikes = $bloque6['byType'];
            $this->_view->debug_bloque6 = $bloque6['debug']; 

            // === Consumo de APIs de Socios Comerciales (B2B Telecom / empresa ligada a la página actual) ===
            $apiDebug = ['step' => 'init', 'page_id' => $this->resolveCmsPageId()];
            $empresaCreds = $this->getEmpresaApiCredentialsForCurrentPage();
            if ($empresaCreds) {
                $apiDebug['empresa'] = [
                    'id_pagina'      => $empresaCreds['id_pagina'],
                    'id_empresa'     => $empresaCreds['id_empresa'],
                    'pagina_nombre'  => $empresaCreds['pagina_nombre'],
                    'empresa_nombre' => $empresaCreds['empresa_nombre'],
                ];
                // 1) Obtener token
                list($token, $authDbg) = $this->fetchSocioToken($empresaCreds);
                $apiDebug['auth'] = $authDbg;
                if ($token) {
                    // 2) Obtener planes por tipo de producto (3)
                    list($planes, $planesDbg) = $this->fetchPlanesByProductType($token, '3');
                    $apiDebug['planes'] = $planesDbg;
                    $this->_view->planesSocio = is_array($planes) ? $planes : [];
                } else {
                    $this->_view->planesSocio = [];
                }
            } else {
                $apiDebug['empresa'] = 'no-credentials-for-page';
                $this->_view->planesSocio = [];
            }
            $this->_view->planesSocioDebug = $apiDebug;

        } catch (\Throwable $e) {
            $debug['error'] = $e->getMessage();
            $this->_view->cmsDebug = $debug;
            @error_log('[Personas Carousel CMS] '.$e->getMessage());
            $this->_view->carouselImages = [];
        }

        $this->_view->renderizar(array('@personas/inicio'));
    }

    public function usuarios($section = null)
    {
        // Normalizar sección si viene como tercer segmento en la URL
        $section = is_string($section) ? strtolower(trim($section)) : null;

        // Si viene una sub-sección, redirigimos internamente a los métodos existentes
        switch ($section) {
            case 'recargar':
                return $this->usuariosRecargar();
            case 'portabilidad':
                return $this->usuariosPortabilidad();
            case 'misnumeros':
                return $this->usuariosMisnumeros();
            case 'miscompras':
                return $this->usuariosMiscompras();
            case 'miperfil':
                return $this->usuariosMiperfil();
        }

        // Vista principal de inicio del panel de usuarios
        $this->loadFooterPersonas();
        $this->_view->hideMainNav      = true; // sin header público
        $this->_view->hideGlobalFooter = true; // sin footer público
        // Renderiza el panel de usuarios: views/personas/recarUser.phtml/usuarios.phtml
        $this->_view->renderizar(array('@personas/recarUser.phtml/usuarios'));
    }

    // === Secciones internas del panel de usuario (recarUser.phtml) ===

    public function usuariosRecargar()
    {
        $this->loadFooterPersonas();
        $this->_view->hideMainNav      = true;
        $this->_view->hideGlobalFooter = true;
        $this->_view->renderizar(array('@personas/recarUser.phtml/recargar'));
    }

    public function usuariosPortabilidad()
    {
        $this->loadFooterPersonas();
        $this->_view->hideMainNav      = true;
        $this->_view->hideGlobalFooter = true;
        $this->_view->renderizar(array('@personas/recarUser.phtml/portabilidad'));
    }

    public function usuariosMisnumeros()
    {
        $this->loadFooterPersonas();
        $this->_view->hideMainNav      = true;
        $this->_view->hideGlobalFooter = true;
        $this->_view->renderizar(array('@personas/recarUser.phtml/misnumeros'));
    }

    public function usuariosMiscompras()
    {
        $this->loadFooterPersonas();
        $this->_view->hideMainNav      = true;
        $this->_view->hideGlobalFooter = true;
        $this->_view->renderizar(array('@personas/recarUser.phtml/miscompras'));
    }

    public function usuariosMiperfil()
    {
        $this->loadFooterPersonas();
        $this->_view->hideMainNav      = true;
        $this->_view->hideGlobalFooter = true;
        $this->_view->renderizar(array('@personas/recarUser.phtml/miperfil'));
    }

    
    // Consultar - equivalencias de producción (sin prefijos)
    public function cobertura()
    {
        $this->loadFooterPersonas();
        // Cargar banner de Cobertura desde CMS (Bloque )
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }

            $PESTANA = $this->resolveCmsTabId('personas', 'Consultar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Consultar', 'Cobertura');
            $id7 = $this->cmsResolveId($PESTANA, 'Banner Cobertura', 'banner', $SUB);
            $bloque7 = CmsRepository::loadElementsByBlockId($id7, 'imagen', 1);
            $this->_view->banner_cobertura = isset($bloque7['items'][0]) ? $bloque7['items'][0] : null;
            $this->_view->banner_cobertura_link = CmsRepository::getFirstLinkForType($id7, 'imagen', 1);
            $this->_view->debug_bloque7 = $bloque7['debug'];
        } catch (\Throwable $e) {
            $this->_view->banner_cobertura = null;
        }

        $this->_view->renderizar(array('@personas/consultar/cobertura'));
    }

    public function compatibilidad()
    {
        $this->loadFooterPersonas();
        // Cargar CMS para subpestaña Compatibilidad
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }

            $PESTANA = $this->resolveCmsTabId('personas', 'Consultar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Consultar', 'Compatibilidad');
            // Banner Compatibilidad
            $id8 = $this->cmsResolveId($PESTANA, 'Banner Compatibilidad', 'banner', $SUB);
            $b8 = CmsRepository::loadElementsByBlockId($id8, 'imagen', 1);
            $this->_view->banner_compatibilidad = $b8['items'][0] ?? null;
            $this->_view->banner_compatibilidad_link = CmsRepository::getFirstLinkForType($id8, 'imagen', 1);
            $this->_view->debug_bloque8 = $b8['debug'];

            
            // Video + Carrusel - esperamos tipos: titulo, video, imagen
            $id9 = $this->cmsResolveId($PESTANA, 'No conoces tu número IMEI. Aquí te decimos cómo obtenerlo en solo 4 pasos', 'video_carrusel', $SUB);
            $b9 = CmsRepository::loadBlockByIdTypes($id9, ['titulo','video','imagen'], 1);
            $this->_view->compat_vc = $b9['byType'];
            $this->_view->debug_bloque9 = $b9['debug'];

            // Necesitas ayuda (WhatsApp): expone link y mensaje opcional
            $id10 = $this->cmsResolveId($PESTANA, 'Necesitas ayuda', 'boton', $SUB);
            // Mantener items crudos por si se usan en otros lados
            $b10 = CmsRepository::loadElementsByBlockId($id10, null, 1);
            $this->_view->compat_ayuda = $b10['items'];
            $this->_view->debug_bloque10 = $b10['debug'];
            // Link desde columna 'link' del tipo 'link'
            try { $this->_view->compat_ayuda_link = CmsRepository::getFirstLinkForType($id10, 'link', 1); } catch (\Throwable $e) { $this->_view->compat_ayuda_link = null; }
            // Mensaje opcional desde 'texto' o 'titulo'
            try {
                $b10msg = CmsRepository::loadBlockByIdTypes($id10, ['texto','titulo'], 1);
                $msg = $b10msg['byType']['texto'][0] ?? ($b10msg['byType']['titulo'][0] ?? null);
                $this->_view->compat_ayuda_msg = is_string($msg) ? trim($msg) : null;
            } catch (\Throwable $e) { $this->_view->compat_ayuda_msg = null; }
        } catch (\Throwable $e) {
            // fallbacks silenciosos
        }

        $this->_view->renderizar(array('@personas/consultar/compatibilidad'));
    }

    public function estatusenvio()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Banner Estatus Envío
            $PESTANA = $this->resolveCmsTabId('personas', 'Consultar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Consultar', 'Estatus De Envio');
            $id11 = $this->cmsResolveId($PESTANA, 'Banner estatus de envío', 'banner', $SUB);
            $b11 = CmsRepository::loadElementsByBlockId($id11, 'imagen', 1);
            $this->_view->banner_estatus = $b11['items'][0] ?? null;
            $this->_view->banner_estatus_link = CmsRepository::getFirstLinkForType($id11, 'imagen', 1);
            $this->_view->debug_bloque11 = $b11['debug'];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/consultar/estatusEnvio'));
    }

    public function apn()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Banner APN y Tips/Imagen adicional
            $PESTANA = $this->resolveCmsTabId('personas', 'Consultar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Consultar', 'Configura tu APN');
            $id12 = $this->cmsResolveId($PESTANA, 'Banner Configura tu APN', 'banner', $SUB);
            $b12 = CmsRepository::loadElementsByBlockId($id12, 'imagen', 1);
            $this->_view->banner_apn = $b12['items'][0] ?? null;
            $this->_view->banner_apn_link = CmsRepository::getFirstLinkForType($id12, 'imagen', 1);
            $this->_view->debug_bloque12 = $b12['debug'];
            $id13 = $this->cmsResolveId($PESTANA, 'Tips Likephone', 'banner', $SUB);
            $b13 = CmsRepository::loadElementsByBlockId($id13, 'imagen', 1);
            $this->_view->apn_imagen = $b13['items'][0] ?? null;
            $this->_view->apn_imagen_link = CmsRepository::getFirstLinkForType($id13, 'imagen', 1);
            $this->_view->debug_bloque13 = $b13['debug'];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/consultar/configuraApn'));
    }

    public function faq()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            $PESTANA = $this->resolveCmsTabId('personas', 'Consultar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Consultar', 'Preguntas Frecuentes');
            // Banner Preguntas
            $id14 = $this->cmsResolveId($PESTANA, 'Banner preguntas frecuentes', 'banner', $SUB);
            $b14 = CmsRepository::loadElementsByBlockId($id14, 'imagen', 1);
            $this->_view->banner_preguntas = $b14['items'][0] ?? null;
            $this->_view->banner_preguntas_link = CmsRepository::getFirstLinkForType($id14, 'imagen', 1);
            $this->_view->debug_bloque14 = $b14['debug'];
            $id15 = $this->cmsResolveId($PESTANA, 'Preguntas y respuestas', 'texto_simple', $SUB);
            // Cargar también por tipos para reutilizar links/textos en la barra lateral (compatibilidad)
            $b15 = $id15 ? CmsRepository::loadBlockByIdTypes($id15, ['titulo','texto','link','social'], 1) : null;
            $faqItems = [];
            if ($id15) {
                $rows = Capsule::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['contenido','link'])
                    ->where('id_bloque', $id15)
                    ->where('visible', 1)
                    ->where('tipo', 'pregunta')
                    ->orderBy('id_elemento')
                    ->get();
                foreach ($rows as $row) {
                    $pregunta = is_string($row->contenido ?? null) ? trim($row->contenido) : '';
                    $respuestas = [];
                    $rawLink = $row->link ?? null;
                    if (is_string($rawLink) && trim($rawLink) !== '') {
                        $decoded = json_decode($rawLink, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                            // Nuevo esquema: arreglo de respuestas [{"texto":"...","link":"..."}, ...]
                            foreach ($decoded as $ans) {
                                if (!is_array($ans)) { continue; }
                                $txt = isset($ans['texto']) && is_string($ans['texto']) ? trim($ans['texto']) : '';
                                $lnk = isset($ans['link']) && is_string($ans['link']) ? trim($ans['link']) : '';
                                if ($txt !== '' || $lnk !== '') {
                                    $respuestas[] = ['texto' => $txt, 'link' => $lnk];
                                }
                            }
                        } else {
                            // Compatibilidad: si no es arreglo, tratar de leer como una sola respuesta
                            $single = json_decode($rawLink, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($single)) {
                                $txt = isset($single['texto']) && is_string($single['texto']) ? trim($single['texto']) : '';
                                $lnk = isset($single['link']) && is_string($single['link']) ? trim($single['link']) : '';
                                if ($txt !== '' || $lnk !== '') {
                                    $respuestas[] = ['texto' => $txt, 'link' => $lnk];
                                }
                            }
                        }
                    }
                    if ($pregunta !== '' || !empty($respuestas)) {
                        $faqItems[] = [
                            'pregunta'   => $pregunta,
                            'respuestas' => $respuestas,
                        ];
                    }
                }
            }
            $this->_view->faq_items = $faqItems;
            $this->_view->faq_byType = isset($b15) && isset($b15['byType']) ? $b15['byType'] : [];
            $this->_view->debug_bloque15 = isset($b15) && isset($b15['debug']) ? $b15['debug'] : [];
            
            // Síguenos (Bloque 16): tomar cualquier fila visible con 'link' no vacío
            try {
                $id16 = $this->cmsResolveId($PESTANA, 'Síguenos', 'social', $SUB);
                $rows = Capsule::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['contenido','link'])
                    ->where('id_bloque', $id16)
                    ->where('visible', 1)
                    ->whereNotNull('link')
                    ->whereRaw("TRIM(link) <> ''")
                    ->orderBy('id_elemento')
                    ->get();
                $this->_view->faq_social_links = array_values(array_filter(array_map(function($r){
                    return is_string($r->link ?? null) && trim($r->link) !== '' ? trim($r->link) : null;
                }, $rows ? $rows->toArray() : [])));
            } catch (\Throwable $e) { $this->_view->faq_social_links = []; }
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/consultar/preguntasFrecuentes'));
    }

    public function quienessomos()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Banner Quiénes Somos (Bloque 17) – pestaña/subpestaña dinámicas
            $PESTANA = $this->resolveCmsTabId('personas', 'Consultar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Consultar', 'Quiénes Somos');
            $id17 = $this->cmsResolveId($PESTANA, 'Banner Dale play a tu vida', 'banner', $SUB);
            $b17 = CmsRepository::loadElementsByBlockId($id17, 'imagen', 1);
            $this->_view->banner_quienes = $b17['items'][0] ?? null;
            $this->_view->banner_quienes_link = CmsRepository::getFirstLinkForType($id17, 'imagen', 1);
            $this->_view->debug_bloque17 = $b17['debug'];
            // Misión/Visión (Bloque 18): titulo, texto
            $id18 = $this->cmsResolveId($PESTANA, 'Misión y visión', 'texto_simple', $SUB);
            $b18 = CmsRepository::loadBlockByIdTypes($id18, ['titulo','texto'], 1);
            $this->_view->qs_mv = $b18['byType'];
            // Exponer crudo otros bloques para uso futuro (resolver dinámicamente por coordenadas pestaña/subpestaña)
            try {
                $pid = $this->resolveCmsPageId();
                $sid = $this->resolveCmsSectionId('personas', $pid);
                $coords = [
                    'id_pagina'    => $pid,
                    'id_seccion'   => $sid,
                    'id_pestana'   => $PESTANA,
                    'id_subpestana'=> $SUB,
                    'visible'      => 1,
                ];
                $dynId = CmsRepository::resolveBlockId($coords);
                $dynId = $dynId ? (int)$dynId : 0;
                $this->_view->qs_texto = $dynId > 0
                    ? (CmsRepository::loadElementsByBlockId($dynId, null, 1)['items'] ?? [])
                    : [];
            } catch (\Throwable $e) {
                $this->_view->qs_texto = [];
            }
            // IDs correctos en CMS: 19 (imagen_texto_boton), 20 (titulo_gif), 21 (imagen_texto_botonlink)
            $id19 = $this->cmsResolveId($PESTANA, 'Imagen conecta con los tuyos', 'imagen_texto_boton', $SUB);
            $this->_view->qs_imgbtn = CmsRepository::loadBlockByIdTypes($id19, ['imagen','titulo','subtitulo','texto','boton','link'], 1)['byType'] ?? [];
            // Link principal del bloque 19 asociado al boton
            try { $this->_view->qs_imgbtn_link = CmsRepository::getFirstLinkForType($id19, 'boton', 1); }
            catch (\Throwable $e) { $this->_view->qs_imgbtn_link = null; }
            $id20 = $this->cmsResolveId($PESTANA, 'Cobertura', 'titulo_gif', $SUB);
            $this->_view->qs_titulogif = CmsRepository::loadBlockByIdTypes($id20, ['titulo','imagen'], 1)['byType'] ?? [];
            $id21 = $this->cmsResolveId($PESTANA, 'Compatibilidad', 'imagen_texto_botonlink', $SUB);
            $this->_view->qs_imgtextolink = CmsRepository::loadBlockByIdTypes($id21, ['titulo','subtitulo','imagen','texto','boton','link'], 1)['byType'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/consultar/quienesSomos'));
    }

    // Portabilidad
    public function portabilidad()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // 22: Banner conserva tu número – pestaña dinámica Portabilidad
            $PESTANA = $this->resolveCmsTabId('personas', 'Portabilidad');
            $id22 = $this->cmsResolveId($PESTANA, 'Banner conserva tu número', 'banner', null);
            $b22 = CmsRepository::loadElementsByBlockId($id22, 'imagen', 1);
            $this->_view->port_banner = $b22['items'][0] ?? null;
            $this->_view->port_banner_link = CmsRepository::getFirstLinkForType($id22, 'imagen', 1);
            $this->_view->debug_bloque22 = $b22['debug'];
            // 23: Video y carrusel
            $id23 = $this->cmsResolveId($PESTANA, 'Video y carrusel', 'video_carrusel', null);
            $b23 = CmsRepository::loadBlockByIdTypes($id23, ['titulo','video','imagen'], 1);
            $this->_view->port_vc = $b23['byType'];
            $this->_view->debug_bloque23 = $b23['debug'];
            // 24: Datos para portabilidad (banner extra)
            $id24 = $this->cmsResolveId($PESTANA, 'Datos para portabilidad', 'banner', null);
            $b24 = CmsRepository::loadElementsByBlockId($id24, 'imagen', 1);
            $this->_view->port_datos_banner = $b24['items'][0] ?? null;
            $this->_view->port_datos_banner_link = CmsRepository::getFirstLinkForType($id24, 'imagen', 1);
            $this->_view->debug_bloque24= $b24['debug'];
            // 25: Ayuda (boton) - WhatsApp: expone link y mensaje opcional
            $id25 = $this->cmsResolveId($PESTANA, 'Necesitas ayuda', 'boton', null);
            $b25 = CmsRepository::loadBlockByIdTypes($id25, ['boton','link'], 1);
            $this->_view->port_ayuda = $b25['byType'];
            $this->_view->debug_bloque25 = $b25['debug'];
            try { $this->_view->port_ayuda_link = CmsRepository::getFirstLinkForType($id25, 'link', 1); } catch (\Throwable $e) { $this->_view->port_ayuda_link = null; }
            try {
                $b25msg = CmsRepository::loadBlockByIdTypes($id25, ['texto','titulo'], 1);
                $pmsg = $b25msg['byType']['texto'][0] ?? ($b25msg['byType']['titulo'][0] ?? null);
                $this->_view->port_ayuda_msg = is_string($pmsg) ? trim($pmsg) : null;
            } catch (\Throwable $e) { $this->_view->port_ayuda_msg = null; }
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/portabilidad'));
    }

    // Recargas
    public function recargas()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Subpestaña Recargas Likephone: 26 banner, 27 gif_textos (pestaña/subpestaña dinámicas)
            $PESTANA = $this->resolveCmsTabId('personas', 'Recargar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Recargar', 'Recargar Likephone');
            $id26 = $this->cmsResolveId($PESTANA, 'Banner recargar', 'banner', $SUB);
            $b26 = CmsRepository::loadElementsByBlockId($id26, 'imagen', 1);
            $this->_view->rec_banner = $b26['items'][0] ?? null;
            $this->_view->rec_banner_link = CmsRepository::getFirstLinkForType($id26, 'imagen', 1);
            $this->_view->debug_bloque26 = $b26['debug'];
            $id27 = $this->cmsResolveId($PESTANA, 'Recarga tu Likephone', 'gif_textos', $SUB);
            $b27 = CmsRepository::loadBlockByIdTypes($id27, ['imagen','titulo','subtitulo','texto'], 1);
            $this->_view->rec_gif_textos = $b27['byType'];
            $this->_view->debug_bloque27 = $b27['debug'];
        } catch (\Throwable $e) {}

        // === Consumo de APIs de Socios Comerciales (mismo flujo que en index) ===
        try {
            $apiDebug = ['step' => 'init', 'page_id' => $this->resolveCmsPageId()];
            $empresaCreds = $this->getEmpresaApiCredentialsForCurrentPage();
            if ($empresaCreds) {
                $apiDebug['empresa'] = [
                    'id_pagina'      => $empresaCreds['id_pagina'],
                    'id_empresa'     => $empresaCreds['id_empresa'],
                    'pagina_nombre'  => $empresaCreds['pagina_nombre'],
                    'empresa_nombre' => $empresaCreds['empresa_nombre'],
                ];
                // 1) Obtener token
                list($token, $authDbg) = $this->fetchSocioToken($empresaCreds);
                $apiDebug['auth'] = $authDbg;
                if ($token) {
                    // 2) Obtener planes por tipo de producto (3)
                    list($planes, $planesDbg) = $this->fetchPlanesByProductType($token, '3');
                    $apiDebug['planes'] = $planesDbg;
                    $this->_view->planesSocio = is_array($planes) ? $planes : [];
                } else {
                    $this->_view->planesSocio = [];
                }
            } else {
                $apiDebug['empresa'] = 'no-credentials-for-page';
                $this->_view->planesSocio = [];
            }
            $this->_view->planesSocioDebug = $apiDebug;
        } catch (\Throwable $e) {
            // En caso de falla, exponer estructuras vacías para que la vista no truene
            $this->_view->planesSocio = [];
            $this->_view->planesSocioDebug = ['error' => $e->getMessage()];
        }

        $this->_view->renderizar(array('@personas/recargar/likephone'));
    }

    public function dashboardRecargas()
    {
        $this->loadFooterPersonas();
        $this->redireccionar('personas/usuarios');
    }

    public function recargar_empresas()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Subpestaña Recargas Empresas: 28 banner, 29 gif_teRecargarxtos (pestaña/subpestaña dinámicas)
            $PESTANA = $this->resolveCmsTabId('personas', 'Recargar');
            $SUB     = $this->resolveCmsSubtabId('personas', 'Recargar', 'Recargar Empresas');
            $id28 = $this->cmsResolveId($PESTANA, 'Banner recargar empresas', 'banner', $SUB);
            $b28 = CmsRepository::loadElementsByBlockId($id28, 'imagen', 1);
            $this->_view->rec_emp_banner = $b28['items'][0] ?? null;
            $this->_view->rec_emp_banner_link = CmsRepository::getFirstLinkForType($id28, 'imagen', 1);
            $this->_view->debug_bloque28 = $b28['debug'];
            $id29 = $this->cmsResolveId($PESTANA, 'Recargas empresas', 'gif_textos', $SUB);
            $b29 = CmsRepository::loadBlockByIdTypes($id29, ['imagen','titulo','subtitulo','texto'], 1);
            $this->_view->rec_emp_gif_textos = $b29['byType'];
            $this->_view->debug_bloque29 = $b29['debug'];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/recargar/empresas'));
    }

    // Contacto
    public function contacto()
    {
        $this->loadFooterPersonas();
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // 30: Banner principal – pestaña dinámica Contacto
            $PESTANA = $this->resolveCmsTabId('personas', 'Contacto');
            $id30 = $this->cmsResolveId($PESTANA, 'Banner contacto', 'banner', null);
            $b30 = CmsRepository::loadElementsByBlockId($id30, 'imagen', 1);
            $this->_view->contacto_banner = $b30['items'][0] ?? null;
            $this->_view->contacto_banner_link = CmsRepository::getFirstLinkForType($id30, 'imagen', 1);
            $this->_view->debug_bloque30 = $b30['debug'];
            // 31: Imagen secundaria (lado izquierdo del formulario)
            $id31 = $this->cmsResolveId($PESTANA, 'Banner secundario', 'banner', null);
            $b31 = CmsRepository::loadElementsByBlockId($id31, 'imagen', 1);
            $this->_view->contacto_secundario = $b31['items'][0] ?? null;
            $this->_view->contacto_secundario_link = CmsRepository::getFirstLinkForType($id31, 'imagen', 1);
            $this->_view->debug_bloque31 = $b31['debug'];
            // 32: Redes sociales y links de contacto
            $id32 = $this->cmsResolveId($PESTANA, 'Contáctanos', null, null);
            $b32 = CmsRepository::loadElementsByBlockId($id32, null, 1);
            $this->_view->contacto_social = $b32['items'];
            $this->_view->debug_bloque32 = $b32['debug'];
            // Igual que en FAQ (B16): tomar cualquier fila visible con 'link' no vacío de elementos_bloques
            try {
                $rows32 = Capsule::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['contenido','link','tipo'])
                    ->where('id_bloque', $id32)
                    ->where('visible', 1)
                    ->whereNotNull('link')
                    ->whereRaw("TRIM(link) <> ''")
                    ->orderBy('id_elemento')
                    ->get();
                $this->_view->contacto_social_links = array_values(array_filter(array_map(function($r){
                    return is_string($r->link ?? null) && trim($r->link) !== '' ? trim($r->link) : null;
                }, $rows32 ? $rows32->toArray() : [])));
            } catch (\Throwable $e) { $this->_view->contacto_social_links = []; }
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('@personas/contacto'));
    }
}
