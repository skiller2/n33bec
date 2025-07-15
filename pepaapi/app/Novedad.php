<?php


namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

class Novedad extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $connection = 'mysql_asis';
    protected $table = 'moviNovedades';
    protected $primaryKey = array('cod_empleado','cod_empresa','tipo_novedad','fec_novedad_desde');
    
    public $incrementing = false;
    protected $fillable = array('cod_empleado','cod_empresa','tipo_novedad','fec_novedad_desde','fec_novedad_hasta','des_novedad');
}