<?php


namespace App;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $connection = 'mysql_asis';
    protected $table = 'maesEmpresas';
    protected $primaryKey = 'cod_empresa';
    public $incrementing = false;
    protected $fillable = array('nom_empresa', 'des_empresa');
}