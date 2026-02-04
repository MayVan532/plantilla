<?php
/**
 * solo exista un objeto del registro
 */
class Registry{
	private static $_instancea;
	private $_data;
	//asegura de que no se pueda crear un instancea de la clase
	//solo se puede instancear desde dentro de la clase
	private function __construct() {
	}
	//singleton
	public static function getInstancia(){
     //si no esta registrada crea una nueva instancea del registro sino solo retorna
	  if(!self::$_instancea instanceof self){
	  	self::$_instancea= new Registry();
	  }	
	  return self::$_instancea;
	}
	/*
	 * la forma mas facil es hacer un arreglo de objetos de las clases compartidas
	 */
	//metodos magicos
	public function __set($name,$value){
		$this->_data[$name]=$value;
	}
	
	//set si es una variable vacia y no es null isset== y esta definida
	public function __get($name){
		if(isset($this->_data[$name])){
			return $this->_data[$name];
		}
		
		return false;
	}
}

?>