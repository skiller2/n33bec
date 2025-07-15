<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

class HabiCredSectores extends Model
{
    use Traits\LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'habiCredSectores';
    protected $primaryKey = array('cod_credencial','cod_ou','cod_sector');
    //public $keyType = 'decimal';
    public $incrementing = false;
    protected $fillable = array('cod_esquema_acceso', 'stm_habilitacion_desde','stm_habilitacion_hasta');
}