<?php
return array(
    'module_manager' => array(
        'version' => 1,
        'modules' => array(
            'Rindow\\Module\\Stomp\\Module' => true,
            'RindowTest\\Stomp\\Messaging\\TestMessageSenderModule' => true,
        ),
        'autorun' => 'RindowTest\\Stomp\\Messaging\\TestMessageSenderModule',
    ),
    'cache' => array(
        'enableFileCache' => false,
    ),
    'messaging'=> array(
        'handler'=>array(
            'source' => '/queue/dummy',
            //'timeout' => 100,
            'subscribe' => array(
                '/queue/fooDest',
                '/queue/fooDest2',
            ),
        ),
        'sender'=>array(
            //'queueName' => '/queue/fooDest',
            'queueName' => '/queue/fooDest2',
            //'timeout' => 100,
        ),
    ),
);
