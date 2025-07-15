<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

class TemaEvent
{
    use SerializesModels;

    public $cod_tema;
    public $stm_evento;
    public $event_data;

    /**
     * Create a new event instance.
     *
     * @param  \App\Order  $order
     * @return void
     */
    public function __construct($cod_tema, $stm_evento, $event_data)
    {
        $this->cod_tema = strtolower($cod_tema);
        $this->stm_evento = $stm_evento;
        if (!isset($event_data['des_observaciones'])) 
            $event_data['des_observaciones'] = "";
        $this->event_data = $event_data;

//        Log::channel("eventos")->info("Evento Tema $cod_tema", array($event_data));

    }
}