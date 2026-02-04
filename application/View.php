<?php
// clase para manejar las vistas
class View
{
    private $_request;
    private $_js;
    private $_rutas;

    public function __construct(Request $peticion)
    {
        $this->_request = $peticion;
        $this->_js = array();
        $this->_rutas = array();

        $modulo = $this->_request->getModulo();
        $controlador = $this->_request->getControlador();

        if ($modulo) {
            $this->_rutas['view'] = ROOT . 'modules' . DS . $modulo . DS . 'views' . DS . $controlador . DS;
            $this->_rutas['js'] = BASE_URL . 'modules/' . $modulo . '/views/' . $controlador . '/js/';
            $this->_rutas['ruta_general'] = 'modules/' . $modulo . '/';
        } else {
            $this->_rutas['view'] = ROOT . 'views' . DS . $controlador . DS;
            $this->_rutas['js'] = BASE_URL . 'views/' . $controlador . '/js/';
            $this->_rutas['ruta_general'] = '';
        }
    }

    // comprobar si se puede leer una direccion de vista
    public function disponibleview($vista)
    {
        // Soporta rutas absolutas con '@' (por ejemplo: @personas/inicio)
        if (preg_match("/^@/", $vista)) {
            $rutax = str_replace("@", "", $vista);
            return is_readable(ROOT . 'views' . DS . $rutax . '.phtml');
        } else {
            return is_readable($this->_rutas['view'] . $vista . '.phtml');
        }
    }

    // metodo que incluye la vista
    public function renderizar(array $vista, $item = false)
    {
        $js = array();
        if (count($this->_js)) {
            $js = $this->_js;
        }

        // Layout único (header)
        if ($item !== 'ajax') {
            include_once ROOT . 'views' . DS . 'layout' . DS . DEFAULT_LAYOUT . DS . 'header.php';
        }

        // incluir vistas dentro de un contenedor visible (full width, sin separación bajo el menú)
        echo "<main id=\"app-content\" class=\"container-fluid\" style=\"min-height:40vh; padding:0; position:relative; z-index:1; margin-top:0;\">";
        if (is_array($vista) && count($vista)) {
            for ($i = 0; $i < count($vista); $i++) {
                $isAbs = preg_match("/^@/", $vista[$i]);
                $path = $isAbs
                    ? ROOT . 'views' . DS . str_replace("@", "", $vista[$i]) . '.phtml'
                    : $this->_rutas['view'] . $vista[$i] . '.phtml';

                // Contenedor sin restricciones: cada vista controla su propio layout
                echo '<div class="page-section" style="padding:0; margin:0; position:relative; z-index:1;">';
                if (is_readable($path)) {
                    include $path;
                } else {
                    echo '<div class="alert alert-warning">No se encontró la vista: ' . htmlspecialchars($path) . '</div>';
                }
                echo '</div>';
            }
        }
        echo "</main>";

        // Layout único (footer)
        if ($item !== 'ajax') {
            include_once ROOT . 'views' . DS . 'layout' . DS . DEFAULT_LAYOUT . DS . 'footer.php';
        }
    }

    // agregar js de otras vistas
    public function setJs(array $js)
    {
        if (is_array($js) && count($js)) {
            for ($i = 0; $i < count($js); $i++) {
                if (preg_match("/^@/", $js[$i])) {
                    $rutaxjs = str_replace("@", "", $js[$i]);
                    $this->_js[] = BASE_URL . '' . $rutaxjs . '.js';
                } else {
                    $this->_js[] = $this->_rutas['js'] . $js[$i] . '.js';
                }
            }
        } else {
            throw new Exception("Error de js");
        }
    }

    // funcion para cargar widget basico
    public function widget($widget, $method, $options = array())
    {
        if (!is_array($options)) {
            $options = array($options);
        }

        if (is_readable(ROOT . 'widgets' . DS . $widget . '.php')) {
            include_once ROOT . 'widgets' . DS . $widget . '.php';

            $widgetClass = $widget . 'Widget';

            if (!class_exists($widgetClass)) {
                throw new Exception('Error clase widget');
            }

            if (is_callable($widgetClass, $method)) {
                if (count($options)) {
                    return call_user_func_array(array(new $widgetClass, $method), $options);
                } else {
                    return call_user_func(array(new $widgetClass, $method));
                }
            }

            throw new Exception('Error metodo widget');
        }

        throw new Exception('Error de widget');
    }
}
?>