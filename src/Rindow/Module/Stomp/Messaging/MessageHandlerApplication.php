<?php
namespace Rindow\Module\Stomp\Messaging;

use Rindow\Module\Stomp\Exception;

class MessageHandlerApplication
{
    protected $handler;
    protected $destinationResolver;
    protected $transactionBoundary;
    protected $timeout;
    protected $config;

    public function setHandler(/* MessageHandler */$handler)
    {
        $this->handler = $handler;
    }

    public function setDestinationResolver($destinationResolver)
    {
        $this->destinationResolver = $destinationResolver;
    }

    public function setTransactionBoundary($transactionBoundary)
    {
        $this->transactionBoundary = $transactionBoundary;
    }

    public function getDestinationResolver()
    {
        return $this->destinationResolver;
    }

    public function setConfig(array $config=null)
    {
        $this->config = $config;
    }

    public function run()
    {
        if(!$this->handler)
            throw new Exception\DomainException('The message handler is not specified.');
        if(!isset($this->config['source']))
            throw new Exception\DomainException('The source queue name is not specified.');
        $name = $this->config['source'];
        $channel = $this->destinationResolver
                                ->resolveDestination($name);
        $resourceManager = $channel->getResourceManager();
        $this->subscribe($resourceManager);
        if(isset($this->config['timeout']))
            $this->timeout = $this->config['timeout'];
        while($this->transactionBoundary->required(array($this,'transactional'))) {
            ;
        }
        return $resourceManager;
    }

    public function transactional()
    {
        $name = $this->config['source'];
        $channel = $this->destinationResolver
                                ->resolveDestination($name);
        $message = $channel->receive($this->timeout);
        if($message==null)
            return false;
        $this->handler->handleMessage($message);
        return true;
    }

    protected function subscribe($resourceManager)
    {
        if(!isset($this->config['subscribe']))
            return;
        $subscribes = $this->config['subscribe'];
        foreach($subscribes as $name) {
            $resourceManager->subscribe($name);
        }
    }
}
