<?php


namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

class Empleado extends Model
{
    use Traits\LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $connection = 'mysql_asis';
    protected $table = 'maesEmpleados';
    protected $primaryKey = array('cod_empleado','cod_empresa');
    protected $casts = [
        'obj_dias_horarios' => 'array',
        'ind_activo' => 'boolean'
    ];
    public $incrementing = false;
    protected $fillable = array('cod_empleado', 'cod_empresa','cod_persona','nom_persona','ape_persona','cod_sexo','cod_tipo_doc',
        'nro_documento','nro_documento_ant','email','obj_dias_horarios','ind_activo');
}