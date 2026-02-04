<?php
// application/Config.php - detecta entorno y usa env vars si existen

date_default_timezone_set('America/Mexico_City');

// Host sin puerto
$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
$host = preg_replace('/:\d+$/', '', $host);

// ¿Local?
$isLocal = preg_match('/^(localhost|127\.0\.0\.1)$/i', $host);

// BASE_URL: env > default por entorno
$envBase = getenv('BASE_URL');
if ($envBase && $envBase !== false) {
    define('BASE_URL', rtrim($envBase, '/') . '/');
} else {
    // Ajusta el dominio productivo si ya lo tienes
    define('BASE_URL', $isLocal ? 'http://localhost/qaweb/' : 'https://qaweb.tuoperador.net/');
}

// CMS_PAGE_ID: define qué cliente/página (id_pagina en cms_admin) usará TODO el sitio.
define('CMS_PAGE_ID', 18);   

// Constantes generales
define('DEFAULT_CONTROLLER', 'index');
define('DEFAULT_LAYOUT', 'default');
define('APP_NAME', 'qaweb');
define('APP_SLOGAN', 'qaweb');
define('APP_COMPANY', 'qaweb');
define('SESSION_TIME', 1000);
define('HASH_KEY', 'U776hbxmajuHJJ%$!#');
define('MODELOS', 2);

// Conexión principal (opcional)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: ($isLocal ? 'root' : 'admin'));
define('DB_PASS', getenv('DB_PASS') ?: ($isLocal ? '' : 'Admin#2025!'));
define('DB_NAME', getenv('DB_NAME') ?: 'likephoneqa'); // vacío -> deshabilita conexión primaria

// Conexión secundaria CMS (requerida)
define('CMS_DB_HOST', getenv('CMS_DB_HOST') ?: 'localhost');
define('CMS_DB_USER', getenv('CMS_DB_USER') ?: ($isLocal ? 'root' : 'admin'));
define('CMS_DB_PASS', getenv('CMS_DB_PASS') ?: ($isLocal ? '' : 'Admin#2025!')); // pass por defecto en prod si no hay env
define('CMS_DB_NAME', getenv('CMS_DB_NAME') ?: 'cms_admin');

// Otros (API)
define('LINK_API', getenv('LINK_API') ?: 'https://qa.api.likephone.mx/');
