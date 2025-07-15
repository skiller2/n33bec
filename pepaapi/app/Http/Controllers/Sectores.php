<?php

namespace App\Http\Controllers;

use App\Sector;
use App\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use function auth;
use function response;
use App\HabiSectoresxOU;
use App\Helpers\ConfigParametro;
use App\ImagenSector;
use Dompdf\Helpers;
use App\Helpers\RemoteN33;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Broadcast;


class Sectores extends Controller
{
    private static function createTree(&$list, $parent)
    {
        $tree = array();
        foreach ($parent as $k => $l) {
            if (isset($list[$l['cod_sector']])) {
                $l['children'] = SELF::createTree($list, $list[$l['cod_sector']]);
            }
            $tree[$l['cod_sector']] = $l;
        }
        return $tree;
    }

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


    public static function cleanCaches()
    {
        Cache::forget("SECTORES");

        $context = array(
            'msgtext' => "Configuración sectores actualizada",
            "EstadoVal" => true, 
            "EstadoDen" => "Sectores", 
            "EstadoColor" => "green"
        );
        Broadcast::driver('fast-web-socket')->broadcast(["estados", "pantalla"], 'info',  $context);

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
        
        $fieldName = 'maesSectores.cod_sector';
        $order = 'asc';
        if($sort){
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        $query = Sector::select('maesSectores.*','s2.nom_sector as nom_sector_padre')
        ->leftjoin('maesSectores as s2','s2.cod_sector','=','maesSectores.cod_sector_padre');
        $query = Sector::filtroQuery($query,$filtro);

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }


    public function gridOptions($version = "")
    {
        switch($version){
            case "2":
                    $columnDefs[] = array("prop"=>"cod_sector", "name" => "Cód. Sector", "key" => "cod_sector","visible"=>false);
                    $columnDefs[] = array("prop"=>"cod_sector_padre", "name" => "Cód. Sector Padre", "key" => "cod_sector_padre","visible"=>false);
                    $columnDefs[] = array("prop"=>"cod_referencia", "name" => "Denominación Sector");
                    $columnDefs[] = array("prop"=>"nom_sector", "name" => "Nombre");
                    $columnDefs[] = array("prop"=>"nom_sector_padre", "name" => "Nombre Padre");
                    $columnDefs[] = array("prop"=>"des_sector", "name" => "Descripción");
                    $columnDefs[] = array("prop"=>"des_ubicacion", "name" => "Ubicación");
                    $columnDefs[] = array("prop"=>"max_cant_personas", "name" => "Cant. Máx. Pers");
                    $columnDefs[] = array("prop"=>"ind_permanencia", "name" => "Control Permanencia", "pipe" => "ftBoolean");
                    $columnDefs[] = array("prop"=>"aud_stm_ingreso", "name" => "Fecha Alta");
            break;
            default:
                    $columnDefs[] = array("field"=>"cod_sector","displayName"=>"Cód. Sector","visible"=>false);
                    $columnDefs[] = array("field"=>"cod_sector_padre","displayName"=>"Cód. Sector Padre","visible"=>false);
                    $columnDefs[] = array("field"=>"cod_referencia","displayName"=>"Denominación Sector");
                    $columnDefs[] = array("field"=>"nom_sector","displayName"=>"Nombre");
                    $columnDefs[] = array("field"=>"nom_sector_padre","displayName"=>"Nombre Padre");
                    $columnDefs[] = array("field"=>"des_sector","displayName"=>"Descripción");
                    $columnDefs[] = array("field"=>"des_ubicacion","displayName"=>"Ubicación");
                    $columnDefs[] = array("field"=>"max_cant_personas","displayName"=>"Cant. Máx. Pers");
                    $columnDefs[] = array("field"=>"ind_permanencia","displayName"=>"Control Permanencia","cellFilter"=>"ftBoolean");
                    $columnDefs[] = array("field"=>"aud_stm_ingreso","displayName"=>"Fecha Alta","type"=>"date","cellFilter"=>"ftDateTime");
        }
        $columnKeys = ['cod_sector'];
        
        $filtros[] = array('id' => 'maesSectores.cod_sector', 'name' => 'Cód. Sector');
        $filtros[] = array('id' => 'maesSectores.cod_sector_padre', 'name' => 'Cód. Sector Padre');

        $filtros[] = array('id' => 'maesSectores.cod_referencia', 'name' => 'Denominación Sector');
        $filtros[] = array('id' => 'maesSectores.nom_sector', 'name' => 'Nombre');
        $filtros[] = array('id' => 's2.nom_sector_padre', 'name' => 'Nombre Padre');
        $filtros[] = array('id' => 'maesSectores.des_sector', 'name' => 'Descripción');
        $filtros[] = array('id' => 'maesSectores.des_ubicacion', 'name' => 'Ubicación');
        $filtros[] = array('id' => 'maesSectores.max_cant_personas', 'name' => 'Cant. Máx. Pers');



        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys"=>$columnKeys,"columnDefs"=>$columnDefs,"filtros"=>$filtros,"rango"=>$rango);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function detalle($cod_sector,$ou_sel)
    {
        $clave = json_decode(base64_decode($cod_sector), true); 
        $cod_sector = $clave[0][0];
        $img_hash = "";
        $resultado = Sector::find($cod_sector);

        $imagen = ImagenSector::find($cod_sector, ['aud_stm_ultmod']);
        if (isset($imagen['aud_stm_ultmod']))
            $img_hash= base64_encode($imagen['aud_stm_ultmod']);
        $resultado['img_hash']=$img_hash;

        $query =  Sector::select('maesSectores.cod_sector', 'maesSectores.cod_sector_padre','maesSectores.cod_referencia', 'maesSectores.nom_sector', 'maesSectores.ind_permanencia', 'maesSectores.max_cant_personas', 'maesSectores.obj_urls_videos', DB::raw('COUNT(maesTemas.cod_tema) as cant_cod_tema'))
        ->leftJoin('maesTemas', 'maesTemas.cod_sector', '=', 'maesSectores.cod_sector')
        ->groupBy('maesSectores.cod_sector', 'maesSectores.cod_sector_padre','maesSectores.cod_referencia', 'maesSectores.nom_sector', 'maesSectores.ind_permanencia', 'maesSectores.max_cant_personas', 'maesSectores.obj_urls_videos')
        ->find($cod_sector);

        $cant_cod_tema = $query['cant_cod_tema'];


        $resultado['cant_cod_tema']=$cant_cod_tema;
        return $resultado;
    }

    public function getSectores($xusuario=false)
    {
        $sectores = array();
        if($xusuario!="false"){
            $cod_usuario = auth()->user()['cod_usuario'];
            $query = Usuario::select('obj_sectores')->where('cod_usuario',$cod_usuario)->first();
            if($query['obj_sectores']){
                $obj_sectores = $query['obj_sectores'];
                $sectores = Sector::select('cod_sector','cod_referencia','nom_sector','cod_sector_padre')->whereIn('cod_sector',$obj_sectores)->get();
            }
        }
        else{
            $sectores = Sector::select('cod_sector','cod_referencia','nom_sector','cod_sector_padre')->get();
        }
        return $sectores;
    }
        
    public static function getSectorInfoCache($cod_sector)
    {
        $listaCache=ConfigParametro::getSectores();
        return $listaCache[$cod_sector];
    }    
    
    public function getSectoresxOU($cod_ou,$xusuario)
    {
        $sectores = array();
        if($xusuario!="false"){
            $cod_usuario = auth()->user()['cod_usuario'];
            $query = Usuario::select('obj_sectores')->where('cod_usuario',$cod_usuario)->first();
            if($query['obj_sectores']){                
                $obj_sectores = $query['obj_sectores'];
                $sectores = Sector::select('maesSectores.cod_sector')
                        ->join('habiSectoresxOU','habiSectoresxOU.cod_sector','=','maesSectores.cod_sector')
                        ->where('habiSectoresxOU.cod_ou','=',$cod_ou)
                        ->whereIn('maesSectores.cod_sector',$obj_sectores)
                        ->get();
            }
        }
        else{
            $sectores =  Sector::select('maesSectores.cod_sector')
                        ->join('habiSectoresxOU','habiSectoresxOU.cod_sector','=','maesSectores.cod_sector')
                        ->where('habiSectoresxOU.cod_ou','=',$cod_ou)
                        ->get();
        }
        return $sectores;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $cod_sector = Sector::getUuid();

        $sector = new Sector;
        $sector->cod_sector = $cod_sector;
        $sector->cod_referencia = $request->input('cod_referencia');
        $sector->cod_sector_padre = $request->input('cod_sector_padre');
        $sector->nom_sector = $request->input('nom_sector');
        $sector->des_sector = $request->input('des_sector');
        $sector->des_ubicacion = $request->input('des_ubicacion');
        $sector->max_cant_personas = $request->input('max_cant_personas');
        $sector->ind_permanencia = $request->input('ind_permanencia');
        $sector->obj_urls_videos = $request->input('obj_urls_videos');

        Sector::addAuditoria($sector,"A");
        $sector->save();

        if($request->input('imagenes')){
            foreach($request->input('imagenes') as $tipo_uso => $blb_imagen) {
                $imagen = new ImagenSector();
                $imagen->cod_sector = $cod_sector;
                $imagen->blb_imagen = $blb_imagen;
                $imagen->tipo_uso = $tipo_uso;
                ImagenSector::addAuditoria($imagen,"A");
                $imagen->save();
            }
        }

        $this->cleanCaches();
        return response(['ok' => 'El sector fue creado satisfactoriamente con id: '.$cod_sector], Response::HTTP_OK);
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
        $cod_sector = $request->input('cod_sector');
        
        $sector = Sector::find($cod_sector);
        $sector->cod_sector_padre = $request->input('cod_sector_padre');
        $sector->cod_referencia = $request->input('cod_referencia');
        $sector->nom_sector = $request->input('nom_sector');
        $sector->des_sector = $request->input('des_sector');
        $sector->des_ubicacion = $request->input('des_ubicacion');
        $sector->max_cant_personas = $request->input('max_cant_personas');
        $sector->ind_permanencia = $request->input('ind_permanencia');
        $sector->obj_urls_videos = $request->input('obj_urls_videos');

        Sector::addAuditoria($sector,"M");
        $sector->save();
        $this->cleanCaches();

        $imagen = ImagenSector::find($cod_sector);
        if ($imagen) {
            $imagen->delete();
        }

        if(is_array($request->input('imagenes'))){
            foreach($request->input('imagenes') as $tipo_uso => $blb_imagen) {
                $imagen = new ImagenSector;
                $imagen->cod_sector = $request->input('cod_sector');
                $imagen->tipo_uso = $tipo_uso;
                $imagen->blb_imagen = $blb_imagen;
                ImagenSector::addAuditoria($imagen,"A");
                $imagen->save();
            }
        } else if ($request->input('img_plano')) {
            $imagen = new ImagenSector;
            $imagen->cod_sector = $request->input('cod_sector');
            $imagen->tipo_uso = "img_plano";
            $imagen->blb_imagen = $request->input('img_plano');
            ImagenSector::addAuditoria($imagen,"A");
            $imagen->save();
        }




        return response(['ok' => 'Actualización exitosa #: '.$cod_sector], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete($cod_sector)
    {
        $clave = json_decode(base64_decode($cod_sector), true); 

        $cod_sector = $clave[0][0];
        $sector = Sector::find($cod_sector);
        $sector->delete();

        $imagen = ImagenSector::find($cod_sector);
        if ($imagen) {
            $imagen->delete();
        }

        $this->cleanCaches();
        return response(['ok' => 'Se eliminó satisfactoriamente el sector #'.$cod_sector], Response::HTTP_OK);
    }

    public function getSectoresSync() {
        return array('data'=>Sector::select()->get(),'next_page_url' => false);
    }

    public function getSectoresxOUSync() {
        return array('data'=>HabiSectoresxOU::select()->get(),'next_page_url' => false);
    }

    public function getSectoresTree()
    {
        $new = array();
        $new2 = array();
        $query = Sector::select('maesSectores.*', 's2.nom_sector as nom_sector_padre')
            ->leftjoin('maesSectores as s2', 's2.cod_sector', '=', 'maesSectores.cod_sector_padre')->get();

        foreach ($query as $row) {
            $new[$row['cod_sector_padre']][] = array(
                'cod_sector' => $row['cod_sector'],
                'nom_sector' => $row['nom_sector'],
            );
        }
        return SELF::createTree($new, array(array('cod_sector' => '', 'nom_sector' => '')));
    }
//displaysucesos/sectordetalle
    public function getSectorDetalle($cod_tema_sector)
    {
        $cod_tema_sector = base64_decode($cod_tema_sector);
        $ind_alarma = 0;
        $ind_prealarma = 0;
        $ind_falla = 0;
        $ind_alarmatec = 0;
        $img_hash = "";
        $vasectoresresp=array();
        $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $cod_sector=str_replace($cod_tema_local."/","",$cod_tema_sector);
        $vaRemotos = Cache::get("N33BEC_REMOTO", array());

//        Cache::forget("SECTORES");

        $vasectores = ConfigParametro::getSectores();
        if (!isset($vasectores[$cod_sector])) {

            
            foreach ($vaRemotos as $cod_tema_origen=>$remoto){
                if (strpos($cod_tema_sector,$cod_tema_origen) === 0){
                    $ret = RemoteN33::getRemoteData($remoto['url']."/api/v1/displaysucesos/sectordetalle/".base64_encode($cod_tema_sector),5);
                    if ($ret!==false)
                        return $ret;
                }
            } 
            return response(['error' => 'Sector no existe'], Response::HTTP_CONFLICT);
        }
        $imagen = ImagenSector::find($cod_sector, ['aud_stm_ultmod']);
        if (isset($imagen['aud_stm_ultmod']))
            $img_hash= base64_encode($imagen['aud_stm_ultmod']);

        $vasectoresresp[]=array("cod_sector"=>$cod_sector,"nom_sector"=>$vasectores[$cod_sector]['nom_sector'],"cod_tema_sector"=>$vasectores[$cod_sector]['cod_tema_sector']);
        $obj_urls_videos = $vasectores[$cod_sector]['obj_urls_videos'];

        foreach ($vasectores[$cod_sector]['familia'] as $cod_sector_fam){
            $vasectoresresp[]=array("cod_sector"=>$cod_sector_fam,"nom_sector"=>$vasectores[$cod_sector_fam]['nom_sector'],"cod_tema_sector"=>$vasectores[$cod_sector_fam]['cod_tema_sector']);
        }

        return array("sectores" => $vasectoresresp, "cod_sector" =>$cod_sector, "obj_urls_videos"=>$obj_urls_videos, "ind_alarma" => $ind_alarma, "ind_alarmatec" => $ind_alarmatec, "ind_prealarma" => $ind_prealarma, "ind_falla" => $ind_falla, "img_hash" => $img_hash, "cant_cod_tema"=>$vasectores[$cod_sector]['cant_cod_tema']);
    }




}
