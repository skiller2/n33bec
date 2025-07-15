<?php

namespace App\Console\Commands;

use App\Events\TemaEvent;
use App\Helpers\ConfigParametro;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use PiPHP\GPIO\GPIO;
use PiPHP\GPIO\Pin\InputPinInterface;
use App\Traits\LibGeneral;
use Illuminate\Support\Facades\Log;
use Revolt\EventLoop;
use Illuminate\Support\Facades\Broadcast;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;

class IODaemon extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:IODaemon
                            {--debug : Print debug information to console}
                            {--io=} {--value=}
                            ej: php artisan command:IODaemon --io=27 --value=0
                            ej: curl  -X POST -d "cod_tema=IO05&nom_tema=Bateria&value=1&des_valor=Bat_baja&stm_event=201801011645&id_disp_origen=3&id_disp_reporte=1111&valor_analogico=1756&des_unidad_medida=mV" http://192.168.5.127/angulara/pepaapi/public/api/v1/movieventos/evento
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'IO Processing daemon';

    /**
     * The console command description.
     *
     * @var json
     */
    protected $ios_conf = array();

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $temp_dir = "";
    protected $timezone = "";
    protected $tema_local = "";
    protected $daemon_conf_ver = "";

    const logFileName = "io";
    const config_tag = "iolast_";
    const confVersion = "daemon_conf_ver";

    protected function printDebugInfo($text, $status = "info") {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    public function procesopines(InputPinInterface $pin, $value) {
        
        $io_nro = $pin->getNumber();
        $value = $pin->getValue();
        $cod_tema = $this->ios_conf[$io_nro]['cod_tema'];        
        $des_valor = $this->ios_conf[$io_nro]['val_' . $value];
        
        if ($value == Cache::get(self::config_tag . $cod_tema))
            return;

        $this->printDebugInfo("$cod_tema $io_nro: $des_valor ($io_nro: $value)");
        
        $stm_actual = Carbon::now();
        $valor_analogico = ""; //TODO
        $event_data = array("valor" => $value, "valor_analogico" => $valor_analogico, );
        event(new TemaEvent($cod_tema, $stm_actual, $event_data));

        return true;
    }




    protected function loadConfigData() {
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));;
        $this->ios_conf = ConfigParametro::getLocalIOs();
        $this->timezone = ConfigParametro::get('TIMEZONE_INFORME', false);

        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    public function checkConfigData()
    {
        if (Cache::get(self::confVersion) != $this->daemon_conf_ver) {
            if ($this->loadConfigData()) {
                $context = array(
                    'msgtext' => "Proceso GPIOs actualizando configuración"
                );

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                $this->printDebugInfo($context['msgtext']);
                EventLoop::stop();
            }
        }
    }

    public function busmsg()
    {
        $cod_daemon = basename(__FILE__, ".php");

        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  array("msgtext" => "Inicio proceso " . $cod_daemon));

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

        }
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $this->loadConfigData();
        if ($this->option('io')) {
            if ($this->option('valor') == "0" || $this->option('valor') == "1") {
                $io_nro = $this->option('io');
                $value = $this->option('valor');
                if (!isset($this->ios_conf[$io_nro]['cod_tema'])){
                    $errmsg="GPIO: $io_nro, valor: $value, no localizado en la configuración";
                    $this->printDebugInfo($errmsg);
                    echo $errmsg."\n";
                    return;
                }
                $cod_tema = $this->ios_conf[$io_nro]['cod_tema'];        
                $des_valor = $this->ios_conf[$io_nro]['val_' . $value];
                $stm_actual = Carbon::now();
                $valor_analogico = ""; //TODO
                $des_unidad_medida = ""; //TODO
                $event_data = array("valor" => $value, "des_valor" => $des_valor, "valor_analogico" => $valor_analogico, 
                    "des_unidad_medida" => $des_unidad_medida);
                event(new TemaEvent($cod_tema, $stm_actual, $event_data));
            } else {
                echo 'Debe ingresar un valor';
            }
        } else {


            $this->printDebugInfo("Inicio configuración IOS");
            $io = new GPIO();
            $pinX = "";
            // Create an interrupt watcher
            $interruptWatcher = $io->createWatcher();
            $general_error = false;
            // Configure interrupts for both rising and falling edges

        
            foreach ($this->ios_conf as $io_nro => $value) {
                if (!is_numeric($io_nro))
                    continue;

                $cod_tema_origen= $this->ios_conf[$io_nro]['cod_tema'];
                $this->printDebugInfo("Inicializo gpio: $io_nro ");
                $pin_counter = 100;
                $pin_initialized = false;
                $io_value = 0;
                $skip_pin = false;
                do {
                    try {
                        switch ($value["cod_tipo_uso"]) {
                            case "DINEXT":
                            case "DIN":
                                $pinX = $io->getInputPin($io_nro);
                                $pinX->setEdge(InputPinInterface::EDGE_BOTH);
                                $interruptWatcher->register($pinX, array($this, 'procesopines'));
                                $io_value = $pinX->getValue();
                                break;
                            case "DOUT":
                                $pinX = $io->getOutputPin($io_nro);
                                $io_value = $pinX->getValue();
                                break;
                            case "AIN":
                                $io_value = 0;
                                break;
                            case "AOUT":
                                $io_value = 0;
                                break;
                            default:
                                $skip_pin = true;
                                break;
                        }
                        $pin_initialized = true;
                    } catch (\Exception $e) {
                        //sleep(2);
                        $this->printDebugInfo("Error $pin_counter: " . $e->getMessage() . ", reintentando $io_nro nro $pin_counter");
                    }
                    $pin_counter--;
                    
                } while (!$pin_initialized && $pin_counter > 0);
                if ($skip_pin)
                    continue;
                if ($pin_initialized) {
                    Cache::forever(self::config_tag . $cod_tema_origen, $io_value);
                    Cache::forever("COUNT_" . self::config_tag . $cod_tema_origen, 0);
                } else {
                    $general_error = true;
                    break;
                }
            }

        
            if ($general_error) {
                $this->printDebugInfo("Error inicializando IOS");
                return;
            }

            // Watch for interrupts, timeout after 5000ms (5 seconds)
            $this->printDebugInfo("Iniciando proceso de escucha");

//            while ($interruptWatcher->watch(10000)) {};
 
            EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );
            EventLoop::delay(2, function(){ $this->busmsg();});


            EventLoop::run();
			
			
        }
    }


//End Handle
}