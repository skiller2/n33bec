<?php
/*
namespace App;

use Illuminate\Auth\Authenticatable; 
use Illuminate\Database\Eloquent\Model;
//use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

//class Usuario extends Authenticatable
class Usuario extends Model implements AuthenticatableContract 
*/


namespace App;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    use Notifiable;
    use Traits\LibGeneral;
//    use Authenticatable;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;
    
    protected $casts = [
        'obj_permisos' => 'array',
        'obj_ou' => 'array',
        'obj_sectores' => 'array',
        'obj_esquemas' => 'array',
        'ind_estado' => 'boolean',
        'ind_visita_simplificada' => 'boolean'
    ];

    protected $table = 'permUsuarios';
    protected $primaryKey = 'cod_usuario';
    public $incrementing = false;
    protected $fillable = array('cod_usuario','cod_persona', 'contrasena','obj_permisos','obj_ou',
                                'obj_sectores','obj_esquemas','sector_default','esquema_default','cod_tema_lector','ind_visita_simplificada','ind_estado');
    


    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
	 
	public function getJWTIdentifier() {
		return $this->cod_usuario;
	} 
	 
	 
	public function getJWTCustomClaims()
    {

         return ["cod_usuario" => $this->cod_usuario ];
    }	
	
	
}
