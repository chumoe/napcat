<?php

namespace Chumoe\Napcat;

use support\Cache;

/**
 * 队列类，实现了一个简单的队列数据结构，使用单例模式管理多个队列实例
 */
class Queue
{
    /**
     * 队列的键名，用于在缓存中标识队列
     * @var string
     */
    private string $key;
    /**
     * 队列的最大容量
     * @var int
     */
    private int $maxSize;
    /**
     * 存储队列实例的静态数组，实现单例模式
     * @var array
     */
    private static array $instances = [];

    /**
     * 私有构造函数，防止直接实例化
     * @param string $key 队列的键名
     * @param int $maxSize 队列的最大容量
     */
    private function __construct(string $key, int $maxSize)
    {
        $this->key = $key;
        $this->maxSize = $maxSize;
    }

    /**
     * 获取队列实例的单例方法
     * @param string $key 队列的键名，默认为'queue'
     * @param int $maxSize 队列的最大容量，默认为100
     * @return self 返回队列实例
     */
    public static function getInstance(string $key = 'queue', int $maxSize = 100): self
    {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self($key, $maxSize);
        }
        return self::$instances[$key];
    }

    /**
     * 向队列中添加消息
     * @param mixed $message 要添加到队列的消息
     * @return void
     */
    public function enqueue(mixed $message): void
    {
        $queue = $this->getQueue();
        if (count($queue) >= $this->maxSize) {
            array_shift($queue); // 如果队列已满，移除最旧的元素
        }
        $queue[] = $message; // 将新消息添加到队列末尾
        $this->saveQueue($queue);
    }

    /**
     * 从队列中取出消息
     * @return mixed|null 返回队列中的第一个消息，如果队列为空则返回null
     */
    public function dequeue(): mixed
    {
        $queue = $this->getQueue();
        if (empty($queue)) {
            return null; // 队列空
        }
        $message = array_shift($queue); // 移除并返回队列中的第一个元素
        $this->saveQueue($queue);
        return $message;
    }

    /**
     * 检查队列是否为空
     * @return bool 如果队列为空返回true，否则返回false
     */
    public function isEmpty(): bool
    {
        return empty($this->getQueue());
    }

    /**
     * 从缓存中获取队列数据
     * @return array 返回队列数组
     */
    private function getQueue(): array
    {
        return Cache::get($this->key, []);
    }

    /**
     * 将队列数据保存到缓存中
     * @param array $queue 要保存的队列数据
     * @return void
     */
    private function saveQueue(array $queue): void
    {
        Cache::set($this->key, $queue);
    }
}
