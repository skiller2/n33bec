<?php


namespace App;

use Illuminate\Database\Eloquent\Model;

class TipoNovedad extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $connection = 'mysql_asis';
    protected $table = 'confTipoNovedad';
    protected $primaryKey = 'tipo_novedad';
    public $incrementing = false;
    protected $fillable = array('tipo_novedad', 'nom_novedad', 'ind_tipo_novedad');
}