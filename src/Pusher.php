<?php

namespace Chumoe\Napcat;

use support\Cache;
use support\Log;
use Webman\Event\Event;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;

class Pusher
{
    private int $timer_id;
    public function onWebSocketConnect(TcpConnection $connection, Request $http_buffer): void
    {
        $authorization = config('plugin.chumoe.napcat.app.authorization');
        $result = preg_match('/^Bearer\s+(.+)$/i', $http_buffer->header('Authorization', ''), $matches);
        if (empty($authorization) || ($result && $matches[1] == $authorization)) {
            $this->timer_id = Timer::add(1, function (Queue $queue) use (&$connection) {
                if (!$queue->isEmpty()) {
                    $connection->send($queue->dequeue());
                }
            }, ['queue' => Queue::getInstance('napcat.messages')]);
            Cache::set('napcat.run', true);
            echo "Napcat Connected.\r\n";
        } else {
            Log::error("Napcat Unauthorized access.\r\n");
            $connection->close();
        }
    }

    public function onMessage(TcpConnection $connection, mixed $data): void
    {
        if (!is_json($data, $msg)) {
            return;
        }
        if (isset($msg['post_type'])) {
            // 处理心跳事件
            if ($msg['post_type'] == 'meta_events' && isset($msg['meta_event_type']) && $msg['meta_event_type'] == 'heartbeat') {
                Cache::set('napcat.run', $msg['online'] ?: false);
            }
            // 处理事件分发
            $event_name = match ($msg['post_type']) {
                'meta_events' => 'napcat.meta_events',
                'message', 'message_sent' => 'napcat.message',
                'notice' => 'napcat.notice',
                'request' => 'napcat.request',
                default => 'napcat.default'
            };
            $msg['connection'] = $connection;
            Event::emit($event_name, $msg);
        } else if (isset($msg['status']) && isset($msg['echo'])) {
            if ($msg['status'] != 'ok') {
                Log::warning("Napcat error: " . print_r($msg, true) . "\r\n");
            }
            Napcat::getInstance()->message($msg);
        }
    }

    public function onClose(TcpConnection $connection): void
    {
        Timer::del($this->timer_id);
        Cache::set('napcat.run', false);
        echo "Napcat connection closed.\r\n";
    }
}
