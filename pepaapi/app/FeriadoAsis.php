<?php


namespace App;

use Illuminate\Database\Eloquent\Model;

class FeriadoAsis extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $connection = 'mysql_asis';
    protected $table = 'maesFeriados';
    protected $primaryKey = 'fec_feriado';
    public $incrementing = false;
    protected $fillable = array('fec_feriado', 'des_feriado');
}