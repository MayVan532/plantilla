<?php
class userController extends Controller
{

	public function __construct()
	{
		parent::__construct();
		if (!Session::get('autenticado')) {
			$this->redireccionar('');
		}

		if (Session::get('tipo_usuario') != USERFINAL) {
			$this->redireccionar('');
		}
	}
	public function index() {}
}
