<?php

namespace App;

use App\Traits\LibGeneral;
use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Esquema extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'confEsquemaAcceso';
    protected $primaryKey = array('cod_esquema_acceso','cod_ou');
    
    protected $casts = [
        'obj_intervalos_habiles' => 'array',
        'obj_intervalos_nohabiles' => 'array',
        'obj_intervalos_mixtos' => 'array',
        'ind_estado' => 'boolean'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_esquema_acceso', 'cod_ou', 'des_esquema_acceso', 'obj_intervalos_mixtos', 'obj_intervalos_habiles', 
        'obj_intervalos_nohabiles', 'ind_estado', 'fec_habilitacion_hasta', 'aud_stm_ingreso', 'aud_usuario_ingreso', 'aud_ip_ingreso', 
        'aud_stm_ultmod', 'aud_ip_ultmod', 'aud_usuario_ultmod');
}
