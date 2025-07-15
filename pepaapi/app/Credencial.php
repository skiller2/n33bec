<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Credencial extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesAliasCred';
    protected $primaryKey = 'cod_credencial';    
    public $incrementing = false;
    protected $fillable = array('cod_credencial', 'ref_credencial','tipo_credencial');
}