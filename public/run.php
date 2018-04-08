<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use GuzzleHttp\Client;
use Ratchet\Client\WebSocket;
use FtcDiscordBot\Bot;
use Ratchet\RFC6455\Messaging\MessageInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';
$config = include 'config/app.php';

$httpClient = new Client();
$loop = React\EventLoop\Factory::create();
$connector = new Ratchet\Client\Connector($loop);

$bot = new Bot($config, $loop, $connector, $httpClient);

$brokerConn = new AMQPStreamConnection('broker', 5672, 'guest', 'guest');
$channel = $brokerConn->channel();
$channel->queue_declare('hello', false, false, false, false);


$app = function (WebSocket $conn) use ($loop, &$app, $bot, $channel) {
    $bot->setConnection($conn);
    $conn->on('message', function (MessageInterface $msg) use ($conn, $loop, $bot, $channel) {
        echo "Received: {$msg}\n";
        
        $response = json_decode($msg, true);
        if ($response['op'] == 10) {
            $bot->startHeartbeats($response['d']['heartbeat_interval']);
            $bot->identify();
        }
        
        if ($response['d']) {
            $mess = new AMQPMessage(json_encode(['event' => $response['t'], 'data' => $response['d']]));
            $channel->basic_publish($mess, '', 'hello');
        }
    });
        
    $conn->on('close', function ($code = null, $reason = null) use ($app, $bot) {
        echo "Connection closed ({$code} - {$reason})\n";
        
        $bot->getLoop()->addTimer(3, function () use ($bot, $app) {
            $bot->connect($app);
        });
    });
    
};


$bot->connect($app);
$bot->run();

$channel->close();
$brokerConn->close();
