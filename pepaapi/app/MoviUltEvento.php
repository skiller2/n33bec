<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;
use phpDocumentor\Reflection\Types\Boolean;

class MoviUltEvento extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviUltEvento';
    protected $primaryKey = array('cod_tema', 'ind_modo_prueba');
    protected $casts = [
        'ind_modo_prueba' => 'boolean'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_tema','stm_evento','ind_modo_prueba', 'valor', 'des_valor', 'des_observaciones');
}
