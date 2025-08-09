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
use App\MoviDisplayTema;
use App\Helpers\TemaValue;
use Carbon\Carbon;
use App\Http\Controllers\MoviDisplayTemas;

use Amp\Websocket\Options;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use function Amp\delay;
use Amp\Parallel\Worker\createWorker;

class MoviDisplayTemasDaemon extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:MoviDisplayTemasDaemon
                            {--debug : Print debug information to console}
                            {--linea= : Línea de captura}
                            {--tema= : Componente}
                            Ej: php artisan command:MoviDisplayTemasDaemon --linea=BLA BLA SENSOR1 XXXX';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'MoviDisplayTemas Processing daemon';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temp_dir = "";
    protected $base_topic;
    private $temas;
    private $tema_local;
    private $cod_tema_audioevac;
    private $confighash;
    const logFileName = "movidisplaytema";
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
                    'msgtext' => __("Audio evacuación, actualizando configuración")
                );

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                $this->printDebugInfo($context['msgtext']);
                EventLoop::stop();
            }
        }
    }

    protected function loadConfigData()
    {
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $this->temas = ConfigParametro::getTemas();
        $this->cod_tema_audioevac = strtolower(ConfigParametro::get("TEMA_AUDIOEVAC", false));

        if ($this->cod_tema_audioevac && !isset($this->temas[$this->cod_tema_audioevac])) {
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'alert',  array("msgtext" => "Parámetro TEMA_AUDIOEVAC tema ".$this->cod_tema_audioevac." no registrado"));
            $this->cod_tema_audioevac = "";
        }


        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->sectores = ConfigParametro::getSectores();

        $tmp_confighash = hash("sha256", $this->tema_local . $this->cod_tema_audioevac);
        if ($this->confighash != $tmp_confighash) {
            $this->confighash = $tmp_confighash;
            return true;
        } else
            return false;
    }

    public function audioevac($ind_modo_prueba,$cod_tema_disparo){

        $mdt = new MoviDisplayTemas;
        $evento_avisador = (stripos($this->temas[$cod_tema_disparo]['nom_tema'], "AVI") !== false) ? true : false;
        $res = $mdt->getLista();
        $vasectores = array();
        foreach ($res as $record){
            $cod_tema=          $record->cod_tema;
            $cod_sector=        $record->cod_sector;
            $tipo_evento=       $record->tipo_evento;
            $des_observaciones= $record->des_observaciones;

            //Cuento los eventos por sector y tipo
            $vasectores[$cod_sector][$tipo_evento]=(isset($vasectores[$cod_sector][$tipo_evento])) ? $vasectores[$cod_sector][$tipo_evento]+1:1;
//                echo "$cod_tema $cod_sector $tipo_evento $des_observaciones \n"; 

            if ($tipo_evento!="AL") continue;


            if ($vasectores[$cod_sector][$tipo_evento]>0){
                $cod_referencia = $this->sectores[$cod_sector]['cod_referencia'];
                $cod_tema = $this->cod_tema_audioevac;
                if ($cod_tema) {
                    $valor = array("cod_referencia"=>$cod_referencia,"tipo_evento"=>$tipo_evento,"cant_eventos"=>$vasectores[$cod_sector][$tipo_evento],"evento_avisador"=>$evento_avisador);
                    if ($ind_modo_prueba)
                        $level = "PRUEBAZ";
                    else if ($evento_avisador)
                        $level = "ALARM2"; 
                    else if ($vasectores[$cod_sector][$tipo_evento] > 1)
                        $level = "ALARM1";    
                    else
                        $level = "PREALM";

                    $extra_data = sprintf("CHG:PEPA\0%s:%s\0",$level,$cod_referencia);
                    $event_data = array("valor" => $valor, "des_observaciones" => $level." ".$cod_referencia, "extra_data"=> $extra_data);
                    event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                }
            }
        }
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
                if (isset($payloadDecoded['context']['command'])) {
                    switch (strtolower($payloadDecoded['context']['command'])) {
                        case 'reset':
                            exit();
                            break;
                        default:
                            # code...
                            break;
                    }
                }
            }

            if (isset($payloadDecoded['context']["cod_tema"])) {
                $cod_tema = $payloadDecoded['context']['cod_tema'];
                $valor = $payloadDecoded['context']['valor'];
                $stm_evento = $payloadDecoded['timeStamp'];
                $ind_modo_prueba = Cache::get("ind_modo_prueba", 0);
                $res = TemaValue::get($this->temas[$cod_tema], $valor);
                //                            $des_valor = $res['des_valor'];
                $tipo_evento = $res['tipo_evento'];
                $ind_display_evento = $this->temas[$cod_tema]['ind_display_evento'];

                if (!$ind_display_evento) continue;

                switch ($tipo_evento) {
                    case 'AL':
                        $this->printDebugInfo('dispara audioevac' . $payloadDecoded['timeStamp'] . ' : ' . $tipo_evento . " ". $cod_tema);
                        $this->audioevac($ind_modo_prueba,$cod_tema);
                        break;

                    default:
                        # code...
                        break;
                }
                //Ahora cuento la cantidad de alarmas por sectores

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

        //this->audioevac(false,"");
        EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );
        EventLoop::delay(2, function(){ $this->busmsg();});
        EventLoop::run();

    }
    //End Handle
}
