<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;
use Carbon\Carbon;

class MoviCredSector extends Model
{
    use LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviCredSector';
    protected $primaryKey = array('cod_credencial','cod_sector');
    protected $appends = ['tiempo_permanencia'];

    public $incrementing = false;

    public function getTiempoPermanenciaAttribute() {
        $to = Carbon::now();
        $from = Carbon::createFromFormat('Y-m-d H:s:i', $this->stm_ingreso);
        $diff = str_pad($to->diffInHours($from),2,"0",STR_PAD_LEFT).":".  $to->diff($from)->format('%I');;
        return $diff;
    }

    protected $fillable = array('cod_credencial','cod_sector','stm_ingreso','aud_usuario_ingreso','aud_stm_ingreso','aud_ip_ingreso','tiempo_permanencia');
}
