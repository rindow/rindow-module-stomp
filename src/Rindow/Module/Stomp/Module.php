<?php
namespace Rindow\Module\Stomp;

class Module
{
    public function getConfig()
    {
        return array(
            'container' => array(
                'aliases' => array(
                    'Rindow\\Messaging\\DefaultMessageSession' => 'Rindow\\Module\\Stomp\\Messaging\\Session',
                ),
                'components' => array(
                    'Rindow\\Module\\Stomp\\Messaging\\Session' => array(
                        'properties' => array(
                            'driver'       => array('ref'=>'Rindow\\Module\\Stomp\\Stomp'),
                        ),
                    ),
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultDestinationResolver' => array(
                        'class' => 'Rindow\\Module\\Stomp\\Messaging\\DestinationResolver',
                        'properties' => array(
                            'messageFactory' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultMessageFactory'),
                            'transactionManager' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultTransactionManager'),
                        ),
                    ),
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultMessageFactory' => array(
                        'class'=>'Rindow\\Messaging\\Support\\MessageFactory',
                    ),
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultTransactionManager' => array(
                        'class' => 'Rindow\\Transaction\\Local\\TransactionManager',
                    ),
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultMessageHandlerApplication' => array(
                        'class' => 'Rindow\\Module\\Stomp\\Messaging\\MessageHandlerApplication',
                        'properties' => array(
                            'destinationResolver' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultDestinationResolver'),
                            'transactionBoundary' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultTransactionBoundary'),
                        ),
                    ),
                    'Rindow\\Module\\Stomp\\Messaging\\DefaultTransactionBoundary' => array(
                        'class' => 'Rindow\\Transaction\\Support\\TransactionBoundary',
                        'properties' => array(
                            'transactionManager' => array('ref'=>'Rindow\\Module\\Stomp\\Messaging\\DefaultTransactionManager'),
                        ),
                        'proxy' => 'disable',
                    ),
                ),
            ),
        );
    }

    public function invoke($moduleManager)
    {
        $app = $moduleManager->getServiceLocator()
            ->get('Rindow\\Module\\Stomp\\Messaging\\DefaultMessageHandlerApplication');
        try {
            $app->run();
            $app->getDestinationResolver()->getResourceManager()->close();
        } catch(\Exception $e) {
            $app->getDestinationResolver()->getResourceManager()->close();
            throw $e;
        }
    }
}
