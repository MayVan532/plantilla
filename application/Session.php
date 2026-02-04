<?php
class Session
{
    public static function init()
    {
        //iniciar sesion 
        session_set_cookie_params(0, '/', 'localhost');
        session_name('web_session');
        session_start();
    }

    
    //metodo para destruir una variable de sesion
    public static function destroy($clave = false)
    {
        if ($clave) {
            if (is_array($clave)) {
                for ($i = 0; i < count($clave); $i++) {
                    //si existe variable de sesion 
                    if (isset($_SESSION[$clave[$i]])) {
                        unset($_SESSION[$clave[$i]]);
                    }
                }
            }
            //sino es un arreglo
            else {
                if (isset($_SESSION[$clave])) {
                    unset($_SESSION[$clave]);
                }
            }
        } else {
            session_destroy();
        }
    }
    //asignar valor a una clave
    public static function set($clave, $valor)
    {
        if (!empty($clave)) {
            $_SESSION[$clave] = $valor;
        }
    }
    //obtener valor de la sesion
    public static function get($clave)
    {
        if (isset($_SESSION[$clave])) {
            return $_SESSION[$clave];
        }
    }


    public static function acceso($level)
    {
        if (!Session::get('autenticado')) {
            header('location:' . BASE_URL . 'error/access/5050');
            exit;
        }

        if (Session::getLevel($level) > Session::getLevel(Session::get('level'))) {
            header('location:' . BASE_URL . 'error/access/5050');
            exit;
        }
    }

    public static function accesoView($level)
    {
        if (!Session::get('autenticado')) {
            return false;
        }

        if (Session::getLevel($level) > Session::getLevel(Session::get('level'))) {
            return false;
        }

        return true;
    }
    //niveles de acceso roles
    public static function getLevel($level)
    {
        $role['admin'] = 3;
        $role['especial'] = 2;
        $role['usuario'] = 1;

        if (!array_key_exists($level, $role)) {
            throw new Exception('Error de acceso');
        } else {
            return $role[$level];
        }
    }

    public static function accesoEstricto(array $level, $noAdmin = false)
    {
        if (!Session::get('autenticado')) {
            header('location:' . BASE_URL . 'error/access/5050');
            exit;
        }

        Session::tiempo();

        if ($noAdmin == false) {
            if (Session::get('level') == 'admin') {
                return;
            }
        }

        if (count($level)) {
            if (in_array(Session::get('level'), $level)) {
                return;
            }
        }

        header('location:' . BASE_URL . 'error/access/5050');
    }


    public static function accesoViewEstricto(array $level, $noAdmin = false)
    {
        if (!Session::get('autenticado')) {
            return false;
        }

        if ($noAdmin == false) {
            if (Session::get('level') == 'admin') {
                return true;
            }
        }

        if (count($level)) {
            if (in_array(Session::get('level'), $level)) {
                return true;
            }
        }

        return false;
    }

    public static function tiempo()
    {
        if (!Session::get('tiempo') || !defined('SESSION_TIME')) {
            throw new Exception('No se ha definido el tiempo de sesion');
        }
        //quitar si es tiempo indefinido
        if (SESSION_TIME == 0) {
            return;
        }

        if (time() - Session::get('tiempo') > (SESSION_TIME * 60)) {
            Session::destroy();
            header('location:' . BASE_URL . 'error/access/8080');
        } else {
            Session::set('tiempo', time());
        }
    }
}
