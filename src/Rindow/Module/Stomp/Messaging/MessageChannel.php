<?php
namespace Rindow\Module\Stomp\Messaging;

use Interop\Lenient\Messaging\MessageChannel as MessageChannelInterface;

class MessageChannel implements MessageChannelInterface
{
    protected $resourceManager;
    protected $destination;

    public function __construct($resourceManager,$destination)
    {
        $this->resourceManager = $resourceManager;
        $this->destination = $destination;
    }

    public function getResourceManager()
    {
        return $this->resourceManager;
    }

    public function send(/*Message*/$message,$timeout=null)
    {
        $this->resourceManager->doSend($this->destination,$message,$timeout);
    }

    public function receive($timeout=null)
    {
        $this->resourceManager->subscribe($this->destination);
        return $this->resourceManager->doReceive($timeout);
    }

    public function close()
    {
        $this->resourceManager = null;
        $this->destination = null;
    }
}
