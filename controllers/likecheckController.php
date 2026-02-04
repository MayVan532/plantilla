<?php
class likecheckController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $pid = $this->resolveCmsPageId();
        $this->loadHeaderSections($pid);
    }

    /** Carga variables de footer para la sección LikeCheck. */
    protected function loadFooterLikecheck(): void
    {
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            $pid = $this->resolveCmsPageId();
            $sid = $this->resolveCmsSectionId('likecheck', $pid);
            $footerRes = CmsRepository::loadBlockByCoordsTypes([
                'id_pagina'  => $pid,
                'id_seccion' => $sid,
                'tipo_bloque'=> 'footer',
                'visible'    => 1,
            ], ['imagen','link','social','texto','correo','telefono'], 1);
            $dbg = $footerRes['debug'] ?? [];
            $blockId = isset($footerRes['block']->id_bloque) ? (int)$footerRes['block']->id_bloque : 0;
            if ($blockId <= 0) {
                $alt = CmsRepository::loadBlockByCoordsTypes([
                    'id_pagina'=>$pid,'id_seccion'=>$sid,'tipo_bloque'=>'footer_l','visible'=>1
                ], ['imagen','link','social','texto','correo','telefono'], 1);
                $dbg['alt_tipo'] = 'footer_l';
                $blockId = isset($alt['block']->id_bloque) ? (int)$alt['block']->id_bloque : 0;
            }
            $rows = [];
            if ($blockId > 0) {
                try {
                    $rows = \Illuminate\Database\Capsule\Manager::connection('cms')
                        ->table('elementos_bloques')
                        ->select(['tipo','contenido','link','visible'])
                        ->where('id_bloque', $blockId)
                        ->where('visible', 1)
                        ->orderBy('id_elemento')
                        ->get()->toArray();
                } catch (\Throwable $e) { $rows = []; $dbg['rows_error'] = $e->getMessage(); }
            }
            $logo=null; $docs=[]; $aviso=null; $copy=null; $tel=null; $mail=null; $addr=null; $socials=[];
            $logo1=null; $logo2=null; $logo1t=null; $logo2t=null; $ftitle=null;
            foreach ($rows as $r) {
                $tipo = strtolower(trim((string)($r->tipo ?? '')));
                $cont = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                $lnk  = is_string($r->link ?? null) ? trim($r->link) : '';
                switch ($tipo) {
                    case 'imagen':
                        // Logos etiquetados por 'link' => logo_1 / logo_2
                        if ($lnk === 'logo_1' && $cont!=='') { $logo1 = $cont; }
                        else if ($lnk === 'logo_2' && $cont!=='') { $logo2 = $cont; }
                        else if ($logo===null && $cont!=='') { $logo=$cont; }
                        break;
                    case 'link': if ($cont!=='' && $lnk!=='') { $docs[]=['t'=>$cont,'u'=>$lnk]; } break;
                    case 'direccion': if ($addr===null && $cont!=='') { $addr=$cont; } break;
                    case 'aviso': if ($aviso===null && ($cont!=='' || $lnk!=='')) { $aviso = $cont!=='' ? $cont : $lnk; } break;
                    case 'social':
                        if ($cont!=='' && $lnk!=='') { $socials[strtolower($cont)]=$lnk; }
                        if (($cont==='correo' || $cont==='email') && !$mail) { $mail = $lnk ?: $cont; }
                        if (($cont==='telefono') && !$tel) { $tel = $lnk ?: $cont; }
                        break;
                    case 'texto':
                        if ($aviso===null && preg_match('/aviso\s+de\s+privacidad/i',$cont)) { $aviso=$cont; break; }
                        if ($copy===null && preg_match('/copyright/i',$cont)) { $copy=$cont; break; }
                        if ($lnk === 'texto_logo_1' && $logo1t===null && $cont!=='') { $logo1t = $cont; break; }
                        if ($lnk === 'texto_logo_2' && $logo2t===null && $cont!=='') { $logo2t = $cont; break; }
                        if ($addr===null && $cont!=='' && !preg_match('/\+?\d[\d\s\.-]{6,}/',$cont)) { $addr=$cont; }
                        break;
                    case 'copyright':
                        if ($copy===null && $cont!=='') { $copy = $cont; }
                        break;
                    case 'titulo':
                        if ($ftitle===null && $cont!=='') { $ftitle = $cont; }
                        break;
                    case 'telefono': if ($tel===null && $cont!=='') { $tel=$cont; } break;
                    case 'correo': if ($mail===null && $cont!=='') { $mail=$cont; } break;
                }
            }
            // Normalizar URLs de redes sociales (agregar https:// si falta, soportar handles)
            if (!empty($socials)) {
                $norm = [];
                foreach ($socials as $k => $u) {
                    $key = strtolower(trim((string)$k));
                    $val = trim((string)$u);
                    if ($val === '') { continue; }
                    $lower = strtolower($val);
                    // Si viene solo el handle para TikTok o YouTube (p.ej. @likephonemx)
                    if ($key === 'tiktok') {
                        if ($lower[0] === '@') { $val = 'https://www.tiktok.com/'.$val; }
                        elseif (strpos($lower, 'tiktok.com') === false && stripos($val, 'http') !== 0) {
                            $val = 'https://www.tiktok.com/@'.ltrim($val, '@/');
                        }
                    } elseif ($key === 'youtube') {
                        if ($lower[0] === '@') { $val = 'https://www.youtube.com/'.$val; }
                        elseif (strpos($lower, 'youtube.com') === false && strpos($lower, 'youtu.be') === false && stripos($val, 'http') !== 0) {
                            $val = 'https://www.youtube.com/@'.ltrim($val, '@/');
                        }
                    }
                    // Prefijar esquema si falta
                    if (stripos($val, 'http://') !== 0 && stripos($val, 'https://') !== 0) {
                        $val = 'https://'.$val;
                    }
                    $norm[$key] = $val;
                }
                $socials = $norm;
            }
            
            $this->_view->debug_footer = $dbg + ['blockId'=>$blockId, 'rows'=>count($rows)];
            $this->_view->footer_logo = $logo;
            $this->_view->footer_docs = $docs;
            $this->_view->footer_aviso = $aviso;
            $this->_view->footer_copy  = $copy;
            $this->_view->footer_tel   = $tel;
            $this->_view->footer_mail  = $mail;
            $this->_view->footer_addr  = $addr;
            $this->_view->footer_social= $socials;
            // Exponer estructura extendida para Likecheck
            $this->_view->footer_logo1 = $logo1;
            $this->_view->footer_logo2 = $logo2;
            $this->_view->footer_logo1_text = $logo1t;
            $this->_view->footer_logo2_text = $logo2t;
            $this->_view->footer_title = $ftitle;
        } catch (\Throwable $e) {
            $this->_view->debug_footer = ['error'=>$e->getMessage()];
        }
    }

    // Helper: resuelve id_bloque para LikeCheck (id_pagina=1, id_seccion=3)
    protected function cmsResolveId(int $pestanaId, string $titulo, ?string $tipoBloque = null, ?int $subpestanaId = null)
    {
        try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
        if (!class_exists('CmsRepository')) {
            $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
            if (is_readable($repoPath)) { require_once $repoPath; }
        }
        $pid = $this->resolveCmsPageId();
        $sid = $this->resolveCmsSectionId('likecheck', $pid);
        // Coordendas base: página + sección + título; si se pasa pestaña, también se filtra por id_pestana
        $coords = [
            'id_pagina'  => $pid,
            'id_seccion' => $sid,
            'titulo'     => $titulo,
            'visible'    => 1,
        ];
        if ($pestanaId > 0) {
            $coords['id_pestana'] = $pestanaId;
        }
        if ($tipoBloque)   { $coords['tipo_bloque']   = $tipoBloque; }
        if ($subpestanaId) { $coords['id_subpestana'] = $subpestanaId; }
        $id = CmsRepository::resolveBlockId($coords);
        return $id ? (int)$id : 0;
    }

    public function index()
    {
        // Redirigir al inicio de likecheck
        $this->redireccionar('likecheck/inicio');
    }

    public function inicio()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Inicio (pestaña = 14) | bloques: 100
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > Inicio
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Inicio');
            // 100: Sección principal (video + texto + botón)
            $id100 = $this->cmsResolveId($PESTANA, 'Sección principal (video + texto + botón)', 'video_parrafo_boton', null);
            $b100 = CmsRepository::loadBlockByIdTypes($id100, ['video','parrafo','boton','titulo','subtitulo','texto','imagen'], 1);
            $this->_view->lc_inicio = $b100['byType'] ?? [];
            // Link real del botón (columna link) para tipo 'boton'
            $this->_view->lc_inicio_btn_link = CmsRepository::getFirstLinkForType($id100, 'boton', 1);
            $this->_view->debug_lc_100 = $b100['debug'] ?? [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('inicio'));
    }

    public function funcionalidades()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Funcionalidades (pestaña = 15) | bloques: 101,102
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > Funcionalidades
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Funcionalidades');
            // 101: Funcionalidades (JSON en campo contenido) -> titulo, puntos[], imagen
            // Nota: resolvemos el id del bloque solo por título y pedimos los elementos
            // tipo 'funcionalidades_card', cuyo campo 'contenido' guarda el JSON.
            $id101 = $this->cmsResolveId($PESTANA, 'Funcionalidades', null, null);
            $b101 = CmsRepository::loadElementsByBlockId($id101, 'funcionalidades_card', 1);
            $rawItems = $b101['items'] ?? [];
            $cards = [];
            if (!empty($rawItems)) {
                // Tomar el primer registro (contenido JSON); si hubiera más, usar todos
                $jsonStrings = [];
                foreach ($rawItems as $row) {
                    if (is_string($row)) {
                        // Fila viene directa como JSON
                        $jsonStrings[] = $row;
                    } elseif (is_array($row)) {
                        // En la mayoría de los casos viene en 'contenido'
                        if (isset($row['contenido']) && is_string($row['contenido'])) {
                            $jsonStrings[] = $row['contenido'];
                        }
                    }
                }
                foreach ($jsonStrings as $js) {
                    $decoded = json_decode($js, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !$decoded) { continue; }
                    // Puede venir como un solo objeto o como arreglo de objetos
                    $list = isset($decoded[0]) && is_array($decoded[0]) ? $decoded : [$decoded];
                    foreach ($list as $it) {
                        if (!is_array($it)) { continue; }
                        $cards[] = [
                            'titulo' => isset($it['titulo']) ? (string)$it['titulo'] : '',
                            'imagen' => isset($it['imagen']) ? (string)$it['imagen'] : null,
                            'puntos' => isset($it['puntos']) && is_array($it['puntos']) ? array_values($it['puntos']) : [],
                        ];
                    }
                }
            }
            $this->_view->lc_func_cards = $cards;
            $this->_view->debug_lc_101 = $b101['debug'] ?? [];

            // Para la vista, exponer detalle inicial: puntos de la primera funcionalidad
            $this->_view->lc_func_detalle = (!empty($cards) && !empty($cards[0]['puntos'])) ? $cards[0]['puntos'] : [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('funcionalidades'));
    }

    // Nueva ruta oficial: /likecheck/app_crm -> vista app&crm.phtml
    public function app_crm()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > App & CRM (pestaña = 16) | bloques: 103,104,106
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > APP & CRM
            $PESTANA = $this->resolveCmsTabId('likecheck', 'APP & CRM');
            // 103: App (imagen_texto_sin_boton) + lista_item
            $id103 = $this->cmsResolveId($PESTANA, 'App', 'imagen_texto_sin_boton', null);
            $b103 = CmsRepository::loadBlockByIdTypes($id103, ['titulo','texto','imagen','lista_item'], 1);
            $this->_view->lc_app = $b103['byType'] ?? [];
            $this->_view->debug_lc_103 = $b103['debug'] ?? [];
            // 104: CRM Administrativo (imagen_texto_sin_boton) + lista_item
            $id104 = $this->cmsResolveId($PESTANA, 'CRM Administrativo', 'imagen_texto_sin_boton', null);
            $b104 = CmsRepository::loadBlockByIdTypes($id104, ['titulo','texto','imagen','lista_item'], 1);
            $this->_view->lc_crm = $b104['byType'] ?? [];
            $this->_view->debug_lc_104 = $b104['debug'] ?? [];
            // 106: Agenda tu demo (boton_personalizado)
            $id106 = $this->cmsResolveId($PESTANA, 'Agenda tu demo', 'boton_personalizado', null);
            $b106 = CmsRepository::loadElementsByBlockId($id106, 'boton', 1);
            $this->_view->lc_appcrm_agenda = $b106['items'] ?? [];
            // Link real del botón para App & CRM (tipo 'boton')
            $link106 = CmsRepository::getFirstLinkForType($id106, 'boton', 1);
            // Fallback: si no se resolvió por helper, intentar tomarlo del primer item
            if (!$link106 && !empty($this->_view->lc_appcrm_agenda[0])) {
                $first = $this->_view->lc_appcrm_agenda[0];
                // Puede venir como arreglo o como stdClass con propiedad 'link'
                if (is_array($first) && !empty($first['link']) && is_string($first['link'])) {
                    $link106 = $first['link'];
                } elseif (is_object($first) && isset($first->link) && is_string($first->link) && $first->link !== '') {
                    $link106 = $first->link;
                }
            }
            $this->_view->lc_appcrm_agenda_link = $link106;
            $this->_view->debug_lc_106 = $b106['debug'] ?? [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('@likecheck/app&crm'));
    }

    public function planes()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Planes (pestaña = 17) | bloques: 105,107
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > Planes
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Planes');
            // 105: Planes LikeCheck (estructura v2: titulov2, subtitulov2, textov2)
            $id105 = $this->cmsResolveId($PESTANA, 'Planes LikeCheck ', null, null);
            $b105 = CmsRepository::loadBlockByIdTypes($id105, ['titulov2','subtitulov2','textov2'], 1);
            $this->_view->lc_planes_titulo = $b105['byType'] ?? [];
            $this->_view->debug_lc_105 = $b105['debug'] ?? [];
            // 107: Beneficios sin cargo (con lista de puntos)
            $id107 = $this->cmsResolveId($PESTANA, 'Beneficios sin cargo', null, null);
            $b107 = CmsRepository::loadBlockByIdTypes($id107, ['titulo','texto','imagen','lista_item'], 1);
            $this->_view->lc_planes_beneficios = $b107['byType'] ?? [];
            $this->_view->lc_planes_beneficios_list = $this->_view->lc_planes_beneficios['lista_item'] ?? [];
            $this->_view->debug_lc_107 = $b107['debug'] ?? [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('planes'));
    }

    public function cotizador()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Cotizador (pestaña = 18) | bloques: 108,109
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > Cotizador
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Cotizador');
            // 108: Cotizador - WhatsApp (bloque con botón/label, número y link)
            $id108 = $this->cmsResolveId($PESTANA, 'Cotizador - WhatsApp', null, null);
            $b108 = CmsRepository::loadBlockByIdTypes($id108, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->lc_cotizador_whatsapp = $b108['byType'] ?? [];
            // La URL real está en elementos tipo 'link', no en 'boton'
            $this->_view->lc_cotizador_whatsapp_link = CmsRepository::getFirstLinkForType($id108, 'link', 1);
            $this->_view->debug_lc_108 = $b108['debug'] ?? [];
            // 109: Agenda tu demo (boton_personalizado)
            $id109 = $this->cmsResolveId($PESTANA, 'Agenda tu demo', 'boton_personalizado', null);
            $b109 = CmsRepository::loadElementsByBlockId($id109, 'boton', 1);
            $this->_view->lc_cotizador_agenda = $b109['items'] ?? [];
            $this->_view->lc_cotizador_agenda_link = CmsRepository::getFirstLinkForType($id109, 'boton', 1);
            $this->_view->debug_lc_109 = $b109['debug'] ?? [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('cotizador'));
    }

    public function promocion()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Promoción (pestaña = 19) | bloques: 110-116
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            
            // LikeCheck > Promoción
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Promoción');
            // 110: Títulos Promoción (titulo_subtitulo_texto)
            $id110 = $this->cmsResolveId($PESTANA, 'Títulos Promoción', 'titulo_subtitulo_texto', null);
            $b110 = CmsRepository::loadBlockByIdTypes($id110, ['titulo','subtitulo','texto'], 1);
            $this->_view->lc_promo_titulos = $b110['byType'] ?? [];
            $this->_view->debug_lc_110 = $b110['debug'] ?? [];
            // 111: Tarjetas Promoción (imagen_texto)
            $id111 = $this->cmsResolveId($PESTANA, 'Tarjetas Promoción', 'imagen_texto', null);
            $b111 = CmsRepository::loadZippedItemsById($id111, ['imagen','titulo','texto'], 1);
            $this->_view->lc_promo_tarjetas = $b111['items'] ?? [];
            $this->_view->debug_lc_111 = $b111['debug'] ?? [];
            // 112: Contrata ahora (boton_personalizado)
            $id112 = $this->cmsResolveId($PESTANA, 'Contrata ahora', 'boton_personalizado', null);
            // Mantener carga de elementos tipo 'boton' para el label del CTA
            $b112 = CmsRepository::loadElementsByBlockId($id112, 'boton', 1);
            $this->_view->lc_promo_cta = $b112['items'] ?? [];
            // Resolver link real priorizando el tipo 'link' (como en Empresas bloque 53)
            try {
                $link112 = CmsRepository::getFirstLinkForType($id112, 'link', 1);
            } catch (\Throwable $e) { $link112 = null; }
            if (!$link112) {
                // Fallback: intentar desde tipo 'boton' para compatibilidad con estructura anterior
                try {
                    $link112 = CmsRepository::getFirstLinkForType($id112, 'boton', 1);
                } catch (\Throwable $e) { $link112 = null; }
            }
            $this->_view->lc_promo_cta_link = $link112 ?: null;
            $this->_view->debug_lc_112 = $b112['debug'] ?? [];
            // 113: Planes Prepago LikeCheck (titulo + texto)
            $id113 = $this->cmsResolveId($PESTANA, 'Planes Prepago LikeCheck', null, null);
            $b113 = CmsRepository::loadBlockByIdTypes($id113, ['titulo','texto'], 1);
            $this->_view->lc_promo_planes = $b113['byType'] ?? [];
            $this->_view->debug_lc_113 = $b113['debug'] ?? [];
            // 114: Imágenes (carrusel)
            $id114 = $this->cmsResolveId($PESTANA, 'Imágenes', 'carrusel', null);
            $b114 = CmsRepository::loadElementsByBlockId($id114, 'imagen', 1);
            $this->_view->lc_promo_carrusel = $b114['items'] ?? [];
            $this->_view->debug_lc_114 = $b114['debug'] ?? [];
            // 115: Imagen mujer (banner)
            $id115 = $this->cmsResolveId($PESTANA, 'Imagen mujer', 'banner', null);
            $b115 = CmsRepository::loadElementsByBlockId($id115, 'imagen', 1);
            $this->_view->lc_promo_banner1 = ($b115['items'][0] ?? null) ?: null;
            $this->_view->lc_promo_banner1_link = CmsRepository::getFirstLinkForType($id115, 'imagen', 1);
            $this->_view->debug_lc_115 = $b115['debug'] ?? [];
            // 116: Imagen “Adquiere tu pago” (banner)
            $id116 = $this->cmsResolveId($PESTANA, 'Imagen “Adquiere tu pago”', 'banner', null);
            $b116 = CmsRepository::loadElementsByBlockId($id116, 'imagen', 1);
            $this->_view->lc_promo_banner2 = ($b116['items'][0] ?? null) ?: null;
            $this->_view->lc_promo_banner2_link = CmsRepository::getFirstLinkForType($id116, 'imagen', 1);
            $this->_view->debug_lc_116 = $b116['debug'] ?? [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('promocion'));
    }

    public function clientes()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Clientes (pestaña = 20) | bloques: 117-121
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > Clientes
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Clientes');
            // 117: Nuestros clientes (carrusel)
            $id117 = $this->cmsResolveId($PESTANA, 'Nuestros clientes', 'carrusel', null);
            $b117 = CmsRepository::loadElementsByBlockId($id117, 'imagen', 1);
            $this->_view->lc_clientes_carrusel = $b117['items'] ?? [];
            $this->_view->debug_lc_117 = $b117['debug'] ?? [];
            // 118: Presencia LikeCheck (banner)
            $id118 = $this->cmsResolveId($PESTANA, 'Presencia LikeCheck', 'banner', null);
            $b118 = CmsRepository::loadElementsByBlockId($id118, 'imagen', 1);
            $this->_view->lc_clientes_presencia = ($b118['items'][0] ?? null) ?: null;
            $this->_view->lc_clientes_presencia_link = CmsRepository::getFirstLinkForType($id118, 'imagen', 1);
            $this->_view->debug_lc_118 = $b118['debug'] ?? [];
            // 119: Adaptable a cualquier tipo (tarjeta_simple) -> imagen + texto (etiqueta)
            $id119 = $this->cmsResolveId($PESTANA, 'Adaptable a cualquier tipo', 'tarjeta_simple', null);
            $b119 = CmsRepository::loadZippedItemsById($id119, ['imagen','texto'], 1);
            $this->_view->lc_clientes_adaptable = $b119['items'] ?? [];
            $this->_view->debug_lc_119 = $b119['debug'] ?? [];
            // 120: Imagen (banner)
            $id120 = $this->cmsResolveId($PESTANA, 'Imagen', 'banner', null);
            $b120 = CmsRepository::loadElementsByBlockId($id120, 'imagen', 1);
            $this->_view->lc_clientes_banner = ($b120['items'][0] ?? null) ?: null;
            $this->_view->lc_clientes_banner_link = CmsRepository::getFirstLinkForType($id120, 'imagen', 1);
            $this->_view->debug_lc_120 = $b120['debug'] ?? [];
            // 121: Testimonios (YouTube video) -> elemento tipo 'video'
            $id121 = $this->cmsResolveId($PESTANA, 'Testimonios', null, null);
            $b121 = CmsRepository::loadElementsByBlockId($id121, 'video', 1);
            $items121 = $b121['items'] ?? [];
            $this->_view->lc_clientes_testimonios = $items121;
            $link121 = null;
            if (!empty($items121)) {
                $first = $items121[0];
                if (is_string($first)) {
                    $link121 = trim($first);
                } elseif (is_array($first)) {
                    // puede venir en 'contenido' o en 'video'
                    if (isset($first['contenido']) && is_string($first['contenido'])) {
                        $link121 = trim($first['contenido']);
                    } elseif (isset($first['video']) && is_string($first['video'])) {
                        $link121 = trim($first['video']);
                    }
                }
            }
            $this->_view->lc_clientes_testimonios_link = $link121 ?: null;
            $this->_view->debug_lc_121 = $b121['debug'] ?? [];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('clientes'));
    }

    // Mapea /likecheck/contacto a la vista contactanos.phtml
    public function contacto()
    {
        $this->loadFooterLikecheck();
        // LikeCheck > Contactanos (pestaña = 21) | bloques: 122
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // LikeCheck > Contáctanos
            $PESTANA = $this->resolveCmsTabId('likecheck', 'Contáctanos');
            // 122: Comunícate con nosotros (email/whatsapp) - sin forzar tipo; mapear por contenido
            $id122 = $this->cmsResolveId($PESTANA, 'Comunícate con nosotros', null, null);
            $byType = [];
            $links = [];
            try {
                // 1) Tipos explícitos: correo/email/whatsapp/wa
                $rows = \Illuminate\Database\Capsule\Manager::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['tipo','contenido','link'])
                    ->where('id_bloque', $id122)
                    ->whereIn('tipo', ['correo','email','whatsapp','wa'])
                    ->where(function($q){ $q->where('visible', 1)->orWhereNull('visible'); })
                    ->orderBy('id_elemento')
                    ->get();
                // 2) Genérico: tipo='social' (contenido es la llave, link es la URL)
                $socialRows = \Illuminate\Database\Capsule\Manager::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['tipo','contenido','link'])
                    ->where('id_bloque', $id122)
                    ->where('tipo', 'social')
                    ->where(function($q){ $q->where('visible', 1)->orWhereNull('visible'); })
                    ->orderBy('id_elemento')
                    ->get();

                foreach (array_merge($rows->toArray(), $socialRows->toArray()) as $r) {
                    $tipo = strtolower(trim((string)($r->tipo ?? '')));
                    $keyField = strtolower(trim((string)($r->contenido ?? '')));
                    // Determinar clave
                    $key  = null;
                    if (in_array($tipo, ['correo','email'])) { $key = 'email'; }
                    else if (in_array($tipo, ['whatsapp','wa'])) { $key = 'whatsapp'; }
                    else if ($tipo === 'social') {
                        if (in_array($keyField, ['correo','email','mail'])) { $key = 'email'; }
                        if (in_array($keyField, ['whatsapp','wa','wame'])) { $key = 'whatsapp'; }
                    }
                    if (!$key) { continue; }
                    $url  = trim((string)($r->link ?? ''));
                    $val  = trim((string)($r->contenido ?? ''));
                    // Normalizar hrefs
                    if ($key === 'email') {
                        $href = $url !== '' ? $url : $val;
                        if ($href !== '' && stripos($href, 'mailto:') !== 0) { $href = 'mailto:'.$href; }
                        if ($href !== '') {
                            if (!isset($byType['email'])) { $byType['email'] = []; }
                            $byType['email'][] = $href;
                            $links[] = ['icon' => 'email', 'url' => $href];
                        }
                    } else if ($key === 'whatsapp') {
                        $href = $url !== '' ? $url : $val;
                        // Si es solo número, convertir a wa.me
                        $num = preg_replace('/\D+/', '', $href);
                        if ($href === '' && $val !== '') { $href = $val; }
                        if ($href !== '') {
                            if (stripos($href, 'http') !== 0) {
                                if ($num !== '') { $href = 'https://wa.me/'.$num; }
                            }
                            if (!isset($byType['whatsapp'])) { $byType['whatsapp'] = []; }
                            $byType['whatsapp'][] = $href;
                            $links[] = ['icon' => 'whatsapp', 'url' => $href];
                        }
                    }
                }
                $this->_view->lc_contacto_links = $links;
            } catch (\Throwable $e) {
                $this->_view->lc_contacto_links = [];
            }
            // Fallback extra: usar CmsRepository::getFirstLinkForType por tipo
            if (empty($byType['email']) && empty($byType['correo'])) {
                $mail = CmsRepository::getFirstLinkForType($id122, 'email', 1) ?: CmsRepository::getFirstLinkForType($id122, 'correo', 1);
                if ($mail) { $byType['email'] = [$mail]; }
            }
            if (empty($byType['whatsapp']) && empty($byType['wa'])) {
                $wa = CmsRepository::getFirstLinkForType($id122, 'whatsapp', 1) ?: CmsRepository::getFirstLinkForType($id122, 'wa', 1);
                if ($wa) { $byType['whatsapp'] = [$wa]; }
            }

            // Fallback amplio: inspeccionar todas las filas del bloque y deducir email/whatsapp por patrón
            if (empty($byType['email']) || empty($byType['whatsapp'])) {
                try {
                    $all = \Illuminate\Database\Capsule\Manager::connection('cms')
                        ->table('elementos_bloques')
                        ->select(['tipo','contenido','link'])
                        ->where('id_bloque', $id122)
                        ->where(function($q){ $q->where('visible', 1)->orWhereNull('visible'); })
                        ->orderBy('id_elemento')
                        ->get();
                    foreach ($all as $r) {
                        $tipo = strtolower(trim((string)($r->tipo ?? '')));
                        $cont = trim((string)($r->contenido ?? ''));
                        $lnk  = trim((string)($r->link ?? ''));
                        // Email por patrón
                        if (empty($byType['email'])) {
                            $candidate = $lnk !== '' ? $lnk : $cont;
                            if ($candidate !== '') {
                                if (stripos($candidate, 'mailto:') === 0 || strpos($candidate, '@') !== false) {
                                    if (stripos($candidate, 'mailto:') !== 0) { $candidate = 'mailto:'.$candidate; }
                                    $byType['email'] = [$candidate];
                                    $links[] = ['icon' => 'email', 'url' => $candidate];
                                }
                            }
                        }
                        // WhatsApp por patrón
                        if (empty($byType['whatsapp'])) {
                            $candidate = $lnk !== '' ? $lnk : $cont;
                            if ($candidate !== '') {
                                $lower = strtolower($candidate);
                                if (strpos($lower, 'wa.me') !== false || strpos($lower, 'api.whatsapp.com') !== false) {
                                    $byType['whatsapp'] = [$candidate];
                                    $links[] = ['icon' => 'whatsapp', 'url' => $candidate];
                                } else {
                                    $num = preg_replace('/\D+/', '', $candidate);
                                    if ($num !== '' && strlen($num) >= 8) {
                                        $byType['whatsapp'] = ['https://wa.me/'.$num];
                                        $links[] = ['icon' => 'whatsapp', 'url' => 'https://wa.me/'.$num];
                                    }
                                }
                            }
                        }
                        if (!empty($byType['email']) && !empty($byType['whatsapp'])) { break; }
                    }
                    $this->_view->lc_contacto_links = $links;
                    if (!empty($_GET['debugcms'])) {
                        $this->_view->lc_contacto_debug_rows = $all;
                    }
                } catch (\Throwable $e) {}
            }

            $this->_view->lc_contacto_social = $byType;
            $this->_view->debug_lc_122 = ['byType'=>$byType,'links'=>$this->_view->lc_contacto_links,'blockId'=>$id122];
        } catch (\Throwable $e) {}
        $this->_view->renderizar(array('@likecheck/contactanos'));
    }
}
