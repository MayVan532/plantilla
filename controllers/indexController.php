<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class indexController extends Controller
{
	private $_usuarios;

	public function __construct()
	{
		parent::__construct();

		$this->_usuarios = $this->loadModel('usuarios');
	}

	public function index()
	{
		// Mostrar la vista de Personas > inicio como página principal
		$this->_view->renderizar(array('@personas/inicio'));
	}

	public function cerrar()
	{
		Session::destroy();
		$this->redireccionar("login");
	}

	public function test_bd()
	{
		$sims = $this->_usuarios
			->select("*")
			->limit(5)
			->get()->toArray();

		echo json_encode($sims);
	}
}
