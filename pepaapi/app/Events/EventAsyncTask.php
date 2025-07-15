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
        event(new TemaEvent($this->cod_tema, Carbon::now(), $this->event_data));
    }
}
