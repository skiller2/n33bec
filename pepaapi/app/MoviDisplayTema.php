<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

class MoviDisplayTema extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviDisplayTemas';
    protected $primaryKey = array('cod_tema', 'tipo_evento');
    protected $casts = [
//        'json_detalle' => 'array'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_tema','tipo_evento','valor','stm_evento','des_observaciones');
}
