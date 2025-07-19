<?php

namespace App\Http\Controllers\Asis;

use App\Helpers\ConfigParametro;
use App\Novedad;
use App\Registro;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use function response;
use DateTimeZone;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use App\Traits\Libgeneral;
use Illuminate\Support\Facades\Validator;


class Novedades extends Controller
{
    public static function getAbility($metodo)
    {
        switch ($metodo){
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
                return "ab_asistencia";
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
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'),true);
        $sort = json_decode($request->input('sort'),true);
        
        $fieldName = 'fec_novedad_desde';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTabla($fieldName);
        
        $query = Novedad::select('moviNovedades.cod_empleado', 'maesEmpleados.ape_persona','maesEmpleados.nom_persona', 'moviNovedades.tipo_novedad', 
                'confTipoNovedad.nom_novedad','moviNovedades.fec_novedad_desde', 'moviNovedades.fec_novedad_hasta','moviNovedades.des_novedad',
                'moviNovedades.aud_stm_ingreso','maesEmpresas.nom_empresa', 'moviNovedades.cod_empresa')
                ->leftjoin('maesEmpleados', 'maesEmpleados.cod_empleado', '=', 'moviNovedades.cod_empleado')
                ->leftjoin('maesEmpresas', 'maesEmpresas.cod_empresa', '=', 'moviNovedades.cod_empresa')
                ->leftjoin('confTipoNovedad', 'confTipoNovedad.tipo_novedad', '=', 'moviNovedades.tipo_novedad');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }
                    
                    if ($operacion == "LIKE")
                            $valor = "%" . $valor . "%";
                    $tabla = self::getTabla($nombre);
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(maesEmpleados.nom_persona, maesEmpleados.ape_persona, maesEmpleados.nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
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
            $fileName="Novedades.$typeExp";
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
                    $fecha = date_create($row['fec_novedad_desde'], $timezoneGMT)->setTimeZone($timezoneApp);                    
                    $row['fec_novedad_desde'] = date_format($fecha,"d/m/Y H:i:s");

                    $fecha = date_create($row['fec_novedad_hasta'], $timezoneGMT)->setTimeZone($timezoneApp);                    
                    $row['fec_novedad_hasta'] = date_format($fecha,"d/m/Y H:i:s");
                }
                $writer->addRows($arExport);
                unset($arExport);
            });            
            $writer->close();
            return;
        }
    }

    private static function getTabla($campo) {
        $tabla = "";
        switch ($campo) {
            case "nom_persona":
            case "ape_persona":
                $tabla = "maesEmpleados.";
                break;
            case "nom_empresa":
                $tabla = "maesEmpresas.";
                break;
            case "nom_novedad":
                $tabla = "confTipoNovedad.";
                break;
            default:
                $tabla = "moviNovedades.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_empleado", "name"=> __("Cód. Empleado"), "key" => "cod_empleado");
                    $columnDefs[] = array("prop"=>"ape_persona", "name"=> __("Apellido Empleado"));
                    $columnDefs[] = array("prop"=>"nom_persona", "name"=> __("Nombre Empleado"));
                    $columnDefs[] = array("prop"=>"tipo_novedad", "name"=> __("Tipo Novedad"), "key" => "tipo_novedad");
                    $columnDefs[] = array("prop"=>"nom_novedad", "name"=> __("Nombre Novedad"));
                    $columnDefs[] = array("prop"=>"fec_novedad_desde", "name"=> __("Fecha Desde"), "key" => "fec_novedad_desde");
                    $columnDefs[] = array("prop"=>"fec_novedad_hasta", "name"=> __("Fecha Hasta"));
                    $columnDefs[] = array("prop"=>"des_novedad", "name"=> __("Descripción"));
                    $columnDefs[] = array("prop"=>"nom_empresa", "name"=> __("Organización"));
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name"=> __("Fecha Alta"));
                    $columnDefs[] = array("prop"=>"cod_empresa", "name"=> __("Cód. Organización"), "key" => "cod_empresa", "visible" => false);
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_empleado", "displayName"=> __("Cód. Empleado"));
                    $columnDefs[] = array("field"=>"ape_persona", "displayName"=> __("Apellido Empleado"));
                    $columnDefs[] = array("field"=>"nom_persona", "displayName"=> __("Nombre Empleado"));                    
                    $columnDefs[] = array("field"=>"tipo_novedad", "displayName"=> __("Tipo Novedad"));
                    $columnDefs[] = array("field"=>"nom_novedad", "displayName"=> __("Novedad"));
                    $columnDefs[] = array("field"=>"fec_novedad_desde", "displayName"=> __("Fecha Desde"), "type"=>"date", "cellFilter"=>"ftDate");
                    $columnDefs[] = array("field"=>"fec_novedad_hasta", "displayName"=> __("Fecha Hasta"), "type"=>"date", "cellFilter"=>"ftDate");
                    $columnDefs[] = array("field"=>"des_novedad", "displayName"=> __("Descripción"));
                    $columnDefs[] = array("field"=>"nom_empresa", "displayName"=> __("Organización"));
                    $columnDefs[] = array("field"=>"aud_stm_ingreso", "displayName"=> __("Fecha Alta"),"type"=>"date","cellFilter"=>"ftDateTime");
                    $columnDefs[] = array("field"=>"cod_empresa", "displayName"=> __("Cód. Organización"), "visible" => false);
        }
        $columnKeys = ['cod_empleado','cod_empresa','tipo_novedad','fec_novedad_desde'];  
        
        $filtros[] = array('id' => 'cod_empleado', 'name'=> __("Cód. Empleado"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'cod_empresa', 'name'=> __("Cód. Organización"));
        $filtros[] = array('id' => 'nom_empresa', 'name'=> __("Organización"));
        $filtros[] = array('id' => 'tipo_novedad', 'name'=> __("Tipo Novedad"));
        $filtros[] = array('id' => 'nom_novedad', 'name'=> __("Novedad"));
        $filtros[] = array('id' => 'des_novedad', 'name'=> __("Descripción"));

        $rango['desde'] = array('id' => 'fec_novedad_desde', 'tipo' => 'date');
        $rango['hasta'] = array('id' => 'fec_novedad_hasta', 'tipo' => 'date');

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @param  int  $ou_sel (Organización seleccionada)
     * @return Response
     */
    public function detalle($clave, $ou_sel)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_empleado = $clave[0][0];
        $cod_empresa = $clave[0][1];
        $tipo_novedad = $clave[0][2];
        $fec_novedad_desde = $clave[0][3];
        
        $resultado = Novedad::select('moviNovedades.cod_empleado', 'moviNovedades.cod_empresa', 'moviNovedades.tipo_novedad', 
                'moviNovedades.fec_novedad_desde', 'moviNovedades.fec_novedad_hasta', 'moviNovedades.des_novedad', 
                'maesEmpleados.nom_persona', 'maesEmpleados.ape_persona', 
                'maesEmpresas.nom_empresa', 
                'confTipoNovedad.nom_novedad')
                ->leftjoin('maesEmpleados', 'maesEmpleados.cod_empleado', '=', 'moviNovedades.cod_empleado')
                ->leftjoin('maesEmpresas', 'maesEmpresas.cod_empresa', '=', 'moviNovedades.cod_empresa')
                ->leftjoin('confTipoNovedad', 'confTipoNovedad.tipo_novedad', '=', 'moviNovedades.tipo_novedad')
                ->where('moviNovedades.cod_empleado','=',$cod_empleado)
                ->where('moviNovedades.cod_empresa','=',$cod_empresa)
                ->where('moviNovedades.tipo_novedad','=',$tipo_novedad)
                ->where('moviNovedades.fec_novedad_desde','=',$fec_novedad_desde)
                ->get();        
        if(count($resultado)>0){
            $resultado = $resultado[0];
            $resultado['des_empleado'] = $resultado['ape_persona']." ".$resultado['nom_persona'];
        }
        
        return $resultado;
    }

    public static function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'empleadosSel' => 'required',
            'tipo_novedad' => 'required',
            'fec_novedad_desde' => 'required',
            'fec_novedad_hasta' => 'required',
            'des_novedad' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'empleadosSel.required' => 'Debe seleccionar una Persona',
            'tipo_novedad.required' => 'Debe seleccionar un Tipo Novedad',
            'fec_novedad_desde.required' => 'Debe seleccionar Fecha Novedad Desde',
            'fec_novedad_hasta.required' => 'Debe seleccionar Fecha Novedad Hasta',
            'des_novedad.required' => 'Debe ingresar una Descripción'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $empleadosSel = $request->input('empleadosSel');
        foreach($empleadosSel as $cod_empleado){
            $novedad = new Novedad;
            $novedad->cod_empleado = $cod_empleado;
            $novedad->cod_empresa = $request->input('cod_empresa');
            $novedad->tipo_novedad = $request->input('tipo_novedad');
            $novedad->fec_novedad_desde = $request->input('fec_novedad_desde');
            $novedad->fec_novedad_hasta = $request->input('fec_novedad_hasta');
            $novedad->des_novedad = $request->input('des_novedad');
            
            Novedad::addAuditoria($novedad, "A");
            $novedad->save();
        }

        return response(['ok' => __('La novedad :FEC_NOVEDAD_DESDE fue creada satisfactoriamente',['FEC_NOVEDAD_DESDE'=>$novedad->fec_novedad_desde])], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'cod_empleado' => 'required',
            'tipo_novedad' => 'required',
            'fec_novedad_desde' => 'required',
            'fec_novedad_hasta' => 'required',
            'des_novedad' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'cod_empleado.required' => 'Debe seleccionar una Persona',
            'tipo_novedad.required' => 'Debe seleccionar un Tipo Novedad',
            'fec_novedad_desde.required' => 'Debe seleccionar Fecha Novedad Desde',
            'fec_novedad_hasta.required' => 'Debe seleccionar Fecha Novedad Hasta',
            'des_novedad.required' => 'Debe ingresar una Descripción'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        $cod_empleado = $request->input('cod_empleado');
        $cod_empresa = $request->input('cod_empresa');
        $tipo_novedad = $request->input('tipo_novedad');
        $fec_novedad_desde = $request->input('fec_novedad_desde');

        $novedad = Novedad::where("cod_empleado","=",$cod_empleado)->where("cod_empresa","=",$cod_empresa)
                    ->where("tipo_novedad","=",$tipo_novedad)->where("fec_novedad_desde","=",$fec_novedad_desde)
                    ->first();

        $novedad->fec_novedad_hasta = $request->input('fec_novedad_hasta');
        $novedad->des_novedad = $request->input('des_novedad');
        Novedad::addAuditoria($novedad, "M");
        $novedad->save();
        
        return response(['ok' => "Actualización exitosa"], Response::HTTP_OK);
    }

    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_empleado = $clave[0][0];
        $cod_empresa = $clave[0][1];
        $tipo_novedad = $clave[0][2];
        $fec_novedad_desde = $clave[0][3];        
        //DB::connection('mysql_asis')->enableQueryLog();

        $novedad = Novedad::select('moviNovedades.fec_novedad_hasta', 'confTipoNovedad.ind_tipo_novedad')
                    ->where("moviNovedades.cod_empleado","=",$cod_empleado)->where("moviNovedades.cod_empresa","=",$cod_empresa)
                    ->where("moviNovedades.tipo_novedad","=",$tipo_novedad)->where("moviNovedades.fec_novedad_desde","=",$fec_novedad_desde)
                    ->join('confTipoNovedad', 'confTipoNovedad.tipo_novedad','=','moviNovedades.tipo_novedad')
                    ->first();
        $fec_novedad_hasta = $novedad['fec_novedad_hasta'];
        $ind_tipo_novedad = $novedad['ind_tipo_novedad'];

        $registro = Registro::where('moviRegistro.cod_empleado', $cod_empleado)
                    ->where('moviRegistro.fec_registro', '>=', $fec_novedad_desde)
                    ->where('moviRegistro.fec_registro', '<=', $fec_novedad_hasta)
                    ->where('moviRegistro.cod_empresa', $cod_empresa);
        if($ind_tipo_novedad == "T"){
            $registro->update(['moviRegistro.tipo_novedad_trabajo'=> '', 'moviRegistro.des_novedad_trabajo' => '']);
        }else{
            $registro->update(['moviRegistro.tipo_novedad'=> '', 'moviRegistro.des_novedad' => '']);
        }

        $novedad = Novedad::where("cod_empresa", $cod_empresa)->where("cod_empleado", $cod_empleado)
                    ->where("tipo_novedad", $tipo_novedad)->where("fec_novedad_desde", $fec_novedad_desde)
                    ->first();
        $novedad->delete();

        //$q = DB::connection('mysql_asis')->getQueryLog();
        //file_put_contents('C:/temp/archivo.txt', var_export($q, true));        
        
        return response(['ok'=> __("Se eliminó satisfactoriamente la novedad")], Response::HTTP_OK);
    }

}
