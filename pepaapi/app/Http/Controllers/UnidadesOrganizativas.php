<?php

namespace App\Http\Controllers;

use App\HabiAcceso;
use App\HabiCredGrupo;
use App\HabiCredPersona;
use App\HabiCredSectores;
use App\HabiSectoresxOU;
use App\UnidadesOrganiz;
use App\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use function response;

class UnidadesOrganizativas extends Controller
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
        
        $fieldName = 'cod_ou';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = UnidadesOrganiz::select();
        $query = UnidadesOrganiz::filtroQuery($query,$filtro);

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_ou", "name"=> __("Cod. Organización"), "key" => "cod_ou");
                    $columnDefs[] = array("prop"=>"nom_ou", "name"=> __("Nombre"));
                    $columnDefs[] = array("prop"=>"des_ou", "name"=> __("Descripción"));
                    $columnDefs[] = array("prop"=>"centro_emergencias", "name"=> __("Centro Emergencias"));
                    $columnDefs[] = array("prop"=>"tel_centro_emergencias", "name"=> __("Teléfono"));
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name"=> __("Fecha Alta"), "pipe" => "ftDateTime", "searchtype" => "date");
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_ou","displayName"=> __("Cod. Organización"));
                    $columnDefs[] = array("field"=>"nom_ou","displayName"=> __("Nombre"));
                    $columnDefs[] = array("field"=>"des_ou","displayName"=> __("Descripción"));
                    $columnDefs[] = array("field"=>"centro_emergencias","displayName"=> __("Centro Emergencias"));
                    $columnDefs[] = array("field"=>"tel_centro_emergencias","displayName"=> __("Teléfono"));
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=> __("Fecha Alta"),"type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['cod_ou'];
        
        $filtros[] = array('id' => 'cod_ou', 'name'=> __("Cód. Organización"));
        $filtros[] = array('id' => 'nom_ou', 'name'=> __("Nombre"));
        $filtros[] = array('id' => 'des_ou', 'name'=> __("Descripción"));
        $filtros[] = array('id' => 'centro_emergencias', 'name'=> __("Centro Emergencias"));

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
        $cod_ou = $clave[0][0];
        $vaSectores=array();
        $datosSectores = habiSectoresxOU::select('habiSectoresxOU.cod_sector')
                          ->where('habiSectoresxOU.cod_ou','=',$cod_ou)
                          ->get();

        foreach ($datosSectores as $sector) {
          $vaSectores[]=$sector['cod_sector'];
        }
        $vaResultado=UnidadesOrganiz::find($cod_ou);
        $vaResultado["sectoresSel"]=$vaSectores;
        return $vaResultado;
    }

    public function getOU()
    {
        $ou =  UnidadesOrganiz::select('cod_ou','nom_ou')->orderBy('nom_ou','asc')->get();
        return $ou;
    }
    
    public function getOUxUsuario()
    {
        $ou = array();
        //$obj_ou = auth()->user()['obj_ou'];
        $cod_usuario = auth()->user()['cod_usuario'];
        $query = Usuario::select('obj_ou')->where('cod_usuario',$cod_usuario)->get();
        if($query[0]['obj_ou']){
            $obj_ou = $query[0]['obj_ou'];
            $ou =  UnidadesOrganiz::select('cod_ou','nom_ou')->whereIn('cod_ou',$obj_ou)->orderBy('nom_ou','asc')->get();
        }
        return $ou;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request, UnidadesOrganiz $unidadesOrganiz)
    {
      /*
        $user = Auth::user();
        file_put_contents("c:/temp/archivo.txt",var_export($user,true)."\n",FILE_APPEND);
        if($user->can('store',$unidadesOrganiz)){
            file_put_contents("c:/temp/archivo.txt",var_export('can',true)."\n",FILE_APPEND);
        }
*/
        //$this->authorize("store",$unidadesOrganiz);
        $cod_ou = UnidadesOrganiz::getUuid();
        $sectoresSel = $request->input('sectoresSel');

        //$unidadesOrganiz = new UnidadesOrganiz;
        $unidadesOrganiz->cod_ou = $cod_ou;
        $unidadesOrganiz->nom_ou = $request->input('nom_ou');
        $unidadesOrganiz->des_ou = $request->input('des_ou');
        $unidadesOrganiz->ind_ou_admin = $request->input('ind_ou_admin');
        $unidadesOrganiz->centro_emergencias = $request->input('centro_emergencias');
        $unidadesOrganiz->tel_centro_emergencias = $request->input('tel_centro_emergencias');

        UnidadesOrganiz::addAuditoria($unidadesOrganiz,"A");
        $unidadesOrganiz->save();

        //HabiSectoresxOU
        HabiSectoresxOU::where('cod_ou',$cod_ou)->delete();
        if($sectoresSel){
            foreach($sectoresSel as $sector)
            {
                $sectores = new HabiSectoresxOU;
                $sectores->cod_ou = $cod_ou;
                $sectores->cod_sector = $sector;
                HabiSectoresxOU::addAuditoria($sectores,"RL");
                $sectores->save();
            }
            Cache::forget("sectores");
        }

        return response(['ok' => __('La organización fue creada satisfactoriamente con código :COD_OU',['COD_OU'=>$cod_ou])], Response::HTTP_OK);
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
        $cod_ou = $request->input('cod_ou');
        
        $unidadesOrganiz = UnidadesOrganiz::find($cod_ou);
        $unidadesOrganiz->nom_ou = $request->input('nom_ou');
        $unidadesOrganiz->des_ou = $request->input('des_ou');
        $unidadesOrganiz->ind_ou_admin = $request->input('ind_ou_admin');
        $unidadesOrganiz->centro_emergencias = $request->input('centro_emergencias');
        $unidadesOrganiz->tel_centro_emergencias = $request->input('tel_centro_emergencias');
        $sectoresSel = $request->input('sectoresSel');
        
        UnidadesOrganiz::addAuditoria($unidadesOrganiz,"M");
        $unidadesOrganiz->save();
        
        HabiSectoresxOU::where('cod_ou',$cod_ou)->delete();
        if($sectoresSel){
            foreach($sectoresSel as $sector)
            {
                $sectores = new HabiSectoresxOU;
                $sectores->cod_ou = $cod_ou;
                $sectores->cod_sector = $sector;
                HabiSectoresxOU::addAuditoria($sectores,"RL");
                $sectores->save();
            }
            Cache::forget("sectores");
        }
        
        return response(['ok' => "Actualización exitosa #".$cod_ou], Response::HTTP_OK);
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
        $cod_ou = $clave[0][0];
        
        $habiCredSectores =  HabiCredSectores::select('cod_credencial')->where('cod_ou','=',$cod_ou)->get()->toArray();
        $credenciales_a_borrar = array_map(function($item){ return $item['cod_credencial']; }, $habiCredSectores);
        
        if(!empty($credenciales_a_borrar)){
            HabiCredPersona::whereIn('cod_credencial', $credenciales_a_borrar)->delete();
            HabiCredGrupo::whereIn('cod_credencial', $credenciales_a_borrar)->delete();
            HabiAcceso::whereIn('cod_credencial', $credenciales_a_borrar)->delete();            
        }
        HabiCredSectores::where('cod_ou',$cod_ou)->delete();
        HabiSectoresxOU::where('cod_ou',$cod_ou)->delete();        
        UnidadesOrganiz::find($cod_ou)->delete();
        
        Cache::forget("sectores");
        
        return response(['ok' => __('Se eliminó satisfactoriamente la organización código :COD_OU',['COD_OU'=>$cod_ou])], Response::HTTP_OK);
    }

    public function getOUSync() {
        return array('data'=>UnidadesOrganiz::select()->get(),'next_page_url' => false);
    }

}
