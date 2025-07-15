<?php

namespace App\Http\Controllers;

use App\Feriado;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use App\Helpers\ConfigParametro;

class Feriados extends Controller {

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

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request, $export) {
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
        $fieldName = 'fec_feriado';
        $order = 'desc';
        if ($sort) {
            if (!empty($sort[0]))
                $sort = $sort[0];
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = Feriado::select('fec_feriado', 'des_feriado', 'aud_stm_ingreso');

        if ($filtro)
            $query = Feriado::filtroQuery($query, $filtro);

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
            $fileName="Feriados.$typeExp";
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
                    $fecha = date_create($row['aud_stm_ingreso'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $row['aud_stm_ingreso'] = date_format($fecha,"d/m/Y H:i:s");
                }
                $writer->addRows($arExport);
                unset($arExport);
            });            
            $writer->close();
            return;
        }
    }

    public function gridOptions($version = "") {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "fec_feriado", "name" => "Fecha", "key" => "fec_feriado", "pipe" => "ftDate", "searchtype" => "date");
                $columnDefs[] = array("prop" => "des_feriado", "name" => "Descripción", "searchtype" => "text");
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name" => "Fecha Alta", "pipe" => "ftDateTime", "searchtype" => "date");
                break;
            default:
                $columnDefs[] = array("field" => "fec_feriado", "displayName" => "Fecha", "type" => "date", "cellFilter" => "ftDate");
                $columnDefs[] = array("field" => "des_feriado", "displayName" => "Descripción");
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName" => "Fecha Alta", "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['fec_feriado'];
        
        $filtros[] = array('id' => 'fec_feriado', 'name' => 'Fecha');
        $filtros[] = array('id' => 'des_feriado', 'name' => 'Descripción');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function detalle($clave,$ou_sel) {
        $clave = json_decode(base64_decode($clave), true); 
        $fec_feriado = $clave[0][0];
        $record =Feriado::find($fec_feriado);
        if ($record)
            return response($record, Response::HTTP_OK);
        else 
            return response(["error"=>"No localizado"], Response::HTTP_NOT_FOUND);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request) {
        Cache::forget("FECHA_ACTUAL");
        Cache::forget("FECHA_FERIADO");
        $feriado = new Feriado;
        $feriado->fec_feriado = $request->input('fec_feriado');
        $feriado->des_feriado = $request->input('des_feriado');
        Feriado::addAuditoria($feriado, "A");
        $feriado->save();

        return response(['ok' => 'El feriado fue creado satisfactoriamente con fecha: ' . $feriado->fec_feriado], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request) {
        $fec_feriado = $request->input('fec_feriado');
        Cache::forget("FECHA_ACTUAL");
        Cache::forget("FECHA_FERIADO");
        $feriado = Feriado::find($fec_feriado);
        $feriado->fec_feriado = $fec_feriado;
        $feriado->des_feriado = $request->input('des_feriado');

        Feriado::addAuditoria($feriado, "M");
        $feriado->save();

        return response(['ok' => 'Actualización exitosa: ' . $fec_feriado], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($clave) {
        $clave = json_decode(base64_decode($clave), true); 
        $fec_feriado = $clave[0][0];
        Cache::forget("FECHA_ACTUAL");
        Cache::forget("FECHA_FERIADO");
        $feriado = Feriado::find($fec_feriado);
        $feriado->delete();
        return response(['ok' => 'Se eliminó satisfactoriamente el feriado: ' . $fec_feriado], Response::HTTP_OK);
    }

    public static function isFeriado($stm_fecha) {
        $fecha_feriado = $stm_fecha->format('Y-m-d');
        $obj_feriado = Cache::get("FECHA_FERIADO");
        if ($obj_feriado && $obj_feriado['fecha'] == $fecha_feriado) {
            return $obj_feriado['esferiado'];
        } else {
            $esferiado = (Feriado::find($fecha_feriado)) ? true : false;
            Cache::forever("FECHA_FERIADO", array("fecha" => $fecha_feriado, "esferiado" => $esferiado));
            return $esferiado;
        }
    }

}
