<?php
namespace FtcDiscordBot;

use React\EventLoop\LoopInterface;
use Ratchet\Client\Connector;
use GuzzleHttp\ClientInterface;
use Ratchet\Client\WebSocket;
use React\EventLoop\Timer\Timer;

class Bot
{
    
    private $baseUri;
    
    /**
     * @var LoopInterface $loop
     */
    private $loop;
    
    /**
     * @var Connector $connector
     */
    private $connector;
    
    /**
     * @var ClientInterface $httpClient
     */
    private $httpClient;
    
    /**
     * @var WebSocket $connection
     */
    private $connection;
    
    private $config;
    
    private $sequence = null;
    
    
    public function __construct(
        $config,
        LoopInterface $loop,
        Connector $connector,
        ClientInterface $httpClient)
    {
        $this->config = $config;
        $this->loop = $loop;
        $this->connector = $connector;
        $this->httpClient = $httpClient;
        $this->baseUri = $this->retrieveBaseUri();
    }
    
    
    public function connect($app)
    {
        $this->getConnector()($this->baseUri.'/?v=6&encoding=json')
            ->then($app, function(\Exception $e) {
                echo "Could not connect: {$e->getMessage()}\n";
                $this->getLoop()->stop();
                });
    }
    
                
    public function run()
    {
        $this->loop->run();
    }
    
    
    public function startHeartbeats($heartbeatsInterval)
    {
        $intervalInSeconds = $heartbeatsInterval / 1000;
        $this->loop->addPeriodicTimer($intervalInSeconds, function(Timer $timer) {
            $this->connection->send(json_encode(['op' => 1, 'd' => $this->sequence]));
            echo 'Sent heartbeat, sequence was '.$this->sequence.PHP_EOL;
        });
    }
    
    public function updateSequence($sequence)
    {
        $this->sequence = $sequence;
    }
    
    public function resume($sessionId, $sequence)
    {
        $payload = [
            'token' => $this->config['discord']['token'],
            'session_id' => $sessionId,
            'seq' => $sequence,
        ];
        $mess = [
            'op' => 6,
            'd' => $payload,
        ];
        
        $this->connection->send(json_encode($mess));
    }
    
    public function identify()
    {
        $payload = [
            'token' => $this->config['discord']['token'],
            'properties' => [
                '$os' => 'linux',
                '$browser' => 'disco',
                '$device' => 'disco'
            ],
            'large_threshold' => 50,
        ];
        $mess = [
            'op' => 2,
            'd' => $payload,
        ];
        
        $this->connection->send(json_encode($mess));
    }
    
    public function getLoop()
    {
        return $this->loop;
    }
    
    public function getConnector()
    {
        return $this->connector;
    }
    
    public function setConnection(WebSocket $connection)
    {
        $this->connection = $connection;
    }
    
    public function closeConnection()
    {
        $this->connection->close();
    }
    
    private function retrieveBaseUri()
    {
        $response = $this->httpClient->get('https://discordapp.com/api/gateway?v=6&encoding=json');
        $payload = $response->getBody()->getContents();
        
        return json_decode($payload, true)['url'];
    }
    
}
