<?php

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;

class MoviUltReporte extends Model
{
    use LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'moviUltReporte';
    protected $primaryKey = 'id_disp_origen';
    
    public $incrementing = false;
    protected $fillable = array('des_observaciones');
}
