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
use function Amp\delay;


class CheckComAsyncTask implements Task
{
    /**
     * @inheritDoc
     */

    private $cod_tema;
    private $url;
    private $config_tag = "iolast_";
    public function __construct(string $cod_tema,  string $url)
    {
        $this->cod_tema = $cod_tema;
        $this->url = $url;
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        require_once __DIR__ . '/../../bootstrap/app.php';
        $kernel = app()->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->output();
 
        $valor = 1;
        $des_observaciones = "";
        $failretry = 3;
        //        echo "check $this->cod_tema,  url:".$this->url." \n\n";
        while ($failretry > 0) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $valor = ($http_code == 200 || $http_code == 301) ? 1 : 0;
            $des_observaciones  = "HTTPCODE " . $http_code;
            $ret = curl_close($ch);
            //echo $des_observaciones."\n";
            if ($valor == 1)
                break;
            else
                $failretry--;
             delay(2); //secs
        }
        $event_data = array("valor" => $valor, "des_observaciones" => $des_observaciones);

        if ($valor != Cache::get($this->config_tag . $this->cod_tema))
            event(new TemaEvent($this->cod_tema, Carbon::now(), $event_data));

        return 0;
    }
}
