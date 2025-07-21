<?php

namespace App\Console\Commands;

use App\Events\TemaEvent;
use App\Events\EventAsync485Task;
use Illuminate\Console\Command;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Middleware\ComunicacionDispositivos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Revolt\EventLoop;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Sync\Channel;
use Amp\Cancellation;
use Amp\Socket;
use Amp\Delayed;
use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\Parallel\Worker\ContextWorkerPool;
use Amp\Process\Process;
use Amp\Websocket\Options;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use Amp\Parallel\Worker\createWorker;
use function Amp\delay;
use App\Helpers\TemaValue;


function parseMsg($buff, $log)
{
    $evento = json_decode($buff, true);
    $origin = "";
    $gpio = "";
    $valor = "";

    if ($evento != null) {
        $origin = (isset($evento['origin'])) ? $evento['origin'] : "";
        $gpio = (isset($evento['gpio'])) ? $evento['gpio'] : "";
        $valor = (isset($evento['valor'])) ? $evento['valor'] : "";
    } else {
        $context = array(
            'msgtext' => __("Error decodificando tam :TAM, data :BUFF",['TAM'=>strlen($buff),'BUFF'=>$buff]) 
        );
        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);
        $log->info($context['msgtext'], array());
    }
    return array(
        "origin" => $origin,
        "gpio" => $gpio,
        "valor" => $valor
    );
}

class Rs485Daemon extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:Rs485Daemon
                            {--debug : Print debug information to console}
                            {--cod_tema= : Identificacion de origen ej desa/lector/218}
                            {--device= : Fijar /dev/ttyUSB0}
                            {--baud= : Fijar 115200}
                            {--subtema= : Fijar bus4}
                            {--value= : lectura ej 9877187}
                            Ej: php artisan command:Rs485Daemon --cod_tema=desa/lector/218 --value=9877187';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RS485 Processing daemon';

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
    protected $temp_dir = "";
    //    protected $base_topic;
    protected $command;
    private $poolEvents;
    private $process = array();
    private $confighash;
    private $config;
    private $tema_local;
    const logFileName = "serial";
    const timetolog = 0;  //0.1
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
                    'msgtext' => __("Proceso BUS485 actualizando configuración")
                );

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                $this->printDebugInfo($context['msgtext']);

                //EventLoop::stop();
                exit();
            }
        }
    }

    public function testsend()
    {
        $context = array(
            'msgtext' => __("BUS485 Check interno OK")
        );
        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
    }
/*
    public function socklisten()
    {
        $datagram = DatagramSocket::bind('127.0.0.1:1337');
        $this->printDebugInfo("Datagram active on {$datagram->getAddress()}");
        while ([$address, $data] = $datagram->receive()) {
            //            $data = \sprintf("Received '%s' from %s\n", \trim($data), $address);
            $decode = json_decode($data,true);
            $tema_base = $decode["cod_tema"];
            $tema_base = substr($tema_base,0,strrpos($tema_base,"/"));
            $tema_base = substr($tema_base,0,strrpos($tema_base,"/"));
            $decode_data = $decode["data"];
            $context = array(
                'msgtext' => __("Enviado :DECODE_DATA a :TEMA_BASE",['TEMA_BASE'=>$tema_base,'DECODE_DATA'=>$decode_data])
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
            if (isset($this->process[$tema_base]) && $this->process[$tema_base]->isRunning())
                $this->process[$tema_base]->getStdin()->write("$decode_data\n");
        }
    }
*/
    public function socklisten()
    {
    $datagram = Socket\bindUdpSocket('127.0.0.1:1337');
    $this->printDebugInfo("Datagram active on {$datagram->getAddress()}");

    /** @psalm-suppress PossiblyNullArrayAccess */
    while ([$address, $data] = $datagram->receive()) {
        assert($address instanceof Socket\SocketAddress);
        assert(is_string($data));

            $decode = json_decode($data,true);
            $tema_base = $decode["cod_tema"];
            $tema_base = substr($tema_base,0,strrpos($tema_base,"/"));
            $tema_base = substr($tema_base,0,strrpos($tema_base,"/"));
            $decode_data = $decode["data"];
            $context = array(
                'msgtext' => __("Enviado :DECODE_DATA a :TEMA_BASE",['DECODE_DATA'=>$decode_data,'TEMA_BASE'=>$tema_base])
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
            if (isset($this->process[$tema_base]) && $this->process[$tema_base]->isRunning())
                $this->process[$tema_base]->getStdin()->write("$decode_data\n");


//        $datagram->send($address, $message);
    }
    }

    public function rs485proc($watch_id, $config)
    {
        $command = $config['command'];
        $pi = pathinfo($command);
        $command_short = $pi['basename'];
        $tema_base = $config['tema'];
        $linecache = "";
        $process = "";
        while (true) {
            $process = Process::start($command);
            $this->process[$tema_base] = $process;

            $ignoralectura = false;

            if (!$process->isRunning())
                break;

            $context = array(
                'msgtext' => __("Conexión exitosa con :COMMAND_SHORT, PID :PID",['COMMAND_SHORT'=>$command_short ,"PID" => $process->getPid()])
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
            $this->printDebugInfo($context['msgtext']);
            echo "notificado \n";



            $stream = $process->getStdout();
            $streamin = $process->getStdin();

            // Fuerzo el envio
            // $sendtxt=sprintf("%s %s W%05d\n",41,91,1);
            // $streamin->write($sendtxt);
            // sleep(1);
            // $sendtxt=sprintf("%s %s W%05d\n",41,92,1);
            // $streamin->write($sendtxt);

            // $this->printDebugInfo($sendtxt);


            while ($process->isRunning() && null !== $chunk = $stream->read()) {
                $linecache .= $chunk;
                $len = strpos($linecache, "\n");


                while ($len !== false) {
                    $line = substr($linecache, 0, $len);

                    $msg = parseMsg($line, Log::channel(self::logFileName));

                    $origin = $msg['origin'];
                    $gpio = $msg['gpio'];
                    $valor = $msg['valor'];

                    /*                    
                    $now   = Carbon::now('UTC');
                    $start = Carbon::createFromTimeString('10:00');
                    $end   = Carbon::createFromTimeString('22:00');
                    // dayOfWeek returns a number between 0 (sunday) and 6 (saturday)
                    if ($now->between($start, $end) && $now->dayOfWeek != 0 && $now->dayOfWeek != 6) {
                        $ignoralectura=true;
                        $sendtxt=sprintf("%s %s W%05d\n",41,91,288);
                        $streamin->write($sendtxt);
                        sleep(1);
                        $sendtxt=sprintf("%s %s W%05d\n",41,92,288);
                        $streamin->write($sendtxt);

//                        $this->printDebugInfo($sendtxt);
                    } else if ($ignoralectura==true){
                        $ignoralectura=false;
                        $sendtxt=sprintf("%s %s W%05d\n",41,91,1);
                        $streamin->write($sendtxt);
                        sleep(1);
                        $sendtxt=sprintf("%s %s W%05d\n",41,92,1);
                        $streamin->write($sendtxt);
                        $this->printDebugInfo($sendtxt);
                    }
*/

                    switch ($gpio) {
                        case "":
                            break;
                        case "101":
                        case "102":
                            $post = $gpio;
                            $cod_tema = $tema_base . "/" . $origin . "/" . $post;
                            $datacred = array("cod_tema" => $cod_tema, "valor" => hexdec($valor));
                            $ret = $this->comdisp->leecredencial($datacred);
                            if ($ret->status() == 200 && $ignoralectura == false) {
                                $respuesta = $ret->original['rs485'];
                                $sendtxt = sprintf("%s %s W%02d%02d%02d%02d%02d\n", $origin, $gpio, $respuesta['rele1'], $respuesta['rele2'], $respuesta['rele3'], $respuesta['buzzer'], $respuesta['led']);
                                $streamin->write($sendtxt);
                                if (isset($ret->original['channel']))
                                    Broadcast::driver('fast-web-socket')->broadcast([$ret->original['channel']], $ret->original['event'],  $ret->original['context']);
                                // $this->printDebugInfo($sendtxt);
                            }

                            break;
                        default:
                            $this->poolEvents->submit(new EventAsync485Task($tema_base, $line));
                            break;
                    }

                    $linecache = substr($linecache, $len + 1);
                    $len = strpos($linecache, "\n");
                    // new Delayed(50);
                }
            }

            $code = $process->join();
            $context = array(
                'msgtext' => __("Se cerró el proceso :COMMAND_SHORT con código :CODE",['COMMAND_SHORT'=>$command_short,'CODE'=>$code])
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);
            $this->printDebugInfo($context['msgtext']);
            \Amp\delay(0.5);
        }
        //        EventLoop::stop();
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
                        //EventLoop::stop();
                        exit();
                        break;
                    
                    default:
                        # code...
                        break;
                }
            }
        }
    }


    protected function loadConfigData()
    {
        $vars485conf = array();
        $this->config = array();
        $this->tema_local =      strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $rs485conf = ConfigParametro::get('RS485_CONF', false);
        if (trim($rs485conf)!="") 
            $vars485conf = explode(",", $rs485conf);

        $licence =  ConfigParametro::get('LICENCIA', false);
        $licence =  ($licence) ? $licence : "UNLICENCED";
        $enable_gpio =  ConfigParametro::get('RS485_ENABLE_GPIO', false);
        $enable_gpio = ($enable_gpio) ? $enable_gpio : 11;
        $bus_id = 120;
        $this->daemon_conf_ver = Cache::get(self::confVersion);

        $this->comdisp->loadConfigData();
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);

        if ($this->option('device') && $this->option('baud') && $this->option('subtema')) {
            $vars485conf = array($this->option('device') . ":" . $this->option('baud') . ":" . $this->option('subtema'));
        }

        $tmp_confighash = hash("sha256", $this->tema_local . $enable_gpio . $rs485conf . $bus_id);
        if ($this->confighash != $tmp_confighash) {
            $this->confighash = $tmp_confighash;

            foreach ($vars485conf as $index => $config) {
                $vaconfig = explode(":", $config);
                $ipport = $vaconfig[0];
                $baudrateport = $vaconfig[1];
                $subtema = $vaconfig[2];
                $procesonom = isset($vaconfig[3])?$vaconfig[3]:"";
                $this->config[$index]['command'] = dirname(__FILE__) . "/../../../bin/$procesonom $ipport $baudrateport $bus_id $enable_gpio $licence";
                $this->config[$index]['tema']    = $this->tema_local . (($subtema!="") ? "/" . $subtema:'');
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
        $this->comdisp = new ComunicacionDispositivos;



        $this->loadConfigData();
        
//        $factory = new BootstrapWorkerFactory(__DIR__ . '/daemon.php');
//                    WorkerFactory(__DIR__ . '/daemon.php')
//        $contextFactory = new ProcessContextFactory();
//        $context = $contextFactory->start(__DIR__ . '/daemon.php');
        $this->poolEvents =  \Amp\Parallel\Worker\createWorker();
//        $this->poolEvents =  \Amp\Parallel\Worker\createWorker();
        if (!defined('SIGINT'))
            define('SIGINT', 0);
        if (!defined('SIGTERM'))
            define('SIGTERM', 0);
        if (!defined('SIGHUP'))
            define('SIGHUP', 0);
//        EventLoop::setDriver(new TracingDriver());

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
//                        $this->poolEvents->shutdown();
                        //EventLoop::stop();
                        exit();
                        return;
                    }
                )
            );
        }

        EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );
        EventLoop::delay(2, function(){ $this->busmsg();});
        EventLoop::delay(2, function(){$this->socklisten();});
        foreach ($this->config as $config) {
            EventLoop::delay(1, function() use ($config):void {$this->rs485proc(0,$config);});
        }
        
        EventLoop::run();
    }
}
