<?php

namespace App\Http\Controllers\Asis;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function response;
use App\TipoNovedad;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class TiposNovedades extends Controller
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
      //$user = Auth::user();
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'),true);
        $sort = json_decode($request->input('sort'),true);
        
        $fieldName = 'tipo_novedad';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = TipoNovedad::select();
        $query = TipoNovedad::filtroQuery($query,$filtro);

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"tipo_novedad", "name" => "Tipo Novedad", "key" => "tipo_novedad");
                    $columnDefs[] = array("prop"=>"nom_novedad", "name" => "Nombre");
                    $columnDefs[] = array("prop"=>"ind_tipo_novedad", "name" => "Ind. Tipo Novedad");
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name" => "Fecha Alta");
            break;
            default:
                    $columnDefs[] = array("field"=>"tipo_novedad","displayName"=>"Tipo Novedad");
                    $columnDefs[] = array("field"=>"nom_novedad","displayName"=>"Nombre");
                    $columnDefs[] = array("field"=>"ind_tipo_novedad","displayName"=>"Ind. Tipo Novedad");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=>"Fecha Alta","type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['tipo_novedad'];
        
        $filtros[] = array('id' => 'tipo_novedad', 'name' => 'Tipo Novedad');
        $filtros[] = array('id' => 'nom_novedad', 'name' => 'Nombre');
        $filtros[] = array('id' => 'ind_tipo_novedad', 'name' => 'Ind. Tipo Novedad');

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
    public function detalle($clave, $ou_sel)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $tipo_novedad = $clave[0][0];
        
        $vaResultado = TipoNovedad::find($tipo_novedad);
        return $vaResultado;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request, TipoNovedad $tipoNovedad)
    {
        $validator = Validator::make($request->all(), [
            'tipo_novedad' => 'required',
            'ind_tipo_novedad' => 'required'
        ],
        [   
            'tipo_novedad.required' => 'Debe ingresar un Tipo Novedad',
            'ind_tipo_novedad' => 'Debe seleccionar Indicador Tipo Novedad'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $tipoNovedad->tipo_novedad = $request->input('tipo_novedad');
        $tipoNovedad->nom_novedad = $request->input('nom_novedad');
        $tipoNovedad->ind_tipo_novedad = $request->input('ind_tipo_novedad');
        TipoNovedad::addAuditoria($tipoNovedad, "A");
        $tipoNovedad->save();

        return response(['ok' => 'El Tipo Novedad fue creado satisfactoriamente con id: '.$tipoNovedad->tipo_novedad], Response::HTTP_OK);
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
        $validator = Validator::make($request->all(), [
            'tipo_novedad' => 'required',
            'ind_tipo_novedad' => 'required'
        ],
        [   
            'tipo_novedad.required' => 'Debe ingresar un Tipo Novedad',
            'ind_tipo_novedad' => 'Debe seleccionar Indicador Tipo Novedad'
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }

        $tipo_novedad = $request->input('tipo_novedad');
        
        $tipoNovedad = TipoNovedad::find($tipo_novedad);
        $tipoNovedad->nom_novedad = $request->input('nom_novedad');
        $tipoNovedad->ind_tipo_novedad = $request->input('ind_tipo_novedad');     
        TipoNovedad::addAuditoria($tipoNovedad,"M");
        $tipoNovedad->save();
        
        return response(['ok' => "Actualización exitosa #" . $tipoNovedad->tipo_novedad], Response::HTTP_OK);
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
        $tipo_novedad = $clave[0][0];

        TipoNovedad::find($tipo_novedad)->delete();
        
        return response(['ok' => 'Se eliminó satisfactoriamente el Tipo Novedad #' . $tipo_novedad], Response::HTTP_OK);
    }

    public function getTiposNovedades()
    {
        $resultado = TipoNovedad::select('tipo_novedad','nom_novedad','ind_tipo_novedad')->orderBy('nom_novedad','asc')->get();
        return $resultado;
    }

}
