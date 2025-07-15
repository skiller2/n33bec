<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use App\Helpers\WebSocketLocal;
use App\Helpers\ConfigParametro;


class FastWebSocket extends Broadcaster
{
    protected $websock;

    // Implement all abstract methods.
    public function __construct()
    {
        /*
        try{
            $this->websock = new WebSocketLocal(array
                (
                    'host' => '127.0.0.1',
                    'port' => 8090,
                    'path' => ''
                ));
        } catch (\Exception $e) {
        
        }
        */
    }
    
    public function send($channel, $level, $message, array $context = array())
    {
        /*
        foreach ($channels as $channel) {
            $payload = [
                'text' => array_merge(['eventtype' => $event], $payload)
            ];
            $request = $this->client->createRequest('POST', '/pub?id=' . $channel, ['json' => $payload]);
            $response = $this->client->send($request);
        }*/
        //file_put_contents("C:/temp/broadcast.txt",$channel." ".$level." ".$message." ".var_export($context,true));

    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        foreach ($channels as $channel) {
            //$payload = [
            //    'text' => array_merge(['eventtype' => $event], $payload)
            //];
            list($usec, $sec) = explode(' ', microtime());            
            $timeStamp = date('Y-m-d H:i:s.', $sec) . str_pad( $usec * 1000000, 6,"0",STR_PAD_LEFT);
            $msg = json_encode(array("origen" => "server",
                                    "channel" => $channel,
                                    "message" => isset($payload['msgtext'])?$payload['msgtext']:"",
                                    "context" => $payload,
                                    "timeStamp" => $timeStamp,                                    
                                    "level" => $event));
            try{
                $ch = curl_init("http:///localhost/wspub/$channel");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);                
                $ret = curl_exec($ch);
                curl_close($ch);
                $ret ="";
//                $result = $this->websock->send($msg);
            } catch (\Exception $e) { }
        }
    }   
    
    public function auth($request)
    {
        
    }

    public function validAuthenticationResponse($request, $result)
    {
        
    }

}