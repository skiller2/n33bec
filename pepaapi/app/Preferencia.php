<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Preferencia extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'confPreferencias';
    protected $primaryKey = 'cod_usuario';
    public $incrementing = false;
    protected $casts = [
        'obj_preferencias' => 'array'
    ];
    protected $fillable = array('obj_preferencias');
}