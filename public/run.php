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
var_dump(getmyuid(), getmygid());
require 'vendor/autoload.php';
$discordConfig = include '/app/config/bot.local.php';
$brokerConfig = include '/app/config/broker.local.php';
$cacheConfig = include '/app/config/cache.local.php';

$httpClient = new Client();
$loop = React\EventLoop\Factory::create();
$connector = new Ratchet\Client\Connector($loop);

$bot = new Bot($discordConfig, $loop, $connector, $httpClient);

$brokerConn = getBrokerConnection($brokerConfig['broker']);
// while (($connection = fsockopen($brokerConfig['host'], $brokerConfig['port'])) === false) {
//     echo 'Waiting for broker service being reachable...'.PHP_EOL;
//     sleep(1);
// }
// fclose($connection);
// echo 'Broker service reachable !.'.PHP_EOL;

// $brokerConn = new AMQPStreamConnection(
//     $brokerConfig['host'],
//     $brokerConfig['port'],
//     $brokerConfig['username'],
//     $brokerConfig['password']
// );
$channel = $brokerConn->channel();
$channel->queue_declare('hello', false, true, false, false);

$cacheHost = 'cache';
$cachePort = 6379;


$cacheConn = getCacheConnection($cacheConfig['cache']);
// while (($connection = fsockopen($cacheConfig['cache']['server'].':'.$cacheConfig['cache']['port'], $cacheConfig)) === false) {
//     echo 'Waiting for cache service being reachable...'.PHP_EOL;
//     sleep(1);
// }
// fclose($connection);
// echo 'Cache service is reachable !'.PHP_EOL;

// $cacheConn = new RedisClient($cacheConfig['cache']);

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
            $mess = new AMQPMessage(json_encode(['event' => $response['t'], 'data' => $response['d'], 'timestamp' => microtime(true)]), ['delivery_mode' => 2, 'timestamp' => microtime(true)]);
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

function getBrokerConnection($brokerConfig)
{
    while (($connection = fsockopen($brokerConfig['host'], $brokerConfig['port'])) === false) {
        echo 'Waiting for broker service being reachable...'.PHP_EOL;
        sleep(1);
    }
    fclose($connection);
    echo 'Broker service reachable !.'.PHP_EOL;
    
   return new AMQPStreamConnection(
        $brokerConfig['host'],
        $brokerConfig['port'],
        $brokerConfig['username'],
        $brokerConfig['password']
        );
}

function getCacheConnection($config)
{
    while (($connection = fsockopen($config['host'], $config['port'])) === false) {
        echo 'Waiting for cache service being reachable...'.PHP_EOL;
        sleep(1);
    }
    fclose($connection);
    echo 'Cache service is reachable !'.PHP_EOL;
    
    return new RedisClient(['server' => $config['host'].':'.$config['port']]);
}
