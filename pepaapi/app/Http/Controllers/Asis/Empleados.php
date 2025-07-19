<?php

namespace App\Http\Controllers\Asis;

use App\Helpers\ConfigParametro;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Http\Response;
use function response;
use App\Http\Controllers\Controller;
use App\Empleado;
use DateTimeZone;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use App\Traits\Libgeneral;

class Empleados extends Controller
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

    public function index(Request $request, $export)
    {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'),true);
        $sort = json_decode($request->input('sort'),true);
        
        $fieldName = 'cod_empleado';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        
        $tablaOrden = self::getTabla($fieldName);        
        $query = Empleado::select('maesEmpleados.cod_empleado','maesEmpleados.cod_empresa','maesEmpleados.cod_persona','maesEmpleados.nom_persona',
                                'maesEmpleados.ape_persona','maesEmpleados.cod_sexo','maesEmpleados.cod_tipo_doc','maesEmpleados.nro_documento',
                                'maesEmpleados.email','maesEmpleados.obj_dias_horarios','maesEmpleados.ind_activo','maesEmpresas.nom_empresa',
                                'maesEmpleados.aud_stm_ingreso', 'maesEmpleados.fec_alta')
                            ->join('maesEmpresas','maesEmpresas.cod_empresa','=','maesEmpleados.cod_empresa');
        if(count($filtro['json'])>0){
            foreach($filtro['json'] as $filtro){
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if($valor != "" && $nombre != "" && $operacion != ""){
                    
                    if ($nombre == "aud_stm_ingreso")
                        $nombre = "fec_alta";

                    if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }
                    $tabla = self::getTabla($nombre);
                    if($operacion == "LIKE")
                        $valor = "%".$valor."%";
                    
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(maesEmpleados.nom_persona, maesEmpleados.ape_persona, maesEmpleados.nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
                    }else{
                        $query->where($tabla.$nombre,$operacion,$valor);
                    }
                }
            }
        }
        
        $query->orderBy($tablaOrden.$fieldName, $order);
        
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
            $fileName="Empleados.$typeExp";
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
                    if ($row['obj_dias_horarios'])
                        $row['obj_dias_horarios'] = json_encode($row['obj_dias_horarios'], true);
                }
                $writer->addRows($arExport);
                unset($arExport);
            });            
            $writer->close();
            return;
        }
    }
    
    private static function getTabla($campo){
        $tabla = "";
        switch($campo){
            case "nom_empresa":
                $tabla = "maesEmpresas.";
                break;
            default:
                $tabla = "maesEmpleados.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_empleado", "name"=> __("Cod. Empleado"), "key" => "cod_empleado");
                    $columnDefs[] = array("prop"=>"cod_empresa", "name"=> __("Cod. Empresa"), "key" => "cod_empresa", "visible" => false);
                    $columnDefs[] = array("prop"=>"nom_empresa", "name"=> __("Empresa"));
                    $columnDefs[] = array("prop"=>"cod_persona", "name"=> __("Cod. Persona"));
                    $columnDefs[] = array("prop"=>"ape_persona", "name"=> __("Apellido"));
                    $columnDefs[] = array("prop"=>"nom_persona", "name"=> __("Nombre"));
                    $columnDefs[] = array("prop"=>"cod_sexo", "name"=> __("Sexo"));
                    $columnDefs[] = array("prop"=>"cod_tipo_doc", "name"=> __("Tipo Doc."));
                    $columnDefs[] = array("prop"=>"nro_documento", "name"=> __("Nro. Doc."));
                    $columnDefs[] = array("prop"=>"email", "name"=> __("Email"));
                    //$columnDefs[] = array("prop"=>"obj_dias_horarios", "name"=> __("Días/Horarios"));
                    $columnDefs[] = array("prop"=>"ind_activo", "name"=> __("Activo"));
                    $columnDefs[] = array("prop"=>"fec_alta", "name"=> __("Fecha Alta"));
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_empleado", "displayName"=> __("Cod. Empleado"));
                    $columnDefs[] = array("field"=>"cod_empresa", "displayName"=> __("Cod. Empresa"), "visible" => false);
                    $columnDefs[] = array("field"=>"nom_empresa", "displayName"=> __("Empresa"));
                    $columnDefs[] = array("field"=>"cod_persona", "displayName"=> __("Cod. Persona"));
                    $columnDefs[] = array("field"=>"ape_persona", "displayName"=> __("Apellido"));
                    $columnDefs[] = array("field"=>"nom_persona", "displayName"=> __("Nombre"));
                    $columnDefs[] = array("field"=>"cod_sexo", "displayName"=> __("Sexo"));
                    $columnDefs[] = array("field"=>"cod_tipo_doc", "displayName"=> __("Tipo Doc."));
                    $columnDefs[] = array("field"=>"nro_documento", "displayName"=> __("Nro. Doc."));
                    $columnDefs[] = array("field"=>"email", "displayName"=> __("Email"));
                    //$columnDefs[] = array("field"=>"obj_dias_horarios", "displayName"=> __("Días/Horarios"));
                    $columnDefs[] = array("field"=>"ind_activo", "displayName"=> __("Activo"),"cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"fec_alta","displayName"=> __("Fecha Alta"),"type"=>"date","cellFilter"=>"ftDate");
        }
        $columnKeys = ['cod_empleado', 'cod_empresa'];
        
        $filtros[] = array('id' => 'cod_empleado', 'name'=> __("Cod. Empleado"));
        $filtros[] = array('id' => 'nom_empresa', 'name'=> __("Empresa"));
        $filtros[] = array('id' => 'cod_persona', 'name'=> __("Cod. Persona"));
        $filtros[] = array('id' => 'des_persona', 'name'=> __("Apellido y Nombre"));
        $filtros[] = array('id' => 'nro_documento', 'name'=> __("Nombre"));

        $rango['desde'] = array('id' => 'fec_alta', 'tipo' => 'date');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public function detalle($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
		if (empty($clave[0]))
            return response(['error'=> __("Debe seleccionar registro")], Response::HTTP_CONFLICT); 
        $cod_empleado = $clave[0][0];
        $cod_empresa = $clave[0][1];
        
        $resultado = Empleado::select('maesEmpleados.cod_empleado','maesEmpleados.cod_empresa','maesEmpleados.cod_persona','maesEmpleados.nom_persona',
                'maesEmpleados.ape_persona','maesEmpleados.cod_sexo','maesEmpleados.cod_tipo_doc','maesEmpleados.nro_documento','maesEmpleados.nro_documento_ant',
                'maesEmpleados.email','maesEmpleados.obj_dias_horarios','maesEmpleados.ind_activo', 'maesEmpleados.fec_alta','maesEmpresas.nom_empresa')
                ->join('maesEmpresas','maesEmpresas.cod_empresa','=','maesEmpleados.cod_empresa')
                ->where('maesEmpleados.cod_empleado','=',$cod_empleado)
                ->where('maesEmpleados.cod_empresa','=',$cod_empresa)
                ->get();        
        if(count($resultado)>0){
            $resultado = $resultado[0];
            $busq_persona = new \stdClass();
            $busq_persona->cod_persona = $resultado->cod_persona;
            $busq_persona->des_persona = $resultado->nom_persona." ".$resultado->ape_persona." ".$resultado->nro_documento;
            $busq_persona->nro_documento = $resultado->nro_documento;
            $resultado->busq_persona = $busq_persona;
        }
        
        return $resultado;
    }

    //Alta de usuario
    public function store(Request $request, Empleado $empleado)
    {        
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'cod_persona' => 'required',
            'cod_empleado' => 'required',
            'nom_persona' => 'required',
            'ape_persona' => 'required',
            'cod_sexo' => 'required',
            'cod_tipo_doc' => 'required',
            'nro_documento' => 'required',
            'fec_alta' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Empresa',
            'cod_persona.required' => 'Debe seleccionar una Persona',
            'cod_empleado.required' => 'Debe ingresar Cód. Empleado',
            'nom_persona.required' => 'Debe ingresar un Nombre',
            'ape_persona.required' => 'Debe ingresar un Apellido',
            'cod_sexo.required' => 'Debe seleccionar un Sexo',
            'cod_tipo_doc.required' => 'Debe seleccionar un Tipo Documento',
            'nro_documento.required' => 'Debe ingresar un Nro. Documento',
            'fec_alta.required' => 'Debe ingresar una Fecha Alta'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $empleado->cod_empleado = $request->input('cod_empleado');
        $empleado->cod_empresa = $request->input('cod_empresa');
        $empleado->cod_persona = $request->input('cod_persona');
        $empleado->nom_persona = $request->input('nom_persona');
        $empleado->ape_persona = $request->input('ape_persona');
        $empleado->cod_sexo = $request->input('cod_sexo');
        $empleado->cod_tipo_doc = $request->input('cod_tipo_doc');
        $empleado->nro_documento = $request->input('nro_documento');
        $empleado->nro_documento_ant = $request->input('nro_documento');
        $empleado->email = $request->input('email');
        $empleado->obj_dias_horarios = $request->input('obj_dias_horarios');        
        $empleado->ind_activo = $request->input('ind_activo');
        $empleado->fec_alta = $request->input('fec_alta');
        Empleado::addAuditoria($empleado, "A");
        $empleado->save();

        return response(['ok' => __('El empleado fue creado satisfactoriamente con identificador :COD_EMPLEADO',['COD_EMPLEADO'=>$empleado->cod_empleado])], Response::HTTP_OK);
    }

    //Alta de usuario
    public function storeHorarios(Request $request, Empleado $empleado)
    {
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'empleadosSel' => 'required'
        ],
        [   
            'cod_empresa.required' => 'Debe seleccionar una Empresa',
            'empleadosSel.required' => 'Debe seleccionar una Persona'
        ]);

        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $empleadosSel = $request->input('empleadosSel');
        $cod_empresa = $request->input('cod_empresa');
        $obj_dias_horarios = $request->input('obj_dias_horarios');

        foreach($empleadosSel as $cod_empleado){
            $empleado = Empleado::where("cod_empleado","=",$cod_empleado)->where("cod_empresa","=",$cod_empresa)->first();
            $empleado->obj_dias_horarios = $obj_dias_horarios;
            Empleado::addAuditoria($empleado, "M");
            $empleado->save();
        }

        return response(['ok'=>__('Actualización existosa')], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_empleado' => 'required',
            'cod_empresa' => 'required',
            'nom_persona' => 'required',
            'ape_persona' => 'required',
            'cod_sexo' => 'required',
            'cod_tipo_doc' => 'required',
            'nro_documento' => 'required',
            'fec_alta' => 'required'
        ],
        [   
            'cod_empleado.required' => 'Debe seleccionar un Empleado',
            'cod_empresa.required' => 'Debe seleccionar una Empresa',
            'nom_persona.required' => 'Debe ingresar un Nombre',
            'ape_persona.required' => 'Debe ingresar un Apellido',
            'cod_sexo.required' => 'Debe seleccionar un Sexo',
            'cod_tipo_doc.required' => 'Debe seleccionar un Tipo Documento',
            'nro_documento.required' => 'Debe ingresar un Nro. Documento',
            'fec_alta.required' => 'Debe ingresar Fecha Alta'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        $cod_empleado = $request->input('cod_empleado');
        $cod_empresa = $request->input('cod_empresa');

        $empleado = Empleado::where("cod_empleado","=",$cod_empleado)->where("cod_empresa","=",$cod_empresa)->first();        
        $nro_documento_ant = $empleado->nro_documento;

        $empleado->cod_persona = $request->input('cod_persona');
        $empleado->nom_persona = $request->input('nom_persona');
        $empleado->ape_persona = $request->input('ape_persona');
        $empleado->cod_sexo = $request->input('cod_sexo');
        $empleado->cod_tipo_doc = $request->input('cod_tipo_doc');
        $empleado->nro_documento = $request->input('nro_documento');
        $empleado->nro_documento_ant = $nro_documento_ant;
        $empleado->email = $request->input('email');
        $empleado->obj_dias_horarios = $request->input('obj_dias_horarios');
        $empleado->ind_activo = $request->input('ind_activo');
        $empleado->fec_alta = $request->input('fec_alta');
        Empleado::addAuditoria($empleado, "M");
        $empleado->save();
        
        return response(['ok' => "Actualización exitosa #" . $cod_empleado], Response::HTTP_OK);
    }

    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_empleado = $clave[0][0];
        $cod_empresa = $clave[0][1];

        $empleado = Empleado::where("cod_empresa","=",$cod_empresa)->where("cod_empleado","=",$cod_empleado)->first();
        $empleado->delete();
        
        return response(['ok' => __('Se eliminó satisfactoriamente el empleado :COD_EMPLEADO de la empresa :COD_EMPRESA',['COD_EMPLEADO'=>$cod_empleado,'COD_EMPRESA'=>$cod_empresa] ) ], Response::HTTP_OK);
    }

    public function getEmpleados($cod_empresa)
    {
        if($cod_empresa=="")
            return response(['error'=> __("Debe selecciona Empresa")], Response::HTTP_CONFLICT);

        $resultado = Empleado::select('ape_persona','nom_persona','cod_empleado')
                    ->where('cod_empresa', '=', $cod_empresa)
                    ->where('ind_activo', '=', '1')
                    ->orderBy('ape_persona','asc')->get();
        foreach($resultado as $index=>$valor)
        {
            $resultado[$index]['des_empleado'] = $valor['ape_persona']." ".$valor['nom_persona'];
        }
        return $resultado;

    }
}
