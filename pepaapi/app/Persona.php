<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesPersonas';
    protected $primaryKey = 'cod_persona';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $casts = [
        'ind_bloqueo' => 'boolean'
    ];
    
    protected $fillable = array('nom_persona', 'ape_persona','cod_sexo','cod_tipo_doc','nro_documento','email','ind_bloqueo',
                                'des_motivo_bloqueo','img_persona','obs_visitas');
}