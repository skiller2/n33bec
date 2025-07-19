<?php

namespace App\Http\Controllers;

use App\Helpers\ConfigParametro;
use App\MoviUltSuceso;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MoviUltSucesos extends Controller
{
	const config_tag = "iolast_";
    public static function getAbility($metodo)
    {
        switch ($metodo){
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
        $filtro = json_decode($request->input('filtro'),true);
        $sort = json_decode($request->input('sort'),true);
        
        $fieldName = 'stm_evento';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        // ...
        $query = MoviEvento::select();
        $query = MoviEvento::filtroQuery($query,$filtro);

        $query->orderBy($fieldName, $order);

        if($export == "false")
        {
            $resultado = $query->paginate($pageSize);
            return $resultado;
        }
        else
        {
            switch ($export){
                case "xls":
                    $typeExp=Type::XLSX;
                    break;
                case "csv":
                    $typeExp=Type::CSV;
                    break;                    
                case "ods":
                    $typeExp=Type::ODS;
                    break;
                default:
                    $typeExp=Type::XLSX;
                    break;
            }
            $fileName="Movimientos_permitidos.$typeExp";
            $writer = WriterFactory::create($typeExp); // for XLSX files
            $writer->openToBrowser($fileName); // stream data directly to the browser
            $timezoneGMT = new DateTimeZone('GMT');
            $timezoneApp = new DateTimeZone(ConfigParametro::get('TIMEZONE_INFORME',false));
            
            $query->chunk(1000, function($multipleRows) use ($writer,$timezoneGMT,$timezoneApp) {
                static $FL=true;
                if ($FL) {
                    $writer->addRow(array_keys($multipleRows[0]->getAttributes()));
                    $FL=false;
                }
                $arExport = $multipleRows->toArray();

                foreach($arExport AS &$row) {
                    $fecha = date_create($row['stm_evento'], $timezoneGMT)->setTimeZone($timezoneApp);
                    //$row['stm_movimiento'] = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha);
                    $row['stm_evento'] =date_format($fecha,"d/m/Y H:i:s");
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
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_tema", "name"=> __("Tema"), "key" => "cod_tema");
                    $columnDefs[] = array("prop"=>"stm_ult_suceso", "name"=> __("Fecha Suceso"), "key" => "stm_ult_suceso");
                    $columnDefs[] = array("prop"=>"json_detalle", "name"=> __("I/O"), "key" => "json_detalle");
                    $columnDefs[] = array("prop"=>"nom_tema", "name"=> __("Etiqueta"));
                    $columnDefs[] = array("prop"=>"id_disp_reporte", "name"=> __("ID Reporte"));
                    $columnDefs[] = array("prop"=>"valor_analogico", "name"=> __("Valor Analógico"));
                    $columnDefs[] = array("prop"=>"des_unidad_medida", "name"=> __("Unid. Med."));
                    $columnDefs[] = array("prop"=>"valor", "name"=> __("Valor"));
                    $columnDefs[] = array("prop"=>"des_valor", "name"=> __("Descripción"));
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name"=> __("Fecha Alta"));
            break;
            default:
                    $columnDefs[] = array("field"=>"id_disp_origen","displayName"=> __("ID Origen"));
                    $columnDefs[] = array("field"=>"stm_evento","displayName"=> __("Fecha Evento"),"type"=>"date","cellFilter"=>"ftDateTime");
                    $columnDefs[] = array("field"=>"id_io","displayName"=> __("I/O"));
                    $columnDefs[] = array("field"=>"des_io","displayName"=> __("Etiqueta"));
                    $columnDefs[] = array("field"=>"id_disp_reporte","displayName"=> __("ID Reporte"));
                    $columnDefs[] = array("field"=>"valor_analogico","displayName"=> __("Valor Analógico"));
                    $columnDefs[] = array("field"=>"des_unidad_medida","displayName"=> __("Unid. Med."));
                    $columnDefs[] = array("field"=>"valor","displayName"=> __("Valor"));
                    $columnDefs[] = array("field"=>"des_valor","displayName"=> __("Descripción"));
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=> __("Fecha Alta"),"type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['id_disp_origen','stm_evento','id_io'];
        
        $filtros[] = array('id' => 'id_disp_origen', 'name'=> __("ID Origen"));
        $filtros[] = array('id' => 'id_io', 'name'=> __("I/O"));
        $filtros[] = array('id' => 'des_io', 'name'=> __("Etiqueta"));
        $filtros[] = array('id' => 'id_disp_reporte', 'name'=> __("ID Reporte"));
        $filtros[] = array('id' => 'valor_analogico', 'name'=> __("Valor Analógico"));
        $filtros[] = array('id' => 'des_unidad_medida', 'name'=> __("Unid. Med."));
        $filtros[] = array('id' => 'valor', 'name'=> __("Valor"));
        $filtros[] = array('id' => 'des_valor', 'name'=> __("Descripción"));

        $rango['desde'] = array('id' => 'stm_evento', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public static function resetCounter($datos) {
        $cod_tema = $datos['cod_tema'];
        $suceso = MoviUltSuceso::where('cod_tema', $cod_tema)->first();
        if ($suceso) {
            $suceso->stm_reseteo = Carbon::now();
            $suceso->cant_activacion = 0;
            MoviUltSuceso::addAuditoria($suceso, "RL");
            $suceso->save();
        }
        return response(['ok' => __('Reset contador de :COD_TEMA',['COD_TEMA'=>$cod_tema])], Response::HTTP_OK);
    }

    public static function resetStatus($datos) {
        $cod_tema = $datos['cod_tema'];
        $utlsuceso = MoviUltSuceso::where('cod_tema', $cod_tema)->first();
        if ($utlsuceso) {
            $utlsuceso->ind_alarma = 0;
            $utlsuceso->ind_prealarma = 0;
            $utlsuceso->ind_falla = 0;
            $utlsuceso->ind_alarmatec = 0;

            MoviUltSuceso::addAuditoria($utlsuceso, "RL");
            $utlsuceso->save();
        }
        return response(['ok' => __('Reset estado de :COD_TEMA',['COD_TEMA'=>$cod_tema])], Response::HTTP_OK);
    }

    public static function store($datos)
    {
        $cod_tema = $datos['cod_tema'];

        if (!$cod_tema) {
            return response(['error' => ""], Response::HTTP_CONFLICT);
        }

        $stm_ult_suceso = Carbon::now();

        $suceso = MoviUltSuceso::where('cod_tema', $cod_tema)->first();
        if(!$suceso){
            $suceso = new MoviUltSuceso;
            $suceso->cod_tema = $cod_tema;
            $suceso->cant_activacion = 0;
            $suceso->des_observaciones = "";
            $suceso->stm_reseteo = Carbon::now();
        } else {
            $suceso->cant_activacion++;            
        }
        
        $suceso->stm_ult_suceso = $stm_ult_suceso;
        $suceso->ind_alarma = ($suceso->ind_alarma!=1)?$datos['ind_alarma']:$suceso->ind_alarma;
        $suceso->ind_prealarma = ($suceso->ind_prealarma!=1)?$datos['ind_prealarma']:$suceso->ind_prealarma;
        $suceso->ind_falla = ($suceso->ind_falla!=1)?$datos['ind_falla']:$suceso->ind_falla;
        $suceso->ind_alarmatec = ($suceso->ind_alarmatec!=1)?$datos['ind_alarmatec']:$suceso->ind_alarmatec;
        $suceso->json_detalle = $datos['json_detalle'];
        MoviUltSuceso::addAuditoria($suceso, "RL");
        $ret = $suceso->save();

        return response(['ok' => __('El Suceso :COD_TEMA fue creado satisfactoriamente',['COD_TEMA'=>$suceso->cod_tema])], Response::HTTP_OK);
    }
}
