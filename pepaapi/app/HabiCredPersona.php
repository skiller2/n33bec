<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HabiCredPersona extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'habiCredPersona';
    protected $primaryKey = 'cod_credencial';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_persona','tipo_credencial','tipo_habilitacion','ind_movimiento','stm_habilitacion');
}