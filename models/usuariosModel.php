<?php
use Illuminate\Database\Capsule\Manager as Capsule;
class usuariosModel extends Model
    {
        protected $table = 'usuarios';
        protected $primaryKey ='cv_usuario';
        public $timestamps = false;
        public function __construct() {
            parent::__construct();
        }
               
        public function verificar_usuario($email,$password)
        {
            $data = $this->whereRaw('email = ? and password = ?', [$email,$password])->get()->toArray();
            return $data;
        }
    }
?>