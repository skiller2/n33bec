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

class ModBusDaemon extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ModBusDaemon
                            {--debug : Print debug information to console}
                            {--linea= : Línea de captura}
                            {--tema= : Componente}
                            Ej: php artisan command:ModBusDaemon --linea=BLA BLA SENSOR1 XXXX';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ModBus Processing daemon';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temp_dir = "";
    protected $base_topic;
    private $temas;
    private $tema_local;
    private $confighash;
    private $config=array();
    const logFileName = "modbusdaemon";
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
                    'msgtext' => __("ModBus, actualizando configuración")
                );

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                $this->printDebugInfo($context['msgtext']);
                exit(); //
            }
        }
    }

    protected function loadConfigData()
    {
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $this->temas = ConfigParametro::getTemas();
        $modbus = ConfigParametro::get("MODBUS", true);
        
        foreach ($modbus as $mbconfig) {
            $ip= $mbconfig["ip"];
            $port= $mbconfig["port"];
            $subtema = $mbconfig["subtema"];
            $tema = $this->tema_local . "/" . $subtema;
            $this->config[] = array("ip"=>$ip, "port"=>$port, "tema"=>$tema);
        }

        $tmp_confighash = hash("sha256", $this->tema_local . json_encode($this->config));
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

    public function modbus($config)
    {
        echo "inicio ".var_export($config, true)."\n";

        $cod_daemon = basename(__FILE__, ".php");
        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => __("Inicio proceso :COD_DAEMON",['COD_DAEMON'=>$cod_daemon]) ));
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


        foreach ($this->config as $config) {
            EventLoop::delay(2, function() use ($config) { $this->modbus($config);});
        }
        EventLoop::run();
    }    
    //End Handle
}
