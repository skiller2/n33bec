<?php

namespace App\Http\Controllers\Asis;

use App\Helpers\ConfigParametro;
use App\Registro;
use App\Empleado;
use App\Empresa;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DateTimeZone;
use function response;
use App\FeriadoAsis;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use App\Novedad;
use App\PermanenteOK;
use Illuminate\Support\Facades\DB;
use App\Traits\Libgeneral;
use Illuminate\Support\Facades\Validator;


class Registros extends Controller
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
        
        $fieldName = 'fec_registro';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTabla($fieldName);
        if($fieldName == "nom_novedad_trabajo") {
            $fieldName = "nom_novedad";
        }
        
        $query = Registro::select('moviRegistro.fec_registro','moviRegistro.cod_empleado','maesEmpleados.ape_persona','maesEmpleados.nom_persona',
                'confTipoNovedad.nom_novedad','confTipoNovedadTrabajo.nom_novedad as nom_novedad_trabajo',
                'moviRegistro.ind_feriado', 'moviRegistro.ind_tarde', 'moviRegistro.ind_dia_laborable',
                'moviRegistro.hora_ingreso_esperado','moviRegistro.hora_egreso_esperado', 
                'moviRegistro.hora_ingreso','moviRegistro.hora_egreso','moviRegistro.tm_trabajado','moviRegistro.tm_tardanza','moviRegistro.tm_extra',
                'moviRegistro.cod_empresa','moviRegistro.ind_modif_horarios','moviRegistro.ind_modif_novedad','moviRegistro.aud_stm_ingreso')
                ->leftjoin('maesEmpleados', 'maesEmpleados.cod_empleado', '=', 'moviRegistro.cod_empleado')
                ->leftjoin('maesEmpresas', 'maesEmpresas.cod_empresa', '=', 'moviRegistro.cod_empresa')
                ->leftjoin('confTipoNovedad', 'confTipoNovedad.tipo_novedad', '=', 'moviRegistro.tipo_novedad')
                ->leftjoin('confTipoNovedad as confTipoNovedadTrabajo', 'confTipoNovedadTrabajo.tipo_novedad', '=', 'moviRegistro.tipo_novedad_trabajo');        
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($nombre == "des_empleado") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }
                    if ($operacion == "LIKE")
                            $valor = "%" . $valor . "%";
                    $tabla = self::getTabla($nombre);
                    if ($nombre == "nom_novedad_trabajo") {
                        $nombre = "nom_novedad";
                    }
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
            $fileName="Registro.$typeExp";
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
                    $fecha = date_create($row['fec_registro'], $timezoneGMT)->setTimeZone($timezoneApp);                    
                    $row['fec_registro'] = date_format($fecha,"d/m/Y");
                    $row['ind_feriado'] = ($row['ind_feriado'] == "1") ? "Sí" : "No";
                    $row['ind_tarde'] = ($row['ind_tarde'] == "1") ? "Sí" : "No";
                    $row['ind_dia_laborable'] = ($row['ind_dia_laborable'] == "1") ? "Sí" : "No";
                    $row['ind_modif_horarios'] = ($row['ind_modif_horarios'] == "1") ? "Sí" : "No";
                    $row['ind_modif_novedad'] = ($row['ind_modif_novedad'] == "1") ? "Sí" : "No";
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
            case "nom_novedad_trabajo":
                $tabla = "confTipoNovedadTrabajo.";
                break;
            default:
                $tabla = "moviRegistro.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"fec_registro", "name"=> __("Fecha"), "key" => "fec_registro");
                    $columnDefs[] = array("prop"=>"cod_empleado", "name"=> __("Cód. Empleado"), "key" => "cod_empleado");
                    $columnDefs[] = array("prop"=>"ape_persona", "name"=> __("Apellido Empleado"));
                    $columnDefs[] = array("prop"=>"nom_persona", "name"=> __("Nombre Empleado"));
                    $columnDefs[] = array("prop"=>"nom_novedad", "name"=> __("Novedad"));
                    $columnDefs[] = array("prop"=>"nom_novedad_trabajo", "name"=> __("Novedad Trabajo"), "visible" => false);
                    $columnDefs[] = array("prop"=>"ind_feriado", "name"=> __("Feriado"));
                    $columnDefs[] = array("prop"=>"ind_tarde", "name"=> __("Tarde"));
                    $columnDefs[] = array("prop"=>"ind_dia_laborable", "name"=> __("Día Laborable"));
                    $columnDefs[] = array("prop"=>"hora_ingreso_esperado", "name"=> __("Hora Ingreso Esperado"));
                    $columnDefs[] = array("prop"=>"hora_egreso_esperado", "name"=> __("Hora Egreso Esperado"));
                    $columnDefs[] = array("prop"=>"hora_ingreso", "name"=> __("Hora Ingreso"));
                    $columnDefs[] = array("prop"=>"hora_egreso", "name"=> __("Hora Egreso"));
                    $columnDefs[] = array("prop"=>"tm_trabajado", "name"=> __("Trabajado"));
                    $columnDefs[] = array("prop"=>"tm_tardanza", "name"=> __("Tardanza"));
                    $columnDefs[] = array("prop"=>"tm_extra", "name"=> __("Extra"));
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name"=> __("Fecha Alta"), "visible" => false);
                    $columnDefs[] = array("prop"=>"cod_empresa", "name"=> __("Cód. Organización"), "key" => "cod_empresa", "visible" => false);
                    $columnDefs[] = array("prop"=>"tipo_novedad", "name"=> __("Tipo Novedad"), "visible" => false);
                    $columnDefs[] = array("prop"=>"nom_empresa", "name"=> __("Organización"), "visible" => false);
            break;
            default:
                    $columnDefs[] = array("field"=>"fec_registro", "displayName"=> __("Fecha"), "type"=>"date", "cellFilter"=>"ftDate");
                    $columnDefs[] = array("field"=>"cod_empleado", "displayName"=> __("Cód. Empleado"));
                    $columnDefs[] = array("field"=>"ape_persona", "displayName"=> __("Apellido Empleado"));
                    $columnDefs[] = array("field"=>"nom_persona", "displayName"=> __("Nombre Empleado"));
                    $columnDefs[] = array("field"=>"nom_novedad", "displayName"=> __("Novedad"));
                    $columnDefs[] = array("field"=>"nom_novedad_trabajo", "displayName"=> __("Novedad Trabajo"), "visible" => false);
                    $columnDefs[] = array("field"=>"ind_feriado", "displayName"=> __("Feriado"), "cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"ind_tarde", "displayName"=> __("Tarde"), "cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"ind_dia_laborable", "displayName"=> __("Día Laborable"), "cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"hora_ingreso_esperado", "displayName"=> __("Hora Ingreso Esperado"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"hora_egreso_esperado", "displayName"=> __("Hora Egreso Esperado"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"hora_ingreso", "displayName"=> __("Hora Ingreso"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"hora_egreso", "displayName"=> __("Hora Egreso"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"tm_trabajado", "displayName"=> __("Trabajado"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"tm_tardanza", "displayName"=> __("Tardanza"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"tm_extra", "displayName"=> __("Extra"), "cellFilter"=>"ftHorarios");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso", "displayName"=> __("Fecha Alta"),"type"=>"date","cellFilter"=>"ftDateTime", "visible" => false);
                    $columnDefs[] = array("field"=>"cod_empresa", "displayName"=> __("Cód. Organización"), "visible" => false);
                    //$columnDefs[] = array("field"=>"tipo_novedad", "displayName"=> __("Tipo Novedad"), "visible" => false);
                    //$columnDefs[] = array("field"=>"nom_empresa", "displayName"=> __("Organización"), "visible" => false);
        }

        $columnKeys = ['cod_empleado','cod_empresa','fec_registro'];   
        
        $filtros[] = array('id' => 'cod_empleado', 'name'=> __("Cód. Empleado"));
        $filtros[] = array('id' => 'des_empleado', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nom_empresa', 'name'=> __("Organización"));
        $filtros[] = array('id' => 'nom_novedad', 'name'=> __("Novedad"));
        $filtros[] = array('id' => 'nom_novedad_trabajo', 'name'=> __("Novedad Trabajo"));

        $rango['desde'] = array('id' => 'fec_registro', 'tipo' => 'date');
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
    public function detalle($clave, $ou_sel)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_empleado = $clave[0][0];
        $cod_empresa = $clave[0][1];
        $fec_registro = $clave[0][2];

        $resultado = Registro::select('moviRegistro.cod_empleado', 'moviRegistro.cod_empresa', 'moviRegistro.fec_registro',
                'moviRegistro.ind_feriado', 'moviRegistro.ind_tarde', 'moviRegistro.ind_dia_laborable', 'moviRegistro.hora_ingreso_esperado',
                'moviRegistro.hora_egreso_esperado', 'moviRegistro.hora_ingreso','moviRegistro.hora_egreso','moviRegistro.json_detalle_ingreso',
                'moviRegistro.aud_stm_ingreso','moviRegistro.tm_trabajado','moviRegistro.tm_tardanza','moviRegistro.tm_extra',
                'moviRegistro.tipo_novedad','moviRegistro.des_novedad','moviRegistro.tipo_novedad_trabajo','moviRegistro.des_novedad_trabajo',
                'moviRegistro.ind_modif_horarios','moviRegistro.ind_modif_novedad',
                'maesEmpleados.nom_persona','maesEmpleados.ape_persona',
                'maesEmpresas.nom_empresa', 
                'confTipoNovedad.nom_novedad', 'confTipoNovedadTrabajo.nom_novedad as nom_novedad_trabajo')
                ->leftjoin('maesEmpleados', 'maesEmpleados.cod_empleado', '=', 'moviRegistro.cod_empleado')
                ->leftjoin('maesEmpresas', 'maesEmpresas.cod_empresa', '=', 'moviRegistro.cod_empresa')
                ->leftjoin('confTipoNovedad', 'confTipoNovedad.tipo_novedad', '=', 'moviRegistro.tipo_novedad')
                ->leftjoin('confTipoNovedad as confTipoNovedadTrabajo', 'confTipoNovedadTrabajo.tipo_novedad', '=', 'moviRegistro.tipo_novedad_trabajo')
                ->where('moviRegistro.cod_empleado','=',$cod_empleado)
                ->where('moviRegistro.cod_empresa','=',$cod_empresa)
                ->where('moviRegistro.fec_registro','=',$fec_registro)
                ->get();
        if(count($resultado)>0){
            $resultado = $resultado[0];
            $resultado['des_empleado'] = $resultado['ape_persona']." ".$resultado['nom_persona'];
            $resultado['json_detalle_ingreso'] = json_encode($resultado['json_detalle_ingreso'], true);
        }
        
        return $resultado;
    }

    public static function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'empleadosSel' => 'required',
            'fec_registro' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'empleadosSel.required' => 'Debe seleccionar una Persona',
            'fec_registro.required' => 'Debe seleccionar Fecha Registro'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $empleadosSel = $request->input('empleadosSel');
        $cod_empresa = $request->input('cod_empresa');
        $tipo_novedad = $request->input('tipo_novedad');
        $des_novedad = $request->input('des_novedad');
        $tipo_novedad_trabajo = $request->input('tipo_novedad_trabajo');
        $des_novedad_trabajo = $request->input('des_novedad_trabajo');
        $ind_feriado = ($request->input('ind_feriado')) ? 1 : 0;
        $ind_dia_laborable = ($request->input('ind_dia_laborable')) ? 1 : 0;
        $hora_ingreso_esperado = $request->input('hora_ingreso_esperado');
        $hora_egreso_esperado = $request->input('hora_egreso_esperado');
        $hora_ingreso = $request->input('hora_ingreso');
        $hora_egreso = $request->input('hora_egreso');
        $fec_registro = $request->input('fec_registro');
        $tm_trabajado = ($request->input('tm_trabajado') == '') ? '0' : $request->input('tm_trabajado');
        $tm_tardanza = ($request->input('tm_tardanza') == '') ? '0' : $request->input('tm_tardanza');
        $tm_extra = ($request->input('tm_extra') == '') ? '0' : $request->input('tm_extra');
        $ind_modif_novedad = 1;
        $ind_modif_horarios = 0;
        if ($hora_ingreso || $hora_egreso || $hora_ingreso_esperado || $hora_egreso_esperado || $tm_trabajado || $tm_tardanza || $tm_extra)
            $ind_modif_horarios = 1;

        /*$json_detalle_ingreso = array("ind_feriado" => $ind_feriado, "ind_tarde" => $ind_tarde, "ind_dia_laborable" => $ind_dia_laborable,
            "hora_ingreso_esperado" => $hora_ingreso_esperado, "hora_egreso_esperado" => $hora_egreso_esperado, "hora_ingreso" => $hora_ingreso,
            "hora_egreso" => $hora_egreso);*/
        $json_detalle_ingreso = array();

        $hora_ingreso_ts = ($hora_ingreso) ? strtotime($hora_ingreso) : 0;
        $hora_ingreso_esperado_ts = ($hora_ingreso_esperado) ? strtotime($hora_ingreso_esperado) : 0;
        $tm_tardanza_ts = ($hora_ingreso_ts > 0) ? $hora_ingreso_ts - $hora_ingreso_esperado_ts : 0;

        $param_tarde = ConfigParametro::get('TIEMPO_TOLERANCIA_TARDE_MINS', false);        
        $param_tarde = (int)$param_tarde * 60;
        $ind_tarde = ($tm_tardanza_ts > $param_tarde) ? 1 : 0;

        /*
        $hora_ingreso_ts = ($hora_ingreso) ? strtotime($hora_ingreso) : 0;
        $hora_ingreso_esperado_ts = ($hora_ingreso_esperado) ? strtotime($hora_ingreso_esperado) : 0;
        $hora_egreso_ts = ($hora_ingreso) ? strtotime($hora_ingreso) : 0;
        $hora_egreso_esperado_ts = ($hora_ingreso_esperado) ? strtotime($hora_ingreso_esperado) : 0;

        $tm_tardanza_ts = ($hora_ingreso_ts > 0) ? $hora_ingreso_ts - $hora_ingreso_esperado_ts : 0;
        $tm_trabajado_ts = ($hora_ingreso_ts > 0 && $hora_egreso_ts > 0) ? $hora_egreso_ts - $hora_ingreso_ts : 0;
        $tm_extra_ts = ($tm_trabajado_ts > ($hora_egreso_esperado_ts - $hora_ingreso_esperado_ts)) ? $tm_trabajado_ts - ($hora_egreso_esperado_ts - $hora_ingreso_esperado_ts) : 0;

        $tm_tardanza = gmdate("H:i:s", $tm_tardanza_ts);
        $tm_trabajado = gmdate("H:i:s", $tm_trabajado_ts);
        $tm_extra = gmdate("H:i:s", $tm_extra_ts);
        */
        
        foreach($empleadosSel as $cod_empleado){
            $registro = new Registro;
            $registro->cod_empleado = $cod_empleado;
            $registro->cod_empresa = $cod_empresa;
            $registro->fec_registro = $fec_registro;
            $registro->tipo_novedad = $tipo_novedad;
            $registro->des_novedad = $des_novedad;
            $registro->tipo_novedad_trabajo = $tipo_novedad_trabajo;
            $registro->des_novedad_trabajo = $des_novedad_trabajo;
            $registro->ind_feriado = $ind_feriado;
            $registro->ind_tarde = $ind_tarde;
            $registro->ind_dia_laborable = $ind_dia_laborable;
            $registro->hora_ingreso_esperado = $hora_ingreso_esperado;
            $registro->hora_egreso_esperado = $hora_egreso_esperado;
            $registro->hora_ingreso = $hora_ingreso;
            $registro->hora_egreso = $hora_egreso;
            $registro->json_detalle_ingreso = $json_detalle_ingreso;
            $registro->ind_modif_novedad = $ind_modif_novedad;
            $registro->ind_modif_horarios = $ind_modif_horarios;
            $registro->tm_tardanza = $tm_tardanza;
            $registro->tm_trabajado = $tm_trabajado;
            $registro->tm_extra = $tm_extra;
            
            Registro::addAuditoria($registro, "A");
            $registro->save();
        }
        

        return response(['ok'=> __("El registro :FEC_REGISTRO fue creado satisfactoriamente",['FEC_REGISTRO'=>$fec_registro])], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'cod_empleado' => 'required',
            'fec_registro' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'cod_empleado.required' => 'Debe seleccionar una Persona',
            'fec_registro.required' => 'Debe seleccionar Fecha Registro'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $cod_empleado = $request->input('cod_empleado');
        $cod_empresa = $request->input('cod_empresa');
        $fec_registro = $request->input('fec_registro');
        $tipo_novedad = ($request->input('tipo_novedad')) ? $request->input('tipo_novedad') : '';
        $des_novedad = $request->input('des_novedad');
        $tipo_novedad_trabajo = ($request->input('tipo_novedad_trabajo')) ? $request->input('tipo_novedad_trabajo') : '';
        $des_novedad_trabajo = $request->input('des_novedad_trabajo');
        $ind_feriado = ($request->input('ind_feriado')) ? 1 : 0;
        $ind_tarde = ($request->input('ind_tarde')) ? 1 : 0;
        $ind_dia_laborable = ($request->input('ind_dia_laborable')) ? 1 : 0;
        $hora_ingreso_esperado = $request->input('hora_ingreso_esperado');
        $hora_egreso_esperado = $request->input('hora_egreso_esperado');
        $hora_ingreso = $request->input('hora_ingreso');
        $hora_egreso = $request->input('hora_egreso');
        $tm_trabajado = ($request->input('tm_trabajado') == '') ? '0' : $request->input('tm_trabajado');
        $tm_tardanza = ($request->input('tm_tardanza') == '') ? '0' : $request->input('tm_tardanza');
        $tm_extra = ($request->input('tm_extra') == '') ? '0' : $request->input('tm_extra');
        $ind_modif_novedad = 1;
        $ind_modif_horarios = 0;

        /*$json_detalle_ingreso = array("ind_feriado" => $ind_feriado, "ind_tarde" => $ind_tarde, "ind_dia_laborable" => $ind_dia_laborable,
            "hora_ingreso_esperado" => $hora_ingreso_esperado, "hora_egreso_esperado" => $hora_egreso_esperado, "hora_ingreso" => $hora_ingreso,
            "hora_egreso" => $hora_egreso);*/
        $json_detalle_ingreso = array();

        $hora_ingreso_ts = ($hora_ingreso) ? strtotime($hora_ingreso) : 0;
        $hora_ingreso_esperado_ts = ($hora_ingreso_esperado) ? strtotime($hora_ingreso_esperado) : 0;
        $tm_tardanza_ts = ($hora_ingreso_ts > 0) ? $hora_ingreso_ts - $hora_ingreso_esperado_ts : 0;

        $param_tarde = ConfigParametro::get('TIEMPO_TOLERANCIA_TARDE_MINS', false);        
        $param_tarde = (int)$param_tarde * 60;
        $ind_tarde = ($tm_tardanza_ts > $param_tarde) ? 1 : 0;

        $registro = Registro::where("cod_empleado","=",$cod_empleado)->where("cod_empresa","=",$cod_empresa)
                    ->where("fec_registro","=",$fec_registro)->first();

        if( $registro->hora_ingreso !== $hora_ingreso || $registro->hora_egreso !== $hora_egreso ||
            $registro->hora_ingreso_esperado !== $hora_ingreso_esperado || $registro->hora_egreso_esperado !== $hora_egreso_esperado ||
            $registro->tm_tardanza !== $tm_tardanza || $registro->tm_trabajado !== $tm_trabajado || $registro->tm_extra !== $tm_extra)
                $ind_modif_horarios = 1;
                    
        $registro->tipo_novedad = $tipo_novedad;
        $registro->des_novedad = $des_novedad;
        $registro->tipo_novedad_trabajo = $tipo_novedad_trabajo;
        $registro->des_novedad_trabajo = $des_novedad_trabajo;
        $registro->ind_feriado = $ind_feriado;
        $registro->ind_tarde = $ind_tarde;
        $registro->ind_dia_laborable = $ind_dia_laborable;
        $registro->hora_ingreso_esperado = $hora_ingreso_esperado;
        $registro->hora_egreso_esperado = $hora_egreso_esperado;
        $registro->hora_ingreso = $hora_ingreso;
        $registro->hora_egreso = $hora_egreso;
        $registro->json_detalle_ingreso = $json_detalle_ingreso;
        $registro->ind_modif_novedad = $ind_modif_novedad;
        $registro->ind_modif_horarios = $ind_modif_horarios;
        $registro->tm_tardanza = $tm_tardanza;
        $registro->tm_trabajado = $tm_trabajado;
        $registro->tm_extra = $tm_extra;
        Registro::addAuditoria($registro, "M");
        $registro->save();
        
        return response(['ok' => "Actualización exitosa"], Response::HTTP_OK);
    }

    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_empleado = $clave[0][0];
        $cod_empresa = $clave[0][1];
        $fec_registro = $clave[0][2];

        $registro = Registro::where("cod_empleado","=",$cod_empleado)->where("cod_empresa","=",$cod_empresa)
                    ->where("fec_registro","=",$fec_registro)->first();
        $registro->delete();
        
        return response(['ok'=> __("Se eliminó satisfactoriamente el registro")], Response::HTTP_OK);
    }

    public function updateEmpleados(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'fec_desde' => 'required',
            'fec_hasta' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'fec_desde.required' => 'Debe ingresar Fecha Desde',
            'fec_hasta.required' => 'Debe ingresar Fecha Hasta'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $cod_empresa = $request->input('cod_empresa');
        $fec_desde = $request->input('fec_desde');
        $fec_hasta = $request->input('fec_hasta');

        $feriados = self::getFeriados($fec_desde, $fec_hasta);
        $empleados = self::getEmpleados($cod_empresa);

        $fec_desde_carbon = new Carbon($fec_desde);
        $fec_hasta_carbon = new Carbon($fec_hasta);

        $fechas = [];
        for($date = $fec_desde_carbon; $date->lte($fec_hasta_carbon); $date->addDay()) {
            $fecha = $date->format('Y-m-d');
            $ind_feriado = (isset($feriados[$fecha])) ? "1" : "0";
            $diasem = ($ind_feriado == "1") ? "7" : $date->dayOfWeek;
            $fechas[] = array("fecha"=>$fecha,"diasem"=>$diasem, "ind_feriado" => $ind_feriado);
        }

        //file_put_contents('C:/temp/archivo.txt', var_export($empleado, true));  
        //return response(['error'=> __("Actualización Registros")], Response::HTTP_CONFLICT);

        foreach($fechas as $fecha){
            $ind_feriado = $fecha['ind_feriado'];
            $fecha_registro = $fecha['fecha'];
            $diasem = $fecha['diasem'];

            foreach($empleados as $empleado){

                $fec_alta = $empleado['fec_alta'];

                if(!$fec_alta || $fecha['fecha'] < $fec_alta || (int)$fec_alta === 0)
                    continue;

                $cod_empleado = $empleado['cod_empleado'];
                $obj_dias_horarios = $empleado['obj_dias_horarios'];
                $hora_ingreso_esperado = (isset($obj_dias_horarios[$diasem]['hi'])) ? $obj_dias_horarios[$diasem]['hi'] : "0";
                $hora_egreso_esperado = (isset($obj_dias_horarios[$diasem]['he'])) ? $obj_dias_horarios[$diasem]['he'] : "0";
                $ind_dia_laborable = ($hora_ingreso_esperado == "") ? "0" : "1"; 

                $registro = Registro::where("cod_empleado","=",$cod_empleado)->where("cod_empresa","=",$cod_empresa)
                    ->where("fec_registro","=",$fecha_registro)
                    ->first();

                $audit = "M";
                if(!$registro){
                    $registro = new Registro;
                    $registro->ind_tarde = 0;                    
                    $registro->json_detalle_ingreso = array();
                    $registro->ind_modif_horarios = 0;
                    $registro->ind_modif_novedad = 0;
                    $registro->tm_tardanza = 0;
                    $registro->tm_trabajado = 0;
                    $registro->tm_extra = 0;
                    $audit = "A";
                }

                if($registro->ind_modif_horarios == "1")
                    continue;

                $registro->cod_empleado = $cod_empleado;
                $registro->cod_empresa = $cod_empresa;
                $registro->fec_registro = $fecha_registro;                
                $registro->tipo_novedad = '';
                $registro->ind_feriado = $ind_feriado;
                $registro->ind_dia_laborable = $ind_dia_laborable;
                $registro->hora_ingreso_esperado = $hora_ingreso_esperado;
                $registro->hora_egreso_esperado = $hora_egreso_esperado;                                
                //$registro->hora_ingreso = $hora_ingreso;
                //$registro->hora_egreso = $hora_egreso;                
                Registro::addAuditoria($registro, $audit);
                $registro->save();
            }
        }

        return response(['ok' => "Actualización Empleados"], Response::HTTP_OK);
    }

    public function updateNovedades(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'fec_desde' => 'required',
            'fec_hasta' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'fec_desde.required' => 'Debe ingresar Fecha Desde',
            'fec_hasta.required' => 'Debe ingresar Fecha Hasta'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $cod_empresa = $request->input('cod_empresa');
        $fec_desde = $request->input('fec_desde');
        $fec_hasta = $request->input('fec_hasta');
//DB::connection('mysql_asis')->enableQueryLog();
        $novedades = self::getNovedades($fec_desde, $fec_hasta, $cod_empresa);

        foreach($novedades as $novedad){
            $cod_empleado = $novedad['cod_empleado'];
            $tipo_novedad = $novedad['tipo_novedad'];
            $ind_tipo_novedad = $novedad['ind_tipo_novedad'];
            $des_novedad = $novedad['des_novedad'];
            $fec_novedad_desde = $novedad['fec_novedad_desde'];
            $fec_novedad_hasta = $novedad['fec_novedad_hasta'];

            switch ($ind_tipo_novedad) {
                case "T":
                    Registro::where('cod_empleado', $cod_empleado)
                        ->where('fec_registro', '>=', $fec_novedad_desde)
                        ->where('fec_registro', '<=', $fec_novedad_hasta)
                        ->where('cod_empresa', $cod_empresa)
                        ->where('ind_modif_novedad','!=',"1")
                        ->update(['tipo_novedad_trabajo' => $tipo_novedad, 'des_novedad_trabajo' => $des_novedad]);
                    break;
                case "N":
                    Registro::where('cod_empleado', $cod_empleado)
                        ->where('fec_registro', '>=', $fec_novedad_desde)
                        ->where('fec_registro', '<=', $fec_novedad_hasta)
                        ->where('cod_empresa', $cod_empresa)
                        ->where('ind_modif_novedad','!=',"1")
                        ->update(['tipo_novedad' => $tipo_novedad, 'des_novedad' => $des_novedad]);
                    break;
                default:
                    return response(['error'=> __("Sin Indicador Tipo Novedad")], Response::HTTP_CONFLICT);
                    break;
            }
        }
//file_put_contents('C:/temp/archivo.txt', var_export(DB::connection('mysql_asis')->getQueryLog(), true));
        return response(['ok' => "Actualización Novedades"], Response::HTTP_OK);
    }

    public function updateHorarios(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'fec_desde' => 'required',
            'fec_hasta' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Organización',
            'fec_desde.required' => 'Debe ingresar Fecha Desde',
            'fec_hasta.required' => 'Debe ingresar Fecha Hasta'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $cod_empresa = $request->input('cod_empresa');
        $fec_desde = $request->input('fec_desde');
        $fec_hasta = $request->input('fec_hasta');

        $vvrespuesta = self::getMovimientos($fec_desde, $fec_hasta);
        $movimientos = $vvrespuesta['movimientos'];
        $detalle_ingreso = $vvrespuesta['detalle_ingreso'];

        //GRABAR EL IND DE LLEGADA TARDE Y GRABAR EL JSON

        foreach($movimientos as $cod_persona => $movimiento){            
            foreach($movimiento as $fecha => $detalle){
                $tm_trabajado = 0;
                $stm_ingreso = (isset($detalle['I'])) ? $detalle['I'][0] : null;
                $stm_egreso = (isset($detalle['E'])) ? end($detalle['E']) : null;

                $hora_ingreso = ($stm_ingreso) ? $stm_ingreso->format("H:i:s") : '';
                $hora_egreso = ($stm_egreso) ? $stm_egreso->format("H:i:s") : '';

                if($stm_ingreso){
                    foreach($detalle['I'] as $index => $horario) {
                        if(isset($detalle['E'][$index])) {
                            $tm_trabajado += $detalle['E'][$index]->diffInSeconds($horario);
                        }
                    }
                }

                $tm_trabajado = gmdate("H:i:s", $tm_trabajado);

                $json_detalle_ingreso = isset($detalle_ingreso[$cod_persona][$fecha]) ? json_encode($detalle_ingreso[$cod_persona][$fecha], true) : json_encode(array(), true);
                
                Registro::where('moviRegistro.fec_registro', '=', $fecha)
                    ->where('moviRegistro.ind_modif_horarios','!=',"1")
                    ->where('maesEmpleados.cod_persona', $cod_persona)
                    ->where('maesEmpleados.cod_empresa', $cod_empresa)
                    ->join("maesEmpleados",function($join){
                        $join->on('maesEmpleados.cod_empleado', '=', 'moviRegistro.cod_empleado')
                            ->on('maesEmpleados.cod_empresa', '=', 'moviRegistro.cod_empresa');
                    })
                    ->update(['moviRegistro.hora_ingreso' => $hora_ingreso, 'moviRegistro.hora_egreso' => $hora_egreso, 
                        'moviRegistro.json_detalle_ingreso' => $json_detalle_ingreso,
                        'moviRegistro.tm_trabajado' => $tm_trabajado,
                        'moviRegistro.ind_tarde' => DB::raw("IF(moviRegistro.hora_ingreso_esperado < CAST('$hora_ingreso' AS TIME), 1, 0)"),
                        'moviRegistro.tm_tardanza' => DB::raw('IF(moviRegistro.hora_ingreso IS NOT NULL, moviRegistro.hora_ingreso - moviRegistro.hora_ingreso_esperado, 0)'),
                        'moviRegistro.tm_extra' => DB::raw("IF(CAST('$tm_trabajado' AS TIME) > (moviRegistro.hora_egreso_esperado - moviRegistro.hora_ingreso_esperado), CAST('$tm_trabajado' AS TIME) - (moviRegistro.hora_egreso_esperado - moviRegistro.hora_ingreso_esperado), 0)")
                        ]);
            }
        }
        return response(['ok' => "Actualización Control Acceso"], Response::HTTP_OK);
    }

    private function getEmpleados($cod_empresa)
    {
        if($cod_empresa=="")
            return response(['error'=> __("Debe selecciona Organización")], Response::HTTP_CONFLICT);

        $resultado = Empleado::select('cod_empleado','obj_dias_horarios', 'fec_alta')
                    ->where('cod_empresa', '=', $cod_empresa)
                    ->where('ind_activo', '=', '1')
                    ->get();
        return $resultado;
    }

    private function getFeriados($fec_desde, $fec_hasta)
    {
        $feriados = array();
        $resultado = FeriadoAsis::select('fec_feriado')
                    ->where('fec_feriado','>=',$fec_desde)
                    ->where('fec_feriado','<=',$fec_hasta)
                    ->get();

        foreach($resultado as $row){
            $feriados[$row['fec_feriado']] = $row['fec_feriado'];
        }
        
        return $feriados;
    }

    private function getNovedades($fec_desde, $fec_hasta, $cod_empresa)
    {
        return Novedad::select('moviNovedades.tipo_novedad', 'moviNovedades.cod_empleado', 'moviNovedades.fec_novedad_desde', 
                    'moviNovedades.fec_novedad_hasta','moviNovedades.des_novedad', 'confTipoNovedad.ind_tipo_novedad')
                    ->join('confTipoNovedad', 'confTipoNovedad.tipo_novedad','=','moviNovedades.tipo_novedad')
                    ->where('moviNovedades.fec_novedad_desde', '>=', $fec_desde)
                    ->where('moviNovedades.fec_novedad_hasta', '<=', $fec_hasta)
                    ->where('moviNovedades.cod_empresa', '=', $cod_empresa)
                    ->get();
    }

    private function getMovimientos($fec_desde, $fec_hasta)
    {
        $permanente = PermanenteOK::select('cod_persona', 'ind_movimiento', 'stm_movimiento')
                    ->where('stm_movimiento', '>=', $fec_desde)
                    ->where('stm_movimiento', '<=', $fec_hasta.' 23:59:59')
                    ->orderBy('cod_persona','asc')
                    ->orderBy('stm_movimiento','asc')
                    ->get();

        $detalle_ingreso = array();
        $movimientos = array();
        foreach($permanente as $movimiento) {
            $cod_persona = $movimiento['cod_persona'];
            $ind_movimiento = $movimiento['ind_movimiento'];
            $stm_movimiento = $movimiento['stm_movimiento'];
			
			$fecha_obj = Carbon::parse($stm_movimiento, 'UTC');
			$fecha_obj->setTimezone('-03:00');
			$fecha = $fecha_obj->format("Y-m-d");			
            $detalle_ingreso[$cod_persona][$fecha][$ind_movimiento][] = $fecha_obj->format('H:i:s');
            $movimientos[$cod_persona][$fecha][$ind_movimiento][] = $fecha_obj;
        }

        return array("detalle_ingreso" => $detalle_ingreso, "movimientos" => $movimientos);
    }

    private static function procesaRespuesta($vvrespuesta, $vaerrors) {
        $statusCode = $vvrespuesta->status();
        $message = '';
        if ($statusCode !== Response::HTTP_OK) {
            $message = json_decode($vvrespuesta->content(), true)['error'];
            $vaerrors[] = array($statusCode, $message);
        } 
        return array($statusCode, $message, $vaerrors);
    }

    public static function syncAsis() 
    {
        $fec_desde = Carbon::yesterday()->format('Y-m-d H:i:s');
        $fec_hasta = Carbon::today()->subSeconds(1)->format('Y-m-d H:i:s');
        $vaempresas = Empresa::select('cod_empresa')->take(1)->get();
        $vaerrors = array();
        $registros = new Registros();
        
        foreach($vaempresas as $empresa) {

            $datos = array('cod_empresa' => $empresa['cod_empresa'], 'fec_desde' => $fec_desde, 'fec_hasta' => $fec_hasta);
            $request = new Request($datos);
            
            // EMPLEADOS
            try {
                $vvrespuesta = $registros->updateEmpleados($request);
                $vvrespuesta = $registros->procesaRespuesta($vvrespuesta, $vaerrors);
                $vaerrors = $vvrespuesta[2];
            } catch (\Exception $e) {
                $vaerrors[] = array($e->getCode(), $e->getMessage());
            }

            // HORARIOS
            try {
                $vvrespuesta = $registros->updateHorarios($request);
                $vvrespuesta = $registros->procesaRespuesta($vvrespuesta, $vaerrors);
                $vaerrors = $vvrespuesta[2];
            } catch (\Exception $e) {
                $vaerrors[] = array($e->getCode(), $e->getMessage());
            }

            // NOVEDADES
            try {
                $vvrespuesta = $registros->updateNovedades($request);
                $vvrespuesta = $registros->procesaRespuesta($vvrespuesta, $vaerrors);
                $vaerrors = $vvrespuesta[2];
            } catch (\Exception $e) {
                $vaerrors[] = array($e->getCode(), $e->getMessage());
            }
        }

        return array('errors' => $vaerrors);
    }

}
