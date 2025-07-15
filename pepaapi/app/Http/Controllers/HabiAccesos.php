<?php

namespace App\Http\Controllers;


use App\HabiAcceso;
use App\HabiCredPersona;
use App\HabiCredSectores;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HabiAccesos extends Controller {

    public function getLastUpdate() {
        $lastUpdate = Cache::get('HabiAccesoLastUpdate');
        if ($lastUpdate == ""){
            $lastUpdate = Carbon::now()->format('Y-m-d H:i:s');
            Cache::forever('HabiAccesoLastUpdate', $lastUpdate);
        }
        return $lastUpdate;
    }

    public function getHabiAccesoSync(Request $request) {
        $page = $request->input('page');
        $pageSize = $request->input('pageSize');
        return HabiAcceso::select()->simplePaginate($pageSize, ['*'], 'page', $page);
    }

    public static function checkhabiAcceso($checkFirst = false) {
        //SI NO EXISTE LA TABLA HABIACCESO, LA CREA
        if($checkFirst){
            $selHabiAcceso = self::select()->first();
            if (!empty($selHabiAcceso))
                return;
        }

        $stm_actual = Carbon::now()->format('Y-m-d H:i:s.u');
        $user = Auth::user();
        $cod_usuario = (isset($user['cod_usuario'])) ? $user['cod_usuario'] : "interno";
        //$ip = Request::ip();
        $ip = '';
        
        $vaLectores = ConfigParametro::getTemas("LECTOR");
        $selHabiAcceso = HabiCredPersona::select('habiCredPersona.cod_credencial', 'habiCredPersona.cod_ou_hab', 'habiCredPersona.cod_persona_contacto', 
                'habiCredPersona.cod_ou_emisora', 'maesAliasCred.ref_credencial', 'habiCredPersona.cod_persona', 'habiCredPersona.tipo_habilitacion', 
                'habiCredPersona.stm_habilitacion_hasta', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 'maesPersonas.cod_sexo', 
                'habiCredPersona.obs_habilitacion', 'maesPersonas.cod_tipo_doc', 'maesPersonas.nro_documento', 'habiCredGrupo.cod_grupo', 
                'maesUnidadesOrganiz.nom_ou as nom_ou_hab', 'personaContacto.nom_persona as nom_persona_contacto', 
                'personaContacto.ape_persona as ape_persona_contacto', 'habiCredPersona.cod_esquema_acceso')
                ->leftjoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'habiCredPersona.cod_credencial')
                ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
                ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
                ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
                ->leftjoin('maesPersonas as personaContacto', 'personaContacto.cod_persona', '=', 'habiCredPersona.cod_persona')
                ->get();
        foreach ($selHabiAcceso as $row) {
            $cod_credencial = $row['cod_credencial'];
            $sectoresSel = HabiCredSectores::select('cod_sector')->where('cod_credencial', $cod_credencial)->get();
            $vaTemas = array();
            foreach ($sectoresSel as $cod) {
                $cod_sector = $cod['cod_sector'];
                foreach($vaLectores as $cod_tema => $datos_tema) {
                    if($datos_tema['cod_sector'] == $cod_sector) {
                        $vaTemas[$cod_tema] = $cod_tema;
                    }
                }
            }
            $json_temas = $vaTemas;

            HabiAcceso::updateOrCreate([
                    'cod_credencial' => $row['cod_credencial']
                ],
                [
                    'ref_credencial' => $row['ref_credencial'],
                    'cod_persona' => $row['cod_persona'],
                    'nom_persona' => $row['nom_persona'],
                    'ape_persona' => $row['ape_persona'],
                    'cod_sexo' => $row['cod_sexo'],
                    'cod_tipo_doc' => $row['cod_tipo_doc'],
                    'nro_documento' => $row['nro_documento'],
                    'tipo_habilitacion' => $row['tipo_habilitacion'],
                    'obs_habilitacion' => $row['obs_habilitacion'],
                    'cod_grupo' => $row['cod_grupo'],
                    'cod_ou_hab' => $row['cod_ou_hab'],
                    'nom_ou_hab' => $row['nom_ou_hab'],
                    'cod_persona_contacto' => $row['cod_persona_contacto'],
                    'nom_persona_contacto' => $row['nom_persona_contacto'],
                    'ape_persona_contacto' => $row['ape_persona_contacto'],
                    'cantidad_ingresos' => 0,
                    'json_temas' => $json_temas,
                    'cod_esquema_acceso' => $row['cod_esquema_acceso'],
                    'stm_habilitacion_hasta' => $row['stm_habilitacion_hasta'],
                    'aud_usuario_ingreso' => $cod_usuario,
                    'aud_stm_ingreso' => $stm_actual,
                    'aud_ip_ingreso' => $ip
                ]);
        }
        Cache::forever("HabiAccesoLastUpdate",Carbon::now()->format('Y-m-d H:i:s'));
        return true;
    }
}
