<?php

return [
    'pusher' => [
        'handler' => Chumoe\Napcat\Pusher::class,
        'listen' => 'websocket://0.0.0.0:8082',
        'count' => 1,
    ]
];
