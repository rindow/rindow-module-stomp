<?php
namespace RindowTest\Stomp\Messaging\DestinationResolverWithActiveMQTest;

use PHPUnit\Framework\TestCase;
use Rindow\Messaging\Support\MessageFactory;
use Rindow\Messaging\Core\GenericMessagingTemplate;
use Rindow\Transaction\Local\TransactionManager;
use Rindow\Module\Stomp\Messaging\DestinationResolver;
use Rindow\Module\Stomp\Messaging\ResourceManager;
use Rindow\Module\Stomp\Stomp;
use Rindow\Module\Stomp\Frame;
use Rindow\Module\Stomp\Exception;

class Test extends TestCase
{
    public static $skip = false;
    public static function setUpBeforeClass()
    {
        $stomp = new Stomp();
        try {
            $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        } catch(Exception\RuntimeException $e) {
            self::$skip = true;
        }
    }

    public function setUp()
    {
        if(self::$skip) {
            $this->markTestSkipped();
            return;         
        }
        $stomp = new Stomp();
        $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $stomp->subscribe('/queue/fooDest');
        $stomp->subscribe('/queue/fooDest2');
        while($message = $stomp->readFrame($readTimeout=1))
            $stomp->ack($message);
    }

    public function testSendAndReceiveAndBeginAndCommitStandalone()
    {
        //Stomp::$debug = true;
        $tx = new TransactionManager();
        $messageFactory = new MessageFactory();
        $destinationResolver = new DestinationResolver(
            $messageFactory,$tx);

        $tx2 = new TransactionManager();
        $messageFactory2 = new MessageFactory();
        $destinationResolver2 = new DestinationResolver(
            $messageFactory2,$tx2);


        $tx->begin();
        $name = '/queue/fooDest';
        $channel = $destinationResolver->resolveDestination($name);
        $message = $messageFactory->createMessage();
        $message->setPayload('Msg1');
        $channel->send($message);
        $tx->commit();

        $tx2->begin();
        $name = '/queue/fooDest';
        $channel2 = $destinationResolver2->resolveDestination($name);
        $message2 = $channel2->receive(10);
        $this->assertEquals('Msg1',$message2->getPayload());

        $name = '/queue/fooDest2';
        $channel3 = $destinationResolver2->resolveDestination($name);
        $message3 = $messageFactory2->createMessage();
        $message3->setPayload('Msg2');
        $channel3->send($message3);
        $tx2->commit();

        $tx->begin();
        $name = '/queue/fooDest2';
        $channel = $destinationResolver->resolveDestination($name);
        $message = $channel->receive(10);
        $this->assertEquals('Msg2',$message->getPayload());
        $tx->commit();
    }
}
