<?php
class CORE
{

  public function head()
  {
    $data =
      <<<EOF
        \$cv_usuario=Session::get('cv_usuario');
        \$tipo_usuario=Session::get('tipo_usuario');
        \$info_cuenta=\$this->_usuarios->find(\$cv_usuario);
        \$data=\$this->_usuarios->where("cv_usuario","=",\$cv_usuario)->get()->toArray();
        \$this->_view->nombre_usuario=\$data[0]['nombre_usuario'];										
      EOF;
    return $data;
  }


  //imprimir json error....
  public function jsonError($tipo, $texto)
  {
    $respuesta = array('alert' => $tipo, 'mensaje' => $texto);
    echo json_encode($respuesta);
    exit;
  }


  public function jsonError2($clave, $tipo, $texto)
  {
    $respuesta = array('clave' => $clave, 'alert' => $tipo, 'mensaje' => $texto);
    echo json_encode($respuesta);
    exit;
  }


  //funciones para archivos....
  public function sizeFile($bytes)
  {
    $bytes = floatval($bytes);
    $arBytes = array(
      0 => array(
        "UNIT" => "TB",
        "VALUE" => pow(1024, 4)
      ),
      1 => array(
        "UNIT" => "GB",
        "VALUE" => pow(1024, 3)
      ),
      2 => array(
        "UNIT" => "MB",
        "VALUE" => pow(1024, 2)
      ),
      3 => array(
        "UNIT" => "KB",
        "VALUE" => 1024
      ),
      4 => array(
        "UNIT" => "B",
        "VALUE" => 1
      ),
    );

    foreach ($arBytes as $arItem) {
      if ($bytes >= $arItem["VALUE"]) {
        $result = $bytes / $arItem["VALUE"];
        $result = str_replace(".", ",", strval(round($result, 2))) . " " . $arItem["UNIT"];
        break;
      }
    }
    return $result;
  }


  public function downloadFile($fullPath)
  {
    // 
    if (headers_sent())
      die('Headers Sent');

    // rquiere oara algunos navegadores
    if (ini_get('zlib.output_compression'))
      ini_set('zlib.output_compression', 'Off');

    // existe fichero? 
    if (file_exists($fullPath)) {

      // Parsear informacion / obtener extencion
      $fsize = filesize($fullPath);
      $path_parts = pathinfo($fullPath);
      $ext = strtolower($path_parts["extension"]);

      // determinar el tipo de contenido
      switch ($ext) {
        case "pdf":
          $ctype = "application/pdf";
          break;
        case "exe":
          $ctype = "application/octet-stream";
          break;
        case "zip":
          $ctype = "application/zip";
          break;
        case "doc":
          $ctype = "application/msword";
          break;
        case "xls":
          $ctype = "application/vnd.ms-excel";
          break;
        case "ppt":
          $ctype = "application/vnd.ms-powerpoint";
          break;
        case "gif":
          $ctype = "image/gif";
          break;
        case "png":
          $ctype = "image/png";
          break;
        case "jpeg":
        case "jpg":
          $ctype = "image/jpg";
          break;
        default:
          $ctype = "application/force-download";
      }

      header("Pragma: public"); // requiere
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private", false); //
      header("Content-Type: $ctype");
      header("Content-Disposition: attachment; filename=\"" . basename($fullPath) . "\";");
      header("Content-Transfer-Encoding: binary");
      header("Content-Length: " . $fsize);
      ob_clean();
      flush();
      readfile($fullPath);
    } else
      die('File Not Found');
  }


  public function downloadFile_nombre($fullPath, $nombre)
  {
    // 
    if (headers_sent())
      die('Headers Sent');

    // rquiere oara algunos navegadores
    if (ini_get('zlib.output_compression'))
      ini_set('zlib.output_compression', 'Off');

    // existe fichero? 
    if (file_exists($fullPath)) {

      // Parsear informacion / obtener extencion
      $fsize = filesize($fullPath);
      $path_parts = pathinfo($fullPath);
      $ext = strtolower($path_parts["extension"]);

      // determinar el tipo de contenido
      switch ($ext) {
        case "pdf":
          $ctype = "application/pdf";
          break;
        case "exe":
          $ctype = "application/octet-stream";
          break;
        case "zip":
          $ctype = "application/zip";
          break;
        case "doc":
          $ctype = "application/msword";
          break;
        case "xls":
          $ctype = "application/vnd.ms-excel";
          break;
        case "ppt":
          $ctype = "application/vnd.ms-powerpoint";
          break;
        case "gif":
          $ctype = "image/gif";
          break;
        case "png":
          $ctype = "image/png";
          break;
        case "jpeg":
        case "jpg":
          $ctype = "image/jpg";
          break;
        default:
          $ctype = "application/force-download";
      }

      header("Pragma: public"); // requiere
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private", false); //
      header("Content-Type: $ctype");
      header("Content-Disposition: attachment; filename=\"" . basename($nombre) . "\";");
      header("Content-Transfer-Encoding: binary");
      header("Content-Length: " . $fsize);
      ob_clean();
      flush();
      readfile($fullPath);
    } else
      die('File Not Found');
  }


  //operaciones extra
  /*
  * Generar una cadena aleatoria
  */
  function cadena_aleatoria($l, $c = 'abcdefghijklmnopqrstuvwxyz1234567890')
  {
    for ($s = '', $cl = strlen($c) - 1, $i = 0; $i < $l; $s .= $c[mt_rand(0, $cl)], ++$i);
    $fecha = new DateTime();
    $fecha_format = $fecha->format('Y-m-d H:i:s');
    $semilla = md5($fecha_format);
    $s = $s . '' . $semilla;
    return $s;
  }


  //validar cadena
  function val_comentario($value)
  {
    $value = trim($value);
    if (get_magic_quotes_gpc()) {
      $value = stripslashes($value);
    }
    $value = strtr($value, array_flip(get_html_translation_table(HTML_ENTITIES)));
    $value = strip_tags($value);
    $value = htmlspecialchars($value);
    return $value;
  }


  function ordernarArray($ArrayaOrdenar, $por_este_campo, $descendiente = false)
  {
    $posicion = array();
    $NuevaFila = array();
    foreach ($ArrayaOrdenar as $clave => $fila) {
      $posicion[$clave] = $fila[$por_este_campo];
      $NuevaFila[$clave] = $fila;
    }
    if ($descendiente) {
      arsort($posicion);
    } else {
      asort($posicion);
    }
    $ArrayOrdenado = array();
    foreach ($posicion as $clave => $pos) {
      $ArrayOrdenado[] = $NuevaFila[$clave];
    }
    return $ArrayOrdenado;
  }


  /*
 * validar URL que sea de una imagen retornar FALSE sino es
 */
  function val_url_img($url)
  {
    $res = false;
    if (preg_match("#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", $url)) {
      $formatos = array("jpg", "gif", "png", "bmp", "jpeg");
      $regex_formato = "#^.+\.(" . implode('|', $formatos) . ")$#";
      if (preg_match($regex_formato, $url)) {
        $res = true;
      }
    }
    if (preg_match("#(^|[\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", $url)) {
      $formatos = array("jpg", "gif", "png", "bmp", "jpeg");
      $regex_formato = "#^.+\.(" . implode('|', $formatos) . ")$#";
      if (preg_match($regex_formato, $url)) {
        $res = true;
      }
    }
    return $res;
  }


  /*
 * validar una fecha 
 */


  function val_fecha($fecha)
  {
    $res = false;
    if (preg_match("/^((((19|20)(([02468][048])|([13579][26]))-02-29))|((20[0-9][0-9])|(19[0-9][0-9]))-((((0[1-9])|(1[0-2]))-((0[1-9])|(1\d)|(2[0-8])))|((((0[13578])|(1[02]))-31)|(((0[1,3-9])|(1[0-2]))-(29|30)))))$/", $fecha) === 1) {
      $res = true;
    }
    return $res;
  }


  /*
  * validar un email
  */
  public function valida_email($x)
  {
    $estado = false;
    if (filter_var($x, FILTER_VALIDATE_EMAIL)) {
      $estado = true;
    }
    return $estado;
  }


  /*
   * validar una cadena de entrada
   */
  function val_cadena($value)
  {
    $value = trim($value);
    if (get_magic_quotes_gpc()) {
      $value = stripslashes($value);
    }
    $value = strtr($value, array_flip(get_html_translation_table(HTML_ENTITIES)));
    $value = strip_tags($value);
    $value = htmlspecialchars($value);
    return $value;
  }


  /*
    * validar una url
    */
  public function valUrl($url)
  {

    if (preg_match("#(^|[\n ])([\w]+?://[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", $url)) {
      return true;
    }

    if (preg_match("#(^|[\n ])((www|ftp)\.[\w\#$%&~/.\-;:=,?@\[\]+]*)#is", $url)) {
      return true;
    }
    return false;
  }


  function archivo_icon($tipo)
  {
    $icon = '';
    if ($tipo == "jpg" || $tipo == "jpe" || $tipo == "jpeg" || $tipo == "png" || $tipo == "gif" || $tipo == "bmp") {
      $icon = "fa fa-file-image-o";
    }
    if ($tipo == "mp3") {
      $icon = "fa fa-file-audio-o";
    }
    if ($tipo == "mp4" || $tipo == "flv") {
      $icon = "fa fa-file-movie-o";
    }
    if ($tipo == "doc" || $tipo == "docx") {
      $icon = "fa fa-file-word-o";
    }
    if ($tipo == "ppt" || $tipo == "pptx") {
      $icon = "fa fa-file-powerpoint-o";
    }
    if ($tipo == "xls" || $tipo == "xlsx") {
      $icon = "fa fa-file-excel-o";
    }
    if ($tipo == "pdf") {
      $icon = "fa fa-file-pdf-o";
    }
    if ($tipo == "exe") {
      $icon = "fa fa-file-code-o";
    }
    if ($tipo == "rar" || $tipo == "zip") {
      $icon = "fa fa-file-archive-o";
    }
    if ($icon == 'txt') {
      $icon = 'fa fa-file-text-o';
    }

    return $icon;
  }


  public function fecha($fecha)
  {
    $ago = "";
    $date1 = new DateTime($fechaxd);
    $date2 = new DateTime("now");

    $interval = $date1->diff($date2);
    $years = $interval->format('%y');
    $months = $interval->format('%m');
    $days = $interval->format('%d');
    $horas = $interval->format('%h');
    $minutos = $interval->format('%i');
    $segundos = $interval->format('%s');
    if ($years > 0) {
      $ago = ($years . ' a&ntilde;o(s)');
    }

    if ($months > 0 && $years == 0) {
      $ago = ($months . ' mese(s)');
    }
    if ($days > 0 && $months == 0) {
      $ago = ($days . ' dia(s)');
    }
    if ($horas > 0 && $days == 0 && $months == 0) {
      $ago = ($horas . ' hora(s)');
    }
    if ($minutos > 0 && $horas == 0 && $days == 0 && $months == 0) {
      $ago = ($minutos . ' minuto(s)');
    }
    if ($segundos > 0 && $minutos == 0 && $horas == 0 && $days == 0 && $months == 0) {
      $ago = ($segundos . ' segundo(s)');
    }
    return $ago;
  }


  public function FormatPrice($price)
  {
    $price = preg_replace("/[^0-9\.]/", "", str_replace(',', '.', $price));
    if (substr($price, -3, 1) == '.') {
      $sents = '.' . substr($price, -2);
      $price = substr($price, 0, strlen($price) - 3);
    } elseif (substr($price, -2, 1) == '.') {
      $sents = '.' . substr($price, -1);
      $price = substr($price, 0, strlen($price) - 2);
    } else {
      $sents = '.00';
    }
    $price = preg_replace("/[^0-9]/", "", $price);
    return number_format($price . $sents, 2, '.', '');
  }


  public function convertir_moneda($moneda)
  {
    setlocale(LC_MONETARY, 'es_MX');
    /*$moneda=str_replace("MXN", "",money_format('%i',$moneda));
	
	 $moneda=money_format('%i',$moneda);
     $moneda=str_replace("MXN", "", $moneda);*/
    $moneda = number_format($moneda, 2, '.', '');
    return $moneda;
  }


  public function limpiar_html_recortar($cadena)
  {
    $cadena = htmlspecialchars_decode($cadena);
    $cadena = strip_tags($cadena);
    $cadena = substr($cadena, 0, 200);
    return $cadena;
  }


  public function fecha_diagonal($fecha)
  {
    setlocale(LC_TIME, 'es_MX.UTF-8');
    $fecha1 = $fecha;
    $an_1 = date("Y", strtotime($fecha1));
    $me_1 = date("n", strtotime($fecha1));
    $di_1 = date("j", strtotime($fecha1));
    $miFecha1 = gmmktime(12, 0, 0, $me_1, $di_1, $an_1);
    $fecha1 = strftime("%d/%m/%Y", $miFecha1);

    return $fecha1;
  }


  function getRealIP()
  {

    if (isset($_SERVER["HTTP_CLIENT_IP"])) {

      return $_SERVER["HTTP_CLIENT_IP"];
    } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {

      return $_SERVER["HTTP_X_FORWARDED_FOR"];
    } elseif (isset($_SERVER["HTTP_X_FORWARDED"])) {

      return $_SERVER["HTTP_X_FORWARDED"];
    } elseif (isset($_SERVER["HTTP_FORWARDED_FOR"])) {

      return $_SERVER["HTTP_FORWARDED_FOR"];
    } elseif (isset($_SERVER["HTTP_FORWARDED"])) {

      return $_SERVER["HTTP_FORWARDED"];
    } else {

      return $_SERVER["REMOTE_ADDR"];
    }
  }
}
