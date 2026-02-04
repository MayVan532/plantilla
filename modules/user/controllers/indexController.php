<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class indexController extends userController
{

	private $_usuarios;

	public function __construct()
	{
		parent::__construct();
		if (!Session::get('autenticado')) {
			$this->redireccionar('');
		}
		$this->_usuarios = $this->loadModel('usuarios');
	}


	public function index()
	{
		$core = new CORE;

		$vistas = array('index');
		$this->_view->setJs(array('index'));
		$this->_view->renderizar($vistas);
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
