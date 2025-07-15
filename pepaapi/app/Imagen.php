<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Imagen extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesPersImgs';
    protected $primaryKey = 'cod_persona';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_persona','img_persona','img_documento',
    'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}