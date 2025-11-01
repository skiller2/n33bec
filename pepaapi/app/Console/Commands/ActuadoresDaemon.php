<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use App\Events\TemaEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Revolt\EventLoop;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Context\Parallel;
use Amp\Delayed;
use Amp\Parallel\Worker\BootstrapWorkerFactory;
use Amp\Process\Process;
use Amp\Websocket\Options;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use Amp\Parallel\Worker\createWorker;
use App\MoviDisplayTema;
use App\Helpers\TemaValue;
use Carbon\Carbon;
use App\Http\Controllers\MoviDisplayTemas;
use function Amp\delay;

class ActuadoresDaemon extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ActuadoresDaemon
                            {--debug : Print debug information to console}
                            {--linea= : Línea de captura}
                            {--tema= : Componente}
                            Ej: php artisan command:ActuadoresDaemon --linea=BLA BLA SENSOR1 XXXX';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actuadores Processing daemon';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temp_dir = "";
    protected $base_topic;
    private $temas;
    private $tema_local;
    private $cod_tema_rele_estrobo;
    private $cod_tema_rele_alarma;
    private $cod_tema_rele_falla;
    private $confighash;
    const logFileName = "actuadorestema";
    const channel = "movidisplaytema";
    protected $daemon_conf_ver = "";
    const confVersion = "daemon_conf_ver";
    const config_tag = "iolast_";


    protected function printDebugInfo($text, $status = "info")
    {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    public function checkConfigData()
    {
        if (Cache::get(self::confVersion) != $this->daemon_conf_ver) {
            if ($this->loadConfigData()) {
                $context = array(
                    'msgtext' => __("Actuadores, actualizando configuración")
                );

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                $this->printDebugInfo($context['msgtext']);
                exit(); //EventLoop::stop();
            }
        }
    }

    protected function loadConfigData()
    {
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $this->temas = ConfigParametro::getTemas();
        $actuadores = ConfigParametro::get("ACTUADORES", true);
        $this->cod_tema_rele_alarma = (isset($actuadores['cod_tema_rele_alarma'])) ? $actuadores['cod_tema_rele_alarma'] : "";
        $this->cod_tema_rele_falla = (isset($actuadores['cod_tema_rele_falla'])) ? $actuadores['cod_tema_rele_falla'] : "";
        $this->cod_tema_rele_estrobo = (isset($actuadores['cod_tema_rele_estrobo'])) ? $actuadores['cod_tema_rele_estrobo'] : "";

        if ($this->cod_tema_rele_alarma &&  !isset($this->temas[$this->cod_tema_rele_alarma])) {
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'alert',  array("msgtext" => __("Parámetro ACTUADORES tema :COD_TEMA_RELE_ALARMA no registrado",['COD_TEMA_RELE_ALARMA'=>$this->cod_tema_rele_alarma])));
            $this->cod_tema_rele_alarma = "";
        }

        if ($this->cod_tema_rele_falla && !isset($this->temas[$this->cod_tema_rele_falla])) {
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'alert',  array("msgtext" => __("Parámetro ACTUADORES tema :COD_TEMA_RELE_FALLA no registrado",['COD_TEMA_RELE_FALLA'=> $this->cod_tema_rele_falla])));
            $this->cod_tema_rele_falla = "";
        }

        if ($this->cod_tema_rele_estrobo && !isset($this->temas[$this->cod_tema_rele_estrobo])) {
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'alert',  array("msgtext" => __("Parámetro ACTUADORES tema :COD_TEMA_RELE_ESTROBO no registrado",['COD_TEMA_RELE_ESTROBO'=>$this->cod_tema_rele_estrobo])));
            $this->cod_tema_rele_estrobo = "";
        }

        $this->tiempo_seg = (isset($actuadores['tiempo_seg'])) ? $actuadores['tiempo_seg'] : "3";
        $this->tiempo_seg_estrobo = (isset($actuadores['tiempo_seg_estrobo'])) ? $actuadores['tiempo_seg_estrobo'] : "30";
        $this->valor_ini = (isset($actuadores['valor_ini'])) ? $actuadores['valor_ini'] : "1";
        $this->valor_fin = (isset($actuadores['valor_fin'])) ? $actuadores['valor_fin'] : "0";
        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->sectores = ConfigParametro::getSectores();

        $tmp_confighash = hash("sha256", $this->tema_local . json_encode($actuadores));
        if ($this->confighash != $tmp_confighash) {
            $this->confighash = $tmp_confighash;
            return true;
        } else
            return false;
    }

    public function busmsg()
    {
        $cod_daemon = basename(__FILE__, ".php");

        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => __("Inicio proceso :COD_DAEMON",['COD_DAEMON'=>$cod_daemon])));

        $context = array(
            'msgtext' => "",
            'cod_tema' => "",
            'cod_daemon' => $cod_daemon,
            'command' => 'start'
        );
        Broadcast::driver('fast-web-socket')->broadcast(["procesos"], "info",  $context);

        $connectionFactory = new Rfc6455ConnectionFactory(
            heartbeatQueue: new PeriodicHeartbeatQueue(
                heartbeatPeriod: 5, // 5 seconds
            ),
            rateLimit: new ConstantRateLimit(
                bytesPerSecondLimit: 2 ** 17, // 128 KiB
                framesPerSecondLimit: 10,
            ),
            parserFactory: new Rfc6455ParserFactory(
                messageSizeLimit: 2 ** 20, // 1 MiB
            ),
            frameSplitThreshold: 2 ** 14, // 16 KiB
            closePeriod: 0.5, // 0.5 seconds
        );
        
        $connector = new Rfc6455Connector($connectionFactory);
        $constr = "ws://localhost:80/wssub/procesos/0/1/2/3/4/5/6?token='da'&cod_usuario='fds'";
        $handshake = (new WebSocketHandshake($constr));
        $lastTimeStamp =  Cache::get($cod_daemon . "timestamp");

        $this->printDebugInfo('Conectando con ' . $constr);

        $connection = $connector->connect($handshake);
        foreach ($connection as $message) {
            $payload = $message->buffer();
            $payloadDecoded = json_decode($payload, true);

            $tmpmsg = $payloadDecoded['context']["msgtext"];
            if ($payloadDecoded['timeStamp'] . "-" . hash('sha256', $tmpmsg) <= $lastTimeStamp) {
                $this->printDebugInfo('skip ' . $payloadDecoded['timeStamp'] . ' : ' . $tmpmsg);
                continue;
            }

            $lastTimeStamp = $payloadDecoded['timeStamp'] . "-" . hash('sha256', $tmpmsg);
            Cache::forever($cod_daemon . "timestamp", $lastTimeStamp);

            if (isset($payloadDecoded['context']["cod_daemon"]) && $payloadDecoded['context']["cod_daemon"] == $cod_daemon) {
                if (isset($payloadDecoded['context']['command'])  && $payloadDecoded['context']['command'] == "reset")
                exit(); //EventLoop::stop();
            }
        }
    }

    public function actuadores()
    {
        $cod_daemon = basename(__FILE__, ".php");

        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => __("Inicio proceso :COD_DAEMON",['COD_DAEMON'=>$cod_daemon]) ));

        $context = array(
            'msgtext' => "",
            'cod_tema' => "",
            'cod_daemon' => $cod_daemon,
            'command' => 'start'
        );
        Broadcast::driver('fast-web-socket')->broadcast(["procesos"], "info",  $context);

        $connectionFactory = new Rfc6455ConnectionFactory(
            heartbeatQueue: new PeriodicHeartbeatQueue(
                heartbeatPeriod: 5, // 5 seconds
            ),
            rateLimit: new ConstantRateLimit(
                bytesPerSecondLimit: 2 ** 17, // 128 KiB
                framesPerSecondLimit: 10,
            ),
            parserFactory: new Rfc6455ParserFactory(
                messageSizeLimit: 2 ** 20, // 1 MiB
            ),
            frameSplitThreshold: 2 ** 14, // 16 KiB
            closePeriod: 0.5, // 0.5 seconds
        );
        
        $connector = new Rfc6455Connector($connectionFactory);
        $constr = "ws://localhost:80/wssub/procesos/0/1/2/3/4/5/6?token='da'&cod_usuario='fds'";
        $handshake = (new WebSocketHandshake($constr));
        $lastTimeStamp =  Cache::get($cod_daemon . "timestamp");

        $this->printDebugInfo('Conectando con ' . $constr);

        $connection = $connector->connect($handshake);
        foreach ($connection as $message) {
            $payload = $message->buffer();
            $payloadDecoded = json_decode($payload, true);

            $tmpmsg = $payloadDecoded['context']["msgtext"];
            if ($payloadDecoded['timeStamp'] . "-" . hash('sha256', $tmpmsg) <= $lastTimeStamp) {
                $this->printDebugInfo('skip ' . $payloadDecoded['timeStamp'] . ' : ' . $tmpmsg);
                continue;
            }

            if (isset($payloadDecoded['context']["cod_tema"])) {
                $cod_tema = $payloadDecoded['context']['cod_tema'];
                $valor = isset($payloadDecoded['context']['valor'])?$payloadDecoded['context']['valor']:"";
                if (!$cod_tema) continue;
                $stm_evento = $payloadDecoded['timeStamp'];
                $ind_modo_prueba = Cache::get("ind_modo_prueba", 0);
                $res = TemaValue::get($this->temas[$cod_tema], $valor);
                
                //                            $des_valor = $res['des_valor'];
                $tipo_evento = $res['tipo_evento'];
                $ind_display_evento = $this->temas[$cod_tema]['ind_display_evento'];
                $cod_sector = $this->temas[$cod_tema]['cod_sector'];
                $cant_temas_sector = $this->sectores[$cod_sector]['cant_cod_tema'];
                $nom_sector = $this->sectores[$cod_sector]['nom_sector'];
                $evento_avisador = (stripos($this->temas[$cod_tema]['nom_tema'], "AVI") !== false) ? true : false;
                $contador_sector_alarm = 0;


                switch ($tipo_evento) {
                    case 'AL':
                        if ($this->cod_tema_rele_estrobo) {
                            $event_data = array("valor" => $this->valor_ini, "delay" => $this->tiempo_seg_estrobo, "valor_fin" => $this->valor_fin, "des_valor" => "", "des_observaciones" => "");
                            event(new TemaEvent($this->cod_tema_rele_estrobo, Carbon::now(), $event_data));
                        }

                        if (!$this->cod_tema_rele_alarma ) 
                            break;

                        $mdt = new MoviDisplayTemas;
                        $res = $mdt->getLista();

                        foreach ($res as $record) {
                            $cod_tema_row =          $record->cod_tema;
                            $cod_sector_row =        $record->cod_sector;
                            $tipo_evento_row =       $record->tipo_evento;
                            $des_observaciones_row = $record->des_observaciones;

                            if ($tipo_evento != "AL" || $cod_sector != $cod_sector_row) continue;

                            $contador_sector_alarm++;
                        }

                        if ($ind_modo_prueba == true){

                            $msg = __("Llamador, ignora llamada :TIPO_EVENTO, sector :NOM_SECTOR, alarmas activas :CONTADOR_SECTOR_ALARM, cantidad dispositivos :CANT_TEMAS_SECTOR, prueba :IND_MODO_PRUEBA, avisador :EVENTO_AVISADOR",['TIPO_EVENTO'=>$tipo_evento,'NOM_SECTOR'=>$nom_sector,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CANT_TEMAS_SECTOR'=>$cant_temas_sector,'IND_MODO_PRUEBA'=>$ind_modo_prueba]);
                            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => $msg));
                            break;
                        }


                        if ($evento_avisador || $contador_sector_alarm > 1 || $cant_temas_sector == 1) {
                            $event_data = array("valor" => $this->valor_ini, "delay" => $this->tiempo_seg, "valor_fin" => $this->valor_fin, "des_valor" => "", "des_observaciones" => "sector: $nom_sector, alarmas: $contador_sector_alarm");
                            event(new TemaEvent($this->cod_tema_rele_alarma, Carbon::now(), $event_data));

                            $msg = __("Llamador, activo llamada :TIPO_EVENTO, sector :NOM_SECTOR, alarmas activas :CONTADOR_SECTOR_ALARM, cantidad dispositivos :CANT_TEMAS_SECTOR, prueba :IND_MODO_PRUEBA, avisador :EVENTO_AVISADOR",
                            ['TIPO_EVENTO'=>$tipo_evento,'NOM_SECTOR'=>$nom_sector,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CANT_TEMAS_SECTOR'=>$cant_temas_sector,'IND_MODO_PRUEBA'=>$ind_modo_prueba]);
                            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => $msg));
                        } else {
                            $msg = __("Llamador, ignora llamada :TIPO_EVENTO, sector :NOM_SECTOR, alarmas activas :CONTADOR_SECTOR_ALARM, cantidad dispositivos :CANT_TEMAS_SECTOR, prueba :IND_MODO_PRUEBA, avisador :EVENTO_AVISADOR",
                            ['TIPO_EVENTO'=>$tipo_evento,'NOM_SECTOR'=>$nom_sector,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CANT_TEMAS_SECTOR'=>$cant_temas_sector,'IND_MODO_PRUEBA'=>$ind_modo_prueba]);
                            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => $msg));
                        }

                        break;
                    case 'FA':
                        $this->printDebugInfo('dispara ' . $payloadDecoded['timeStamp'] . ' : ' . $tipo_evento);


                        if (!$this->cod_tema_rele_falla)
                            break;

                        if ($ind_modo_prueba == true){
                            $msg = __("Llamador, ignora llamada :TIPO_EVENTO, sector :NOM_SECTOR, alarmas activas :CONTADOR_SECTOR_ALARM, cantidad dispositivos :CANT_TEMAS_SECTOR, prueba :IND_MODO_PRUEBA, avisador :EVENTO_AVISADOR",['TIPO_EVENTO'=>$tipo_evento,'NOM_SECTOR'=>$nom_sector,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CANT_TEMAS_SECTOR'=>$cant_temas_sector,'IND_MODO_PRUEBA'=>$ind_modo_prueba,'EVENTO_AVISADOR'=>$evento_avisador]);
                            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => $msg));
                            break;
                        }


                        $event_data = array("valor" => $this->valor_ini, "delay" => $this->tiempo_seg, "valor_fin" => $this->valor_fin, "des_valor" => "", "des_observaciones" => "");
                        event(new TemaEvent($this->cod_tema_rele_falla, Carbon::now(), $event_data));
                        $msg = __("Llamador, activo llamada :TIPO_EVENTO, sector :NOM_SECTOR, alarmas activas :CONTADOR_SECTOR_ALARM, cantidad dispositivos :CANT_TEMAS_SECTOR, prueba :IND_MODO_PRUEBA, avisador :EVENTO_AVISADOR",['TIPO_EVENTO'=>$tipo_evento,'NOM_SECTOR'=>$nom_sector,'CONTADOR_SECTOR_ALARM'=>$contador_sector_alarm,'CANT_TEMAS_SECTOR'=>$cant_temas_sector,'IND_MODO_PRUEBA'=>$ind_modo_prueba,'EVENTO_AVISADOR'=>$evento_avisador]);
                        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => $msg));
                        break;

                    default:
                        # code...
                        break;
                }


                $lastTimeStamp = $payloadDecoded['timeStamp'] . "-" . hash('sha256', $tmpmsg);
                Cache::forever(self::logFileName . "timestamp", $lastTimeStamp);
            }
        }
    }



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->temp_dir = sys_get_temp_dir();
        $this->loadConfigData();
        //        $factory = new BootstrapWorkerFactory(__DIR__ . '/daemon.php');
        //        $this->poolEvents = new DefaultPool(10, $factory);

        if ($this->option("linea") && $this->option("tema")) {
            $linea = $this->option("linea");
            $subtema = $this->option("tema");
            //            $event_data = array("valor" => $valor, "des_valor"=>$valor, "measure_unit"=>"Display");
            //            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
            $cod_tema = $this->tema_local . "/" . $subtema;
            //            $this->poolEvents->enqueue(new EventAsync485AreaTask($cod_tema, $linea));
            return;
        }

        EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );
        EventLoop::delay(2, function(){ $this->busmsg();});
        EventLoop::delay(2, function(){ $this->actuadores();});
        EventLoop::run();
    }    
    //End Handle
}
