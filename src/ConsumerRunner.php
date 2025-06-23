<?php

namespace Linjoe\Amqp;

use PhpAmqpLib\Message\AMQPMessage;
use support\Log;

class ConsumerRunner
{
    protected array $consumers = [];

    public function __construct(array $consumers = [])
    {
        // key 是队列名，value 是对应消费者类
        $this->consumers = $consumers ?: [
            'email.send' => \app\Consumer\EmailConsumer::class,
            // 更多队列 => 消费者类映射...
        ];
    }

    public function onWorkerStart(): void
    {
        foreach ($this->consumers as $queue => $consumerClass) {
            \Workerman\Timer::add(0.001, function () use ($queue, $consumerClass) {
                $this->startConsume($queue, $consumerClass);
            }, [], false);
        }
    }

    protected function startConsume(string $queue, string $consumerClass): void
    {
        $consumer = new $consumerClass();

        $config     = config('rabbitmq.connections.default');
        $connection = ConnectionManager::get('default', $config);
        $channel    = $connection->channel();

        // 从配置获取声明参数
        $queueConfig = config("rabbitmq.queues.$queue", []);

        $exchange     = $queueConfig['exchange'] ?? 'default-exchange';
        $exchangeType = $queueConfig['exchange_type'] ?? 'direct';
        $routingKey   = $queueConfig['routing_key'] ?? $queue;

        // 统一声明队列和交换机，参数完全保持一致
        QueueDeclarator::declare(
            $channel,
            $queue,
            $exchange,
            $routingKey,
            $queueConfig
        );

        $channel->basic_qos(0, 1, false); // 一次只推送一个任务

        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $msg) use ($consumer) {
                try {
                    $consumer->consume($msg);
                } catch (\Throwable $e) {
                    Log::error("AMQP消费异常：" . $e->getMessage());
                }
            }
        );

        while (count($channel->callbacks)) {
            try {
                $channel->wait();
            } catch (\Throwable $e) {
                Log::error("AMQP消费循环异常 ({$queue}): " . $e->getMessage());
                break;
            }
        }
    }
}
