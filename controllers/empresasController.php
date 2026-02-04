<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class empresasController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $pid = $this->resolveCmsPageId();
        $this->loadHeaderSections($pid);
    }
    protected function loadFooterEmpresas(): void
    {
        try {
            
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            $pid = $this->resolveCmsPageId();
            $sidEmp = $this->resolveCmsSectionId('empresas', $pid);
            $sidPer = $this->resolveCmsSectionId('personas', $pid);
            $footerRes = CmsRepository::loadBlockByCoordsTypes([
                'id_pagina'  => $pid,
                'id_seccion' => $sidEmp,
                'tipo_bloque'=> 'footer',
                'visible'    => 1,
            ], ['imagen','link','social','texto','correo','telefono'], 1);
            $dbg = $footerRes['debug'] ?? [];
            $blockId = isset($footerRes['block']->id_bloque) ? (int)$footerRes['block']->id_bloque : 0;
            if ($blockId <= 0) {
                $alt = CmsRepository::loadBlockByCoordsTypes([
                    'id_pagina'=>$pid,'id_seccion'=>$sidEmp,'tipo_bloque'=>'footer_e','visible'=>1
                ], ['imagen','link','social','texto','correo','telefono'], 1);
                $dbg['alt_tipo'] = 'footer_e';
                $blockId = isset($alt['block']->id_bloque) ? (int)$alt['block']->id_bloque : 0;
            }
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
            $logo=null; $docs=[]; $aviso=null; $copy=null; $tel=null; $mail=null; $addr=null; $socials=[]; $ftitle=null;
            foreach ($rows as $r) {
                $tipo = strtolower(trim((string)($r->tipo ?? '')));
                $cont = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                $lnk  = is_string($r->link ?? null) ? trim($r->link) : '';
                switch ($tipo) {
                    case 'imagen': if ($logo===null && $cont!=='') { $logo=$cont; } break;
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
                        if ($addr===null && $cont!=='' && !preg_match('/\+?\d[\d\s\.-]{6,}/',$cont)) { $addr=$cont; }
                        break;
                    case 'telefono': if ($tel===null && $cont!=='') { $tel=$cont; } break;
                    case 'correo': if ($mail===null && $cont!=='') { $mail=$cont; } break;
                    case 'copyright': if ($copy===null && $cont!=='') { $copy=$cont; } break;
                    case 'titulo': if ($ftitle===null && $cont!=='') { $ftitle=$cont; } break;
                    case 'subtitulo': if ($ftitle===null && $cont!=='') { $ftitle=$cont; } break;
                }
            }
           
            if (empty($socials)) {
                try {
                    $pers = CmsRepository::loadBlockByCoordsTypes([
                        'id_pagina'=>$pid,'id_seccion'=>$sidPer,'tipo_bloque'=>'footer','visible'=>1
                    ], ['social','texto','correo','telefono','link'], 1);
                    $pid = isset($pers['block']->id_bloque) ? (int)$pers['block']->id_bloque : 0;
                    if ($pid>0) {
                        $prows = Capsule::connection('cms')
                            ->table('elementos_bloques')
                            ->select(['tipo','contenido','link'])
                            ->where('id_bloque', $pid)
                            ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                            ->orderBy('id_elemento')
                            ->get()->toArray();
                        foreach ($prows as $r) {
                            $t = strtolower(trim((string)($r->tipo ?? '')));
                            $c = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                            $l = is_string($r->link ?? null) ? trim($r->link) : '';
                            if ($t==='social' && $c!=='' && $l!=='') { $socials[strtolower($c)]=$l; }
                        }
                    }
                } catch (\Throwable $e) { $dbg['social_personas_error'] = $e->getMessage(); }
            }
           
            if (!empty($socials)) {
                $norm = [];
                foreach ($socials as $k=>$u) {
                    $key = strtolower(trim((string)$k));
                    $val = trim((string)$u);
                    if ($val==='') { continue; }
                    $lower = strtolower($val);
                    if ($key==='tiktok') {
                        if ($lower!=='' && $lower[0]==='@') { $val = 'https://www.tiktok.com/'.$val; }
                        elseif (strpos($lower,'tiktok.com')===false && stripos($val,'http')!==0) { $val = 'https://www.tiktok.com/@'.ltrim($val,'@/'); }
                    } elseif ($key==='youtube') {
                        if ($lower!=='' && $lower[0]==='@') { $val = 'https://www.youtube.com/'.$val; }
                        elseif (strpos($lower,'youtube.com')===false && strpos($lower,'youtu.be')===false && stripos($val,'http')!==0) { $val = 'https://www.youtube.com/@'.ltrim($val,'@/'); }
                    }
                    if (stripos($val,'http://')!==0 && stripos($val,'https://')!==0) { $val = 'https://'.$val; }
                    $norm[$key] = $val;
                }
                $socials = $norm;
            }
            // Empresas: forzar teléfono y correo a los de Personas SIEMPRE
            try {
                $pers = CmsRepository::loadBlockByCoordsTypes([
                    'id_pagina'=>$pid,'id_seccion'=>$sidPer,'tipo_bloque'=>'footer','visible'=>1
                ], ['telefono','correo','social','texto'], 1);
                $pid = isset($pers['block']->id_bloque) ? (int)$pers['block']->id_bloque : 0;
                if ($pid>0) {
                    $prows = Capsule::connection('cms')
                        ->table('elementos_bloques')
                        ->select(['tipo','contenido','link'])
                        ->where('id_bloque', $pid)
                        ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                        ->orderBy('id_elemento')
                        ->get()->toArray();
                    $ptel = null; $pmail = null;
                    foreach ($prows as $r) {
                        $t = strtolower(trim((string)($r->tipo ?? '')));
                        $c = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                        $l = is_string($r->link ?? null) ? trim($r->link) : '';
                        if ($t==='telefono' && $c!=='') { $ptel = $c; }
                        if ($t==='correo' && $c!=='') { $pmail = $c; }
                        if ($t==='social') {
                            if ($ptel===null && strtolower($c)==='telefono' && $l!=='') { $ptel = $l; }
                            if ($pmail===null && in_array(strtolower($c), ['correo','email','mail']) && ($l!=='' || $c!=='')) { $pmail = $l !== '' ? $l : $c; }
                        }
                        if ($ptel!==null && $pmail!==null) { break; }
                    }
                    if ($ptel!==null) { $tel = $ptel; }
                    if ($pmail!==null) { $mail = $pmail; }
                }
            } catch (\Throwable $e) { $dbg['telmail_personas_force_error'] = $e->getMessage(); }
            // Si Empresas no tiene aviso, intenta reutilizar el de Personas (seccion=1)
            if (empty($aviso)) {
                try {
                    $pers = CmsRepository::loadBlockByCoordsTypes([
                        'id_pagina'=>$pid,'id_seccion'=>$sidPer,'tipo_bloque'=>'footer','visible'=>1
                    ], ['aviso','texto'], 1);
                    $pid = isset($pers['block']->id_bloque) ? (int)$pers['block']->id_bloque : 0;
                    if ($pid>0) {
                        $prows = Capsule::connection('cms')
                            ->table('elementos_bloques')
                            ->select(['tipo','contenido','link'])
                            ->where('id_bloque', $pid)
                            ->where('visible', 1)
                            ->orderBy('id_elemento')
                            ->get()->toArray();
                        foreach ($prows as $r) {
                            $t = strtolower(trim((string)($r->tipo ?? '')));
                            $c = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                            $l = is_string($r->link ?? null) ? trim($r->link) : '';
                            if ($t==='aviso' && ($c!=='' || $l!=='')) { $aviso = $c!=='' ? $c : $l; break; }
                            if (preg_match('/aviso\s+de\s+privacidad/i',$c)) { $aviso=$c; break; }
                        }
                    }
                } catch (\Throwable $e) { $dbg['aviso_personas_error'] = $e->getMessage(); }
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
            $this->_view->footer_title = $ftitle;
        } catch (\Throwable $e) {
            $this->_view->debug_footer = ['error'=>$e->getMessage()];
        }
    }

    protected function cmsResolveId(int $pestanaId, string $titulo, ?string $tipoBloque = null, ?int $subpestanaId = null)
    {
        try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
        if (!class_exists('CmsRepository')) {
            $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
            if (is_readable($repoPath)) { require_once $repoPath; }
        }
        $pid = $this->resolveCmsPageId();
        $sid = $this->resolveCmsSectionId('empresas', $pid);
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
        $this->redireccionar('empresas/inicio');
    }
 
    public function inicio()
    {
        $this->loadFooterEmpresas();
        // Cargar CMS de Empresas > Inicio (bloques 36-45)
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Empresas > Inicio
            $PESTANA = $this->resolveCmsTabId('empresas', 'Inicio');
            // 36: Banner principal
            $id36 = $this->cmsResolveId($PESTANA, 'Banner principal', 'banner', null);
            $b36 = CmsRepository::loadElementsByBlockId($id36, 'imagen', 1);
            $this->_view->emp_banner1 = $b36['items'][0] ?? null;
            $this->_view->debug_bloque36 = $b36['debug'] ?? [];
            // 37: Nuestro negocio (titulo + texto)
            $id37 = $this->cmsResolveId($PESTANA, 'Nuestro negocio es hacer crecer el tuyo', 'texto_simple', null);
            $b37 = CmsRepository::loadBlockByIdTypes($id37, ['titulo','texto'], 1);
            $this->_view->emp_intro = $b37['byType'] ?? [];
            $this->_view->debug_bloque37 = $b37['debug'] ?? [];
            // 38: Soluciones empresariales (tarjeta_simple) con links reales por item
            $id38 = $this->cmsResolveId($PESTANA, 'Soluciones empresariales', 'tarjeta_simple', null);
            $b38 = CmsRepository::loadZippedItemsByIdWithLinks($id38, ['imagen','titulo','texto','boton'], 1);
            $this->_view->emp_soluciones = $b38['items'] ?? [];
            $this->_view->debug_bloque38 = $b38['debug'] ?? [];
            // 39: Banner intermedio (imagen + link con prioridad: imagen.linkcol -> tipo 'link' -> boton -> titulo)
            $id39 = $this->cmsResolveId($PESTANA, 'Banner intermedio', 'banner', null);
            $b39img = CmsRepository::loadElementsByBlockId($id39, 'imagen', 1);
            $this->_view->emp_banner_mid_img = $b39img['items'][0] ?? null;
            $b39link = CmsRepository::getFirstLinkForType($id39, 'imagen', 1);
            if (!$b39link) {
                $linkType = CmsRepository::loadElementsByBlockId($id39, 'link', 1);
                $b39link = $linkType['items'][0] ?? null;
            }
            if (!$b39link) { $b39link = CmsRepository::getFirstLinkForType($id39, 'boton', 1); }
            if (!$b39link) { $b39link = CmsRepository::getFirstLinkForType($id39, 'titulo', 1); }
            $this->_view->emp_banner_mid_link = $b39link ?: null;
            $this->_view->debug_bloque39 = $b39img['debug'] ?? [];
            // 40: Tarjetas (tarjeta) con links reales por item
            $id40 = $this->cmsResolveId($PESTANA, 'Tarjetas', 'tarjeta', null);
            $b40 = CmsRepository::loadZippedItemsByIdWithLinks($id40, ['imagen','titulo','boton'], 1);
            $this->_view->emp_tarjetas = $b40['items'] ?? [];
            $this->_view->debug_bloque40 = $b40['debug'] ?? [];
            // 41: Banner inferior (imagen + link con prioridad: imagen.linkcol -> tipo 'link' -> boton -> titulo)
            $id41 = $this->cmsResolveId($PESTANA, 'Banner inferior', 'banner', null);
            $b41img = CmsRepository::loadElementsByBlockId($id41, 'imagen', 1);
            $this->_view->emp_banner_bottom_img = $b41img['items'][0] ?? null;
            $b41link = CmsRepository::getFirstLinkForType($id41, 'imagen', 1);
            if (!$b41link) {
                $linkType41 = CmsRepository::loadElementsByBlockId($id41, 'link', 1);
                $b41link = $linkType41['items'][0] ?? null;
            }
            if (!$b41link) { $b41link = CmsRepository::getFirstLinkForType($id41, 'boton', 1); }
            if (!$b41link) { $b41link = CmsRepository::getFirstLinkForType($id41, 'titulo', 1); }
            $this->_view->emp_banner_bottom_link = $b41link ?: null;
            $this->_view->debug_bloque41 = $b41img['debug'] ?? [];
            // 42: Agenda tu demo (boton_personalizado)
            $id42 = $this->cmsResolveId($PESTANA, 'Agenda tu demo', 'boton_personalizado', null);
            $b42 = CmsRepository::loadBlockByIdTypes($id42, ['boton','link','texto','titulo'], 1);
            $this->_view->emp_agenda = $b42['byType'] ?? [];
            $this->_view->emp_agenda_link = CmsRepository::getFirstLinkForType($id42, 'boton', 1) ?: (($this->_view->emp_agenda['link'][0] ?? null) ?: null);
            $this->_view->debug_bloque42 = $b42['debug'] ?? [];
            // 43: Atajos (captura) - resolver links reales por índice
            $id43 = $this->cmsResolveId($PESTANA, 'Atajos', 'captura', null);
            $b43 = CmsRepository::loadZippedItemsByIdWithLinks($id43, ['titulo'], 1);
            $this->_view->emp_atajos = $b43['items'] ?? [];
            $this->_view->debug_bloque43 = $b43['debug'] ?? [];
            // 44: Eleva la eficiencia (titulo_subtitulo_texto)
            $id44 = $this->cmsResolveId($PESTANA, 'Eleva la eficiencia', 'titulo_subtitulo_texto', null);
            $b44 = CmsRepository::loadBlockByIdTypes($id44, ['titulo','subtitulo','texto'], 1);
            $this->_view->emp_eleva = $b44['byType'] ?? [];
            $this->_view->debug_bloque44 = $b44['debug'] ?? [];
            // 45: Redes sociales (social)
            $id45 = $this->cmsResolveId($PESTANA, 'Redes sociales', 'social', null);
            $b45 = CmsRepository::loadBlockByIdTypes($id45, ['facebook','instagram','tiktok','youtube','whatsapp','wa','telefono','correo'], 1);
            $this->_view->emp_social = $b45['byType'] ?? [];
            // También soportar esquema alterno: filas con icono en 'contenido' (ej. 'fab fa-facebook-f') y URL en 'link'
            // Traer cualquier fila visible con link no vacío, sin importar 'tipo' (puede ser 'social' o 'link')
            try {
                $rows = Capsule::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['contenido','link'])
                    ->where('id_bloque', $id45)
                    ->where('visible', 1)
                    ->whereNotNull('link')
                    ->whereRaw("TRIM(link) <> ''")
                    ->orderBy('id_elemento')
                    ->get();
                $this->_view->emp_social_links = array_map(function($r){
                    return ['icon' => (string)($r->contenido ?? ''), 'url' => (string)($r->link ?? '')];
                }, $rows ? $rows->toArray() : []);
            } catch (\Throwable $e) {
                $this->_view->emp_social_links = [];
            }
            $this->_view->debug_bloque45 = $b45['debug'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('empresas'));
    }

    public function tarifas()
    {
        $this->loadFooterEmpresas();
        // Cargar CMS de Empresas > Tarifas (bloques 46-53)
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Empresas > Tarifas preferenciales
            $PESTANA = $this->resolveCmsTabId('empresas', 'Tarifas preferenciales');
            // 46: Banner principal
            $id46 = $this->cmsResolveId($PESTANA, 'Banner principal', 'banner', null);
            $b46 = CmsRepository::loadElementsByBlockId($id46, 'imagen', 1);
            $this->_view->emp_ta_banner = $b46['items'][0] ?? null;
            $this->_view->debug_bloque46 = $b46['debug'] ?? [];
            // 47: Tarjetas de información (tarjeta_simple) - incluir 'texto' para la leyenda
            $id47 = $this->cmsResolveId($PESTANA, 'Tarjetas de información', 'tarjeta_simple', null);
            $b47 = CmsRepository::loadZippedItemsById($id47, ['imagen','texto','titulo','boton','link'], 1);
            $this->_view->emp_ta_cards = $b47['items'] ?? [];
            $this->_view->debug_bloque47 = $b47['debug'] ?? [];
            // 48: Mayor control (imagen_texto_sin_boton) + lista de puntos
            $id48 = $this->cmsResolveId($PESTANA, 'Mayor control, menor costo', 'imagen_texto_sin_boton', null);
            $b48 = CmsRepository::loadBlockByIdTypes($id48, ['titulo','subtitulo','texto','imagen','lista_item'], 1);
            $this->_view->emp_ta_control = $b48['byType'] ?? [];
            $this->_view->debug_bloque48 = $b48['debug'] ?? [];
            // 49: Agenda tu demo (boton_personalizado)
            $id49 = $this->cmsResolveId($PESTANA, 'Agenda tu demo', 'boton_personalizado', null);
            $b49 = CmsRepository::loadBlockByIdTypes($id49, ['boton','link','texto','titulo'], 1);
            $this->_view->emp_ta_agenda = $b49['byType'] ?? [];
            // Resolver link real: priorizar link de la fila 'boton'
            try {
                $link49 = CmsRepository::getFirstLinkForType($id49, 'boton', 1);
            } catch (\Throwable $e) { $link49 = null; }
            if (!$link49) { $link49 = $this->_view->emp_ta_agenda['link'][0] ?? null; }
            $this->_view->emp_ta_agenda_link = $link49 ?: null;
            $this->_view->debug_bloque49 = $b49['debug'] ?? [];
            // 50: Banner LikeCheck (imagen + link con prioridad: imagen.linkcol -> tipo 'link' -> boton -> titulo)
            $id50 = $this->cmsResolveId($PESTANA, 'Banner LikeCheck', 'banner', null);
            $b50 = CmsRepository::loadElementsByBlockId($id50, 'imagen', 1);
            $this->_view->emp_ta_banner_lc = $b50['items'][0] ?? null;
            // Resolver link real del banner 50
            $b50link = CmsRepository::getFirstLinkForType($id50, 'imagen', 1);
            if (!$b50link) {
                $linkType50 = CmsRepository::loadElementsByBlockId($id50, 'link', 1);
                $b50link = $linkType50['items'][0] ?? null;
            }
            if (!$b50link) { $b50link = CmsRepository::getFirstLinkForType($id50, 'boton', 1); }
            if (!$b50link) { $b50link = CmsRepository::getFirstLinkForType($id50, 'titulo', 1); }
            $this->_view->emp_ta_banner_lc_link = $b50link ?: null;
            $this->_view->debug_bloque50 = $b50['debug'] ?? [];
            // 51: Agenda tu demo LikeCheck (boton_personalizado)
            $id51 = $this->cmsResolveId($PESTANA, 'Agenda tu demo LikeCheck', 'boton_personalizado', null);
            $b51 = CmsRepository::loadBlockByIdTypes($id51, ['boton','link','texto','titulo'], 1);
            $this->_view->emp_ta_agenda_lc = $b51['byType'] ?? [];
            // Resolver link real priorizando botón
            try {
                $link51 = CmsRepository::getFirstLinkForType($id51, 'boton', 1);
            } catch (\Throwable $e) { $link51 = null; }
            if (!$link51) { $link51 = $this->_view->emp_ta_agenda_lc['link'][0] ?? null; }
            $this->_view->emp_ta_agenda_lc_link = $link51 ?: null;
            $this->_view->debug_bloque51 = $b51['debug'] ?? [];
            // 52: Botón Agenda adicional (boton_personalizado)
            $id52 = $this->cmsResolveId($PESTANA, 'Botón Agenda', 'boton_personalizado', null);
            $b52 = CmsRepository::loadBlockByIdTypes($id52, ['boton','link','texto','titulo'], 1);
            $this->_view->emp_ta_btn_agenda = $b52['byType'] ?? [];
            // Resolver link real priorizando el de 'boton'
            try {
                $link52 = CmsRepository::getFirstLinkForType($id52, 'boton', 1);
            } catch (\Throwable $e) { $link52 = null; }
            if (!$link52) { $link52 = $this->_view->emp_ta_btn_agenda['link'][0] ?? null; }
            $this->_view->emp_ta_btn_agenda_link = $link52 ?: null;
            $this->_view->debug_bloque52 = $b52['debug'] ?? [];
            // 53: Botón WhatsApp (boton)
            $id53 = $this->cmsResolveId($PESTANA, 'WhatsApp', 'boton', null);
            $b53 = CmsRepository::loadBlockByIdTypes($id53, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->emp_ta_whatsapp = $b53['byType'] ?? [];
            try { $this->_view->emp_ta_whatsapp_link = CmsRepository::getFirstLinkForType($id53, 'link', 1); } catch (\Throwable $e) { $this->_view->emp_ta_whatsapp_link = null; }
            $this->_view->debug_bloque53 = $b53['debug'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('tarifas'));
    }

    public function marcaRapida()
    {
        $this->loadFooterEmpresas();
        // Cargar CMS de Empresas > Marcas Rápidas (bloques 54-67)
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Empresas > Marcas rápidas
            $PESTANA = $this->resolveCmsTabId('empresas', 'Marcas rápidas');
            // 54: Sección 1 (texto_video) - solo video y texto
            $id54 = $this->cmsResolveId($PESTANA, 'Sección 1', 'texto_video', null);
            $b54 = CmsRepository::loadBlockByIdTypes($id54, ['video','texto'], 1);
            $this->_view->mr_sec1 = $b54['byType'] ?? [];
            $this->_view->debug_bloque54 = $b54['debug'] ?? [];

            // 55: Banner principal (imagen + link)
            $id55 = $this->cmsResolveId($PESTANA, 'Banner principal', 'banner', null);
            $b55 = CmsRepository::loadElementsByBlockId($id55, 'imagen', 1);
            $this->_view->mr_banner1 = $b55['items'][0] ?? null;
            $b55link = CmsRepository::getFirstLinkForType($id55, 'imagen', 1);
            if (!$b55link) {
                $linkType55 = CmsRepository::loadElementsByBlockId($id55, 'link', 1);
                $b55link = $linkType55['items'][0] ?? null;
            }
            if (!$b55link) { $b55link = CmsRepository::getFirstLinkForType($id55, 'boton', 1); }
            if (!$b55link) { $b55link = CmsRepository::getFirstLinkForType($id55, 'titulo', 1); }
            $this->_view->mr_banner1_link = $b55link ?: null;
            $this->_view->debug_bloque55 = $b55['debug'] ?? [];

            // 56: Texto informativo (párrafo)
            $id56 = $this->cmsResolveId($PESTANA, 'Texto informativoo', 'parrafo', null);
            $b56 = CmsRepository::loadBlockByIdTypes($id56, ['texto','titulo','subtitulo'], 1);
            $this->_view->mr_info = $b56['byType'] ?? [];
            $this->_view->debug_bloque56 = $b56['debug'] ?? [];

            // 57: Banner secundario (imagen + link)
            $id57 = $this->cmsResolveId($PESTANA, 'Banner secundario', 'banner', null);
            $b57 = CmsRepository::loadElementsByBlockId($id57, 'imagen', 1);
            $this->_view->mr_banner2 = $b57['items'][0] ?? null;
            $b57link = CmsRepository::getFirstLinkForType($id57, 'imagen', 1);
            if (!$b57link) {
                $linkType57 = CmsRepository::loadElementsByBlockId($id57, 'link', 1);
                $b57link = $linkType57['items'][0] ?? null;
            }
            if (!$b57link) { $b57link = CmsRepository::getFirstLinkForType($id57, 'boton', 1); }
            if (!$b57link) { $b57link = CmsRepository::getFirstLinkForType($id57, 'titulo', 1); }
            $this->_view->mr_banner2_link = $b57link ?: null;
            $this->_view->debug_bloque57 = $b57['debug'] ?? [];

            // 58: Tu ecosistema digital (texto_simple)
            $id58 = $this->cmsResolveId($PESTANA, 'Tu ecosistema digital', 'texto_simple', null);
            $b58 = CmsRepository::loadBlockByIdTypes($id58, ['titulo','texto','subtitulo'], 1);
            $this->_view->mr_ecosistema = $b58['byType'] ?? [];
            $this->_view->debug_bloque58 = $b58['debug'] ?? [];

            // 59: Imágenes de tu ecosistema (imagen_texto) - varios items
            $id59 = $this->cmsResolveId($PESTANA, 'Imágenes de tu ecosistema', 'imagen_texto', null);
            $b59 = CmsRepository::loadZippedItemsById($id59, ['imagen','titulo','texto'], 1);
            $this->_view->mr_ecosistema_cards = $b59['items'] ?? [];
            $this->_view->debug_bloque59 = $b59['debug'] ?? [];

            // 60: Desarrollo (imagen_texto_sin_boton)
            $id60 = $this->cmsResolveId($PESTANA, 'Desarrollo', 'imagen_texto_sin_boton', null);
            $b60 = CmsRepository::loadBlockByIdTypes($id60, ['titulo','subtitulo','texto','imagen'], 1);
            $this->_view->mr_desarrollo = $b60['byType'] ?? [];
            $this->_view->debug_bloque60 = $b60['debug'] ?? [];

            // 61: Soluciones modulares (imagen_texto_sin_boton)
            $id61 = $this->cmsResolveId($PESTANA, 'Soluciones modulares', 'imagen_texto_sin_boton', null);
            $b61 = CmsRepository::loadBlockByIdTypes($id61, ['titulo','subtitulo','texto','imagen'], 1);
            $this->_view->mr_modulares = $b61['byType'] ?? [];
            $this->_view->debug_bloque61 = $b61['debug'] ?? [];

            // 62: Nicho comercial (tarjetas_texto) incluyendo campos v2
            $id62 = $this->cmsResolveId($PESTANA, 'Nicho comercial', 'tarjetas_texto', null);
            $b62 = CmsRepository::loadZippedItemsById($id62, ['titulo','subtitulo','texto','imagen','titulov2','subtitulov2','textov2'], 1);
            $this->_view->mr_nicho_com = $b62['items'] ?? [];
            $this->_view->debug_bloque62 = $b62['debug'] ?? [];

            // 63: Nicho institucional (tarjetas_texto) incluyendo campos v2
            $id63 = $this->cmsResolveId($PESTANA, 'Nicho institucional', 'tarjetas_texto', null);
            $b63 = CmsRepository::loadZippedItemsById($id63, ['titulo','subtitulo','texto','imagen','titulov2','subtitulov2','textov2'], 1);
            $this->_view->mr_nicho_inst = $b63['items'] ?? [];
            $this->_view->debug_bloque63 = $b63['debug'] ?? [];

            // 64: Imagen móvil (banner + link)
            $id64 = $this->cmsResolveId($PESTANA, 'Imagen móvil', 'banner', null);
            $b64 = CmsRepository::loadElementsByBlockId($id64, 'imagen', 1);
            $this->_view->mr_movil = $b64['items'][0] ?? null;
            $b64link = CmsRepository::getFirstLinkForType($id64, 'imagen', 1);
            if (!$b64link) {
                $linkType64 = CmsRepository::loadElementsByBlockId($id64, 'link', 1);
                $b64link = $linkType64['items'][0] ?? null;
            }
            if (!$b64link) { $b64link = CmsRepository::getFirstLinkForType($id64, 'boton', 1); }
            if (!$b64link) { $b64link = CmsRepository::getFirstLinkForType($id64, 'titulo', 1); }
            $this->_view->mr_movil_link = $b64link ?: null;
            $this->_view->debug_bloque64 = $b64['debug'] ?? [];

            // 65: Banner Whitephone (imagen + link)
            $id65 = $this->cmsResolveId($PESTANA, 'Banner Whitephone', 'banner', null);
            $b65 = CmsRepository::loadElementsByBlockId($id65, 'imagen', 1);
            $this->_view->mr_whitephone = $b65['items'][0] ?? null;
            $b65link = CmsRepository::getFirstLinkForType($id65, 'imagen', 1);
            if (!$b65link) {
                $linkType65 = CmsRepository::loadElementsByBlockId($id65, 'link', 1);
                $b65link = $linkType65['items'][0] ?? null;
            }
            if (!$b65link) { $b65link = CmsRepository::getFirstLinkForType($id65, 'boton', 1); }
            if (!$b65link) { $b65link = CmsRepository::getFirstLinkForType($id65, 'titulo', 1); }
            $this->_view->mr_whitephone_link = $b65link ?: null;
            $this->_view->debug_bloque65 = $b65['debug'] ?? [];

            // 66: Agendar consulta (boton_personalizado)
            $id66 = $this->cmsResolveId($PESTANA, 'Agendar consulta', 'boton_personalizado', null);
            $b66 = CmsRepository::loadBlockByIdTypes($id66, ['boton','link','texto','titulo'], 1);
            $this->_view->mr_agenda = $b66['byType'] ?? [];
            // Resolver link real priorizando el de 'boton'
            try {
                $agLink = CmsRepository::getFirstLinkForType($id66, 'boton', 1);
            } catch (\Throwable $e) { $agLink = null; }
            if (!$agLink) { $agLink = $this->_view->mr_agenda['link'][0] ?? null; }
            $this->_view->mr_agenda_link = $agLink ?: null;
            $this->_view->debug_bloque66 = $b66['debug'] ?? [];

            // 67: Botón WhatsApp
            $id67 = $this->cmsResolveId($PESTANA, 'WhatsApp', 'boton', null);
            $b67 = CmsRepository::loadBlockByIdTypes($id67, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->mr_whatsapp = $b67['byType'] ?? [];
            try { $this->_view->mr_whatsapp_link = CmsRepository::getFirstLinkForType($id67, 'link', 1); } catch (\Throwable $e) { $this->_view->mr_whatsapp_link = null; }
            $this->_view->debug_bloque67 = $b67['debug'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('marcaRapida'));
    }

    public function marcasBlancas()
    {
        $this->loadFooterEmpresas();
        // Cargar CMS de Empresas > Marcas Blancas (bloques 68-81)
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Empresas > Marcas Blancas
            $PESTANA = $this->resolveCmsTabId('empresas', 'Marcar Blancas');
            // 68: Banner principal (imagen + link)
            $id68 = $this->cmsResolveId($PESTANA, 'Banner principal', 'banner', null);
            $b68 = CmsRepository::loadElementsByBlockId($id68, 'imagen', 1);
            $this->_view->mb_banner1 = $b68['items'][0] ?? null;
            $b68link = CmsRepository::getFirstLinkForType($id68, 'imagen', 1);
            if (!$b68link) {
                $linkType68 = CmsRepository::loadElementsByBlockId($id68, 'link', 1);
                $b68link = $linkType68['items'][0] ?? null;
            }
            if (!$b68link) { $b68link = CmsRepository::getFirstLinkForType($id68, 'boton', 1); }
            if (!$b68link) { $b68link = CmsRepository::getFirstLinkForType($id68, 'titulo', 1); }
            $this->_view->mb_banner1_link = $b68link ?: null;
            $this->_view->debug_mb_68 = $b68['debug'] ?? [];

            // 69: Likephone te ayuda... (texto_simple)
            $id69 = $this->cmsResolveId($PESTANA, 'Likephone te ayuda a lanzar tu propia Marca de Telefonía Móvil', 'texto_simple', null);
            $b69 = CmsRepository::loadBlockByIdTypes($id69, ['titulo','subtitulo','texto'], 1);
            $this->_view->mb_ayuda = $b69['byType'] ?? [];
            $this->_view->debug_mb_69 = $b69['debug'] ?? [];

            // 70: Banner intermedio (imagen + link)
            $id70 = $this->cmsResolveId($PESTANA, 'Banner intermedio', 'banner', null);
            $b70 = CmsRepository::loadElementsByBlockId($id70, 'imagen', 1);
            $this->_view->mb_banner2 = $b70['items'][0] ?? null;
            $b70link = CmsRepository::getFirstLinkForType($id70, 'imagen', 1);
            if (!$b70link) {
                $linkType70 = CmsRepository::loadElementsByBlockId($id70, 'link', 1);
                $b70link = $linkType70['items'][0] ?? null;
            }
            if (!$b70link) { $b70link = CmsRepository::getFirstLinkForType($id70, 'boton', 1); }
            if (!$b70link) { $b70link = CmsRepository::getFirstLinkForType($id70, 'titulo', 1); }
            $this->_view->mb_banner2_link = $b70link ?: null;
            $this->_view->debug_mb_70 = $b70['debug'] ?? [];

            // 71: Tu ecosistema digital (texto_simple)
            $id71 = $this->cmsResolveId($PESTANA, 'Tu ecosistema digital', 'texto_simple', null);
            $b71 = CmsRepository::loadBlockByIdTypes($id71, ['titulo','subtitulo','texto'], 1);
            $this->_view->mb_ecos_titulo = $b71['byType'] ?? [];
            $this->_view->debug_mb_71 = $b71['debug'] ?? [];

            // 72: Imágenes de tu ecosistema (imagen_texto)
            $id72 = $this->cmsResolveId($PESTANA, 'Imágenes de tu ecosistema', 'imagen_texto', null);
            $b72 = CmsRepository::loadZippedItemsById($id72, ['imagen','titulo','texto'], 1);
            $this->_view->mb_ecos_cards = $b72['items'] ?? [];
            $this->_view->debug_mb_72 = $b72['debug'] ?? [];

            // 73: Banner integración por APIs (imagen + link)
            $id73 = $this->cmsResolveId($PESTANA, 'Banner integración por APIs', 'banner', null);
            $b73 = CmsRepository::loadElementsByBlockId($id73, 'imagen', 1);
            $this->_view->mb_apis = $b73['items'][0] ?? null;
            $b73link = CmsRepository::getFirstLinkForType($id73, 'imagen', 1);
            if (!$b73link) {
                $linkType73 = CmsRepository::loadElementsByBlockId($id73, 'link', 1);
                $b73link = $linkType73['items'][0] ?? null;
            }
            if (!$b73link) { $b73link = CmsRepository::getFirstLinkForType($id73, 'boton', 1); }
            if (!$b73link) { $b73link = CmsRepository::getFirstLinkForType($id73, 'titulo', 1); }
            $this->_view->mb_apis_link = $b73link ?: null;
            $this->_view->debug_mb_73 = $b73['debug'] ?? [];

            // 74: Banner Whitephone (imagen + link)
            $id74 = $this->cmsResolveId($PESTANA, 'Banner Whitephone', 'banner', null);
            $b74 = CmsRepository::loadElementsByBlockId($id74, 'imagen', 1);
            $this->_view->mb_whitephone = $b74['items'][0] ?? null;
            $b74link = CmsRepository::getFirstLinkForType($id74, 'imagen', 1);
            if (!$b74link) {
                $linkType74 = CmsRepository::loadElementsByBlockId($id74, 'link', 1);
                $b74link = $linkType74['items'][0] ?? null;
            }
            if (!$b74link) { $b74link = CmsRepository::getFirstLinkForType($id74, 'boton', 1); }
            if (!$b74link) { $b74link = CmsRepository::getFirstLinkForType($id74, 'titulo', 1); }
            $this->_view->mb_whitephone_link = $b74link ?: null;
            $this->_view->debug_mb_74 = $b74['debug'] ?? [];

            // 75: Medio de recarga (tarjeta_mixta)
            $id75 = $this->cmsResolveId($PESTANA, 'Medio de recarga', 'tarjeta_mixta', null);
            $b75 = CmsRepository::loadZippedItemsById($id75, ['imagen','titulo','texto','link','boton'], 1);
            $this->_view->mb_recarga = $b75['items'] ?? [];
            $this->_view->debug_mb_75 = $b75['debug'] ?? [];

            // 76: Soluciones modulares (texto_simple)
            $id76 = $this->cmsResolveId($PESTANA, 'Soluciones modulares', 'texto_simple', null);
            $b76 = CmsRepository::loadBlockByIdTypes($id76, ['titulo','subtitulo','texto'], 1);
            $this->_view->mb_modulares = $b76['byType'] ?? [];
            $this->_view->debug_mb_76 = $b76['debug'] ?? [];

            // 77: Nicho comercial (tarjetas_texto) incluyendo campos v2
            $id77 = $this->cmsResolveId($PESTANA, 'Nicho comercial', 'tarjetas_texto', null);
            $b77 = CmsRepository::loadZippedItemsById($id77, ['titulo','subtitulo','texto','imagen','titulov2','subtitulov2','textov2'], 1);
            $this->_view->mb_nicho_com = $b77['items'] ?? [];
            $this->_view->debug_mb_77 = $b77['debug'] ?? [];

            // 78: Nicho institucional (tarjetas_texto) incluyendo campos v2
            $id78 = $this->cmsResolveId($PESTANA, 'Nicho institucional', 'tarjetas_texto', null);
            $b78 = CmsRepository::loadZippedItemsById($id78, ['titulo','subtitulo','texto','imagen','titulov2','subtitulov2','textov2'], 1);
            $this->_view->mb_nicho_inst = $b78['items'] ?? [];
            $this->_view->debug_mb_78 = $b78['debug'] ?? [];

            // 79: Imagen móvil (banner + link)
            $id79 = $this->cmsResolveId($PESTANA, 'Imagen móvil', 'banner', null);
            $b79 = CmsRepository::loadElementsByBlockId($id79, 'imagen', 1);
            $this->_view->mb_movil = $b79['items'][0] ?? null;
            $b79link = CmsRepository::getFirstLinkForType($id79, 'imagen', 1);
            if (!$b79link) {
                $linkType79 = CmsRepository::loadElementsByBlockId($id79, 'link', 1);
                $b79link = $linkType79['items'][0] ?? null;
            }
            if (!$b79link) { $b79link = CmsRepository::getFirstLinkForType($id79, 'boton', 1); }
            if (!$b79link) { $b79link = CmsRepository::getFirstLinkForType($id79, 'titulo', 1); }
            $this->_view->mb_movil_link = $b79link ?: null;
            $this->_view->debug_mb_79 = $b79['debug'] ?? [];

            // 80: Agendar consulta (boton_personalizado)
            $id80 = $this->cmsResolveId($PESTANA, 'Agendar consulta', 'boton_personalizado', null);
            $b80 = CmsRepository::loadBlockByIdTypes($id80, ['boton','link','titulo','texto'], 1);
            $this->_view->mb_agenda = $b80['byType'] ?? [];
            // Resolver link real priorizando botón
            try { $mbAgLink = CmsRepository::getFirstLinkForType($id80, 'boton', 1); } catch (\Throwable $e) { $mbAgLink = null; }
            if (!$mbAgLink) { $mbAgLink = $this->_view->mb_agenda['link'][0] ?? null; }
            $this->_view->mb_agenda_link = $mbAgLink ?: null;
            $this->_view->debug_mb_80 = $b80['debug'] ?? [];

            // 81: WhatsApp (boton)
            $id81 = $this->cmsResolveId($PESTANA, 'WhatsApp', 'boton', null);
            $b81 = CmsRepository::loadBlockByIdTypes($id81, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->mb_whatsapp = $b81['byType'] ?? [];
            try { $this->_view->mb_whatsapp_link = CmsRepository::getFirstLinkForType($id81, 'link', 1); } catch (\Throwable $e) { $this->_view->mb_whatsapp_link = null; }
            $this->_view->debug_mb_81 = $b81['debug'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('marcasBlancas'));
    }

    public function distribuciones()
    {
        $this->loadFooterEmpresas();
        // Cargar CMS de Empresas > Distribuciones (bloques 82-91)
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Empresas > Distribuciones
            $PESTANA = $this->resolveCmsTabId('empresas', 'Distribuciones');
            // 82: Banner principal (banner)
            $id82 = $this->cmsResolveId($PESTANA, 'Banner principal', 'banner', null);
            $b82 = CmsRepository::loadElementsByBlockId($id82, 'imagen', 1);
            $img82 = $b82['items'][0] ?? null;
            $this->_view->dist_banner1 = is_array($img82) ? $img82 : ($img82 ? ['imagen' => $img82] : null);
            // Resolver link real del banner 82
            $b82link = CmsRepository::getFirstLinkForType($id82, 'imagen', 1);
            if (!$b82link) {
                $linkType82 = CmsRepository::loadElementsByBlockId($id82, 'link', 1);
                $b82link = $linkType82['items'][0] ?? null;
            }
            if (!$b82link) { $b82link = CmsRepository::getFirstLinkForType($id82, 'boton', 1); }
            if (!$b82link) { $b82link = CmsRepository::getFirstLinkForType($id82, 'titulo', 1); }
            $this->_view->dist_banner1_link = $b82link ?: null;
            $this->_view->debug_dist_82 = $b82['debug'] ?? [];

            // 83: Texto introductorio (parrafo)
            $id83 = $this->cmsResolveId($PESTANA, 'Texto introductorio', 'parrafo', null);
            $b83 = CmsRepository::loadBlockByIdTypes($id83, ['texto','titulo','subtitulo'], 1);
            $this->_view->dist_intro = $b83['byType'] ?? [];
            $this->_view->debug_dist_83 = $b83['debug'] ?? [];

            // 84: ¿Por qué Likephone es fácil de vender? (texto_simple)
            $id84 = $this->cmsResolveId($PESTANA, '¿Por qué Likephone es fácil de vender?', 'texto_simple', null);
            $b84 = CmsRepository::loadBlockByIdTypes($id84, ['titulo','subtitulo','texto'], 1);
            $this->_view->dist_facil = $b84['byType'] ?? [];
            $this->_view->debug_dist_84 = $b84['debug'] ?? [];

            // 85: Contáctanos ahora (boton)
            $id85 = $this->cmsResolveId($PESTANA, 'Contáctanos ahora', 'boton', null);
            $b85 = CmsRepository::loadBlockByIdTypes($id85, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->dist_contacto = $b85['byType'] ?? [];
            try { $this->_view->dist_contacto_link = CmsRepository::getFirstLinkForType($id85, 'link', 1); } catch (\Throwable $e) { $this->_view->dist_contacto_link = null; }
            $this->_view->debug_dist_85 = $b85['debug'] ?? [];

            // 86: Venta de Likephone (parrafo)
            $id86 = $this->cmsResolveId($PESTANA, 'Venta de Likephone', 'parrafo', null);
            $b86 = CmsRepository::loadBlockByIdTypes($id86, ['texto','titulo','subtitulo'], 1);
            $this->_view->dist_venta = $b86['byType'] ?? [];
            $this->_view->debug_dist_86 = $b86['debug'] ?? [];

            // 87: Banner intermedio (banner)
            $id87 = $this->cmsResolveId($PESTANA, 'Banner intermedio', 'banner', null);
            $b87 = CmsRepository::loadElementsByBlockId($id87, 'imagen', 1);
            $img87 = $b87['items'][0] ?? null;
            $this->_view->dist_banner2 = is_array($img87) ? $img87 : ($img87 ? ['imagen' => $img87] : null);
            // Resolver link real del banner 87
            $b87link = CmsRepository::getFirstLinkForType($id87, 'imagen', 1);
            if (!$b87link) {
                $linkType87 = CmsRepository::loadElementsByBlockId($id87, 'link', 1);
                $b87link = $linkType87['items'][0] ?? null;
            }
            if (!$b87link) { $b87link = CmsRepository::getFirstLinkForType($id87, 'boton', 1); }
            if (!$b87link) { $b87link = CmsRepository::getFirstLinkForType($id87, 'titulo', 1); }
            $this->_view->dist_banner2_link = $b87link ?: null;
            $this->_view->debug_dist_87 = $b87['debug'] ?? [];

            // 88: CRM (imagen_texto_sin_boton) + lista_item
            $id88 = $this->cmsResolveId($PESTANA, 'CRM', 'imagen_texto_sin_boton', null);
            $b88 = CmsRepository::loadBlockByIdTypes($id88, ['titulo','texto','imagen','lista_item'], 1);
            $this->_view->dist_crm = $b88['byType'] ?? [];
            $this->_view->debug_dist_88 = $b88['debug'] ?? [];

            // 89: App Whitephone (imagen_texto_sin_boton) + lista_item
            $id89 = $this->cmsResolveId($PESTANA, 'App Whitephone', 'imagen_texto_sin_boton', null);
            $b89 = CmsRepository::loadBlockByIdTypes($id89, ['titulo','texto','imagen','lista_item'], 1);
            $this->_view->dist_app_white = $b89['byType'] ?? [];
            $this->_view->debug_dist_89 = $b89['debug'] ?? [];

            // 90: Agendar (boton_personalizado)
            $id90 = $this->cmsResolveId($PESTANA, 'Agendar', 'boton_personalizado', null);
            $b90 = CmsRepository::loadBlockByIdTypes($id90, ['boton','link','titulo','texto'], 1);
            $this->_view->dist_agenda = $b90['byType'] ?? [];
            // Resolver link real priorizando botón
            try { $ag90 = CmsRepository::getFirstLinkForType($id90, 'boton', 1); } catch (\Throwable $e) { $ag90 = null; }
            if (!$ag90) {
                $ag90Arr = $this->_view->dist_agenda['link'][0] ?? null;
                $ag90 = $ag90Arr ?: null;
            }
            if (!$ag90) { $ag90 = CmsRepository::getFirstLinkForType($id90, 'titulo', 1); }
            $this->_view->dist_agenda_link = $ag90 ?: null;
            $this->_view->debug_dist_90 = $b90['debug'] ?? [];

            // 91: WhatsApp (boton)
            $id91 = $this->cmsResolveId($PESTANA, 'WhatsApp', 'boton', null);
            $b91 = CmsRepository::loadBlockByIdTypes($id91, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->dist_whatsapp = $b91['byType'] ?? [];
            try { $this->_view->dist_whatsapp_link = CmsRepository::getFirstLinkForType($id91, 'link', 1); } catch (\Throwable $e) { $this->_view->dist_whatsapp_link = null; }
            $this->_view->debug_dist_91 = $b91['debug'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('distribuciones'));
    }

    public function iot()
    {
        // Footer Empresas dinámico también en IoT
        $this->loadFooterEmpresas();

        // Cargar CMS de Empresas > IoT (bloques 92,93,94,96,97,98,99)
        try {
            try { $this->loadModel('CmsRepository'); } catch (\Throwable $e) {}
            if (!class_exists('CmsRepository')) {
                $repoPath = ROOT . 'models' . DS . 'CmsRepository.php';
                if (is_readable($repoPath)) { require_once $repoPath; }
            }
            // Empresas > IOT
            $PESTANA = $this->resolveCmsTabId('empresas', 'IOT');
            // 92: Banner principal
            $id92 = $this->cmsResolveId($PESTANA, 'Banner principal', 'banner', null);
            $b92 = CmsRepository::loadElementsByBlockId($id92, 'imagen', 1);
            $img92 = $b92['items'][0] ?? null;
            $this->_view->iot_banner1 = is_array($img92) ? $img92 : ($img92 ? ['imagen' => $img92] : null);
            // Resolver link real del banner 92
            $b92link = CmsRepository::getFirstLinkForType($id92, 'imagen', 1);
            if (!$b92link) {
                $linkType92 = CmsRepository::loadElementsByBlockId($id92, 'link', 1);
                $b92link = $linkType92['items'][0] ?? null;
            }
            if (!$b92link) { $b92link = CmsRepository::getFirstLinkForType($id92, 'boton', 1); }
            if (!$b92link) { $b92link = CmsRepository::getFirstLinkForType($id92, 'titulo', 1); }
            $this->_view->iot_banner1_link = $b92link ?: null;
            $this->_view->debug_iot_92 = $b92['debug'] ?? [];

            // 93: Mayor control, menor costo (imagen_texto_sin_boton) + lista_item
            $id93 = $this->cmsResolveId($PESTANA, 'Mayor control', 'imagen_texto_sin_boton', null);
            $b93 = CmsRepository::loadBlockByIdTypes($id93, ['titulo','subtitulo','texto','imagen','lista_item'], 1);
            $this->_view->iot_mayor = $b93['byType'] ?? [];
            $this->_view->debug_iot_93 = $b93['debug'] ?? [];

            // 94: Agenda tu demo (boton_personalizado)
            $id94 = $this->cmsResolveId($PESTANA, 'Agenda tu demo', 'boton_personalizado', null);
            $b94 = CmsRepository::loadBlockByIdTypes($id94, ['boton','link','titulo','texto'], 1);
            $this->_view->iot_agenda = $b94['byType'] ?? [];
            // Resolver link real priorizando botón
            try { $iotAg = CmsRepository::getFirstLinkForType($id94, 'boton', 1); } catch (\Throwable $e) { $iotAg = null; }
            if (!$iotAg) { $iotAg = $this->_view->iot_agenda['link'][0] ?? null; }
            if (!$iotAg) { $iotAg = CmsRepository::getFirstLinkForType($id94, 'titulo', 1); }
            $this->_view->iot_agenda_link = $iotAg ?: null;
            $this->_view->debug_iot_94 = $b94['debug'] ?? [];

            // 96: AVL Tracking (head + tarjetas)
            $id96 = $this->cmsResolveId($PESTANA, 'AVL Tracking', null, null);
            $b96 = CmsRepository::loadZippedItemsById($id96, ['titulo','subtitulo','texto','imagen'], 1);
            $cards96 = $b96['items'] ?? [];
            // Fallback: cuando las tarjetas vienen como filas JSON en 'contenido'
            {
                try {
                    $rows = Capsule::connection('cms')
                        ->table('elementos_bloques')
                        ->select(['contenido','tipo'])
                        ->where('id_bloque', $id96)
                        ->where(function($q){ $q->where('visible', 1)->orWhereNull('visible'); })
                        ->orderBy('id_elemento')
                        ->get();
                    $cards = [];
                    foreach ($rows as $r) {
                        $raw = (string)($r->contenido ?? '');
                        $data = json_decode($raw, true);
                        if (is_array($data)) {
                            // normalizar claves básicas
                            $t = isset($data['titulo']) ? (string)$data['titulo'] : (isset($data['title']) ? (string)$data['title'] : '');
                            $sub = isset($data['subtitulo']) ? (string)$data['subtitulo'] : (isset($data['subtitle']) ? (string)$data['subtitle'] : '');

                            // "texto" puede venir como string o como arreglo de bullets
                            $txSource = $data['texto'] ?? ($data['text'] ?? '');
                            if (is_array($txSource)) {
                                $lines = [];
                                foreach ($txSource as $ln) {
                                    $ln = trim((string)$ln);
                                    if ($ln === '') { continue; }
                                    $lines[] = $ln;
                                }
                                $tx = implode("\n", $lines);
                            } else {
                                $tx = (string)$txSource;
                            }

                            if ($t !== '' || $tx !== '' || $sub !== '') {
                                $cards[] = ['titulo' => $t, 'texto' => $tx, 'subtitulo' => $sub];
                            }
                        }
                    }
                    if (!empty($cards)) { $cards96 = $cards; }
                } catch (\Throwable $e) {}
            }
            $this->_view->iot_avl_cards = $cards96;
            $this->_view->debug_iot_96 = $b96['debug'] ?? [];
            // Heading (titulo/subtitulo/texto) del mismo bloque
            $b96h = CmsRepository::loadBlockByIdTypes($id96, ['titulo','subtitulo','texto'], 1);
            $this->_view->iot_avl_head = $b96h['byType'] ?? [];

            // 97: Tarifas IoT (banner)
            $id97 = $this->cmsResolveId($PESTANA, 'Tarifas IoT', 'banner', null);
            $b97 = CmsRepository::loadElementsByBlockId($id97, 'imagen', 1);
            $img97 = $b97['items'][0] ?? null;
            $this->_view->iot_tarifas = is_array($img97) ? $img97 : ($img97 ? ['imagen' => $img97] : null);
            // Resolver link real del banner 97
            $b97link = CmsRepository::getFirstLinkForType($id97, 'imagen', 1);
            if (!$b97link) {
                $linkType97 = CmsRepository::loadElementsByBlockId($id97, 'link', 1);
                $b97link = $linkType97['items'][0] ?? null;
            }
            if (!$b97link) { $b97link = CmsRepository::getFirstLinkForType($id97, 'boton', 1); }
            if (!$b97link) { $b97link = CmsRepository::getFirstLinkForType($id97, 'titulo', 1); }
            $this->_view->iot_tarifas_link = $b97link ?: null;
            $this->_view->debug_iot_97 = $b97['debug'] ?? [];

            // 98: Costos (parrafo)
            $id98 = $this->cmsResolveId($PESTANA, 'Costos', 'parrafo', null);
            $b98 = CmsRepository::loadBlockByIdTypes($id98, ['texto','titulo','subtitulo'], 1);
            $this->_view->iot_costos = $b98['byType'] ?? [];
            $this->_view->debug_iot_98 = $b98['debug'] ?? [];

            // 99: WhatsApp (boton)
            $id99 = $this->cmsResolveId($PESTANA, 'WhatsApp', 'boton', null);
            $b99 = CmsRepository::loadBlockByIdTypes($id99, ['boton','link','telefono','wa','texto'], 1);
            $this->_view->iot_whatsapp = $b99['byType'] ?? [];
            try { $this->_view->iot_whatsapp_link = CmsRepository::getFirstLinkForType($id99, 'link', 1); } catch (\Throwable $e) { $this->_view->iot_whatsapp_link = null; }
            $this->_view->debug_iot_99 = $b99['debug'] ?? [];
        } catch (\Throwable $e) {}

        $this->_view->renderizar(array('iot'));
    }

    public function cotizar()
    {
        $this->loadFooterEmpresas();

        $this->_view->renderizar(array('cotizar'));
    }
}
