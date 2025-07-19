<?php

namespace App\Http\Controllers;

use App\Events\TemaEvent;
use App\Helpers\ConfigParametro;
use App\MoviEvento;
use App\MoviUltEvento;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class MoviEventos extends Controller
{
    const config_tag = "iolast_";
    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "gridOptions":
            case "detalle":
                return "ab_gestion";
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
        /*
        try {
            JWTAuth::parseToken()->toUser();
        } catch (Exception $e) {
            return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_UNAUTHORIZED);
        }
        */
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'stm_evento';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        // ...
        $query = MoviEvento::select();
        $query = MoviEvento::filtroQuery($query, $filtro);

        $query->orderBy($fieldName, $order);

        if ($export == "false") {
            $resultado = $query->paginate($pageSize);
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
                    $row['json_detalle'] = json_encode($row['json_detalle']);
                }
                $writer->addRows($arExport);
                unset($arExport);
            });
            $writer->close();
            return;
        }
    }

    public function gridOptions($version = "")
    {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "cod_tema", "name"=> __("Cód. Componente"), "key" => "cod_tema");
                $columnDefs[] = array("prop" => "stm_evento", "name"=> __("Fecha Evento"), "key" => "stm_evento");
                $columnDefs[] = array("prop" => "ind_modo_prueba", "name"=> __("Prueba"));
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Nombre"));
                $columnDefs[] = array("prop" => "origen", "name"=> __("Origen"));
                $columnDefs[] = array("prop" => "valor", "name"=> __("Valor"));
                $columnDefs[] = array("prop" => "des_valor", "name"=> __("Descripción"));
                $columnDefs[] = array("prop" => "valor_analogico", "name"=> __("Valor Analógico"));
                $columnDefs[] = array("prop" => "des_unidad_medida", "name"=> __("Unid. Med."));
                $columnDefs[] = array("prop" => "des_observaciones", "name"=> __("Observaciones"));
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name"=> __("Fecha Alta"));
                break;
            default:
                $columnDefs[] = array("field" => "cod_tema", "displayName"=> __("Cód. Componente"));
                $columnDefs[] = array("field" => "stm_evento", "displayName"=> __("Fecha Evento"), "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "ind_modo_prueba", "displayName"=> __("Prueba"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Nombre"));
                $columnDefs[] = array("field" => "origen", "displayName"=> __("Origen"));
                $columnDefs[] = array("field" => "valor", "displayName"=> __("Valor"));
                $columnDefs[] = array("field" => "des_valor", "displayName"=> __("Descripción"));
                $columnDefs[] = array("field" => "valor_analogico", "displayName"=> __("Valor Analógico"));
                $columnDefs[] = array("field" => "des_observaciones", "displayName"=> __("Observaciones"));
                $columnDefs[] = array("field" => "des_unidad_medida", "displayName"=> __("Unid. Med."));
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName"=> __("Fecha Alta"), "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_tema', 'stm_evento'];

        $filtros[] = array('id' => 'cod_tema', 'name'=> __("Cód. Componente"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Nombre Componente"));
        $filtros[] = array('id' => 'ind_modo_prueba', 'name'=> __("Prueba"));
        $filtros[] = array('id' => 'valor', 'name'=> __("Valor"));
        $filtros[] = array('id' => 'des_valor', 'name'=> __("Descripción"));
        $filtros[] = array('id' => 'valor_analogico', 'name'=> __("Valor Analógico"));
        $filtros[] = array('id' => 'des_unidad_medida', 'name'=> __("Unid. Med."));
        $filtros[] = array('id' => 'des_observaciones', 'name'=> __("Observaciones"));

        $rango['desde'] = array('id' => 'stm_evento', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }

    public static function store($datos)
    {
        $evento = new MoviEvento;
        $evento->cod_tema = $datos->cod_tema;
        $evento->stm_evento = $datos->stm_evento->format('Y-m-d H:i:s.u');
        $evento->nom_tema = $datos->event_data["nom_tema"];
        $evento->ind_modo_prueba = $datos->event_data["ind_modo_prueba"];
        $evento->json_detalle = $datos->event_data;
        $evento->valor = is_array($datos->event_data["valor"]) ? var_Export($datos->event_data["valor"],true) : $datos->event_data["valor"];
        $evento->origen = (isset($datos->event_data["origen"])) ? $datos->event_data["origen"] : "local";
        $evento->des_valor = (isset($datos->event_data["des_valor"])) ? $datos->event_data["des_valor"] : "";
        $evento->valor_analogico = (isset($datos->event_data["valor_analogico"])) ? $datos->event_data["valor_analogico"] : "";
        $evento->des_unidad_medida = (isset($datos->event_data["des_unidad_medida"])) ? $datos->event_data["des_unidad_medida"] : "";
        $evento->des_observaciones = (isset($datos->event_data["des_observaciones"])) ? $datos->event_data["des_observaciones"] : "";

        MoviEvento::addAuditoria($evento, "RL");
        $evento->save();


        $ultevento = MoviUltEvento::find2(['cod_tema'=>$datos->cod_tema,'ind_modo_prueba'=>$datos->event_data["ind_modo_prueba"]]);
        if (!$ultevento)
            $ultevento = new MoviUltEvento;
        $ultevento->cod_tema = $datos->cod_tema;
        $ultevento->stm_evento = $datos->stm_evento->format('Y-m-d H:i:s.u');
        $ultevento->ind_modo_prueba = $datos->event_data["ind_modo_prueba"];
        $ultevento->valor = is_array($datos->event_data["valor"]) ? var_Export($datos->event_data["valor"],true) : $datos->event_data["valor"];
        $ultevento->des_valor = (isset($datos->event_data["des_valor"])) ? $datos->event_data["des_valor"] : "";
        $ultevento->des_observaciones = (isset($datos->event_data["des_observaciones"])) ? $datos->event_data["des_observaciones"] : "";
        MoviUltEvento::addAuditoria($ultevento, "RL");
        
        $ultevento->save();
        return response(['ok'=> __("El evento :STM_EVENTO fue creado satisfactoriamente",['STM_EVENTO' =>$evento->stm_evento])], Response::HTTP_OK);
    }

    public static function altaEventoExt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_tema' => 'required',
            'valor' => 'required',
        ], [
            'cod_tema.required'=> __("Debe ingresar código de componente"),
            'valor.required'=> __("Debe ingresar valor"),
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $cod_tema = $request->input('cod_tema');
        $stm_evento_input = $request->input('stm_evento');
        $valor = $request->input('valor');
        $des_valor = $request->input('des_valor');
        $valor_fin = $request->input('valor_fin');
        $tiempo_seg = $request->input('delay');
        $des_unidad_medida = $request->input('des_unidad_medida');

        $stm_evento = ($stm_evento_input) ? Carbon::parse($stm_evento_input) : Carbon::now();

        $event_data = array(
            "valor" => $valor,
            "des_valor" => $des_valor,
            "des_unidad_medida" => $des_unidad_medida,
            "delay" => $tiempo_seg,
            "valor_fin" => $valor_fin,
            "origen" => "Remoto"
        );
        if ($valor != Cache::get(self::config_tag . $cod_tema))
            event(new TemaEvent($cod_tema, $stm_evento, $event_data));

        return response(['ok'=> __("El evento externo :COD_TEMA fue procesado satisfactoriamente",['COD_TEMA'=>$cod_tema])], Response::HTTP_OK);
    }
}
