<?php

namespace App\Listeners;

use App\Events\TemaEvent;
use Illuminate\Support\Facades\Log;
use App\Helpers\ConfigParametro;
use App\Helpers\TemaValue;
use App\Http\Controllers\MoviEventos;
use App\Http\Controllers\MoviDisplayTemas;
use App\MoviDisplayTema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Feriados;
use App\Http\Controllers\Temas;
use Carbon\Carbon;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use App\Jobs\SendMail;
use App\Http\Controllers\MoviUltSucesos;
use PiPHP\GPIO\GPIO;
use PiPHP\GPIO\Pin\PinInterface;
use Illuminate\Http\Request;


class TemaListener
{
    protected $confSucesos;
    protected $timezone;
    protected $tipoDias;
    protected $temas;
    const logFileName = "sucesos";
    const config_tag = "iolast_";
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        $this->timezone = ConfigParametro::get('TIMEZONE_INFORME', false);
        $this->confSucesos = ConfigParametro::getTemas("SUCESO");
        $this->temas = ConfigParametro::getTemas();
        $this->tipoDias = ConfigParametro::get('TIPO_DIAS', true);
        $this->expressionLanguage = new ExpressionLanguage();
        $this->expressionLanguage->registerProvider(new StringExpressionLanguageProvider());
    }

    public function saveEstadosTemas($cod_tema, $value, $stm_event)
    {
        $vadetalle = [];

        foreach ($this->temas[$cod_tema]['subtemas'] as $cod_subtema) {
            $vadetalle[$cod_subtema] = array('valor' => Cache::get("iolast_" . $cod_subtema));
        }
        Cache::forever(self::config_tag . $cod_tema . "/$value", array('stm_event' => $stm_event, 'estados_temas' => $vadetalle));
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\TemaEvent  $event
     * @return void
     */
    public function handle(TemaEvent $event)
    {
        $event->event_data["ind_modo_prueba"] = Cache::get("ind_modo_prueba", 0);
        $cod_tema = $event->cod_tema;
        $valor = (isset($event->event_data["valor"])) ? $event->event_data["valor"] : "NONE";
        $des_valor = (isset($event->event_data["des_valor"])) ? $event->event_data["des_valor"] : "Sin descripción";
        $des_observaciones = (isset($event->event_data["des_observaciones"])) ? $event->event_data["des_observaciones"] : "";
        $ind_modo_prueba = $event->event_data["ind_modo_prueba"];
        $json_detalle = (isset($event->event_data["json_detalle"])) ? $event->event_data["json_detalle"] : array();
        Cache::forever(self::config_tag . $cod_tema, $valor);

        $contador_msg = "";
        if (!isset($this->temas[$cod_tema])) {
            $obs = ($des_observaciones != "") ? ", obs: " . $des_observaciones : "";
            $valor_str = (is_array($valor)) ? json_encode($valor) : $valor;
            $context = array(

                'msgtext' => "Tema $cod_tema no registrado valor devuelto $des_valor($valor_str)$obs",
                'cod_tema' => $cod_tema
            );
            $request = new Request(array("cod_tema" => $cod_tema, "valor" => $valor_str, "des_observaciones" => $des_observaciones, "stm_ultimo_reporte" => $event->stm_evento));
            Temas::storeNoRegis($request);
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], "info",  $context);
            return;
        }

        $nom_tema = $this->temas[$cod_tema]['nom_tema'];
        $url_envio = $this->temas[$cod_tema]['url_envio'];

        if (isset($event->event_data['origen']) && $event->event_data['origen'] != "" && $url_envio != "") {
            $context = array(
                'msgtext' => "$nom_tema en loop, ingreso " . $event->event_data['origen'] . " y tiene configurada url de envío ",
                'cod_tema' => $cod_tema
            );
            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], "warning",  $context);

            return;
        }

        $delay_seg = (isset($event->event_data["delay"])) ? $event->event_data["delay"] : "0";
        $valor_fin = (isset($event->event_data["valor_fin"])) ? $event->event_data["valor_fin"] : "";
        $event->event_data["nom_tema"] = $nom_tema;
        $event->event_data['tipo_evento'] = "";

        //        $msgenvio = ($url_envio) ? ", envío a $url_envio" : "";
        $msgenvio = ($url_envio) ? ", envío remoto" : "";

        switch ($this->temas[$cod_tema]['cod_tipo_uso']) {
            case "LECTOR":
                //$this->fanalizaSuceso($ind_rechazo, $stm_evento, $cod_credencial, $nom_ape_persona, $cod_tema);
                break;
            case "SUCESO":
                $ind_activa_audio = 0;

                switch ($valor) {
                    case 'A':
                        $level = "alarma";
                        $des_valor = "Alarma";
                        $ind_activa_audio = ($this->temas[$cod_tema]['ind_activa_audio_alarma']) ? 1 : 0;
                        //                        $this->saveEstadosTemas($cod_tema, $valor, Carbon::NOW());
                        break;
                    case 'P':
                        $level = "prealarma";
                        $des_valor = "Pre Alarma";
                        $ind_activa_audio = ($this->temas[$cod_tema]['ind_activa_audio_prealarma']) ? 1 : 0;
                        //                        $this->saveEstadosTemas($cod_tema, $valor, Carbon::NOW());
                        break;
                    case 'T':
                        $level = "alarmatec";
                        $des_valor = "Alarma Técnica";
                        $ind_activa_audio = ($this->temas[$cod_tema]['ind_activa_audio_alarmatec']) ? 1 : 0;
                        //                        $this->saveEstadosTemas($cod_tema, $valor, Carbon::NOW());
                        break;
                    case 'F':
                        $level = "falla";
                        $des_valor = "Falla";
                        $ind_activa_audio = ($this->temas[$cod_tema]['ind_activa_audio_falla']) ? 1 : 0;
                        //                        $this->saveEstadosTemas($cod_tema, $valor, Carbon::NOW());
                        break;
                    case 'R':
                        $level = "success";
                        $des_valor = "Resetea contador";
                        $ind_activa_audio = 0;
                        break;
                    case 'N':
                        $level = "success";
                        $des_valor = "Normalizado";
                        $ind_activa_audio = 0;
                        break;
                    default:
                        $level = "success";
                        $des_valor = "Desconocido";
                        $ind_activa_audio = 0;
                        break;
                }

                $ind_alarma = ($valor == "A");
                $ind_prealarma = ($valor == "P");
                $ind_falla = ($valor == "F");
                $ind_alarmatec = ($valor == "T");

                $ultSuceso = array(
                    'cod_tema' => $cod_tema,
                    'stm_ult_suceso' => $event->stm_evento,
                    'ind_alarma' => $ind_alarma,
                    'ind_alarmatec' => $ind_alarmatec,
                    'ind_prealarma' => $ind_prealarma,
                    'ind_falla' => $ind_falla,
                    'json_detalle' => $json_detalle
                );
                try {
                    switch ($valor) {
                        case 'R':
                            MoviUltSucesos::resetCounter($ultSuceso);
                            break;
                        case 'N':
                            MoviUltSucesos::resetStatus($ultSuceso);
                            break;
                        default:
                            MoviUltSucesos::store($ultSuceso);
                            break;
                    }
                } catch (\Exception $e) {
                    Log::channel("eventos")->info("Error grabando ult suceso " . $event->cod_tema . " " . $e->getMessage(), array($event->event_data));
                }
                $obs = ($des_observaciones != "") ? ", obs: " . $des_observaciones : "";
                $valor_str = (is_array($valor)) ? json_encode($valor) : $valor;
                $context = array(
                    'cod_tema' => $cod_tema,
                    'valor' => $valor,
                    'des_valor' => $des_valor,
                    'ind_activa_audio' => $ind_activa_audio,
                    'msgtext' => "$nom_tema $des_valor($valor_str)$obs",
                    'ind_modo_prueba' => $ind_modo_prueba
                );
                Broadcast::driver('fast-web-socket')->broadcast(["sucesos"], $level,  $context);

                if ($valor == "A") {
                    try {
                        if ($this->temas[$cod_tema]['acciones_alarma']) {
                            $this->expressionLanguage->evaluate($this->temas[$cod_tema]['acciones_alarma']);
                        }
                    } catch (\Throwable $th) {
                        throw $th;
                    }
                }


                if ($valor == "P") {
                    try {
                        if ($this->temas[$cod_tema]['acciones_prealarma']) {
                            $this->expressionLanguage->evaluate($this->temas[$cod_tema]['acciones_prealarma']);
                        }
                    } catch (\Throwable $th) {
                        throw $th;
                    }
                }

                if ($valor == "F") {
                    try {
                        if ($this->temas[$cod_tema]['acciones_falla']) {
                            $this->expressionLanguage->evaluate($this->temas[$cod_tema]['acciones_falla']);
                        }
                    } catch (\Throwable $th) {
                        throw $th;
                    }
                }

                if ($valor == "T") {
                    try {
                        if ($this->temas[$cod_tema]['acciones_alarmatec']) {
                            $this->expressionLanguage->evaluate($this->temas[$cod_tema]['acciones_alarmatec']);
                        }
                    } catch (\Throwable $th) {
                        throw $th;
                    }
                }

                if ($valor == "N") {
                    try {
                        if ($this->temas[$cod_tema]['acciones_normal']) {
                            $this->expressionLanguage->evaluate($this->temas[$cod_tema]['acciones_normal']);
                        }
                    } catch (\Throwable $th) {
                        throw $th;
                    }
                }

                if ($this->temas[$cod_tema]['des_destinatarios']) {
                    $mailevt = array(
                        'des_template_mail' => $this->temas[$cod_tema]['des_template_mail'],
                        'des_asunto' => "Prueba Evento",
                        'des_destinatarios' => $this->temas[$cod_tema]['des_destinatarios']
                    );
                    $job = (new SendMail($this->temas[$cod_tema]['des_template_mail'], $mailevt));
                    dispatch($job->onQueue("low"));
                }

                break;
            case "COMUNIC":
            case "DIN":
            case "DINEXT":

                $count = 0;
                if ($this->temas[$cod_tema]["count"] == true && $valor > 0) {
                    $incre = Cache::get("COUNT_" . self::config_tag . $cod_tema);
                    $count = $incre + 1;
                    Cache::forever("COUNT_" . self::config_tag . $cod_tema, $count);
                    $contador_msg = ", contador:$count";
                }
                $auto_reset = $this->temas[$cod_tema]["auto_reset"];
                //                $des_valor = ($valor == 1) ? $this->temas[$cod_tema]["val_1"] : $this->temas[$cod_tema]["val_0"];
                //                $level = ($valor == 1) ? $this->temas[$cod_tema]['color_val_1'] : $this->temas[$cod_tema]['color_val_0'];
                $res = TemaValue::get($this->temas[$cod_tema], $valor);
                $des_valor = $res['des_valor'];
                $tipo_evento = $res['tipo_evento'];
                $color = $res['color'];
                $level = $tipo_evento;

                if ($auto_reset == 1 && $tipo_evento == "NO") { //
                    $vaResultado = MoviDisplayTema::where('cod_tema', '=', $cod_tema)->get();
                    if ($vaResultado) {
                        foreach ($vaResultado as $aborrar)
                            $aborrar->delete();
                        $des_observaciones .= " reposición automática";
                    }
                }
                $obs = ($des_observaciones != "") ? ", obs: " . $des_observaciones : "";
                $valor_str = (is_array($valor)) ? json_encode($valor) : $valor;
                if ($tipo_evento != "IG") {
                    $context = array(
                        'msgtext' => "$nom_tema $des_valor($valor_str) $contador_msg $msgenvio $obs",
                        'cod_tema' => $cod_tema,
                        'valor' => $valor,
                        'des_valor' => $des_valor,
                        'count' => $count,
                        'color' => $color,
                        'ind_modo_prueba' => $ind_modo_prueba
                    );

                    $event->event_data['des_valor'] = $des_valor;
                    $event->event_data['tipo_evento'] = $tipo_evento;
                    $event->event_data['color'] = $color;
                    Broadcast::driver('fast-web-socket')->broadcast(["io"], $level,  $context);
                }

                $tipo_intervalo = $this->getTipoIntervalo($event->stm_evento);
                $vaIntervalosSuceso = $this->getIntervaloSucesos($tipo_intervalo);


                $hora = $event->stm_evento->copy()->timezone($this->timezone)->format('H');
                foreach ($vaIntervalosSuceso as $cod_tema_suc => $intervalos) {
                    if (
                        strpos($this->confSucesos[$cod_tema_suc]['cond_alarma'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_prealarma'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_falla'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_alarmatec'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_normal'], $cod_tema) === false
                    ) continue;

                    foreach ($intervalos as $intervalo) {
                        if ((($intervalo['d'] < $intervalo['h']) && ($intervalo['d'] <= $hora && $intervalo['h'] > $hora)) || (($intervalo['d'] > $intervalo['h']) && ($intervalo['d'] <= $hora or $hora < $intervalo['h']))) {
                            $this->procesoSuceso($cod_tema_suc, $event, $cod_tema);
                            break;
                        }
                    }
                }

                break;
            case "AIN":
                $count = 0;
                if ($this->temas[$cod_tema]["count"] == true) {
                    $incre = Cache::get("COUNT_" . self::config_tag . $cod_tema);
                    $count = $incre + 1;
                    Cache::forever("COUNT_" . self::config_tag . $cod_tema, $count);
                    $contador_msg = ", contador:$count";
                }
                $des_valor = $valor . $this->temas[$cod_tema]['measure_unit'];
                $level = $this->temas[$cod_tema]['color_val'];

                $tipo_intervalo = $this->getTipoIntervalo($event->stm_evento);
                $vaIntervalosSuceso = $this->getIntervaloSucesos($tipo_intervalo);

                $context = array(
                    'msgtext' => "$nom_tema $des_valor $contador_msg $msgenvio ",
                    'cod_tema' => $cod_tema,
                );

                $event->event_data['des_valor'] = $des_valor;
                $event->event_data['tipo_evento'] = "";

                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], $level,  $context);

                //                if ($url_envio=="") {
                $hora = $event->stm_evento->copy()->timezone($this->timezone)->format('H');
                foreach ($vaIntervalosSuceso as $cod_tema_suc => $intervalos) {
                    if (
                        strpos($this->confSucesos[$cod_tema_suc]['cond_alarma'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_prealarma'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_falla'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_alarmatec'], $cod_tema) === false &&
                        strpos($this->confSucesos[$cod_tema_suc]['cond_normal'], $cod_tema) === false
                    ) continue;

                    foreach ($intervalos as $intervalo) {
                        if ((($intervalo['d'] < $intervalo['h']) && ($intervalo['d'] <= $hora && $intervalo['h'] > $hora)) || (($intervalo['d'] > $intervalo['h']) && ($intervalo['d'] <= $hora or $hora < $intervalo['h']))) {
                            $this->procesoSuceso($cod_tema_suc, $event, $cod_tema);
                            break;
                        }
                    }
                }
                //                }

                break;
            case 'DOUT':
                $count = 0;
                if ($this->temas[$cod_tema]["count"] == true) {
                    $incre = Cache::get("COUNT_" . self::config_tag . $cod_tema);
                    $count = $incre + 1;
                    Cache::forever("COUNT_" . self::config_tag . $cod_tema, $count);
                    $contador_msg = ", contador:$count";
                }
                $accion_type = (isset($this->temas[$cod_tema]["accion_type"])) ? $this->temas[$cod_tema]["accion_type"] : "local";
                $bus_id = (isset($this->temas[$cod_tema]["bus_id"])) ? $this->temas[$cod_tema]["bus_id"] : "";
                $gpio = (isset($this->temas[$cod_tema]["gpio"])) ? $this->temas[$cod_tema]["gpio"] : "";
                switch ($accion_type) {
                    case "local":
                        $gpio = (isset($this->temas[$cod_tema]["io"])) ? $this->temas[$cod_tema]["io"] : "";
                        $url_envio = "";
                        if ($gpio == "") {
                            Log::channel("eventos")->info("Error gpio no configurado para " . $cod_tema, array($this->temas[$cod_tema]));
                            return;
                        }

                        $rasp_io = new GPIO();

                        try {
                            $pin = $rasp_io->getOutputPin($gpio);
                            if ($valor == 1) {
                                $valor_fin = 0;
                                $pin->setValue(PinInterface::VALUE_HIGH);
                            } else {
                                $valor_fin = 1;
                                $pin->setValue(PinInterface::VALUE_LOW);
                            }
                        } catch (\Exception $e) {
                        }
                        break;
                    case "url":
                        if ($url_envio == "") {
                            $msg = "Campo URL no configurado para " . $this->temas[$cod_tema]['nom_tema'];
                            Log::channel("eventos")->info($msg, array($this->temas[$cod_tema]));
                            $context = array(
                                'msgtext' => $msg,
                                'cod_tema' => $cod_tema
                            );
                            Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], "warning",  $context);


                            return;
                        }
                        break;
                    case "bus":
                        if ($bus_id == "") {
                            Log::channel("eventos")->info("Error Identificador del bus no configurado para " . $cod_tema, array($this->temas[$cod_tema]));
                            return;
                        }

                        $server = '127.0.0.1';
                        $port   = 1337;
                        $input  = "$bus_id $gpio W$valor\n";
                        $sock   = socket_create(AF_INET, SOCK_DGRAM, 0);

                        $json_msg = json_encode(array("cod_tema"=>$cod_tema,"data"=>$input));
                        socket_sendto($sock, $json_msg, strlen($json_msg), 0, $server, $port);
                        socket_close($sock);
                        break;
                    case "cai":
                        if ($bus_id == "") {
                            Log::channel("eventos")->info("Error Identificador del bus no configurado para " . $cod_tema, array($this->temas[$cod_tema]));
                            return;
                        }

                        $context = array(
                            'msgtext' => "$nom_tema $des_valor($valor) $contador_msg $msgenvio",
                            'cod_tema' => $cod_tema,
                            'valor' => $valor,
                            'bus_id' => $bus_id,
                            'des_valor' => $des_valor,
                            'ind_modo_prueba' => $ind_modo_prueba,
                            'cod_daemon' => "Area54Daemon",
                            "command" => "bus"
                        );
   
                        Broadcast::driver('fast-web-socket')->broadcast(["procesos"], "INFO",  $context);
                        break;
                }

                $res = TemaValue::get($this->temas[$cod_tema], $valor);
                $des_valor = $res['des_valor'];
                $tipo_evento = $res['tipo_evento'];
                $color = $res['color'];
                $level = $tipo_evento;

                $obs = ($des_observaciones != "") ? ", obs: " . $des_observaciones : "";
                $valor_str = (is_array($valor)) ? json_encode($valor) : $valor;
                $context = array(
                    'msgtext' => "$nom_tema $des_valor($valor_str) $contador_msg $msgenvio $obs",
                    'cod_tema' => $cod_tema,
                    'valor' => $valor,
                    'des_valor' => $des_valor,
                    'color' => $color,
                    'count' => $count,
                    'ind_modo_prueba' => $ind_modo_prueba
                );

                $event->event_data['des_valor'] = $des_valor;
                $event->event_data['tipo_evento'] = $tipo_evento;

                Broadcast::driver('fast-web-socket')->broadcast(["io"], $level,  $context);

                break;
            default:
                break;
        }

        if ($url_envio)
            $this->envioDispositivo($cod_tema, $url_envio, $event->event_data);
        else {
            if ($delay_seg > 0) {
                $tmp = Cache::get('delayed', array());
                $tmp[$cod_tema] = array(Carbon::now()->addSeconds($delay_seg), $valor_fin, 0);
                Cache::forever("delayed", $tmp);
            } else {
                $vaPendDelaytmp = Cache::get('delayed', array());
                unset($vaPendDelaytmp[$cod_tema]);
                Cache::forever("delayed", $vaPendDelaytmp);
            }
        }

        if ($this->temas[$cod_tema]['ind_registra_evento']) {
            if ($url_envio)
                $event->event_data['des_observaciones'] = $url_envio;
            try {
                MoviEventos::store($event);
            } catch (\Exception $e) {
                Log::channel("eventos")->info("Error grabando evento " . $cod_tema . " " . $e->getMessage(), array($event->event_data));
            }
        }

        if ($this->temas[$cod_tema]['ind_display_evento']) {
            switch ($event->event_data['tipo_evento']) {
                case "EV":
                case "NO":
                case "IG":
                    break;
                default:
                    try {
                        MoviDisplayTemas::store($event);
                    } catch (\Exception $e) {
                        Log::channel("eventos")->info("Error grabando evento " . $cod_tema . " " . $e->getMessage(), array($event->event_data));
                    }
                    break;
            }
        }
    }

    //Debería analizar las reglas del suceso en el caso que este contenga el tema de dispara el evento
    public function procesoSuceso($cod_tema_suc, $event, $cod_tema_disparado)
    {
        $config = $this->confSucesos[$cod_tema_suc];
        $des_valor = (isset($event->event_data['des_valor'])) ? $event->event_data['des_valor'] : $event->event_data['valor'];
        $nom_tema = $config['nom_tema'];
        $subtemas = is_array($config['subtemas']) ? $config['subtemas'] :  array();
        $vatemassnap = array();
        $global_variables = array(
            'cod_tema_disparado' => $cod_tema_disparado,
            'cod_tema_suceso' => $cod_tema_suc,
        );

        foreach ($subtemas as $cod_subtema) {
            $valor_subtema = Cache::get("iolast_" . $cod_subtema);
            $vatemassnap[$cod_subtema] = array('valor' => $valor_subtema);
            $global_variables['tema'][$cod_subtema] = $valor_subtema;
        }

        $vatemassnap[$cod_tema_disparado] = array('valor' => $event->event_data['valor']);
        $global_variables['tema'][$cod_tema_disparado] = $event->event_data['valor'];

        /*
        $context = array(
            'msgtext' => "Proceso condiciones $nom_tema ($cod_tema_suc), disparado por $cod_tema_disparo con valor $valor",
            //'cod_tema_origen' => $cod_tema,
        );
        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
        */
        try {
            if (
                $config['cond_alarma'] &&
                strpos($this->confSucesos[$cod_tema_suc]['cond_alarma'], $cod_tema_disparado) !== false
            ) {
                $res = $this->expressionLanguage->evaluate($config['cond_alarma'], $global_variables);

                if ($res == true) { //Actualizo la tabla de suceso moviUltSuceso
                    $valor = "A";
                    $event_data = array("valor" => $valor, "des_observaciones" => $des_valor, "json_detalle" => "");
                    Cache::forever(self::config_tag . $cod_tema_suc . "/$valor", array('stm_event' => $event->stm_evento, 'estados_temas' => $vatemassnap));
                    event(new TemaEvent($cod_tema_suc, Carbon::now(), $event_data));
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        try {
            if (
                $config['cond_prealarma'] &&
                strpos($this->confSucesos[$cod_tema_suc]['cond_prealarma'], $cod_tema_disparado) !== false
            ) {
                $res = $this->expressionLanguage->evaluate($config['cond_prealarma'], $global_variables);
                if ($res == true) { //Actualizo la tabla de suceso moviUltSuceso
                    $valor = "P";
                    $event_data = array("valor" => $valor, "des_observaciones" => $des_valor, "json_detalle" => "");
                    Cache::forever(self::config_tag . $cod_tema_suc . "/$valor", array('stm_event' => $event->stm_evento, 'estados_temas' => $vatemassnap));
                    event(new TemaEvent($cod_tema_suc, Carbon::now(), $event_data));
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        try {
            if ($config['cond_alarmatec'] && strpos($this->confSucesos[$cod_tema_suc]['cond_alarmatec'], $cod_tema_disparado) !== false) {
                $res = $this->expressionLanguage->evaluate($config['cond_alarmatec'], $global_variables);
                if ($res == true) { //Actualizo la tabla de suceso moviUltSuceso
                    $valor = "T";
                    $event_data = array("valor" => $valor, "des_observaciones" => $des_valor, "json_detalle" => "");
                    Cache::forever(self::config_tag . $cod_tema_suc . "/$valor", array('stm_event' => $event->stm_evento, 'estados_temas' => $vatemassnap));
                    event(new TemaEvent($cod_tema_suc, Carbon::now(), $event_data));
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        try {
            if ($config['cond_falla'] && strpos($this->confSucesos[$cod_tema_suc]['cond_falla'], $cod_tema_disparado) !== false) {
                $res = $this->expressionLanguage->evaluate($config['cond_falla'], $global_variables);
                if ($res == true) { //Actualizo la tabla de suceso moviUltSuceso
                    $valor = "F";
                    $event_data = array("valor" => $valor, "des_observaciones" => $des_valor, "json_detalle" => "");
                    Cache::forever(self::config_tag . $cod_tema_suc . "/$valor", array('stm_event' => $event->stm_evento, 'estados_temas' => $vatemassnap));
                    event(new TemaEvent($cod_tema_suc, Carbon::now(), $event_data));
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }

        try {
            if ($config['cond_normal'] && strpos($this->confSucesos[$cod_tema_suc]['cond_normal'], $cod_tema_disparado) !== false) {
                $res = $this->expressionLanguage->evaluate($config['cond_normal'], $global_variables);
                if ($res == true) { //Actualizo la tabla de suceso moviUltSuceso
                    $valor = "N";
                    $event_data = array("valor" => $valor, "des_observaciones" => $des_valor, "json_detalle" => "");
                    event(new TemaEvent($cod_tema_suc, Carbon::now(), $event_data));
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getTipoIntervalo($stm_evento)
    {
        $dayOfWeek = $stm_evento->copy()->timezone($this->timezone)->dayOfWeek + 1;
        $tipo_dia = "";  //H - N - M - X
        if (Feriados::isFeriado($stm_evento)) {
            $tipo_dia = $this->tipoDias["F"];
        } else {
            if (array_key_exists($dayOfWeek, (array) $this->tipoDias))
                $tipo_dia = $this->tipoDias[$dayOfWeek];
            else
                $tipo_dia = "X";
        }
        return $tipo_dia;
    }

    public function getIntervaloSucesos($tipo_intervalo)
    {
        $vaIntervalosSuceso = array();
        foreach ($this->confSucesos as $cod_tema => $row) {
            switch ($tipo_intervalo) {
                case "H":
                    $vaIntervalosSuceso[$cod_tema] = $row['obj_intervalos_habiles'];
                    break;
                case "N":
                    $vaIntervalosSuceso[$cod_tema] = $row['obj_intervalos_nohabiles'];
                    break;
                case "M":
                    $vaIntervalosSuceso[$cod_tema] = $row['obj_intervalos_mixtos'];
                    break;
                default:
                    $vaIntervalosSuceso[$cod_tema] = $row['obj_intervalos_nohabiles'];
                    break;
            }
            if (empty($vaIntervalosSuceso[$cod_tema]))
                $vaIntervalosSuceso[$cod_tema] = array(array('d' => '0', 'h' => '24'));
        }

        return $vaIntervalosSuceso;
    }

    public function envioDispositivo($cod_tema, $url_envio, $event_data)
    {
        $event_data["cod_tema"] = $cod_tema;
        $json = json_encode($event_data);
        $sendMsg = "";
        //        echo "Envio $cod_tema a $url_envio  $json";
        //        return;

        /*
        $json=str_replace("\"","\\\"",$json);
        $params = " -X POST -H \"Content-Type: application/json\" --data \"$json\" ";
        //            foreach($this->url_eventos as $url){
        $cmd = "(curl $params $url_envio) &";
        pclose(popen($cmd, "r"));
        //            }
*/
        if (strpos($url_envio, "tcp") === 0 || strpos($url_envio, "udp") === 0) {
            $error_no = "";
            $error_str = "";
            if (isset($event_data['extra_data']))
                $sendMsg = $event_data['extra_data'];
            else
                $sendMsg = sprintf("%s", $json);

            //        $datagram = DatagramSocket::bind('127.0.0.1:1337');
            //        $datagram = new DatagramSocket();

            //        $address = new SocketAddress("127.0.0.1","1337");
            //       $datagram->send($address,$sendMsg);

            //       $connectContext = new ConnectContext;

            //    $socket = connect("127.0.0.1:1337");
            //        stream_socket_sendto($socket,$sendMsg);
            //$socket->write($sendMsg);
            try {
                $timeout = 3;
                $socket = stream_socket_client($url_envio, $error_no, $error_str, $timeout); //'udp://127.0.0.1:1337'
                if ($socket) {
                    fwrite($socket, substr($sendMsg, 0, 9));
                    usleep(200);
                    fwrite($socket, substr($sendMsg, 9));
                }
            } catch (\Exception $e) {
                $msg = sprintf("Envio a %s %s %s (%s)", $this->temas[$cod_tema]['nom_tema'], $url_envio, $error_str, $error_no);
                Log::channel("eventos")->info($msg, array($this->temas[$cod_tema]));
                $context = array(
                    'msgtext' => $msg,
                    'cod_tema' => $cod_tema
                );
                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], "warning",  $context);
            }
        } else {

            $ch = curl_init($url_envio);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

function getTemaValue($cod_tema)
{
    return Cache::get("iolast_" . $cod_tema);
}

function getTemaCounter($cod_tema)
{
    return Cache::get("COUNT_" . "iolast_" . $cod_tema);
}

function isTemaVal($valor, $match)
{
    return (stripos($valor, $match) === false) ? false : true;
}


function setTemaValue($cod_tema, $valor_ini, $valor_fin, $tiempo_seg)
{
    $event_data = array("valor" => $valor_ini, "delay" => $tiempo_seg, "valor_fin" => $valor_fin);
    event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
}

function sendMail()
{
    //    $event_data = array("valor" => $valor_ini, "delay" => $tiempo_seg, "valor_fin" => $valor_fin);
    //    event(new TemaEvent($cod_tema, Carbon::now(), $event_data));
}


class StringExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return [
            new ExpressionFunction('lowercase', function ($str) {
                return sprintf('(is_string(%1$s) ? strtolower(%1$s) : %1$s)', $str);
            }, function ($arguments, $str) {
                if (!is_string($str)) {
                    return $str;
                }

                return strtolower($str);
            }),
            ExpressionFunction::fromPhp('App\Listeners\getTemaValue', 'getTemaValue'),
            ExpressionFunction::fromPhp('App\Listeners\isTemaVal', 'isTemaVal'),
            ExpressionFunction::fromPhp('App\Listeners\getTemaCounter', 'getTemaCounter'),
            ExpressionFunction::fromPhp('App\Listeners\setTemaValue', 'setTemaValue'),
            ExpressionFunction::fromPhp('App\Listeners\sendMail', 'sendMail'),

        ];
    }
}
