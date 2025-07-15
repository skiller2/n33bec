<?php

namespace App\Http\Controllers;

use App\Events\IOEvent;
use App\Helpers\ConfigParametro;
use App\MoviCredSector;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use App\Traits\Libgeneral;

class MoviCredSectores extends Controller
{
    public static function getAbility($metodo)
    {
        switch ($metodo){
            case "index":
            case "gridOptions":
            case "detalle":
            case "delete":
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
        
        $fieldName = 'stm_ingreso';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            if ($fieldName === "tiempo_permanencia") $fieldName = 'stm_ingreso';
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        $tablaOrden = self::getTabla($fieldName);
        //DB::enableQueryLog();
        // ...
        $query = MoviCredSector::select('moviCredSector.stm_ingreso','moviCredSector.cod_credencial','maesAliasCred.ref_credencial',
        'maesPersonas.nom_persona','maesPersonas.ape_persona','maesPersonas.nro_documento','moviCredSector.cod_sector',
        'maesSectores.nom_sector','maesUnidadesOrganiz.nom_ou')
        ->leftJoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'moviCredSector.cod_credencial')
        ->leftJoin('habiCredPersona', 'habiCredPersona.cod_credencial', '=', 'moviCredSector.cod_credencial')
        ->leftJoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
        ->leftJoin('maesSectores', 'maesSectores.cod_sector', '=', 'moviCredSector.cod_sector')
        ->leftJoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
        ;
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($nombre == "ref_credencial") {
                        $operacion = "=";
                        if (is_numeric($valor)) {
                            $valor = (int) $valor;
                            $valor = (string) $valor;
                        }
                    }
                    else if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }
                    
                    if ($operacion == "LIKE")
                            $valor = "%" . $valor . "%";
                    $tabla = self::getTabla($nombre);
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(maesPersonas.nom_persona, maesPersonas.ape_persona, maesPersonas.nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else {
                        $query->where($tabla . $nombre, $operacion, $valor);    
                    }
                }
            }
        }

        $query->orderBy($tablaOrden . $fieldName, $order);

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
            $fileName="MoviCredSector.$typeExp";
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
                /* OJO no se puede modificar stm_ingreso 
                foreach($multipleRows AS &$row) {
                    $fecha = date_create($row['stm_ingreso'], $timezoneGMT)->setTimeZone($timezoneApp);
                    $row['stm_ingreso'] = date_format($fecha,"d/m/Y H:i:s");
                }*/
                $writer->addRows($multipleRows->toArray());
            });            
            $writer->close();
            return;
        }
    }

    private static function getTabla($campo) {
        $tabla = "";
        switch ($campo) {
            case "nro_documento":
            case "nom_persona":
            case "ape_persona":
                $tabla = "maesPersonas.";
                break;
            case "cod_sector":
                $tabla = "habiCredSectores.";
                break;
            case "nom_ou":
                $tabla = "maesUnidadesOrganiz.";
                break;
            case "nom_sector":
                $tabla = "maesSectores.";
                break;
            case "ref_credencial":
                $tabla = "maesAliasCred.";
                break;
            default:
                $tabla = "moviCredSector.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"stm_ingreso", "name" => "Fecha");
                    $columnDefs[] = array("prop"=>"tiempo_permanencia", "name" => "Tiempo Permanencia");
                    $columnDefs[] = array("prop"=>"cod_credencial", "name" => "Cód. Tarjeta", "key" => "cod_credencial");
                    $columnDefs[] = array("prop"=>"ref_credencial", "name" => "Ref. Tarjeta");
                    $columnDefs[] = array("prop"=>"nom_persona", "name" => "Nombre");
                    $columnDefs[] = array("prop"=>"ape_persona", "name" => "Apellido");
                    $columnDefs[] = array("prop"=>"nro_documento", "name" => "Nro. Documento");
                    $columnDefs[] = array("prop"=>"cod_sector", "name" => "Cód. Sector", "key" => "cod_sector");
                    $columnDefs[] = array("prop"=>"nom_sector", "name" => "Sector");
                    $columnDefs[] = array("prop"=>"nom_ou", "name" => "Organización");
                    
            break;
            default:
                    $columnDefs[] = array("field"=>"stm_ingreso","displayName"=>"Fecha","type"=>"date","cellFilter"=>"ftDateTime");
                    $columnDefs[] = array("field"=>"tiempo_permanencia","displayName"=>"Tiempo Permanencia","type"=>"");
                    $columnDefs[] = array("field"=>"cod_credencial","displayName"=>"Cód. Tarjeta", "cellFilter" => "ftTarjeta");
                    $columnDefs[] = array("field"=>"ref_credencial","displayName"=>"Ref. Tarjeta");
                    $columnDefs[] = array("field"=>"nom_persona","displayName"=>"Nombre");
                    $columnDefs[] = array("field"=>"ape_persona","displayName"=>"Apellido");
                    $columnDefs[] = array("field"=>"nro_documento","displayName"=>"Nro. Documento");
                    $columnDefs[] = array("field"=>"cod_sector","displayName"=>"Cód. Sector", "visible" => false);
                    $columnDefs[] = array("field"=>"nom_sector","displayName"=>"Sector");
                    $columnDefs[] = array("field"=>"nom_ou","displayName"=>"Organización");
        }
        $columnKeys = ['cod_credencial','cod_sector'];
        
        $filtros[] = array('id' => 'cod_credencial', 'name' => 'Cód Tarjeta');
        $filtros[] = array('id' => 'ref_credencial', 'name' => 'Ref. Tarjeta');
        $filtros[] = array('id' => 'des_persona', 'name' => 'Apellido y Nombre');
        $filtros[] = array('id' => 'nro_documento', 'name' => 'Nro. Documento');
        $filtros[] = array('id' => 'nom_sector', 'name' => 'Sector');
        $filtros[] = array('id' => 'nom_ou', 'name' => 'Organización');

        $rango['desde'] = array('id' => 'stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @param  int  $ou_sel (Organización seleccionada)
     * @return Response
     */
    public function detalle($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_credencial = $clave[0][0];
        $cod_sector = $clave[0][1];
        
        $vaResultado = MoviCredSector::select('moviCredSector.stm_ingreso','moviCredSector.cod_credencial','maesAliasCred.ref_credencial',
        'maesPersonas.nom_persona','maesPersonas.ape_persona','maesPersonas.nro_documento','moviCredSector.cod_sector',
        'maesSectores.nom_sector','maesUnidadesOrganiz.nom_ou')
        ->leftJoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'moviCredSector.cod_credencial')
        ->leftJoin('habiCredPersona', 'habiCredPersona.cod_credencial', '=', 'moviCredSector.cod_credencial')
        ->leftJoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
        ->leftJoin('maesSectores', 'maesSectores.cod_sector', '=', 'moviCredSector.cod_sector')
        ->leftJoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
        ->where('moviCredSector.cod_credencial', $cod_credencial)
        ->where('moviCredSector.cod_sector', $cod_sector)
        ->get();
        if($vaResultado[0]) {
            $vaResultado = $vaResultado[0];
        }
        return $vaResultado;
    }

    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_credencial = $clave[0][0];
        $cod_sector = $clave[0][1];

        $vaResultado = MoviCredSector::where('cod_credencial', $cod_credencial)->where('cod_sector', $cod_sector)->first();
        if($vaResultado) {
            $vaResultado->delete();
            return response(['ok' => 'Se eliminó satisfactoriamente el movimiento'], Response::HTTP_OK);
        }else {
            return response(['error' => 'No se encontró el registro'], Response::HTTP_CONFLICT);
        }
    }

}
