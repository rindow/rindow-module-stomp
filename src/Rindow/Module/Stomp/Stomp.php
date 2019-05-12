<?php
namespace Rindow\Module\Stomp;

use Rindow\Module\Stomp\Exception;

class Stomp
{
    public static $debug;

    protected $scheme = 'tcp';
    protected $host = 'localhost';
    protected $port = 61613;
    protected $username = '';
    protected $password = '';
    protected $socket;
    protected $readTimeoutSec = 10;
    protected $readTimeoutMicroSec = 0;
    protected $acceptVersion = '1.0,1.1,1.2';
    protected $session;
    protected $heartBeat;
    protected $server;
    protected $protocolVersion;
    protected $encodedHeader = false;
    protected $receiptMode = false;
    protected $ackMode = true;
    protected $receiptId = 1;
    protected $subscriptionId = 1;
    protected $stockMessages = array();
    protected $browseMode;
    protected $inDestructor = false;
    protected $autoConnect = false;
    protected $connectionEventListener;

    public function __construct($brokerURL=null)
    {
        if($brokerURL)
            $this->setBrokerURL($brokerURL);
    }

    public function __destruct()
    {
        $this->inDestructor = true;
        if($this->socket)
            $this->disconnect();
    }

    public function setBrokerURL($brokerURL)
    {
        $parts = parse_url($brokerURL);
        if(!isset($parts['host']) || !isset($parts['port']))
            throw new Exception\DomainException('invalid brokerURL: '.$brokerURL);
        $this->host = $parts['host'];
        $this->port = $parts['port'];
    }

    public function setScheme($scheme)
    {
        $this->scheme = $scheme;
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setReadTimeout($seconds , $microseconds=0)
    {
        $this->readTimeoutSec = $seconds;
        $this->readTimeoutMicroSec = $microseconds;
    }

    public function setReadTimeoutSecond($readTimeoutSecond)
    {
        $this->readTimeoutSec = $readTimeoutSecond;
    }

    public function setReadTimeoutMicroSecond($readTimeoutMicroSecond)
    {
        $this->readTimeoutMicroSec = $readTimeoutMicroSecond;
    }

    public function setAcceptVersion($acceptVersion)
    {
        $this->acceptVersion = $acceptVersion;
    }

    public function setReceiptMode($mode=false)
    {
        $this->receiptMode = $mode;
    }

    public function getReceiptMode()
    {
        return $this->receiptMode;
    }

    public function setAckMode($mode=true)
    {
        if($mode!==true && $mode!==false && $mode!=='auto' &&
            $mode!=='client' && $mode!=='client-individual')
            throw new Exception\DomainException('invalid mode type:'.$mode);
        $this->ackMode = $mode;
    }

    public function getAckMode()
    {
        return $this->ackMode;
    }

    public function setBrowseMode($mode=false)
    {
        $this->browseMode = $mode;
    }

    public function getBrowseMode()
    {
        return $this->browseMode;
    }

    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    public function getSessionId()
    {
        return $this->session;
    }

    public function getHeartBeat()
    {
        return $this->heartBeat;
    }

    public function getServer()
    {
        return $this->server;
    }

    public function setConnectedEventListener($connectionEventListener)
    {
        $this->connectionEventListener = $connectionEventListener;
    }

    public function setAutoConnect($autoConnect)
    {
        $this->autoConnect = $autoConnect;
    }

    public function isConnected()
    {
        return $this->socket !== null;
    }

    protected function assertConnect()
    {
        if($this->socket)
            return;
        if(!$this->autoConnect)
            throw new Exception\DomainException("not connected.");
        $this->connect();
    }

    public function connect($username=null, $password=null, array $properties=null)
    {
        if($this->socket)
            throw new Exception\DomainException('already connected.');

        $brokerURL = "{$this->scheme}://{$this->host}:{$this->port}";
        if (false === $this->socket = @stream_socket_client($brokerURL, $errno, $errstr)) {
            throw new Exception\RuntimeException('Could not connect to '.$brokerURL.': '.$errstr);
        }
        if($username===null)
            $username = $this->username;
        if($password===null)
            $password = $this->password;
        $frame = new Frame('CONNECT');
        $frame->addHeader('login', $username);
        $frame->addHeader('passcode', $password);
        if($this->acceptVersion!='1.0') {
            $frame->addHeader('host', $this->host);
            $frame->addHeader('accept-version', $this->acceptVersion);
        }
        if($properties)
            $frame->addHeaders($properties);
        $this->writePhyscalFrame($frame);

        $response = $this->readPhyscalFrame();
        if(!$response)
            throw new Exception\DomainException('Connection failed');
        if($response->getCommand()!=='CONNECTED')
            throw new Exception\DomainException($response->getBody());

        $this->protocolVersion = $response->getHeader('version');
        if($this->protocolVersion >= '1.1')
            $this->encodedHeader = true;
        $this->session   = $response->getHeader('session');
        $this->server    = $response->getHeader('server');
        $this->heartBeat = $response->getHeader('heart-beat');
        if($this->connectionEventListener)
            call_user_func($this->connectionEventListener,$this);
        return $frame;
    }

    public function send($destination, $msg, array $properties=null, $sync = null)
    {
if(is_string($msg))
    $str = $msg;
else
    $str = $msg->getBody();
if(self::$debug)
    fputs(STDERR,"send({$destination},{$str})");

        if(is_string($msg)) {
            $frame = new Frame('SEND',null,$msg);
        } else if($msg instanceof Frame) {
            $frame = $msg;
        } else {
            throw Exception\DomainException('message type must be string or "Frame".');
        }
        if($destination) {
            $frame->addHeader('destination',$destination);
        } else {
            if($frame->getHeader('destination')===null)
                throw new Exception\DomainException('destination not specifed.');
        }
        if($properties)
            $frame->addHeaders($properties);
        if($this->protocolVersion >= '1.1') {
            if(!$frame->getHeader('content-type'))
                $frame->addHeader('content-type', 'text/plain');
            if(!$frame->getHeader('content-length'))
                $frame->addHeader('content-length', strlen($frame->getBody()));
        }
        $this->writeFrame($frame,$sync);
        return $frame;
    }

    public function subscribe($destination, array $properties=null, $sync=null)
    {
if(self::$debug)
    fputs(STDERR,"subscribe({$destination})");

        if(isset($this->subscribes[$destination])) {
            $subscriptionId = $this->subscribes[$destination];
        } else {
            $subscriptionId = $this->subscriptionId++;
            $this->subscribes[$destination] = $subscriptionId;
        }
        $frame = new Frame('SUBSCRIBE');
        $frame->addHeader('destination',$destination);
        if($this->protocolVersion >= '1.1')
            $frame->addHeader('id',$subscriptionId);
        if($this->browseMode) {
            // for ActiveMQ
            $frame->addHeader('browser','true');
        } else {
            if($this->ackMode===true || $this->ackMode==='client')
                $frame->addHeader('ack','client');
            else if($this->ackMode==='client-individual')
                $frame->addHeader('ack','client-individual');
            else
                $frame->addHeader('ack','auto');
        }
        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        return $frame;
    }

    public function unsubscribe($destination, array $properties=null, $sync=null)
    {
if(self::$debug)
    fputs(STDERR,"unsubscribe({$destination})");
        if(!isset($this->subscribes[$destination])) {
            throw new Exception\DomainException('not be subscribed:'.$destination);
        }
        $frame = new Frame('UNSUBSCRIBE');
        if($this->protocolVersion >= '1.1')
            $frame->addHeader('id',$this->subscribes[$destination]);
        else
            $frame->addHeader('destination',$destination);
        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        unset($this->subscribes[$destination]);
        return $frame;
    }

    public function begin($transactionId, array $properties=null, $sync=null)
    {
        $frame = new Frame('BEGIN');
        $frame->addHeader('transaction',$transactionId);
        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        return $frame;
    }

    public function commit($transactionId, array $properties=null, $sync=null)
    {
        $frame = new Frame('COMMIT');
        $frame->addHeader('transaction',$transactionId);
        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        return $frame;
    }

    public function abort($transactionId, array $properties=null, $sync=null)
    {
        $frame = new Frame('ABORT');
        $frame->addHeader('transaction',$transactionId);
        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        return $frame;
    }

    public function ack(Frame $message, $transactionId = null, array $properties=null, $sync=null)
    {
        $frame = new Frame('ACK');
        if($this->protocolVersion === '1.1')
            $frame->addHeader('subscription',$message->getHeader('subscription'));

        $frame->addHeader('message-id',$message->getHeader('message-id'));

        if($this->protocolVersion >= '1.2')
            $frame->addHeader('id',$message->getHeader('ack'));

        if($transactionId)
            $frame->addHeader('transaction',$transactionId);

        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        return $frame;
    }

    public function nack(Frame $message, $transactionId = null, array $properties=null, $sync=null)
    {
        if($this->protocolVersion === '1.0')
            throw new Exception\DomainException('nack is not supported in STOMP protocol version 1.0');

        $frame = new Frame('NACK');
        if($this->protocolVersion === '1.1')
            $frame->addHeader('subscription',$message->getHeader('subscription'));

        $frame->addHeader('message-id',$message->getHeader('message-id'));

        if($this->protocolVersion >= '1.2')
            $frame->addHeader('id',$message->getHeader('ack'));

        if($transactionId)
            $frame->addHeader('transaction',$transactionId);

        if($properties)
            $frame->addHeaders($properties);
        $this->writePhyscalFrame($frame);
        return $frame;
    }

    public function disconnect(array $properties=null, $sync=null)
    {
        $frame = new Frame('DISCONNECT');
        if($properties)
            $frame->addHeaders($properties);
        $this->writeFrame($frame,$sync);
        $a = fclose($this->socket);
        $this->socket = null;
        return $frame;
    }

    public function readFrame($readTimeout=null,$newFrame=null)
    {
if(self::$debug)
    fputs(STDERR,"readFrame()");
        if(count($this->stockMessages)) {
            $frame = array_shift($this->stockMessages);
        } else {
            $frame = $this->readPhyscalFrame($readTimeout,$newFrame);
            if($frame===false) {
if(self::$debug)
    fputs(STDERR,"readFrame(empty)");
                return false;
            }
        }
        if($frame->getCommand()!=='MESSAGE')
            throw new Exception\DomainException('invalid message "'.$frame->getCommand().'": '.$frame->getBody());
if(self::$debug)
    fputs(STDERR,"readFrame(read)");
        return $frame;
    }

    protected function writeFrame(Frame $frame,$sync)
    {
        if($sync===null)
            $sync = $this->receiptMode;
        if($sync) {
            $receiptId = 'msg-'.($this->receiptId++);
            $frame->addHeader('receipt', $receiptId);
        }
        $this->writePhyscalFrame($frame);
        if($sync) {
            while(true) {
                $response = $this->readPhyscalFrame();
                if($response===false)
                    throw new Exception\DomainException('no receipt');
                if($response->getCommand()=='RECEIPT')
                    break;
                if($response->getCommand()=='ERROR')
                    throw new Exception\DomainException('protocol error:'.$response->getBody());
                $this->stockMessages[] = $response;
            }
            if($response->getHeader('receipt-id') != $receiptId)
                throw new Exception\DomainException("receipt id unmatch.");
        }
    }

    protected function writePhyscalFrame(Frame $frame)
    {
        $this->assertConnect();

        $data = $frame->getCommand() . "\n";
        foreach ($frame->getHeaders() as $name => $value) {
            if($this->encodedHeader) {
                $value = str_replace(array('\\',"\r","\n",':'), array('\\\\','\\r','\\n','\\c'),  $value);
            }
            $data .= $name . ":" . $value . "\n";
        }
        $data .= "\n";
        $body = $frame->getBody();
        if($body)
            $data .= $body;
        $data .= "\x00";
        $length = strlen($data);
        $n = fwrite($this->socket, $data, $length);
        if($n===false)
            throw new Exception\RuntimeException("Error in writing to connection.");
        if($n!==$length)
            throw new Exception\RuntimeException("unmatch writen length.");
    }

    public function hasFrame()
    {
        if(count($this->stockMessages)>0)
            return true;
        return $this->hasPhyscalFrameToRead(1);
    }

    protected function hasPhyscalFrameToRead($readTimeout=null)
    {
        $this->assertConnect();
        $reads = array($this->socket);
        $writes = array();
        $except = array();
        if($readTimeout===null) {
            $tv_sec = $this->readTimeoutSec;
            $tv_usec = $this->readTimeoutMicroSec;
        } else {
            if(is_array($readTimeout)) {
                $tv_sec =  $readTimeout[0];
                $tv_usec = $readTimeout[1];
            } else {
                $tv_sec = $readTimeout;
                $tv_usec = 0;
            }
        }
        $num = stream_select($reads , $writes , $except , $tv_sec, $tv_usec);
        if($num===false) {
            throw new Exception\DomainException("select error");
        }
        if($num==0)
            return false;

        return true;
    }

    protected function readPhyscalFrame($readTimeout=null,$newFrame=null)
    {
        if(!$this->hasPhyscalFrameToRead($readTimeout))
            return false;

        $command = stream_get_line($this->socket, $max=8192, "\n");
        if($command===false)
            throw new Exception\RuntimeException("read error in command.");
        $headers = array();
        while(true) {
            $line = stream_get_line($this->socket, $max=8192, "\n");
            if($line===false)
                throw new Exception\RuntimeException("read error in headers.");
            if($line=='')
                break;
            $parts = explode(':',$line);
            $name  = strtolower(array_shift($parts));
            if($this->encodedHeader) {
                if(!isset($parts[0])) {
                    throw new Exception\RuntimeException('protocol error: header format');
                }
                $value = str_replace(array('\\\\','\\r','\\n','\\c'), array('\\',"\r","\n",':'), $parts[0]);
            } else {
                $value = implode(':',$parts);
            }
            $headers[$name] = $value;
        }

        $body = null;
        if(isset($headers['content-length'])) {
            $length = 0;
            $contentLength = intval($headers['content-length']);
            while(true) {
                $max = $contentLength - $length;
                $body .= $block = fread($this->socket, $max);
                if($block===false)
                    throw new Exception\RuntimeException("read error in content.");
                $length += strlen($block);
                if($length >= $contentLength) {
                    $block = fread($this->socket, 2);
                    if($block!=="\x00\n")
                        throw new Exception\RuntimeException("end of content is not found.");
                    break;
                }
            }
        } else {
            while(true) {
                $body .= $line = stream_get_line($this->socket, $max=8192, "\x00\n");
                if($line===false)
                    throw new Exception\RuntimeException("read error in content.");
                if(strlen($line)<8192)
                    break;
            }
        }
        if($command==='ERROR') {
            if(isset($headers['message'])) {
                $error = $headers['message'].'('.$body.')';
            } else {
                $error = $body;
            }
            throw new Exception\RuntimeException('Error: '.$error);
        }
        if($newFrame===null) {
            $newFrame = new Frame($command,$headers,$body);
        } else {
            $newFrame->setCommand($command);
            $newFrame->addHeaders($headers);
            $newFrame->setBody($body);
        }
        return  $newFrame;
    }
}