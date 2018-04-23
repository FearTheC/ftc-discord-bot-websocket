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
use RedisClient\ClientFactory;
use RedisClient\RedisClient;

chdir(dirname(__DIR__));

require 'vendor/autoload.php';
$config = include 'config/app.php';

$httpClient = new Client();
$loop = React\EventLoop\Factory::create();
$connector = new Ratchet\Client\Connector($loop);

$bot = new Bot($config, $loop, $connector, $httpClient);

$brokerHost = 'broker';
$brokerPort = 5672;

while (($connection = fsockopen($brokerHost, $brokerPort)) === false) {
    echo 'Waiting for broker service startup'.PHP_EOL;
    sleep(1);
}
fclose($connection);
echo 'Broker service started up'.PHP_EOL;

$brokerConn = new AMQPStreamConnection($brokerHost, $brokerPort, 'guest', 'guest');
$channel = $brokerConn->channel();
$channel->queue_declare('hello', false, true, false, false);

$cacheHost = 'cache';
$cachePort = 6379;
while (($connection = fsockopen($cacheHost, $cachePort)) === false) {
    echo 'Waiting for cache service startup'.PHP_EOL;
    sleep(1);
}
fclose($connection);
echo 'Cache service started up'.PHP_EOL;

$cacheConn = new RedisClient($config['cache']);

if ($seq = $cacheConn->get('last_event_seq')) {
    $bot->updateSequence($seq);
}


$app = function (WebSocket $conn) use ($loop, &$app, $bot, $channel, $cacheConn) {
    $bot->setConnection($conn);
    $conn->on('message', function (MessageInterface $msg) use ($conn, $loop, $bot, $channel, $cacheConn) {
        echo "Received: {$msg}\n";
        $response = json_decode($msg, true);
        if ($response['op'] == 9) {
            $sleepTime = rand(1,5);
            echo "Invalid resume or identify, waiting ".$sleepTime."s to reconnect".PHP_EOL;
            sleep($sleepTime);
            if (!$response['d']) {
                $bot->identify();
            }
        }
        
        if ($response['t'] == 'READY') {
            $cacheConn->set('session_id', $response['d']['session_id']);
        }
        
        if ($response['s'] != null) {
            $cacheConn->set('last_event_seq', $response['s']);
            $bot->updateSequence($response['s']);
        }
        
        if ($response['d']) {
            $mess = new AMQPMessage(json_encode(['event' => $response['t'], 'data' => $response['d']]), ['delevery_mode' => 2]);
            $channel->basic_publish($mess, '', 'hello');
        }
        
        if ($response['op'] == 10) {
            if ($sessionId = $cacheConn->get('session_id')) {
                echo 'RESUMING with sessId '.$sessionId.' & seq '.$cacheConn->get('last_event_seq').PHP_EOL;
                $bot->resume($sessionId, $cacheConn->get('last_event_seq'));
            } else {
                echo 'IDENTIFYING'.PHP_EOL;
                $bot->identify();
            }
            $bot->startHeartbeats($response['d']['heartbeat_interval']);
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
