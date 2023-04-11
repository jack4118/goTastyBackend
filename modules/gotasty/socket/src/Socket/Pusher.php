<?php
namespace Socket;
use Ratchet\ConnectionInterface;

use Ratchet\MessageComponentInterface;

class Pusher implements MessageComponentInterface {
    protected $clients;
    protected $subscribedTopics = array();

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        echo date("Y-m-d H:i:s")." New connection! ({$conn->resourceId})\n";
        echo date("Y-m-d H:i:s")." Connection count: ".(count($this->clients))." Subscribe Connection: ".(count($this->subscribedTopics))."\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $numRecv = count($this->clients) - 1;
        $subTag = explode("_", $msg);

        if(trim($subTag[0]) == "sub"){
            $this->subscribedTopics[$from->resourceId][trim($subTag[1])] = $subTag[1];
            echo date("Y-m-d H:i:s")." Subscribe {$subTag[1]} connection! ({$from->resourceId})\n";
        }else{
            unset($this->subscribedTopics[$from->resourceId][trim($subTag[1])]);
            echo date("Y-m-d H:i:s")." Unsubscribe {$subTag[1]} connection! ({$from->resourceId})\n";
        }

        echo date("Y-m-d H:i:s")." ({$from->resourceId}) Channel Count: ".(count($this->subscribedTopics[$from->resourceId]))."\n";

    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        unset($this->subscribedTopics[$conn->resourceId]);
        $this->clients->detach($conn);
        echo date("Y-m-d H:i:s")." Connection {$conn->resourceId} has disconnected\n";
        echo date("Y-m-d H:i:s")." Connection count: ".(count($this->clients))." Subscribe Connection: ".(count($this->subscribedTopics))."\n";

    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo date("Y-m-d H:i:s")." An error has occurred: {$e->getMessage()}\n";
        unset($this->subscribedTopics[$conn->resourceId]);
        $this->clients->detach($conn);
        $conn->close();
    }

    public function onBroadcast($entry) {
        $entryData = json_decode($entry, true);
        foreach ($this->clients as $client) {
            if($this->subscribedTopics[$client->resourceId][$entryData["category"]]){
                echo date("Y-m-d H:i:s")." Send ({$client->resourceId}) : ".$entry."\n";
                $client->send($entry);
            }
        }
    }
}