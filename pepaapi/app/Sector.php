<?php


namespace App;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use Traits\LibGeneral;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'maesSectores';
    protected $primaryKey = 'cod_sector';
    public $incrementing = false;
    protected $casts = [
        'ind_permanencia' => 'boolean',
        'obj_urls_videos' => 'array',

    ];
    protected $fillable = array('cod_sector', 'cod_referencia', 'nom_sector', 'des_sector', 'des_abrev_sectores', 'des_ubicacion', 'ind_permanencia', 'max_cant_personas','obj_urls_videos',
        'aud_usuario_ingreso', 'aud_stm_ingreso', 'aud_ip_ingreso', 'aud_usuario_ultmod', 'aud_stm_ultmod', 'aud_ip_ultmod');
}