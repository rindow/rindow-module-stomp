<?php
namespace Rindow\Module\Stomp\Messaging;

use Interop\Lenient\Messaging\Core\DestinationResolver as DestinationResolverInterface;

class DestinationResolver implements DestinationResolverInterface
{
    protected $transactionManager;
    protected $config;
    protected $resourceManager;

    public function __construct(
        $messageFactory=null,
        $transactionManager=null,
        array $config=null)
    {
        if($messageFactory)
            $this->setMessageFactory($messageFactory);
        if($transactionManager)
            $this->setTransactionManager($transactionManager);
        if($config)
            $this->setConfig($config);
    }

    public function setMessageFactory($messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    public function setTransactionManager($transactionManager)
    {
        $this->transactionManager = $transactionManager;
    }

    public function setConfig(array $config=null)
    {
        $this->config = $config;
    }

    public function getTransactionId()
    {
        if($this->resourceManager==null)
            return null;
        return $this->resourceManager->getTransactionId();
    }

    protected function createResourceManager($messageFactory,$config)
    {
        return new ResourceManager($messageFactory,$config);
    }

    public function getResourceManager()
    {
        if($this->resourceManager)
            return $this->resourceManager;
        $this->resourceManager = $this->createResourceManager($this->messageFactory,$this->config);
        return $this->resourceManager;
    }

    public function resolveDestination($name)
    {
        if($this->transactionManager) {
            $transaction = $this->transactionManager->getTransaction();
            if($transaction) {
                $transaction->enlistResource($this->getResourceManager());
            }
        }
        $messageChannel = new MessageChannel($this->getResourceManager(),$name);
        return $messageChannel;
    }
}
