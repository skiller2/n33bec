<?php

namespace App\Console\Commands;

use App\Events\TemaEvent;
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
use Amp\Delayed;
use Illuminate\Support\Env;
use Illuminate\Support\Facades;
use function Amp\delay;
use Illuminate\Support\Facades\Broadcast;
use App\Helpers\RemoteN33;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Amp\Websocket\Options;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketHandshake;
use Amp\Websocket\PeriodicHeartbeatQueue;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use Amp\Parallel\Worker\createWorker;


//use Symfony\Component\Console\Application;
//use Illuminate\Contracts\Foundation\Application;




class MoviDisplayTemasRemoteDaemon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:MoviDisplayTemasRemoteDaemon
                            {--debug : Print debug information to console}
                            ej: php artisan command:MoviDisplayTemasRemoteDaemon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza Display Remoto daemon';

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
    protected $remotos = array();
    protected $tema_local;
    protected $timezone = "";
    protected $remotoshash = "";
    protected $daemon_conf_ver = "";
    protected $connected = false;
    const confVersion = "daemon_conf_ver";
    const logFileName = "delayedio";
    const config_tag = "iolast_";
    private $poolDelay;
    private $poolComunic;

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
        $this->timezone      = ConfigParametro::get('TIMEZONE_INFORME', false);
        $this->remotos       = ConfigParametro::get("N33BEC_REMOTO", true);
        $this->remotoshash   = hash("sha256", json_encode($this->remotos));
        $this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a " . $this->daemon_conf_ver);
    }

    public function checkConfigData()
    {
        $remotoshash = $this->remotoshash;
        if (Cache::get(self::confVersion) != $this->daemon_conf_ver) {
            $this->loadConfigData();
            if ($this->remotoshash != $remotoshash)
            EventLoop::stop();
        }
    }

    public function getSyncMoviDisplayTemasRemote($handle, $central_remota)
    {

        while (true) {
            $tmp = RemoteN33::getRemoteData($central_remota['url'] . "/api/v1/parametros/getParametro/TEMA_LOCAL", 5);
            if ($tmp) {
                $cod_tema_remoto = $tmp['val_parametro'];

                $tmpRemotos = Cache::get("N33BEC_REMOTO", array());
                $tmpRemotos[$cod_tema_remoto]=array('url'=>$central_remota['url']);
                Cache::forever("N33BEC_REMOTO", $tmpRemotos);

                $movidisptemadata = RemoteN33::getRemoteData($central_remota['url'] . "/api/v1/displaysucesos/lista/false", 5);
                if (is_array($movidisptemadata)) {
                    foreach ($movidisptemadata as $row) {
                        $cod_tema_remoto;
                        $row['cod_tema'];
                        $row['tipo_evento'];
                        $row['stm_evento'];
                        $row['nom_tema'];
                        $row['cod_sector'];
                        $row['cant_activacion'];
                        $row['des_observaciones'];
                        $row['sectores'];
                    }

                    $remotos = Cache::get("remoto_display", array());
                    $remotos[$cod_tema_remoto] = $movidisptemadata;
                    Cache::forever("remoto_display", $remotos);
                }
            } else {
                $this->printDebugInfo("Error " . $central_remota['url'] . "/api/v1/parametros/getParametro/TEMA_LOCAL");
//                $remotos = Cache::get("remoto_display", array());
//                $remotos[$cod_tema_remoto] = array();
//                Cache::forever("remoto_display", $remotos);

            }
             delay(1);
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public function handle()
    {
        $factory = new BootstrapWorkerFactory(__DIR__ . '/daemon.php');

        $this->poolDelay = new DefaultPool(5, $factory);
        $this->poolComunic = new DefaultPool(5, $factory);
        $this->loadConfigData();

        EventLoop::repeat($sInterval = 1, function() { $this->checkConfigData();    }  );
        EventLoop::delay(2, function(){ $this->busmsg();});

        foreach ($this->remotos as $remoto) {
            EventLoop::delay(5, function(){ $this->getSyncMoviDisplayTemasRemote($remoto);} );
        }

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
}
