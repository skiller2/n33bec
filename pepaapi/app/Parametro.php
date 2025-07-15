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
 * Description of Parametro
 *
 * @author fpl
 */
class Parametro extends Model
{
    use LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'confParametros';
    protected $primaryKey = 'den_parametro';
    public $incrementing = false;
    protected $fillable = array('val_parametro', 'des_parametro');

}
