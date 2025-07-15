<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImagenSector extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesSectImgs';
    protected $primaryKey = 'cod_sector';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_sector','blb_imagen',
    'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}