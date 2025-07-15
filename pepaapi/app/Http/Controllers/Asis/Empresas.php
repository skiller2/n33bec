<?php

namespace App\Http\Controllers\Asis;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function response;
use App\Empresa;
use Validator;
use App\Http\Controllers\Controller;
use App\UnidadesOrganiz;

class Empresas extends Controller
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
        
        $fieldName = 'cod_empresa';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = Empresa::select();
        $query = Empresa::filtroQuery($query,$filtro);

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_empresa", "name" => "Cód. Empresa", "key" => "cod_empresa");
                    $columnDefs[] = array("prop"=>"nom_empresa", "name" => "Nombre");
                    $columnDefs[] = array("prop"=>"des_empresa", "name" => "Descripción");
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name" => "Fecha Alta");
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_empresa","displayName"=>"Cód. Empresa");
                    $columnDefs[] = array("field"=>"nom_empresa","displayName"=>"Nombre");
                    $columnDefs[] = array("field"=>"des_empresa","displayName"=>"Descripción");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=>"Fecha Alta","type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['cod_empresa'];
        
        $filtros[] = array('id' => 'cod_empresa', 'name' => 'Cód. Empresa');
        $filtros[] = array('id' => 'nom_empresa', 'name' => 'Nombre');
        $filtros[] = array('id' => 'des_empresa', 'name' => 'Descripción');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public function getEmpresas()
    {
        return  Empresa::select('cod_empresa','nom_empresa')->orderBy('nom_empresa','asc')->get();
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
        $cod_empresa = $clave[0][0];
        
        $vaResultado = Empresa::find($cod_empresa);
        return $vaResultado;
    }

    public function updateEmpresas()
    {
        $ou = UnidadesOrganiz::select()->get();
        foreach($ou as $row){
            $empresa = Empresa::where('cod_ou',$row['cod_ou'])->first();
            $audit = "M";
            if(!$empresa){
                $empresa = new Empresa;
                $audit = "A";
                $empresa->cod_empresa = Empresa::getUuid();
            }
            
            $empresa->nom_empresa = $row['nom_ou'];
            $empresa->des_empresa = $row['des_ou'];
            $empresa->cod_ou = $row['cod_ou'];
            Empresa::addAuditoria($empresa, $audit);
            $empresa->save();
        }
        //return response(['error' => 'update empresas'], Response::HTTP_CONFLICT);
        return response(['ok' => 'Organizaciones actualizadas'], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request, Empresa $empresa)
    {/*
        $validator = Validator::make($request->all(), [
            'nom_empresa' => 'required'
        ],
        [   
            'nom_empresa.required' => 'Debe ingresar un Nombre'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $cod_empresa = Empresa::getUuid();
        $empresa->cod_empresa = $cod_empresa;
        $empresa->nom_empresa = $request->input('nom_empresa');
        $empresa->des_empresa = $request->input('des_empresa');
        $empresa->cod_ou = $request->input('cod_ou');
        Empresa::addAuditoria($empresa, "A");
        $empresa->save();

        return response(['ok' => 'La Organización fue creada satisfactoriamente con id: '.$cod_empresa], Response::HTTP_OK);
        */
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {/*
        $validator = Validator::make($request->all(), [
            'cod_empresa' => 'required',
            'nom_empresa' => 'required'
        ],
        [   
            'cod_empresa' => 'Debe ingresar Cód. Organización',
            'nom_empresa.required' => 'Debe ingresar un Nombre'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $cod_empresa = $request->input('cod_empresa');
        
        $empresa = Empresa::find($cod_empresa);
        $empresa->nom_empresa = $request->input('nom_empresa');
        $empresa->des_empresa = $request->input('des_empresa');
        $empresa->cod_ou = $request->input('cod_ou');
        Empresa::addAuditoria($empresa,"M");
        $empresa->save();
        
        return response(['ok' => "Actualización exitosa #" . $cod_empresa], Response::HTTP_OK);
        */
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($clave)
    {
        /*
        $clave = json_decode(base64_decode($clave), true); 
        $cod_empresa = $clave[0][0];

        Empresa::find($cod_empresa)->delete();
        
        return response(['ok' => 'Se eliminó satisfactoriamente la empresa #' . $cod_empresa], Response::HTTP_OK);
        */
    }

}
