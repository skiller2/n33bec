<?php


namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

class Registro extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $connection = 'mysql_asis';
    protected $table = 'moviRegistro';
    protected $primaryKey = array('cod_empleado','cod_empresa','fec_registro');
    protected $casts = [
        'json_detalle_ingreso' => 'array'
    ];
    
    public $incrementing = false;
    protected $fillable = array('cod_empleado','cod_empresa','fec_registro','tipo_novedad','ind_feriado','ind_tarde','ind_dia_laborable',
        'hora_ingreso_esperado','hora_egreso_esperado','hora_ingreso','hora_egreso','json_detalle_ingreso',
        'ind_modif_horarios','ind_modif_novedad','tm_trabajado','tm_tardanza','tm_extra');
}