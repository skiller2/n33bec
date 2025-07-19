<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ConfigParametro;
use App\MoviCredSector;
use App\Sector;
use Carbon\Carbon;
use App\HabiCredPersona;
use App\Imagen;

class DisplaySector extends Controller {

    public static function getAbility($metodo) {
        switch ($metodo) {
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
                return "ab_gestion";
            default:
                return "";
        }
    }

    public function getSectorCants($cod_sector) {
       
        $sector = Sector::find($cod_sector);
        if(!$sector) {
            return response(['error' => __('Sector inexistente')], Response::HTTP_CONFLICT);
        }

        $nom_sector = $sector->nom_sector;
        $max_cant_personas = $sector->max_cant_personas;

        $cant_personas = MoviCredSector::where('cod_sector', $cod_sector)->count();

        return array("cant_personas" => $cant_personas, "nom_sector" => $nom_sector, "max_cant_personas" => $max_cant_personas, "cod_sector" => $cod_sector);
    }

    public function getLista($cod_sector) {
        $valista = array();
        $vaResultado = MoviCredSector::select('maesPersonas.ape_persona', 'maesPersonas.nom_persona',
        'maesUnidadesOrganiz.nom_ou', 'moviCredSector.stm_ingreso')
        ->leftjoin('habiCredPersona', 'habiCredPersona.cod_credencial', 'moviCredSector.cod_credencial')
        ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
        ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
        ->where('cod_sector', $cod_sector)
        ->orderBy('moviCredSector.stm_ingreso', 'asc')
        ->get();
        if (!empty($vaResultado[0])) {
//            for($i = 0; $i <= 10; $i++) {
                foreach ($vaResultado as $row) {
                    $valista[] = array('des_persona' => $row['nom_persona']." ".$row['ape_persona'], "nom_ou" => $row['nom_ou'],
                        'tiempo_permanencia' => $row['tiempo_permanencia']);
//                }
            }
        }
        return $valista;
    }

    public function getPersona($cod_credencial) {

        $ape_persona = ""; $nom_persona = ""; $cod_persona = ""; $nom_ou = ""; $vencimiento = __("No disponible"); $tipo_habilitacion = "";
        Carbon::setLocale('es');
        $query = HabiCredPersona::select('habiCredPersona.cod_persona', 'habiCredPersona.tipo_habilitacion',
        'maesPersonas.ape_persona', 'maesPersonas.nom_persona', 'maesUnidadesOrganiz.nom_ou','maesPersAptoF.fec_vencimiento_af')
        ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
        ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
        ->leftjoin('maesPersAptoF', 'maesPersAptoF.cod_persona', '=', 'habiCredPersona.cod_persona')
        ->where('habiCredPersona.cod_credencial', '=', $cod_credencial)
        ->get();
        if (!empty($query[0])) {
            $ape_persona = $query[0]['ape_persona'];
            $nom_persona = $query[0]['nom_persona'];
            $cod_persona = $query[0]['cod_persona'];
            $tipo_habilitacion = $query[0]['tipo_habilitacion'];
            $nom_ou = $query[0]['nom_ou'];
            if ($query[0]['fec_vencimiento_af']) {
                $vencimiento = Carbon::now()->diffForHumans($query[0]['fec_vencimiento_af'], true, true);
            }
        }

        return array("ape_persona" => $ape_persona, "nom_persona" => $nom_persona, "cod_persona" => $cod_persona, "nom_ou" => $nom_ou, 
            "vencimiento" => $vencimiento, "tipo_habilitacion" => $tipo_habilitacion);

    }

    public function getFoto($cod_credencial) {

        $query = HabiCredPersona::select('habiCredPersona.cod_persona')
        ->where('habiCredPersona.cod_credencial', '=', $cod_credencial)
        ->get();

        if (!empty($query[0])) {
            $imagen = Imagen::find($query[0]['cod_persona']);
            if($imagen) {
                return $imagen->img_persona;
            }
        }
        return '';
    }

}
