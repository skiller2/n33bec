<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AptoFisico extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesPersAptoF';
    protected $primaryKey = 'cod_persona';
    public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_persona', 'img_apto_fisico', 'fec_otorgamiento_af','fec_vencimiento_af',
    'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}