<?php
namespace RindowTest\Stomp\Messaging;

use Rindow\Messaging\MessageHandler;

class TestMessageHandler implements MessageHandler
{
    public function setMessagingTemplate($messagingTemplate)
    {
        $this->messagingTemplate = $messagingTemplate;
    }

    public function handleMessage(/*Message*/ $message)
    {
        echo $this->messagingTemplate->getMessageConverter()->fromMessage($message)."\n";
        //throw new \Exception("Error Processing Request", 1);
    }
}
