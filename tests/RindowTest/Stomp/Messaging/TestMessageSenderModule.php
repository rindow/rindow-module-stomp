<?php
namespace RindowTest\Stomp\Messaging;

class TestMessageSenderModule
{
    public function getConfig()
    {
        return array(
            'container' => array(
                'components' => array(
                    'RindowTest\\Stomp\\Messaging\\TestMessageSender' => array(
                        'properties' => array(
                            'messagingTemplate' => array('ref'=>'RindowTest\\Stomp\\Messaging\\SenderMessageTemplate'),
                            'queueName' => array('config'=>'messaging::sender::queueName'),
                        ),
                    ),
                    'RindowTest\\Stomp\\Messaging\\SenderMessageTemplate' => array(
                        'class' => 'Rindow\\Messaging\\Core\\GenericMessagingTemplate',
                        'properties' => array(
                            'destinationResolver' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultDestinationResolver'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function invoke($moduleManager)
    {
        $app = $moduleManager->getServiceLocator()
            ->get('RindowTest\\Stomp\\Messaging\\TestMessageSender');
        $app->run();
    }
}
