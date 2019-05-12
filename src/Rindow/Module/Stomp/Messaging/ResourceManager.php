<?php
namespace Rindow\Module\Stomp\Messaging;

use Rindow\Module\Stomp\Exception;
use Rindow\Module\Stomp\Stomp;
use Interop\Lenient\Transaction\ResourceManager as ResourceManagerInterface;

class ResourceManager implements ResourceManagerInterface
{
    protected $queueDriver;
    protected $messageFactory;
    //protected $listener;
    protected $messages = array();
    protected $transactionId;
    protected $subscribes = array();
    protected $autoAck = true;
    protected $ackMode = true;
    //protected $fixedListener = false;
    protected $lastFrame;
    protected $timeout;
    protected $config = array();
    protected $name;

    public function __construct(
        $messageFactory,$config=null)
    {
        $this->setMessageFactory($messageFactory);
        if($config)
            $this->setConfig($config);
    }

    public function setQueueDriver($queueDriver)
    {
        $this->queueDriver = $queueDriver;
    }

    public function setMessageFactory($messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }
/*
    public function setConnectedEventListener($listener)
    {
        $this->listener = $listener;
    }

    public function isConnected()
    {
        if($this->queueDriver==null)
            return false;
        return $this->getQueueDriver()->isConnected();
    }
*/

    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
    }

    public function setConfig($config)
    {
        $this->config = $config;
        if(isset($config['timeout']))
            $this->setTimeout($config['timeout']);
    }

    protected function getQueueDriver()
    {
        if($this->queueDriver==null) {
            $this->queueDriver=$this->createStomp();
            $this->queueDriver->setAckMode($this->ackMode);
            $this->queueDriver->setAutoConnect(true);
            if($this->timeout!==null)
                $this->queueDriver->setReadTimeout($this->timeout);
            if(isset($this->config['brokerURL']))
                $this->queueDriver->setbrokerURL($this->config['brokerURL']);
            if(isset($this->config['host']))
                $this->queueDriver->setHost($this->config['host']);
            if(isset($this->config['port']))
                $this->queueDriver->setPort($this->config['port']);
            if(isset($this->config['scheme']))
                $this->queueDriver->setScheme($this->config['scheme']);
            if(isset($this->config['readTimeoutSecond']))
                $this->queueDriver->setReadTimeoutSecond($this->config['readTimeoutSecond']);
            if(isset($this->config['readTimeoutMicroSecond']))
                $this->queueDriver->setReadTimeoutMicroSecond($this->config['readTimeoutMicroSecond']);
            if(isset($this->config['username']))
                $this->queueDriver->setUsername($this->config['username']);
            if(isset($this->config['password']))
                $this->queueDriver->setPassword($this->config['password']);
        }
        //if($this->listener && !$this->fixedListener) {
        //    $this->queueDriver->setConnectedEventListener($this->listener);
        //    $this->fixedListener = true;
        //}
        return $this->queueDriver;
    }

    protected function createStomp()
    {
        return new Stomp();
    }

    public function isNestedTransactionAllowed()
    {
        return false;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function beginTransaction($definition=null)
    {
        if($this->transactionId)
            throw new Exception\DomainException('Nested transaction is not supported.');
        /* CAUTION: Don't change the position of this sentence for infinity loop */
        $this->transactionId = sha1(spl_object_hash($this).uniqid(rand(), true));
        $this->getQueueDriver()->begin($this->transactionId);
    }

    public function commit()
    {
        if(!$this->transactionId)
            throw new Exception\DomainException('the session is not in transaction.');
        $this->doCommit();
    }

    public function rollback()
    {
        if(!$this->transactionId)
            throw new Exception\DomainException('the session is not in transaction.');
        $this->doRollback();
    }
/*
    public function createSavepoint($savepoint=null)
    {
        throw new Exception\DomainException('savepoint is not supported.');
    }

    public function releaseSavepoint($savepoint)
    {
        throw new Exception\DomainException('savepoint is not supported.');
    }

    public function rollbackSavepoint($savepoint)
    {
        throw new Exception\DomainException('savepoint is not supported.');
    }
*/
    public function suspend()
    {
        throw new Exception\DomainException('suspend operation is not supported.');
    }

    public function resume($txObject)
    {
        throw new Exception\DomainException('resume operation is not supported.');
    }

    public function doSend($destination,$message,$timeout)
    {
        $stomp = $this->getQueueDriver();
        if(!$stomp->isConnected())
            $stomp->connect(); /* To start a transaction by the transaction manager */
        $headers = $message->getHeaders();
        if($this->transactionId)
            $headers['transaction'] = $this->transactionId;
        $stomp->send($destination, $message->getPayload(), $headers, $sync = null);
    }

    public function subscribe($destination)
    {
        if(isset($this->subscribes[$destination]))
            return;
        $this->getQueueDriver()->subscribe($destination);
    }

    public function doReceive($timeout)
    {
        $frame = $this->getQueueDriver()->readFrame($timeout);
        if(!$frame)
            return null;
        $message = $this->messageFactory->createMessage($frame->getBody(),$frame->getHeaders());
        $this->lastFrame = $frame;
        if($this->transactionId==null)
            if($this->autoAck) {
                $this->getQueueDriver()->ack($frame);
        }
        return $message;
    }

    public function doCommit()
    {
        if($this->autoAck && $this->lastFrame)
            $this->getQueueDriver()->ack($this->lastFrame,$this->transactionId);
        $this->getQueueDriver()->commit($this->transactionId);
        $this->lastFrame = null;
        $this->transactionId = null;
    }

    public function doRollback()
    {
        $this->getQueueDriver()->abort($this->transactionId);
        $this->lastFrame = null;
        $this->transactionId = null;
    }

    public function close()
    {
        if($this->queueDriver==null)
            return;
        $this->queueDriver->disconnect();
        //$this->queueDriver->setConnectedEventListener(null);
        $this->queueDriver = null;
    }
}
