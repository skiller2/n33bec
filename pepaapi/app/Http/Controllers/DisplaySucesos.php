<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use App\Helpers\ConfigParametro;
use App\Helpers\TemaValue;
use App\MoviCredSector;
use App\MoviUltSuceso;
use App\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Events\TemaEvent;
use Illuminate\Support\Facades\Auth;
use App\Helpers\RemoteN33;
use Illuminate\Support\Facades\Broadcast;


class DisplaySucesos extends Controller
{

    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
                return "ab_gestion";
            case "resetear";
                return "ab_resetsucesos";
            default:
                return "";
        }
    }

    public function getLista(Request $request)
    {
        $valista = array();
        $vaRemoto = array();
        $ind_falla_gral = false;
        $ind_alarma_gral = false;
        $ind_prealarma_gral = false;
        $ind_alarmatec_gral = false;
        $vaResultado = ConfigParametro::getTemas("SUCESO");
        $n33Remoto = ConfigParametro::get("N33BEC_REMOTO", true);
        $vaSectores = ConfigParametro::getSectores();

        $vaRemotos=array();
        $ip = $request->ip();
        $hashsecremoto = '';

        if (!empty($n33Remoto)) {
            foreach ($n33Remoto as $central_remota) {
                $tmp = RemoteN33::getRemoteData($central_remota['url']."/api/v1/parametros/getParametro/TEMA_LOCAL", 2);
                if (!isset($tmp['val_parametro']))
                    continue;
                $cod_tema_remoto = $tmp['val_parametro'];
                $vaRemotos[$cod_tema_remoto]=$central_remota;
                if ($central_remota['url']){
                    $tmp = RemoteN33::getRemoteData($central_remota['url']."/api/v1/displaysucesos/listasec", 10);
                    if (isset($tmp['lista']) && !empty($tmp['lista'])){
                        $hashsecremoto = hash("sha256",json_encode($tmp['lista']).$hashsecremoto);
                        $vaRemoto[]=$tmp;
                    }
                }
            }

            if (Cache::get("LISTASEC_REMOTO","")!=$hashsecremoto){
                $context = array(
                    'msgtext' => __("Configuración sectores remotos actualizada"),
                    "EstadoVal" => true, 
                    "EstadoDen" => "Sectores", 
                    "EstadoColor" => "green"
                );
                Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'info',  $context);
                Cache::forever("LISTASEC_REMOTO", $hashsecremoto);
            }
    
        }
        
        
        foreach ($vaResultado as $cod_tema => $tema) {
            if (!$tema['ind_mostrar_en_panel'])
                continue;
            
            $valista[$cod_tema] = array(
                'id' => substr($tema['hashkey'],0,20),
                'cod_tema' => $cod_tema,
                'cod_sector' => $tema['cod_sector'],
                'nom_tema' => $tema['nom_tema'],
                'parent_id' => $tema['cod_sector'],
                'stm_ult_suceso' => "",
                'class_gral' => "normal",
                'ind_alarma' => "",
                'ind_prealarma' => "",
                'ind_alarmatec' => "",
                'ind_falla' => "",
                'cant_activacion' => 0,
                'prioridad' => 4,
            );
        }
        $codigos = array_keys($valista);

        foreach ($vaSectores as $cod_sector => $sector) {
            $valista[$cod_sector] = array(
                'id' => $cod_sector,
                'parent_id' => $sector['cod_sector_padre'],
                'cod_tema' => '',
                'cod_tema_sector' => $sector['cod_tema_sector'],
                'cod_sector' => $cod_sector,
                'nom_tema' => $sector['nom_sector'],
                'stm_ult_suceso' => "",
                'class_gral' => "normal",
                'ind_alarma' => "",
                'ind_prealarma' => "",
                'ind_alarmatec' => "",
                'ind_falla' => "",
                'cant_activacion' => 0,
                'prioridad' => 4,
                'ind_video' => (!empty($sector['obj_urls_videos']))? 1:0,
            );
        }

        $vaResultado = MoviUltSuceso::select('cod_tema', 'stm_ult_suceso', 'json_detalle', 'ind_alarma', 'cant_activacion', 'ind_prealarma', 'ind_falla', 'ind_alarmatec')
            ->whereIn('cod_tema', $codigos)
            ->orderBy('stm_ult_suceso', 'desc')
            ->get();

        foreach ($vaResultado as $row) {
            $cod_tema = $row['cod_tema'];
            $nom_tema = $valista[$cod_tema]['nom_tema'];
            $stm_ult_suceso = $row['stm_ult_suceso'];
            $ind_alarma = $row['ind_alarma'];
            $ind_prealarma = $row['ind_prealarma'];
            $ind_alarmatec = $row['ind_alarmatec'];
            $ind_falla = $row['ind_falla'];
            $cant_activacion = $row['cant_activacion'];

            $class_gral = $valista[$cod_tema]['class_gral'];
            $prioridad = 4;

            if ($ind_falla) {
                $class_gral = "falla";
                $prioridad = 3;
                $ind_falla_gral = true;
            }

            if ($ind_prealarma) {
                $class_gral = "prealarma";
                $prioridad = 2;
                $ind_prealarma_gral = true;
            }

            if ($ind_alarmatec) {
                $class_gral = "alarmatec";
                $prioridad = 1;
                $ind_alarmatec_gral = true;
            }

            if ($ind_alarma) {
                $class_gral = "alarma";
                $prioridad = 0;
                $ind_alarma_gral = true;
            }

            $valista[$cod_tema]['stm_ult_suceso']=$stm_ult_suceso;
            $valista[$cod_tema]['class_gral']=$class_gral;
            
            $valista[$cod_tema]['ind_alarma']=$ind_alarma;
            $valista[$cod_tema]['ind_prealarma']=$ind_prealarma;
            $valista[$cod_tema]['ind_alarmatec']=$ind_alarmatec;
            $valista[$cod_tema]['ind_falla']=$ind_falla;

            $valista[$cod_tema]['ind_alarma']=random_int ( 0 , 1 );;
            $valista[$cod_tema]['ind_prealarma']=random_int ( 0 , 1 );;
            $valista[$cod_tema]['ind_alarmatec']=random_int ( 0 , 1 );;
            $valista[$cod_tema]['ind_falla']=random_int ( 0 , 1 );;


             

            $valista[$cod_tema]['cant_activacion']=$cant_activacion;
            $valista[$cod_tema]['prioridad']=$prioridad;

        }
        
        foreach ($vaRemoto as $listrem) {
            $valista = array_merge ($valista,$listrem['lista']);
            if ($listrem['ind_falla_gral']==1) $ind_falla_gral=1;
            if ($listrem['ind_alarma_gral']==1) $ind_alarma_gral=1;
            if ($listrem['ind_prealarma_gral']==1) $ind_prealarma_gral=1;
            if ($listrem['ind_alarmatec_gral']==1) $ind_alarmatec_gral=1;
        }

        usort($valista, function ($item1, $item2) {
            return $item1['prioridad'] <=> $item2['prioridad'];
        });

        return array("lista" => $valista, "ind_falla_gral" => $ind_falla_gral, "ind_alarma_gral" => $ind_alarma_gral, "ind_prealarma_gral" => $ind_prealarma_gral, "ind_alarmatec_gral" => $ind_alarmatec_gral, "ip"=>$ip);
    }

    public function getSubTemasDetalle($cod_tema)
    {
        $cod_tema = base64_decode($cod_tema);
        $vatemas = ConfigParametro::getTemas("");
        if (!isset($vatemas[$cod_tema]))
            return response(['error' => __('Tema no existe')], Response::HTTP_CONFLICT);

        $vadetalle = Cache::get("iolast_" . $cod_tema . "/A");
        $stm_event_alarma = (isset($vadetalle['stm_event'])) ? $vadetalle['stm_event'] : "";
        $estados_temas_alarma = (isset($vadetalle['estados_temas'])) ? $vadetalle['estados_temas'] : array();
        $vadetalle = Cache::get("iolast_" . $cod_tema . "/P");
        $stm_event_prealarma = (isset($vadetalle['stm_event'])) ? $vadetalle['stm_event'] : "";
        $estados_temas_prealarma = (isset($vadetalle['estados_temas'])) ? $vadetalle['estados_temas'] : array();

        $vadetalle = Cache::get("iolast_" . $cod_tema . "/T");
        $stm_event_alarma_tecnica = (isset($vadetalle['stm_event'])) ? $vadetalle['stm_event'] : "";
        $estados_temas_alarma_tecnica = (isset($vadetalle['estados_temas'])) ? $vadetalle['estados_temas'] : array();

        $vadetalle = Cache::get("iolast_" . $cod_tema . "/F");
        $stm_event_falla = (isset($vadetalle['stm_event'])) ? $vadetalle['stm_event'] : "";
        $estados_temas_falla = (isset($vadetalle['estados_temas'])) ? $vadetalle['estados_temas'] : array();

        $estados_temas_actuales = [];

        foreach ($estados_temas_falla as $cod_tema_falla => $tema) {
            $valor = $tema['valor'];
            $res = TemaValue::get($vatemas[$cod_tema_falla], $valor);
            $estados_temas_falla[$cod_tema_falla] = array(
                'valor' => $valor,
                'direccion' => $res['direccion'],
                'des_valor' => $res['des_valor'],
                'nom_tema' => $res['nom_tema'],
                'color' => $res['color']
            );
        }

        foreach ($estados_temas_alarma as $cod_tema_alar => $tema) {
            $valor = $tema['valor'];
            $res = TemaValue::get($vatemas[$cod_tema_alar], $valor);
            $estados_temas_alarma[$cod_tema_alar] = array(
                'valor' => $valor,
                'direccion' => $res['direccion'],
                'des_valor' => $res['des_valor'],
                'nom_tema' => $res['nom_tema'],
                'color' => $res['color']
            );
        }

        foreach ($estados_temas_prealarma as $cod_tema_pre => $tema) {
            $valor = $tema['valor'];
            $res = TemaValue::get($vatemas[$cod_tema_pre], $valor);
            $estados_temas_prealarma[$cod_tema_pre] = array(
                'valor' => $valor,
                'direccion' => $res['direccion'],
                'des_valor' => $res['des_valor'],
                'nom_tema' => $res['nom_tema'],
                'color' => $res['color']
            );
        }

        foreach ($estados_temas_alarma_tecnica as $cod_tema_tec => $tema) {
            $valor = $tema['valor'];
            $res = TemaValue::get($vatemas[$cod_tema_tec], $valor);
            $estados_temas_alarma_tecnica[$cod_tema_tec] = array(
                'valor' => $valor,
                'direccion' => $res['direccion'],
                'des_valor' => $res['des_valor'],
                'nom_tema' => $res['nom_tema'],
                'color' => $res['color']
            );
        }

        foreach ($vatemas[$cod_tema]['subtemas'] as $cod_subtema) {
            $valor = Cache::get("iolast_" . $cod_subtema);
            if ($valor == NULL) $valor = "";
            $res = TemaValue::get($vatemas[$cod_subtema], $valor);
            $estados_temas_actuales[$cod_subtema] = array(
                'valor' => $valor,
                'direccion' => $res['direccion'],
                'des_valor' => $res['des_valor'],
                'nom_tema' => $res['nom_tema'],
                'color' => $res['color']
            );
        }

        return array(
            "stm_event_alarma" => $stm_event_alarma,
            "stm_event_prealarma" => $stm_event_prealarma,
            "stm_event_alarma_tecnica" => $stm_event_alarma_tecnica,
            "stm_event_falla" => $stm_event_falla,
            "estados_temas_alarma" => $estados_temas_alarma,
            "estados_temas_prealarma" => $estados_temas_prealarma,
            "estados_temas_alarma_tecnica" => $estados_temas_alarma_tecnica,
            "estados_temas_falla" => $estados_temas_falla,
            "estados_temas_actuales" => $estados_temas_actuales
        );
    }


    public function getTemaDetalle($cod_tema)
    {
        $cod_tema = base64_decode($cod_tema);
        $img_tema = "";
        $tipo_uso = "";
        $ind_alarma = 0;
        $ind_prealarma = 0;
        $ind_falla = 0;
        $ind_alarmatec = 0;

        if (!isset($vatemas[$cod_tema]))
            return response(['error' => __('Tema no existe')], Response::HTTP_CONFLICT);

        $nom_tema = $vatemas[$cod_tema]['nom_tema'];

        $vaResultado = MoviUltSuceso::select('ind_alarma', 'ind_prealarma', 'ind_falla', 'ind_alarmatec')
            ->where('moviUltSuceso.cod_tema', $cod_tema)
            ->first();
        if ($vaResultado) {
            $ind_alarma = $vaResultado['ind_alarma'];
            $ind_prealarma = $vaResultado['ind_prealarma'];
            $ind_falla = $vaResultado['ind_falla'];
            $ind_alarmatec = $vaResultado['ind_alarmatec'];
        }

        return array("img_tema" => $img_tema, "tipo_uso" => $tipo_uso, "ind_alarma" => $ind_alarma, "ind_alarmatec" => $ind_alarmatec, "ind_prealarma" => $ind_prealarma, "ind_falla" => $ind_falla, "cod_tema" => $cod_tema, "nom_tema" => $nom_tema);
    }


    public function resetear(Request $request)
    {
        $cod_tema = $request->input('cod_tema');
        $user = Auth::user();

        $cod_usuario = ($user['cod_usuario']) ? $user['cod_usuario'] : "interno";
        $des_observaciones = $user['ape_persona'] . ", " . $user['nom_persona'] . " ($cod_usuario)";
        $ind = $request->input('ind');
        if ($ind == "contador") {
            $event_data = array("valor" => "R", "des_observaciones" => $des_observaciones, "json_detalle" => "");
            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
        } else if ($ind == "estado") {
            $event_data = array("valor" => "N", "des_observaciones" => $des_observaciones, "json_detalle" => "");
            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
        }

        return response(['ok' => ''], Response::HTTP_OK);
    }
}
