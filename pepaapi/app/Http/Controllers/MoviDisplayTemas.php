<?php

namespace App\Http\Controllers;

use App\Events\TemaEvent;
use App\Helpers\ConfigParametro;
use App\MoviDisplayTema;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use App\Helpers\RemoteN33;

class MoviDisplayTemas extends Controller
{
    const config_tag = "iolast_";
    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "gridOptions":
            case "detalle":
                return "ab_gestion";
            case "resetear":
            case "restaurarTema":
                return "ab_resetsucesos";
            default:
                return "";
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request, $export)
    {
        $ip = $request->ip();
        $query = MoviDisplayTema::select('moviDisplayTemas.cod_tema', 'moviDisplayTemas.tipo_evento', 'moviDisplayTemas.stm_evento', 'maesTemas.nom_tema', 'maesTemas.cod_sector', 'moviDisplayTemas.cant_activacion', 'moviDisplayTemas.des_observaciones')
            ->join('maesTemas', 'maesTemas.cod_tema', '=', 'moviDisplayTemas.cod_tema')
            ->orderBy('tipo_evento', 'asc')
            ->orderBy('stm_evento', 'desc')
            ->get();

        //        $query = MoviDisplayTema::filtroQuery($query, $filtro);


        $vasectores = ConfigParametro::getSectores();
        $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));

        foreach ($query as $idx => $tematipo) {
            $cod_sector = $tematipo['cod_sector'];
            //            $des_observaciones = $tematipo['des_observaciones'];
            $vasectoresresp = array();
            $nom_sectores = "";

            if (!isset($vasectores[$cod_sector]))
                continue;

            $vasectoresresp[] = array("cod_sector" => $cod_sector, "nom_sector" => $vasectores[$cod_sector]['nom_sector'], "cod_tema_sector"=>$vasectores[$cod_sector]['cod_tema_sector']);

            $familia = $vasectores[$cod_sector]['familia'];

            foreach ($familia as $cod_sector_fam) {
                $vasectoresresp[] = array("cod_sector" => $cod_sector_fam, "nom_sector" => $vasectores[$cod_sector_fam]['nom_sector'], "cod_tema_sector"=>$vasectores[$cod_sector]['cod_tema_sector']);
            }

            $familia = array_reverse($familia);
            foreach ($familia as $cod_sector_fam) {
                $nom_sectores .= "/" . $vasectores[$cod_sector_fam]['nom_sector'];
            }
            $nom_sectores .= "/" . $vasectores[$cod_sector]['nom_sector'];

            $query[$idx]['cod_tema_sector'] = $cod_tema_local."/".$tematipo['cod_sector'];
            $query[$idx]['sectores'] = $vasectoresresp;
            $query[$idx]['nom_sectores'] = $nom_sectores;
        }


        //        $query->orderBy($fieldName, $order);

        if ($export == "false") {
            $resultado =  $query->toArray();
            //            $resultado = $query->paginate($pageSize);

            $remotos = Cache::get("remoto_display", array());
            foreach($remotos as $cod_tema_remoto=>$lista){
                $resultado=array_merge($resultado,$lista);
            }
            return $resultado;
        } else {
            switch ($export) {
                case "xls":
                    $typeExp = Type::XLSX;
                    break;
                case "csv":
                    $typeExp = Type::CSV;
                    break;
                case "ods":
                    $typeExp = Type::ODS;
                    break;
                default:
                    $typeExp = Type::XLSX;
                    break;
            }
            $fileName = "eventos.$typeExp";
            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME', false));

            $query->chunk(1000, function ($multipleRows) use ($writer, $timezoneGMT, $timezoneApp) {
                static $FL = true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL = false;
                }
                $arExport = $multipleRows->toArray();

                foreach ($arExport as &$row) {
                    $stm_evento = date_create($row['stm_evento'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $aud_stm_ingreso = date_create($row['aud_stm_ingreso'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $row['stm_evento'] = date_format($stm_evento, "d/m/Y H:i:s");
                    $row['aud_stm_ingreso'] = date_format($aud_stm_ingreso, "d/m/Y H:i:s");
                    //                    $row['json_detalle'] = json_encode($row['json_detalle']);
                }
                $writer->addRows($arExport);
                unset($arExport);
            });
            $writer->close();
            return;
        }
    }

    public static function store($datos)
    {
        $evento = MoviDisplayTema::where('cod_tema', '=', $datos->cod_tema)
            ->where('tipo_evento', '=', $datos->event_data['tipo_evento'])
            ->first();

        if ($evento) {
            $evento->cant_activacion = $evento->cant_activacion + 1;
            $evento->des_observaciones = $datos->event_data['des_observaciones'];
        } else {
            $evento = new MoviDisplayTema;
            $evento->cod_tema = $datos->cod_tema;
            $evento->tipo_evento = $datos->event_data['tipo_evento'];
            $evento->valor = $datos->event_data['valor'];
            $evento->des_observaciones = $datos->event_data['des_observaciones'];
            $evento->stm_evento = $datos->stm_evento->format('Y-m-d H:i:s.u');
            $evento->cant_activacion = 1;
            MoviDisplayTema::addAuditoria($evento, "RL");

            $level = "info";
            $context = array(
                'cod_tema' => $evento->cod_tema,
                'valor' => $evento->valor,
                'tipo_evento' => $evento->tipo_evento,
                "ind_modo_prueba" => Cache::get("ind_modo_prueba", 0),
                'msgtext' => ""
            );

            Broadcast::driver('fast-web-socket')->broadcast(["movidisplaytema"], $level,  $context);
        }
        $evento->save();

        return response(['ok' => __('El evento :STM_EVENTO fue creado satisfactoriamente',['STM_EVENTO'=>$evento->stm_evento])], Response::HTTP_OK);
    }

    public function delete($cod_tema)
    {
        $vaResultado = MoviDisplayTema::where('cod_tema', '=', $cod_tema)->get();
        if ($vaResultado) {
            foreach ($vaResultado as $aborrar)
                $aborrar->delete();

            /*
            $level="info";
            $context = array(
                'cod_tema' => $evento->cod_tema,
                'valor' => $evento->valor,
                'tipo_evento' => $evento->tipo_evento,
                'msgtext' => ""
            );

            Broadcast::driver('fast-web-socket')->broadcast(["movidisplaytema"], $level,  $context);
*/
//            MoviDisplayTemas::sendMoviDisplayTemasSync();

            return response(['ok' => __('Se eliminó satisfactoriamente el movimiento')], Response::HTTP_OK);
        } else {
            return response(['ok' => __('Sin registros para eliminar')], Response::HTTP_OK);
        }
    }

    


    public function restaurarTemaRemote(Request $request)
    {
        $cod_tema = $request->input('cod_tema');
        $cod_usuario = $request->input('cod_usuario');
        $ape_persona = $request->input('ape_persona');
        $nom_persona = $request->input('nom_persona');
        $cod_tema_origen = $request->input('cod_tema_origen');
        $user = array("ape_persona" => $ape_persona, "nom_persona" => $nom_persona,);
        $des_observaciones = "Cambio manual, " . $user['ape_persona'] . ", " . $user['nom_persona'] . " ($cod_usuario@$cod_tema_origen)";
        $temas = ConfigParametro::getTemas();
        if (!isset($temas[$cod_tema])) {
            return response(['error' => __("No se encontro el código :COD_TEMA",['COD_TEMA'=>$cod_tema])], Response::HTTP_CONFLICT);
        }

        $tema = $temas[$cod_tema];
        $valor = $tema['val_NO'];
        $ind_manual = $tema['ind_manual'];



        if ($ind_manual == 1) {
            $this->delete($cod_tema);
            $event_data = array("valor" => $valor, "des_observaciones" => $des_observaciones, "json_detalle" => "");
            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
            return response(['ok' => ''], Response::HTTP_OK);
        } else {
            return response(['error' => __('No se encuentra habilitada la opción de modificación manual')], Response::HTTP_CONFLICT);
        }
    }


    public function restaurarTema(Request $request)
    {
        $cod_tema = $request->input('cod_tema');
        $user = Auth::user();
        $cod_usuario = ($user['cod_usuario']) ? $user['cod_usuario'] : "interno";
        $des_observaciones = "Cambio manual, " . $user['ape_persona'] . ", " . $user['nom_persona'] . " ($cod_usuario)";
        $temas = ConfigParametro::getTemas();
        if (!isset($temas[$cod_tema])) {
            $vaRemotos = Cache::get("N33BEC_REMOTO", array());
            $postData = array(
                "cod_tema" => $cod_tema,
                "cod_tema_origen" => strtolower(ConfigParametro::get("TEMA_LOCAL", false)),
                "ape_persona" => $user['ape_persona'],
                "nom_persona" => $user['nom_persona'],
                "cod_usuario" => $cod_usuario
            );

            foreach ($vaRemotos as $cod_tema_origen => $remoto) {
                if (strpos($cod_tema, $cod_tema_origen) === 0) {
                    $ret = RemoteN33::postRemoteData($remoto['url'] . "/api/v1/displaysucesos/remoto/restaurarTema", $postData);
                    if ($ret !== false)
                        return $ret;
                }
            }

            return response(['error' => __("No se encontro el código :COD_TEMA",['COD_TEMA'=>$cod_tema])], Response::HTTP_CONFLICT);
        }

        $tema = $temas[$cod_tema];
        $valor = $tema['val_NO'];
        $ind_manual = $tema['ind_manual'];



        if ($ind_manual == 1) {
            $this->delete($cod_tema);
            $event_data = array("valor" => $valor, "des_observaciones" => $des_observaciones, "json_detalle" => "");
            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
            return response(['ok' => ''], Response::HTTP_OK);
        } else {
            return response(['error'=> __("No se encuentra habilitada la opción de modificación manual")], Response::HTTP_CONFLICT);
        }
    }


    public function getLista()
    {
        $query = MoviDisplayTema::select('moviDisplayTemas.cod_tema', 'moviDisplayTemas.tipo_evento', 'moviDisplayTemas.stm_evento', 'maesTemas.nom_tema', 'maesTemas.cod_sector', 'moviDisplayTemas.cant_activacion', 'moviDisplayTemas.des_observaciones')
            ->join('maesTemas', 'maesTemas.cod_tema', '=', 'moviDisplayTemas.cod_tema')
            ->orderBy('tipo_evento', 'asc')
            ->orderBy('stm_evento', 'desc')
            ->get();
        return $query;
    }

    public function cmdCentral(Request $request)
    {
        $cod_tema = $request->input('cod_tema');
        $cmd = $request->input('cmd');
        $temas = ConfigParametro::getTemas();
        if (!isset($temas[$cod_tema])) {
            return response(['error' => __("No se encontro el código :COD_TEMA",['COD_TEMA'=>$cod_tema])], Response::HTTP_CONFLICT);
        }

        $tema = $temas[$cod_tema];
        $bus_id = (isset($tema['bus_id']))?$tema['bus_id']:"";

        if ($bus_id=="") {
            return response(['error'=> __("No se encuentra configurado el bus_id para enviar comandos")], Response::HTTP_CONFLICT);
        }


        $context = array(
            'msgtext' => "",
            'cod_tema' => $cod_tema,
            'bus_id' => $bus_id,
            'command' => "bus",
            'subcommand'=> $cmd,
            'cod_daemon'=> 'Area54Daemon'
        );

        Broadcast::driver('fast-web-socket')->broadcast(["procesos"], "info",  $context);

        sleep(2);
        return response(['ok'=> __("Orden enviada")], Response::HTTP_OK);
    }


    public function resetear(Request $request)
    {
        $cod_tema = $request->input('cod_tema');
        $user = Auth::user();
        $temas = ConfigParametro::getTemas();
        $tema = $temas[$cod_tema];
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
