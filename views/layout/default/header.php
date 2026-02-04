<?php if (Session::get('autenticado')) : ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <?php
          $uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
          $uriPathLower = parse_url($uri, PHP_URL_PATH);
          $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/');
          // Compute relative path: remove basePath prefix
          $relPath = preg_replace('#^'.preg_quote($basePath, '#').'#', '', $uriPathLower);
          if ($relPath === '') { $relPath = '/'; }
          $tabTarget = 'menu-personas';
          if (preg_match('#^/empresas($|/)#', $relPath)) { $tabTarget = 'menu-empresas'; }
          elseif (preg_match('#^/likecheck($|/)#', $relPath)) { $tabTarget = 'menu-likecheck'; }

          // Base: usar variables del controlador si existen (footer_*), luego CMS
          $H_social = isset($this->footer_social) && is_array($this->footer_social) ? $this->footer_social : [];
          $H_tel    = isset($this->footer_tel)   ? (string)$this->footer_tel : '';
          $H_mail   = isset($this->footer_mail)  ? (string)$this->footer_mail : '';
          $cmsPageId = isset($this->cms_page_id) ? (int)$this->cms_page_id : (defined('CMS_PAGE_ID') ? (int)CMS_PAGE_ID : 1);
          // Cargar redes/teléfono/correo desde el footer CMS por sección si faltan
          try {
            if (!class_exists('CmsRepository')) { require_once ROOT.'models'.DS.'CmsRepository.php'; }
            // id_seccion dinámico según la página activa (secciones_cms)
            $sec = 1;
            if ($tabTarget === 'menu-empresas' && isset($this->secIdEmpresas)) {
              $sec = (int)$this->secIdEmpresas;
            } elseif ($tabTarget === 'menu-likecheck' && isset($this->secIdLike)) {
              $sec = (int)$this->secIdLike;
            } elseif (isset($this->secIdPersonas)) {
              $sec = (int)$this->secIdPersonas;
            }
            $tipo = 'footer';
            if (empty($H_social) || $H_tel==='' || $H_mail==='') {
              $footerRes = CmsRepository::loadBlockByCoordsTypes([
              'id_pagina'=>$cmsPageId,'id_seccion'=>$sec,'tipo_bloque'=>$tipo,'visible'=>1
              ], ['social','telefono','correo','texto'], 1);
              $bid = isset($footerRes['block']->id_bloque) ? (int)$footerRes['block']->id_bloque : 0;
            if ($bid<=0 && $sec===2) {
              $alt = CmsRepository::loadBlockByCoordsTypes(['id_pagina'=>$cmsPageId,'id_seccion'=>2,'tipo_bloque'=>'footer_e','visible'=>1], ['social','telefono','correo','texto'], 1);
              $bid = isset($alt['block']->id_bloque) ? (int)$alt['block']->id_bloque : 0;
            }
            if ($bid<=0 && $sec===3) {
              $alt = CmsRepository::loadBlockByCoordsTypes(['id_pagina'=>$cmsPageId,'id_seccion'=>3,'tipo_bloque'=>'footer_l','visible'=>1], ['social','telefono','correo','texto'], 1);
              $bid = isset($alt['block']->id_bloque) ? (int)$alt['block']->id_bloque : 0;
            }
            if ($bid>0) {
              $rows = \Illuminate\Database\Capsule\Manager::connection('cms')
                ->table('elementos_bloques')
                ->select(['tipo','contenido','link','visible'])
                ->where('id_bloque',$bid)
                ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                ->orderBy('id_elemento')
                ->get()->toArray();
              foreach ($rows as $r) {
                $t = strtolower(trim((string)($r->tipo ?? '')));
                $c = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                $l = is_string($r->link ?? null) ? trim($r->link) : '';
                if ($t==='social' && $c!=='' && $l!=='') { if (empty($H_social[strtolower($c)])) $H_social[strtolower($c)] = $l; }
                if ($t==='telefono' && $c!=='') { if ($H_tel==='')  $H_tel  = $c; }
                if ($t==='correo'   && $c!=='') { if ($H_mail==='') $H_mail = $c; }
                // Algunas instalaciones usan social/correo/telefono en 'social'
                if ($t==='social') {
                  if (empty($H_tel) && strtolower($c)==='telefono' && $l!=='') { $H_tel = $l; }
                  if (empty($H_mail) && in_array(strtolower($c),['correo','email','mail']) && ($l!==''||$c!=='')) { $H_mail = $l!==''?$l:$c; }
                }
                // Derivar teléfono de texto libre si contiene dígitos
                if ($t==='texto' && empty($H_tel)) {
                  if (preg_match('/\+?\d[\d\s\.-]{6,}/', $c)) { $H_tel = $c; }
                }
              }
            }
            // Para Empresas, si aún falta algo, usar datos de Personas
            if ($sec===2) {
              $secPersonas = isset($this->secIdPersonas) ? (int)$this->secIdPersonas : 1;
              $p = CmsRepository::loadBlockByCoordsTypes(['id_pagina'=>$cmsPageId,'id_seccion'=>$secPersonas,'tipo_bloque'=>'footer','visible'=>1], ['social','telefono','correo','texto'], 1);
              $pbid = isset($p['block']->id_bloque) ? (int)$p['block']->id_bloque : 0;
              if ($pbid>0) {
                $rows = \Illuminate\Database\Capsule\Manager::connection('cms')
                  ->table('elementos_bloques')
                  ->select(['tipo','contenido','link'])
                  ->where('id_bloque',$pbid)
                  ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                  ->orderBy('id_elemento')
                  ->get()->toArray();
                foreach ($rows as $r) {
                  $t = strtolower(trim((string)($r->tipo ?? '')));
                  $c = is_string($r->contenido ?? null) ? trim($r->contenido) : '';
                  $l = is_string($r->link ?? null) ? trim($r->link) : '';
                  if ($t==='social' && $c!=='' && $l!=='') { if (empty($H_social[strtolower($c)])) $H_social[strtolower($c)]=$l; }
                  if ($t==='telefono' && $c!=='') { if ($H_tel==='')  $H_tel  = $c; }
                  if ($t==='correo' && $c!=='')   { if ($H_mail==='') $H_mail = $c; }
                  if ($t==='social') {
                    if ($H_tel==='' && strtolower($c)==='telefono' && $l!=='') { $H_tel = $l; }
                    if ($H_mail==='' && in_array(strtolower($c), ['correo','email','mail']) && ($l!==''||$c!=='')) { $H_mail = $l!=='' ? $l : $c; }
                  }
                }
              }
            }
            }
            // Normalizar redes (https + handles tiktok/youtube)
            if (!empty($H_social)) {
              $norm = [];
              foreach ($H_social as $k=>$u) {
                $key = strtolower(trim((string)$k));
                $val = trim((string)$u);
                if ($val==='') continue;
                $lower = strtolower($val);
                if ($key==='tiktok') {
                  if ($lower!=='' && $lower[0]=='@') { $val = 'https://www.tiktok.com/'.$val; }
                  elseif (strpos($lower,'tiktok.com')===false && stripos($val,'http')!==0) { $val = 'https://www.tiktok.com/@'.ltrim($val,'@/'); }
                } elseif ($key==='youtube') {
                  if ($lower!=='' && $lower[0]=='@') { $val = 'https://www.youtube.com/'.$val; }
                  elseif (strpos($lower,'youtube.com')===false && strpos($lower,'youtu.be')===false && stripos($val,'http')!==0) { $val = 'https://www.youtube.com/@'.ltrim($val,'@/'); }
                }
                if (stripos($val,'http://')!==0 && stripos($val,'https://')!==0) { $val = 'https://'.$val; }
                $norm[$key] = $val;
              }
              $H_social = $norm;
            }
          } catch (\Throwable $e) {}
        ?>
        <meta charset="utf-8">
        <title><?php echo APP_NAME; ?></title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="Free HTML Templates" name="keywords">
        <meta content="Free HTML Templates" name="description">

        <!-- Favicon -->
        <link href="<?php echo BASE_URL; ?>img/favicon.ico" rel="icon">

        <!-- Google Web Fonts -->
        <link rel="preconnect" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css2?family=Handlee&family=Nunito&display=swap" rel="stylesheet">

        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

        <link href="<?php echo BASE_URL; ?>resources/assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
        <link href="<?php echo BASE_URL; ?>resources/assets/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

        <!-- Customized Bootstrap Stylesheet -->
        <style>
          <?php include ROOT . 'views' . DS . 'layout' . DS . DEFAULT_LAYOUT . DS . 'css' . DS . 'style.css'; ?>
          /* Force global page background to white below the gray top tabs */
          html, body { background:#ffffff !important; }
          /* Collapse fallback (no Bootstrap JS) */
          .collapse { display: none; }
          .collapse.show { display: block; }
          .personas-main-content,
          .empresas-content,
          .likecheck-main-content,
          .container,
          .container-fluid { background: transparent; }
          /* Active state for submenu items */
          .navbar-nav .nav-link.active,
          .navbar-nav .dropdown-item.active {
            color: #00bcd4 !important;
            font-weight: 700;
          }
          /* Text-only highlight for active dropdown items */
          .dropdown-item.active,
          .dropdown-item:active {
            background-color: transparent !important;
            color: #00bcd4 !important;
          }
          /* Navbar height fixed and items centered */
          .navbar { min-height: 64px; padding-top: 8px; padding-bottom: 8px; background:#ffffff !important; border:1px solid #e5e7eb; box-shadow: 0 6px 20px rgba(0,0,0,.06); }
          .navbar .navbar-nav { align-items: center; }
          /* Menú: forzar una sola línea para mantener altura uniforme */
          .navbar-nav .nav-link { white-space: nowrap; text-align: center; line-height: 1; word-break: keep-all; }
          .menu-area .nav-link { padding: 0 0.75rem; font-size: 1rem; }
          .menu-area .navbar-nav { flex-wrap: nowrap; }
          /* Prevent long submenu from breaking layout: keep on one line and allow horizontal scroll when needed */
          .menu-area .navbar-nav { flex-wrap: nowrap; }
          @media (max-width: 992px) {
            .menu-area .navbar-nav { overflow-x: auto; -webkit-overflow-scrolling: touch; }
          }
          /* Make all submenus slightly compact on medium screens to avoid wrapping */
          @media (max-width: 1200px) {
            .menu-area .nav-link { padding: 0 0.65rem; font-size: 0.95rem; }
          }
        </style>
        <style>
          /* Dropdown on hover and subtle styling embedded inline as requested */
          .nav-item.dropdown:hover > .dropdown-menu,
          .nav-item.dropdown:focus-within > .dropdown-menu {

            display: block;
          }
          .navbar-light .navbar-nav .nav-link {
            font-family: 'Nunito', sans-serif;
          }
          .dropdown-menu {
            margin-top: 0;
            border-radius: 4px;
            box-shadow: 0 6px 20px rgba(0,0,0,.15);
          }
          .dropdown-item {
            font-family: 'Nunito', sans-serif;
          }
          /* Top tabs redesigned (gray buttons, icons, separators, social + contact) */
          .top-tabs { background:#f3f4f6; color:#374151; box-shadow:0 1px 4px rgba(0,0,0,.06); }
          .top-tabs .tabs-container { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; }
          .top-tabs .tabs-left { display:flex; align-items:center; gap:10px; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; }
          .top-tabs .tab-btn { display:inline-flex; align-items:center; gap:8px; color:#374151; background:transparent !important; border:0 !important; border-radius:6px; padding:6px 10px; font-weight:700; text-transform:none; letter-spacing:.2px; white-space:nowrap; transition:color .2s ease; }
          .top-tabs .tabs-left .tab-btn:not(.active) { background:transparent !important; border:0 !important; }
          .top-tabs .tab-btn i { color:#6b7280; }
          .top-tabs .tab-btn:hover { color:#111827; text-decoration:none; }
          .top-tabs .tab-btn.active { color:#00c7d9; background:transparent !important; border:0 !important; }
          .top-tabs .tab-btn.active i { color:#00c7d9; }
          .top-tabs .divider { width:2px; height:24px; background:#d1d5db; display:inline-block; border-radius:2px; }
          .top-tabs .tabs-right { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
          .top-tabs .social a { color:#00c7d9 !important; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:#e5e7eb; border:1px solid #d1d5db; transition:all .2s ease; text-decoration:none; }
          .top-tabs .social a i { color:#00c7d9 !important; }
          .top-tabs .social a:hover { color:#00aebf !important; text-decoration:none; }
          .top-tabs .social a:hover i { color:#00aebf !important; }
          .top-tabs .contact a { color:#00c7d9 !important; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; text-decoration:none; font-size:0; background:#e5e7eb; border:1px solid #d1d5db; }
          .top-tabs .contact a i { font-size:16px; color:#00c7d9 !important; }
          /* Normalizar orientación de iconos de contacto */
          .top-tabs .contact a i { transform: none !important; -webkit-transform: none !important; }
          .top-tabs .contact a:hover i { color:#00aebf !important; }
          /* Skeleton circles */
          .top-tabs .skel { display:inline-block; width:28px; height:28px; border-radius:50%; background:#e5e7eb; border:1px solid #d1d5db; }
          @media (max-width: 992px) {
            .top-tabs .tabs-container { flex-direction: column; align-items: stretch; gap:8px; }
            .top-tabs .tabs-right { order:1; display:flex; justify-content:flex-end; gap:10px; }
            .top-tabs .tabs-left { order:2; justify-content:flex-start; overflow-x:auto; }
            .top-tabs .contact { display:none; }
            .top-tabs .tab-btn { padding:4px 8px; font-size:13px; }
          }
          /* Toggle areas */
          .menu-area { display: none; }
          .menu-area.active { display: block; }
          /* Full-width content below menu (keep menu width intact) */
          .personas-main-content,
          .empresas-content,
          .likecheck-main-content { width:100%; max-width:100%; margin:0; }
          .personas-main-content > .container,
          .personas-main-content > .container-lg,
          .personas-main-content > .container-fluid,
          .empresas-content .empresas-container,
          .likecheck-main-content .likecheck-container { width:100%; max-width:100%; padding-left:0; padding-right:0; margin:0; }
          /* Remove gap between menu and content */
          .nav-bar { margin-bottom: 0; }
          /* Consistent logo box: force same height across brands */
          .navbar-brand { padding-top: 0; padding-bottom: 0; }
          .brand-logo-box { width:190px; height:48px; position:relative; display:flex; align-items:center; justify-content:flex-start; overflow:hidden; }
          /* Skeleton placeholder when there is no logo image (soft, like other skeletons) */
          .brand-logo-skeleton {
            width:100%;
            height:100%;
            border-radius:6px;
            background:#e5e7eb;
          }
          /* Universal clamp for any logo image inside navbar brand */
          .navbar .navbar-brand img { height:38px !important; width:auto !important; max-height:38px !important; max-width:140px !important; object-fit:contain !important; display:block !important; }
          .navbar .navbar-brand .brand-logo-img, .navbar-brand .brand-logo-img {
            position:absolute;
            left:8px;
            top:50%;
            transform:translateY(-50%);
            height:38px !important;
            max-width:140px !important;
          }
        </style>
        <script>
          document.addEventListener('DOMContentLoaded', function () {
            var BASE_PATH = <?php echo json_encode(rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/')); ?>;
            function relativePath(path){
              try{
                var p = (path || window.location.pathname).toLowerCase();
                var rx = new RegExp('^' + BASE_PATH.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
                var rel = p.replace(rx, '');
                if (!rel.startsWith('/')) rel = '/' + rel;
                return rel;
              }catch(e){ return (window.location.pathname||'').toLowerCase(); }
            }
            var tabBars = document.querySelectorAll('.top-tabs');
            tabBars.forEach(function(bar){
              var tabs = bar.querySelectorAll('.tab-btn');
              tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                  // activar pestaña
                  tabs.forEach(function(t){ t.classList.remove('active'); });
                  tab.classList.add('active');
                  var target = tab.getAttribute('data-target');
                  // Alternar menús (document-wide)
                  var areas = document.querySelectorAll('.menu-area');
                  areas.forEach(function(a){ a.classList.remove('active'); });
                  var show = document.querySelector('#' + target);
                  if (show) show.classList.add('active');
                  // navegación explícita si hay href
                  var href = tab.getAttribute('href');
                  if (href) { window.location.href = href; }
                });
              });
            });
            // Activar pestaña según URL actual
            var path = relativePath(window.location.pathname);
            var targetId = 'menu-personas';
            if (path.indexOf('/empresas') === 0) targetId = 'menu-empresas';
            else if (path.indexOf('/likecheck') === 0) targetId = 'menu-likecheck';
            var targetTab = document.querySelector('.top-tabs .tab-btn[data-target="' + targetId + '"]');
            if (targetTab) {
              targetTab.classList.add('active');
            }
            // Alternar el bloque de menú correspondiente (Personas/Empresas/Likecheck)
            var areas = document.querySelectorAll('.menu-area');
            areas.forEach(function(a){ a.classList.remove('active'); });
            var show = document.querySelector('#' + targetId);
            if (show) show.classList.add('active');

            // Mobile navbar toggler fallback (works even if Bootstrap JS fails)
            try {
              var toggler = document.querySelector('.navbar-toggler');
              var collapse = document.getElementById('navbarCollapse');
              if (toggler && collapse) {
                toggler.addEventListener('click', function (e) {
                  e.preventDefault();
                  var isOpen = collapse.classList.contains('show');
                  collapse.classList.toggle('show', !isOpen);
                  toggler.setAttribute('aria-expanded', (!isOpen).toString());
                });
              }
            } catch (e) {}
          });
        </script>
    </head>
    <body>
        <?php $hideMainNav = !empty($this->hideMainNav); ?>
        <?php if (!$hideMainNav) { ?>
        <!-- Top Tabs Start -->
        <div class="container-fluid top-tabs">
          <div class="container-lg tabs-container">
            <div class="tabs-left">
              <a class="tab-btn<?php echo ($tabTarget==='menu-personas'?' active':''); ?>" href="<?php echo BASE_URL; ?>personas/inicio" data-target="menu-personas" style="background:transparent!important;border:0!important;box-shadow:none!important;border-radius:0!important;padding:6px 10px;"><i class="fas fa-user"></i> Personas</a>
              <span class="divider"></span>
              <a class="tab-btn<?php echo ($tabTarget==='menu-empresas'?' active':''); ?>" href="<?php echo BASE_URL; ?>empresas/inicio" data-target="menu-empresas" style="background:transparent!important;border:0!important;box-shadow:none!important;border-radius:0!important;padding:6px 10px;"><i class="fas fa-building"></i> Empresas</a>
              <span class="divider"></span>
              <a class="tab-btn<?php echo ($tabTarget==='menu-likecheck'?' active':''); ?>" href="<?php echo BASE_URL; ?>likecheck" data-target="menu-likecheck" style="background:transparent!important;border:0!important;box-shadow:none!important;border-radius:0!important;padding:6px 10px;"><i class="fas fa-check-circle"></i> Likecheck</a>
            </div>
            <div class="tabs-right">
              <div class="social" style="display:flex; gap:8px;">
                <?php if (!empty($H_social['facebook'])) { ?>
                  <a href="<?php echo htmlspecialchars($H_social['facebook']); ?>" target="_blank" rel="noopener" aria-label="Facebook" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fab fa-facebook-f" style="color:#00bcd4;"></i></a>
                <?php } ?>
                <?php if (!empty($H_social['instagram'])) { ?>
                  <a href="<?php echo htmlspecialchars($H_social['instagram']); ?>" target="_blank" rel="noopener" aria-label="Instagram" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fab fa-instagram" style="color:#00bcd4;"></i></a>
                <?php } ?>
                <?php if (!empty($H_social['tiktok'])) { ?>
                  <a href="<?php echo htmlspecialchars($H_social['tiktok']); ?>" target="_blank" rel="noopener" aria-label="TikTok" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fab fa-tiktok" style="color:#00bcd4;"></i></a>
                <?php } ?>
                <?php if (!empty($H_social['youtube'])) { ?>
                  <a href="<?php echo htmlspecialchars($H_social['youtube']); ?>" target="_blank" rel="noopener" aria-label="YouTube" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fab fa-youtube" style="color:#00bcd4;"></i></a>
                <?php } ?>
                <?php if (!empty($H_social['whatsapp'])) { ?>
                  <a href="<?php echo htmlspecialchars($H_social['whatsapp']); ?>" target="_blank" rel="noopener" aria-label="WhatsApp" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fab fa-whatsapp" style="color:#00bcd4;"></i></a>
                <?php } ?>
                <?php if (empty($H_social)) { ?>
                  <span class="skel" aria-hidden="true"></span>
                  <span class="skel" aria-hidden="true"></span>
                  <span class="skel" aria-hidden="true"></span>
                  <span class="skel" aria-hidden="true"></span>
                <?php } ?>
              </div>
              <div class="contact" style="display:flex; gap:8px; align-items:center;">
                <?php if (!empty($H_tel)) { $rawTel = trim((string)$H_tel); $num = preg_replace('/\D+/', '', $rawTel); $telHref = $num!=='' ? ('tel:+'.$num) : ('tel:'.preg_replace('/\s+/', '', $rawTel)); if ($telHref!=='tel:' && $telHref!=='') { ?>
                  <a href="<?php echo htmlspecialchars($telHref); ?>" aria-label="Teléfono" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fas fa-phone" style="color:#00bcd4; transform:none !important; -webkit-transform:none !important; rotate:0deg !important; scale:1 !important;"></i></a>
                <?php } } else { ?>
                  <span class="skel" aria-hidden="true"></span>
                <?php } ?>
                <?php if (!empty($H_mail)) { $mailHref = stripos($H_mail,'mailto:')===0 ? $H_mail : ('mailto:'.$H_mail); ?>
                  <a href="<?php echo htmlspecialchars($mailHref); ?>" aria-label="Correo" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#e5e7eb;border:1px solid #d1d5db;"><i class="fas fa-envelope" style="color:#00bcd4;"></i></a>
                <?php } else { ?>
                  <span class="skel" aria-hidden="true"></span>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
        <!-- Top Tabs End -->

        <!-- Navbar Start -->
        <div class="container-fluid position-relative nav-bar p-0">
            <div class="container-lg position-relative p-0 px-lg-3" style="z-index: 9;">
                <nav class="navbar navbar-expand-lg bg-light navbar-light shadow-lg py-3 py-lg-0 pl-3 pl-lg-5">
                    <?php
                      // Logotipo dinámico por página/sección: bloques_cms (tipo_bloque='banner', titulo='Logotipo')
                      $logoImg = null; $logoHref = null;
                      try {
                        if (!class_exists('CmsRepository')) { require_once ROOT.'models'.DS.'CmsRepository.php'; }
                        $cmsPageId = isset($this->cms_page_id) ? (int)$this->cms_page_id : (defined('CMS_PAGE_ID') ? (int)CMS_PAGE_ID : 0);
                        if ($cmsPageId > 0) {
                          // Resolver id_seccion actual usando variables ya expuestas por loadHeaderSections
                          $secId = null;
                          if ($tabTarget === 'menu-empresas' && isset($this->secIdEmpresas)) {
                            $secId = (int)$this->secIdEmpresas;
                          } elseif ($tabTarget === 'menu-likecheck' && isset($this->secIdLike)) {
                            $secId = (int)$this->secIdLike;
                          } elseif (isset($this->secIdPersonas)) {
                            $secId = (int)$this->secIdPersonas;
                          }
                          if ($secId) {
                            // Buscar bloque Logotipo (banner) para esta página/sección
                            $logoBlock = CmsRepository::loadBlockByCoordsTypes([
                              'id_pagina'  => $cmsPageId,
                              'id_seccion' => $secId,
                              'tipo_bloque'=> 'banner',
                              'titulo'     => 'Logotipo',
                              'visible'    => 1,
                            ], ['imagen','link'], 1);
                            $logoBlockId = isset($logoBlock['block']->id_bloque) ? (int)$logoBlock['block']->id_bloque : 0;
                            if ($logoBlockId > 0) {
                              $row = \Illuminate\Database\Capsule\Manager::connection('cms')
                                ->table('elementos_bloques')
                                ->select(['contenido','link'])
                                ->where('id_bloque', $logoBlockId)
                                ->where('tipo', 'imagen')
                                ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                                ->orderBy('id_elemento')
                                ->first();
                              if ($row) { $logoImg = $row->contenido ?? null; $logoHref = $row->link ?? null; }
                            }
                          }
                        }
                      } catch (\Throwable $e) {}
                      // Fallback de href por sección si viene vacío
                      if (!$logoHref) {
                        if ($tabTarget === 'menu-empresas') { $logoHref = BASE_URL . 'empresas/inicio'; }
                        elseif ($tabTarget === 'menu-likecheck') { $logoHref = BASE_URL . 'likecheck'; }
                        else { $logoHref = BASE_URL . 'personas/inicio'; }
                      }
                    ?>
                    <a href="<?php echo htmlspecialchars($logoHref); ?>" class="navbar-brand">
                      <?php if ($logoImg) { ?>
                        <div class="brand-logo-box"><img class="brand-logo-img" src="<?php echo htmlspecialchars($logoImg); ?>" alt="Logo" width="140" height="38"/></div>
                      <?php } else { ?>
                        <div class="brand-logo-box"><div class="brand-logo-skeleton" aria-hidden="true"></div></div>
                      <?php } ?>
                    </a>
                    <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <div class="collapse navbar-collapse px-3" id="navbarCollapse">
                        <!-- Personas Menu -->
                        <div id="menu-personas" class="menu-area w-100<?php echo ($tabTarget==='menu-personas'?' active':''); ?>">
                          <div class="navbar-nav font-weight-bold ml-auto py-0">
                              <a href="<?php echo BASE_URL; ?>personas/inicio" class="nav-item nav-link<?php echo (preg_match('#^/personas/inicio($|/)#', $relPath)?' active':''); ?>">Inicio</a>
                              <div class="nav-item dropdown">
                                  <a href="#" class="nav-link<?php echo (preg_match('#^/(cobertura|compatibilidad|estatusenvio|apn|faq|quienessomos)($|/)#', $relPath)?' active':''); ?>">Consultar</a>
                                  <div class="dropdown-menu border-0 m-0">
                                      <a href="<?php echo BASE_URL; ?>cobertura" class="dropdown-item<?php echo (preg_match('#^/cobertura($|/)#',$relPath)?' active':''); ?>">Cobertura</a>
                                      <a href="<?php echo BASE_URL; ?>compatibilidad" class="dropdown-item<?php echo (preg_match('#^/compatibilidad($|/)#',$relPath)?' active':''); ?>">Compatibilidad</a>
                                      <a href="<?php echo BASE_URL; ?>estatusenvio" class="dropdown-item<?php echo (preg_match('#^/estatusenvio($|/)#',$relPath)?' active':''); ?>">Estatus De Envío</a>
                                      <a href="<?php echo BASE_URL; ?>apn" class="dropdown-item<?php echo (preg_match('#^/apn($|/)#',$relPath)?' active':''); ?>">Configura Tu APN</a>
                                      <a href="<?php echo BASE_URL; ?>faq" class="dropdown-item<?php echo (preg_match('#^/faq($|/)#',$relPath)?' active':''); ?>">Preguntas Frecuentes</a>
                                      <a href="<?php echo BASE_URL; ?>quienessomos" class="dropdown-item<?php echo (preg_match('#^/quienessomos($|/)#',$relPath)?' active':''); ?>">Quiénes Somos</a>
                                  </div>
                              </div>
                              <a href="<?php echo BASE_URL; ?>portabilidad" class="nav-item nav-link<?php echo (preg_match('#^/portabilidad($|/)#', $relPath)?' active':''); ?>">Portabilidad</a>
                              <div class="nav-item dropdown">
                                  <a href="#" class="nav-link<?php echo (preg_match('#^/(recargas|recargar_empresas)($|/)#', $relPath)?' active':''); ?>">Recargar</a>
                                  <div class="dropdown-menu border-0 m-0">
                                      <a href="<?php echo BASE_URL; ?>recargas" class="dropdown-item<?php echo (preg_match('#^/recargas($|/)#', $relPath)?' active':''); ?>">Recargar Likephone</a>
                                      <a href="<?php echo BASE_URL; ?>recargar_empresas" class="dropdown-item<?php echo (preg_match('#^/recargar_empresas($|/)#', $relPath)?' active':''); ?>">Recargar Empresas</a>
                                  </div>
                              </div>
                              <a href="<?php echo BASE_URL; ?>contacto" class="nav-item nav-link<?php echo (preg_match('#^/contacto($|/)#', $relPath)?' active':''); ?>">Contacto</a>
                              <a href="<?php echo BASE_URL; ?>index/cerrar" class="nav-item nav-link" style="color:#e11d48;font-weight:800;">Cerrar Sesion</a>
                          </div>
                        </div>

                        <!-- Empresas Menu -->
                        <div id="menu-empresas" class="menu-area w-100<?php echo ($tabTarget==='menu-empresas'?' active':''); ?>">
                          <div class="navbar-nav font-weight-bold ml-auto py-0">
                              <a href="<?php echo BASE_URL; ?>empresas/inicio" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/inicio')!==false?' active':''); ?>">Inicio</a>
                              <a href="<?php echo BASE_URL; ?>empresas/tarifas" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/tarifas')!==false?' active':''); ?>">Tarifas preferenciales</a>
                              <a href="<?php echo BASE_URL; ?>empresas/marcaRapida" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/marcarapida')!==false?' active':''); ?>">Marcas rápidas</a>
                              <a href="<?php echo BASE_URL; ?>empresas/marcasBlancas" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/marcasblancas')!==false?' active':''); ?>">Marcas Blancas</a>
                              <a href="<?php echo BASE_URL; ?>empresas/distribuciones" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/distribuciones')!==false?' active':''); ?>">Distribuciones</a>
                              <a href="<?php echo BASE_URL; ?>empresas/iot" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/iot')!==false?' active':''); ?>">IOT</a>
                              <a href="<?php echo BASE_URL; ?>empresas/cotizar" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/cotizar')!==false?' active':''); ?>">Cotizar</a>
                          </div>
                        </div>

                        <!-- Likecheck Menu -->
                        <div id="menu-likecheck" class="menu-area w-100<?php echo ($tabTarget==='menu-likecheck'?' active':''); ?>">
                          <div class="navbar-nav font-weight-bold ml-auto py-0">
                              <a href="<?php echo BASE_URL; ?>likecheck" class="nav-item nav-link<?php echo (preg_match('#/likecheck(/inicio)?$#',$uriPathLower)?' active':''); ?>">Inicio</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/funcionalidades" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/funcionalidades')!==false?' active':''); ?>">Funcionalidades</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/app_crm" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/app_crm')!==false?' active':''); ?>">APP &amp; CRM</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/planes" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/planes')!==false?' active':''); ?>">Planes</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/cotizador" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/cotizador')!==false?' active':''); ?>">Cotizador</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/promocion" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/promocion')!==false?' active':''); ?>">Promoción</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/clientes" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/clientes')!==false?' active':''); ?>">Clientes</a>
                              <a href="<?php echo BASE_URL; ?>likecheck/contacto" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/contacto')!==false?' active':''); ?>">Contáctanos</a>
                          </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
        <!-- Navbar End -->
        <?php } // end !hideMainNav ?>

    <?php else : ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <?php
              // Mirror URL parsing used in authenticated header to decide active tab/menu
              $uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
              $uriPathLower = parse_url($uri, PHP_URL_PATH);
              $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?: '', '/');
              $relPath = preg_replace('#^'.preg_quote($basePath, '#').'#', '', $uriPathLower);
              if ($relPath === '') { $relPath = '/'; }

              // Flags de visibilidad enviados por el controlador (fallback: todo visible)
              $showPersonas  = isset($this->showPersonas)  ? (bool)$this->showPersonas  : true;
              $showEmpresas  = isset($this->showEmpresas)  ? (bool)$this->showEmpresas  : true;
              $showLikecheck = isset($this->showLikecheck) ? (bool)$this->showLikecheck : true;
              if (!$showPersonas && !$showEmpresas && !$showLikecheck) {
                $showPersonas = $showEmpresas = $showLikecheck = true;
              }

              $tabTarget = 'menu-personas';
              if (preg_match('#^/empresas($|/)#', $relPath) && $showEmpresas) { $tabTarget = 'menu-empresas'; }
              elseif (preg_match('#^/likecheck($|/)#', $relPath) && $showLikecheck) { $tabTarget = 'menu-likecheck'; }
            ?>
            <meta charset="utf-8">
            <title><?php echo APP_NAME; ?></title>
            <meta content="width=device-width, initial-scale=1.0" name="viewport">
            <meta content="Free HTML Templates" name="keywords">
            <meta content="Free HTML Templates" name="description">

            <!-- Favicon -->
            <link href="<?php echo BASE_URL; ?>img/favicon.ico" rel="icon">

            <!-- Google Web Fonts -->
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Handlee&family=Nunito&display=swap" rel="stylesheet">

            <!-- Font Awesome -->
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

            <!-- Libraries Stylesheet -->
            <link href="<?php echo BASE_URL; ?>resources/assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
            <link href="<?php echo BASE_URL; ?>resources/assets/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

            <!-- Customized Bootstrap Stylesheet -->
            <style>
            <?php include ROOT . 'views' . DS . 'layout' . DS . DEFAULT_LAYOUT . DS . 'css' . DS . 'style.css'; ?>
            </style>
            <style>
              /* Mismos estilos de logo que en el header autenticado */
              .navbar-brand { padding-top: 0; padding-bottom: 0; }
              .brand-logo-box { width:220px; height:48px; position:relative; display:flex; align-items:center; justify-content:flex-start; overflow:hidden; }
              .brand-logo-skeleton {
                width:100%;
                height:100%;
                border-radius:6px;
                background:#e5e7eb;
              }
              /* Skeleton círculos para redes en header sin sesión */
              .top-tabs .skel { display:inline-block; width:28px; height:28px; border-radius:50%; background:#e5e7eb; border:1px solid #d1d5db; }
              .navbar .navbar-brand img { height:38px !important; width:auto !important; max-height:38px !important; max-width:140px !important; object-fit:contain !important; display:block !important; }
              .navbar .navbar-brand .brand-logo-img, .navbar-brand .brand-logo-img {
                position:absolute;
                left:8px;
                top:50%;
                transform:translateY(-50%);
                height:38px !important;
                max-width:140px !important;
              }
            </style>
            <?php
              // Preferir variables del controlador (las mismas del footer)
              $H_social = isset($this->footer_social) && is_array($this->footer_social) ? $this->footer_social : [];
              $H_tel    = isset($this->footer_tel)   ? (string)$this->footer_tel : '';
              $H_mail   = isset($this->footer_mail)  ? (string)$this->footer_mail : '';
              $cmsPageId = isset($this->cms_page_id) ? (int)$this->cms_page_id : (defined('CMS_PAGE_ID') ? (int)CMS_PAGE_ID : 1);
              // Cargar redes/teléfono/correo desde CMS footer (no autenticado) si faltan
              try {
                if (!class_exists('CmsRepository')) { require_once ROOT.'models'.DS.'CmsRepository.php'; }
                $sec = ($tabTarget==='menu-empresas') ? 2 : (($tabTarget==='menu-likecheck') ? 3 : 1);
                $tipo = 'footer';
                $res = (empty($H_social) || $H_tel==='' || $H_mail==='') ? CmsRepository::loadBlockByCoordsTypes([
                  'id_pagina'=>$cmsPageId,'id_seccion'=>$sec,'tipo_bloque'=>$tipo,'visible'=>1
                ], ['social','telefono','correo','texto'], 1) : null;
                $bid = $res ? (isset($res['block']->id_bloque) ? (int)$res['block']->id_bloque : 0) : 0;
                if ($bid<=0 && $sec===2) {
                  $alt = CmsRepository::loadBlockByCoordsTypes(['id_pagina'=>$cmsPageId,'id_seccion'=>2,'tipo_bloque'=>'footer_e','visible'=>1], ['social','telefono','correo','texto'], 1);
                  $bid = isset($alt['block']->id_bloque) ? (int)$alt['block']->id_bloque : 0;
                }
                if ($bid<=0 && $sec===3) {
                  $alt = CmsRepository::loadBlockByCoordsTypes(['id_pagina'=>$cmsPageId,'id_seccion'=>3,'tipo_bloque'=>'footer_l','visible'=>1], ['social','telefono','correo','texto'], 1);
                  $bid = isset($alt['block']->id_bloque) ? (int)$alt['block']->id_bloque : 0;
                }
                if ($bid>0) {
                  $rows = \Illuminate\Database\Capsule\Manager::connection('cms')
                    ->table('elementos_bloques')
                    ->select(['tipo','contenido','link'])
                    ->where('id_bloque',$bid)
                    ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                    ->orderBy('id_elemento')->get()->toArray();
                  foreach ($rows as $r){
                    $t=strtolower(trim((string)($r->tipo??''))); $c=is_string($r->contenido??null)?trim($r->contenido):''; $l=is_string($r->link??null)?trim($r->link):'';
                    if ($t==='social' && $c!=='' && $l!==''){ $H_social[strtolower($c)]=$l; }
                    if ($t==='telefono' && $c!==''){ $H_tel=$c; }
                    if ($t==='correo' && $c!==''){ $H_mail=$c; }
                    if ($t==='social'){
                      if ($H_tel==='' && strtolower($c)==='telefono' && $l!==''){ $H_tel=$l; }
                      if ($H_mail==='' && in_array(strtolower($c),['correo','email','mail']) && ($l!==''||$c!=='')){ $H_mail=$l!==''?$l:$c; }
                    }
                    if ($t==='texto' && $H_tel==='') { if (preg_match('/\+?\d[\d\s\.-]{6,}/',$c)) { $H_tel=$c; } }
                  }
                }
                // Empresas: forzar a usar SIEMPRE lo de Personas
                if ($sec===2){
                  $p = CmsRepository::loadBlockByCoordsTypes(['id_pagina'=>$cmsPageId,'id_seccion'=>1,'tipo_bloque'=>'footer','visible'=>1], ['social','telefono','correo','texto'], 1);
                  $pbid = isset($p['block']->id_bloque)?(int)$p['block']->id_bloque:0;
                  if ($pbid>0){
                    $rows = \Illuminate\Database\Capsule\Manager::connection('cms')
                      ->table('elementos_bloques')->select(['tipo','contenido','link'])
                      ->where('id_bloque',$pbid)->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                      ->orderBy('id_elemento')->get()->toArray();
                    foreach($rows as $r){
                      $t=strtolower(trim((string)($r->tipo??''))); $c=is_string($r->contenido??null)?trim($r->contenido):''; $l=is_string($r->link??null)?trim($r->link):'';
                      if ($t==='social' && $c!=='' && $l!==''){ $H_social[strtolower($c)]=$l; }
                      if ($t==='telefono' && $c!==''){ $H_tel=$c; }
                      if ($t==='correo' && $c!==''){ $H_mail=$c; }
                      if ($t==='social') {
                        if ($H_tel==='' && strtolower($c)==='telefono' && $l!=='') { $H_tel=$l; }
                        if ($H_mail==='' && in_array(strtolower($c), ['correo','email','mail']) && ($l!==''||$c!=='')) { $H_mail = $l!=='' ? $l : $c; }
                      }
                    }
                  }
                }
              } catch (\Throwable $e) {}
            ?>
            <style>
              /* Collapse fallback (no Bootstrap JS) */
              .collapse { display:none; }
              .collapse.show { display:block; }
              /* Dropdown on hover and subtle styling embedded inline as requested */
              .nav-item.dropdown:hover > .dropdown-menu,
              .nav-item.dropdown:focus-within > .dropdown-menu { display:block; }
              /* Navbar height fixed and items centered */
              .navbar { min-height: 64px; padding-top: 8px; padding-bottom: 8px; background:#ffffff !important; border:1px solid #e5e7eb; box-shadow: 0 6px 20px rgba(0,0,0,.06); }
              .navbar .navbar-nav { align-items: center; }
              .navbar-light .navbar-nav .nav-link { font-family:'Nunito', sans-serif; white-space: nowrap; text-align: center; line-height: 1; }
              .menu-area .nav-link { padding: 0 0.75rem; font-size: 1rem; }
              .menu-area .navbar-nav { flex-wrap: nowrap; }
              .dropdown-menu { margin-top:0; border-radius:4px; box-shadow:0 6px 20px rgba(0,0,0,.15); }
              .dropdown-item { font-family:'Nunito', sans-serif; }
              /* Top tabs redesigned (gray bg, turquoise active, icons with circles) */
              .top-tabs { background:#f3f4f6; color:#374151; box-shadow:0 1px 4px rgba(0,0,0,.06); }
              .top-tabs .tabs-container { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; }
              .top-tabs .tabs-left { display:flex; align-items:center; gap:10px; flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; }
              .top-tabs .tab-btn { display:inline-flex; align-items:center; gap:8px; color:#374151; background:transparent; border:0; border-radius:6px; padding:6px 10px; font-weight:700; text-transform:none; letter-spacing:.2px; white-space:nowrap; transition:color .2s ease; }
              .top-tabs .tab-btn i { color:#6b7280; }
              .top-tabs .tab-btn:hover { color:#111827; text-decoration:none; }
              .top-tabs .tab-btn.active { color:#00c7d9; background:transparent; border:0; }
              .top-tabs .tab-btn.active i { color:#00c7d9; }
              .top-tabs .divider { width:2px; height:24px; background:#d1d5db; display:inline-block; border-radius:2px; }
              .top-tabs .tabs-right { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
              .top-tabs .social a { color:#00c7d9; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:#e5e7eb; border:1px solid #d1d5db; transition:all .2s ease; text-decoration:none; }
              .top-tabs .social a i { color:#00c7d9; }
              .top-tabs .social a:hover { text-decoration:none; }
              .top-tabs .social a:hover i { color:#00aebf; }
              .top-tabs .contact a { color:#00c7d9; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; text-decoration:none; font-size:0; background:#e5e7eb; border:1px solid #d1d5db; }
              .top-tabs .contact a i { font-size:16px; color:#00c7d9; }
              /* Normalize phone icon orientation */
              .top-tabs .contact a i { transform:none !important; -webkit-transform:none !important; }
              .top-tabs .contact a i.fa-phone { transform:none !important; -webkit-transform:none !important; rotate:0deg !important; scale:1 !important; }
              .top-tabs .contact a:hover i { color:#00aebf; }
              .menu-area { display:none; }
              .menu-area.active { display:block; }
              /* Full-width content below menu */
              .personas-main-content,
              .empresas-content,
              .likecheck-main-content { width:100%; max-width:100%; margin:0; }
              .personas-main-content > .container,
              .personas-main-content > .container-lg,
              .personas-main-content > .container-fluid,
              .empresas-content .empresas-container,
              .likecheck-main-content .likecheck-container { width:100%; max-width:100%; padding-left:0; padding-right:0; margin:0; }
              .nav-bar { margin-bottom:0; }
              @media (max-width: 992px) {
                .top-tabs .tabs-container { flex-direction: column; align-items: stretch; gap:8px; }
                .top-tabs .tabs-right { order:1; display:flex; justify-content:flex-end; gap:10px; }
                .top-tabs .tabs-left { order:2; justify-content:flex-start; overflow-x:auto; }
                .top-tabs .contact { display:none; }
                .top-tabs .tab-btn { padding:4px 8px; font-size:13px; }
              }
            </style>
            <script>
              document.addEventListener('DOMContentLoaded', function () {
                var tabBars = document.querySelectorAll('.top-tabs');
                tabBars.forEach(function(bar){
                  var tabs = bar.querySelectorAll('.tab-btn');
                  tabs.forEach(function(tab){
                    tab.addEventListener('click', function(){
                      tabs.forEach(function(t){ t.classList.remove('active'); });
                      tab.classList.add('active');
                      var target = tab.getAttribute('data-target');
                      var areas = document.querySelectorAll('.menu-area');
                      areas.forEach(function(a){ a.classList.remove('active'); });
                      var show = document.querySelector('#' + target);
                      if (show) show.classList.add('active');
                      // Toggle login button only for Personas
                      var loginBtn = document.getElementById('loginBtn');
                      if (loginBtn) {
                        loginBtn.style.display = (target === 'menu-personas') ? '' : 'none';
                      }
                      // Navegar solo si el tab es enlace y a ruta diferente
                      var href = tab.getAttribute('href');
                      if (href) {
                        try {
                          var dest = new URL(href, window.location.origin);
                          if (dest.href !== window.location.href) {
                            window.location.href = dest.href;
                          }
                        } catch (e) {}
                      }
                    });
                  });
                });
                // Activar pestaña según URL actual
                var path = (window.location.pathname || '').toLowerCase();
                var targetId = 'menu-personas';
                if (path.indexOf('/empresas') !== -1) targetId = 'menu-empresas';
                else if (path.indexOf('/likecheck') !== -1) targetId = 'menu-likecheck';
                var targetTab = document.querySelector('.top-tabs .tab-btn[data-target="' + targetId + '"]');
                if (targetTab) {
                  targetTab.classList.add('active');
                }
                // Alternar el bloque de menú correspondiente (Personas/Empresas/Likecheck)
                var areas = document.querySelectorAll('.menu-area');
                areas.forEach(function(a){ a.classList.remove('active'); });
                var show = document.querySelector('#' + targetId);
                if (show) show.classList.add('active');

                // Mobile navbar toggler fallback (works even if Bootstrap JS fails)
                try {
                  var toggler = document.querySelector('.navbar-toggler');
                  var collapse = document.getElementById('navbarCollapse');
                  if (toggler && collapse) {
                    toggler.addEventListener('click', function (e) {
                      e.preventDefault();
                      var isOpen = collapse.classList.contains('show');
                      collapse.classList.toggle('show', !isOpen);
                      toggler.setAttribute('aria-expanded', (!isOpen).toString());
                    });
                  }
                } catch (e) {}
              });
            </script>
        </head>

        <body>
            <!-- Top Tabs Start -->
            <div class="container-fluid top-tabs">
              <div class="container-lg tabs-container">
                <div class="tabs-left">
                  <?php if ($showPersonas) { ?>
                    <a class="tab-btn<?php echo ($tabTarget==='menu-personas'?' active':''); ?>" href="<?php echo BASE_URL; ?>personas/inicio" data-target="menu-personas"><i class="fas fa-user"></i> Personas</a>
                  <?php } ?>
                  <?php if ($showPersonas && ($showEmpresas || $showLikecheck)) { ?>
                    <span class="divider"></span>
                  <?php } ?>
                  <?php if ($showEmpresas) { ?>
                    <a class="tab-btn<?php echo ($tabTarget==='menu-empresas'?' active':''); ?>" href="<?php echo BASE_URL; ?>empresas/inicio" data-target="menu-empresas"><i class="fas fa-building"></i> Empresas</a>
                  <?php } ?>
                  <?php if ($showEmpresas && $showLikecheck) { ?>
                    <span class="divider"></span>
                  <?php } ?>
                  <?php if ($showLikecheck) { ?>
                    <a class="tab-btn<?php echo ($tabTarget==='menu-likecheck'?' active':''); ?>" href="<?php echo BASE_URL; ?>likecheck" data-target="menu-likecheck"><i class="fas fa-check-circle"></i> Likecheck</a>
                  <?php } ?>
                </div>
                <div class="tabs-right">
                  <div class="social" style="display:flex; gap:8px;">
                    <?php if (!empty($H_social['facebook'])) { ?>
                      <a href="<?php echo htmlspecialchars($H_social['facebook']); ?>" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <?php } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                    <?php if (!empty($H_social['instagram'])) { ?>
                      <a href="<?php echo htmlspecialchars($H_social['instagram']); ?>" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <?php } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                    <?php if (!empty($H_social['tiktok'])) { ?>
                      <a href="<?php echo htmlspecialchars($H_social['tiktok']); ?>" target="_blank" rel="noopener" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                    <?php } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                    <?php if (!empty($H_social['youtube'])) { ?>
                      <a href="<?php echo htmlspecialchars($H_social['youtube']); ?>" target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <?php } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                    <?php if (!empty($H_social['whatsapp'])) { ?>
                      <a href="<?php echo htmlspecialchars($H_social['whatsapp']); ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <?php } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                  </div>
                  <div class="contact" style="display:flex; gap:8px; align-items:center;">
                    <?php if (!empty($H_tel)) { $num = preg_replace('/\D+/', '', $H_tel); $telHref = $num!=='' ? ('tel:+'.$num) : ''; if ($telHref!=='') { ?>
                      <a href="<?php echo htmlspecialchars($telHref); ?>" aria-label="Teléfono"><i class="fas fa-phone" style="color:#00c7d9; transform:none !important; -webkit-transform:none !important; rotate:0deg !important; scale:1 !important;"></i></a>
                    <?php } } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                    <?php if (!empty($H_mail)) { $mailHref = stripos($H_mail,'mailto:')===0 ? $H_mail : ('mailto:'.$H_mail); ?>
                      <a href="<?php echo htmlspecialchars($mailHref); ?>" aria-label="Correo"><i class="fas fa-envelope" style="color:#00c7d9;"></i></a>
                    <?php } else { ?>
                      <span class="skel" aria-hidden="true"></span>
                    <?php } ?>
                  </div>
                </div>
              </div>
            </div>
            <!-- Top Tabs End -->

            <!-- Navbar Start -->
            <div class="container-fluid position-relative nav-bar p-0">
                <div class="container-lg position-relative p-0 px-lg-3" style="z-index: 9;">
                    <nav class="navbar navbar-expand-lg bg-light navbar-light shadow-lg py-3 py-lg-0 pl-3 pl-lg-5">
                        <?php
                          // Logotipo dinámico por página/sección (no autenticado): tipo_bloque='banner', titulo='Logotipo'
                          $logoImg = null; $logoHref = null;
                          try {
                            if (!class_exists('CmsRepository')) { require_once ROOT.'models'.DS.'CmsRepository.php'; }
                            $cmsPageId = defined('CMS_PAGE_ID') ? (int)CMS_PAGE_ID : 0;
                            if ($cmsPageId > 0) {
                              $secId = null;
                              if ($tabTarget === 'menu-empresas' && isset($this->secIdEmpresas)) {
                                $secId = (int)$this->secIdEmpresas;
                              } elseif ($tabTarget === 'menu-likecheck' && isset($this->secIdLike)) {
                                $secId = (int)$this->secIdLike;
                              } elseif (isset($this->secIdPersonas)) {
                                $secId = (int)$this->secIdPersonas;
                              }
                              if ($secId) {
                                $logoBlock = CmsRepository::loadBlockByCoordsTypes([
                                  'id_pagina'  => $cmsPageId,
                                  'id_seccion' => $secId,
                                  'tipo_bloque'=> 'banner',
                                  'titulo'     => 'Logotipo',
                                  'visible'    => 1,
                                ], ['imagen','link'], 1);
                                $logoBlockId = isset($logoBlock['block']->id_bloque) ? (int)$logoBlock['block']->id_bloque : 0;
                                if ($logoBlockId > 0) {
                                  $row = \Illuminate\Database\Capsule\Manager::connection('cms')
                                    ->table('elementos_bloques')
                                    ->select(['contenido','link'])
                                    ->where('id_bloque', $logoBlockId)
                                    ->where('tipo', 'imagen')
                                    ->where(function($q){ $q->where('visible',1)->orWhereNull('visible'); })
                                    ->orderBy('id_elemento')
                                    ->first();
                                  if ($row) { $logoImg = $row->contenido ?? null; $logoHref = $row->link ?? null; }
                                }
                              }
                            }
                          } catch (\Throwable $e) {}
                          // Fallback de href por sección si viene vacío
                          if (!$logoHref) {
                            if ($tabTarget === 'menu-empresas') { $logoHref = BASE_URL . 'empresas/inicio'; }
                            elseif ($tabTarget === 'menu-likecheck') { $logoHref = BASE_URL . 'likecheck'; }
                            else { $logoHref = BASE_URL . 'personas/inicio'; }
                          }
                        ?>
                        <a href="<?php echo htmlspecialchars($logoHref); ?>" class="navbar-brand">
                          <?php if ($logoImg) { ?>
                            <div class="brand-logo-box"><img class="brand-logo-img" src="<?php echo htmlspecialchars($logoImg); ?>" alt="Logo" width="140" height="38"/></div>
                          <?php } else { ?>
                            <div class="brand-logo-box"><div class="brand-logo-skeleton" aria-hidden="true"></div></div>
                          <?php } ?>
                        </a>
                        <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#navbarCollapse">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse justify-content-between px-3" id="navbarCollapse">
                            <!-- Personas Menu -->
                            <?php if ($showPersonas) { ?>
                            <div id="menu-personas" class="menu-area w-100<?php echo ($tabTarget==='menu-personas'?' active':''); ?>">
                              <div class="navbar-nav font-weight-bold ml-auto py-0">
                                  <a href="<?php echo BASE_URL; ?>personas/inicio" class="nav-item nav-link<?php echo (preg_match('#^/personas/inicio($|/)#', $relPath)?' active':''); ?>">Inicio</a>
                                  <div class="nav-item dropdown">
                                      <a href="#" class="nav-link<?php echo (preg_match('#^/(cobertura|compatibilidad|estatusenvio|apn|faq|quienessomos)($|/)#', $relPath)?' active':''); ?>">Consultar</a>
                                      <div class="dropdown-menu border-0 m-0">
                                          <a href="<?php echo BASE_URL; ?>cobertura" class="dropdown-item<?php echo (preg_match('#^/cobertura($|/)#',$relPath)?' active':''); ?>">Cobertura</a>
                                          <a href="<?php echo BASE_URL; ?>compatibilidad" class="dropdown-item<?php echo (preg_match('#^/compatibilidad($|/)#',$relPath)?' active':''); ?>">Compatibilidad</a>
                                          <a href="<?php echo BASE_URL; ?>estatusenvio" class="dropdown-item<?php echo (preg_match('#^/estatusenvio($|/)#',$relPath)?' active':''); ?>">Estatus De Envío</a>
                                          <a href="<?php echo BASE_URL; ?>apn" class="dropdown-item<?php echo (preg_match('#^/apn($|/)#',$relPath)?' active':''); ?>">Configura Tu APN</a>
                                          <a href="<?php echo BASE_URL; ?>faq" class="dropdown-item<?php echo (preg_match('#^/faq($|/)#',$relPath)?' active':''); ?>">Preguntas Frecuentes</a>
                                          <a href="<?php echo BASE_URL; ?>quienessomos" class="dropdown-item<?php echo (preg_match('#^/quienessomos($|/)#',$relPath)?' active':''); ?>">Quiénes Somos</a>
                                      </div>
                                  </div>
                                  <a href="<?php echo BASE_URL; ?>portabilidad" class="nav-item nav-link<?php echo (preg_match('#^/portabilidad($|/)#', $relPath)?' active':''); ?>">Portabilidad</a>
                                  <div class="nav-item dropdown">
                                      <a href="#" class="nav-link<?php echo (preg_match('#^/(recargas|recargar_empresas)($|/)#', $relPath)?' active':''); ?>">Recargar</a>
                                      <div class="dropdown-menu border-0 m-0">
                                          <a href="<?php echo BASE_URL; ?>recargas" class="dropdown-item<?php echo (preg_match('#^/recargas($|/)#', $relPath)?' active':''); ?>">Recargar Likephone</a>
                                          <a href="<?php echo BASE_URL; ?>recargar_empresas" class="dropdown-item<?php echo (preg_match('#^/recargar_empresas($|/)#', $relPath)?' active':''); ?>">Recargar Empresas</a>
                                      </div>
                                  </div>
                                  <a href="<?php echo BASE_URL; ?>contacto" class="nav-item nav-link<?php echo (preg_match('#^/contacto($|/)#', $relPath)?' active':''); ?>">Contacto</a>
                                  <a href="<?php echo BASE_URL; ?>login" class="nav-item nav-link<?php echo (preg_match('#^/login($|/)#', $relPath)?' active':''); ?>">Inicia Sesion</a>
                              </div>
                            </div>
                            <?php } ?>

                            <!-- Empresas Menu -->
                            <?php if ($showEmpresas) { ?>
                            <div id="menu-empresas" class="menu-area w-100<?php echo ($tabTarget==='menu-empresas'?' active':''); ?>">
                              <div class="navbar-nav font-weight-bold ml-auto py-0">
                                  <a href="<?php echo BASE_URL; ?>empresas/inicio" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/inicio')!==false?' active':''); ?>">Inicio</a>
                                  <a href="<?php echo BASE_URL; ?>empresas/tarifas" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/tarifas')!==false?' active':''); ?>">Tarifas preferenciales</a>
                                  <a href="<?php echo BASE_URL; ?>empresas/marcaRapida" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/marcarapida')!==false?' active':''); ?>">Marcas rápidas</a>
                                  <a href="<?php echo BASE_URL; ?>empresas/marcasBlancas" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/marcasblancas')!==false?' active':''); ?>">Marcas Blancas</a>
                                  <a href="<?php echo BASE_URL; ?>empresas/distribuciones" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/distribuciones')!==false?' active':''); ?>">Distribuciones</a>
                                  <a href="<?php echo BASE_URL; ?>empresas/iot" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/iot')!==false?' active':''); ?>">IOT</a>
                                  <a href="<?php echo BASE_URL; ?>empresas/cotizar" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/empresas/cotizar')!==false?' active':''); ?>">Cotizar</a>
                              </div>
                            </div>
                            <?php } ?>

                            <!-- Likecheck Menu -->
                            <?php if ($showLikecheck) { ?>
                            <div id="menu-likecheck" class="menu-area w-100<?php echo ($tabTarget==='menu-likecheck'?' active':''); ?>">
                              <div class="navbar-nav font-weight-bold ml-auto py-0">
                                  <a href="<?php echo BASE_URL; ?>likecheck" class="nav-item nav-link<?php echo (preg_match('#/likecheck(/inicio)?$#',$uriPathLower)?' active':''); ?>">Inicio</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/funcionalidades" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/funcionalidades')!==false?' active':''); ?>">Funcionalidades</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/app_crm" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/app_crm')!==false?' active':''); ?>">APP &amp; CRM</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/planes" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/planes')!==false?' active':''); ?>">Planes</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/cotizador" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/cotizador')!==false?' active':''); ?>">Cotizador</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/promocion" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/promocion')!==false?' active':''); ?>">Promoción</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/clientes" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/clientes')!==false?' active':''); ?>">Clientes</a>
                                  <a href="<?php echo BASE_URL; ?>likecheck/contacto" class="nav-item nav-link<?php echo (strpos($uriPathLower,'/likecheck/contacto')!==false?' active':''); ?>">Contáctanos</a>
                              </div>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="collapse navbar-collapse justify-content-between px-3" id="navbarCollapse"></div>
                    </nav>
                </div>
            </div>
            <!-- Navbar End -->

        <?php endif; ?>