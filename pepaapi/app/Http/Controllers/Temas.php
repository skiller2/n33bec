<?php

namespace App\Http\Controllers;

use App\Tema;
use Illuminate\Support\Facades\Broadcast;

use App\Helpers\ConfigParametro;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use function response;
use Illuminate\Support\Facades\Validator;
use App\MoviDisplayTema;
use App\Helpers\TemaValue;
use Amp\Socket\DatagramSocket;
use Amp\Socket\SocketAddress;
use function Amp\Socket\connect;
use Amp\Socket\ConnectContext;
use Carbon\Carbon;
use App\Events\TemaEvent;
use App\MoviTemaNoRegis;
use App\ImagenTema;
use App\Helpers\RemoteN33;
use App\MoviUltEvento;

class Temas extends Controller
{
    const config_tag = "iolast_";

    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "indexnr":
            case "store":
            case "update":
            case "delete":
            case "deletenr":
            case "gridOptions":
            case "gridOptionsnr":
            case "sendCommand":
            case "runEvent":
            case "getTemas":
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
        /*
          try {
          JWTAuth::parseToken()->toUser();
          } catch (Exception $e) {
          return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_UNAUTHORIZED);
          }
         */
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'cod_tema';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTabla($fieldName);
        $query = Tema::select(
            'maesTemas.cod_tema',
            'maesTemas.nom_tema',
            'maesTemas.url_envio',
            'maesTemas.cod_sector',
            'maesTemas.cod_clase',
            'maesSectores.nom_sector',
            'maesTemas.des_ubicacion',
            'maesTemas.cod_tipo_uso',
            'maesTemas.ind_mostrar_en_panel',
            'maesTemas.ind_registra_evento',
            'maesTemas.ind_display_evento',
            'maesTemas.ind_notifica_evento',
            'maesTemas.ind_activo',
            'maesTemas.aud_stm_ingreso',
            'maesTemas.aud_stm_ultmod'
        )
            ->join('maesSectores', 'maesSectores.cod_sector', '=', 'maesTemas.cod_sector');
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTabla($nombre);
                    $query->where($tabla . $nombre, $operacion, $valor);
                }
            }
        }

        return $query->orderBy($tablaOrden . $fieldName, $order)->paginate($pageSize);
    }

    public function indexnr(Request $request, $export)
    {
        /*
          try {
          JWTAuth::parseToken()->toUser();
          } catch (Exception $e) {
          return Response::json(['error' => $e->getMessage()], HttpResponse::HTTP_UNAUTHORIZED);
          }
         */
        $pageSize = $request->input('pageSize');
        $filtro = json_decode($request->input('filtro'), true);
        $sort = json_decode($request->input('sort'), true);

        $fieldName = 'cod_tema';
        $order = 'desc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }

        $tablaOrden = self::getTablanr($fieldName);
        $query = MoviTemaNoRegis::select(
            'moviTemasNoRegis.cod_tema',
            'moviTemasNoRegis.nom_tema',
            'moviTemasNoRegis.valor',
            'moviTemasNoRegis.cant_reportes',
            'moviTemasNoRegis.des_observaciones',
            'moviTemasNoRegis.stm_ultimo_reporte',
            'moviTemasNoRegis.aud_stm_ingreso',
            'moviTemasNoRegis.aud_stm_ultmod'
        );
        if (count($filtro['json']) > 0) {
            foreach ($filtro['json'] as $filtro) {
                $nombre = $filtro['NombreCampo'];
                $operacion = $filtro['operacion'];
                $valor = $filtro['ValorCampo'];
                if ($valor != "" && $nombre != "" && $operacion != "") {
                    if ($operacion == "LIKE")
                        $valor = "%" . $valor . "%";
                    $tabla = self::getTabla($nombre);
                    $query->where($tabla . $nombre, $operacion, $valor);
                }
            }
        }

        return $query->orderBy($tablaOrden . $fieldName, $order)->paginate($pageSize);
    }

    private static function getTablanr($campo)
    {
        $tabla = "";
        switch ($campo) {
            default:
                $tabla = "moviTemasNoRegis.";
                break;
        }
        return $tabla;
    }

    private static function getTabla($campo)
    {
        $tabla = "";
        switch ($campo) {
            case 'cod_sector':
            case 'nom_sector':
                $tabla = "maesSectores.";
                break;
            default:
                $tabla = "maesTemas.";
                break;
        }
        return $tabla;
    }

    public static function cleanCaches()
    {
        Cache::forget("TEMAS_CACHE");
        Cache::forget("LECTOR");
        Cache::forget("DIN");
        Cache::forget("DOUT");
        Cache::forget("AIN");
        Cache::forget("AOUT");
        Cache::forget("SUCESO");
        Cache::forget("COMUNIC");
        Cache::forget("LOCALIOS");
        Cache::forever("daemon_conf_ver", (int)Cache::get("daemon_conf_ver") + 1);
        Cache::forever("EstadoHabiAccesoDispo", false);


        $context = array(
            'msgtext' => __("Configuración temas actualizada"),
            //            "EstadoVal" => true, 
            //            "EstadoDen" => "Temas", 
            //            "EstadoColor" => "red"
        );
        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
    }

    public function gridOptions($version = "")
    {

        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "cod_tema", "name"=> __("Componente"), "key" => "cod_tema");
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Etiqueta"));
                $columnDefs[] = array("prop" => "nom_sector", "name"=> __("Sector"));
                $columnDefs[] = array("prop" => "des_ubicacion", "name"=> __("Ubicación"));
                $columnDefs[] = array("prop" => "cod_tipo_uso", "name"=> __("Tipo Uso"));
                $columnDefs[] = array("prop" => "ind_mostrar_en_panel", "name"=> __("Muestra en Panel"));
                $columnDefs[] = array("prop" => "ind_registra_evento", "name"=> __("Registra Evento"));
                $columnDefs[] = array("prop" => "ind_notifica_evento", "name"=> __("Notifica Evento"));
                $columnDefs[] = array("prop" => "ind_display_evento", "name"=> __("Display Evento"));
                $columnDefs[] = array("prop" => "ind_activo", "name"=> __("Activo"));
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name"=> __("Fecha Alta"));
                $columnDefs[] = array("prop" => "aud_stm_ultmod", "name"=> __("Fecha Modif."));

                break;
            default:
                $columnDefs[] = array("field" => "cod_tema", "displayName"=> __("Componente"));
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Etiqueta"));
                $columnDefs[] = array("field" => "nom_sector", "displayName"=> __("Sector"));
                $columnDefs[] = array("field" => "des_ubicacion", "displayName"=> __("Ubicación"));
                $columnDefs[] = array("field" => "cod_tipo_uso", "displayName"=> __("Tipo Uso"));
                $columnDefs[] = array("field" => "ind_mostrar_en_panel", "displayName"=> __("Muestra en Panel"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "ind_registra_evento", "displayName"=> __("Registra Evento"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "ind_notifica_evento", "displayName"=> __("Notifica Evento"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "ind_display_evento", "displayName"=> __("Display Evento"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "ind_activo", "displayName"=> __("Activo"), "cellFilter" => "ftBoolean");
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName"=> __("Fecha Alta"), "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "aud_stm_ultmod", "displayName"=> __("Fecha Modif."), "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_tema'];

        $filtros[] = array('id' => 'cod_tema', 'name'=> __("Cód. componente"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Etiqueta"));
        $filtros[] = array('id' => 'nom_sector', 'name'=> __("Sector"));
        $filtros[] = array('id' => 'des_ubicacion', 'name'=> __("Ubicación"));
        $filtros[] = array('id' => 'cod_tipo_uso', 'name'=> __("Tipo Uso"));

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }

    public function gridOptionsnr($version = "")
    {

        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "cod_tema", "name"=> __("Componente"), "key" => "cod_tema");
                $columnDefs[] = array("prop" => "nom_tema", "name"=> __("Etiqueta"));
                $columnDefs[] = array("prop" => "valor", "name"=> __("Valor"));
                $columnDefs[] = array("prop" => "des_observaciones", "name"=> __("Observaciones"));
                $columnDefs[] = array("prop" => "stm_ultimo_reporte", "name"=> __("Último reporte"));
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name"=> __("Fecha Alta"));
                $columnDefs[] = array("prop" => "aud_stm_ultmod", "name"=> __("Fecha Modif."));

                break;
            default:
                $columnDefs[] = array("field" => "cod_tema", "displayName"=> __("Componente"));
                $columnDefs[] = array("field" => "nom_tema", "displayName"=> __("Etiqueta"));
                $columnDefs[] = array("field" => "valor", "displayName"=> __("Valor"));
                $columnDefs[] = array("field" => "des_observaciones", "displayName"=> __("Observaciones"));
                $columnDefs[] = array("field" => "stm_ultimo_reporte", "displayName"=> __("Último reporte"), "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName"=> __("Fecha Alta"), "type" => "date", "cellFilter" => "ftDateTime");
                $columnDefs[] = array("field" => "aud_stm_ultmod", "displayName"=> __("Fecha Modif."), "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['cod_tema'];

        $filtros[] = array('id' => 'cod_tema', 'name'=> __("Cód. componente"));
        $filtros[] = array('id' => 'nom_tema', 'name'=> __("Etiqueta"));
        $filtros[] = array('id' => 'valor', 'name'=> __("Valor"));
        $filtros[] = array('id' => 'des_observaciones', 'name'=> __("Observaciones"));

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function detalle($cod_tema)
    {
        $clave = json_decode(base64_decode($cod_tema), true);

        $cod_tema = $clave[0][0];

        $tema = Tema::find($cod_tema);
        $tema['cod_tema_key'] = $cod_tema;
        return $tema;
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
            'cod_tema' => 'required',
            'nom_tema' => 'required',
            'des_ubicacion' => 'required',
            'cod_tipo_uso' => 'required',
            'cod_clase' => "required"

        ], [
            'cod_tema.required'=> __("Debe ingresar componente"),
            'nom_tema.required'=> __("Debe ingresar etiqueta"),
            'des_ubicacion.required'=> __("Debe ingresar ubicación"),
            'cod_tipo_uso.required'=> __("Debe seleccionar tipo uso"),
            'cod_clase.required'=> __("Debe seleccionar una clase")
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $tema = new Tema;
        $tema->cod_tema = strtolower($request->input('cod_tema'));
        $tema->nom_tema = $request->input('nom_tema');
        $tema->cod_sector = $request->input('cod_sector');
        $tema->cod_clase = $request->input('cod_clase');
        $tema->des_ubicacion = $request->input('des_ubicacion');
        $tema->url_envio = $request->input('url_envio');
        $tema->cod_tipo_uso = $request->input('cod_tipo_uso');
        $tema->json_parametros = $request->input('json_parametros');
        $tema->json_posicion_img = $request->input('json_posicion_img');
        $tema->json_subtemas = array();
        $tema->ind_mostrar_en_panel = ($request->input('ind_mostrar_en_panel')) ? $request->input('ind_mostrar_en_panel') : 0;
        $tema->ind_registra_evento = ($request->input('ind_registra_evento')) ? $request->input('ind_registra_evento') : 0;
        $tema->ind_display_evento = ($request->input('ind_display_evento')) ? $request->input('ind_display_evento') : 0;
        $tema->ind_notifica_evento = ($request->input('ind_notifica_evento')) ? $request->input('ind_notifica_evento') : 0;
        $tema->ind_activo = ($request->input('ind_activo')) ? $request->input('ind_activo') : 0;

        if ($tema->cod_tipo_uso == "SUCESO") {
            $tema->json_subtemas = $this->setSubtemas(serialize($request->input('json_parametros')));
        }

        if($request->input('img_tema')){
            $imagenes = new ImagenTema;
            $imagenes->cod_tema = $tema->cod_tema;
            $imagenes->img_tema = $request->input('img_tema');
            $imagenes->tipo_uso = "img_tema";
            ImagenTema::addAuditoria($imagenes,"A");
            $imagenes->save();
        }

        Tema::addAuditoria($tema, "A");
        $tema->save();

        $temanr = MoviTemaNoRegis::find($tema->cod_tema);
        if ($temanr)
            $temanr->delete();

        $this->cleanCaches();
        return response(['ok' => __('El componente fue creado satisfactoriamente con identificador :COD_TEMA',['COD_TEMA'=>$tema->cod_tema])], Response::HTTP_OK);
    }



    public static function storeNoRegis(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_tema' => 'required',
        ], [
            'cod_tema.required'=> __("Debe ingresar componente"),
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $temanr = MoviTemaNoRegis::where('cod_tema', '=', $request->input('cod_tema'))
            ->first();
        if ($temanr) {
            $temanr->cant_reportes = $temanr->cant_reportes+1;
        } else {
            $temanr = new MoviTemaNoRegis;
            $temanr->cod_tema = strtolower($request->input('cod_tema'));
            $temanr->nom_tema = $request->input('nom_tema');
            $temanr->valor = $request->input('valor');
            $temanr->stm_ultimo_reporte = $request->input('stm_ultimo_reporte');
            $temanr->des_observaciones = $request->input('des_observaciones');
            $temanr->cant_reportes = 1;
        }
        MoviTemaNoRegis::addAuditoria($temanr, "RL");
        $temanr->save();


        return response(['ok' => __('El componente fue creado satisfactoriamente con identificador :COD_TEMA',['COD_TEMA'=>$tema->cod_tema]) . $temanr->cod_tema], Response::HTTP_OK);
    }


    public function setSubtemas($json_parametros)
    {
        $subtemas = array();
        $vatemas = ConfigParametro::getTemas();
        foreach ($vatemas as $cod_tema => $detalle_tema) {
            if (strpos($json_parametros, $cod_tema) !== false)
                $subtemas[] = $cod_tema;
        }

        return $subtemas;
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
            'cod_tema' => 'required',
            'nom_tema' => 'required',
            'des_ubicacion' => 'required',
            'cod_tipo_uso' => 'required',
            'cod_clase' => 'required'
        ], [
            'cod_tema.required'=> __("Debe ingresar componente"),
            'nom_tema.required'=> __("Debe ingresar etiqueta"),
            'des_ubicacion.required'=> __("Debe ingresar ubicación"),
            'cod_tipo_uso.required'=> __("Debe seleccionar tipo uso"),
            'cod_clase.required'=> __("Debe seleccionar clase")
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $cod_tema_key = $request->input('cod_tema_key');

        $tema = Tema::find($cod_tema_key);
        $tema->cod_tema = $request->input('cod_tema');
        $tema->nom_tema = $request->input('nom_tema');
        $tema->cod_sector = $request->input('cod_sector');
        $tema->cod_clase = $request->input('cod_clase');
        $tema->des_ubicacion = $request->input('des_ubicacion');
        $tema->cod_tipo_uso = $request->input('cod_tipo_uso');
        $tema->url_envio = $request->input('url_envio');
        $tema->json_parametros = $request->input('json_parametros');
        $tema->json_posicion_img = $request->input('json_posicion_img');
        $tema->json_subtemas = [];
        $tema->ind_mostrar_en_panel = ($request->input('ind_mostrar_en_panel')) ? $request->input('ind_mostrar_en_panel') : 0;
        $tema->ind_registra_evento = ($request->input('ind_registra_evento')) ? $request->input('ind_registra_evento') : 0;
        $tema->ind_display_evento = ($request->input('ind_display_evento')) ? $request->input('ind_display_evento') : 0;
        $tema->ind_notifica_evento = ($request->input('ind_notifica_evento')) ? $request->input('ind_notifica_evento') : 0;
        $tema->ind_activo = ($request->input('ind_activo')) ? $request->input('ind_activo') : 0;
        if ($tema->cod_tipo_uso == "SUCESO") {
            $tema->json_subtemas = $this->setSubtemas(serialize($request->input('json_parametros')));
        }


        if($request->input('img_tema') ){
            $auditoria="M";
            $imagenes = ImagenTema::select()->where('cod_tema',$request->input('cod_tema'))->first();
            if(!$imagenes){
                $imagenes = new ImagenTema;
                $imagenes->cod_tema = $request->input('cod_tema');
                $auditoria="A";
            }
            $imagenes->tipo_uso = "img_tema";
            $imagenes->img_tema = $request->input('img_tema');
            ImagenTema::addAuditoria($imagenes,$auditoria);
            $imagenes->save();
        } else {
            $imagenes = ImagenTema::select()->where('cod_tema',$request->input('cod_tema'))->first();
            if($imagenes)
                $imagenes->delete();
        }

        
        Tema::addAuditoria($tema, "M");
        $tema->save();

        if ($tema->ind_display_evento==0 || $tema->ind_activo==0) {
            MoviDisplayTema::where('cod_tema', $cod_tema_key)->delete();
        }

        $temanr = MoviTemaNoRegis::find($request->input('cod_tema'));
        if ($temanr)
            $temanr->delete();

        $this->cleanCaches();


        return response(['ok' => "Actualización exitosa #" . $tema->cod_tema], Response::HTTP_OK);
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
        $cod_tema = $clave[0][0];

        $tema = Tema::find($cod_tema);
        $tema->delete();

        $imagenes = ImagenTema::select()->where('cod_tema',$cod_tema)->first();
        if($imagenes)
            $imagenes->delete();

        $this->cleanCaches();
        Cache::forever("EstadoHabiAccesoDispo", false);
        return response(['ok' => "Se eliminó satisfactoriamente el componente " . $cod_tema], Response::HTTP_OK);
    }

    public function deletenr($clave)
    {
        $clave = json_decode(base64_decode($clave), true);
        $cod_tema = $clave[0][0];

        $temanr = MoviTemaNoRegis::find($cod_tema);
        if ($temanr)
            $temanr->delete();

        return response(['ok' => "Se eliminó satisfactoriamente el componente " . $cod_tema], Response::HTTP_OK);
    }


    //PARA MOVIMIENTOS - LEER TARJETA
    public function getLectores($ind_movimiento = "")
    {
        $vaLectores = array();
        $lectores = ConfigParametro::getTemas("LECTOR");
        foreach ($lectores as $cod_tema => $datos) {
            if ($ind_movimiento == "" || $ind_movimiento == $datos['ind_movimiento']) {
                $descripcion = $cod_tema . " " . $datos["nom_tema"] . " " . $datos["des_ubicacion"];
                $vaLectores[$cod_tema] = array(
                    "descripcion" => $descripcion, "ind_separa_facility_code" => $datos['ind_separa_facility_code'],
                    "cod_tema" => $cod_tema, 'id' => $cod_tema
                );
            }
        }
        return $vaLectores;
    }

    public function getTemas($ind_tipo_uso="")
    {
        $vaTemas = array();
        $temas = ConfigParametro::getTemas($ind_tipo_uso);
        foreach ($temas as $cod_tema => $datos) {
            $des_tema = $cod_tema . " " . $datos["nom_tema"] . " " . $datos["des_ubicacion"];
            $vaTemas[$cod_tema] = array(
                "des_tema" => $des_tema,
                "nom_tema" => $datos["nom_tema"],
                "cod_tema" => $cod_tema,
                "cod_clase" => $datos["cod_clase"]
            );
        }
        return $vaTemas;
    }

    public function getClases($export=false)
    {
        $vaClases = array();
        $vaClases = array(
            array("cod_clase"=>"AVIS","nom_clase"=>"Avisador"),
            array("cod_clase"=>"TEMP","nom_clase"=>"Sensor Temperatura"),
            array("cod_clase"=>"HUMO","nom_clase"=>"Sensor Humo"),
            array("cod_clase"=>"HUTE","nom_clase"=>"Sensor Humo/Temp"),
            array("cod_clase"=>"CEIN","nom_clase"=>"Central Incendio"),
            array("cod_clase"=>"PACA","nom_clase"=>"Control de Acceso"),
            array("cod_clase"=>"SEPU","nom_clase"=>"Sensor puerta"),
            array("cod_clase"=>"TEIP","nom_clase"=>"Teléfono IP"),
            array("cod_clase"=>"OTRO","nom_clase"=>"Otro"),
        );
        return $vaClases;
    }

    public function getTemasDetalleSector($cod_tema_sector)
    {
        $cod_tema_sector = base64_decode($cod_tema_sector);
        $cod_tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $cod_sector=str_replace($cod_tema_local."/","",$cod_tema_sector);
        $vaRemotos = Cache::get("N33BEC_REMOTO", array());

        $ind_alarma = false;
        $ind_prealarma = false;
        $ind_falla = false;
        $ind_alarmatec = false;
        $ind_desconexion = false;

        $estados_temas_actuales = array();

        $vasectores = ConfigParametro::getSectores();

        if (!isset($vasectores[$cod_sector])) {
            foreach ($vaRemotos as $cod_tema_origen=>$remoto){
                if (strpos($cod_tema_sector,$cod_tema_origen) === 0){
                    $ret = RemoteN33::getRemoteData($remoto['url']."/api/v1/displaysucesos/sectortemasdetalle/".base64_encode($cod_tema_sector),5);
                    if ($ret!==false)
                        return $ret;
                }
            } 
            return response(['error'=> __("Sector no localizado")], Response::HTTP_CONFLICT);
        }

        $vatemas = ConfigParametro::getTemas();

        foreach ($vatemas as $cod_tema => $det_tema) {
            if ($det_tema['cod_sector'] != $cod_sector)
                continue;

            $valor = $det_tema['valor_omision'];
            if (Cache::has("iolast_" . $cod_tema))
                $valor = Cache::get("iolast_" . $cod_tema);


            $res = TemaValue::get($det_tema, $valor);
            $estados_temas_actuales[] = array(
                'cod_tema' => $cod_tema,
                'valor' => $valor,
                'direccion' => $res['direccion'],
                'des_valor' => $res['des_valor'],
                'nom_tema' => $det_tema['nom_tema'],
                'color' => $res['color'],
                'tipo_evento' => $res['tipo_evento'],
                'json_posicion_img' => $det_tema['json_posicion_img']
            );

            switch ($res['tipo_evento']) {
                case 'AL':
                    $ind_alarma = true;
                    break;
                case 'PA':
                    $ind_prealarma = true;
                    break;
                case 'FA':
                    $ind_falla = true;
                    break;
                case 'AT':
                    $ind_alarmatec = true;
                    break;
                case 'DE':
                    $ind_desconexion = true;
                    break;

                default:
                    break;
            }
        }

        $tipo_evento_prioridad = "NO";
        if ($ind_falla)
            $tipo_evento_prioridad = "FA";

        if ($ind_prealarma)
            $tipo_evento_prioridad = "PA";

        if ($ind_alarmatec)
            $tipo_evento_prioridad = "AT";

        if ($ind_alarma)
            $tipo_evento_prioridad = "AL";

        if ($ind_desconexion)
            $tipo_evento_prioridad = "DE";

        return response(['estados_temas_actuales' => $estados_temas_actuales, "tipo_evento_prioridad" => $tipo_evento_prioridad, "ind_falla" => $ind_falla, "ind_prealarma" => $ind_prealarma, "ind_alarma" => $ind_alarma, "ind_alarmatec" => $ind_alarmatec, "ind_desconexion" => $ind_desconexion], Response::HTTP_OK);
    }

    public function setOperationMode(Request $request)
    {
        $ind_modo_prueba = $request->input('ind_modo_prueba');
        $ind_modo_prueba = ($ind_modo_prueba == 1) ? 1 : 0;

        if ($ind_modo_prueba != Cache::get("ind_modo_prueba", 0)) {
            Cache::forever("ind_modo_prueba", $ind_modo_prueba);
        }

        $this->getEstadosLeds();
        return response(['ok' => "Modo seteado en ".$ind_modo_prueba], Response::HTTP_OK);
    }

    public function sendCommand(Request $request)
    {
        $cod_tema  = $request->input('cod_tema');
        $id = $request->input('bus_id');
        $accion = $request->input('accion');
        $param = $request->input('param');
        $valor = $request->input('valor');

        $data = sprintf("%s %s %s%s", $id, $param, $accion, $valor);

        $socket = stream_socket_client('udp://127.0.0.1:1337');
        $json_msg = json_encode(array("cod_tema"=>$cod_tema,"data"=>$data));
        fwrite($socket, $json_msg);

        return response(['ok' => "Comando enviado"], Response::HTTP_OK);
    }

    public function runEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cod_tema' => 'required',
        ], [
            'cod_tema.required'=> __("Debe ingresar componente"),
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response(['error' => implode(", ", $errors->all())], Response::HTTP_CONFLICT);
        }

        $cod_tema = $request->input('cod_tema');
        $valor = $request->input('valor');
        $extra_data = $request->input('extra_data');
        $des_observaciones = "ejecución manual";
        $vatemas = ConfigParametro::getTemas();
        if(!isset($vatemas[$cod_tema]))
            return response(['error'=> __("Tema :COD_TEMA no reconocido",['COD_TEMA'=>$cod_tema])], Response::HTTP_CONFLICT);


        $vaextra_data = json_decode($extra_data,true);
        $valor_json = json_decode($valor,true);
        if ($valor_json)
            $valor=$valor_json;

        $event_data = array("valor" => $valor, "des_valor" => $valor, "des_observaciones" => $des_observaciones);

        if ($vaextra_data){
            $event_data = array_merge($event_data,$vaextra_data);
        } else if ($extra_data!="") {

           $extra_data= preg_replace_callback(
               '/\\\\([nrtvf\\\\$"]|[0-7]{1,3}|\x[0-9A-Fa-f]{1,2})/',
               function ($matches) {
                    return stripcslashes($matches[0]);
               }
               , $request->input('extra_data')
           );

           $event_data['extra_data']=$extra_data;
        }

        event(new TemaEvent($cod_tema, Carbon::now(), $event_data));

        $count = Cache::get("COUNT_" . self::config_tag.$cod_tema);
        $value = Cache::get(self::config_tag . $cod_tema);


        $res= TemaValue::get($vatemas[$cod_tema],$value);
        $des_valor = $res['des_valor'];
        $direccion = $res['direccion'];
        $color = $res['color'];

        return response(['ok' => "Evento procesado",'des_valor'=>$des_valor, 'color'=>$color, 'count'=>$count], Response::HTTP_OK);
    }


    public function getTemaDetalle($cod_tema) ///api/v1/displaysucesos/temadetalle/
    {
        $cod_tema = base64_decode($cod_tema);
        $stm_evento = "";
        $des_observaciones  = "";
        $ind_alarma = false;
        $ind_prealarma = false;
        $ind_falla = false;
        $ind_alarmatec = false;
        $ind_desconexion = false;

        $vatemas = ConfigParametro::getTemas("");
        if (!isset($vatemas[$cod_tema])) {
            $vaRemotos = Cache::get("N33BEC_REMOTO", array());
            foreach ($vaRemotos as $cod_tema_remoto=>$remoto) {
                if (strpos($cod_tema,$cod_tema_remoto) === 0) {
                    $ret = RemoteN33::getRemoteData($remoto['url']."/api/v1/displaysucesos/temadetalle/".base64_encode($cod_tema),5);
                    if ($ret!==false)
                        return $ret;
                }
            }
            return response(['error'=> __("Tema no existe")], Response::HTTP_CONFLICT);
        }
        $nom_tema = $vatemas[$cod_tema]['nom_tema'];
        $bus_id = (isset($vatemas[$cod_tema]['bus_id']))?$vatemas[$cod_tema]['bus_id']:"";
        $des_ubicacion = $vatemas[$cod_tema]['des_ubicacion'];
        $json_posicion_img = $vatemas[$cod_tema]['json_posicion_img'];

        $stm_evento_prueba= "";
        $stm_evento_ultimo= "";
        $vaResultado = MoviDisplayTema::where('moviDisplayTemas.cod_tema', $cod_tema)->get();
        $temaeventoreal = MoviUltEvento::find2(['cod_tema'=>$cod_tema,'ind_modo_prueba'=>0]);
        $temaeventoprueba = MoviUltEvento::find2(['cod_tema'=>$cod_tema,'ind_modo_prueba'=>1]);

        foreach ($vaResultado as $tema) {
            $stm_evento = $tema['stm_evento'];
            $des_observaciones = $tema['des_observaciones'];
            switch ($tema['tipo_evento']) {
                case 'AL':
                    $ind_alarma = true;
                    break;
                case 'PA':
                    $ind_prealarma = true;
                    break;
                case 'FA':
                    $ind_falla = true;
                    break;
                case 'AT':
                    $ind_alarmatec = true;
                    break;
                case 'DE':
                    $ind_desconexion = true;
                    break;

                default:
                    break;
            }
        }

        if ($temaeventoreal) {
            if ($stm_evento!=$temaeventoreal->stm_evento)
            $stm_evento_ultimo=$temaeventoreal->stm_evento;
        }

        if ($temaeventoprueba) {
            $stm_evento_prueba=$temaeventoprueba->stm_evento;
        }



        $tipo_evento_prioridad = "NO";
        if ($ind_falla)
            $tipo_evento_prioridad = "FA";

        if ($ind_prealarma)
            $tipo_evento_prioridad = "PA";

        if ($ind_alarmatec)
            $tipo_evento_prioridad = "AT";

        if ($ind_alarma)
            $tipo_evento_prioridad = "AL";

        if ($ind_desconexion)
            $tipo_evento_prioridad = "DE";

        return array("cod_tema" => $cod_tema, "nom_tema" => $nom_tema, "bus_id"=>$bus_id, "json_posicion_img" => $json_posicion_img, "tipo_evento_prioridad" => $tipo_evento_prioridad, "ind_falla" => $ind_falla, "ind_prealarma" => $ind_prealarma, "ind_alarma" => $ind_alarma, "ind_alarmatec" => $ind_alarmatec, "ind_desconexion" => $ind_desconexion, "des_ubicacion" => $des_ubicacion, "stm_evento" => $stm_evento, "des_observaciones" => $des_observaciones, "stm_evento_prueba"=>$stm_evento_prueba, "stm_evento_ultimo"=>$stm_evento_ultimo);
    }

    public function getTemasSync()
    {
        return array('data' => Tema::select()->get(), 'next_page_url' => false);
    }

    public function getEstadosLeds()
    {
        $estadoHabiAcceso = Cache::get("EstadoHabiAccesoDispo");
        $colorEstado = ($estadoHabiAcceso) ? "green" : "red";

        $context = array(
            'msgtext' => "",
            "EstadoVal" => $estadoHabiAcceso,
            "EstadoDen" => "HabiAcceso",
            "EstadoColor" => $colorEstado
        );

        Broadcast::driver('fast-web-socket')->broadcast(["estados"], 'info',  $context);

        $ind_modo_prueba = Cache::get("ind_modo_prueba", 0);
        $colorEstado = ($ind_modo_prueba) ? "yellow" : "green";

        $context = array(
            'msgtext' => "",
            "EstadoVal" => $ind_modo_prueba,
            "EstadoDen" => "indModoPrueba",
            "EstadoColor" => $colorEstado
        );

        Broadcast::driver('fast-web-socket')->broadcast(["estados"], 'info',  $context);
        return response(['ok'=>__('Estados enviados')], Response::HTTP_OK);
    }
}
