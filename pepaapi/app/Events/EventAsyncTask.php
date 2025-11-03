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


class EventAsyncTask implements Task
{
    /**
     * @inheritDoc
     */

    private $cod_tema;
    private $event_data;

    public function __construct(string $cod_tema, array $event_data)
    {
        $this->cod_tema = $cod_tema;
        $this->event_data = $event_data;
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        require_once __DIR__ . '/../../bootstrap/app.php';
        $kernel = app()->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        Event::dispatch(new TemaEvent($this->cod_tema, Carbon::now(), $this->event_data));
        return true;
    }
}
