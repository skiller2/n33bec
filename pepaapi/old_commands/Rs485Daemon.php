<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Http\Middleware\ComunicacionDispositivos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;

use PiPHP\GPIO\GPIO;
use PiPHP\GPIO\Pin\PinInterface;
use Amp\Delayed;

//use Calcinai\PHPi\Pin\PinFunction;
//use Calcinai\PHPi\Pin;

//socat  pty,link=/dev/virtualcom0,raw  tcp:192.168.5.119:8080

class Rs485Daemon extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:Rs485Daemon
                            {--debug : Print debug information to console}
                            {--cod_tema= : Identificacion de origen ej desa/lector/218}
                            {--value= : lectura ej 9877187}
                            Ej: php artisan command:Rs485Daemon --cod_tema=desa/lector/218 --value=9877187';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RS485 Processing daemon';

    /**
     * The console command description.
     *
     * @var json
     */
    protected $_device = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $_baudrate;
    protected $temp_dir = "";
    protected $_dHandle = "";
    protected $comdisp;
    protected $data_tmp;
    protected $enable_pin;
	protected $rasp_io;
    const logFileName = "serial";
	const timetolog = 0;  //0.1
    protected $daemon_conf_ver="";
    const confVersion = "daemon_conf_ver";

    protected function printDebugInfo($text, $status = "info") {
        if ($this->option('debug')) {
            Log::channel(self::logFileName)->info($text, array());
        }
        return true;
    }

    public function connect_retry() {
        $retrysecs = 10;
        $this->deviceClose();
       
        while (1) {
            $this->printDebugInfo("Intentando conexión con puerto " . $this->_device);
            try {
                if ($this->deviceOpen()){
                    break;
                }
            } catch (\Exception $e) {
                $this->printDebugInfo("Error conectando " . $this->_device . " " . $e->getMessage() . " reintento en $retrysecs segundos");

                $context=array(
                    'msgtext'=>__("Error conectando con puerto :DEVICE",['DEVICE'=>$this->_device])
                );
                Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'error',  $context);

                $this->deviceClose();
            }
            sleep($retrysecs);
        }
    }

    public function deviceClose() {
        if ($this->_dHandle)
            @fclose($this->_dHandle);
        $this->_dHandle = null;
        return true;
    }

    public function deviceOpen($mode = "r+b") {
        @fclose($this->_dHandle);
        if ($this->_dHandle) {
            trigger_error("The device is already opened", E_USER_NOTICE);
            return true;
        }
        $this->_device = ConfigParametro::get('RS485_PORT', false);
        if (!$this->_device) {
            trigger_error("The device" . $this->_device . " must be set before to be open", E_USER_WARNING);
            return false;
        }

        if (!preg_match("@^[raw]\+?b?$@", $mode)) {
            trigger_error("Invalid opening mode : " . $mode . ". Use fopen() modes.", E_USER_WARNING);
            return false;
        }
        $this->enable_pin->setValue(PinInterface::VALUE_LOW);
        //$this->enable_pin->low();
        //exec("/bin/stty -F ".$this->_device." ".$this->_baudrate." -icrnl -imaxbel -opost -onlcr -isig -icanon -echo time 2 min 100");
        exec("/bin/stty -F " . $this->_device . " " . $this->_baudrate . " raw -echo");
        //exec("mode ".$this->_device." BAUD=".$this->_baudrate." PARITY=n DATA=8 STOP=1 xon=off octs=off rts=on");

        $this->_dHandle = @fopen($this->_device, $mode);

        if ($this->_dHandle !== false) {
            stream_set_blocking($this->_dHandle, 0);
            return true;
        }

        $this->_dHandle = null;
        trigger_error("Unable to open the device " . $this->_device, E_USER_WARNING);
        return false;
    }

    public function sendCommand_RandWiegand($tema_local,$io, $habilita) {
        $msg = sprintf("RS485 destino:%s comando:RandWiegand habilita:%s", $tema_local, $habilita);
        $this->printDebugInfo($msg);

        // 1 0 3 68 7 2 50 57 54 66 54 67 51 3 221 4
        $message = "              ";
        $message[0] = "\x01"; //Start
        $message[1] = chr($tema_local);
        $message[2] = "\x00";
        $message[3] = "D";
        $message[4] = "\x05"; //Tamano paquete
        $message[5] = "\x02"; //Inicio
        if ($habilita)
            $message[6] = "\x01"; //   "\x11"; //paquete
        else
            $message[6] = "\x00"; //   "\x11"; //paquete
        $message[7] = "\x00";
        $message[8] = "\x00";
        $message[9] = "\x00";
        $message[10] = "\x00";
        $message[11] = "\x03"; //Fin
        $message[12] = "\x1B"; //CRC  Falta calcular CRC
        $message[13] = "\x04"; //Fin comando

        if (fwrite($this->_dHandle, $message) !== false)
            return true;
        else
            return false;
    }

    public function sendCommand($tema_local,$io, $vadata) {
		if (!$vadata && empty($vadata))
			return true;

        // 1 0 3 68 7 2 50 57 54 66 54 67 51 3 221 4

        $message = "           ";
        $message[0] = "\x01"; //Start
        $message[1] = chr($tema_local);
        $message[2] = "\x00";
        $message[3] = "A";
        $message[4] = "\x07"; //Tamano paquete
        $message[5] = "\x02"; //Inicio
        $message[6] = chr($vadata[0]); //   "\x11"; //paquete buzzer1
        $message[7] = chr($vadata[1]); // "\x12"; //paquete buzzer2
        $message[8] = chr($vadata[2]); // "\x13"; //paquete rele1
        $message[9] = chr($vadata[3]); // "\x14"; //paquete rele2
        $message[10] = chr($vadata[4]); // "\x15"; //paquete rele3
        $message[11] = chr($vadata[5]); // "\x16"; //paquete led1
        $message[12] = chr($vadata[6]); // "\x17"; //paquete led2
        $message[13] = "\x03"; //Fin
        $message[14] = "\x1B"; //CRC  Falta calcular CRC
        $message[15] = "\x04"; //Fin comando
		
        $this->enable_pin->setValue(PinInterface::VALUE_HIGH);
        //$this->enable_pin->high();
		$resp = fwrite($this->_dHandle, $message);
		fflush($this->_dHandle);
		usleep( 2 * 1000 );
		$this->enable_pin->setValue(PinInterface::VALUE_LOW);
		//$this->enable_pin->low();
		if ($resp !== false)
            return true;
        else
            return false;
    }

    public function readPort($count = 0) {
        $data = "";
        $chr = "";
        $read = array($this->_dHandle);
        $write = NULL;
        $except = NULL;
        $break = false;

        if (false === ($num_changed_streams = stream_select($read, $write, $except, 1))) {
			$this->printDebugInfo("error select");

        } elseif ($num_changed_streams > 0) {

            do {
                $chr = stream_get_contents($this->_dHandle, 1);
                $this->data_tmp .= $chr;

                if ($chr == "\x04")
                    $break = true;
            } while ($chr != "" and $break != true);
        }

        if ($chr == "\x04") {
            $data = $this->data_tmp;
            $this->data_tmp = "";
        }

        return $data;
    }

    public function readPort_old($count = 0) {
        $content = "";
        $i = 0;

        if ($count !== 0) {
            do {
                if ($i > $count)
                    $content .= fread($this->_dHandle, ($count - $i));
                else
                    $content .= fread($this->_dHandle, 128);
            } while (($i += 128) === strlen($content));
        }
        else {
            /*
              do {
              $content.= fread($this->_dHandle, 128);
              usleep(100);
              } while (strpos($content,"\x04")===false );
             */

            $break = false;
            do {
                do {
                    $chr = fread($this->_dHandle, 1);
                    $content .= $chr;
                    if ($chr == "\x04" || feof($this->_dHandle))
                        $break = true;
                } while ($chr != "");
                usleep(100);
            } while ($break == false);
            if (feof($this->_dHandle))
                $this->connect_retry();
        }

        return $content;
    }

    protected function loadConfigData() {
        $this->tema_local = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $this->_device = ConfigParametro::get('RS485_PORT', false);        
		$this->daemon_conf_ver = Cache::get(self::confVersion);
        $this->printDebugInfo("Configuración actualizada a ".$this->daemon_conf_ver);
	}
    
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        
        
        $this->_baudrate = 115200;
        $this->temp_dir = sys_get_temp_dir();
        $this->rasp_io = new GPIO();
          $this->enable_pin = $this->rasp_io->getOutputPin(7);

//        $this->rasp_io = \Calcinai\PHPi\Factory::create();
//        $this->enable_pin = $this->rasp_io->getPin(7) //BCM pin number
//             ->setFunction(PinFunction::OUTPUT);
		
        $this->loadConfigData();
        if ($this->_device == "") {
            $this->printDebugInfo("Parametro RS485_PORT vacío");
            return;
        }
        
        if ($this->option('cod_tema') and $this->option('value')) {
            $vacredencial = array(
				'cod_tema' => $this->option('cod_tema'),
                'value' => $this->option('value')
            );

            //Debería ir al Listener
            $this->comdisp = new ComunicacionDispositivos;
            $foo = $this->comdisp->leeCredencial($vacredencial);
            if ($foo->status() == 200) {
				$respuesta = $foo->original['rs485'];
                echo "Resultado " . json_encode($respuesta) . "\n";
            }
            return;
        }

        $this->connect_retry();
        // We can change the baud rate, parity, length, stop bits, flow control
        /*
          $serial->confBaudRate(115200);
          $serial->confParity("none");
          $serial->confCharacterLength(8);
          $serial->confStopBits(1);
          $serial->confFlowControl("none");
         */

        $leeCredencialPerf = 0;
        $parseCommandPerf = 0;
        $sendCommandPerf = 0;
        $readdata="";
		$status="";
        
        $context=array(
            'msgtext'=>__("Conexión exitosa con :DEVICE",['DEVICE'=>$this->_device])
        );
        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);

        $this->printDebugInfo("Conexión exitosa con " . $this->_device);

//        $this->sendCommand_RandWiegand(206,1);
        while (1) {
            if (Cache::get(self::confVersion)!=$this->daemon_conf_ver){
                if ($this->_device != ConfigParametro::get('RS485_PORT', false)) {
                    $this->printDebugInfo("Reinicio por cambio de configuración de puerto");
                    return; 
                }
                unset($this->comdisp);
                $this->loadConfigData();
                $this->comdisp = new ComunicacionDispositivos;
            }
            
            if (file_exists("/tmp/randwiegand_start") === true) {
                unlink("/tmp/randwiegand_start");
                $this->sendCommand_RandWiegand(206,'0', 1);
            }
            if (file_exists("/tmp/randwiegand_stop") === true) {
                unlink("/tmp/randwiegand_stop");
                $this->sendCommand_RandWiegand(206,'0', 0);
            }

            $read_tmp = $this->readPort(0);
            if ($read_tmp == "") {
                continue;
            } else {  //Proceso comando
                $read = $read_tmp;
                $start = microtime(true);
                $data = $this->comdisp->parseCommand($read);
                $status = "ERROR";
                $parseCommandPerf = (microtime(true) - $start);
				$status="NONE";
				$readdata="";
				$leeCredencialPerf=0;
				$sendCommandPerf=0;
                if ($data != false) {
                    if ($data['comando'] == 'R') {
                        $start1 = microtime(true);
                        $foo = $this->comdisp->leeCredencial($data);
                        if ($foo->status() == 200) {
							$respuesta = $foo->original['rs485'];
                            $leeCredencialPerf = (microtime(true) - $start1);
                            $start2 = microtime(true);
                            $this->sendCommand($data['id_origen'],$data['io'], $respuesta);
                            $sendCommandPerf = microtime(true) - $start2;
                            $status = "OK";
							$readdata=sprintf("%s %s,  Rta:%s", $data['cod_tema'],  $data['value'], json_encode($respuesta));
                        } else {
							$readdata="leeCredencial: ".$foo->status();
						}
                    } else if ($data['comando'] == 'S') {
                        $status = "START";
						$start2 = microtime(true);
                        $this->sendCommand($data['id_origen'],$data['io'], array(4,4,4,4,4,4,4));
                        $sendCommandPerf = microtime(true) - $start2;						
						
						$readdata=sprintf("%s %s ", $data['cod_tema'],  $data['value']);
                        $msg = sprintf("RS485 %s: %s , parseCommand:%f", $status, $readdata, $parseCommandPerf * 1000);

                        $context=array(
                            'msgtext'=>$msg
                        );
                        Broadcast::driver('fast-web-socket')->broadcast(["pantalla"], 'info',  $context);
                    } else {
                        $status = "CMDFAIL";
						$readdata=strlen($read) . " " . bin2hex($read);
					}
				} else {
					$status="FAIL";
					$readdata=strlen($read) . " " . bin2hex($read);
				}
                $globalTimePerf = microtime(true) - $start;
                if($status!="OK" || $globalTimePerf>self::timetolog){
                    $msg = sprintf("RS485 %s: %s, parseCommand:%f, leeCredencialPerf:%f, sendCommandPerf:%f, globalTimePerf:%f", $status, $readdata,$parseCommandPerf * 1000, $leeCredencialPerf * 1000, $sendCommandPerf * 1000, $globalTimePerf * 1000);
                    $this->printDebugInfo($msg);
                }
            }
        }// end while
    }
//End Handle
}
