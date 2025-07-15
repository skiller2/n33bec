<?php

namespace App\Helpers;

class WebSocketLocal {

    private $params;
    private $head;
    private $instance;

    public function __construct($params) {
        foreach ($params as $key => $value)
            $this->params[$key] = $value;
        $local = "http://" . $this->params['host'];
        if (isset($_SERVER['REMOTE_ADDR']))
            $local = "http://" . $_SERVER['REMOTE_ADDR'];
        $this->head = "GET / HTTP/1.1\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Host: " . $this->params['host'] . "\r\n" .
                "Origin: " . $local . "\r\n" .
                "Sec-WebSocket-Key: TyPfhFqWTjuw8eDAxdY8xg==\r\n" .
                "Sec-WebSocket-Version: 13\r\n";
        $this->instance = false;
    }

    public function send($msg) {
        /*
        $wsdata="";
        //$heads=$this->head . "Content-Length: " . strlen($msg) . "\r\n\r\n";
        if ($this->instance===false)
            $this->connect();
        
        if ($this->instance!==false){
            fwrite($this->instance, $this->hybi10Encode($msg));
            $wsdata = fread($this->instance, 2000);
            if ($wsdata===false || $wsdata=="") 
                $this->close();
        }
        return $this->hybi10Decode($wsdata);
        */

        $url = "http://localhost/wspub/$channel";
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

    }

    public function close() {
        if ($this->instance !== false) {
            fclose($this->instance);
        }
        $this->instance = false;
    }

    private function connect() {
        $this->instance = fsockopen($this->params['host'], $this->params['port'], $errno, $errstr, 2);
        if ($this->instance===false){
            return false;
        }

        fwrite($this->instance, $this->head."\r\n");
        $headers = fread($this->instance, 2000);
        if ($headers === false || $headers ==""){
            $this->close();
        }

    }

    private function hybi10Decode($data) {
        $bytes = $data;
        $dataLength = '';
        $mask = '';
        $coded_data = '';
        $decodedData = '';
        $secondByte = sprintf('%08b', ord($bytes[1]));
        $masked = ($secondByte[0] == '1') ? true : false;
        $dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);
        if ($masked === true) {
            if ($dataLength === 126) {
                $mask = substr($bytes, 4, 4);
                $coded_data = substr($bytes, 8);
            } elseif ($dataLength === 127) {
                $mask = substr($bytes, 10, 4);
                $coded_data = substr($bytes, 14);
            } else {
                $mask = substr($bytes, 2, 4);
                $coded_data = substr($bytes, 6);
            }
            for ($i = 0; $i < strlen($coded_data); $i++)
                $decodedData .= $coded_data[$i] ^ $mask[$i % 4];
        } else {
            if ($dataLength === 126)
                $decodedData = substr($bytes, 4);
            elseif ($dataLength === 127)
                $decodedData = substr($bytes, 10);
            else
                $decodedData = substr($bytes, 2);
        }
        return $decodedData;
    }

    private function hybi10Encode($payload, $type = 'text', $masked = true) {
        $frameHead = array();
        $frame = '';
        $payloadLength = strlen($payload);
        switch ($type) {
            case 'text' :
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;
            case 'close' :
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;
            case 'ping' :
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;
            case 'pong' :
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }
        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535) {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++)
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2] > 127) {
                $this->close(1004);
                return false;
            }
        } elseif ($payloadLength > 125) {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        } else
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i)
            $frameHead[$i] = chr($frameHead[$i]);
        if ($masked === true) {
            // generate a random mask:
            $mask = array();
            for ($i = 0; $i < 4; $i++)
                $mask[$i] = chr(rand(0, 255));
            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++)
            $frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        return $frame;
    }

}
