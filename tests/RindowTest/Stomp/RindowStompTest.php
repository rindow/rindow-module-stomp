<?php
namespace RindowTest\Stomp\RindowStompTest;

use PHPUnit\Framework\TestCase;
use Rindow\Module\Stomp\Stomp;
use Rindow\Module\Stomp\Frame;
use Rindow\Module\Stomp\Exception;

class Test extends TestCase
{
    const TEST_WAIT_TIME = 100000;
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
        $stomp->subscribe('/queue/foo');
        while($message = $stomp->readFrame($readTimeout=1))
            $stomp->ack($message);
        $stomp->disconnect();
    }

    public function testSimple()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        Stomp::$debug=false;
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $stomp->disconnect();
    }

    public function testNormal()
    {
        $stomp = new Stomp();

        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $stomp->setReceiptMode(true);
        $this->assertEquals('1.2',$stomp->getProtocolVersion());
        $this->assertNotNull($stomp->getSessionId());
        $this->assertNotNull($stomp->getServer());
        $this->assertNotNull($stomp->getHeartBeat());
        $this->assertEquals('CONNECT',$frame->getCommand());
        $this->assertEquals(RINDOW_STOMP_USER,$frame->getHeader('login'));
        $this->assertEquals(RINDOW_STOMP_PASSWORD,$frame->getHeader('passcode'));
        $this->assertEquals('localhost',$frame->getHeader('host'));
        $this->assertEquals('1.0,1.1,1.2',$frame->getHeader('accept-version'));

        $frame = $stomp->begin('tx1');
        $this->assertEquals('BEGIN',$frame->getCommand());
        $this->assertEquals('tx1',$frame->getHeader('transaction'));

        $frame = $stomp->send('/queue/foo','hello');
        $this->assertEquals('SEND',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertEquals('text/plain',$frame->getHeader('content-type'));
        $this->assertEquals(strlen('hello'),$frame->getHeader('content-length'));
        $this->assertEquals('hello',$frame->getBody());

        $frame = $stomp->send('/queue/foo','world');
        $this->assertEquals('SEND',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertEquals('text/plain',$frame->getHeader('content-type'));
        $this->assertEquals(strlen('world'),$frame->getHeader('content-length'));
        $this->assertEquals('world',$frame->getBody());

        $frame = $stomp->send('/queue/foo','bar');
        $this->assertEquals('SEND',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertEquals('text/plain',$frame->getHeader('content-type'));
        $this->assertEquals(strlen('bar'),$frame->getHeader('content-length'));
        $this->assertEquals('bar',$frame->getBody());

        $frame = $stomp->commit('tx1');
        $this->assertEquals('COMMIT',$frame->getCommand());
        $this->assertEquals('tx1',$frame->getHeader('transaction'));

        $frame = $stomp->subscribe('/queue/foo');
        $this->assertEquals('SUBSCRIBE',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertEquals(1,$frame->getHeader('id'));
        $this->assertEquals('client',$frame->getHeader('ack'));

        $frame = $stomp->begin('tx2');
        $this->assertEquals('BEGIN',$frame->getCommand());
        $this->assertEquals('tx2',$frame->getHeader('transaction'));

        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $this->assertEquals('text/plain',$message->getHeader('content-type'));
        $this->assertEquals(strlen('hello'),$message->getHeader('content-length'));
        $this->assertNotNull($message->getHeader('ack'));
        $this->assertNotNull($message->getHeader('message-id'));
        $this->assertEquals('/queue/foo',$message->getHeader('destination'));
        $this->assertEquals(1,$message->getHeader('subscription'));
        $messageId = $message->getHeader('message-id');
        $ackId = $message->getHeader('ack');

        $frame = $stomp->ack($message,'tx2');
        $this->assertEquals($messageId,$frame->getHeader('message-id'));
        $this->assertEquals($ackId,$frame->getHeader('id'));
        $this->assertEquals('tx2',$frame->getHeader('transaction'));

        $message = $stomp->readFrame();
        $this->assertEquals('world',$message->getBody());
        $this->assertEquals('text/plain',$message->getHeader('content-type'));
        $this->assertEquals(strlen('world'),$message->getHeader('content-length'));
        $this->assertNotNull($message->getHeader('ack'));
        $this->assertNotNull($message->getHeader('message-id'));
        $this->assertEquals('/queue/foo',$message->getHeader('destination'));
        $this->assertEquals(1,$message->getHeader('subscription'));
        $messageId = $message->getHeader('message-id');
        $ackId = $message->getHeader('ack');

        $frame = $stomp->ack($message,'tx2');
        $this->assertEquals($messageId,$frame->getHeader('message-id'));
        $this->assertEquals($ackId,$frame->getHeader('id'));
        $this->assertEquals('tx2',$frame->getHeader('transaction'));

        $frame = $stomp->commit('tx2');
        $this->assertEquals('COMMIT',$frame->getCommand());
        $this->assertEquals('tx2',$frame->getHeader('transaction'));

        $frame = $stomp->unsubscribe('/queue/foo');
        $this->assertEquals('UNSUBSCRIBE',$frame->getCommand());
        $this->assertEquals(1,$frame->getHeader('id'));
        $this->assertNull($frame->getHeader('destination'));

        $frame = $stomp->disconnect();
        $this->assertEquals('DISCONNECT',$frame->getCommand());
    }

    public function testVersion1_0()
    {
        $stomp = new Stomp();
        $stomp->setAcceptVersion('1.0');
        $stomp->setReceiptMode(true);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $this->assertEquals('1.0',$stomp->getProtocolVersion());
        $this->assertNotNull($stomp->getSessionId());
        $this->assertNotNull($stomp->getServer());
        $this->assertNotNull($stomp->getHeartBeat());
        $this->assertEquals('CONNECT',$frame->getCommand());
        $this->assertEquals(RINDOW_STOMP_USER,$frame->getHeader('login'));
        $this->assertEquals(RINDOW_STOMP_PASSWORD,$frame->getHeader('passcode'));
        $this->assertNull($frame->getHeader('host'));
        $this->assertNull($frame->getHeader('accept-version'));

        $frame = $stomp->send('/queue/foo','hello');
        $this->assertEquals('SEND',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertNull($frame->getHeader('content-type'));
        $this->assertNull($frame->getHeader('content-length'));
        $this->assertEquals('hello',$frame->getBody());

        $frame = $stomp->subscribe('/queue/foo');
        $this->assertEquals('SUBSCRIBE',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertNull($frame->getHeader('id'));
        $this->assertEquals('client',$frame->getHeader('ack'));

        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $this->assertNull($message->getHeader('content-type'));
        $this->assertNull($message->getHeader('content-length'));
        $this->assertNull($message->getHeader('ack'));
        $this->assertNotNull($message->getHeader('message-id'));
        $this->assertEquals('/queue/foo',$message->getHeader('destination'));
        $this->assertNull($message->getHeader('subscription'));
        $messageId = $message->getHeader('message-id');

        $frame = $stomp->ack($message);
        $this->assertEquals($messageId,$frame->getHeader('message-id'));
        $this->assertNull($frame->getHeader('id'));

        $frame = $stomp->unsubscribe('/queue/foo');
        $this->assertEquals('UNSUBSCRIBE',$frame->getCommand());
        $this->assertNull($frame->getHeader('id'));
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $stomp->disconnect();
    }

    public function testVersion1_1()
    {
        $stomp = new Stomp();
        $stomp->setAcceptVersion('1.1');
        $stomp->setReceiptMode(true);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $this->assertEquals('1.1',$stomp->getProtocolVersion());
        $this->assertNotNull($stomp->getSessionId());
        $this->assertNotNull($stomp->getServer());
        $this->assertNotNull($stomp->getHeartBeat());
        $this->assertEquals('CONNECT',$frame->getCommand());
        $this->assertEquals(RINDOW_STOMP_USER,$frame->getHeader('login'));
        $this->assertEquals(RINDOW_STOMP_PASSWORD,$frame->getHeader('passcode'));
        $this->assertEquals('localhost',$frame->getHeader('host'));
        $this->assertEquals('1.1',$frame->getHeader('accept-version'));

        $frame = $stomp->send('/queue/foo','hello');
        $this->assertEquals('SEND',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertEquals('text/plain',$frame->getHeader('content-type'));
        $this->assertEquals(strlen('hello'),$frame->getHeader('content-length'));
        $this->assertEquals('hello',$frame->getBody());

        $frame = $stomp->subscribe('/queue/foo');
        $this->assertEquals('SUBSCRIBE',$frame->getCommand());
        $this->assertEquals('/queue/foo',$frame->getHeader('destination'));
        $this->assertEquals('1',$frame->getHeader('id'));
        $this->assertEquals('client',$frame->getHeader('ack'));

        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $this->assertEquals('text/plain',$message->getHeader('content-type'));
        $this->assertEquals(strlen('hello'),$message->getHeader('content-length'));
        $this->assertNull($message->getHeader('ack'));
        $this->assertNotNull($message->getHeader('message-id'));
        $this->assertEquals('/queue/foo',$message->getHeader('destination'));
        $this->assertEquals(1,$message->getHeader('subscription'));
        $messageId = $message->getHeader('message-id');

        $frame = $stomp->ack($message);
        $this->assertEquals($messageId,$frame->getHeader('message-id'));
        $this->assertNull($frame->getHeader('id'));

        $frame = $stomp->unsubscribe('/queue/foo');
        $this->assertEquals('UNSUBSCRIBE',$frame->getCommand());
        $this->assertEquals(1,$frame->getHeader('id'));
        $this->assertNull($frame->getHeader('destination'));
        $stomp->disconnect();
    }


    public function testBrokerURL()
    {
        $stomp = new Stomp();
        $this->assertEquals('localhost',$stomp->getHost());
        $this->assertEquals(61613,$stomp->getPort());
        //$stomp->disconnect();

        $stomp = new Stomp('tcp://foo:11111');
        $this->assertEquals('foo',$stomp->getHost());
        $this->assertEquals(11111,$stomp->getPort());

        $stomp->setBrokerURL('tcp://hogehoge:12345');
        $this->assertEquals('hogehoge',$stomp->getHost());
        $this->assertEquals(12345,$stomp->getPort());

        $stomp->setHost('localhost');
        $this->assertEquals('localhost',$stomp->getHost());
        $stomp->setPort(61613);
        $this->assertEquals(61613,$stomp->getPort());
        //$stomp->disconnect();
    }

    /**
     * @expectedException        Rindow\Module\Stomp\Exception\DomainException
     * @expectedExceptionMessage invalid brokerURL: invalidString
     */
    public function testInvalidBrokerURL()
    {
        $stomp = new Stomp();
        $stomp->setBrokerURL('invalidString');
    }

    public function testAckAutoMode()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->send('/queue/foo','disappear');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setAckMode(false);
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        usleep( self::TEST_WAIT_TIME );
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $stomp->disconnect();


        $stomp = new Stomp();
        $stomp->setAckMode(false);
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertFalse($message);
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setAckMode(false);
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertFalse($message);
        $stomp->disconnect();
    }

    public function testAckClientMode()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->send('/queue/foo','world');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('world',$message->getBody());
        $stomp->disconnect();
        unset($stomp);

        $stomp2 = new Stomp();
        $stomp2->setReadTimeout(0,500000);
        $frame = $stomp2->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp2->subscribe('/queue/foo');
        $message = $stomp2->readFrame();
        $this->assertEquals('hello',$message->getBody());
        $message2 = $stomp2->readFrame();
        $this->assertTrue(is_object($message2));
        $this->assertEquals('world',$message2->getBody());
        sleep(1);
        $frame = $stomp2->ack($message);
        $stomp2->disconnect();
        unset($stomp2);

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertEquals('world',$message->getBody());
        $frame = $stomp->ack($message);
        $stomp->disconnect();
        unset($stomp);
    }

    public function testNack()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        if(strpos($stomp->getServer(), 'apache-apollo')===false) {
            $this->markTestSkipped('needs the message queue service "apache-apollo"');
            return;
        }
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->send('/queue/foo','world');
        $frame = $stomp->send('/queue/foo','disappear');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $stomp->setAckMode('client-individual');
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $this->assertEquals('client-individual',$frame->getHeader('ack'));
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $frame = $stomp->nack($message);
        $this->assertEquals('NACK',$frame->getCommand());
        $message = $stomp->readFrame();
        $this->assertEquals('world',$message->getBody());
        $frame = $stomp->ack($message);
        $message = $stomp->readFrame();
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $stomp->setAckMode('client-individual');
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertEquals('hello',$message->getBody());
        $frame = $stomp->ack($message);
        $message = $stomp->readFrame();
        $this->assertFalse($message);
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $stomp->setAckMode(true);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('disappear',$message->getBody());
        $frame = $stomp->nack($message);
        $this->assertEquals('NACK',$frame->getCommand());
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $stomp->setAckMode(true);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertFalse($message);
        $stomp->disconnect();
    }

    public function testBrowserMode()
    {
        $stomp = new Stomp();
        $stomp->setBrowseMode(true);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        if(strpos($stomp->getServer(), 'ActiveMQ')===false &&
            strpos($stomp->getServer(), 'apache-apollo')===false) {
            $this->markTestSkipped();
            return;
        }

        $frame = $stomp->subscribe('/queue/foo');
        $this->assertEquals('true',$frame->getHeader('browser'));
        $this->assertNull($frame->getHeader('ack'));
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('end',$message->getHeader('browser'));
        $stomp->disconnect();

        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->send('/queue/foo','world');
        $frame = $stomp->send('/queue/foo','bar');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setBrowseMode(true);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $this->assertEquals('true',$frame->getHeader('browser'));
        $this->assertNull($frame->getHeader('ack'));

        $message = $stomp->readFrame();
        $this->assertNull($message->getHeader('browser'));
        $message = $stomp->readFrame();
        $this->assertNull($message->getHeader('browser'));
        $message = $stomp->readFrame();
        $this->assertNull($message->getHeader('browser'));
        $message = $stomp->readFrame();
        $this->assertEquals('end',$message->getHeader('browser'));
        $stomp->disconnect();
    }

    public function testSkipAckAllBeforeSpecifiedMessage()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->send('/queue/foo','world');
        $frame = $stomp->send('/queue/foo','boo');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        // Want to ack hello
        $hello = $stomp->readFrame();
        $this->assertTrue(is_object($hello));
        $this->assertEquals('hello',$hello->getBody());
        // Want to ack world
        $world = $stomp->readFrame();
        $this->assertTrue(is_object($world));
        $this->assertEquals('world',$world->getBody());
        // Want to skip boo
        $boo = $stomp->readFrame();
        $this->assertTrue(is_object($boo));
        $this->assertEquals('boo',$boo->getBody());
        // Do ack world only
        $frame = $stomp->ack($world);
        $stomp->disconnect();
        unset($stomp);

        // Responsed ack before world.
        $stomp2 = new Stomp();
        $stomp2->setReadTimeout(0,500000);
        $frame = $stomp2->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp2->subscribe('/queue/foo');
        $message = $stomp2->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('boo',$message->getBody());
        $stomp2->disconnect();
        unset($stomp2);
    }

    public function testRollbackSend()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $message = new Frame('SEND',null,'hello');
        $frame = $stomp->send('/queue/foo',$message);
        $frame = $stomp->begin('tx1');
        $message = new Frame('SEND',null,'world');
        $message->addHeader('transaction', 'tx1');
        $frame = $stomp->send('/queue/foo',$message);
        $frame = $stomp->abort('tx1');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $frame = $stomp->ack($message);
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $message = $stomp->readFrame();
        $this->assertFalse($message);
        $stomp->disconnect();
        unset($stomp);
    }

    public function testRollbackReadFrame()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->send('/queue/foo','hello');
        $frame = $stomp->send('/queue/foo','world');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $frame = $stomp->ack($message);
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $frame = $stomp->begin('tx1');
        $message = $stomp->readFrame();
        $this->assertEquals('world',$message->getBody());
        $frame = $stomp->ack($message,'tx1');
        $frame = $stomp->abort('tx1');
        $stomp->disconnect();
        unset($stomp);

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('world',$message->getBody());
        $frame = $stomp->ack($message);
        $stomp->disconnect();
        unset($stomp);
    }

    /*
    *  ActiveMQ has BUG in "client-individual" mode.
    *  It has been reported a memory leak problem.
    */
    public function testMultiTransaction()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->begin('tx1');
        $message = new Frame('SEND',null,'hello');
        $message->addHeader('transaction', 'tx1');
        $frame = $stomp->send('/queue/foo',$message);
        $frame = $stomp->begin('tx2');
        $message = new Frame('SEND',null,'abort');
        $message->addHeader('transaction', 'tx2');
        $frame = $stomp->send('/queue/foo',$message);
        $frame = $stomp->abort('tx2');
        $message = new Frame('SEND',null,'world');
        $message->addHeader('transaction', 'tx1');
        $frame = $stomp->send('/queue/foo',$message);
        $frame = $stomp->commit('tx1');
        $stomp->disconnect();

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $stomp->setAckMode('client-individual');//<= **** MUST BE "individual" mode ****
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $frame = $stomp->begin('tx1');
        $message = $stomp->readFrame();
        $frame = $stomp->ack($message,'tx1');
        $this->assertTrue(is_object($message));
        $this->assertEquals('hello',$message->getBody());
        $frame = $stomp->begin('tx2');
        $message = $stomp->readFrame();
        $this->assertEquals('world',$message->getBody());
        $frame = $stomp->ack($message,'tx2');
        $frame = $stomp->abort('tx2');
        $frame = $stomp->commit('tx1');
        $stomp->disconnect();
        unset($stomp);

        $stomp = new Stomp();
        $stomp->setReadTimeout(0,500000);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $frame = $stomp->subscribe('/queue/foo');
        $frame = $stomp->begin('tx1');
        $message = $stomp->readFrame();
        $this->assertTrue(is_object($message));
        $this->assertEquals('world',$message->getBody());
        $frame = $stomp->ack($message,'tx1');
        $frame = $stomp->commit('tx1');
        $message = $stomp->readFrame();
        $this->assertFalse($message);
        $stomp->disconnect();
        unset($stomp);
    }

    /**
     * @expectedException        Rindow\Module\Stomp\Exception\RuntimeException
     * @expectedExceptionMessage Could not connect to tcp://localhost:99999
     */
    public function testConnectionError()
    {
        $stomp = new Stomp();
        $stomp->setPort(99999);
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $stomp->disconnect();
    }

    /**
     * @expectedException        Rindow\Module\Stomp\Exception\RuntimeException
     * @expectedExceptionMessage Error:
     */
    public function testProtocolError()
    {
        $stomp = new Stomp();
        $frame = $stomp->connect(RINDOW_STOMP_USER,RINDOW_STOMP_PASSWORD);
        $stomp->commit('none',null,$sync=true);
        $stomp->disconnect();
    }
}