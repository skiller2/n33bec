<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

class MoviUltSuceso extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviUltSuceso';
    protected $primaryKey = array('cod_tema');

    protected $casts = [
        'json_detalle' => 'array'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_tema', 'stm_ult_suceso', 'json_detalle', 'ind_alarma', 'cant_activacion', 
        'ind_prealarma', 'ind_falla', 'ind_alarmatec','stm_reseteo', 'des_observaciones');
}
