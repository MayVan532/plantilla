<?php /* Footer visible para todas las vistas salvo cuando la vista pida ocultarlo (hideGlobalFooter) */ ?>
<?php
  if (!empty($this->hideGlobalFooter)) {
    return; // Paneles internos como personas/usuarios no muestran el footer público
  }

  // Prefer data provided by controller (e.g., Personas), fallback to CMS loader
  $F_logo   = isset($this->footer_logo) ? $this->footer_logo : null;
  $F_docs   = isset($this->footer_docs) ? $this->footer_docs : [];
  $F_social = isset($this->footer_social) ? $this->footer_social : [];
  $F_aviso  = isset($this->footer_aviso) ? $this->footer_aviso : '';
  $F_tel    = isset($this->footer_tel) ? $this->footer_tel : '';
  $F_mail   = isset($this->footer_mail) ? $this->footer_mail : '';
  $F_copy   = isset($this->footer_copy) ? $this->footer_copy : '';
  $F_addr   = isset($this->footer_addr) ? $this->footer_addr : '';
  $cmsPageId = isset($this->cms_page_id) ? (int)$this->cms_page_id : (defined('CMS_PAGE_ID') ? (int)CMS_PAGE_ID : 1);

  if ($F_logo===null && empty($F_docs) && empty($F_social)) {
    try { if (!class_exists('CmsRepository')) { require_once ROOT.'models'.DS.'CmsRepository.php'; } } catch (\Throwable $e) {}
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $sec  = 1; $tipo = 'footer';
    if (preg_match('#/(empresas)(/|$)#i', $uri)) { $sec = 2; $tipo = 'footer_e'; }
    elseif (preg_match('#/(likecheck)(/|$)#i', $uri)) { $sec = 3; $tipo = 'footer_l'; }
    try {
      if (class_exists('CmsRepository')) {
        $r = CmsRepository::loadBlockByCoordsTypes([
          'id_pagina'=>$cmsPageId,
          'id_seccion'=>$sec,
          'tipo_bloque'=>$tipo,
          'visible'=>1,
        ], ['imagen','link','social','texto','correo','telefono'], 1);
        $by = $r['byType'] ?? [];
        $F_logo = isset($by['imagen'][0]) ? ($by['imagen'][0]['contenido'] ?? null) : null;
        foreach (($by['link'] ?? []) as $it) { $t = trim((string)($it['contenido'] ?? '')); $u = trim((string)($it['link'] ?? '')); if ($t!=='' && $u!=='') { $F_docs[] = ['t'=>$t,'u'=>$u]; } }
        foreach (($by['social'] ?? []) as $it) { $name=strtolower(trim((string)($it['contenido'] ?? ''))); $lnk=trim((string)($it['link'] ?? '')); if ($name && $lnk) { $F_social[$name]=$lnk; } }
        foreach (($by['texto'] ?? []) as $it) { $txt=trim((string)($it['contenido'] ?? '')); if ($F_aviso==='' && preg_match('/aviso\s+de\s+privacidad/i',$txt)) { $F_aviso=$txt; continue; } if ($F_copy==='' && preg_match('/copyright/i',$txt)) { $F_copy=$txt; continue; } if ($F_tel==='' && preg_match('/\+?\d[\d\s\.-]{6,}/',$txt)) { $F_tel=$txt; continue; } }
        if (!$F_mail && !empty($by['correo'][0]['contenido'])) { $F_mail = trim((string)$by['correo'][0]['contenido']); }
        if (!$F_tel  && !empty($by['telefono'][0]['contenido'])) { $F_tel = trim((string)$by['telefono'][0]['contenido']); }
      }
    } catch (\Throwable $e) {}
  }
?>
<?php $uri = $_SERVER['REQUEST_URI'] ?? ''; $isEmp = preg_match('#/(empresas)(/|$)#i', $uri); $isLC = preg_match('#/(likecheck)(/|$)#i', $uri); ?>

<style>
  /* Skeleton styles (static) */
  .fp-skeleton{background:rgba(255,255,255,0.12); border-radius:8px;}
  .fp-sq{width:38px; height:38px; border-radius:10px; margin:0 4px;}
  .fp-ln{height:14px; border-radius:6px; margin:10px 0;}
  .fp-ln.w1{width:85%;}
  .fp-ln.w2{width:65%;}
  .fp-ln.w3{width:55%;}
  /* T&C modal list items look clickable */
  .tc-list .tc-item { cursor:pointer; }
  /* Footer responsive centering */
  @media (max-width: 576px) {
    .footer-col { text-align:center !important; }
    .footer-contact p, .footer-contact a { text-align:center !important; display:inline-block; }
    .footer-contact p { display:flex; align-items:center; justify-content:center; gap:8px; flex-wrap:wrap; }
    .footer-contact p i { margin-right:4px; }
    .footer-social-title { margin-top: 12px !important; }
    .footer-contact-wrap { max-width: 520px; margin: 0 auto; padding: 0 16px; }
  }
  @media (min-width: 577px) and (max-width: 992px) {
    .footer-contact-wrap { max-width: 640px; margin: 0 auto; padding: 0 20px; }
  }
</style>

<!-- Footer Start -->
<div class="container-fluid text-white py-5 px-sm-3 px-lg-5 text-center" 
     style="margin-top: 90px; background-color:#003041;">
    
    <div class="row pt-5 justify-content-center">

        <!-- Título de sección (Empresas y Likecheck) -->
        <?php if ($isLC || $isEmp) { ?>
          <div class="col-12">
            <?php 
              $titleTxt = isset($this->footer_title) ? trim((string)$this->footer_title) : '';
              if ($titleTxt !== '') { ?>
                <h4 style="color:#6cd0ff; letter-spacing:2px; font-weight:800; margin-bottom:24px;">
                  <?php echo htmlspecialchars($titleTxt); ?>
                </h4>
            <?php } else { ?>
                <div class="fp-skeleton" style="width:220px; height:20px; border-radius:6px; margin:0 auto 24px;"></div>
            <?php } ?>
          </div>
        <?php } ?>

        <!-- Columna 1 -->
        <div class="col-lg-3 col-md-6 mb-5 text-justify">
            <?php if ($isLC) { ?>
              <!-- Likecheck: Logos lado a lado con su descripción debajo de cada uno -->
              <div style="display:flex; justify-content:center; gap:24px; flex-wrap:nowrap; margin-bottom:12px;">
                <div style="flex:1 1 48%; max-width:48%; text-align:center;">
                  <?php if (!empty($this->footer_logo1)) { ?>
                    <img src="<?php echo htmlspecialchars($this->footer_logo1); ?>" alt="logo1" style="max-width:100%; height:auto; max-height:56px; object-fit:contain;">
                  <?php } else { ?>
                    <div class="fp-skeleton" style="display:inline-block; width:100%; height:48px; border-radius:10px;"></div>
                  <?php } ?>
                  <?php if (!empty($this->footer_logo1_text)) { ?>
                    <p style="line-height:1.6; opacity:0.9; font-size:14px; margin-top:8px;">
                      <?php echo htmlspecialchars($this->footer_logo1_text); ?>
                    </p>
                  <?php } else { ?>
                    <div class="fp-skeleton fp-ln w2" style="margin:10px auto; width:90%;"></div>
                  <?php } ?>
                </div>
                <div style="flex:1 1 48%; max-width:48%; text-align:center;">
                  <?php if (!empty($this->footer_logo2)) { ?>
                    <img src="<?php echo htmlspecialchars($this->footer_logo2); ?>" alt="logo2" style="max-width:100%; height:auto; max-height:56px; object-fit:contain;">
                  <?php } else { ?>
                    <div class="fp-skeleton" style="display:inline-block; width:100%; height:48px; border-radius:10px;"></div>
                  <?php } ?>
                  <div class="mt-2" style="margin-top:6px;">
                    <a href="<?php echo BASE_URL; ?>personas/inicio" style="color:#6cd0ff; text-decoration:none; font-weight:600; font-size:13px;">
                      &larr; Ir a LikePhone
                    </a>
                  </div>
                  <?php if (!empty($this->footer_logo2_text)) { ?>
                    <p style="line-height:1.6; opacity:0.9; font-size:14px; margin-top:8px;">
                      <?php echo htmlspecialchars($this->footer_logo2_text); ?>
                    </p>
                  <?php } else { ?>
                    <div class="fp-skeleton fp-ln w2" style="margin:10px auto; width:90%;"></div>
                  <?php } ?>
                </div>
              </div>
            <?php } else { ?>
              <a href="#" class="navbar-brand d-block mb-3 text-center" style="text-decoration:none;">
                  <?php if (!empty($F_logo)) { ?>
                      <img src="<?php echo htmlspecialchars($F_logo); ?>" alt="logo" style="max-width:180px; height:auto; display:inline-block;">
                  <?php } else { ?>
                      <?php if ($isEmp) { ?>
                          <!-- Sin logo en Empresas: no mostrar texto de fallback -->
                          <span style="display:block; height:24px;"></span>
                      <?php } else { ?>
                          <h1 style="color:#fff; font-weight:700; letter-spacing:1px;">LIKEPHONE</h1>
                      <?php } ?>
                  <?php } ?>
              </a>
              <p style="line-height:1.6; opacity:0.9; font-size:14px;">
                  ¡Con LikePhone, la mejor red de telefonía está en tus manos! Gestiona tu línea de forma 
                  rápida, sencilla y segura. Recargas, portabilidad, planes, saldo y más.
              </p>
            <?php } ?>

            <?php 
              $socialVals = array_values(array_filter(array_map(function($v){ return is_string($v) ? trim($v) : ''; }, $F_social)));
              $hasSocial = count($socialVals) > 0;
              if ($hasSocial) { ?>
              <h6 class="text-uppercase mt-4 mb-3 text-center" 
                  style="letter-spacing:4px; font-size:14px; font-weight:700; color:#ffffff; opacity:0.9;">
                  Redes sociales
              </h6>

              <div class="d-flex justify-content-center">
                <?php if (!empty($F_social['facebook'])) { ?>
                  <a class="btn btn-outline-light btn-square mx-1" href="<?php echo htmlspecialchars($F_social['facebook']); ?>" target="_blank" 
                     style="width:38px; height:38px; border-radius:10px;">
                      <i class="fab fa-facebook-f"></i>
                  </a>
                <?php } ?>
                <?php if (!empty($F_social['instagram'])) { ?>
                  <a class="btn btn-outline-light btn-square mx-1" href="<?php echo htmlspecialchars($F_social['instagram']); ?>" target="_blank" 
                     style="width:38px; height:38px; border-radius:10px;">
                      <i class="fab fa-instagram"></i>
                  </a>
                <?php } ?>
                <?php if (!empty($F_social['tiktok'])) { ?>
                  <a class="btn btn-outline-light btn-square mx-1" href="<?php echo htmlspecialchars($F_social['tiktok']); ?>" target="_blank" 
                     style="width:38px; height:38px; border-radius:10px;">
                      <i class="fab fa-tiktok"></i>
                  </a>
                <?php } ?>
                <?php if (!empty($F_social['youtube'])) { ?>
                  <a class="btn btn-outline-light btn-square mx-1" href="<?php echo htmlspecialchars($F_social['youtube']); ?>" target="_blank" 
                     style="width:38px; height:38px; border-radius:10px;">
                      <i class="fab fa-youtube"></i>
                  </a>
                <?php } ?>
                <?php if (!empty($F_social['whatsapp'])) { ?>
                  <a class="btn btn-outline-light btn-square mx-1" href="<?php echo htmlspecialchars($F_social['whatsapp']); ?>" target="_blank" 
                     style="width:38px; height:38px; border-radius:10px;">
                      <i class="fab fa-whatsapp"></i>
                  </a>
                <?php } ?>
              </div>
            <?php } elseif (!$isEmp) { ?>
              <h6 class="text-uppercase mt-4 mb-3 text-center" 
                  style="letter-spacing:4px; font-size:14px; font-weight:700; color:#ffffff; opacity:0.9;">
                  Redes sociales
              </h6>
              <div class="d-flex justify-content-center" aria-hidden="true">
                <div class="fp-skeleton fp-sq"></div>
                <div class="fp-skeleton fp-sq"></div>
                <div class="fp-skeleton fp-sq"></div>
                <div class="fp-skeleton fp-sq"></div>
                <div class="fp-skeleton fp-sq"></div>
              </div>
            <?php } ?>
        </div>

        <!-- Columna 2 -->
        <div class="col-lg-3 col-md-6 mb-5 text-justify">

            <h5 style="color:#ffffff; text-transform:uppercase; margin-bottom:1rem; 
                       letter-spacing:4px; text-align:center; font-size:15px;">
                Links
            </h5>

            <div style="display:flex; flex-direction:column; align-items:center;">
                <?php 
                  $uri = $_SERVER['REQUEST_URI'] ?? '';
                  $isEmp = preg_match('#/(empresas)(/|$)#i', $uri);
                  $isLC  = preg_match('#/(likecheck)(/|$)#i', $uri);
                  if ($isEmp) {
                ?>
                  <!-- Empresas -->
                  <a href="/qaweb/empresas/inicio" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Inicio</a>
                  <a href="/qaweb/empresas/tarifas" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Tarifas preferenciales</a>
                  <a href="/qaweb/empresas/marcaRapida" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Marcas Rápidas</a>
                  <a href="/qaweb/empresas/marcasBlancas" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Marcas Blancas</a>
                  <a href="/qaweb/empresas/distribuciones" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Distribuciones</a>
                  <a href="/qaweb/empresas/iot" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">IoT</a>
                  <a href="/qaweb/empresas/cotizar" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Cotizar</a>
                <?php } elseif ($isLC) { ?>
                  <!-- Likecheck -->
                  <a href="/qaweb/likecheck/inicio" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Inicio</a>
                  <a href="/qaweb/likecheck/funcionalidades" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Funcionalidades</a>
                  <a href="/qaweb/likecheck/app_crm" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">App & CRM</a>
                  <a href="/qaweb/likecheck/planes" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Planes</a>
                  <a href="/qaweb/likecheck/cotizador" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Cotizador</a>
                  <a href="/qaweb/likecheck/promocion" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Promoción</a>
                  <a href="/qaweb/likecheck/clientes" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Clientes</a>
                  <a href="/qaweb/likecheck/contacto" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Contacto</a>
                <?php } else { ?>
                  <!-- Personas -->
                  <a href="<?php echo BASE_URL; ?>personas/inicio" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500; transition:0.2s;">Inicio</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>cobertura">Cobertura</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>compatibilidad">Compatibilidad</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>estatusenvio">Estatus de Envío</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>apn">Configura tu APN</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>faq">Preguntas Frecuentes</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>quienessomos">Quiénes Somos</a>
                  <a href="<?php echo BASE_URL; ?>portabilidad" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Portabilidad</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>recargas">Recargar Likephone</a>
                  <a style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;" href="<?php echo BASE_URL; ?>recargar_empresas">Recargar Empresas</a>
                  <a href="<?php echo BASE_URL; ?>contacto" style="color:#ffffff; margin:6px 0; text-decoration:none; font-weight:500;">Contacto</a>
                <?php } ?>

                <!-- Documentos legales se moverán a la columna de Contacto -->
            </div>
        </div>

        <!-- Columna 3 -->
        <div class="col-lg-3 col-md-6 mb-5 text-justify footer-col footer-contact">
            <h5 class="text-white text-uppercase mb-4 text-center" 
                style="letter-spacing:4px; font-size:15px;">
                Contáctanos
            </h5>

            <?php 
              $hasContact = (trim($F_addr) !== '' || trim($F_tel) !== '' || trim($F_mail) !== '');
              if ($hasContact) { ?>
                <div class="footer-contact-wrap">
                  <?php if (!empty($F_addr)) { ?>
                    <p style="opacity:0.9; font-size:14px;"><i class="fa fa-map-marker-alt mr-2"></i>
                      <?php echo htmlspecialchars($F_addr); ?>
                    </p>
                  <?php } ?>
                  <?php if (!empty($F_tel)) { ?>
                    <?php 
                      $num = preg_replace('/\D+/', '', $F_tel);
                      $hrefTel = $num !== '' ? ('tel:+'.$num) : '';
                    ?>
                    <p style="opacity:0.9; font-size:14px;"><i class="fa fa-phone-alt mr-2"></i>
                      <?php if ($hrefTel !== '') { ?><a href="<?php echo htmlspecialchars($hrefTel); ?>" style="color:#ffffff; text-decoration:none;">+<?php echo htmlspecialchars($num); ?></a><?php } else { echo htmlspecialchars($F_tel); } ?>
                    </p>
                  <?php } ?>
                  <?php if (!empty($F_mail)) { ?>
                    <?php 
                      $hrefMail = stripos($F_mail, 'mailto:') === 0 ? $F_mail : ('mailto:'.trim($F_mail));
                    ?>
                    <p style="opacity:0.9; font-size:14px;"><i class="fa fa-envelope mr-2"></i>
                      <a href="<?php echo htmlspecialchars($hrefMail); ?>" style="color:#ffffff; text-decoration:none;">
                        <?php echo htmlspecialchars(preg_replace('/^mailto:/i','',$hrefMail)); ?>
                      </a>
                    </p>
                  <?php } ?>
                </div>
            <?php } else { ?>
                <div class="fp-skeleton fp-ln w1" aria-hidden="true"></div>
                <div class="fp-skeleton fp-ln w2" aria-hidden="true"></div>
                <div class="fp-skeleton fp_ln w3" aria-hidden="true"></div>
            <?php } ?>

            <?php if (!$isLC) { ?>
              <!-- Botón Términos (no para Likecheck) -->
              <div class="text-center mt-4">
                  <button class="px-4 py-2" 
                          style="background:none; border:2px solid #fff; color:#fff; 
                                 border-radius:6px; font-weight:600; letter-spacing:1px; 
                                 transition:0.3s;"
                          data-bs-toggle="modal" data-bs-target="#modalAvisos">
                      DOCUMENTOS LEGALES
                  </button>
              </div>
            <?php } ?>
        </div>
    </div>
</div>


<!-- Modal Términos y Condiciones -->
<div class="modal fade" id="modalAvisos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">

      <div class="modal-content" 
           style="background:#fff; border-radius:12px; overflow:hidden;">

          <!-- Header -->
          <div class="modal-header" 
               style="background-color:#003041; color:#ffffff; border-bottom:1px solid rgba(255,255,255,.1);">
              <h5 class="modal-title" style="color:#ffffff;">DOCUMENTOS LEGALES</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Body -->
          <div class="modal-body" style="padding:25px; line-height:1.6;">
            <div class="row" style="min-height:420px;">
              <!-- Lista de documentos -->
              <div class="col-md-4 border-end mb-3 mb-md-0">
                <ul class="list-group list-group-flush tc-list">
                  <?php if ($F_aviso) { ?>
                    <li class="list-group-item tc-item active" data-type="text" data-target="tc-aviso">
                      TERMINOS Y CONDICIONES | AVISO DE PRIVACIDAD
                    </li>
                  <?php } ?>
                  <?php if (!empty($F_docs)) { foreach ($F_docs as $idx => $d) { ?>
                    <li class="list-group-item tc-item<?php echo (!$F_aviso && $idx===0 ? ' active' : ''); ?>" 
                        data-type="link" 
                        data-url="<?php echo htmlspecialchars($d['u']); ?>">
                      <?php echo htmlspecialchars($d['t']); ?>
                    </li>
                  <?php }} ?>
                </ul>
              </div>

              <!-- Contenido del documento seleccionado -->
              <div class="col-md-8">
                <div style="max-height:70vh; overflow-y:auto; padding-right:6px;">
                <div id="tc-aviso-panel" class="tc-panel" style="display:none;">
                  <?php if ($F_aviso) { ?>
                    <div style="margin-bottom:0; white-space:pre-wrap; text-align:justify;">
                      <?php echo nl2br(htmlspecialchars($F_aviso)); ?>
                    </div>
                  <?php } else { ?>
                    <div style="opacity:.7;">No hay aviso de privacidad configurado.</div>
                  <?php } ?>
                </div>
                <div id="tc-frame-panel" class="tc-panel" style="display:none; height:380px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:13px; opacity:.7;">Documento seleccionado</span>
                    <a id="tc-open-new" href="#" target="_blank" rel="noopener" style="font-size:13px; color:#0f6674; text-decoration:none; display:none;">Abrir en nueva pestaña</a>
                  </div>
                  <iframe id="tc-frame" src="" style="border:0; width:100%; height:100%;" loading="lazy"></iframe>
                </div>
                <div id="tc-empty-panel" class="tc-panel" style="display:none; opacity:.7;">
                  Selecciona un documento en la lista de la izquierda.
                </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer" 
               style="background-color:#003041; padding:15px;">
              <button class="btn btn-light px-4" data-bs-dismiss="modal">Cerrar</button>
          </div>

      </div>
  </div>
</div>

<script>
  (function(){
    var modal = document.getElementById('modalAvisos');
    if (!modal) return;
    function showPanel(type, url){
      var aviso = document.getElementById('tc-aviso-panel');
      var framePanel = document.getElementById('tc-frame-panel');
      var empty = document.getElementById('tc-empty-panel');
      var openNew = document.getElementById('tc-open-new');
      if (!aviso || !framePanel || !empty) return;
      aviso.style.display = 'none';
      framePanel.style.display = 'none';
      empty.style.display = 'none';
      if (openNew) { openNew.style.display = 'none'; openNew.removeAttribute('href'); }
      if (type === 'text') {
        aviso.style.display = 'block';
      } else if (type === 'link' && url) {
        var f = document.getElementById('tc-frame');
        if (f) f.src = url;
        framePanel.style.display = 'block';
        if (openNew) { openNew.style.display = 'inline'; openNew.href = url; }
      } else {
        empty.style.display = 'block';
      }
    }
    modal.addEventListener('shown.bs.modal', function(){
      var items = modal.querySelectorAll('.tc-item');
      // Siempre resetear selección: primero Aviso de Privacidad si existe
      items.forEach(function(li){ li.classList.remove('active'); });
      var first = modal.querySelector('.tc-item[data-type="text"][data-target="tc-aviso"]');
      if (!first && items.length > 0) first = items[0];
      if (first) {
        first.classList.add('active');
        var t = first.getAttribute('data-type');
        var u = first.getAttribute('data-url');
        showPanel(t, u);
      } else {
        showPanel(null, null);
      }
    });
    modal.addEventListener('hidden.bs.modal', function(){
      var f = document.getElementById('tc-frame');
      if (f) f.src = '';
    });
    modal.addEventListener('click', function(e){
      var li = e.target.closest('.tc-item');
      if (!li) return;
      var list = modal.querySelectorAll('.tc-item');
      list.forEach(function(x){ x.classList.remove('active'); });
      li.classList.add('active');
      var t = li.getAttribute('data-type');
      var u = li.getAttribute('data-url');
      showPanel(t, u);
    });
  })();
</script>

<?php if (isset($_GET['debugcms']) && $_GET['debugcms'] == '1') { ?>
  <div style="background:#0b2a36; color:#e2e8f0; font-size:12px; padding:12px; margin:0; border-top:1px solid #134454;">
    <strong>Footer Debug (Personas)</strong>
    <pre style="white-space:pre-wrap; margin:8px 0; color:#e2e8f0;">
<?php 
  $dbg = [
    'logo' => $F_logo,
    'docs' => $F_docs,
    'social' => $F_social,
    'tel' => $F_tel,
    'mail' => $F_mail,
    'copy' => $F_copy,
  ];
  if (isset($this->debug_footer)) { $dbg['debug_footer'] = $this->debug_footer; }
  echo htmlspecialchars(json_encode($dbg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
    </pre>
  </div>
<?php } ?>

<!-- Copyright -->
<div class="container-fluid text-white border-top py-4 text-center" 
     style="background-color:#002530; border-color:rgba(255,255,255,.1);">
    <?php if (trim((string)$F_copy) === '') { ?>
      <div class="fp-skeleton fp-ln w2" style="display:inline-block; width:180px;"></div>
    <?php } else { ?>
      <?php echo htmlspecialchars($F_copy); ?>
    <?php } ?>
</div>

<!-- Scripts Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
