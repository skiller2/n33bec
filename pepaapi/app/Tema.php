<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tema extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesTemas';
    protected $primaryKey = 'cod_tema';
    public $incrementing = false;
    protected $casts = [
        'json_parametros' => 'array',
        'json_subtemas' => 'array',
        'json_posicion_img' => 'array',
        'ind_mostrar_en_panel' => 'boolean',
        'ind_registra_evento' => 'boolean',
        'ind_display_evento' => 'boolean',
        'ind_notifica_evento' => 'boolean',
        'ind_activo' => 'boolean'
    ];
    protected $fillable = array('cod_tema', 'nom_tema', 'cod_sector', 'des_ubicacion', 'cod_tipo_uso','cod_clase', 
        'json_parametros', 'json_posicion_img', 'json_subtemas', 'ind_mostrar_en_panel', 'ind_registra_evento','ind_display_evento','ind_notifica_evento',  'ind_activo',
        'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}