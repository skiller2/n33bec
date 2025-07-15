<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HabiCredGrupo extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'habiCredGrupo';
    protected $primaryKey = 'cod_credencial';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_grupo');
}