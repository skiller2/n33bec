<?php

namespace App\Http\Controllers;

use App\Credencial;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SebastianBergmann\RecursionContext\Exception;

class StockCredenciales extends Controller
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
                return "ab_gestion";
            default:
                return "";
        }
    }

    public function index(Request $request, $export)
    {
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'),true);
        $sort = json_decode($request->input('sort'),true);
        
        $fieldName = 'cod_credencial';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $query = Credencial::select();
        if(count($filtro['json'])>0){
            foreach($filtro['json'] as $filtro){
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if($valor != "" && $nombre != "" && $operacion != ""){                    
                    if($nombre=="ref_credencial"){
                        $operacion="=";
                        if(is_numeric($valor)){
                            $valor = (int)$valor;
                            $valor = (string)$valor;
                        }
                    }
                    else if($nombre=="tipo_habilitacion"){
                        $operacion="=";
                        $valor = substr($valor, 0, 1);
                    }
                    if($operacion == "LIKE")
                        $valor = "%".$valor."%";
                    $query->where($nombre,$operacion,$valor);
                }
            }
        }

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_credencial", "name" => "Cód. Tarjeta", "key" => "cod_credencial");
                    $columnDefs[] = array("prop"=>"ref_credencial", "name" => "Ref. Tarjeta");
                    $columnDefs[] = array("prop"=>"tipo_credencial", "name" => "Tipo Tarjeta", "pipe" => "ftTipoCred");
                    $columnDefs[] = array("prop"=>"tipo_habilitacion", "name" => "Tipo Habilitacion", "pipe" => "ftTipoHab");
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name" => "Fecha Alta", "pipe" => "ftDateTime");
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_credencial","displayName"=>"Cód. Tarjeta","cellFilter"=>"ftTarjeta");
                    $columnDefs[] = array("field"=>"ref_credencial","displayName"=>"Ref. Tarjeta");
                    $columnDefs[] = array("field"=>"tipo_credencial","displayName"=>"Tipo Tarjeta");
                    $columnDefs[] = array("field"=>"tipo_habilitacion","displayName"=>"Tipo Habilitación","cellFilter"=>"ftTipoHab");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=>"Fecha Alta","type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['cod_credencial'];
        
        $filtros[] = array('id' => 'cod_credencial', 'name' => 'Cód. Tarjeta');
        $filtros[] = array('id' => 'ref_credencial', 'name' => 'Ref. Tarjeta');
        $filtros[] = array('id' => 'tipo_credencial', 'name' => 'Tipo Tarjeta');
        $filtros[] = array('id' => 'tipo_habilitacion', 'name' => 'Tipo Habilitación');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    public function detalle($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_credencial = $clave[0][0];
        return Credencial::find($cod_credencial);
    }

    //Alta de credencial
    public function store(Request $request)
    {
        $cod_credencial = $request->input('cod_credencial');
        $ref_credencial = $request->input('ref_credencial');
        $tipo_credencial = $request->input('tipo_credencial');
        $tipo_habilitacion = $request->input('tipo_habilitacion');

        if(!$cod_credencial || $cod_credencial == "")
            return response(['error' => 'Debe ingresar Cód. Tarjeta'], Response::HTTP_CONFLICT);
        if(!$ref_credencial || $ref_credencial == "")
            return response(['error' => 'Debe ingresar Ref. Tarjeta'], Response::HTTP_CONFLICT);
        if(!$tipo_credencial || $tipo_credencial == "")
            return response(['error' => 'Debe ingresar Tipo Tarjeta'], Response::HTTP_CONFLICT);
        if(!$tipo_habilitacion || $tipo_habilitacion == "")
            return response(['error' => 'Debe ingresar Tipo Habilitación'], Response::HTTP_CONFLICT);
        
        if(is_numeric($ref_credencial))
            $ref_credencial = (int)$ref_credencial;

        $credencial = new Credencial;
        $credencial->cod_credencial = $cod_credencial;
        $credencial->ref_credencial = $ref_credencial;
        $credencial->tipo_credencial = $tipo_credencial;
        $credencial->tipo_habilitacion = $tipo_habilitacion;
        Credencial::addAuditoria($credencial,"A");

        $credencial->save();

        return response(['ok' => 'La Tarjeta fue creada satisfactoriamente con id: '.$cod_credencial], Response::HTTP_OK);
    }

    public function update(Request $request)
    {
        $cod_credencial = $request->input('cod_credencial');
        $ref_credencial = $request->input('ref_credencial');
        $tipo_credencial = $request->input('tipo_credencial');
        $tipo_habilitacion = $request->input('tipo_habilitacion');

        if(!$cod_credencial || $cod_credencial == "")
            return response(['error' => 'Debe ingresar Tarjeta'], Response::HTTP_CONFLICT);
        if(!$ref_credencial || $ref_credencial == "")
            return response(['error' => 'Debe ingresar Ref. Tarjeta'], Response::HTTP_CONFLICT);
        if(!$tipo_credencial || $tipo_credencial == "")
            return response(['error' => 'Debe ingresar Tipo Tarjeta'], Response::HTTP_CONFLICT);
        if(!$tipo_habilitacion || $tipo_habilitacion == "")
            return response(['error' => 'Debe ingresar Tipo Habilitación'], Response::HTTP_CONFLICT);
        
        if(is_numeric($ref_credencial))
            $ref_credencial = (int)$ref_credencial;
        
        $credencial = Credencial::find($cod_credencial);
        $credencial->ref_credencial = $ref_credencial;
        $credencial->tipo_credencial = $tipo_credencial;
        $credencial->tipo_habilitacion = $tipo_habilitacion;
        Credencial::addAuditoria($credencial,"M");
        try
        {
            $credencial->save();
        }
        catch (Exception $e)
        {
            return response(['error' => 'Error grabando Tarjeta'], Response::HTTP_CONFLICT);
        }

        return response(['ok' => 'Actualización exitosa #'.$cod_credencial], Response::HTTP_OK);
    }


    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_credencial = $clave[0][0];
        
        $credencial = Credencial::find($cod_credencial);
        $credencial->delete();
        return response(['ok' => 'Se eliminó satisfactoriamente la Tarjeta #'.$cod_credencial], Response::HTTP_OK);
    }

}
