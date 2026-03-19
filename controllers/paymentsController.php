<?php
class paymentsController extends Controller
{
  public function index() { $this->redireccionar(); }

  private function apiAuthHeader(): string {
    try {
      if (!class_exists('TokenApiExterno')) {
        $lib = ROOT . 'libs' . DS . 'class.TokenApiExterno.php';
        if (is_readable($lib)) { require_once $lib; }
      }
      return TokenApiExterno::obtenerAuthorizationHeader();
    } catch (\Throwable $e) {
      return '';
    }
  }

  private function logDebugCheckout(string $type, array $entry): void {
    try {
      $dir = defined('ROOT') ? (rtrim(ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tmp') : sys_get_temp_dir();
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $file = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'checkout_debug.log';
      $line = date('Y-m-d H:i:s') . "\t" . $type . "\t" . json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
      @file_put_contents($file, $line, FILE_APPEND);
    } catch (\Throwable $e) { /* noop */ }
  }

  private function callCheckout(string $url, array $payload): array {
    $auth = $this->apiAuthHeader();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => array_values(array_filter([
        'Content-Type: application/json',
        'Accept: application/json',
        $auth !== '' ? ('Authorization: '.$auth) : null,
      ])),
      CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT => 45,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $json = is_string($resp) ? json_decode($resp, true) : null;
    if (is_array($json)) {
      // Intento flexible para localizar la URL del iframe
      $keys = ['iframe_url','iframeUrl','iframe','url'];
      foreach ($keys as $k) {
        if (!empty($json[$k]) && is_string($json[$k])) {
          return ['ok'=>($http>=200&&$http<300), 'iframe_url'=>$json[$k], 'http'=>$http, 'raw'=>$json];
        }
      }
      return ['ok'=>($http>=200&&$http<300), 'error'=>$json['mensaje'] ?? 'No se recibió iframe_url', 'http'=>$http, 'raw'=>$json];
    }

    // Si no es JSON: si es 2xx y parece HTML embebible, devolver como data URL para iframe
    if ($http >= 200 && $http < 300 && is_string($resp)) {
      $snippet = strtolower(substr($resp, 0, 256));
      if (strpos($snippet, '<!doctype html') !== false || strpos($snippet, '<html') !== false) {
        $b64 = base64_encode($resp);
        $dataUrl = 'data:text/html;base64,' . $b64;
        return ['ok'=>true, 'iframe_url'=>$dataUrl, 'http'=>$http, 'raw_html'=>true, 'html'=>$resp];
      }
    }

    // Si no, devolver como error con html crudo para depurar
    return ['ok'=>false, 'error'=>$err ?: 'Respuesta no JSON', 'http'=>$http, 'html'=>$resp];
  }

  public function crearCheckoutRecarga()
  {
    header('Content-Type: application/json; charset=UTF-8');
    $cv_plan     = (string)($this->getPostParam('cv_plan') ?? '');
    $numero_data = preg_replace('/[^0-9]/','', (string)($this->getPostParam('numero_data') ?? ''));
    $email       = (string)($this->getPostParam('email') ?? '');
    $debugFlag   = ((string)($this->getPostParam('debugpay') ?? '')) === '1' || (isset($_GET['debugpay']) && $_GET['debugpay'] === '1');

    if ($cv_plan === '' || $numero_data === '') {
      echo json_encode(['success'=>false,'message'=>'Faltan datos requeridos (cv_plan, numero_data).']);
      exit;
    }
    if ($email !== '' && !$this->validarEmail($email)) {
      echo json_encode(['success'=>false,'message'=>'Email inválido.']);
      exit;
    }

    // Nota: cooldown del lado servidor deshabilitado por solicitud del usuario

    $payload = [
      'cv_plan'     => $cv_plan,
      'numero_data' => $numero_data,
    ];
    if ($email !== '') { $payload['email'] = $email; }

    $url = 'https://apis.likephone.mx/api/v1/whitelabels/generic/payments/crearcheckoutrecargaconekta';
    if ($debugFlag) { $this->logDebugCheckout('recarga:request', ['url'=>$url, 'payload'=>$payload]); }
    $res = $this->callCheckout($url, $payload);
    if ($debugFlag) { $this->logDebugCheckout('recarga:response', $res); }
    if (!empty($res['ok']) && !empty($res['iframe_url'])) {
      // Guardar contexto por si se necesita
      Session::set('pending_checkout', ['type'=>'recarga','payload'=>$payload,'when'=>time()]);
      $out = ['success'=>true,'iframe_url'=>$res['iframe_url']];
      if (!empty($res['html'])) { $out['html'] = $res['html']; }
      if ($debugFlag) { $out['debug'] = ['sent_payload'=>$payload,'endpoint'=>$url,'http'=>$res['http']??null,'raw'=>$res['raw']??null,'raw_html'=>!empty($res['raw_html']),'html'=>$res['html']??null]; }
      echo json_encode($out);
      exit;
    }
    $outErr = ['success'=>false,'message'=>$res['error'] ?? 'No se pudo crear el checkout'];
    if ($debugFlag) { $outErr['debug'] = ['sent_payload'=>$payload,'endpoint'=>$url,'res'=>$res]; }
    echo json_encode($outErr);
    exit;
  }

  public function crearCheckoutActivacion()
  {
    header('Content-Type: application/json; charset=UTF-8');
    $expected = ['nombre','apellido_paterno','apellido_materno','correo_electronico','genero_id','fecha_nacimiento','cv_plan','numero_telefono','codigo_postal','colonia','calle','numero_exterior','numero_interior','referencias','observaciones'];
    $payload = [];
    foreach ($expected as $k) { $payload[$k] = (string)($this->getPostParam($k) ?? ''); }
    $debugFlag   = ((string)($this->getPostParam('debugpay') ?? '')) === '1' || (isset($_GET['debugpay']) && $_GET['debugpay'] === '1');
    // Validaciones mínimas
    if ($payload['cv_plan'] === '' || $payload['numero_telefono'] === '' || $payload['correo_electronico'] === '') {
      echo json_encode(['success'=>false,'message'=>'Faltan datos requeridos (cv_plan, numero_telefono, correo_electronico).']);
      exit;
    }
    if (!$this->validarEmail($payload['correo_electronico'])) {
      echo json_encode(['success'=>false,'message'=>'Correo electrónico inválido.']);
      exit;
    }

    $url = 'https://apis.likephone.mx/api/v1/whitelabels/generic/payments/crearcheckoutactivacionconekta';
    if ($debugFlag) { $this->logDebugCheckout('activacion:request', ['url'=>$url, 'payload'=>$payload]); }
    $res = $this->callCheckout($url, $payload);
    if ($debugFlag) { $this->logDebugCheckout('activacion:response', $res); }
    if (!empty($res['ok']) && !empty($res['iframe_url'])) {
      Session::set('pending_checkout', ['type'=>'activacion','payload'=>$payload,'when'=>time()]);
      $out = ['success'=>true,'iframe_url'=>$res['iframe_url']];
      if (!empty($res['html'])) { $out['html'] = $res['html']; }
      if ($debugFlag) { $out['debug'] = ['sent_payload'=>$payload,'endpoint'=>$url,'http'=>$res['http']??null,'raw'=>$res['raw']??null,'raw_html'=>!empty($res['raw_html']),'html'=>$res['html']??null]; }
      echo json_encode($out);
      exit;
    }
    $outErr = ['success'=>false,'message'=>$res['error'] ?? 'No se pudo crear el checkout'];
    if ($debugFlag) { $outErr['debug'] = ['sent_payload'=>$payload,'endpoint'=>$url,'res'=>$res]; }
    echo json_encode($outErr);
    exit;
  }
}
