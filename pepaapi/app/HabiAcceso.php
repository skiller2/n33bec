<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HabiAcceso extends Model {

    use Traits\LibGeneral;

    public $timestamps = false;
    protected $table = 'habiAcceso';
    protected $primaryKey = 'cod_credencial';
    public $incrementing = false;
    protected $casts = [
        'json_temas' => 'array',
        'json_esquema_acceso' => 'array'
    ];
    protected $fillable = array('cod_credencial', 'ref_credencial', 'cod_persona', 'nom_persona', 'ape_persona', 'cod_sexo', 'cod_tipo_doc', 
        'nro_documento', 'tipo_habilitacion', 'cod_ou_hab', 'nom_ou_hab', 'cod_persona_contacto', 'nom_persona_contacto', 'ape_persona_contacto', 
        'stm_habilitacion_hasta', 'obs_habilitacion', 'json_temas', 'cod_esquema_acceso', 'cod_grupo', 'cantidad_ingresos', 
        'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso');

}
