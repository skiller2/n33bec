<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;
use phpDocumentor\Reflection\Types\Boolean;

class MoviEvento extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviEventos';
    protected $primaryKey = array('cod_tema', 'stm_evento');
    protected $casts = [
        'json_detalle' => 'array',
        'ind_modo_prueba' => 'boolean'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_tema','stm_evento','ind_modo_prueba', 'json_detalle', 'valor','des_valor','valor_analogico','des_unidad_medida','des_observaciones');
}
