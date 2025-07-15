<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;


class MoviTemaNoRegis extends Model
{
    use LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviTemasNoRegis';
    protected $primaryKey = 'cod_tema';
    protected $casts = [
//        'json_detalle' => 'array'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_tema','nom_tema','valor','stm_ultimo_reporte','des_observaciones');
}
