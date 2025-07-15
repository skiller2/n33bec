<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImagenTema extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesTemaImgs';
    protected $primaryKey = 'cod_tema';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_tema','img_tema','tipo_uso',
    'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}