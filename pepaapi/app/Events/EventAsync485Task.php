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
use Illuminate\Support\Facades\Event;

class EventAsync485Task implements Task
{
    /**
     * @inheritDoc
     */

    private $base_topic;
    private $buffline;
    private $temas;
    private $comdisp;
    const config_tag = "iolast_";
    const logFileName = "serial";

    public function __construct(string $base_topic, string $buffline)
    {
        $this->base_topic = $base_topic;
        $this->buffline = $buffline;
        $this->temas = ConfigParametro::getTemas('');
        $this->comdisp = new ComunicacionDispositivos;
    }

    public function run(Channel $channel, Cancellation $cancellation):mixed
    {
        require_once __DIR__ . '/../../bootstrap/app.php';
        $kernel = app()->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        $evento = json_decode($this->buffline, true);
        $post = "";
        $msg = parseMsg($this->buffline, Log::channel(self::logFileName));

        $origin = $msg['origin'];
        $gpio = $msg['gpio'];
        $valor = $msg['valor'];

        switch ($gpio) {
            case "":
                break;
            case '00H':
            case '100':
                $post = "alive";
                $valor = 1;
                $cod_tema = strtolower($this->base_topic . "/" . $origin . "/" . $post);

                if (isset($this->temas[$cod_tema])) {
                    $intervalo_seg = $this->temas[$cod_tema]['intervalo_seg'] + 1;
                    Cache::put(self::config_tag . $cod_tema . "_comm", $valor, $intervalo_seg);

                    //                    Log::channel(self::logFileName)->info($this->buffline, array());
                } else {
                    $event_data = array("valor" => $valor);
                    $cod_tema = $this->base_topic . "/" . $origin . "/" . $post;
                    Event::dispatch(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                }
                break;
            default:
                $post = $gpio;
                $event_data = array("valor" => $valor);
                $cod_tema = strtolower($this->base_topic . "/" . $origin . "/" . $post);
                if (isset($this->temas[$cod_tema])) {
                    $res = TemaValue::get($this->temas[$cod_tema], $valor);
                    $des_valor = $res['des_valor'];
                    $tipo_evento = $res['tipo_evento'];
                    $ret_delay_sec = (isset($this->temas[$cod_tema]['val_delay_sec_' . $tipo_evento])) ? $this->temas[$cod_tema]['val_delay_sec_' . $tipo_evento] : 0;
                    if ($ret_delay_sec > 0) {
                        $vaPendDelaytmp = Cache::get('retencion', array());
                        $vaPendDelaytmp[$cod_tema] = array(Carbon::now()->addSeconds($ret_delay_sec), $valor);
                        Cache::forever("retencion", $vaPendDelaytmp);
                        Cache::forever(self::config_tag . $cod_tema, $valor);

                        break;
                    }
                }
                Event::dispatch(new TemaEvent($cod_tema, Carbon::now(), $event_data));
                break;
        }
        return 0;
    }
}

