<?php
namespace RindowTest\Stomp\Messaging\DestinationResolverTest;

use PHPUnit\Framework\TestCase;
use Rindow\Messaging\Support\MessageFactory;
use Rindow\Messaging\Core\GenericMessagingTemplate;
use Rindow\Transaction\Local\TransactionManager;
use Rindow\Module\Stomp\Messaging\DestinationResolver;
use Rindow\Module\Stomp\Messaging\ResourceManager;
use Rindow\Module\Stomp\Stomp;
use Rindow\Module\Stomp\Frame;
use Rindow\Container\ModuleManager;

class TestLogger
{
    public $logdata = array();
    public function log($message)
    {
        $this->logdata[] = $message;
    }
}

class TestDestinationResolver extends DestinationResolver
{
    public $logger;
    protected function createResourceManager($messageFactory,$config)
    {
        $resource = new TestResourceManager($messageFactory,$config);
        $resource->logger = $this->logger;
        return $resource;
    }
/*
    public function getLog()
    {
        if($this->resourceManager==null)
            return array();
        return $this->resourceManager->getLog();
    }
*/
}

class TestResourceManager extends ResourceManager
{
    public $logger;

    protected function createStomp()
    {
        $stomp = new TestStomp();
        $stomp->logger = $this->logger;
        return $stomp;
    }
/*
    public function getLog()
    {
        if($this->queueDriver==null)
            return array();
        return $this->queueDriver->getLog();
    }
*/
}

class TestStomp extends Stomp
{
    //public $log = array();
    public $logger;
/*
    public function getLog()
    {
        return $this->log;
    }
    public function setConnectedEventListener($listener)
    {
        $this->log[] = 'setConnectedEventListener';
        parent::setConnectedEventListener($listener);
    }
*/

    public function connect($username=null, $password=null, array $properties=null)
    {
        if($this->socket)
            throw new \Exception('already connected.');
        $this->socket = true;
        if($this->host!='localhost')
            $this->logger->log('host:'.$this->host);
        if($this->port!='61613')
            $this->logger->log('port:'.$this->port);
        if($this->username)
            $this->logger->log('username:'.$this->username);
        if($this->password)
            $this->logger->log('password:'.$this->password);
        if($this->readTimeoutSec!=10)
            $this->logger->log('timeout:'.$this->readTimeoutSec);
        $frame = new Frame('CONNECT');
        //$frame->addHeader('login', $username);
        //$frame->addHeader('passcode', $password);
        $this->writePhyscalFrame($frame);
        //if($this->connectionEventListener)
        //    call_user_func($this->connectionEventListener,$this);
    }
    public function disconnect(array $properties=null, $sync=null)
    {
        $this->socket = null;
    }
    protected function writePhyscalFrame(Frame $frame)
    {
        $this->assertConnect();
        $this->logger->log($frame->getCommand().':'.$frame->getHeader('transaction').':'.$frame->getHeader('destination').':'.$frame->getBody());
    }
    protected function hasPhyscalFrameToRead($readTimeout=null)
    {
        $this->assertConnect();
        return true;
    }
    protected function readPhyscalFrame($readTimeout=null,$newFrame=null)
    {
        $this->logger->log('MESSAGE:::BODYFoo');
        return new Frame('MESSAGE',null,'BODYFoo');
    }
}

class Test extends TestCase
{
    public function setUp()
    {
    }

    public function testSendAndBeginAndCommit()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');

        $logger->log('[begin transaction]');
        $tx->begin();

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[send message]');
        $channel->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(4,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        //$this->assertEquals('CONNECT:::',$log[1]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[3]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[4]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[resolve destination]',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
                '[commit transaction]',
                'COMMIT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveAndBeginAndCommitStandalone()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $logger->log('[begin transaction]');
        $tx->begin();

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[receive message]');
        $message = $channel->receive();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);

        //$this->assertEquals('setConnectedEventListener',$log[0]);
        //$this->assertEquals('CONNECT:::',$log[1]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[3]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[4]);

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');
        $logger->log('[send message]');
        $channel->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[5]);
        $logger->log('[commit transaction]');
        $tx->commit();

        //$log = $destinationResolver->getLog();
        //$this->assertCount(8,$log);
        //$this->assertEquals('ACK:'.$txId.'::',$log[6]);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[7]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[resolve destination]',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                '[receive message]',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
                '[commit transaction]',
                'ACK:'.$txId.'::',
                'COMMIT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndBeginAndRollback()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');

        $logger->log('[begin transaction]');
        $tx->begin();

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[send message]');
        $channel->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(4,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        //$this->assertEquals('CONNECT:::',$log[1]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[3]);

        $logger->log('[rollback transaction]');
        $tx->rollback();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('ABORT:'.$txId.'::',$log[4]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[resolve destination]',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
                '[rollback transaction]',
                'ABORT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveAndBeginAndRollback()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $logger->log('[begin transaction]');
        $tx->begin();
        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[receive message]');
        $message = $channel->receive();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        //$this->assertEquals('CONNECT:::',$log[1]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[3]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[4]);

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');
        $logger->log('[send message]');
        $channel->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[5]);

        $logger->log('[rollback transaction]');
        $tx->rollback();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(7,$log);
        //$this->assertEquals('ABORT:'.$txId.'::',$log[6]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[resolve destination]',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                '[receive message]',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
                '[rollback transaction]',
                'ABORT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendWithoutTransaction()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[send message]');
        $channel->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(2,$log);
        //$this->assertEquals('CONNECT:::',$log[0]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNull($txId);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[1]);

        $this->assertEquals(array(
                '[resolve destination]',
                '[send message]',
                'CONNECT:::',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
            ),
            $logger->logdata
        );
    }


    public function testSendAndReceiveWithoutTransaction()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[receive message]');
        $message = $channel->receive();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(4,$log);
        //$this->assertEquals('CONNECT:::',$log[0]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNull($txId);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[1]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[2]);
        //$this->assertEquals('ACK:::',$log[3]);

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');
        $logger->log('[send message]');
        $channel->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('SEND::/queue/fooDest:barMsg',$log[4]);

        $this->assertEquals(array(
                '[resolve destination]',
                '[receive message]',
                'CONNECT:::',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                'ACK:'.$txId.'::',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveOtherDestinationAndBeginAndCommit()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $logger->log('[begin transaction]');
        $tx->begin();

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel1 = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(0,$log);

        $logger->log('[receive message]');
        $message = $channel1->receive();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        //$this->assertEquals('CONNECT:::',$log[1]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[3]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[4]);

        $name = '/queue/fooDest2';
        $logger->log('[resolve destination]');
        $channel2 = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');
        $logger->log('[send message]');
        $channel2->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest2:barMsg',$log[5]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(8,$log);
        //$this->assertEquals('ACK:'.$txId.'::',$log[6]);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[7]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[resolve destination]',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                '[receive message]',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[resolve destination]',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest2:barMsg',
                '[commit transaction]',
                'ACK:'.$txId.'::',
                'COMMIT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveAndBeginAndCommitWithSuspendTransaction()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $name = '/queue/fooDest0';
        $logger->log('[resolve destination]');
        $channel0 = $destinationResolver->resolveDestination($name);

        $message = $messageFactory->createMessage();
        $message->setPayload('trigger');
        $logger->log('[send message]');
        $channel0->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(2,$log);
        //$this->assertEquals('CONNECT:::',$log[0]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNull($txId);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest0:trigger',$log[1]);

        $logger->log('[begin transaction]');
        $tx->begin();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(2,$log);

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel1 = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(4,$log);
        //$this->assertEquals('setConnectedEventListener',$log[2]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[3]);

        $logger->log('[receive message]');
        $message = $channel1->receive();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[4]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[5]);

        $name = '/queue/fooDest2';
        $logger->log('[resolve destination]');
        $channel2 = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');
        $logger->log('[send message]');
        $channel2->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(7,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest2:barMsg',$log[6]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(9,$log);
        //$this->assertEquals('ACK:'.$txId.'::',$log[7]);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[8]);

        $this->assertEquals(array(
                '[resolve destination]',
                '[send message]',
                'CONNECT:::',
                'SEND::/queue/fooDest0:trigger',
                '[begin transaction]',
                '[resolve destination]',
                'BEGIN:'.$txId.'::',
                '[receive message]',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[resolve destination]',
                '[send message]',
                'SEND:'.$txId.':/queue/fooDest2:barMsg',
                '[commit transaction]',
                'ACK:'.$txId.'::',
                'COMMIT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveAndRecycleConnection()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;

        $logger->log('[begin transaction]');
        $tx->begin();

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel0 = $destinationResolver->resolveDestination($name);

        $message = $messageFactory->createMessage();
        $message->setPayload('trigger');
        $logger->log('[send message]');
        $channel0->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(4,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        //$this->assertEquals('CONNECT:::',$log[1]);
        $txId1 = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId1);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:trigger',$log[3]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[4]);

        $logger->log('[begin transaction]');
        $tx->begin();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);
        $txId2 = $destinationResolver->getTransactionId();
        $this->assertNull($txId2);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[5]);

        $name = '/queue/fooDest';
        $logger->log('[resolve destination]');
        $channel1 = $destinationResolver->resolveDestination($name);
        $txId2 = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId2);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);

        $logger->log('[receive message]');
        $message = $channel1->receive();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(8,$log);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[6]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[7]);

        $name = '/queue/fooDest2';
        $logger->log('[resolve destination]');
        $channel2 = $destinationResolver->resolveDestination($name);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(8,$log);

        $message = $messageFactory->createMessage();
        $message->setPayload('barMsg');
        $logger->log('[send message]');
        $channel0->send($message);
        $logger->log('[send message]');
        $channel2->send($message);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(9,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest2:barMsg',$log[8]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(11,$log);
        //$this->assertEquals('ACK:'.$txId.'::',$log[9]);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[10]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[resolve destination]',
                'CONNECT:::',
                'BEGIN:'.$txId1.'::',
                '[send message]',
                'SEND:'.$txId1.':/queue/fooDest:trigger',
                '[commit transaction]',
                'COMMIT:'.$txId1.'::',
                '[begin transaction]',
                '[resolve destination]',
                'BEGIN:'.$txId2.'::',
                '[receive message]',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[resolve destination]',
                '[send message]',
                'SEND:'.$txId2.':/queue/fooDest:barMsg',
                '[send message]',
                'SEND:'.$txId2.':/queue/fooDest2:barMsg',
                '[commit transaction]',
                'ACK:'.$txId2.'::',
                'COMMIT:'.$txId2.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveWithTemplate()
    {
        $logger = new TestLogger();
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new TestDestinationResolver(
            $messageFactory,$tx);
        $destinationResolver->logger = $logger;
        $template = new GenericMessagingTemplate($destinationResolver);

        $logger->log('[begin transaction]');
        $tx->begin();

        $name = '/queue/fooDest';
        $logger->log('[receive and convert]');
        $msg = $template->receiveAndConvert($name);
        //$this->assertEquals('BODYFoo',$msg);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(5,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('CONNECT:::',$log[1]);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[2]);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[3]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[4]);

        $logger->log('[convert and send]');
        $template->convertAndSend($name,'barMsg');
        //$log = $destinationResolver->getLog();
        //$this->assertCount(6,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[5]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(8,$log);
        //$this->assertEquals('ACK:'.$txId.'::',$log[6]);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[7]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[receive and convert]',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[convert and send]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
                '[commit transaction]',
                'ACK:'.$txId.'::',
                'COMMIT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }

    public function testSendAndReceiveOnModuleManager()
    {
        $config = array(
            'module_manager' => array(
                'modules' => array(
                    'Rindow\Module\Stomp\Module' => true,
                    //'Rindow\Module\Stomp\Module' => true,
                ),
                'enableCache' => false,
            ),
            'container' => array(
                'components' => array(
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultDestinationResolver' => array(
                        'class' => 'RindowTest\\Stomp\\Messaging\\DestinationResolverTest\\TestDestinationResolver',
                        'properties' => array(
                            'config' => array('config'=>'stomp::connections::default'),
                        ),
                    ),
                ),
            ),

            'stomp' => array(
                'connections' => array(
                    'default' => array(
                        'brokerURL' => 'tcp://foobar:12345/',
                        'timeout' => 100,
                        'username' => 'user',
                        'password' => 'pass',
                    ),
                ),
            ),
        );
        $logger = new TestLogger();
        $mm = new ModuleManager($config);
        $destinationResolver = $mm->getServiceLocator()->get('Rindow\\Module\\Stomp\\Messaging\\DefaultDestinationResolver');
        $destinationResolver->logger = $logger;
        $template = new GenericMessagingTemplate($destinationResolver);
        $tx = $mm->getServiceLocator()->get('Rindow\\Module\\Stomp\\Messaging\\DefaultTransactionManager');

        $logger->log('[begin transaction]');
        $tx->begin();
        $name = '/queue/fooDest';

        $logger->log('[receive and convert]');
        $msg = $template->receiveAndConvert($name);
        $this->assertEquals('BODYFoo',$msg);
        //$log = $destinationResolver->getLog();
        //$this->assertCount(10,$log);
        //$this->assertEquals('setConnectedEventListener',$log[0]);
        $txId = $destinationResolver->getTransactionId();
        $this->assertNotNull($txId);
        //$this->assertEquals('host:foobar',$log[1]);
        //$this->assertEquals('port:12345',$log[2]);
        //$this->assertEquals('username:user',$log[3]);
        //$this->assertEquals('password:pass',$log[4]);
        //$this->assertEquals('timeout:100',$log[5]);
        //$this->assertEquals('CONNECT:::',$log[6]);
        //$this->assertEquals('BEGIN:'.$txId.'::',$log[7]);
        //$this->assertEquals('SUBSCRIBE::/queue/fooDest:',$log[8]);
        //$this->assertEquals('MESSAGE:::BODYFoo',$log[9]);

        $logger->log('[convert and send]');
        $template->convertAndSend($name,'barMsg');
        //$log = $destinationResolver->getLog();
        //$this->assertCount(11,$log);
        //$this->assertEquals('SEND:'.$txId.':/queue/fooDest:barMsg',$log[10]);

        $logger->log('[commit transaction]');
        $tx->commit();
        //$log = $destinationResolver->getLog();
        //$this->assertCount(13,$log);
        //$this->assertEquals('ACK:'.$txId.'::',$log[11]);
        //$this->assertEquals('COMMIT:'.$txId.'::',$log[12]);

        $this->assertEquals(array(
                '[begin transaction]',
                '[receive and convert]',
                'host:foobar',
                'port:12345',
                'username:user',
                'password:pass',
                'timeout:100',
                'CONNECT:::',
                'BEGIN:'.$txId.'::',
                'SUBSCRIBE::/queue/fooDest:',
                'MESSAGE:::BODYFoo',
                '[convert and send]',
                'SEND:'.$txId.':/queue/fooDest:barMsg',
                '[commit transaction]',
                'ACK:'.$txId.'::',
                'COMMIT:'.$txId.'::',
            ),
            $logger->logdata
        );
    }
}
