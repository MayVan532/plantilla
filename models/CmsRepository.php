<?php
use Illuminate\Database\Capsule\Manager as Capsule;
class CmsRepository
{
    protected static $connection = 'cms';
    protected static $defaultPageId = 1;

    public static function getCompanyByName($name)
    {
        try {
            return Capsule::connection(self::$connection)
                ->table('empresas_cms')
                ->where('nombre', $name)
                ->where('visible', 1)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    public static function getPageByName($name)
    {
        return Capsule::connection(self::$connection)
            ->table('pagina_cms')
            ->where('nombre', $name)
            ->where('estatus', 'activo')
            ->first();
    }

    public static function getTabByTitle($pageId, $title)
    {
        return Capsule::connection(self::$connection)
            ->table('pestana_cms')
            ->where('id_pagina', $pageId)
            ->where('titulo', $title)
            ->where('visible', 1)
            ->orderBy('orden_menu')
            ->first();
    }

    public static function getSubtabByTitle($pageId, $title)
    {
        $conn = Capsule::connection(self::$connection);
        try {
            return $conn->table('subpestana_cms')
                ->where('id_pagina', $pageId)
                ->where('titulo', $title)
                ->where('visible', 1)
                ->orderBy('orden_menu')
                ->first();
        } catch (\Throwable $e) {
            try {
                return $conn->table('subpestanas_cms')
                    ->where('id_pagina', $pageId)
                    ->where('titulo', $title)
                    ->where('visible', 1)
                    ->orderBy('orden_menu')
                    ->first();
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    public static function findCarouselBlock($pageName = null, $tabTitle = null)
    {
        $debug = [ 'step' => 'repo:init' ];
        $block = null;
        $page = null;
        $tab = null;

        if ($pageName) {
            $page = self::getPageByName($pageName);
            $debug['page'] = $page ? $page->id_pagina : null;
        }
        if ($page && $tabTitle) {
            $tab = self::getTabByTitle($page->id_pagina, $tabTitle);
            $debug['tab'] = $tab ? $tab->id_pestana : null;
        }
        if ($page && $tab) {
            $block = Capsule::connection(self::$connection)
                ->table('bloques_cms')
                ->where('id_pagina', $page->id_pagina)
                ->where('id_pestana', $tab->id_pestana)
                ->where(function ($q) {
                    $q->where('tipo_bloque', 'carrusel')
                      ->orWhere('titulo', 'like', '%carrusel%');
                })
                ->where('visible', 1)
                ->orderBy('orden')
                ->first();
            $debug['block_by_page_tab'] = $block ? $block->id_bloque : null;
        }
        if (!$block) {
            $block = self::getBlockByTitleLike('carrusel');
            $debug['block_fallback'] = $block ? $block->id_bloque : null;
        }
        return [ 'block' => $block, 'page' => $page, 'tab' => $tab, 'debug' => $debug ];
    }

    public static function getBlockById($blockId)
    {
        return Capsule::connection(self::$connection)
            ->table('bloques_cms')
            ->where('id_bloque', $blockId)
            ->first();
    }

    public static function resolveBlockId(array $coords)
    {
        $conn = Capsule::connection(self::$connection);

        $baseFilter = function($q) use ($coords) {
            if (isset($coords['id_pagina']))     { $q->where('id_pagina', (int)$coords['id_pagina']); }
            if (isset($coords['id_seccion']))    { $q->where('id_seccion', (int)$coords['id_seccion']); }
            if (isset($coords['id_pestana']))    { $q->where('id_pestana', (int)$coords['id_pestana']); }
            if (isset($coords['id_subpestana'])) { $q->where('id_subpestana', (int)$coords['id_subpestana']); }
            if (array_key_exists('visible', $coords)) { $q->where('visible', (int)$coords['visible']); }
            else { $q->where('visible', 1); }
        };

        if (!empty($coords['titulo'])) {
            try {
                $q = $conn->table('bloques_cms');
                $baseFilter($q);
                $q->where('titulo', $coords['titulo']);
                // Bases pueden no tener columna 'updated_at'. Orden seguro por 'orden'.
                $row = $q->orderBy('orden','asc')->first();
                if ($row) { return (int)$row->id_bloque; }
            } catch (\Throwable $e) {}
        }

        if (!empty($coords['tipo_bloque'])) {
            try {
                $q = $conn->table('bloques_cms');
                $baseFilter($q);
                $q->where('tipo_bloque', $coords['tipo_bloque']);
                $row = $q->orderBy('orden','asc')->first();
                if ($row) { return (int)$row->id_bloque; }
            } catch (\Throwable $e) {}
        }

        if (!empty($coords['titulo_like'])) {
            try {
                $q = $conn->table('bloques_cms');
                $baseFilter($q);
                $q->where('titulo','like','%'.$coords['titulo_like'].'%');
                $row = $q->orderBy('orden','asc')->first();
                if ($row) { return (int)$row->id_bloque; }
            } catch (\Throwable $e) {}
        }

        return null;
    }

    /** Carga elementos (contenido) por coordenadas, equivalente a loadElementsByBlockId pero resolviendo el id antes. */
    public static function loadElementsByCoords(array $coords, ?string $type = null, ?int $visible = 1)
    {
        $blockId = self::resolveBlockId($coords);
        if (!$blockId) { return [ 'items' => [], 'block' => null, 'debug' => ['step'=>'repo:loadElementsByCoords','resolved'=>null] ]; }
        return self::loadElementsByBlockId((int)$blockId, $type, $visible);
    }

    /** Carga tipos por coordenadas, equivalente a loadBlockByIdTypes pero resolviendo el id antes. */
    public static function loadBlockByCoordsTypes(array $coords, array $types = ['imagen'], ?int $visible = 1)
    {
        $blockId = self::resolveBlockId($coords);
        if (!$blockId) { return [ 'byType' => [], 'block' => null, 'debug' => ['step'=>'repo:loadBlockByCoordsTypes','resolved'=>null] ]; }
        return self::loadBlockByIdTypes((int)$blockId, $types, $visible);
    }

    /** Variante zipped con links por coordenadas. */
    public static function loadZippedItemsByCoordsWithLinks(array $coords, array $types = ['imagen','titulo','boton'], ?int $visible = 1)
    {
        $blockId = self::resolveBlockId($coords);
        if (!$blockId) { return [ 'items' => [], 'block' => null, 'debug' => ['step'=>'repo:loadZippedItemsByCoordsWithLinks','resolved'=>null] ]; }
        return self::loadZippedItemsByIdWithLinks((int)$blockId, $types, $visible);
    }

    public static function getBlockByTitleLike($title)
    {
        return Capsule::connection(self::$connection)
            ->table('bloques_cms')
            ->where('visible', 1)
            ->where(function ($q) use ($title) {
                $q->where('titulo', 'like', '%'.$title.'%')
                  ->orWhere('tipo_bloque', $title);
            })
            ->orderBy('updated_at', 'desc')
            ->orderBy('orden', 'asc')
            ->first();
    }

    public static function getBlockElements($blockId, $type = null, $visible = 1)
    {
        $query = Capsule::connection(self::$connection)
            ->table('elementos_bloques')
            ->where('id_bloque', $blockId);
        if ($type !== null) {
            $query->where('tipo', $type);
        }
        if ($visible !== null) {
            $query->where('visible', (int)$visible);
        }
        return $query->orderBy('id_elemento')->pluck('contenido')->toArray();
    }

    /**
     * Versión con debug estructurado que NO rompe compatibilidad.
     * Devuelve: ['items'=>array, 'block'=>object|null, 'debug'=>array]
     */
    public static function loadElementsByBlockId(int $blockId, ?string $type = null, ?int $visible = 1)
    {
        $debug = [
            'step'    => 'repo:loadElementsByBlockId',
            'blockId' => $blockId,
            'type'    => $type,
            'visible' => $visible,
        ];

        $block = self::getBlockById($blockId);
        $debug['block'] = $block ? $block->id_bloque : null;

        $items = [];
        if ($block) {
            $items = self::getBlockElements($block->id_bloque, $type, $visible);
        }
        $debug['count'] = is_array($items) ? count($items) : 0;

        return [ 'items' => $items, 'block' => $block, 'debug' => $debug ];
    }

    public static function loadBlockItems(array $opts)
    {
        $debug = [ 'step' => 'repo:loadBlockItems' ];
        $conn = Capsule::connection(self::$connection);
        $company = null; $page = null; $tab = null; $subtab = null; $block = null; $items = [];

        // Company
        if (!empty($opts['companyName'])) {
            $company = self::getCompanyByName($opts['companyName']);
            $debug['company'] = $company ? ($company->id_empresa ?? true) : null;
        }

        // Page
        if (!empty($opts['pageName'])) {
            $page = self::getPageByName($opts['pageName']);
            $debug['page'] = $page ? $page->id_pagina : null;
        }

        // Tab
        if ($page && !empty($opts['tabTitle'])) {
            $tab = self::getTabByTitle($page->id_pagina, $opts['tabTitle']);
            $debug['tab'] = $tab ? $tab->id_pestana : null;
        }

        // Subtab
        if ($page && !empty($opts['subtabTitle'])) {
            $subtab = self::getSubtabByTitle($page->id_pagina, $opts['subtabTitle']);
            $debug['subtab'] = $subtab ? ($subtab->id_subpestana ?? $subtab->id_subpestanas ?? true) : null;
        }

        // Block
        if (!empty($opts['blockId'])) {
            $block = self::getBlockById($opts['blockId']);
        } else if (!empty($opts['blockTitleLike'])) {
            $qry = $conn->table('bloques_cms')->where('visible', 1);
            if ($page) { $qry->where('id_pagina', $page->id_pagina); }
            if ($tab)  { $qry->where('id_pestana', $tab->id_pestana); }
            // Nota: si los bloques referencian subpestañas en tu esquema, aquí agregamos el WHERE id_subpestana cuando lo confirmemos.
            $qry->where(function($q) use ($opts){
                $q->where('titulo','like','%'.$opts['blockTitleLike'].'%')
                  ->orWhere('tipo_bloque',$opts['blockTitleLike']);
            });
            $block = $qry->orderBy('updated_at','desc')->orderBy('orden','asc')->first();
        }
        $debug['block'] = $block ? $block->id_bloque : null;

        // Elements
        if ($block) {
            $items = self::getBlockElements($block->id_bloque, $opts['type'] ?? null, $opts['visible'] ?? 1);
        }

        return [ 'items' => $items, 'block' => $block, 'page' => $page, 'tab' => $tab, 'subtab' => $subtab, 'company' => $company, 'debug' => $debug ];
    }

    public static function loadBlockByTitleTypes(string $pageName, ?string $tabTitle, ?string $subtabTitle, string $blockTitleLike, array $types = ['imagen'])
    {
        $byType = [];
        $fullDebug = ['step' => 'repo:loadBlockByTitleTypes'];
        $base = self::loadBlockItems([
            'pageName'       => $pageName,
            'tabTitle'       => $tabTitle,
            'subtabTitle'    => $subtabTitle,
            'blockTitleLike' => $blockTitleLike,
            'visible'        => 1
        ]);
        $fullDebug['base'] = $base['debug'];
        if ($base['block']) {
            foreach ($types as $t) {
                $items = self::getBlockElements($base['block']->id_bloque, $t, 1);
                $byType[$t] = $items;
            }
        }
        return [ 'byType' => $byType, 'block' => $base['block'], 'debug' => $fullDebug ];
    }

    /**
     * Carga múltiples tipos de elementos para un bloque específico por ID en una sola llamada.
     * Devuelve la misma estructura que loadBlockByTitleTypes, pero filtrando por blockId.
     *
     * @param int   $blockId   ID del bloque en 'bloques_cms'
     * @param array $types     Tipos a cargar (ej: ['imagen','titulo','subtitulo','texto','boton'])
     * @param int|null $visible Si es 1, filtra solo visibles; si null, trae todos
     * @return array { byType: array<string,array>, block: object|null, debug: array }
     */
    public static function loadBlockByIdTypes(int $blockId, array $types = ['imagen'], ?int $visible = 1)
    {
        $byType = [];
        $fullDebug = ['step' => 'repo:loadBlockByIdTypes'];

        // Localiza el bloque por ID
        $block = self::getBlockById($blockId);
        $fullDebug['block'] = $block ? $block->id_bloque : null;

        if ($block) {
            foreach ($types as $t) {
                $items = self::getBlockElements($block->id_bloque, $t, $visible);
                $byType[$t] = $items;
            }
        }

        return [ 'byType' => $byType, 'block' => $block, 'debug' => $fullDebug ];
    }

    /**
     * Zipea por índice los tipos solicitados de un bloque y devuelve un arreglo de items.
     * Estructura: { items: [ { tipo1: val, tipo2: val, ... }, ... ], block, debug }
     */
    public static function loadZippedItemsById(int $blockId, array $types = ['imagen','titulo','texto'], ?int $visible = 1)
    {
        $base = self::loadBlockByIdTypes($blockId, $types, $visible);
        $by = $base['byType'] ?? [];

        $max = 0;
        foreach ($types as $t) {
            $max = max($max, isset($by[$t]) ? count($by[$t]) : 0);
        }

        $items = [];
        for ($i = 0; $i < $max; $i++) {
            $row = [];
            foreach ($types as $t) {
                $row[$t] = $by[$t][$i] ?? null;
            }
            $items[] = $row;
        }

        $debug = [
            'step'  => 'repo:loadZippedItemsById',
            'block' => $base['debug']['block'] ?? null,
            'count' => count($items),
            'types' => $types,
        ];

        return [ 'items' => $items, 'block' => $base['block'], 'debug' => $debug ];
    }

    /**
     * Obtiene filas crudas por tipo, incluyendo columna 'link'.
     */
    protected static function getBlockElementsRaw(int $blockId, string $type, ?int $visible = 1)
    {
        $q = Capsule::connection(self::$connection)
            ->table('elementos_bloques')
            ->select(['contenido','link'])
            ->where('id_bloque', $blockId)
            ->where('tipo', $type)
            ->orderBy('id_elemento');
        if ($visible !== null) { $q->where('visible', (int)$visible); }
        return $q->get()->toArray();
    }

    /** Devuelve el primer link (columna link) para un tipo dado en un bloque. */
    public static function getFirstLinkForType(int $blockId, string $type, ?int $visible = 1)
    {
        try {
            $q = Capsule::connection(self::$connection)
                ->table('elementos_bloques')
                ->select(['link'])
                ->where('id_bloque', $blockId)
                ->where('tipo', $type)
                ->orderBy('id_elemento');
            if ($visible !== null) { $q->where('visible', (int)$visible); }
            $row = $q->first();
            if ($row && isset($row->link)) { return trim((string)$row->link) ?: null; }
        } catch (\Throwable $e) {}
        return null;
    }

    /**
     * Variante que arma items por índice e incluye 'link' real (columna link) tomando prioridad por tipo.
     * Prioridad para link: boton > titulo > imagen > (link como tipo explícito si existe).
     */
    public static function loadZippedItemsByIdWithLinks(int $blockId, array $types = ['imagen','titulo','boton'], ?int $visible = 1)
    {
        // Cargar crudo por tipo
        $rawBy = [];
        $max = 0;
        foreach ($types as $t) {
            $rows = self::getBlockElementsRaw($blockId, $t, $visible);
            $rawBy[$t] = $rows;
            $max = max($max, is_array($rows) ? count($rows) : 0);
        }
        // También intente cargar filas tipo 'link' si existieran
        $rawLinkType = self::getBlockElementsRaw($blockId, 'link', $visible);

        $items = [];
        for ($i=0; $i<$max; $i++) {
            $row = [];
            foreach ($types as $t) {
                $row[$t] = isset($rawBy[$t][$i]) ? ($rawBy[$t][$i]->contenido ?? null) : null;
            }
            // Resolver link por prioridad
            $link = null;
            $prio = ['boton','titulo','imagen'];
            foreach ($prio as $p) {
                if (in_array($p, $types, true) && isset($rawBy[$p][$i])) {
                    $link = trim((string)($rawBy[$p][$i]->link ?? '')) ?: $link;
                    if ($link) { break; }
                }
            }
            // Si hay tipo 'link' explícito y nada aún
            if (!$link && isset($rawLinkType[$i])) {
                $link = trim((string)($rawLinkType[$i]->contenido ?? '')) ?: null;
            }
            if ($link) { $row['link'] = $link; }
            $items[] = $row;
        }

        $debug = [
            'step'  => 'repo:loadZippedItemsByIdWithLinks',
            'block' => $blockId,
            'count' => count($items),
            'types' => $types,
        ];
        return [ 'items' => $items, 'block' => null, 'debug' => $debug ];
    }
}
