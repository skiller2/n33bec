<?php

namespace App\Console\Commands;

use App\Events\TemaEvent;
use App\Events\CheckComAsyncTask;
use App\Events\EventAsyncTask;
use App\Helpers\ConfigParametro;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Amp\Parallel\Worker;
use Amp\Promise;
use Revolt\EventLoop;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\CallableTask;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\TaskException;
use Amp\Parallel\Worker\WorkerException;
use Event;
use Exception;
use Amp\Sync\Channel;
use Amp\Cancellation;
use Amp\Delayed;
use Illuminate\Support\Env;
use Illuminate\Support\Facades;

use Amp\Websocket\Options;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use function Amp\delay;
use Amp\Parallel\Worker\createWorker;



use Illuminate\Support\Facades\Broadcast;


//use Symfony\Component\Console\Application;
//use Illuminate\Contracts\Foundation\Application;

class DelayedTemaDaemon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:DelayedTemaDaemon
                            {--debug : Print debug information to console}
                            ej: php artisan command:DelayedTemaDaemon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delayed Temas Processing daemon';

    /**
     * The console command description.
     *
     * @var json
     */

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temas = array();
    protected $temas_comunic = array();
    protected $tema_local;
    protected $timezone = "";
    protected $daemon_conf_ver = "";
    protected $connected = false;
    const confVersion = "daemon_conf_ver";
    const logFileName = "delayedio";
    const config_tag = "iolast_";
    private $poolEvents;
    
    protected function printDebugInfo($text, $status = "info")
    {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    protected function loadConfigData()
    {
        $this->tema_local    = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $this->temas         = ConfigParametro::getTemas('');
        $this->temas_comunic = ConfigParametro::getTemas('COMUNIC');
        $this->timezone      = ConfigParametro::get('TIMEZONE_INFORME', false);
        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    public function checkConfigData()
    {
        if (Cache::get(self::confVersion) != $this->daemon_conf_ver) {
            $this->loadConfigData();
        }
    }

    public function checkClock()
    {
        //ntpq -c rv

        if (Cache::get(self::confVersion) != $this->daemon_conf_ver) {
            $this->loadConfigData();
        }
    }


    public function checkInternetConexion()
    {
        $retry = 3;
        $vaaddr = array("www.google.com", "www.microsoft.com");
        $cod_tema = $this->tema_local . "/internet_status";

        $old_value = Cache::get(DelayedTemaDaemon::config_tag . $cod_tema);
        $this->connected = false;

        while ($retry > 0) {
            $retry--;
            foreach ($vaaddr as $key => $addr) {
                if (!$socket = @fsockopen($addr, 80, $num, $error, 5)) {
                } else {
                    $this->connected = true;
                    $retry = 0;
                    break;
                }
            }
            if ($retry > 0)
                 delay(2);
        }

        if ($old_value != $this->connected) {
            $event_data = array("valor" => ($this->connected) ? 1 : 0, "des_observaciones" => ($this->connected) ? "": "Sin acceso a Internet");
            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
        }
    }




    public function checkReachTopic()
    {
        //echo "Start checking \n\n";
        while (true) {

            foreach ($this->temas_comunic as $cod_tema => $tema) {

                $intervalo_seg = $tema['intervalo_seg'];
                if (!isset($this->temas_comunic[$cod_tema]['next']))
                    $this->temas_comunic[$cod_tema]['next'] = Carbon::now()->addSeconds($intervalo_seg);
                $stm_actual = Carbon::now();
                if ($this->temas_comunic[$cod_tema]['next'] > $stm_actual) {
                     delay(1);
                    continue;
                }

                $url_check = $tema['url_check'];
                $this->temas_comunic[$cod_tema]['next'] = Carbon::now()->addSeconds($intervalo_seg);

                if ($url_check != "") {
                    try {
                        $this->poolEvents->submit(new CheckComAsyncTask($cod_tema, $url_check));
                    } catch (Exception $e) {
                        echo ("Error" . $e->getMessage() . "  \n\n");
                        Log::channel(self::logFileName)->info("Error ejecutando tarea $cod_tema " . $e->getMessage(), array($cod_tema));
                    }
                } else {
                    //Lee el valor desde cache
                    $comm_value = Cache::get(DelayedTemaDaemon::config_tag . $cod_tema . "_comm");
                    $curr_value = Cache::get(DelayedTemaDaemon::config_tag . $cod_tema);
                    if ($comm_value == NULL) $comm_value = 0;
                    if ($curr_value != $comm_value) {
                        $event_data = array("valor" => $comm_value, "des_observaciones" => "Intervalo $intervalo_seg segundos");
                        event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                    }
                }
                 delay(1);
            }
            //$this->printDebugInfo('Loop topics done');            
             delay(0.5);
        }
    }



    public function delayTopicRetencion()
    {
        $caNow = Carbon::now();
        $vaPendDelay = Cache::get('retencion', array());

        foreach ($vaPendDelay as $cod_tema => $tiempovalor) {
            $valor = Cache::get("iolast_" . $cod_tema, "");
            $valor_old = $tiempovalor[1];
            if ($caNow < $tiempovalor[0])
                continue;

            $event_data = array(
                "valor" => $valor,
                "des_observaciones" => "retenido",
                "json_detalle" => ""
            );

            try {
                if ($valor_old == $valor)
                    $this->poolEvents->submit(new EventAsyncTask($cod_tema, $event_data));

                $vaPendDelaytmp = Cache::get('retencion', array());
                unset($vaPendDelaytmp[$cod_tema]);
                Cache::forever("retencion", $vaPendDelaytmp);
            } catch (Exception $e) {
                echo ("Error" . $e->getMessage() . "  \n\n");
                Log::channel(self::logFileName)->info("Error ejecutando tarea $cod_tema " . $e->getMessage(), array($cod_tema, $event_data));
            }
        }
    }


    public function delayTopicActions()
    {
        $caNow = Carbon::now();
        $vaPendDelay = Cache::get('delayed', array());

        foreach ($vaPendDelay as $cod_tema => $tiempovalor) {
            if ($caNow < $tiempovalor[0])
                continue;
            $valor = $tiempovalor[1];
            $event_data = array(
                "valor" => $valor,
                "des_observaciones" => "",
                "json_detalle" => ""
            );

            try {
                $this->poolEvents->submit(new EventAsyncTask($cod_tema, $event_data));
            } catch (Exception $e) {
                echo ("Error" . $e->getMessage() . "  \n\n");
                Log::channel(self::logFileName)->info("Error ejecutando tarea $cod_tema " . $e->getMessage(), array($cod_tema, $event_data));
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
        $this->loadConfigData();
        $this->poolEvents =  \Amp\Parallel\Worker\createWorker();



        EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );

        EventLoop::repeat($sInterval = 2, function() { $this->checkInternetConexion();});
        EventLoop::repeat($sInterval = 1, function() { $this->delayTopicRetencion();});
        EventLoop::repeat($sInterval = 1, function() { $this->delayTopicActions();});
        EventLoop::delay(1, function() { $this->busmsg();});
        EventLoop::delay(1, function() { $this->checkReachTopic();});
        EventLoop::run();
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
                $command = strtolower((isset($payloadDecoded['context']['command'])) ? $payloadDecoded['context']['command'] : "empty");
                switch ($command) {
                    case 'reset':
                        exit(); //EventLoop::stop();
                        break;
                    
                    default:
                        # code...
                        break;
                }
            }
        }
    }

    protected function reporteVencido($plazo_disp_sin_reportar, $stm_ult_reporte, $stm_actual)
    {

        $arr = preg_split('/(?<=[0-9])(?=[a-z]+)/i', $plazo_disp_sin_reportar);
        $valor_tiempo_sin_reportar = (isset($arr[0])) ? $arr[0] : "";
        $unidad_medida_tiempo = (isset($arr[1])) ? $arr[1] : "";
        if ($valor_tiempo_sin_reportar == "" || $unidad_medida_tiempo == "") {
            return "0";
        }

        switch ($unidad_medida_tiempo) {
            case "I":
                $limit = $stm_ult_reporte->addMinutes($valor_tiempo_sin_reportar);
                break;
            case "H":
                $limit = $stm_ult_reporte->addHours($valor_tiempo_sin_reportar);
                break;
            case "D":
                $limit = $stm_ult_reporte->addDays($valor_tiempo_sin_reportar);
                break;
            case "M":
                $limit = $stm_ult_reporte->addMonths($valor_tiempo_sin_reportar);
                break;
            case "Y":
                $limit = $stm_ult_reporte->addYears($valor_tiempo_sin_reportar);
                break;
        }

        if ($limit < $stm_actual)
            return "1";

        return "0";
    }
}
