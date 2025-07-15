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
class UnidadesOrganiz extends Model
{
    use LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesUnidadesOrganiz';
    protected $primaryKey = 'cod_ou';
    public $incrementing = false;
    protected $fillable = array('cod_ou', 'nom_ou', 'des_ou', 'ind_ou_admin', 'centro_emergencias','tel_centro_emergencias',
        'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}
