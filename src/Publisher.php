<?php

namespace Linjoe\Amqp;

use PhpAmqpLib\Message\AMQPMessage;

class Publisher
{
    public static function publish(
        string $queue,
        string $payload,
        array $options = [],
        string $connection = 'default'
    ): void {
        $config = config("rabbitmq.connections.$connection");
        $conn   = ConnectionManager::get($connection, $config);
        $channel = $conn->channel();

        // 从配置获取队列参数，优先使用传入 options 覆盖
        $queueConfig = config("rabbitmq.queues.$queue", []);

        $exchange      = $queueConfig['exchange'] ?? ($options['exchange'] ?? 'default-exchange');
        $exchangeType  = $queueConfig['exchange_type'] ?? ($options['exchange_type'] ?? 'direct');
        $routingKey    = $queueConfig['routing_key'] ?? ($options['routing_key'] ?? $queue);

        $declareOptions = array_merge($queueConfig, $options);

        $deliveryMode = $declareOptions['delivery_mode'] ?? 2; // 默认持久化消息

        $msg = new AMQPMessage($payload, [
            'delivery_mode' => $deliveryMode,
            'content_type' => $declareOptions['content_type'] ?? 'text/plain',
        ]);

        // 统一声明队列和交换机，传入全部声明参数，避免冲突
        QueueDeclarator::declare(
            $channel,
            $queue,
            $exchange,
            $routingKey,
            $declareOptions
        );

        $channel->basic_publish($msg, $exchange, $routingKey);

        $channel->close();
    }
}
