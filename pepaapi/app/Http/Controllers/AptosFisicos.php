<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers;

use App\AptoFisico;
use App\Imagen;
use App\Persona;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Validator;
use App\Helpers\ConfigParametro;
use App\Traits\Libgeneral;

use function response;

/**
 * Description of Imagenes
 *
 * @author fpl
 */
class AptosFisicos extends Controller {
    
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
        
        $fieldName = 'cod_persona';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        $tablaOrden = self::getTabla($fieldName);
        //DB::enableQueryLog();
        $query = Persona::select('maesPersonas.cod_persona', 'maesPersonas.nom_persona', 'maesPersonas.ape_persona', 
                'maesPersonas.cod_sexo', 'maesPersonas.cod_tipo_doc', 'maesPersonas.nro_documento', 'maesPersonas.email', 
                'maesPersonas.ind_bloqueo', 'maesPersonas.aud_stm_ingreso', 
                'maesPersAptoF.fec_otorgamiento_af', 'maesPersAptoF.fec_vencimiento_af')
                ->leftjoin('maesPersAptoF', 'maesPersAptoF.cod_persona', '=', 'maesPersonas.cod_persona');
        if(count($filtro['json'])>0){
            foreach($filtro['json'] as $filtro){
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if($valor != "" && $nombre != "" && $operacion != ""){
                    
                    if ($nombre == "des_persona") {
                        $operacion = "MATCH";
                        $valor = LibGeneral::filtroMatch($valor);
                    }

                    if($operacion == "LIKE")
                        $valor = "%".$valor."%";
                    if($operacion == "MATCH") {
                        $query->whereRaw("MATCH(nom_persona, ape_persona, nro_documento)AGAINST('$valor' IN BOOLEAN MODE)");
                    }
                    else {
                        $query->where($tablaOrden . $nombre, $operacion, $valor);    
                    }                    
                }
            }
        }

        return $query->orderBy($tablaOrden . $fieldName, $order)->paginate($pageSize);

    }

    private static function getTabla($campo) {
        $tabla = "";
        switch ($campo) {
            case "fec_otorgamiento_af":
            case "fec_vencimiento_af":
                $tabla = "maesPersAptoF.";
                break;
            default:
                $tabla = "maesPersonas.";
                break;
        }
        return $tabla;
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_persona", "name"=> __("Fecha"), "key" => "cod_persona");
                    $columnDefs[] = array("prop"=>"nom_persona", "name"=> __("Nombre"));
                    $columnDefs[] = array("prop"=>"ape_persona", "name"=> __("Apellido"));
                    $columnDefs[] = array("prop"=>"cod_sexo", "name"=> __("Sexo"));
                    $columnDefs[] = array("prop"=>"cod_tipo_doc", "name"=> __("Tipo Doc."));
                    $columnDefs[] = array("prop"=>"nro_documento", "name"=> __("Nro. Doc."));
                    $columnDefs[] = array("prop"=>"email", "name"=> __("E-mail"));
                    $columnDefs[] = array("prop"=>"ind_bloqueo", "name"=> __("Bloqueada"));
                    $columnDefs[] = array("prop"=>"fec_otorgamiento_af", "name"=> __("Fecha Otorgamiento AF"));
                    $columnDefs[] = array("prop"=>"fec_vencimiento_af", "name"=> __("Fecha Vencimiento AF"));
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name"=> __("Fecha Alta"));
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_persona","displayName"=> __("Cód. Persona"));
                    $columnDefs[] = array("field"=>"nom_persona","displayName"=> __("Nombre"));
                    $columnDefs[] = array("field"=>"ape_persona","displayName"=> __("Apellido"));
                    $columnDefs[] = array("field"=>"cod_sexo","displayName"=> __("Sexo"));
                    $columnDefs[] = array("field"=>"cod_tipo_doc","displayName"=> __("Tipo Doc."));
                    $columnDefs[] = array("field"=>"nro_documento","displayName"=> __("Nro. Doc."));
                    $columnDefs[] = array("field"=>"email","displayName"=> __("E-mail"));
                    $columnDefs[] = array("field"=>"ind_bloqueo","displayName"=> __("Bloqueada"),"cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"fec_otorgamiento_af","displayName"=> __("Fecha Otorgamiento AF"),"type"=>"date","cellFilter"=>"ftDateTime");
                    $columnDefs[] = array("field"=>"fec_vencimiento_af","displayName"=> __("Fecha Vencimiento AF"),"type"=>"date","cellFilter"=>"ftDateTime");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=> __("Fecha Alta"),"type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['cod_persona'];
        
        $filtros[] = array('id' => 'cod_persona', 'name' => __('Cód. Persona'));
        $filtros[] = array('id' => 'des_persona', 'name' => __('Apellido y Nombre'));
        $filtros[] = array('id' => 'nro_documento', 'name' => __('Nro. Documento'));

        $rango['desde'] = array('id' => 'fec_otorgamiento_af', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }
    
    public function detalle($clave) {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_persona = $clave[0][0];
        $apt=AptoFisico::find($cod_persona);
        if ($apt)
            return $apt;
            else
            return response(['error' => __('No se encontró apto físico')], Response::HTTP_NOT_FOUND);
            
    }
    public function update(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'nom_persona' => 'required',
            'ape_persona' => 'required',
            'cod_sexo' => 'required',
            'cod_tipo_doc' => 'required',
            'nro_documento' => 'required',
        ],['nom_persona.required'=> __("Debe ingresar Nombre"),
            'ape_persona.required'=> __("Debe ingresar Apellido"),
            'cod_sexo.required'=> __("Debe seleccionar Sexo"),
            'cod_tipo_doc.required'=> __("Debe ingresar Tipo Documento"),
            'nro_documento.required'=> __("Debe ingresar Nro. Documento")]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        $img_apto_fisico = $request->input('img_apto_fisico');
        $fec_otorgamiento_af = $request->input('fec_otorgamiento_af');

        if($img_apto_fisico && !$fec_otorgamiento_af) {
            return response(['error' => __('Debe ingresar Fecha Otorgamiento Apto Físico')], Response::HTTP_CONFLICT);
        }

        $cod_persona = $request->input('cod_persona');
        
        

        $persona = Persona::find($cod_persona);
        $persona->email = $request->input('email');
        Persona::addAuditoria($persona,"M");
        $persona->save();
        
        if($request->input('img_persona')){
            $auditoria="M";
            $imagenes = Imagen::select()->where('cod_persona',$cod_persona)->first();
            if(!$imagenes){
                $imagenes = new Imagen;
                $imagenes->cod_persona = $cod_persona;
                $auditoria="A";
            }
            $imagenes->img_persona = $request->input('img_persona');
            Imagen::addAuditoria($imagenes,$auditoria);
            $imagenes->save();
        }

        if($img_apto_fisico){

            $plazo_vigencia_apto_fisico = ConfigParametro::get('PLAZO_VIGENCIA_APTO_FISICO', false);
            if(!$plazo_vigencia_apto_fisico){
                $plazo_vigencia_apto_fisico = "1Y";
            }
            $fec_vencimiento_af = Persona::addDateDiff($plazo_vigencia_apto_fisico, $fec_otorgamiento_af);

            $auditoria = "M";
            $apto = AptoFisico::select()->where('cod_persona', $cod_persona)->first();
            if(!$apto){
                $apto = new AptoFisico;
                $apto->cod_persona = $cod_persona;
                $auditoria = "A";
            }
            $apto->img_apto_fisico = $img_apto_fisico;
            $apto->fec_otorgamiento_af = $fec_otorgamiento_af;
            $apto->fec_vencimiento_af = $fec_vencimiento_af;
            $apto->stm_notificacion = null;
            AptoFisico::addAuditoria($apto, $auditoria);
            $apto->save();
        }

        return response(['ok' => __('Actualización exitosa :COD_PERSONA',['COD_PERSONA'=>$cod_persona])], Response::HTTP_OK);
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
        $cod_persona = $clave[0][0];
        
        if ($apto = AptoFisico::find($cod_persona)) {
            $apto->delete();
            return response(['ok' => __('Se eliminó satisfactoriamente el apto físico de la persona :COD_PERSONA',['COD_PERSONA'=>$cod_persona])], Response::HTTP_OK);
        }

        return response(['error' => __('La persona :COD_PERSONA no posee apto físico',['COD_PERSONA'=>$cod_persona])], Response::HTTP_CONFLICT);
        
    }
}
