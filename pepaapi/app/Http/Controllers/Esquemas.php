<?php

namespace App\Http\Controllers;

use App\Esquema;
use App\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use function response;

class Esquemas extends Controller
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
    
    public function getEsquemas($xusuario)
    {
        $stm_actual = Carbon::now()->format('Y-m-d H:i:s');
        $esquemas=array();
        if($xusuario!="false"){
            $cod_usuario = auth()->user()['cod_usuario'];
            $query = Usuario::select('obj_esquemas')->where('cod_usuario',$cod_usuario)->first();
            if($query['obj_esquemas']){                
                $obj_esquemas = $query['obj_esquemas'];
                $esquemas = Esquema::select('cod_esquema_acceso','des_esquema_acceso')
                        ->where("ind_estado","=","1")
                        ->where("fec_habilitacion_hasta",">",$stm_actual)->orWhere("fec_habilitacion_hasta","=","0000-00-00 00:00:00")
                        ->whereIn('cod_esquema_acceso',$obj_esquemas)
                        ->get();
            }   
        }
        else{
            $esquemas = Esquema::select('cod_esquema_acceso','des_esquema_acceso')
                        ->where("ind_estado","=","1")
                        ->where("fec_habilitacion_hasta",">",$stm_actual)->orWhere("fec_habilitacion_hasta","=","0000-00-00 00:00:00")
                        ->get();
        }
        return $esquemas;
    }
    
    public function getEsquemasxOU($cod_ou,$xusuario)
    {
        $stm_actual = Carbon::now()->format('Y-m-d H:i:s');
        $esquemas=array();
        if($xusuario!="false"){
            $cod_usuario = auth()->user()['cod_usuario'];
            $query = Usuario::select('obj_esquemas')->where('cod_usuario',$cod_usuario)->first();
            if($query['obj_esquemas']){
                $obj_esquemas = $query['obj_esquemas'];
                $esquemas = Esquema::select('cod_esquema_acceso','des_esquema_acceso')
                        ->where("ind_estado","=","1")
                        ->where("cod_ou","=",$cod_ou)
                        ->where("fec_habilitacion_hasta",">",$stm_actual)->orWhere("fec_habilitacion_hasta","=","0000-00-00 00:00:00")
                        ->whereIn('cod_esquema_acceso',$obj_esquemas)
                        ->get();
            }
        }
        else{
            $esquemas = Esquema::select('cod_esquema_acceso','des_esquema_acceso')
                        ->where("ind_estado","=","1")
                        ->where("cod_ou","=",$cod_ou)
                        ->where("fec_habilitacion_hasta",">",$stm_actual)->orWhere("fec_habilitacion_hasta","=","0000-00-00 00:00:00")
                        ->get();
        }
        return $esquemas;
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
        
        $fieldName = 'cod_esquema_acceso';
        $order = 'desc';        
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        
        $tablaOrden = self::getTabla($fieldName);
        //DB::enableQueryLog();
        // ...
        $query = Esquema::select('confEsquemaAcceso.cod_esquema_acceso','maesUnidadesOrganiz.nom_ou','confEsquemaAcceso.des_esquema_acceso',
                'confEsquemaAcceso.obj_intervalos_habiles','confEsquemaAcceso.obj_intervalos_nohabiles','confEsquemaAcceso.obj_intervalos_mixtos',
                'confEsquemaAcceso.ind_estado','confEsquemaAcceso.aud_stm_ingreso','confEsquemaAcceso.cod_ou','confEsquemaAcceso.fec_habilitacion_hasta')
                ->join('maesUnidadesOrganiz','maesUnidadesOrganiz.cod_ou','=','confEsquemaAcceso.cod_ou');
        if(count($filtro['json'])>0){
            foreach($filtro['json'] as $filtro){
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if($valor != "" && $nombre != "" && $operacion != ""){
                    if($operacion == "LIKE")
                        $valor = "%".$valor."%";
                        $tabla = self::getTabla($nombre);
                    $query->where($tabla.$nombre,$operacion,$valor);
                }
            }
        }
        
        return $query->orderBy($tablaOrden.$fieldName, $order)->paginate($pageSize);
    }
    
    private static function getTabla($campo){
        $tabla = "";
        switch($campo){
            case "nom_ou":
                $tabla = "maesUnidadesOrganiz.";
                break;
            default:
                $tabla = "confEsquemaAcceso.";
                break;
        }
        return $tabla;
    }
    
    public static function cleanCaches(){
        Cache::forget("FECHA_ACTUAL");
        Cache::forget("ESQUEMAS");
    //   Reinicia procesos en segundo plano
        Cache::forever("daemon_conf_ver", (int)Cache::get("daemon_conf_ver")+1);
    }
    
    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_esquema_acceso", "name" => "Cód. Esquema Acceso", "key" => "cod_esquema_acceso");
                    //$columnDefs[] = array("prop"=>"nom_ou", "name" => "Organización");
                    $columnDefs[] = array("prop"=>"des_esquema_acceso", "name" => "Descripción");
                    $columnDefs[] = array("prop"=>"obj_intervalos_habiles", "name" => "Intervalos Hábiles","pipe" => "ftInterval");
                    $columnDefs[] = array("prop"=>"obj_intervalos_nohabiles", "name" => "Intervalos No Hábiles","pipe" => "ftInterval");
                    $columnDefs[] = array("prop"=>"obj_intervalos_mixtos", "name" => "Intervalos Mixtos","pipe" => "ftInterval");
                    $columnDefs[] = array("prop"=>"ind_estado", "name" => "Esquema Activo","pipe" => "ftBoolean");
                    $columnDefs[] = array("prop"=>"fec_habilitacion_hasta", "name" => "Fecha Hasta Hab.", "pipe" => "ftDate", "searchtype" => "date");
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name" => "Fecha Alta", "pipe" => "ftDateTime", "searchtype" => "date");
                    $columnDefs[] = array("prop"=>"cod_ou", "name" => "Organización", "key" => "cod_ou", "pipe" => "ftOU");
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_esquema_acceso","displayName"=>"Cód. Esquema Acceso");        
                    $columnDefs[] = array("field"=>"nom_ou","displayName"=>"Organización");
                    $columnDefs[] = array("field"=>"des_esquema_acceso","displayName"=>"Descripción");
                    $columnDefs[] = array("field"=>"obj_intervalos_habiles","displayName"=>"Intervalos Hábiles","cellFilter"=>"ftDesdeHasta");
                    $columnDefs[] = array("field"=>"obj_intervalos_nohabiles","displayName"=>"Intervalos No Hábiles","cellFilter"=>"ftDesdeHasta");
                    $columnDefs[] = array("field"=>"obj_intervalos_mixtos","displayName"=>"Intervalos Mixtos","cellFilter"=>"ftDesdeHasta");
                    $columnDefs[] = array("field"=>"ind_estado","displayName"=>"Esquema Activo","cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"fec_habilitacion_hasta","displayName"=>"Fecha Hasta Hab.","type"=>"date","cellFilter"=>"ftDateTime" );
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=>"Fecha Alta","type"=>"date","cellFilter"=>"ftDateTime" );
                    $columnDefs[] = array("field"=>"cod_ou","displayName"=>"Organización","visible"=>false);
        }
        $columnKeys = ['cod_esquema_acceso','cod_ou'];
        
        $filtros[] = array('id' => 'cod_esquema_acceso', 'name' => 'Cód. Esquema Acceso');
        $filtros[] = array('id' => 'nom_ou', 'name' => 'Organización');
        $filtros[] = array('id' => 'des_esquema_acceso', 'name' => 'Descripción');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */    
    public static function detalle($clave,$cod_ou_sel)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_esquema_acceso = $clave[0][0];
        $cod_ou = $clave[0][1];
        
        $query = Esquema::select('confEsquemaAcceso.cod_esquema_acceso','confEsquemaAcceso.cod_ou','confEsquemaAcceso.des_esquema_acceso',
                'confEsquemaAcceso.obj_intervalos_habiles','confEsquemaAcceso.obj_intervalos_nohabiles','confEsquemaAcceso.obj_intervalos_mixtos',
                'confEsquemaAcceso.ind_estado','confEsquemaAcceso.fec_habilitacion_hasta')
                ->where('confEsquemaAcceso.cod_esquema_acceso',"=",$cod_esquema_acceso)
                ->where('confEsquemaAcceso.cod_ou',"=",$cod_ou)
                ->get();
        if(empty($query[0])) return;
        
        return $query[0];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_esquema_acceso' => 'required',
            'cod_ou' => 'required',
            'des_esquema_acceso' => 'required',
            'obj_intervalos_habiles' => 'required',
            'obj_intervalos_nohabiles' => 'required',
            'obj_intervalos_mixtos' => 'required',
			'fec_habilitacion_hasta' => 'required'
        ],
        [   'cod_esquema_acceso.required' => 'Debe ingresar un Cód. Esquema Acceso',
            'cod_ou.required' => 'Debe seleccionar una Organización',
            'des_esquema_acceso.required' => 'Debe ingresar una descripción',
            'obj_intervalos_habiles.required' => "Debe ingresar intervalos hábiles",
            'obj_intervalos_nohabiles.required' => "Debe ingresar intervalos no hábiles",
            'obj_intervalos_mixtos.required' => "Debe ingresar intervalos mixtos",
			'fec_habilitacion_hasta.required' => "Debe ingresar fecha/hora hasta habilitación completa"
			
		]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $esquema = new Esquema;
        $esquema->cod_esquema_acceso = $request->input('cod_esquema_acceso');
        $esquema->cod_ou = $request->input('cod_ou');
        $esquema->des_esquema_acceso = $request->input('des_esquema_acceso');
        $esquema->obj_intervalos_habiles = $request->input('obj_intervalos_habiles');
        $esquema->obj_intervalos_nohabiles = $request->input('obj_intervalos_nohabiles');
        $esquema->obj_intervalos_mixtos = $request->input('obj_intervalos_mixtos');
        $esquema->fec_habilitacion_hasta = $request->input('fec_habilitacion_hasta');
        $esquema->ind_estado = $request->input('ind_estado');

        Esquema::addAuditoria($esquema,"A");
        $esquema->save();
        $this->cleanCaches();
        return response(['ok' => 'El esquema '.$esquema->cod_esquema_acceso.' fue creado satisfactoriamente'], Response::HTTP_OK);
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
        $cod_esquema_acceso = $request->input('cod_esquema_acceso');
        $cod_ou = $request->input('cod_ou');

        $validator = Validator::make($request->all(), [
            'cod_esquema_acceso' => 'required',
            'cod_ou' => 'required',
            'des_esquema_acceso' => 'required',
            'cod_esquema_acceso' => 'required',
            'cod_ou' => 'required',
            'obj_intervalos_habiles' => 'required',
            'obj_intervalos_nohabiles' => 'required',
            'obj_intervalos_mixtos' => 'required',
			'fec_habilitacion_hasta' => 'required'
			
        ],
        [   'cod_esquema_acceso.required' => 'Debe ingresar un Cód. Esquema Acceso',
            'cod_ou.required' => 'Debe seleccionar una Organización',
            'des_esquema_acceso.required' => 'Debe ingresar una descripción',  
            'cod_esquema_acceso' => "Debe ingresar Cód. Esquema Acceso",
            'cod_ou' => "Debe seleccionar Organización",
            'obj_intervalos_habiles.required' => "Debe ingresar Intervalos Hábiles",
            'obj_intervalos_nohabiles.required' => "Debe ingresar Intervalos no hábiles",
            'obj_intervalos_mixtos.required' => "Debe ingresar Intervalos mixtos",
			'fec_habilitacion_hasta.required' => "Debe ingresar fecha hasta habilitación"
        ]);
        
        if($validator->fails()){
            $errors = $validator->errors();            
            return response(['error' => implode(", ",$errors->all())], Response::HTTP_CONFLICT);
        }
        
        $esquema = Esquema::where("cod_esquema_acceso","=",$cod_esquema_acceso)->where("cod_ou","=",$cod_ou)->first();
        $esquema->des_esquema_acceso = $request->input('des_esquema_acceso');
        $esquema->obj_intervalos_habiles = $request->input('obj_intervalos_habiles');
        $esquema->obj_intervalos_nohabiles = $request->input('obj_intervalos_nohabiles');
        $esquema->obj_intervalos_mixtos = $request->input('obj_intervalos_mixtos');
        $esquema->fec_habilitacion_hasta = $request->input('fec_habilitacion_hasta');
        $esquema->ind_estado = $request->input('ind_estado');

        Esquema::addAuditoria($esquema,"M");
        $esquema->save();
        $this->cleanCaches();
        return response(['ok' => "Actualización exitosa #".$cod_esquema_acceso], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    //public function delete(Request $request, $cod_esquema_acceso, $cod_ou)
    public function delete($clave)
    {
        $clave = json_decode(base64_decode($clave), true); 
        $cod_esquema_acceso = $clave[0][0];
        $cod_ou = $clave[0][1];
        $esquema = Esquema::where("cod_esquema_acceso","=",$cod_esquema_acceso)->where("cod_ou","=",$cod_ou)->first();
        $esquema->delete();
        $this->cleanCaches();
        return response(['ok' => 'Se eliminó satisfactoriamente el esquema '.$cod_esquema_acceso], Response::HTTP_OK);
    }

    public function getEsquemasSync() {
        return array('data'=>Esquema::select()->get(),'next_page_url' => false);
    }
}
