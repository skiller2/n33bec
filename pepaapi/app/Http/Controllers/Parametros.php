<?php

namespace App\Http\Controllers;

use App\Helpers\ConfigParametro;
use App\Jobs\SendMail;
use App\Parametro;
use Illuminate\Http\Request;
use SebastianBergmann\RecursionContext\Exception;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Broadcast;


class Parametros extends Controller
{
    public static function getAbility($metodo)
    {
        switch ($metodo) {
            case "index":
            case "store":
            case "update":
            case "delete":
            case "gridOptions":
            case "detalle":
            case "listDaemons":
            case "restartDaemon":
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

        $fieldName = 'den_parametro';
        $order = 'asc';
        if ($sort) {
            $fieldName = isset($sort['fieldName']) ? $sort['fieldName'] : $fieldName;
            $order = isset($sort['order']) ? $sort['order'] : $order;
        }
        //DB::enableQueryLog();
        // ...
        $query = Parametro::select();
        $query = Parametro::filtroQuery($query, $filtro);

        return $query->orderBy($fieldName, $order)->paginate($pageSize);
    }

    public function gridOptions($version = "")
    {
        switch ($version) {
            case "2":
                $columnDefs[] = array("prop" => "den_parametro", "name" => "Fecha", "key" => "den_parametro");
                $columnDefs[] = array("prop" => "val_parametro", "name" => "Valor");
                $columnDefs[] = array("prop" => "des_parametro", "name" => "Descripción");
                $columnDefs[] = array("prop" => "aud_stm_ingreso", "name" => "Fecha Alta", "pipe" => "ftDateTime", "searchtype" => "date");
                break;
            default:
                $columnDefs[] = array("field" => "den_parametro", "displayName" => "Parámetro");
                $columnDefs[] = array("field" => "val_parametro", "displayName" => "Valor");
                $columnDefs[] = array("field" => "des_parametro", "displayName" => "Descripción");
                $columnDefs[] = array("field" => "aud_stm_ingreso", "displayName" => "Fecha Alta", "type" => "date", "cellFilter" => "ftDateTime");
        }
        $columnKeys = ['den_parametro'];

        $filtros[] = array('id' => 'den_parametro', 'name' => 'Parámetro');
        $filtros[] = array('id' => 'val_parametro', 'name' => 'Valor');
        $filtros[] = array('id' => 'des_parametro', 'name' => 'Descripción');

        $rango['desde'] = array('id' => 'aud_stm_ingreso', 'tipo' => 'datetime');
        $rango['hasta'] = $rango['desde'];

        return array("columnKeys" => $columnKeys, "columnDefs" => $columnDefs, "filtros" => $filtros, "rango" => $rango);
    }


    protected function cleanCaches()
    {
        Cache::forever("daemon_conf_ver", (int)Cache::get("daemon_conf_ver") + 1);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public static function detalle($den_parametro,$cod_ou)
    {
        $clave = json_decode(base64_decode($den_parametro), true);
        $den_parametro = $clave[0][0];
        return Parametro::find($den_parametro);
    }

    public function getParametro($den_parametro)
    {
        return Parametro::find($den_parametro);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $parametro = new Parametro;
        $parametro->den_parametro = $request->input('den_parametro');
        $parametro->val_parametro = $request->input('val_parametro');
        $parametro->des_parametro = $request->input('des_parametro');

        Parametro::addAuditoria($parametro, "A");
        $parametro->save();
        $this->cleanCaches();
        return response(['ok' => 'El Parámetro ' . $parametro->den_parametro . ' fue creado satisfactoriamente'], Response::HTTP_OK);
    }


    public function sendChatTest(Request $request)
    {
        $des_mensaje = $request->input('des_mensaje');

        try {

            $context = array(
                'msgtext' => $des_mensaje,
                'cod_tema' => "",
                'valor' => "INFO",
                'des_valor' => "INFO",
                'count' => 0,
                'color' => "",
                'ind_modo_prueba' => 0,
                'ind_notifica_evento' => 1,
            );

            Broadcast::driver('fast-web-socket')->broadcast(["io"], "INFO",  $context);


        } catch (Exception $e) {
            return response(['error' => 'Error enviando mensaje al grupo de chat'], Response::HTTP_CONFLICT);
        }

        return response(['ok' => 'Mensaje enviado al grupo'], Response::HTTP_OK);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function sendMailTest(Request $request)
    {
        $des_destinatario = $request->input('des_destinatario');
        $des_asunto = $request->input('des_asunto');
        $des_mensaje = $request->input('des_mensaje');

        $data['des_destinatarios'] = array($des_destinatario);
        $data['des_asunto'] =       $des_asunto;
        $data['des_mensaje'] =      $des_mensaje;

        try {
            //$job = (new SendMail('MAIL_ALERT_TMPL',$data))->onConnection('sync');
            $job = (new SendMail('MAIL_ALERT_TMPL', $data));
            dispatch($job->onQueue("low"));
        } catch (Exception $e) {
            return response(['error' => 'Error enviando mail'], Response::HTTP_CONFLICT);
        }

        return response(['ok' => 'Mail encolado'], Response::HTTP_OK);
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
        $den_parametro = $request->input('den_parametro');
        if ($den_parametro == "TIPO_DIAS")
            Cache::forget("FECHA_ACTUAL");
        $parametro = Parametro::find($den_parametro);
        $parametro->val_parametro = $request->input('val_parametro');
        $parametro->des_parametro = $request->input('des_parametro');

        Parametro::addAuditoria($parametro, "M");
        $parametro->save();
        ConfigParametro::clear($den_parametro);
        $this->cleanCaches();

        //si el io registra evento, guardar en el parametro REGISTRO_EVENTOS
        /*
        if($den_parametro=="IO_ETIQUETAS")
        {
            $io_registrar = array();
            $io_etiquetas = json_decode($parametro->val_parametro,true);
            $tema_local = Parametro::find("TEMA_LOCAL");
            $varegistro_eventos = Parametro::find("REGISTRO_EVENTOS");
            
            foreach($io_etiquetas as $io_nro=>$valores){
                if(isset($valores['registra_evento']))
                    if($valores['registra_evento'] == "true")
                        $io_registrar[] = $io_nro;
            }
            $val_param_registro = json_decode($varegistro_eventos->val_parametro,true);
            $val_param_registro[$tema_local->val_parametro] = $io_registrar;
            
            $varegistro_eventos->val_parametro = json_encode($val_param_registro,true);
            Parametro::addAuditoria($varegistro_eventos,"M");
            $varegistro_eventos->save();            
        }*/

        return response(['ok' => "Actualización exitosa #" . $den_parametro], Response::HTTP_OK);
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
        $den_parametro = $clave[0][0];
        $parametro = Parametro::find($den_parametro);
        $parametro->delete();
        ConfigParametro::clear($den_parametro);
        $this->cleanCaches();
        return response(['ok' => 'Se eliminó satisfactoriamente el Parámetro ' . $den_parametro], Response::HTTP_OK);
    }


    public function listDaemons()
    {
        $vadaemons = array(
            array("cod_daemon" => "MoviDisplayTemasDaemon", "nom_daemon" => "Gestiona Comando y Control", "des_daemon" => "También procesa audio evacuación"),
            array("cod_daemon" => "ActuadoresDaemon", "nom_daemon" => "Gestiona actuadores", "des_daemon" => "Procesa llamador y strobo"),
            array("cod_daemon" => "Area54Daemon", "nom_daemon" => "Gestiona centrales incendio", "des_daemon" => ""),
            array("cod_daemon" => "Rs485Daemon", "nom_daemon" => "Gestiona paneles", "des_daemon" => ""),
            array("cod_daemon" => "DelayedTemaDaemon", "nom_daemon" => "Control de dispositivos", "des_daemon" => "")
        );
        return response($vadaemons, Response::HTTP_OK);
    }

    public function restartDaemon(Request $request)
    {
        $cod_daemon = $request->input('cod_daemon');
        $context = array(
            'msgtext' => "Reinicio $cod_daemon",
            'cod_daemon' => $cod_daemon,
            'command' => "reset",
            'cod_tema' => ""
        );
        Broadcast::driver('fast-web-socket')->broadcast(["procesos", "pantalla"], "info",  $context);

        sleep(2);
        return response(['ok' => 'Orden enviada'], Response::HTTP_OK);
    }
}
