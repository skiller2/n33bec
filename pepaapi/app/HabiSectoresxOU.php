<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCompositePrimaryKey;

/**
 * Description of HabiSectoresxCred
 *
 * @author fpl
 */
class HabiSectoresxOU extends Model
{
    use Traits\LibGeneral;
    use HasCompositePrimaryKey;
    //const CREATED_AT = 'aud_stm_ingreso';
    //const UPDATED_AT = 'aud_stm_ultmod';
    public $timestamps = false;

    protected $table = 'habiSectoresxOU';
    protected $primaryKey = array('cod_ou', 'cod_sector');
    public $incrementing = false;
    protected $fillable = array('cod_ou','cod_sector','aud_usuario_ingreso','aud_ip_ingreso','aud_stm_ingreso');
}
