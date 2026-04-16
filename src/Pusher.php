<?php

namespace Chumoe\Napcat;

use Webman\Event\Event;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

class Pusher
{
    public function onConnect(TcpConnection $connection)
    {
    }

    public function onWebSocketConnect(TcpConnection $connection, Request $http_buffer): void
    {
        $authorization = config('plugin.chumoe.napcat.app.authorization');
        if (!empty($authorization)
            && preg_match('/^Bearer\s+(.+)$/i', $http_buffer->header('Authorization'), $matches)
            && $matches[1] !== $authorization) {
            echo "Authorization error.\n";
        } else {
            Timer::add(1, function (Queue $queue) use (&$connection) {
                if (!$queue->isEmpty()) {
                    $connection->send($queue->dequeue());
                }
            }, ['queue' => Queue::getInstance('napcat')]);
        }
    }

    public function onMessage(TcpConnection $connection, mixed $data): void
    {
        if (is_json($data, $msg)) {
            if (isset($msg['post_type'])) {
                $event_name = match ($msg['post_type']) {
                    'message', 'message_sent' => 'napcat.message',
                    'notice' => 'napcat.notice',
                    'request' => 'napcat.request',
                    default => 'napcat.default'
                };
                $msg['connection'] = $connection;
                Event::emit($event_name, $msg);
            } else if (isset($msg['status']) && isset($msg['echo'])) {
                Napcat::getInstance()->message($msg);
            }
        }
    }

    public function onClose(TcpConnection $connection)
    {
    }
}
