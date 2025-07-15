<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Debug\Exception\FatalThrowableError;

use Carbon\Carbon;

class SendMail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    const logFileName="mail";

    protected $des_destinatarios;
    protected $des_asunto;
    protected $des_mensaje;
    protected $den_template;
    protected $template;
    protected $data;
    protected $configMAIL;
    /**
     * Create a new job instance.
     *
     * @return void
     */

     public function generateViewFile($html, $url, $updated_at)
     {
         // Get the Laravel Views path
         $path = Config::get('view.paths.0');

         // Here we use the date for unique filename - This is the filename for the View
         $viewfilename = $url."-".hash('sha1', $updated_at);

         // Full path with filename
         $fullfilename = $path."/cache/".$viewfilename.".blade.php";

         // Write the string into a file
         if (!file_exists($fullfilename))
         {
             file_put_contents($fullfilename, $html);
         }

         // Return the view filename - This could be directly used in View::make
         return $viewfilename;
     }

    public function __construct($den_template, $data=array())
    {
        $this->den_template = $den_template;
        $this->des_asunto = $data['des_asunto'];
        $this->des_destinatarios = $data['des_destinatarios'];
        $this->template=ConfigParametro::get($den_template,false);

        if(isset($data['stm_evento']) && $data['stm_evento'] != "")
            $data['stm_evento']=$data['stm_evento']->setTimezone(ConfigParametro::get('TIMEZONE_INFORME',false))->format('d-m-Y H:i:s');
        if(isset($data['stm_access']) && $data['stm_access'] != "")
            $data['stm_access']=$data['stm_access']->setTimezone(ConfigParametro::get('TIMEZONE_INFORME',false))->format('d-m-Y H:i:s');
        if(isset($data['fec_vencimiento_af']) && $data['fec_vencimiento_af'] != "")
            $data['fec_vencimiento_af']=$data['fec_vencimiento_af']->setTimezone(ConfigParametro::get('TIMEZONE_INFORME',false))->format('d-m-Y');

        $this->data = $data;

        Config::set('mail.from.address',ConfigParametro::get('SMTP_EMISOR',false));
        Config::set('mail.from.name',"");
        Config::set('mail.username',ConfigParametro::get('SMTP_USUARIO',false));
        Config::set('mail.password',ConfigParametro::get('SMTP_CONTRASENA',false));
        Config::set('mail.driver','smtp');

        $urlParts = parse_url(ConfigParametro::get('SMTP_SERVER',false));

        $port=(isset($urlParts['port'])) ? $urlParts['port'] : 587;
        $host=(isset($urlParts['host'])) ? $urlParts['host'] : $urlParts['path'];
        $schemme=(isset($urlParts['schemme'])) ? $urlParts['schemme'] : "";
        Config::set('mail.host',$host);
        Config::set('mail.port',$port);
        Config::set('mail.encryption',$schemme);
        $this->configMAIL=Config::get('mail');
    }

    public function bladeCompile($value, array $args = array()){
          $generated = \Blade::compileString($value);
          ob_start() and extract($args, EXTR_SKIP);    // We'll include the view contents for parsing within a catcher    // so we can avoid any WSOD errors. If an exception occurs we    // will throw it out to the exception handler.
          try    {
            eval('?>'.$generated);
          }    // If we caught an exception, we'll silently flush the output    // buffer so that no partially rendered views get thrown out    // to the client and confuse the user with junk.
          catch (\Exception $e)    {
            ob_get_clean(); throw $e;
          }
          $content = ob_get_clean();
          return $content;
    }

    public static function render($string, $data)
       {
           $php = \Blade::compileString($string);

           $obLevel = ob_get_level();
           ob_start();
           extract($data, EXTR_SKIP);

           try {
               eval('?' . '>' . $php);
           } catch (\Exception $e) {
               while (ob_get_level() > $obLevel) ob_end_clean();
               throw $e;
           } catch (\Throwable $e) {
               while (ob_get_level() > $obLevel) ob_end_clean();
               throw new FatalThrowableError($e);
           }

           return ob_get_clean();
       }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $des_asunto = $this->des_asunto;
        $des_destinatarios = $this->des_destinatarios;
        $des_mensaje ="Plantilla sin datos \n\n".var_export($this->data,true)."\n\n";
        Log::channel(self::logFileName)->info("Envia mail",$this->data);

        $cuerpo=self::render($this->template,$this->data);

        if($cuerpo != "")
        {
            Config::set('mail',$this->configMAIL);
            Mail::send([], [], function ($message) use ($cuerpo, $des_asunto, $des_destinatarios)
            {
                $message->subject($des_asunto)
                        ->setBody($cuerpo, 'text/html');
                foreach($des_destinatarios as $index=>$destinatario){
                    if($index==0)
                        $message->to($destinatario);
                    else
                        $message->cc($destinatario);
                }
            });
            Log::channel(self::logFileName)->info("Se envió mail con datos",array("data"=>$this->data,"cuerpo"=>$cuerpo,"des_asunto"=>$des_asunto));
        }else{
            Log::channel(self::logFileName)->warning("No se pudo procesar el template",array("template"=>$this->template,
                                                                                                   "den_template"=>$this->den_template,
                                                                                                   "data"=>$this->data));
        }
    }
}
