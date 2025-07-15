<?php

namespace App\Http\Controllers;

use App\ConfGrupoCred;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function response;
use DB;

class GruposCredenciales extends Controller
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
                return "ab_config";
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
      //$user = Auth::user();
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'),true);
        $sort = json_decode($request->input('sort'),true);
        
        $fieldName = 'cod_grupo';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = ConfGrupoCred::select();
        $query = ConfGrupoCred::filtroQuery($query,$filtro);

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_grupo", "name" => "Cód. Grupo", "key" => "cod_grupo");
                    $columnDefs[] = array("prop"=>"des_grupo", "name" => "Descripción");
                    $columnDefs[] = array("prop"=>"cant_max_ingresos", "name" => "Cant. Máx. Ingresos Simultáneos");
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name" => "Fecha Alta", "pipe" => "ftDateTime" );
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_grupo","displayName"=>"Cód. Grupo");
                    $columnDefs[] = array("field"=>"des_grupo","displayName"=>"Descripción");
                    $columnDefs[] = array("field"=>"cant_max_ingresos","displayName"=>"Cant. Máx. Ingresos Simultáneos");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=>"Fecha Alta","type"=>"date","cellFilter"=>"ftDateTime");
        }

        $columnKeys = ['cod_grupo'];
        
        $filtros[] = array('id' => 'cod_grupo', 'name' => 'Cód. Grupo');
        $filtros[] = array('id' => 'des_grupo', 'name' => 'Descripción');
        $filtros[] = array('id' => 'cant_max_ingresos', 'name' => 'Cant. Máx. Ingresos Simultáneos');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
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
        $cod_grupo = $clave[0][0];
        
        $vaResultado=  ConfGrupoCred::find($cod_grupo);
        return $vaResultado;
    }

    public function getGrupo()
    {
        $vagrupos[] = array("cod_grupo"=>"","des_grupo"=>"");        
        $query = ConfGrupoCred::select('cod_grupo','des_grupo')->get();
        if(!empty($query[0])){
            foreach($query as $row){
                $vagrupos[] = array("cod_grupo"=>$row['cod_grupo'],"des_grupo"=>$row['des_grupo']);
            }
        }
        return $vagrupos;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request, ConfGrupoCred $confGrupoCred)
    {
      /*
        $user = Auth::user();
        file_put_contents("c:/temp/archivo.txt",var_export($user,true)."\n",FILE_APPEND);
        if($user->can('store',$unidadesOrganiz)){
            file_put_contents("c:/temp/archivo.txt",var_export('can',true)."\n",FILE_APPEND);
        }
*/
        //$this->authorize("store",$unidadesOrganiz);
        $cod_grupo = ConfGrupoCred::getUuid();
        $confGrupoCred->cod_grupo = $cod_grupo;
        $confGrupoCred->des_grupo = $request->input('des_grupo');
        $confGrupoCred->cant_max_ingresos = $request->input('cant_max_ingresos');

        ConfGrupoCred::addAuditoria($confGrupoCred,"A");
        $confGrupoCred->save();

        return response(['ok' => 'El Grupo de Tarjetas fue creado satisfactoriamente con id: '.$cod_grupo], Response::HTTP_OK);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request)
    {
        $cod_grupo = $request->input('cod_grupo');
        
        $confGrupoCred = ConfGrupoCred::find($cod_grupo);
        $confGrupoCred->des_grupo = $request->input('des_grupo');
        $confGrupoCred->cant_max_ingresos = $request->input('cant_max_ingresos');
        
        ConfGrupoCred::addAuditoria($confGrupoCred,"M");
        $confGrupoCred->save();
        
        return response(['ok' => "Actualización exitosa #".$cod_grupo], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_grupo = $clave[0][0];
        $confGrupoCred = ConfGrupoCred::find($cod_grupo);
        $confGrupoCred->delete();
        return response(['ok' => 'Se eliminó satisfactoriamente el Grupo de Tarjetas #'.$cod_grupo], Response::HTTP_OK);
    }

}
