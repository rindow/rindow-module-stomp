<?php
namespace RindowTest\Stomp\Messaging;

class TestMessageHandlerModule
{
    public function getConfig()
    {
        return array(
            'container' => array(
                'components' => array(
                    'RindowTest\\Stomp\\Messaging\\TestMessageHandler' => array(
                        'properties' => array(
                            'messagingTemplate' => array('ref'=>'RindowTest\\Stomp\\Messaging\\HandlerMessageTemplate'),
                        ),
                    ),
                    'RindowTest\\Stomp\\Messaging\\HandlerMessageTemplate' => array(
                        'class' => 'Rindow\\Messaging\\Core\\GenericMessagingTemplate',
                        'properties' => array(
                            'destinationResolver' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultDestinationResolver'),
                        ),
                    ),
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultMessageHandlerApplication' => array(
                        'properties' => array(
                            'handler' => array('ref'=>'RindowTest\\Stomp\\Messaging\\TestMessageHandler'),
                            'config' => array('config'=>'messaging::handler'),
                        ),
                    ),
                ),
            ),
        );
    }
}
