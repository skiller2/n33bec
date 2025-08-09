<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Events\TemaEvent;
use App\Events\EventAsync485AreaTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Revolt\EventLoop;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Parallel\Context\Parallel;
use Amp\Delayed;
use Amp\Sync\Channel;
use Amp\Cancellation;
use Amp\Process\Process;
use Amp\Websocket\Options;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use function Amp\delay;
use Amp\Parallel\Worker\createWorker;

class Area54Daemon extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:Area54Daemon
                            {--debug : Print debug information to console}
                            {--linea= : Línea de captura}
                            {--tema= : Componente}
                            Ej: php artisan command:Area54Daemon --linea=BLA BLA SENSOR1 XXXX';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Area54 Processing daemon';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temp_dir = "";
    protected $base_topic;
    private $poolEvents;
    private $pipes;
    private $config = array();
    private $process = array();
    private $confighash;
    private $tema_local;
    private $command;
    private $proc;

    const logFileName = "area54";
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
                    'msgtext' => __("Proceso BUS485AREA actualizando configuración")
                );

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                $this->printDebugInfo($context['msgtext']);
                exit();
            }
        }
    }


    public function rs485areaproc($watch_id, $config)
    {
        $command = $config['command'];
        $pi = pathinfo($command);
        $command_short = $pi['basename'];
        $tema_base = $config['tema'];
        $linecache = "";
        $process = "";
        $suspension = EventLoop::getSuspension();

        while (true) {
            $process = Process::start($command);
            $this->process[$tema_base] = $process;

            if (!$process->isRunning())
                break;

            $context = array(
                'msgtext' => __("Conexión exitosa con :COMMAND_SHORT PID :PID" ,['COMMAND_SHORT'=>$command_short,'PID'=>$process->getPid()])
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
            $this->printDebugInfo($context['msgtext']);

            $stream = $process->getStdout();

            while (null !== $chunk =  $stream->read() and $process->isRunning()) {
                $linecache .= $chunk;
                $len = strpos($linecache, "\n");

                while ($len !== false) {
                    $line = substr($linecache, 0, $len);

                    //$wc=$this->poolEvents->getWorkerCount();
                    //echo "data: $line, WorkerCount $wc\n" ;

                    $line = mb_convert_encoding($line, "UTF-8", "ISO-8859-1");
                    if (strlen($line) > 10)
                        $this->poolEvents->submit(new EventAsync485AreaTask($tema_base, $line));


                    $linecache = substr($linecache, $len + 1);
                    $len = strpos($linecache, "\n");
//                     new Delayed(50);
                }
            }

            $code =  $process->join();
//            $process->__destruct();
            $context = array(
                'msgtext' => __("Se cerró el proceso :COMMAND_SHORT con código :CODE",['COMMAND_SHORT'=>$command_short,'CODE'=>$code])
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);
            $this->printDebugInfo($context['msgtext']);

            EventLoop::delay(5, function () use ($suspension): void {
                $suspension->resume(null);
            });
            $suspension->suspend();
        }
    }

    public function rs485areaproc2()
    {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w") // stderr
        );

        $timeout = 500;

        $this->proc = proc_open($this->command, $descriptorspec, $this->pipes, "/tmp");

        if ($this->proc === false) {
            $context = array(
                'msgtext' => __("Error iniciando proceso BUS485AREA")
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);
            $this->printDebugInfo($context['msgtext']);

            return;
        }

        $write  = array(); //array($this->pipes[0]);
        $read   = array($this->pipes[1], $this->pipes[2]);
        $except = array();

        $context = array(
            'msgtext' => __("Conexión exitosa con BUS485AREA")
        );

        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
        $this->printDebugInfo($context['msgtext']);

        while (false !== ($r = stream_select($read, $write, $except, null, $timeout))) {
            foreach ($read as $stream) {
                if ($stream === $this->pipes[1]) {
                    if (feof($stream)) {
                        $context = array(
                            'msgtext' => __("Se cerró el proceso BUS485AREA")
                        );
                        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);
                        $this->printDebugInfo($context['msgtext']);

                        //Debo parar el proceso....
                        exit();
                    } else {
                        $buffline = stream_get_line($stream, 1024, "\n");
                        $buffline = mb_convert_encoding($buffline, "UTF-8", "ISO-8859-1");
                        if (strlen($buffline) > 1)
                            $this->poolEvents->submit(new EventAsync485AreaTask($this->base_topic, $buffline));
                    }
                }
            }
//            new Delayed(50);
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
                        case 'bus':
                            $subcommand = (isset($payloadDecoded['context']['subcommand'])) ? $payloadDecoded['context']['subcommand'] : "";
                            $subtema = (isset($payloadDecoded['context']['bus_id'])) ? $payloadDecoded['context']['bus_id'] : "";
                            $tema_base = $this->tema_local . "/" . $subtema;
                            $notifica = false;
                            if (isset($this->process[$tema_base])) {
                                $write = "";
                                switch ($subcommand) {
                                    case 'reset':
                                        $write = "1400";
                                        $notifica = true;
                                        break;
                                    case 'ack':
                                        $write = "1F00";
                                        $notifica = true;
                                        break;
                                    case 'up':
                                        $write = "0B00";
                                        break;
                                    case 'down':
                                        $write = "0C00";
                                        break;
                                    case 'left':
                                        $write = "0D00";
                                        break;
                                    case 'right':
                                        $write = "0E00";
                                        break;
                                    default:
                                        # code...
                                        break;
                                }
                                if ($notifica) {
                                    $context = array(
                                        'msgtext' => __("Comando enviado :SUBCOMMAND",['SUBCOMMAND'=>$subcommand]),
                                        'cod_tema' => "",
                                        'cod_daemon' => $cod_daemon,
                                        //'command' => 'start'
                                    );
                                    Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], "info",  $context);
                                }
                                $this->process[$tema_base]->getStdin()->write("$write\n");
                            }

                            break;
                        default:
                            # code...
                            break;
                    }
                }
            }
        }
    }




    protected function loadConfigData()
    {
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $executable = "";
        $area54conf = ConfigParametro::get('AREA54_CONF', false);
        $vaarea54conf = explode(",", $area54conf);
        $licence =  ConfigParametro::get('LICENCIA', false);
        $licence =  ($licence) ? $licence : "UNLICENCED";
        $timeout =  5000;
        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $tmp_confighash = hash("sha256", $area54conf);
        if ($this->confighash != $tmp_confighash) {
            $this->confighash = $tmp_confighash;

            foreach ($vaarea54conf as $index => $config) {
                $vaconfig = explode(":", $config);
                if (count($vaconfig) < 2) continue;
                $ipdev = $vaconfig[0];
                $baudrateport = $vaconfig[1];
                $subtema = $vaconfig[2];
                $protocol = strtolower(isset($vaconfig[3]) ? $vaconfig[3] : "area");
                switch ($protocol) {
                    case 'area':
                    case 'rs485area':
                        $executable = "rs485area";
                        break;
                    case 'notifier':
                    case 'termnotif':
                        $executable = "termnotif";
                        break;
                    case 'contact_id':
                        $executable = "contact_id";
                        $timeout =  90000;

                        break;

                    default:
                        $executable = "rs485area";
                        break;
                }

                if (stripos($ipdev, "null") !== false)
                    continue;
                $this->config[$index]['command'] = dirname(__FILE__) . "/../../../bin/$executable $ipdev $baudrateport $timeout $licence";
                $this->config[$index]['tema']    = $this->tema_local . "/" . $subtema;
            }
            return true;
        } else
            return false;
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
        //createWorker();
        $this->poolEvents =  \Amp\Parallel\Worker\createWorker();



        if ($this->option("linea") != "" && $this->option("tema") != "") {
            $linea = $this->option("linea");
            $subtema = $this->option("tema");
            //            $event_data = array("valor" => $valor, "des_valor"=>$valor, "measure_unit"=>"Display");
            //            event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
            $cod_tema = $this->tema_local . "/" . $subtema;
            $this->poolEvents->submit(new EventAsync485AreaTask($cod_tema, $linea));
            return;
        }
       
        if (!defined('SIGINT'))
            define('SIGINT', 0);
        if (!defined('SIGTERM'))
            define('SIGTERM', 0);
        if (!defined('SIGHUP'))
            define('SIGHUP', 0);


        foreach (array(constant('SIGINT'), constant('SIGTERM'), constant('SIGHUP')) as $signal) {
            EventLoop::unreference(
                EventLoop::onSignal(
                    $signal,
                    function () use ($signal) {
                        /*      
                        $context = array(
                            'msgtext' => __("Deteniendo procesos AREA54")
                        );
                        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'warning',  $context);
                        $this->printDebugInfo($context['msgtext']);
                */
                //        $this->poolEvents->shutdown();
                        exit();
                        return;
                    }
                )
            );
        }

        EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );
        EventLoop::delay(2, function(){ $this->busmsg();});

        foreach ($this->config as $config) {
            Cache::forever(self::config_tag . $config['tema'] . "display_area54", array());
            EventLoop::delay(1, function() use ($config):void {$this->rs485areaproc(0,$config);});
        }

        EventLoop::run();
    }
    //End Handle
}
