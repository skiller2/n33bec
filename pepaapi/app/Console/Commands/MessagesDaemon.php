<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
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
use function Amp\delay;
use Amp\Parallel\Worker\createWorker;

class MessagesDaemon extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:MessagesDaemon
                            {--debug : Print debug information to console}
                            {--linea= : Línea de captura}
                            {--tema= : Componente}
                            Ej: php artisan command:Area54Daemon --linea=BLA BLA SENSOR1 XXXX';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Messages Processing daemon';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temp_dir = "";
    protected $base_topic;
    private $poolEvents;
    private $telegram_bot_token;
    private $chat_id;
    private $tema_local;
    private $temas;
    private $confighash;
    const logFileName = "messages";
    const channel = "io";
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
                    'msgtext' => __("Proceso Mensajería instantánea actualizando configuración")
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
        $this->telegram_bot_token = ConfigParametro::get("TELEGRAM_TOKEN", false);
        $this->chat_id = ConfigParametro::get("TELEGRAM_CHAT_ID", false);
        $this->temas = ConfigParametro::getTemas();
        $this->daemon_conf_ver = Cache::get(self::confVersion);

        $tmp_confighash = hash("sha256", $this->tema_local . $this->telegram_bot_token . $this->chat_id);
        if ($this->confighash != $tmp_confighash) {
            $this->confighash = $tmp_confighash;

            return true;
        } else
            return false;
    }

    protected function sendMsg($tmpmsg)
    {
        //$url = "https://api.telegram.org/bot" . $this->telegram_bot_token . "/sendMessage?chat_id=" . $this->chat_id . "&text=";
        $url = "https://api.telegram.org/bot" . $this->telegram_bot_token . "/sendMessage";

        try {
            $ch = curl_init();
            //$msgok = curl_escape($ch, $tmpmsg);
            //$data = ['chat_id'=>$this->chat_id,'text'=>$msgok];
            $data = ['chat_id'=>$this->chat_id,'text'=>'<b>'.$tmpmsg.'</b>', 'parse_mode'=>'HTML'];
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $ret = curl_exec($ch);
            curl_close($ch);
            if (strpos($ret,"\"ok\":false") !== false)
               $this->printDebugInfo('Error telegram: ' . $ret. ' command: '.$url);
        } catch (\Exception $e) {
            $this->printDebugInfo('Error enviando a telegram: ' . $e->getMessage());
        }
        return true;
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

            if (isset($payloadDecoded['context']["msgtext"]) && $this->telegram_bot_token && $this->chat_id) {

                $cod_tema = $payloadDecoded['context']['cod_tema'];
                $ind_modo_prueba = (isset($payloadDecoded['context']['ind_modo_prueba']) && $payloadDecoded['context']['ind_modo_prueba'] == "1") ? 1 : 0;
                if ($cod_tema && $this->temas[$cod_tema]['ind_notifica_evento'] == "1") {
                    $this->printDebugInfo('envía ' . $payloadDecoded['timeStamp'] . ' : ' . $tmpmsg);
                    if ($ind_modo_prueba == 1)
                        $tmpmsg = "Prueba " . $tmpmsg;
                    $this->sendMsg($tmpmsg);
                } else if (isset($payloadDecoded['context']['ind_notifica_evento']) &&  $payloadDecoded['context']['ind_notifica_evento']== "1") {
                    $this->sendMsg($tmpmsg);
                }


                $lastTimeStamp = $payloadDecoded['timeStamp'] . "-" . hash('sha256', $tmpmsg);
                Cache::forever(self::channel . "timestamp", $lastTimeStamp);
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


        EventLoop::run();
    }
    //End Handle
}
