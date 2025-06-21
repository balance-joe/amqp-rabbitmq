<?php
namespace Linjoe\Amqp;

use PhpAmqpLib\Message\AMQPMessage;

class Publisher
{
    public static function publish(
        string $exchange,
        string $routingKey,
        string $payload,
        array $options = [],
        string $connection = 'default'
    ): void {
        $config = config("rabbitmq.connections.$connection");
        $conn   = ConnectionManager::get($connection, $config);
        $channel = $conn->channel();

        $deliveryMode = $options['delivery_mode'] ?? 2;
        $properties   = $options['properties'] ?? [];

        // 构造 AMQP 消息对象
        $msg = new AMQPMessage($payload, array_merge([
            'delivery_mode' => $deliveryMode,
        ], $properties));

        // 声明交换机（type: direct，持久化）
        $channel->exchange_declare($exchange, 'direct', false, true, false);

        // 声明队列 + 绑定（routingKey == queue）
        $channel->queue_declare($routingKey, false, true, false, false);
        $channel->queue_bind($routingKey, $exchange, $routingKey);

        // 发布消息
        $channel->basic_publish($msg, $exchange, $routingKey);

        // 关闭 channel，连接复用
        $channel->close();
    }
}
