<?php

namespace App\Http\Controllers;

use App\Helpers\ConfigParametro;
use App\Http\Controllers\Auth\PepaUserProvider;
use JWTAuth;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use function auth;

class WebSocketController extends Controller implements MessageComponentInterface
{
    private $connections = [];
    private $ind_anonymous_send = false;
    private $pepa;

    public function __construct()
    {
        $this->pepa = new PepaUserProvider;
    }

/**
 * When a new connection is opened it will be passed to this method
 * @param  ConnectionInterface $conn The socket/connection that just connected to your application
 * @throws \Exception
 */
    public function onOpen(ConnectionInterface $conn)
    {
        //echo "onOpen \n";        
        $this->connections[$conn->resourceId] = compact('conn') + ['user_id' => null, 'token' => null];
        $this->ind_anonymous_send = ConfigParametro::get("CONSOLA_SIN_LOGIN", false);
        // $uri = $conn->remoteAddress;
        // echo "ip coneccion ".var_export($uri,true)." \n";
    }

/**
 * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
 * @param  ConnectionInterface $conn The socket/connection that is closing/closed
 * @throws \Exception
 */
    public function onClose(ConnectionInterface $conn)
    {
        $disconnectedId = $conn->resourceId;
        unset($this->connections[$disconnectedId]);
        //echo "onClose";
    }

/**
 * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
 * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
 * @param  ConnectionInterface $conn
 * @param  \Exception $e
 * @throws \Exception
 */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $userId = $this->connections[$conn->resourceId]['user_id'];
        //echo "An error has occurred with user $userId: {$e->getMessage()}\n";
        unset($this->connections[$conn->resourceId]);
        $conn->close();
    }

/**
 * Triggered when a client sends data through the socket
 * @param  \Ratchet\ConnectionInterface $conn The socket/connection that sent the message to your application
 * @param  string $msg The message received
 * @throws \Exception
 */
    public function onMessage(ConnectionInterface $conn, $msg)
    {
        //echo "onMessage init ".var_export($msg,true)." \n\n";
        $msg = json_decode($msg, true);
        $channel = (isset($msg['channel'])) ? $msg['channel'] : "unknown";
        $origen = (isset($msg['origen'])) ? $msg['origen'] : null;
        $context = (isset($msg['context'])) ? $msg['context'] : [];
        $vachannels = array('pantalla', 'estados', 'input', 'io', 'smallscreen', 'sucesos', 'movcred','display_area54');
        
        if ($channel === "login") {
            $this->connections[$conn->resourceId]['user_id'] = null;
            $token = ($context['token']) ? $context['token'] : null;
            if ($token) {
                $codUsuario = ($context['codUsuario']) ? $context['codUsuario'] : null;
                if ($user = $this->pepa->retrieveById($codUsuario)) {
                    if ($user['cod_usuario'] === $codUsuario) {
                        $this->connections[$conn->resourceId]['user_id'] = $user['cod_usuario'];
                    }
                }
            }
            $conn->send(json_encode(array(), true));
            return;
        }
        if (in_array($channel, $vachannels)) {
            $send_msg=json_encode($msg);
            foreach ($this->connections as $resourceId => &$connection) {
                if ($conn->resourceId != $resourceId && $connection['user_id']!="server") {
                    if ($connection['user_id'] || $this->ind_anonymous_send) {
                        $connection['conn']->send($send_msg);
                    } else if ($channel!='movcred'){
                        $connection['conn']->send($send_msg);
                    }
                } else { 
                    $connection['conn']->send("{}"); //El php cuando envía un msg espera respuesta
                }
            } 
        }

        //echo "onMessage fin\n";
        return true;
    }
}
