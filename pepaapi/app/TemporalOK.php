<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TemporalOK extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;
protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $table = 'moviTemporalesOK';
    protected $primaryKey = 'stm_movimiento';
    protected $dates = ['stm_movimiento'];
    public $keyType = 'VARCHAR';
    public $incrementing = false;
    protected $fillable = array('stm_movimiento', 'cod_credencial', 'tipo_credencial','cod_persona','nom_persona','ape_persona',
                                'nro_documento','cod_sector','cod_dispositivo','ind_movimiento');
}