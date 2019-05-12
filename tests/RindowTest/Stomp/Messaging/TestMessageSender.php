<?php
namespace RindowTest\Stomp\Messaging;

use Interop\Lenient\Messaging\MessageHandler;

class TestMessageSender
{
    protected $messagingTemplate;
    protected $queueName;

    public function setMessagingTemplate($messagingTemplate)
    {
        $this->messagingTemplate = $messagingTemplate;
    }

    public function setQueueName($queueName)
    {
        $this->queueName = $queueName;
    }

    public function run()
    {
        $this->messagingTemplate->convertAndSend($this->queueName,'Foo');
    }
}
