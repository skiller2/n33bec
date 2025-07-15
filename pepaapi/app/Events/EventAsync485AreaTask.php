<?php

namespace App\Events;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Amp\Cancellation;
use Carbon\Carbon;


class EventAsync485AreaTask implements Task
{
    /**
     * @inheritDoc
     */

    private $base_topic;
    private $buffline;
    private $temas;
    const config_tag = "iolast_";
    const logFileName = "area54";

    public function __construct(string $base_topic, string $buffline)
    {
        $this->base_topic = $base_topic;
        $this->buffline = $buffline;
        $this->temas = ConfigParametro::getTemas('');
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        require_once __DIR__ . '/../../bootstrap/app.php';
        $kernel = app()->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->output();
        $status = 0;

        $evento = json_decode($this->buffline, true);
        $post = "";
        if ($evento != null) {
            //            $origin = (isset($evento['origin'])) ? $evento['origin'] : "";
            //            $origin = "area";
            $gpio = (isset($evento['gpio'])) ? $evento['gpio'] : "";
            $valor = (isset($evento['value'])) ? $evento['value'] : "";
            $display_status = (isset($evento['display_status'])) ? $evento['display_status'] : "";
            $command_enabled = (isset($evento['command_enabled'])) ? $evento['command_enabled'] : "";
            $desc = (isset($evento['desc'])) ? $evento['desc'] : "";

            switch ($gpio) {
                case 'display':
                    $post = $gpio;
                    $cod_tema = $this->base_topic . "/" .  $post;
                    $context = array(
                        'cod_tema' => $this->base_topic,
                        'msgtext' => "AREA54",
                        'display' => $valor,
                        'display_status' => $display_status,
                        'command_enabled' => $command_enabled
                    );

                    if (Cache::get(self::config_tag . $this->base_topic . "display_area54", array()) != $context) {
                        Cache::forever(self::config_tag . $this->base_topic . "display_area54", $context);
                        Broadcast::driver('fast-web-socket')->broadcast(["display_area54"], 'info',  $context);
                    }
                    break;
                case 'alive':
                    $post = $gpio;
                    $cod_tema = $this->base_topic . "/" .  $post;

                    if (isset($this->temas[$cod_tema])) {
                        $intervalo_seg = $this->temas[$cod_tema]['intervalo_seg'] + 1;
                        Cache::put(self::config_tag . $cod_tema . "_comm", $valor, $intervalo_seg);
                        Log::channel(self::logFileName)->info($this->buffline, array());
                    } else {
                        $event_data = array("valor" => $valor);
                        $cod_tema = $this->base_topic . "/" .  $post;
                        event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                    }

                    break;
                default:
                    $post = $gpio;
                    $cod_tema = $this->base_topic . "/" .  $post;

                    $evts = Cache::get(self::config_tag . $cod_tema . "ext");
                    $evts = is_array($evts) ? $evts : array();
                    $key = hash('ripemd160', $desc);
                    if ($valor == "INFO") {
                        $evts = array();
                        $evts[$key] = $desc;
                        Cache::forever(self::config_tag . $cod_tema . "ext", $evts);

                        $des_observaciones = implode("; ", $evts);
                        $event_data = array("valor" => $valor, "des_valor" => $valor, "des_observaciones" => $des_observaciones);
                        event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                    } else if ($valor != Cache::get(self::config_tag . $cod_tema)) {
                        $evts = array();
                        $evts[$key] = $desc;
                        Cache::forever(self::config_tag . $cod_tema . "ext", $evts);

                        $des_observaciones = implode("; ", $evts);
                        $event_data = array("valor" => $valor, "des_valor" => $valor, "des_observaciones" => $des_observaciones);
                        event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                    } else if (!isset($evts[$key])) {
                        $evts[$key] = $desc;
                        Cache::forever(self::config_tag . $cod_tema . "ext", $evts);

                        $des_observaciones = implode("; ", $evts);
                        $event_data = array("valor" => $valor, "des_valor" => $valor, "des_observaciones" => $des_observaciones);
                        event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                    }

                    break;
            }
        } else {
            $context = array(
                'msgtext' => "Error decodificando tam: " . strlen($this->buffline) . ", data: " . $this->buffline
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);
            Log::channel(self::logFileName)->info($text, array());
        }
        $kernel->terminate(null, $status);
        return $status;
    }
}

