<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App;

use App\Traits\LibGeneral;
use Illuminate\Database\Eloquent\Model;

/**
 * Description of UnidadesOrganiz
 *
 * @author fpl
 */
class ConfGrupoCred extends Model
{
    use LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'confGrupoCred';
    protected $primaryKey = 'cod_grupo';
    public $incrementing = false;
    protected $fillable = array('cod_grupo', 'des_grupo', 'cant_max_ingresos');
}
