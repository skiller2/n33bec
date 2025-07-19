<?php

namespace App\Http\Controllers;

use App\Helpers\ConfigParametro;
use App\MoviUltReporte;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function response;

class MoviReportes extends Controller
{
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
        
        $fieldName = 'stm_ult_reporte';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        // ...
        $query = MoviUltReporte::select();
        $query = MoviUltReporte::filtroQuery($query,$filtro);

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

            $fileName="Movimientos_ultimo_reporte.$typeExp";
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
                    $fecha = date_create($row['stm_ult_reporte'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $row['stm_ult_reporte'] =date_format($fecha,"d/m/Y H:i:s");
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
                    $columnDefs[] = array("prop"=>"id_disp_origen", "name"=> __("ID Origen"), "key" => "id_disp_origen");
                    $columnDefs[] = array("prop"=>"stm_ult_reporte", "name"=> __("Fecha Último Reporte"));
            break;
            default:
                    $columnDefs[] = array("field"=>"id_disp_origen","displayName"=> __("ID Origen"));
                    $columnDefs[] = array("field"=>"stm_ult_reporte","displayName"=> __("Fecha Último Reporte"),"type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['id_disp_origen'];
        
        $filtros[] = array('id' => 'id_disp_origen', 'name'=> __("ID Origen"));

        $rango['desde'] = array('id' => 'stm_ult_reporte', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public static function store($id_disp_origen)
    {
        $reporte = MoviUltReporte::select()->where('id_disp_origen', $id_disp_origen)->first();
        if(!$reporte){
            $reporte = new MoviUltReporte;
            $reporte->id_disp_origen = $id_disp_origen;
        }
        
        $reporte->des_observaciones = "";        
        MoviUltReporte::addAuditoria($reporte, "RL");
        $reporte->stm_ult_reporte = $reporte->aud_stm_ingreso;
        $reporte->save();

        return response(['ok' => __('Componente :ID_DISP_ORIGEN fecha :STM_EVENTO',['ID_DISP_ORIGEN'=>$reporte->id_disp_origen,'STM_EVENTO'=>$reporte->stm_evento])], Response::HTTP_OK);
    }

}
