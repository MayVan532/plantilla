<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class TokenApiExterno
{
    /**
     * Endpoint que genera el token externo
     */
    // Mantener constante por compatibilidad, pero NO se usa. La URL se deriva SIEMPRE de WL_API_BASE_URL.
    const TOKEN_URL = '';

    /**
     * Credenciales del cliente
     * Puedes mover esto a Config.php si luego quieres centralizarlo
     */
    // Sin credenciales embedidas: SIEMPRE se leen de la BD (empresas_cms) según CMS_PAGE_ID
    const CLIENT_ID = '';
    const CLIENT_SECRET = '';

    /**
     * Nombre de las llaves en sesión
     */
    const SESSION_TOKEN_KEY = 'token_api_externo';
    const SESSION_TOKEN_TYPE_KEY = 'token_type';
    const SESSION_TOKEN_EXP_KEY = 'token_api_externo_exp';
    const SESSION_TOKEN_RAW_KEY = 'token_api_externo_raw';

    /**
     * Margen de seguridad en segundos antes de expirar
     * Si faltan <= 60 segundos, se renueva
     */
    const CLOCK_SKEW = 60;

    /**
     * Devuelve un token vigente.
     * Si no existe o ya expiró, lo renueva automáticamente.
     */
    public static function obtener()
    {
        $token = Session::get(self::SESSION_TOKEN_KEY);
        $exp   = (int) Session::get(self::SESSION_TOKEN_EXP_KEY);

        if (!$token || self::tokenExpirado($exp)) {
            self::renovar();
            $token = Session::get(self::SESSION_TOKEN_KEY);
        }

        return $token;
    }

    /**
     * Devuelve el tipo de token, normalmente Bearer
     */
    public static function obtenerTipo()
    {
        $tipo = Session::get(self::SESSION_TOKEN_TYPE_KEY);

        if (!$tipo) {
            self::obtener();
            $tipo = Session::get(self::SESSION_TOKEN_TYPE_KEY);
        }

        return $tipo ?: 'Bearer';
    }

    /**
     * Devuelve el header Authorization listo
     */
    public static function obtenerAuthorizationHeader()
    {
        return self::obtenerTipo() . ' ' . self::obtener();
    }

    /**
     * Fuerza renovación manual del token
     */
    public static function renovar()
    {
        $respuesta = self::solicitarTokenRemoto();

        if (
            !is_array($respuesta) ||
            empty($respuesta['success']) ||
            empty($respuesta['data']['token'])
        ) {
            throw new Exception('No se pudo obtener el token externo.');
        }

        $token     = trim((string)$respuesta['data']['token']);
        $tokenType = trim((string)($respuesta['data']['token_type'] ?? 'Bearer'));
        $exp       = self::obtenerExpDesdeJwt($token);

        if ($exp <= 0) {
            throw new Exception('No se pudo determinar la expiración del JWT externo.');
        }

        Session::set(self::SESSION_TOKEN_KEY, $token);
        Session::set(self::SESSION_TOKEN_TYPE_KEY, $tokenType);
        Session::set(self::SESSION_TOKEN_EXP_KEY, $exp);
        Session::set(self::SESSION_TOKEN_RAW_KEY, json_encode($respuesta, JSON_UNESCAPED_UNICODE));

        return $token;
    }

    /**
     * Limpia el token almacenado
     */
    public static function limpiar()
    {
        Session::set(self::SESSION_TOKEN_KEY, null);
        Session::set(self::SESSION_TOKEN_TYPE_KEY, null);
        Session::set(self::SESSION_TOKEN_EXP_KEY, null);
        Session::set(self::SESSION_TOKEN_RAW_KEY, null);
    }

    /**
     * Indica si el token ya expiró o está por expirar
     */
    private static function tokenExpirado($exp)
    {
        if (!$exp || !is_numeric($exp)) {
            return true;
        }

        return time() >= ((int)$exp - self::CLOCK_SKEW);
    }

    /**
     * Hace la petición real al endpoint remoto
     */
    private static function solicitarTokenRemoto()
    {
        if (!defined('WL_API_BASE_URL') || !is_string(WL_API_BASE_URL) || trim(WL_API_BASE_URL) === '') {
            throw new Exception('WL_API_BASE_URL no está definida en Config.php');
        }
        $creds = self::resolveClientCreds();
        $clientId = $creds['client_id'];
        $clientSecret = $creds['client_secret'];
        $tokenUrl = rtrim(WL_API_BASE_URL, '/').'/auth/token';

        $payload = [
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ];

        $ch = curl_init($tokenUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        curl_close($ch);

        if ($response === false || !empty($curlErr)) {
            throw new Exception('Error cURL al obtener token externo: ' . $curlErr);
        }

        $json = json_decode($response, true);

        if (!is_array($json)) {
            throw new Exception('La respuesta del token externo no es JSON válido.');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $mensaje = isset($json['mensaje']) ? $json['mensaje'] : 'Error HTTP al obtener token externo.';
            throw new Exception($mensaje . ' [HTTP ' . $httpCode . ']');
        }

        return $json;
    }

    private static function resolveClientCreds(): array
    {
        try {
            if (class_exists(Capsule::class) && defined('CMS_PAGE_ID') && (int)CMS_PAGE_ID > 0) {
                $row = Capsule::connection('cms')
                    ->table('pagina_cms as p')
                    ->leftJoin('empresas_cms as e', 'p.id_empresa', '=', 'e.id_empresa')
                    ->select(['e.client_id','e.client_secret'])
                    ->where('p.id_pagina', (int)CMS_PAGE_ID)
                    ->first();
                if ($row) {
                    $cid = is_string($row->client_id ?? null) ? trim($row->client_id) : '';
                    $csc = is_string($row->client_secret ?? null) ? trim($row->client_secret) : '';
                    if ($cid !== '' && $csc !== '') {
                        return ['client_id' => $cid, 'client_secret' => $csc];
                    }
                }
            }
        } catch (\Throwable $e) {}
        throw new Exception('Credenciales del cliente no configuradas en CMS (empresas_cms). Verifica pagina_cms.id_empresa y que existan client_id/client_secret.');
    }

    /**
     * Extrae el exp del payload del JWT sin validar firma,
     * solo para controlar expiración local.
     */
    private static function obtenerExpDesdeJwt($jwt)
    {
        if (!$jwt || substr_count($jwt, '.') < 2) {
            return 0;
        }

        $partes = explode('.', $jwt);
        if (!isset($partes[1])) {
            return 0;
        }

        $payload = self::base64UrlDecode($partes[1]);
        if (!$payload) {
            return 0;
        }

        $data = json_decode($payload, true);
        if (!is_array($data) || empty($data['exp'])) {
            return 0;
        }

        return (int)$data['exp'];
    }

    /**
     * Base64 URL Decode
     */
    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $data = strtr($data, '-_', '+/');
        return base64_decode($data);
    }


    //uso del lib
    // $tokenApi = new TokenApiExterno;
    // $authorization = $tokenApi->obtenerAuthorizationHeader();

    // $ch = curl_init('https://apis.likephone.mx/api/v1/alguna/ruta');
    // curl_setopt_array($ch, [
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_HTTPHEADER => [
    //         'Accept: application/json',
    //         'Authorization: ' . $authorization
    //     ]
    // ]);

    // $response = curl_exec($ch);
    // curl_close($ch);
}
